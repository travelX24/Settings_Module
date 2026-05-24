<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Employee;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

use Athka\Employees\Models\Employee;
use Athka\Attendance\Models\AttendanceDailyLog;
use Athka\SystemSettings\Models\WorkSchedule;
use Athka\SystemSettings\Models\OfficialHolidayOccurrence;
use Athka\SystemSettings\Models\AttendanceGraceSetting;

use Athka\SystemSettings\Services\EmployeeService;
use Athka\SystemSettings\Services\AttendanceService;
use Athka\SystemSettings\Services\WorkScheduleService;
use Athka\SystemSettings\Services\GeofenceService;

class DailyAttendanceController extends Controller
{
    protected $employeeService;
    protected $attendanceService;
    protected $scheduleService;
    protected $geofenceService;

    public function __construct(
        EmployeeService $employeeService,
        AttendanceService $attendanceService,
        WorkScheduleService $scheduleService,
        GeofenceService $geofenceService
    ) {
        $this->employeeService = $employeeService;
        $this->attendanceService = $attendanceService;
        $this->scheduleService = $scheduleService;
        $this->geofenceService = $geofenceService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $this->employeeService->getCompanyId($user);
        $employee = $this->employeeService->resolve($user);

        if (!$employee) return response()->json(['ok' => false, 'message' => 'Employee not found'], 403);

        $start  = $request->query('start');
        $end    = $request->query('end');
        $ensure = (int) ($request->query('ensure', 1)) === 1;
        $forceSync = (int) ($request->query('force_sync', 0)) === 1;

        $from = $start ? Carbon::parse($start)->startOfDay() : now()->startOfDay();
        $to   = $end ? Carbon::parse($end)->startOfDay() : (clone $from);
        if ($to->lt($from)) [$from, $to] = [$to, $from];

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        // 🚀 [Optimization] Bulk fetch context data (Holidays and Requests are safe for range)
        $holidays = $this->scheduleService->getHolidays($companyId, $fromStr, $toStr);
        $requests = $this->scheduleService->getEmployeeRequests($employee->id, $fromStr, $toStr);

        // Schedule cache to handle mid-month changes accurately and fast
        $schedulesCache = [];

        if ($ensure) {
            $existingLogs = DB::table('attendance_daily_logs')
                ->where('saas_company_id', $companyId)
                ->where('employee_id', $employee->id)
                ->whereBetween('attendance_date', [$fromStr, $toStr])
                ->pluck('attendance_date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->toArray();

            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $dateStr = $cursor->toDateString();
                if ($forceSync || !in_array($dateStr, $existingLogs)) {
                    // Fetch accurate schedule for THIS specific day
                    if (!isset($schedulesCache[$dateStr])) {
                        $schedulesCache[$dateStr] = $this->scheduleService->getEffectiveSchedule($companyId, $employee, $dateStr);
                    }
                    $this->attendanceService->ensureLog($companyId, $employee, $dateStr, $schedulesCache[$dateStr], $holidays, $forceSync, $requests);
                }
                $cursor->addDay();
            }
        }

        $logs = $this->attendanceService->getLogs($companyId, $employee->id, $fromStr, $toStr);
        foreach ($logs as $log) {
            $log->setRelation('employee', $employee);
        }

        // Self-heal (Optimized): Recalculate and trigger auto-checkout for past logs with open sessions in a single bulk query
        $todayStr = now()->toDateString();
        $pastLogIds = [];
        foreach ($logs as $log) {
            if ($log->attendance_date->toDateString() < $todayStr) {
                $pastLogIds[] = $log->id;
            }
        }

        if (!empty($pastLogIds)) {
            $logsWithOpenPeriods = DB::table('attendance_daily_details')
                ->whereIn('daily_log_id', $pastLogIds)
                ->whereNull('check_out_time')
                ->pluck('daily_log_id')
                ->unique()
                ->toArray();

            if (!empty($logsWithOpenPeriods)) {
                foreach ($logs as $log) {
                    if (in_array($log->id, $logsWithOpenPeriods, true)) {
                        $log->save();
                    }
                }
            }
        }

        $logIds = $logs->pluck('id')->toArray();
        $allDetails = DB::table('attendance_daily_details')
            ->whereIn('daily_log_id', $logIds)
            ->orderBy('check_in_time')
            ->get()
            ->groupBy('daily_log_id');

        $data = $logs->map(function($log) use ($companyId, $employee, $holidays, $allDetails, $requests, &$schedulesCache) {
            $dateStr = $log->attendance_date->toDateString();
            $details = $allDetails->get($log->id) ?? collect([]);

            // Ensure we use the correct schedule for this day (with caching)
            if (!isset($schedulesCache[$dateStr])) {
                $schedulesCache[$dateStr] = $this->scheduleService->getEffectiveSchedule($companyId, $employee, $dateStr);
            }
            $daySchedule = $schedulesCache[$dateStr];

            // 🚀 [Optimization] Pass pre-fetched requests and accurate schedule
            $metrics = $this->scheduleService->getMetricsForDate($dateStr, $daySchedule, $holidays, $employee, $requests);

            $periods = collect($metrics['periods'] ?? [])->values();
            $usedDetailIds = [];

            if ($periods->isNotEmpty()) {
                $punches = $periods->map(function ($period) use ($details, $dateStr, &$usedDetailIds) {
                    $periodId = $period['id'] ?? null;
                    $detail = null;

                    if ($periodId) {
                        $detail = $details->first(function ($d) use ($periodId, $usedDetailIds) {
                            return !in_array($d->id, $usedDetailIds, true)
                                && (int) $d->work_schedule_period_id === (int) $periodId;
                        });
                    }

                    if (!$detail) {
                        $periodStart = Carbon::parse($dateStr . ' ' . substr((string) $period['start_time'], 0, 5));
                        $periodEnd = Carbon::parse($dateStr . ' ' . substr((string) $period['end_time'], 0, 5));
                        if (($period['is_night_shift'] ?? false) || $periodEnd->lt($periodStart)) {
                            $periodEnd->addDay();
                        }

                        $detail = $details->first(function ($d) use ($dateStr, $periodStart, $periodEnd, $usedDetailIds) {
                            if (in_array($d->id, $usedDetailIds, true) || empty($d->check_in_time)) {
                                return false;
                            }

                            $checkIn = Carbon::parse($dateStr . ' ' . substr((string) $d->check_in_time, 0, 5));
                            return $checkIn->between($periodStart, $periodEnd);
                        });
                    }

                    if ($detail) {
                        $usedDetailIds[] = $detail->id;
                    }

                    return [
                        'check_in' => $detail ? company_time($detail->check_in_time) : null,
                        'check_out' => $detail ? company_time($detail->check_out_time) : null,
                        'status' => $detail->attendance_status ?? null,
                    ];
                });

                $extraPunches = $details
                    ->reject(fn($d) => in_array($d->id, $usedDetailIds, true))
                    ->map(fn($d) => [
                        'check_in' => company_time($d->check_in_time),
                        'check_out' => company_time($d->check_out_time),
                        'status' => $d->attendance_status,
                    ])
                    ->values();

                $punches = $punches->concat($extraPunches)->values();
            } else {
                $punches = $details->map(fn($d) => [
                    'check_in' => company_time($d->check_in_time),
                    'check_out' => company_time($d->check_out_time),
                    'status' => $d->attendance_status,
                ])->values();
            }

            return [
                'id' => (int) $log->id,
                'date' => $dateStr,
                'check_in_time' => company_time($log->check_in_time),
                'check_out_time' => company_time($log->check_out_time),
                'attendance_status' => in_array($metrics['status'], ['holiday', 'no_schedule']) ? $metrics['status'] : (string) $log->attendance_status,
                'status' => in_array($metrics['status'], ['holiday', 'no_schedule']) ? $metrics['status'] : (string) $log->attendance_status,
                'holiday_name' => $metrics['holiday_name'] ?? null,
                'compliance_percentage' => (float) $log->compliance_percentage,
                'actual_hours' => (float) $log->actual_hours,
                'scheduled_hours' => (float) $log->scheduled_hours,
                'scheduled_check_in' => company_time($log->scheduled_check_in),
                'scheduled_check_out' => company_time($log->scheduled_check_out),
                'punches' => $punches,
                'periods' => $periods
                    ->map(fn($p) => company_time($p['start_time']) . ' - ' . company_time($p['end_time']))
                    ->all(),
            ];
        });

        return response()->json(['ok' => true, 'data' => ['days' => $data]]);
    }

