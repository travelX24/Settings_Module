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

        $schedule = $this->scheduleService->getEffectiveSchedule($companyId, $employee);
        $holidays = $this->scheduleService->getHolidays($companyId, $from->toDateString(), $to->toDateString());

        if ($ensure) {
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $this->attendanceService->ensureLog($companyId, $employee->id, $cursor->toDateString(), $schedule, $holidays, $forceSync);
                $cursor->addDay();
            }
        }

        $logs = $this->attendanceService->getLogs($companyId, $employee->id, $from->toDateString(), $to->toDateString());

        // Cache schedules and holidays for the range to optimize
        $schedulesByDate = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $d = $cursor->toDateString();
            $schedulesByDate[$d] = $this->scheduleService->getEffectiveSchedule($companyId, $employee, $d);
            $cursor->addDay();
        }
        $holidays = $this->scheduleService->getHolidays($companyId, $from->toDateString(), $to->toDateString());

        $data = $logs->map(function($log) use ($schedulesByDate, $holidays) {
            // Load details if using Eloquent
            $details = property_exists($log, 'details') ? $log->details : DB::table('attendance_daily_details')->where('daily_log_id', $log->id)->get();

            return [
                'id' => (int) $log->id,
                'date' => Carbon::parse($log->attendance_date)->toDateString(),
                'check_in_time' => $log->check_in_time ? Carbon::parse($log->check_in_time)->format('H:i') : null,
                'check_out_time' => $log->check_out_time ? Carbon::parse($log->check_out_time)->format('H:i') : null,
                'attendance_status' => (string) $log->attendance_status,
                'status' => (string) $log->attendance_status, // Keep for backward compatibility
                'compliance_percentage' => (float) $log->compliance_percentage,
                'actual_hours' => (float) $log->actual_hours,
                'scheduled_hours' => (float) $log->scheduled_hours,
                'scheduled_check_in' => $log->scheduled_check_in ? Carbon::parse($log->scheduled_check_in)->format('H:i') : null,
                'scheduled_check_out' => $log->scheduled_check_out ? Carbon::parse($log->scheduled_check_out)->format('H:i') : null,
                'punches' => collect($details)->map(fn($d) => [
                    'check_in' => $d->check_in_time ? Carbon::parse($d->check_in_time)->format('H:i') : null,
                    'check_out' => $d->check_out_time ? Carbon::parse($d->check_out_time)->format('H:i') : null,
                ]),
                'periods' => collect($this->scheduleService->getMetricsForDate($log->attendance_date->toDateString(), $schedulesByDate[$log->attendance_date->toDateString()] ?? null, $holidays)['periods'] ?? [])
                    ->map(fn($p) => $p['start_time'] . ' - ' . $p['end_time'])
                    ->values()
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
        $schedule = $this->scheduleService->getEffectiveSchedule($companyId, $employee);
        $log = $this->attendanceService->ensureLog($companyId, $employee->id, $dateStr, $schedule, $this->scheduleService->getHolidays($companyId, $dateStr, $dateStr));

        // [Security] Force set attendance_date as clean string to prevent double time specification
        $log->attendance_date = $dateStr;

        $res = $this->attendanceService->recordCheckIn($log, $data['method'], $data['lat'], $data['lng'], $schedule);
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

        $log = DB::table('attendance_daily_logs')->where('saas_company_id', $companyId)->where('employee_id', $employee->id)->whereDate('attendance_date', now()->toDateString())->first();
        if (!$log) return response()->json(['ok' => false, 'message' => tr('No record found for today.')], 422);

        $res = $this->attendanceService->recordCheckOut($log, $data['method'], $data['lat'], $data['lng']);
        return response()->json($res);
    }
}
