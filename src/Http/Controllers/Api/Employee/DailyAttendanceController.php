<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Employee;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

use Athka\Employees\Models\Employee;
use Athka\SystemSettings\Models\WorkSchedule;
use Athka\SystemSettings\Models\OfficialHolidayOccurrence;
use Athka\SystemSettings\Models\AttendanceGraceSetting;

class DailyAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = (int) ($user->saas_company_id ?? $user->company_id ?? 0);
        if ($companyId <= 0) {
            return response()->json(['ok' => false, 'message' => 'Company context not found'], 422);
        }

        $employee = $this->resolveEmployee($user);
        if (! $employee) {
            return response()->json(['ok' => false, 'message' => 'Employee not found'], 403);
        }

        \Illuminate\Support\Facades\Log::info('Daily Attendance Request', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'resolved_employee_id' => $employee->id,
            'resolved_employee_name' => $employee->first_name . ' ' . $employee->last_name,
        ]);

        $start  = $request->query('start');
        $end    = $request->query('end');
        $ensure = (int) ($request->query('ensure', 1)) === 1;

        try {
            $from = $start ? Carbon::parse($start)->startOfDay() : now()->startOfDay();
            $to   = $end ? Carbon::parse($end)->startOfDay() : (clone $from);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Invalid date range'], 422);
        }

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        // max 62 days
        if ($from->diffInDays($to) > 62) {
            $to = (clone $from)->addDays(62);
        }

        $schedule = $this->getCompanySchedule($companyId);
        $holidays = $this->getHolidays($companyId, $from->toDateString(), $to->toDateString());

        if ($ensure) {
            $cursor = (clone $from);
            while ($cursor->lte($to)) {
                $this->ensureLog($companyId, (int) $employee->id, $cursor->toDateString(), $schedule, $holidays);
                $cursor->addDay();
            }
        }

        $logsQ = DB::table('attendance_daily_logs')
            ->where('saas_company_id', $companyId)
            ->where('employee_id', (int) $employee->id)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->orderBy('attendance_date');

        if ($request->filled('status')) {
            $logsQ->where('attendance_status', (string) $request->query('status'));
        }

        $rows = $logsQ->get();
        
        \Illuminate\Support\Facades\Log::info('Attendance Data Retrieved:', [
            'count' => $rows->count(),
            'first_row' => $rows->first(),
        ]);

        $days = $rows->map(function ($r) use ($schedule) {
            $dateObj = Carbon::parse($r->attendance_date);
            $dayKey = $this->dayKey($dateObj);
            
            $periodsOut = [];
            // If it's a workday and we have a schedule, try to get the periods
            if ($schedule && $r->scheduled_hours > 0) {
                $workDays = is_array($schedule->work_days) ? $schedule->work_days : [];
                $isWorkday = in_array($dayKey, $workDays, true);

                $sourcePeriods = collect();
                if ($schedule->periods) {
                    $sourcePeriods = $schedule->periods
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($p) => [
                            'start_time' => substr((string) $p->start_time, 0, 5),
                            'end_time' => substr((string) $p->end_time, 0, 5),
                            'is_night_shift' => (bool) $p->is_night_shift,
                        ]);
                }
                
                foreach ($sourcePeriods as $p) {
                    $periodsOut[] = $p['start_time'] . ' - ' . $p['end_time'];
                }
            }

            return [
                'date' => (string) $r->attendance_date,
                'day_key' => $dayKey,
                'work_schedule_id' => $r->work_schedule_id ? (int) $r->work_schedule_id : null,
                'scheduled_hours' => $r->scheduled_hours !== null ? (float) $r->scheduled_hours : null,

                'scheduled_check_in' => $this->timeToHm($r->scheduled_check_in),
                'scheduled_check_out' => $this->timeToHm($r->scheduled_check_out),
                
                'periods' => $periodsOut,

                'check_in_time' => $this->timeToHm($r->check_in_time),
                'check_out_time' => $this->timeToHm($r->check_out_time),

                'attendance_status' => (string) $r->attendance_status,
                'approval_status' => (string) $r->approval_status,
                'compliance_percentage' => $r->compliance_percentage !== null ? (float) $r->compliance_percentage : null,

                'is_edited' => (bool) $r->is_edited,
                'source' => (string) $r->source,
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'range' => [
                    'start' => $from->toDateString(),
                    'end' => $to->toDateString(),
                ],
                'days' => $days,
            ],
        ]);
    }

    public function today(Request $request)
    {
        $today = now()->toDateString();
        $request->query->set('start', $today);
        $request->query->set('end', $today);
        $request->query->set('ensure', '1');

        return $this->index($request);
    }

    public function checkIn(Request $request)
    {
        $user = $request->user();
        $companyId = (int) ($user->saas_company_id ?? $user->company_id ?? 0);
        if ($companyId <= 0) {
            return response()->json(['ok' => false, 'message' => 'Company context not found'], 422);
        }

        $employee = $this->resolveEmployee($user);
        if (! $employee) {
            return response()->json(['ok' => false, 'message' => 'Employee not found'], 403);
        }

        $data = $request->validate([
            'method' => ['required', 'in:gps,fingerprint,nfc'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        // ✅ 1. Check if method is allowed for this employee
        $prep = app(AttendancePrepController::class)->show($request)->getData();
        if (!$prep->ok) {
            return response()->json(['ok' => false, 'message' => 'Could not verify attendance settings'], 422);
        }

        $methodInfo = $prep->data->methods->{$data['method']} ?? null;
        if (!$methodInfo || !$methodInfo->effective) {
            return response()->json([
                'ok' => false, 
                'message' => 'هذه الطريقة ('. $data['method'] .') غير مفعلة لك حالياً.'
            ], 403);
        }

        // ✅ 2. If GPS, check Geofence
        if ($data['method'] === 'gps') {
            if (!isset($data['lat']) || !isset($data['lng'])) {
                return response()->json(['ok' => false, 'message' => 'Location coordinates are required for GPS attendance'], 422);
            }

            $allowedLocations = $prep->data->gps_locations ?? [];
            $isInside = false;

            foreach ($allowedLocations as $loc) {
                // We use the model's logic to verify
                $locModel = \Athka\SystemSettings\Models\AttendanceGpsLocation::find($loc->id);
                if ($locModel && $locModel->isWithinGeofence((float)$data['lat'], (float)$data['lng'])) {
                    $isInside = true;
                    break;
                }
            }

            if (!$isInside) {
                return response()->json([
                    'ok' => false, 
                    'code' => 'geofence_error',
                    'message' => 'أنت خارج النطاق الجغرافي المسموح به للتحضير.'
                ], 403);
            }
        }

        $dateStr = now()->toDateString();
        $schedule = $this->getCompanySchedule($companyId);
        $holidays = $this->getHolidays($companyId, $dateStr, $dateStr);

        $log = $this->ensureLog($companyId, (int) $employee->id, $dateStr, $schedule, $holidays);

        // ... existing logic ...
        if ((string) ($log->attendance_status ?? '') === 'on_leave' && (string) ($log->approval_status ?? '') === 'approved') {
            return response()->json(['ok' => false, 'code' => 'on_leave', 'message' => 'Employee is on leave'], 409);
        }

        if ($log->scheduled_hours === null) {
            return response()->json(['ok' => false, 'code' => 'not_workday', 'message' => 'Not a workday'], 409);
        }

        if (!empty($log->check_in_time)) {
            return response()->json([
                'ok' => false, 
                'code' => 'already_checked_in',
                'message' => 'لقد قمت بتسجيل الحضور مسبقاً في هذه الفترة'
            ], 409);
        }

        $nowTime = now()->format('H:i:s');

        // ✅ Determine attendance status (present or late)
        $status = 'present';
        if (property_exists($log, 'scheduled_start_time') && !empty($log->scheduled_start_time)) {
            $scheduledStart = \Carbon\Carbon::parse($log->scheduled_start_time);
            $checkInTime = \Carbon\Carbon::parse($nowTime);
            
            // If check-in is more than 15 minutes after scheduled start, mark as late
            if ($checkInTime->greaterThan($scheduledStart->addMinutes(15))) {
                $status = 'late';
            }
        }

        // ...
        DB::table('attendance_daily_logs')
            ->where('id', (int) $log->id)
            ->update([
                'check_in_time' => $nowTime,
                'attendance_status' => $status,
                'approval_status' => 'pending',
                'source' => 'manual',
                'meta_data' => json_encode([
                    'check_in' => [
                        'method' => $data['method'],
                        'lat' => $data['lat'] ?? null,
                        'lng' => $data['lng'] ?? null,
                        'ip' => request()->ip(),
                        'ua' => request()->userAgent(),
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        $fresh = DB::table('attendance_daily_logs')->where('id', (int) $log->id)->first();

        return response()->json([
            'ok' => true,
            'data' => [
                'date' => $dateStr,
                'check_in_time' => $this->timeToHm($fresh->check_in_time),
                'attendance_status' => (string) $fresh->attendance_status,
            ],
        ]);
    }

    public function checkOut(Request $request)
    {
        $user = $request->user();
        $companyId = (int) ($user->saas_company_id ?? $user->company_id ?? 0);
        if ($companyId <= 0) {
            return response()->json(['ok' => false, 'message' => 'Company context not found'], 422);
        }

        $employee = $this->resolveEmployee($user);
        if (! $employee) {
            return response()->json(['ok' => false, 'message' => 'Employee not found'], 403);
        }

        $data = $request->validate([
            'method' => ['required', 'in:gps,fingerprint,nfc'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        // ✅ 1. Check if method is allowed for this employee
        $prep = app(AttendancePrepController::class)->show($request)->getData();
        if (!$prep->ok) {
            return response()->json(['ok' => false, 'message' => 'Could not verify attendance settings'], 422);
        }

        $methodInfo = $prep->data->methods->{$data['method']} ?? null;
        if (!$methodInfo || !$methodInfo->effective) {
            return response()->json([
                'ok' => false, 
                'message' => 'هذه الطريقة ('. $data['method'] .') غير مفعلة لك حالياً.'
            ], 403);
        }

        // ✅ 2. If GPS, check Geofence
        if ($data['method'] === 'gps') {
            if (!isset($data['lat']) || !isset($data['lng'])) {
                return response()->json(['ok' => false, 'message' => 'Location coordinates are required for GPS attendance'], 422);
            }

            $allowedLocations = $prep->data->gps_locations ?? [];
            $isInside = false;

            foreach ($allowedLocations as $loc) {
                $locModel = \Athka\SystemSettings\Models\AttendanceGpsLocation::find($loc->id);
                if ($locModel && $locModel->isWithinGeofence((float)$data['lat'], (float)$data['lng'])) {
                    $isInside = true;
                    break;
                }
            }

            if (!$isInside) {
                return response()->json([
                    'ok' => false, 
                    'code' => 'geofence_error',
                    'message' => 'أنت خارج النطاق الجغرافي المسموح به للانصراف.'
                ], 403);
            }
        }

        $dateStr = now()->toDateString();

        $log = DB::table('attendance_daily_logs')
            ->where('saas_company_id', $companyId)
            ->where('employee_id', (int) $employee->id)
            ->whereDate('attendance_date', $dateStr)
            ->first();

        if (! $log) {
            $schedule = $this->getCompanySchedule($companyId);
            $holidays = $this->getHolidays($companyId, $dateStr, $dateStr);
            $log = $this->ensureLog($companyId, (int) $employee->id, $dateStr, $schedule, $holidays);
        }

        if (empty($log->check_in_time)) {
            return response()->json([
                'ok' => false, 
                'code' => 'no_check_in_record', 
                'message' => 'لا يوجد سجل حضور لهذا اليوم'
            ], 422);
        }

        if (!empty($log->check_out_time)) {
            return response()->json([
                'ok' => false, 
                'code' => 'already_checked_out', 
                'message' => 'لقد قمت بتسجيل الانصراف مسبقاً في هذه الفترة'
            ], 409);
        }

        $nowTime = now()->format('H:i:s');

        // ✅ compute actual minutes (FIXED)
        $actualMinutes = $this->minutesBetweenTimes((string) $log->check_in_time, $nowTime);
        $actualHours = round($actualMinutes / 60, 2);

        // grace
        $grace = AttendanceGraceSetting::query()
            ->where('saas_company_id', $companyId)
            ->where('is_global_default', true)
            ->first();

        $earlyGrace = (int) ($grace->early_leave_grace_minutes ?? 10);

        $status = (string) ($log->attendance_status ?? 'present');
        if (!in_array($status, ['late'], true)) {
            $status = 'present';
        }

        if (!empty($log->scheduled_check_out)) {
            $schedOut = Carbon::parse($dateStr . ' ' . substr((string) $log->scheduled_check_out, 0, 5) . ':00');
            $threshold = (clone $schedOut)->subMinutes($earlyGrace);

            if (Carbon::parse($dateStr . ' ' . substr($nowTime, 0, 5) . ':00')->lt($threshold)) {
                $status = 'early_departure';
            }
        }

        // ✅ compliance (FIX: guard negatives)
        $scheduledMinutes = 0;
        if (!is_null($log->scheduled_hours)) {
            $scheduledMinutes = (int) round(max(0, (float) $log->scheduled_hours) * 60);
        }

        $compliance = null;
        if ($scheduledMinutes > 0) {
            $compliance = round(min(100, ($actualMinutes / $scheduledMinutes) * 100), 2);
        }

        DB::table('attendance_daily_logs')
            ->where('id', (int) $log->id)
            ->update([
                'check_out_time' => $nowTime,
                'actual_hours' => $actualHours,
                'attendance_status' => $status,
                'approval_status' => 'pending',
                'source' => 'manual',
                'compliance_percentage' => $compliance,
                'meta_data' => json_encode([
                    'check_out' => [
                        'method' => $data['method'],
                        'lat' => $data['lat'] ?? null,
                        'lng' => $data['lng'] ?? null,
                        'ip' => request()->ip(),
                        'ua' => request()->userAgent(),
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        $fresh = DB::table('attendance_daily_logs')->where('id', (int) $log->id)->first();

        return response()->json([
            'ok' => true,
            'data' => [
                'date' => $dateStr,
                'check_in_time' => $this->timeToHm($fresh->check_in_time),
                'check_out_time' => $this->timeToHm($fresh->check_out_time),
                'attendance_status' => (string) $fresh->attendance_status,
                'actual_hours' => $fresh->actual_hours !== null ? (float) $fresh->actual_hours : null,
                'compliance_percentage' => $fresh->compliance_percentage !== null ? (float) $fresh->compliance_percentage : null,
            ],
        ]);
    }

    // ================= Helpers =================

    protected function resolveEmployee($user): ?Employee
    {
        $employee = null;

        if (property_exists($user, 'employee_id') && (int) ($user->employee_id ?? 0) > 0) {
            $employee = Employee::query()->where('id', (int) $user->employee_id)->first();
        }

        if (! $employee && method_exists($user, 'employee')) {
            $employee = $user->employee;
        }

        if (! $employee && Schema::hasColumn('employees', 'user_id')) {
            $employee = Employee::query()->where('user_id', (int) $user->id)->first();
        }

        return $employee ?: null;
    }

    protected function getCompanySchedule(int $companyId): ?WorkSchedule
    {
        $schedule = WorkSchedule::query()
            ->with(['periods', 'exceptions'])
            ->where('saas_company_id', $companyId)
            ->where('is_default', true)
            ->first();

        if (! $schedule) {
            $schedule = WorkSchedule::query()
                ->with(['periods', 'exceptions'])
                ->where('saas_company_id', $companyId)
                ->orderByDesc('id')
                ->first();
        }

        return $schedule ?: null;
    }

    protected function getHolidays(int $companyId, string $from, string $to)
    {
        return OfficialHolidayOccurrence::query()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get();
    }

    protected function computeScheduledHoursFromTimes(string $dateStr, ?string $schedIn, ?string $schedOut): ?float
    {
        if (!$schedIn || !$schedOut) return null;

        $st = Carbon::parse($dateStr . ' ' . substr($schedIn, 0, 5) . ':00');
        $et = Carbon::parse($dateStr . ' ' . substr($schedOut, 0, 5) . ':00');

        if ($et->lt($st)) {
            $et->addDay();
        }

        $mins = $st->diffInMinutes($et);
        return $mins > 0 ? round($mins / 60, 2) : null;
    }

    protected function ensureLog(int $companyId, int $employeeId, string $dateStr, ?WorkSchedule $schedule, $holidays)
    {
        $existing = DB::table('attendance_daily_logs')
            ->where('saas_company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $dateStr)
            ->first();

        if ($existing) {
            $needsFill =
                is_null($existing->work_schedule_id) ||
                is_null($existing->scheduled_hours) ||
                is_null($existing->scheduled_check_in) ||
                is_null($existing->scheduled_check_out);

            if ($needsFill && $schedule) {
                $dayKey = $this->dayKey(Carbon::parse($dateStr));

                $isHoliday = $holidays->first(function ($h) use ($dateStr) {
                    return $dateStr >= (string) $h->start_date && $dateStr <= (string) $h->end_date;
                });

                $scheduledMinutes = 0;
                $schedIn = null;
                $schedOut = null;
                $workScheduleId = $schedule ? (int) $schedule->id : null;

                if ($schedule && !$isHoliday) {
                    $workDays = is_array($schedule->work_days) ? $schedule->work_days : [];
                    $isWorkday = in_array($dayKey, $workDays, true);

                    $periods = collect();

                    if ($schedule->exceptions) {
                        $exceptions = $schedule->exceptions->filter(function ($e) use ($dateStr, $dayKey) {
                            if (! $e->is_active) return false;
                            if ($e->specific_date) return (string) $e->specific_date === $dateStr;
                            return (string) ($e->day_of_week ?? '') === $dayKey;
                        })->values();

                        if ($exceptions->count() > 0) {
                            $isWorkday = true;
                            $periods = $exceptions->map(fn ($e) => [
                                'start_time' => substr((string) $e->start_time, 0, 5),
                                'end_time' => substr((string) $e->end_time, 0, 5),
                                'is_night_shift' => (bool) $e->is_night_shift,
                            ]);
                        }
                    }

                    if ($periods->isEmpty() && $isWorkday && $schedule->periods) {
                        $periods = $schedule->periods
                            ->sortBy('sort_order')
                            ->values()
                            ->map(fn ($p) => [
                                'start_time' => substr((string) $p->start_time, 0, 5),
                                'end_time' => substr((string) $p->end_time, 0, 5),
                                'is_night_shift' => (bool) $p->is_night_shift,
                            ]);
                    }

                    if ($periods->isNotEmpty()) {
                        $starts = [];
                        $ends = [];

                        foreach ($periods as $p) {
                            $st = (string) ($p['start_time'] ?? '');
                            $et = (string) ($p['end_time'] ?? '');
                            if ($st === '' || $et === '') continue;

                            $starts[] = $st;
                            $ends[] = $et;

                            $startDt = Carbon::parse($dateStr . ' ' . $st . ':00');
                            $endDt   = Carbon::parse($dateStr . ' ' . $et . ':00');

                            if (!empty($p['is_night_shift']) || $endDt->lt($startDt)) {
                                $endDt->addDay();
                            }

                            $scheduledMinutes += $startDt->diffInMinutes($endDt);
                        }

                        sort($starts);
                        sort($ends);

                        $schedIn = $starts[0] ?? null;
                        $schedOut = $ends[count($ends) - 1] ?? null;
                    }
                }

                $scheduledHours = $scheduledMinutes > 0 ? round($scheduledMinutes / 60, 2) : null;

                $update = [];
                if (is_null($existing->work_schedule_id)) $update['work_schedule_id'] = $workScheduleId;
                if (is_null($existing->scheduled_hours)) $update['scheduled_hours'] = $scheduledHours;
                if (is_null($existing->scheduled_check_in)) $update['scheduled_check_in'] = $schedIn ? ($schedIn . ':00') : null;
                if (is_null($existing->scheduled_check_out)) $update['scheduled_check_out'] = $schedOut ? ($schedOut . ':00') : null;

                if (!empty($update)) {
                    $update['updated_at'] = now();
                    DB::table('attendance_daily_logs')->where('id', (int) $existing->id)->update($update);
                    $existing = DB::table('attendance_daily_logs')->where('id', (int) $existing->id)->first();
                }
            }

            return $existing;
        }

        $dayKey = $this->dayKey(Carbon::parse($dateStr));

        $isHoliday = $holidays->first(function ($h) use ($dateStr) {
            return $dateStr >= (string) $h->start_date && $dateStr <= (string) $h->end_date;
        });

        $scheduledMinutes = 0;
        $schedIn = null;
        $schedOut = null;
        $workScheduleId = $schedule ? (int) $schedule->id : null;

        if ($schedule && !$isHoliday) {
            $workDays = is_array($schedule->work_days) ? $schedule->work_days : [];
            $isWorkday = in_array($dayKey, $workDays, true);

            $periods = collect();

            if ($schedule->exceptions) {
                $exceptions = $schedule->exceptions->filter(function ($e) use ($dateStr, $dayKey) {
                    if (! $e->is_active) return false;
                    if ($e->specific_date) return (string) $e->specific_date === $dateStr;
                    return (string) ($e->day_of_week ?? '') === $dayKey;
                })->values();

                if ($exceptions->count() > 0) {
                    $isWorkday = true;
                    $periods = $exceptions->map(fn ($e) => [
                        'start_time' => substr((string) $e->start_time, 0, 5),
                        'end_time' => substr((string) $e->end_time, 0, 5),
                        'is_night_shift' => (bool) $e->is_night_shift,
                    ]);
                }
            }

            if ($periods->isEmpty() && $isWorkday && $schedule->periods) {
                $periods = $schedule->periods
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn ($p) => [
                        'start_time' => substr((string) $p->start_time, 0, 5),
                        'end_time' => substr((string) $p->end_time, 0, 5),
                        'is_night_shift' => (bool) $p->is_night_shift,
                    ]);
            }

            if ($periods->isNotEmpty()) {
                $starts = [];
                $ends = [];

                foreach ($periods as $p) {
                    $st = (string) ($p['start_time'] ?? '');
                    $et = (string) ($p['end_time'] ?? '');
                    if ($st === '' || $et === '') continue;

                    $starts[] = $st;
                    $ends[] = $et;

                    $startDt = Carbon::parse($dateStr . ' ' . $st . ':00');
                    $endDt   = Carbon::parse($dateStr . ' ' . $et . ':00');

                    if (!empty($p['is_night_shift']) || $endDt->lt($startDt)) {
                        $endDt->addDay();
                    }

                    $scheduledMinutes += $startDt->diffInMinutes($endDt);
                }

                sort($starts);
                sort($ends);

                $schedIn = $starts[0] ?? null;
                $schedOut = $ends[count($ends) - 1] ?? null;
            }
        }

        $scheduledHours = $scheduledMinutes > 0 ? round($scheduledMinutes / 60, 2) : null;

        $id = DB::table('attendance_daily_logs')->insertGetId([
            'saas_company_id' => $companyId,
            'employee_id' => $employeeId,
            'attendance_date' => $dateStr,
            'work_schedule_id' => $workScheduleId,
            'scheduled_hours' => $scheduledHours,
            'scheduled_check_in' => $schedIn ? ($schedIn . ':00') : null,
            'scheduled_check_out' => $schedOut ? ($schedOut . ':00') : null,
            'attendance_status' => 'absent',
            'approval_status' => 'pending',
            'source' => 'automatic',
            'meta_data' => json_encode([
                'generated_by' => 'api',
                'is_holiday' => (bool) $isHoliday,
                'day_key' => $dayKey,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('attendance_daily_logs')->where('id', (int) $id)->first();
    }

    protected function dayKey(Carbon $date): string
    {
        $map = [
            0 => 'sunday',
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
        ];
        return $map[(int) $date->dayOfWeek] ?? 'sunday';
    }

    protected function timeToHm($value): ?string
    {
        if (!$value) return null;
        return substr((string) $value, 0, 5);
    }

    // ✅ FIXED: correct direction + supports H:i and H:i:s + overnight
    protected function minutesBetweenTimes(string $startTime, string $endTime): int
    {
        $startTime = substr($startTime, 0, 8);
        $endTime   = substr($endTime, 0, 8);

        if (strlen($startTime) === 5) $startTime .= ':00';
        if (strlen($endTime) === 5)   $endTime   .= ':00';

        $st = Carbon::createFromFormat('H:i:s', $startTime);
        $et = Carbon::createFromFormat('H:i:s', $endTime);

        $mins = $st->diffInMinutes($et, false); // ✅ الصحيح
        if ($mins < 0) {
            $mins += 24 * 60;
        }
        return (int) $mins;
    }
}
