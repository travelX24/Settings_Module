<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Employee;

use Athka\SystemSettings\Services\EmployeeService;
use Athka\SystemSettings\Services\WorkScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class WorkScheduleController extends Controller
{
    protected $employeeService;

    protected $scheduleService;

    public function __construct(
        EmployeeService $employeeService,
        WorkScheduleService $scheduleService
    ) {
        $this->employeeService = $employeeService;
        $this->scheduleService = $scheduleService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $companyId = $this->employeeService->getCompanyId($user);
        $employee = $this->employeeService->resolve($user);

        if (!$employee) {
            return response()->json([
                'ok' => false,
                'message' => 'Employee record not found',
            ], 403);
        }

        $start = $request->query('start');
        $end = $request->query('end');

        try {
            $from = $start
                ? Carbon::parse($start)->startOfDay()
                : now()->startOfMonth();

            $to = $end
                ? Carbon::parse($end)->startOfDay()
                : $from->copy()->endOfMonth();
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid date range',
            ], 422);
        }

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > 62) {
            $to = $from->copy()->addDays(62);
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        /*
         * The schedule output is expensive because it calculates every
         * date in the requested range. Cache the final employee response
         * so repeated mobile requests do not recalculate the whole month.
         */
        $cacheKey = implode(':', [
            'mobile',
            'work-schedule',
            'v2',
            (int) $companyId,
            (int) $employee->id,
            $fromStr,
            $toStr,
            app()->getLocale(),
        ]);

        $data = Cache::remember(
            $cacheKey,
            now()->addMinutes(5),
            function () use (
                $companyId,
                $employee,
                $from,
                $to,
                $fromStr,
                $toStr
            ) {
                $holidays = $this->scheduleService->getHolidays(
                    $companyId,
                    $fromStr,
                    $toStr
                );

                $requests = $this->scheduleService->getEmployeeRequests(
                    $employee->id,
                    $fromStr,
                    $toStr
                );

                $days = [];
                $cursor = $from->copy();

                while ($cursor->lte($to)) {
                    $dateStr = $cursor->toDateString();

                    $effectiveSchedule =
                        $this->scheduleService->getEffectiveSchedule(
                            $companyId,
                            $employee,
                            $dateStr,
                            false
                        );

                    $metrics =
                        $this->scheduleService->getMetricsForDate(
                            $dateStr,
                            $effectiveSchedule,
                            $holidays,
                            $employee,
                            $requests
                        );

                    $days[] = [
                        'date' => $dateStr,
                        'day_key' => $this->scheduleService->getDayKey(
                            $cursor
                        ),
                        'status' => $metrics['status'],
                        'is_holiday' => $metrics['is_holiday'],
                        'holiday_name' => $metrics['holiday_name'],
                        'leave_name' => $metrics['leave_name'],
                        'is_workday' => $metrics['is_workday'],
                        'total_minutes' => $metrics['total_minutes'],
                        'periods' => $metrics['periods'],
                        'permissions' => $metrics['permissions'],
                    ];

                    $cursor->addDay();
                }

                $initialSchedule =
                    $this->scheduleService->getEffectiveSchedule(
                        $companyId,
                        $employee,
                        $fromStr
                    );

                return [
                    'range' => [
                        'start' => $fromStr,
                        'end' => $toStr,
                    ],
                    'schedule' => $initialSchedule
                        ? [
                            'id' => (int) $initialSchedule->id,
                            'name' => (string) $initialSchedule->name,
                            'work_days' => $initialSchedule->work_days,
                        ]
                        : null,
                    'days' => $days,
                ];
            }
        );

        return response()->json([
            'ok' => true,
            'data' => $data,
        ]);
    }
}