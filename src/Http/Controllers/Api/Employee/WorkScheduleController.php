<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Employee;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Carbon\Carbon;
use Athka\SystemSettings\Services\EmployeeService;
use Athka\SystemSettings\Services\WorkScheduleService;

class WorkScheduleController extends Controller
{
    protected $employeeService;
    protected $scheduleService;

    public function __construct(EmployeeService $employeeService, WorkScheduleService $scheduleService)
    {
        $this->employeeService = $employeeService;
        $this->scheduleService = $scheduleService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $this->employeeService->getCompanyId($user);
        $employee = $this->employeeService->resolve($user);

        if (!$employee) {
            return response()->json(['ok' => false, 'message' => 'Employee record not found'], 403);
        }

        $start = $request->query('start');
        $end   = $request->query('end');

        try {
            $from = $start ? Carbon::parse($start)->startOfDay() : now()->startOfMonth();
            $to   = $end ? Carbon::parse($end)->startOfDay() : (clone $from)->endOfMonth();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Invalid date range'], 422);
        }

        if ($to->lt($from)) [$from, $to] = [$to, $from];

        // Cap to 62 days manually if needed for performance
        if ($from->diffInDays($to) > 62) {
            $to = $from->copy()->addDays(62);
        }

        $schedule = $this->scheduleService->getEffectiveSchedule($companyId, $employee);
        $holidays = $this->scheduleService->getHolidays($companyId, $from->toDateString(), $to->toDateString());

        $days = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $dateStr = $cursor->toDateString();
            $metrics = $this->scheduleService->getMetricsForDate($dateStr, $schedule, $holidays);

            $days[] = [
                'date' => $dateStr,
                'day_key' => $this->scheduleService->getDayKey($cursor),
                'status' => $metrics['status'],
                'is_holiday' => $metrics['is_holiday'],
                'holiday_name' => $metrics['holiday_name'],
                'is_workday' => $metrics['is_workday'],
                'total_minutes' => $metrics['total_minutes'],
                'periods' => $metrics['periods'],
            ];

            $cursor->addDay();
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'range' => [
                    'start' => $from->toDateString(),
                    'end' => $to->toDateString(),
                ],
                'schedule' => $schedule ? [
                    'id' => (int) $schedule->id,
                    'name' => (string) $schedule->name,
                    'work_days' => $schedule->work_days,
                ] : null,
                'days' => $days,
            ],
        ]);
    }
}
