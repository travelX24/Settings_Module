<?php

namespace Athka\SystemSettings\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Athka\Employees\Models\Employee;

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
    public function ensureLog(int $companyId, int $employeeId, string $date, $schedule = null, $holidays = null)
    {
        $existing = DB::table('attendance_daily_logs')
            ->where('saas_company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($existing && !is_null($existing->scheduled_hours)) {
            return $existing;
        }

        $metrics = $this->scheduleService->getMetricsForDate($date, $schedule, $holidays);

        if ($existing) {
            $update = [
                'work_schedule_id' => $schedule ? $schedule->id : null,
                'scheduled_hours' => $metrics['hours'],
                'scheduled_check_in' => $metrics['check_in'],
                'scheduled_check_out' => $metrics['check_out'],
                'updated_at' => now(),
            ];
            DB::table('attendance_daily_logs')->where('id', $existing->id)->update($update);
            return DB::table('attendance_daily_logs')->where('id', $existing->id)->first();
        }

        $id = DB::table('attendance_daily_logs')->insertGetId([
            'saas_company_id' => $companyId,
            'employee_id' => $employeeId,
            'attendance_date' => $date,
            'work_schedule_id' => $schedule ? $schedule->id : null,
            'scheduled_hours' => $metrics['hours'],
            'scheduled_check_in' => $metrics['check_in'],
            'scheduled_check_out' => $metrics['check_out'],
            'attendance_status' => ($metrics['hours'] === null) ? 'day_off' : 'absent',
            'approval_status' => 'pending',
            'source' => 'automatic',
            'meta_data' => json_encode([
                'generated_by' => 'service',
                'is_holiday' => $metrics['is_holiday'],
                'day_key' => $metrics['day_key'],
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('attendance_daily_logs')->where('id', $id)->first();
    }

    /**
     * Record a check-in event.
     */
    public function recordCheckIn(object $log, string $method, ?float $lat, ?float $lng, $schedule, int $lateGraceMins = 15): array
    {
        $now = now();
        $dateStr = $log->attendance_date;
        $nowTime = $now->format('H:i:s');

        // Check for open sessions
        $openSession = DB::table('attendance_daily_details')
            ->where('daily_log_id', $log->id)
            ->whereNull('check_out_time')
            ->first();

        if ($openSession) {
            return ['ok' => false, 'code' => 'already_checked_in', 'message' => 'لديك جلسة حضور مفتوحة بالفعل.'];
        }

        $status = 'present';
        $matchedPeriodId = null;

        if ($schedule && $schedule->periods) {
            $isWithinPeriod = false;
            foreach ($schedule->periods as $p) {
                $pStartAllowed = Carbon::parse($dateStr . ' ' . $p->start_time)->subMinutes(30);
                $pEnd = Carbon::parse($dateStr . ' ' . $p->end_time);
                
                if ($now->between($pStartAllowed, $pEnd)) {
                    $matchedPeriodId = $p->id;
                    $isWithinPeriod = true;

                    // Check if period already used
                    $alreadyUsed = DB::table('attendance_daily_details')
                        ->where('daily_log_id', $log->id)
                        ->where('work_schedule_period_id', $p->id)
                        ->exists();

                    if ($alreadyUsed) {
                        return ['ok' => false, 'code' => 'period_already_completed', 'message' => 'لقد قمت بإكمال تسجيل الحضور لهذه الفترة مسبقاً.'];
                    }

                    // Late calculation
                    $realStart = Carbon::parse($dateStr . ' ' . $p->start_time);
                    if ($now->greaterThan($realStart->addMinutes($lateGraceMins))) {
                        $status = 'late';
                    }
                    break;
                }
            }

            if (!$isWithinPeriod) {
                return ['ok' => false, 'code' => 'too_early_for_checkin', 'message' => 'لا يمكنك التحضير قبل بداية الفترة بأكثر من 30 دقيقة.'];
            }
        }

        // Insert Detail
        DB::table('attendance_daily_details')->insert([
            'daily_log_id' => $log->id,
            'work_schedule_period_id' => $matchedPeriodId,
            'check_in_time' => $nowTime,
            'attendance_status' => $status,
            'meta_data' => json_encode(['method' => $method, 'lat' => $lat, 'lng' => $lng], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Update Main Log
        $updateData = ['updated_at' => $now];
        if (empty($log->check_in_time)) $updateData['check_in_time'] = $nowTime;
        
        // Status precedence
        if ((string)$log->attendance_status === 'absent' || $status === 'late') {
            $updateData['attendance_status'] = $status;
        }

        DB::table('attendance_daily_logs')->where('id', $log->id)->update($updateData);

        return ['ok' => true, 'time' => substr($nowTime, 0, 5), 'status' => $status];
    }

    /**
     * Record a check-out event.
     */
    public function recordCheckOut(object $log, string $method, ?float $lat, ?float $lng): array
    {
        $now = now();
        $nowTime = $now->format('H:i:s');

        $openSession = DB::table('attendance_daily_details')
            ->where('daily_log_id', $log->id)
            ->whereNull('check_out_time')
            ->orderByDesc('id')
            ->first();

        if (!$openSession) {
             return ['ok' => false, 'code' => 'no_check_in_record', 'message' => 'لا يوجد سجل حضور مفتوح حالياً'];
        }

        DB::table('attendance_daily_details')->where('id', $openSession->id)->update([
            'check_out_time' => $nowTime,
            'updated_at' => $now,
        ]);

        // Recalculate Totals
        $allDetails = DB::table('attendance_daily_details')
            ->where('daily_log_id', $log->id)
            ->whereNotNull('check_out_time')
            ->get();

        $totalMinutes = 0;
        foreach ($allDetails as $session) {
            $totalMinutes += $this->minutesBetween((string)$session->check_in_time, (string)$session->check_out_time);
        }
        $actualHours = round($totalMinutes / 60, 2);

        $compliance = null;
        if ($log->scheduled_hours > 0) {
            $compliance = round(min(100, ($totalMinutes / ($log->scheduled_hours * 60)) * 100), 2);
        }

        DB::table('attendance_daily_logs')->where('id', $log->id)->update([
            'check_out_time' => $nowTime,
            'actual_hours' => $actualHours,
            'compliance_percentage' => $compliance,
            'updated_at' => $now,
        ]);

        return ['ok' => true, 'time' => substr($nowTime, 0, 5), 'actual_hours' => $actualHours];
    }

    public function minutesBetween(string $startTime, string $endTime): int
    {
        $st = Carbon::parse($startTime);
        $et = Carbon::parse($endTime);
        if ($et->lt($st)) $et->addDay();
        return (int) $st->diffInMinutes($et);
    }
}