    public function today(Request $request)
    {
        $request->query->set('start', now()->toDateString());
        $request->query->set('end', now()->toDateString());
        return $this->index($request);
    }

    public function checkIn(Request $request)
    {
        $user = $request->user();
        $companyId = $this->employeeService->getCompanyId($user);
        $employee = $this->employeeService->resolve($user);

        if (!$employee) return response()->json(['ok' => false, 'message' => 'Employee not found'], 403);

        $data = $request->validate([
            'method' => ['required', 'in:gps,fingerprint,nfc'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        $prep = app(AttendancePrepController::class)->show($request)->getData();
        if (!$prep->ok) return response()->json(['ok' => false, 'message' => 'Prep Error'], 422);

        if ($data['method'] === 'gps') {
            if (!$this->geofenceService->isWithinAny((float)$data['lat'], (float)$data['lng'], $prep->data->gps_locations ?? [])) {
                return response()->json(['ok' => false, 'code' => 'geofence_error', 'message' => tr('Outside the geographical range.')], 403);
            }
        }

        $dateStr = now()->toDateString();
        $exceptionalDay = $this->scheduleService->getExceptionalDay($companyId, $dateStr, $employee);

        if ($exceptionalDay && (bool)($exceptionalDay->is_holiday ?? true)) {
            $isOfficial = (bool)($exceptionalDay->is_official_holiday ?? false);
            $typeLabel = $isOfficial ? tr('Official Holiday') : tr('Exceptional Day');
            $msgPart = tr('Cannot check-in');
            
            $msg = $msgPart . '. ' . $typeLabel . ': ' . ($exceptionalDay->name ?? '');
            return response()->json(['ok' => false, 'code' => 'exceptional_day', 'message' => $msg], 422);
        }

        $schedule = $this->scheduleService->getEffectiveSchedule($companyId, $employee);
        $checkInSchedule = $schedule;

        if (
            $exceptionalDay
            && !(bool)($exceptionalDay->is_holiday ?? true)
            && !empty($exceptionalDay->start_time)
            && !empty($exceptionalDay->end_time)
        ) {
            $checkInSchedule = (object) [
                'periods' => collect([
                    (object) [
                        'id' => null,
                        'start_time' => $exceptionalDay->start_time,
                        'end_time' => $exceptionalDay->end_time,
                        'is_night_shift' => (bool)($exceptionalDay->is_night_shift ?? false),
                    ],
                ]),
            ];
        }

        $log = $this->attendanceService->ensureLog($companyId, $employee, $dateStr, $schedule, $this->scheduleService->getHolidays($companyId, $dateStr, $dateStr));

        // [Security] Force set attendance_date as clean string to prevent double time specification
        $log->attendance_date = $dateStr;

        $res = $this->attendanceService->recordCheckIn($log, $data['method'], $data['lat'], $data['lng'], $checkInSchedule, 15, $prep->data->tracking_mode ?? 'check_in_out');
        return response()->json($res);
    }

    public function checkOut(Request $request)
    {
        $user = $request->user();
        $companyId = $this->employeeService->getCompanyId($user);
        $employee = $this->employeeService->resolve($user);

        if (!$employee) return response()->json(['ok' => false, 'message' => 'Employee not found'], 403);

        $data = $request->validate([
            'method' => ['required', 'in:gps,fingerprint,nfc'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        $prep = app(AttendancePrepController::class)->show($request)->getData();
        if ($data['method'] === 'gps') {
            if (!$this->geofenceService->isWithinAny((float)$data['lat'], (float)$data['lng'], $prep->data->gps_locations ?? [])) {
                return response()->json(['ok' => false, 'message' => tr('Outside the geographical range.')], 403);
            }
        }

        $log = AttendanceDailyLog::where('saas_company_id', $companyId)
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->first();

        if (!$log) return response()->json(['ok' => false, 'message' => tr('No record found for today.')], 422);

        $res = $this->attendanceService->recordCheckOut($log, $data['method'], $data['lat'], $data['lng']);
        return response()->json($res);
    }
}
