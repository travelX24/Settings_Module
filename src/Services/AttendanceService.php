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
    public function ensureLog(int $companyId, int $employeeId, string $date, $schedule = null, $holidays = null, bool $force = false)
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
            // Fall through → save() → calculateStatus() → auto-checkout applied
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

        // Saving triggers syncWithSchedule and other calculations in the model
        $log->save();

        return $log;
    }

    /**
     * Record a check-in event.
     */
    public function recordCheckIn(AttendanceDailyLog $log, string $method, ?float $lat, ?float $lng, $schedule, int $lateGraceMins = 15, string $trackingMode = "check_in_out"): array
    {
        $now = now();
        $dateStr = Carbon::parse($log->attendance_date)->toDateString();
        $nowTime = $now->format('H:i:s');

        $status = 'present';
        $matchedPeriodId = null;
        $matchedPeriodEndTime = null;

        $isWithinPeriod = false;

        if ($schedule && $schedule->periods) {
            foreach ($schedule->periods as $p) {
                $pStartAllowed = Carbon::parse($dateStr . " " . substr((string)$p->start_time, 0, 5))->subMinutes(30);
                $pEnd = Carbon::parse($dateStr . " " . substr((string)$p->end_time, 0, 5));
                
                if ($now->between($pStartAllowed, $pEnd)) {
                    $matchedPeriodId = $p->id;
                    $matchedPeriodEndTime = $p->end_time;
                    $isWithinPeriod = true;

                    // Late calculation
                    $realStart = Carbon::parse($dateStr . " " . substr((string)$p->start_time, 0, 5));
                    if ($now->greaterThan($realStart->addMinutes($lateGraceMins))) {
                        $status = 'late';
                    }
                    break;
                }
            }
        }

        // ─── Check & Auto-Close open sessions ──────────────────────────────
        // This MUST happen before the too_early_for_checkin gate so that an
        // employee who forgot to check out from period 1 can still trigger
        // check-in for period 2 (or get unblocked between periods).
        $openSession = DB::table('attendance_daily_details')
            ->where('daily_log_id', $log->id)
            ->whereNull('check_out_time')
            ->first();

        if ($openSession) {
            $shouldAutoClose = false;

            // Case 1: We matched a NEW period — the open session is from a different one
            if ($matchedPeriodId && (int)$openSession->work_schedule_period_id !== (int)$matchedPeriodId) {
                $shouldAutoClose = true;
            }

            // Case 2: We're NOT within any period window BUT the open session's period has already ended
            // (employee is stuck between periods — auto-close the old session)
            if (!$isWithinPeriod && !$shouldAutoClose && $openSession->work_schedule_period_id) {
                $openPeriodRow = DB::table('work_schedule_periods')
                    ->where('id', $openSession->work_schedule_period_id)
                    ->first();
                if ($openPeriodRow) {
                    $openPeriodEnd = Carbon::parse($dateStr . " " . substr((string)$openPeriodRow->end_time, 0, 5));
                    if ($now->gt($openPeriodEnd)) {
                        $shouldAutoClose = true;
                    }
                }
            }

            if ($shouldAutoClose) {
                $prevPeriod = DB::table('work_schedule_periods')
                    ->where('id', $openSession->work_schedule_period_id)
                    ->first();

                // ─── Smart Auto-Checkout Time Calculation ───────────────────────
                // Fetch the company's auto-checkout limit (stored in hours despite the column name)
                $grace = AttendanceGraceSetting::where('saas_company_id', $log->saas_company_id)->first();
                $limitHours   = (int) ($grace->auto_checkout_after_minutes ?? 2);
                $limitMinutes = $limitHours * 60;

                $autoOutMethod = 'fallback';
                if ($prevPeriod) {
                    $prevPeriodEnd  = Carbon::parse($dateStr . ' ' . substr((string) $prevPeriod->end_time, 0, 5));
                    // Break = time elapsed since Period-1 ended (always positive here)
                    $breakMinutes = (int) $prevPeriodEnd->diffInMinutes($now);

                    if ($breakMinutes < $limitMinutes) {
                        // Case 1: Break < limit → employee returned within the window.
                        // Auto-checkout = moment the employee checked in for Period 2.
                        $autoOutTime = $nowTime;
                        $autoOutMethod = 'next_period_checkin';
                    } else {
                        // Case 2: Break ≥ limit → cap the session at Period-1 end + limit.
                        $autoOutTime = $prevPeriodEnd->copy()->addHours($limitHours)->format('H:i:s');
                        $autoOutMethod = 'period_end_plus_limit';
                    }
                } else {
                    // No period record found — fallback to scheduled checkout or now
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
                $openSession = null; // session closed — proceed with check-in
            } else {
                // Session is still active for the CURRENT period — block duplicate check-in
                return ['ok' => false, 'code' => 'already_checked_in', 'message' => tr('You already have an open attendance session.')];
            }
        }

        // ─── Period window gate (after auto-close) ─────────────────────────
        if (!$isWithinPeriod) {
            return ['ok' => false, 'code' => 'too_early_for_checkin', 'message' => tr('You cannot check-in more than 30 minutes before the period starts.')];
        }

        // One more check: has this period already been used?
        if ($matchedPeriodId) {
            $alreadyUsed = DB::table('attendance_daily_details')
                ->where('daily_log_id', $log->id)
                ->where('work_schedule_period_id', $matchedPeriodId)
                ->exists();

            if ($alreadyUsed) {
                return ['ok' => false, 'code' => 'period_already_completed', 'message' => tr('You have already completed attendance registration for this period.')];
            }
        }

        // Insert Detail
        $detailId = DB::table('attendance_daily_details')->insertGetId([
            'daily_log_id' => $log->id,
            'work_schedule_period_id' => $matchedPeriodId,
            'check_in_time' => $nowTime,
            'attendance_status' => $status,
            'meta_data' => json_encode(['method' => $method, 'lat' => $lat, 'lng' => $lng], JSON_UNESCAPED_UNICODE),
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
    public function recordCheckOut($log, string $method, ?float $lat, ?float $lng): array
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
                    $periodEndMin = $this->minutesBetween($period->start_time, $period->end_time);
                    $nowFromStart = $this->minutesBetween($period->start_time, $nowTime);
                    if ($nowFromStart >= $periodEndMin) {
                        $canCheckOut = true;
                    }
                }
            }

            if (!$canCheckOut) {
                return [
                    'ok' => false, 
                    'code' => 'checkout_too_early', 
                    'message' => app()->getLocale() === 'ar' 
                        ? 'لا يمكنك تسجيل الانصراف إلا بعد مرور ساعة كاملة من وقت التحضير أو بعد انتهاء فترة الدوام الرسمية.'
                        : 'You cannot check-out within 1 hour of check-in, or before the period ends.'
                ];
            }
        }

        DB::table('attendance_daily_details')->where('id', $openSession->id)->update([
            'check_out_time' => $nowTime,
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
}
