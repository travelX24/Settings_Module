<?php

namespace Athka\SystemSettings\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Athka\Employees\Models\Employee;
use Athka\Attendance\Models\AttendanceDailyLog;
use Athka\Attendance\Models\AttendanceDailyDetail;
use Athka\SystemSettings\Models\AttendanceGraceSetting;

class AttendanceService
{
    protected $scheduleService;

    public function __construct(WorkScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    /**
     * Ensure a daily log exists.
     */
    public function ensureLog(
    int $companyId,
    int $employeeId,
    string $date,
    $schedule = null,
    $holidays = null,
    bool $force = false,
    $requests = null,
)
    {
        $log = AttendanceDailyLog::where('saas_company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date)
            ->first();
        if (!$force && $log && !is_null($log->scheduled_hours) && (float)$log->scheduled_hours > 0) {
            $hasOpenSession = $log->details()
                ->whereNull('check_out_time')
                ->exists();

            if (!$hasOpenSession) {
                return $log;
            }
            // Fall through â†’ save() â†’ calculateStatus() â†’ auto-checkout applied
        }

        if (!$log) {
            $log = new AttendanceDailyLog();
            $log->saas_company_id = $companyId;
            $log->employee_id = $employeeId;
            $log->attendance_date = $date;
            $log->attendance_status = 'absent';
            $log->approval_status = 'pending';
            $log->source = 'automatic';
        }

        // Set pre-fetched parameters on the model before saving to bypass expensive DB queries
        $log->preFetchedHolidays = $holidays;
        $log->preFetchedRequests = $requests;
        $log->preFetchedSchedule = $schedule;

        // Saving triggers syncWithSchedule and other calculations in the model
        $log->save();

return $log;
    }

    /**
     * Record a check-in event.
     */
    public function recordCheckIn(AttendanceDailyLog $log, string $method, ?float $lat, ?float $lng, $schedule, int $lateGraceMins = 15, string $trackingMode = "check_in_out", ?array $locationMeta = null): array
    {
        $now = now();
        $dateStr = Carbon::parse($log->attendance_date)->toDateString();
        $nowTime = $now->format('H:i:s');

        $status = 'present';
        $matchedPeriodId = null;
        $matchedPeriodEndTime = null;
        $matchedPeriodKey = null;
        $matchedPeriodIsLeave = false;
        $matchedPeriodLeaveName = null;

        $isWithinPeriod = false;

        foreach ($this->resolveCheckInPeriods($log, $schedule, $dateStr) as $p) {
            $startTime = substr((string) $p['start_time'], 0, 5);
            $endTime = substr((string) $p['end_time'], 0, 5);
            if (!$startTime || !$endTime) {
                continue;
            }

            $realStart = Carbon::parse($dateStr . " " . $startTime);
            $pStartAllowed = $realStart->copy()->subMinutes(30);
            $pEnd = Carbon::parse($dateStr . " " . $endTime);

            if (!empty($p['is_night_shift']) || $pEnd->lt($realStart)) {
                $pEnd->addDay();
            }

            if ($now->between($pStartAllowed, $pEnd)) {
                $matchedPeriodId = $p['work_schedule_period_id'];
                $matchedPeriodEndTime = $endTime;
                $matchedPeriodKey = $p['period_key'];
                $matchedPeriodIsLeave = (bool) ($p['is_leave'] ?? false);
                $matchedPeriodLeaveName = $p['leave_name'] ?? null;
                $isWithinPeriod = true;

                if ($now->greaterThan($realStart->copy()->addMinutes($lateGraceMins))) {
                    $status = 'late';
                }
                break;
            }
        }

        // â”€â”€â”€ Check & Auto-Close open sessions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        // This MUST happen before the too_early_for_checkin gate so that an
        // employee who forgot to check out from period 1 can still trigger
        // check-in for period 2 (or get unblocked between periods).
        $openSession = DB::table('attendance_daily_details')
            ->where('daily_log_id', $log->id)
            ->whereNull('check_out_time')
            ->first();

        if ($openSession) {
            $shouldAutoClose = false;
            $openPeriodKey = $this->periodKeyFromDetail($openSession);

            // Case 1: We matched a NEW period â€” the open session is from a different one
            if ($matchedPeriodId && (int)$openSession->work_schedule_period_id !== (int)$matchedPeriodId) {
                $shouldAutoClose = true;
            }

            if (!$shouldAutoClose && $matchedPeriodKey && $openPeriodKey && $openPeriodKey !== $matchedPeriodKey) {
                $shouldAutoClose = true;
            }

            // Case 2: We're NOT within any period window BUT the open session's period has already ended
            // (employee is stuck between periods â€” auto-close the old session)
            if (!$isWithinPeriod && !$shouldAutoClose) {
                $openPeriodEndTime = null;

                if ($openSession->work_schedule_period_id) {
                    $openPeriodRow = DB::table('work_schedule_periods')
                        ->where('id', $openSession->work_schedule_period_id)
                        ->first();
                    $openPeriodEndTime = $openPeriodRow?->end_time;
                }

                $openPeriodEndTime = $openPeriodEndTime ?: $this->periodEndTimeFromDetail($openSession);

                if ($openPeriodEndTime) {
                    $openPeriodEnd = Carbon::parse($dateStr . " " . substr((string)$openPeriodEndTime, 0, 5));
                    if ($now->gt($openPeriodEnd)) $shouldAutoClose = true;
                }
            }

            if ($shouldAutoClose) {
                $prevPeriod = DB::table('work_schedule_periods')
                    ->where('id', $openSession->work_schedule_period_id)
                    ->first();

                // â”€â”€â”€ Smart Auto-Checkout Time Calculation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                // Fetch the company's auto-checkout limit (stored in hours despite the column name)
                $grace = AttendanceGraceSetting::where('saas_company_id', $log->saas_company_id)->first();
                $limitHours   = (int) ($grace->auto_checkout_after_minutes ?? 2);
                $limitMinutes = $limitHours * 60;

                $autoOutMethod = 'fallback';
                $prevPeriodEndTime = $prevPeriod?->end_time ?: $this->periodEndTimeFromDetail($openSession);
                if ($prevPeriodEndTime) {
                    $prevPeriodEnd  = Carbon::parse($dateStr . ' ' . substr((string) $prevPeriodEndTime, 0, 5));
                    // Break = time elapsed since Period-1 ended (always positive here)
                    $breakMinutes = (int) $prevPeriodEnd->diffInMinutes($now);

                    if ($breakMinutes < $limitMinutes) {
                        // Case 1: Break < limit â†’ employee returned within the window.
                        // Auto-checkout = moment the employee checked in for Period 2.
                        $autoOutTime = $nowTime;
                        $autoOutMethod = 'next_period_checkin';
                    } else {
                        // Case 2: Break â‰¥ limit â†’ cap the session at Period-1 end + limit.
                        $autoOutTime = $prevPeriodEnd->copy()->addHours($limitHours)->format('H:i:s');
                        $autoOutMethod = 'period_end_plus_limit';
                    }
                } else {
                    // No period record found â€” fallback to scheduled checkout or now
                    $autoOutTime = $log->scheduled_check_out ?? $nowTime;
                }

                DB::table('attendance_daily_details')->where('id', $openSession->id)->update([
                    'check_out_time' => $autoOutTime,
                    'meta_data'      => json_encode(array_merge(
                        json_decode($openSession->meta_data ?? '{}', true),
                        [
                            'auto_closed'      => true,
                            'closed_at_checkin'=> $nowTime,
                            'auto_out_method'  => $autoOutMethod,
                        ]
                    ), JSON_UNESCAPED_UNICODE),
                    'updated_at'     => $now,
                ]);
                $openSession = null; // session closed â€” proceed with check-in
            } else {
                // Session is still active for the CURRENT period â€” block duplicate check-in
                return ['ok' => false, 'code' => 'already_checked_in', 'message' => tr('You already have an open attendance session.')];
            }
        }

        // â”€â”€â”€ Period window gate (after auto-close) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if (!$isWithinPeriod) {
            return ['ok' => false, 'code' => 'too_early_for_checkin', 'message' => tr('You cannot check-in more than 30 minutes before the period starts.')];
        }

        if ($matchedPeriodIsLeave) {
            $leaveLabel = $matchedPeriodLeaveName ?: tr('Leave');
            return ['ok' => false, 'code' => 'on_leave_period', 'message' => tr('You have leave during this work period.') . ' ' . $leaveLabel];
        }

        // One more check: has this period already been used?
        $alreadyUsed = false;
        if ($matchedPeriodId) {
            $alreadyUsed = DB::table('attendance_daily_details')
                ->where('daily_log_id', $log->id)
                ->where('work_schedule_period_id', $matchedPeriodId)
                ->exists();
        } elseif ($matchedPeriodKey) {
            $alreadyUsed = $this->detailPeriodKeyExists((int) $log->id, $matchedPeriodKey);
        }

        if ($alreadyUsed) {
            return ['ok' => false, 'code' => 'period_already_completed', 'message' => tr('You have already completed attendance registration for this period.')];
        }

        // Insert Detail
        $detailId = DB::table('attendance_daily_details')->insertGetId([
            'daily_log_id' => $log->id,
            'work_schedule_period_id' => $matchedPeriodId,
            'check_in_time' => $nowTime,
            'attendance_status' => $status,
            'meta_data' => json_encode(array_merge([
                'method' => $method,
                'lat' => $lat,
                'lng' => $lng,
                'period_key' => $matchedPeriodKey,
                'period_end_time' => $matchedPeriodEndTime,
            ], $locationMeta ?? []), JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Update Main Log
        if (empty($log->check_in_time)) {
            $log->check_in_time = $nowTime;
        }
        
        // Handle automatic check-out if policy is check_in_only
        if ($trackingMode === 'check_in_only') {
            if (!empty($matchedPeriodEndTime)) {
                $checkoutTime = Carbon::parse($dateStr . ' ' . substr((string)$matchedPeriodEndTime, 0, 5))->format('H:i:s');
            } else {
                $checkoutTime = $log->scheduled_check_out ? Carbon::parse($log->scheduled_check_out)->format('H:i:s') : $nowTime;
            }
            
            DB::table('attendance_daily_details')->where('id', $detailId)->update([
                'check_out_time' => $checkoutTime,
                'updated_at' => $now,
            ]);
            $log->check_out_time = $checkoutTime;
        }

        // Force reload details relation to include the auto-closed session before calculation
        $log->unsetRelation('details');

        // Let the model handle status and other calculations
        $log->save();

        return ['ok' => true, 'time' => substr($nowTime, 0, 5), 'status' => $status];
    }

    /**
     * Record a check-out event.
     */
    public function recordCheckOut($log, string $method, ?float $lat, ?float $lng, ?array $locationMeta = null): array
    {
        if (!$log instanceof AttendanceDailyLog) {
            $logId = is_object($log) ? ($log->id ?? null) : $log;
            if ($logId) $log = AttendanceDailyLog::find($logId);
        }

        if (!$log instanceof AttendanceDailyLog) {
            return ['ok' => false, 'code' => 'invalid_log', 'message' => tr('No attendance record found.')];
        }
        $now = now();
        $nowTime = $now->format('H:i:s');

        $openSession = DB::table('attendance_daily_details')
            ->where('daily_log_id', $log->id)
            ->whereNull('check_out_time')
            ->orderByDesc('id')
            ->first();

        if (!$openSession) {
             // Fallback: If no open session, but employee is checking out late, 
             // we look for a detail with the latest check-in time of today that might have been auto-closed
             $openSession = DB::table('attendance_daily_details')
                ->where('daily_log_id', $log->id)
                ->orderByDesc('id')
                ->first();

             if (!$openSession) {
                return ['ok' => false, 'code' => 'no_check_in_record', 'message' => tr('No attendance record found for today. Please register check-in first.')];
             }
        }

        if ($openSession) {
            $canCheckOut = false;
            $elapsedMinutes = $this->minutesBetween($openSession->check_in_time, $nowTime);
            
            if ($elapsedMinutes >= 60) {
                $canCheckOut = true;
            } else if ($openSession->work_schedule_period_id) {
                $period = DB::table('work_schedule_periods')
                    ->where('id', $openSession->work_schedule_period_id)
                    ->first();
                if ($period && !empty($period->end_time)) {
                    $requiredMinutes = $this->minutesBetween($openSession->check_in_time, $period->end_time);
                    if ($elapsedMinutes >= $requiredMinutes) {
                        $canCheckOut = true;
                    }
                }
            }

            if (!$canCheckOut) {
                return [
                    'ok' => false, 
                    'code' => 'checkout_too_early', 
                    'message' => app()->getLocale() === 'ar' 
                        ? 'Ù„Ø§ ÙŠÙ…ÙƒÙ†Ùƒ ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø§Ù†ØµØ±Ø§Ù Ø¥Ù„Ø§ Ø¨Ø¹Ø¯ Ù…Ø±ÙˆØ± Ø³Ø§Ø¹Ø© ÙƒØ§Ù…Ù„Ø© Ù…Ù† ÙˆÙ‚Øª Ø§Ù„ØªØ­Ø¶ÙŠØ± Ø£Ùˆ Ø¨Ø¹Ø¯ Ø§Ù†ØªÙ‡Ø§Ø¡ ÙØªØ±Ø© Ø§Ù„Ø¯ÙˆØ§Ù… Ø§Ù„Ø±Ø³Ù…ÙŠØ©.'
                        : 'You cannot check-out within 1 hour of check-in, or before the period ends.'
                ];
            }
        }

        $checkoutMeta = array_filter([
            'check_out_method' => $method,
            'check_out_lat' => $lat,
            'check_out_lng' => $lng,
            'check_out_is_mocked' => $locationMeta['is_mocked'] ?? null,
            'check_out_gps_accuracy' => $locationMeta['gps_accuracy'] ?? null,
            'check_out_location_captured_at' => $locationMeta['location_captured_at'] ?? null,
        ], fn ($value) => $value !== null);

        DB::table('attendance_daily_details')->where('id', $openSession->id)->update([
            'check_out_time' => $nowTime,
            'meta_data' => json_encode(array_merge(
                json_decode($openSession->meta_data ?? '{}', true) ?: [],
                $checkoutMeta
            ), JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);

        $log->check_out_time = $nowTime;
        // Calculation triggers on save() via Model's calculateActualHours and calculateCompliance
        $log->save();

        // Re-fetch the log to get updated actual_hours and compliance_percentage
        $log->refresh();

        return ['ok' => true, 'time' => substr($nowTime, 0, 5), 'actual_hours' => $log->actual_hours];
    }

    /**
     * Get attendance logs for an employee in a date range.
     */
    public function getLogs(int $companyId, int $employeeId, string $from, string $to)
    {
        return AttendanceDailyLog::where('saas_company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$from, $to])
            ->orderBy('attendance_date', 'asc')
            ->get();
    }

    public function minutesBetween(string $startTime, string $endTime): int
    {
        $st = Carbon::parse($startTime);
        $et = Carbon::parse($endTime);
        if ($et->lt($st)) $et->addDay();
        return (int) $st->diffInMinutes($et);
    }

    private function resolveCheckInPeriods(AttendanceDailyLog $log, $schedule, string $dateStr): array
    {
        if (!$schedule) {
            return [];
        }

        $dayKey = $this->scheduleService->getDayKey(Carbon::parse($dateStr));
        $scheduleExceptions = collect($schedule->exceptions ?? [])
            ->filter(function ($e) use ($dateStr, $dayKey) {
                $specificDate = $e->specific_date ? Carbon::parse($e->specific_date)->toDateString() : null;

                return (bool)($e->is_active ?? true)
                    && (($specificDate && $specificDate === $dateStr) || $e->day_of_week === $dayKey);
            })
            ->values();

        if ($scheduleExceptions->isNotEmpty()) {
            return $scheduleExceptions->map(fn ($e) => [
                'start_time' => $e->start_time,
                'end_time' => $e->end_time,
                'is_night_shift' => (bool)($e->is_night_shift ?? false),
                'work_schedule_period_id' => null,
                'period_key' => 'schedule_exception:' . ($e->id ?? md5($dateStr . $e->start_time . $e->end_time)),
                'is_leave' => false,
                'leave_name' => null,
            ])->all();
        }

        $metrics = $log->tempMetrics;
        if (!$metrics) {
            $employee = $log->employee;
            $holidays = $this->scheduleService->getHolidays((int)$log->saas_company_id, $dateStr, $dateStr);
            $requests = $employee
                ? $this->scheduleService->getEmployeeRequests((int)$employee->id, $dateStr, $dateStr)
                : [];

            $metrics = $this->scheduleService->getMetricsForDate($dateStr, $schedule, $holidays, $employee, $requests);
        }

        $metricsPeriods = collect($metrics['periods'] ?? []);
        if ($metricsPeriods->isNotEmpty()) {
            $basePeriodIds = collect($schedule->periods ?? [])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (string)$id)
                ->all();

            return $metricsPeriods->map(function ($p) use ($basePeriodIds, $dateStr) {
                $id = $p['id'] ?? null;
                $isBasePeriod = $id && in_array((string)$id, $basePeriodIds, true);

                return [
                    'start_time' => $p['start_time'] ?? null,
                    'end_time' => $p['end_time'] ?? null,
                    'is_night_shift' => (bool)($p['is_night_shift'] ?? false),
                    'work_schedule_period_id' => $isBasePeriod ? (int)$id : null,
                    'period_key' => ($isBasePeriod ? 'work_schedule_period:' : 'effective_period:')
                        . ($id ?: md5($dateStr . ($p['start_time'] ?? '') . ($p['end_time'] ?? ''))),
                    'is_leave' => (bool)($p['is_leave'] ?? false),
                    'leave_name' => $p['leave_name'] ?? null,
                ];
            })->all();
        }

        return collect($schedule->periods ?? [])->map(fn ($p) => [
            'start_time' => $p->start_time,
            'end_time' => $p->end_time,
            'is_night_shift' => (bool)($p->is_night_shift ?? false),
            'work_schedule_period_id' => $p->id ?? null,
            'period_key' => 'work_schedule_period:' . ($p->id ?? md5($dateStr . $p->start_time . $p->end_time)),
            'is_leave' => false,
            'leave_name' => null,
        ])->all();
    }

    private function periodKeyFromDetail($detail): ?string
    {
        $meta = json_decode($detail->meta_data ?? '{}', true);
        if (is_array($meta) && !empty($meta['period_key'])) {
            return (string)$meta['period_key'];
        }

        return $detail->work_schedule_period_id ? 'work_schedule_period:' . $detail->work_schedule_period_id : null;
    }

    private function periodEndTimeFromDetail($detail): ?string
    {
        $meta = json_decode($detail->meta_data ?? '{}', true);

        return is_array($meta) && !empty($meta['period_end_time']) ? (string)$meta['period_end_time'] : null;
    }

    private function detailPeriodKeyExists(int $logId, string $periodKey): bool
    {
        return DB::table('attendance_daily_details')
            ->where('daily_log_id', $logId)
            ->get(['meta_data'])
            ->contains(function ($detail) use ($periodKey) {
                $meta = json_decode($detail->meta_data ?? '{}', true);

                return is_array($meta) && ($meta['period_key'] ?? null) === $periodKey;
            });
    }
}
