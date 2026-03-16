<?php

namespace Athka\SystemSettings\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Athka\Employees\Models\Employee;
use Athka\Attendance\Models\AttendanceDailyLog;
use Athka\Attendance\Models\AttendanceDailyDetail;

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
        $log = AttendanceDailyLog::where('saas_company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($log && !is_null($log->scheduled_hours)) {
            return $log;
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
    public function recordCheckIn(AttendanceDailyLog $log, string $method, ?float $lat, ?float $lng, $schedule, int $lateGraceMins = 15): array
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
        if (empty($log->check_in_time)) {
            $log->check_in_time = $nowTime;
        }
        
        // Let the model handle status and other calculations
        $log->save();

        return ['ok' => true, 'time' => substr($nowTime, 0, 5), 'status' => $status];
    }

    /**
     * Record a check-out event.
     */
    public function recordCheckOut(AttendanceDailyLog $log, string $method, ?float $lat, ?float $lng): array
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
