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

        $from = $start ? Carbon::parse($start)->startOfDay() : now()->startOfDay();
        $to   = $end ? Carbon::parse($end)->startOfDay() : (clone $from);
        if ($to->lt($from)) [$from, $to] = [$to, $from];

        $schedule = $this->scheduleService->getEffectiveSchedule($companyId, $employee);
        $holidays = $this->scheduleService->getHolidays($companyId, $from->toDateString(), $to->toDateString());

        if ($ensure) {
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $this->attendanceService->ensureLog($companyId, $employee->id, $cursor->toDateString(), $schedule, $holidays);
                $cursor->addDay();
            }
        }

        $logs = $this->attendanceService->getLogs($companyId, $employee->id, $from->toDateString(), $to->toDateString());

        // Simple transformation for now (can be moved to a Resource later)
        $data = $logs->map(function($log) {
            return [
                'date' => $log->attendance_date,
                'check_in' => $log->check_in_time ? substr($log->check_in_time, 0, 5) : null,
                'check_out' => $log->check_out_time ? substr($log->check_out_time, 0, 5) : null,
                'status' => $log->attendance_status,
                'compliance' => $log->compliance_percentage,
                'actual_hours' => $log->actual_hours,
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
                return response()->json(['ok' => false, 'code' => 'geofence_error', 'message' => 'خارج النطاق الجغرافي.'], 403);
            }
        }

        $dateStr = now()->toDateString();
        $schedule = $this->scheduleService->getEffectiveSchedule($companyId, $employee);
        $log = $this->attendanceService->ensureLog($companyId, $employee->id, $dateStr, $schedule, $this->scheduleService->getHolidays($companyId, $dateStr, $dateStr));

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
                return response()->json(['ok' => false, 'message' => 'خارج النطاق الجغرافي.'], 403);
            }
        }

        $log = DB::table('attendance_daily_logs')->where('saas_company_id', $companyId)->where('employee_id', $employee->id)->whereDate('attendance_date', now()->toDateString())->first();
        if (!$log) return response()->json(['ok' => false, 'message' => 'لا يوجد سجل اليوم.'], 422);

        $res = $this->attendanceService->recordCheckOut($log, $data['method'], $data['lat'], $data['lng']);
        return response()->json($res);
    }
}
