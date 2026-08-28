<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Employee;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

use Athka\Employees\Models\Employee;
use Athka\Attendance\Models\AttendanceDailyLog;
use Athka\SystemSettings\Models\WorkSchedule;
use Athka\SystemSettings\Models\OfficialHolidayOccurrence;
use Athka\SystemSettings\Models\AttendanceGraceSetting;
use Athka\SystemSettings\Models\AttendanceGpsLocation;
use Athka\SystemSettings\Models\AttendanceMethod;
use Athka\SystemSettings\Models\EmployeeGroup;

use Athka\SystemSettings\Services\EmployeeService;
use Athka\SystemSettings\Services\AttendanceService;
use Athka\SystemSettings\Services\WorkScheduleService;
use Athka\SystemSettings\Services\AttendanceLocationGateService;

class DailyAttendanceController extends Controller
{
    protected $employeeService;
    protected $attendanceService;
    protected $scheduleService;
    protected AttendanceLocationGateService $locationGateService;

    public function __construct(
        EmployeeService $employeeService,
        AttendanceService $attendanceService,
        WorkScheduleService $scheduleService,
        AttendanceLocationGateService $locationGateService
    ) {
        $this->employeeService = $employeeService;
        $this->attendanceService = $attendanceService;
        $this->scheduleService = $scheduleService;
        $this->locationGateService = $locationGateService;
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

        // ðŸš€ [Optimization] Bulk fetch context data (Holidays and Requests are safe for range)
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
                    $this->attendanceService->ensureLog($companyId, $employee->id, $dateStr, $schedulesCache[$dateStr], $holidays, $forceSync, $requests);
                }
                $cursor->addDay();
            }
        }

        $syncOpenSessions = (int) $request->query(
    'sync_open_sessions',
    0
) === 1;

if ($syncOpenSessions) {
    AttendanceDailyLog::where('saas_company_id', $companyId)
        ->where('employee_id', $employee->id)
        ->whereBetween('attendance_date', [$fromStr, $toStr])
        ->whereHas(
            'details',
            fn ($query) => $query->whereNull('check_out_time')
        )
        ->get()
        ->each(function ($log) {
            $log->save();
        });
}

        $logs = $this->attendanceService->getLogs($companyId, $employee->id, $fromStr, $toStr);

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

            // ðŸš€ [Optimization] Pass pre-fetched requests and accurate schedule
            $metrics = $this->scheduleService->getMetricsForDate($dateStr, $daySchedule, $holidays, $employee, $requests);
            $periods = collect($metrics['periods'] ?? [])->values();
            $detailsByPeriod = $details
                ->filter(fn ($d) => !empty($d->work_schedule_period_id))
                ->keyBy(fn ($d) => (int) $d->work_schedule_period_id);

            $punches = $periods->map(function ($period) use ($detailsByPeriod) {
                $detail = $detailsByPeriod->get((int) ($period['id'] ?? 0));

                return [
                    'period_id' => isset($period['id']) ? (int) $period['id'] : null,
                    'check_in' => $detail ? company_time($detail->check_in_time) : null,
                    'check_out' => $detail ? company_time($detail->check_out_time) : null,
                    'status' => $detail?->attendance_status,
                ];
            });

            $periodIds = $periods->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
            $unmatchedPunches = $details
                ->filter(fn ($d) => empty($d->work_schedule_period_id) || !in_array((int) $d->work_schedule_period_id, $periodIds, true))
                ->map(fn ($d) => [
                    'period_id' => $d->work_schedule_period_id ? (int) $d->work_schedule_period_id : null,
                    'check_in' => company_time($d->check_in_time),
                    'check_out' => company_time($d->check_out_time),
                    'status' => $d->attendance_status,
                ])
                ->values();

            if ($periods->isEmpty()) {
                $punches = $unmatchedPunches;
            } elseif ($unmatchedPunches->isNotEmpty()) {
                $punches = $punches->concat($unmatchedPunches)->values();
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
                'punches' => $punches->values(),
                'periods' => $periods
                    ->map(function ($p) {
                        $range = company_time($p['start_time']) . ' - ' . company_time($p['end_time']);

                        if (!empty($p['is_leave'])) {
                            return '__leave__|' . trim((string) ($p['leave_name'] ?? '')) . '|' . $range;
                        }

                        return $range;
                    })
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
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'is_mocked' => ['nullable', 'boolean'],
            'gps_accuracy' => ['nullable', 'numeric', 'min:0'],
            'location_captured_at' => ['nullable', 'date'],
        ]);

        /*
         * CHECK-IN WORKDAY ELIGIBILITY GATE
         *
         * Do not create normal attendance when the employee has
         * no effective workday, is on approved leave, or the day
         * is a holiday.
         *
         * We intentionally do NOT block outside_work_window here.
         * Existing attendance rules may allow early arrival such
         * as 08:57 for a 09:00 shift.
         *
         * Check-out is intentionally not gated, so an already-open
         * attendance session can still be closed safely.
         */
        $capturedAt = ! empty($data['location_captured_at'])
            ? \Carbon\CarbonImmutable::parse(
                (string) $data['location_captured_at']
            )
            : \Carbon\CarbonImmutable::now();

        $workWindow = app(
            \Athka\Attendance\Services\TrackingWorkWindowService::class
        )->resolve(
            $employee,
            $capturedAt
        );

        /*
         * An employee may have an assigned schedule while the captured
         * date itself is a configured day off. WorkScheduleService
         * represents this as status=off, not no_schedule.
         *
         * Only inspect this when tracking resolved to outside_work_window.
         * This keeps early/late punches on real workdays allowed and
         * preserves previous-day overnight shift resolution.
         */
        $isScheduleDayOff = false;

        if (
            $workWindow->state ===
            \Athka\Attendance\Services\TrackingWorkWindowService::STATE_OUTSIDE_WORK_WINDOW
        ) {
            $capturedDate = $capturedAt->toDateString();

            $workScheduleService = app(
                \Athka\SystemSettings\Services\WorkScheduleService::class
            );

            $effectiveSchedule = $workScheduleService->getEffectiveSchedule(
                $companyId,
                $employee,
                $capturedDate
            );

            $dayHolidays = $workScheduleService->getHolidays(
                $companyId,
                $capturedDate,
                $capturedDate
            );

            $dayRequests = $workScheduleService->getEmployeeRequests(
                (int) $employee->id,
                $capturedDate,
                $capturedDate
            );

            $dayMetrics = $workScheduleService->getMetricsForDate(
                $capturedDate,
                $effectiveSchedule,
                $dayHolidays,
                $employee,
                $dayRequests
            );

            $isScheduleDayOff =
                (string) ($dayMetrics['status'] ?? '') === 'off';
        }

        $blockedWorkStates = [
            \Athka\Attendance\Services\TrackingWorkWindowService::STATE_NO_SCHEDULE,
            \Athka\Attendance\Services\TrackingWorkWindowService::STATE_HOLIDAY,
            \Athka\Attendance\Services\TrackingWorkWindowService::STATE_LEAVE,
        ];

        if (
            in_array($workWindow->state, $blockedWorkStates, true)
            || $isScheduleDayOff
        ) {
            $attendanceState = $isScheduleDayOff
                ? 'day_off'
                : $workWindow->state;
            $isArabic = str_starts_with(
                strtolower((string) app()->getLocale()),
                'ar'
            );

            $message = match ($attendanceState) {
                'day_off' =>
                    $isArabic
                        ? 'لا يمكنك تسجيل الحضور في يوم راحة.'
                        : 'Check-in is not allowed on a day off.',

                \Athka\Attendance\Services\TrackingWorkWindowService::STATE_HOLIDAY =>
                    $isArabic
                        ? 'لا يمكنك تسجيل الحضور في يوم عطلة.'
                        : 'Check-in is not allowed on a holiday.',

                \Athka\Attendance\Services\TrackingWorkWindowService::STATE_LEAVE =>
                    $isArabic
                        ? 'لا يمكنك تسجيل الحضور أثناء إجازة معتمدة.'
                        : 'Check-in is not allowed during approved leave.',

                default =>
                    $isArabic
                        ? 'لا يوجد لديك دوام مجدول لهذا اليوم.'
                        : 'You do not have a scheduled workday today.',
            };

            return response()->json([
                'ok' => false,
                'code' => 'attendance_day_not_allowed',
                'message' => $message,
                'attendance_state' => $attendanceState,
            ], 422);
        }

        $context = $this->resolveAttendanceMethodContext(
            $companyId,
            $employee,
            $data['method'],
            $user
        );

        if ($methodError = $this->attendanceMethodUnavailableResponse($context, $data['method'])) {
            return $methodError;
        }

        $locationDecision = null;

        if ($data['method'] === 'gps') {
            $allowedLocationIds = collect(
                $context->data->gps_locations ?? []
            )
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $locationDecision = $this->locationGateService->evaluateOnline(
                companyId: $companyId,
                employee: $employee,
                payload: $data,
                allowedLocationIds: $allowedLocationIds,
            );

            if (! $locationDecision->allowed) {
                if ($branchError = $this->attendanceBranchScopeViolationResponse(
                    $context,
                    $companyId,
                    $employee,
                    $data
                )) {
                    return $branchError;
                }

                return response()->json(
                    $locationDecision->toResponseArray(),
                    $locationDecision->httpStatus
                );
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

        $locationMeta = [
            'is_mocked' => (bool)($data['is_mocked'] ?? false),
            'gps_accuracy' => $data['gps_accuracy'] ?? null,
            'location_captured_at' => $data['location_captured_at'] ?? null,
            'location_gate' => $locationDecision?->toArray(),
            'verified_location_id' => $locationDecision?->locationId,
            'verified_location_name' => $locationDecision?->locationName,
            'verified_geofence_type' => $locationDecision?->geofenceType,
            'distance_to_geofence_meters' => $locationDecision?->distanceMeters,
        ];

        $lock = Cache::lock("attendance:checkin:{$companyId}:{$employee->id}:{$dateStr}", 15);

        if (!$lock->get()) {
            return response()->json([
                'ok' => false,
                'code' => 'already_processing',
                'message' => tr('Your attendance request is already being processed.'),
            ], 409);
        }

        try {
            $res = DB::transaction(function () use ($companyId, $employee, $dateStr, $data, $context, $locationMeta) {
                $schedule = $this->scheduleService->getEffectiveSchedule($companyId, $employee);

$holidays = Cache::remember(
    "attendance:holidays:{$companyId}:{$dateStr}",
    now()->addSeconds(60),
    fn () => $this->scheduleService->getHolidays(
        $companyId,
        $dateStr,
        $dateStr
    )
);

$log = $this->attendanceService->ensureLog(
    companyId: $companyId,
    employeeId: $employee->id,
    date: $dateStr,
    schedule: $schedule,
    holidays: $holidays,
);

                // [Security] Force set attendance_date as clean string to prevent double time specification
                $log->attendance_date = $dateStr;

                return $this->attendanceService->recordCheckIn($log, $data['method'], $data['lat'] ?? null, $data['lng'] ?? null, $schedule, 15, $context->data->tracking_mode ?? 'check_in_out', $locationMeta);
            });
        } finally {
            optional($lock)->release();
        }

        if ($locationDecision !== null) {
            $res['location_gate'] = $locationDecision->toArray();
        }

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
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'is_mocked' => ['nullable', 'boolean'],
            'gps_accuracy' => ['nullable', 'numeric', 'min:0'],
            'location_captured_at' => ['nullable', 'date'],
        ]);

        $context = $this->resolveAttendanceMethodContext(
            $companyId,
            $employee,
            $data['method'],
            $user
        );

        if ($methodError = $this->attendanceMethodUnavailableResponse($context, $data['method'])) {
            return $methodError;
        }

        $locationDecision = null;

        if ($data['method'] === 'gps') {
            $allowedLocationIds = collect(
                $context->data->gps_locations ?? []
            )
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $locationDecision = $this->locationGateService->evaluateOnline(
                companyId: $companyId,
                employee: $employee,
                payload: $data,
                allowedLocationIds: $allowedLocationIds,
            );

            if (! $locationDecision->allowed) {
                if ($branchError = $this->attendanceBranchScopeViolationResponse(
                    $context,
                    $companyId,
                    $employee,
                    $data
                )) {
                    return $branchError;
                }

                return response()->json(
                    $locationDecision->toResponseArray(),
                    $locationDecision->httpStatus
                );
            }
        }

        $locationMeta = [
            'is_mocked' => (bool)($data['is_mocked'] ?? false),
            'gps_accuracy' => $data['gps_accuracy'] ?? null,
            'location_captured_at' => $data['location_captured_at'] ?? null,
            'location_gate' => $locationDecision?->toArray(),
            'verified_location_id' => $locationDecision?->locationId,
            'verified_location_name' => $locationDecision?->locationName,
            'verified_geofence_type' => $locationDecision?->geofenceType,
            'distance_to_geofence_meters' => $locationDecision?->distanceMeters,
        ];

        $dateStr = now()->toDateString();
        $lock = Cache::lock("attendance:checkout:{$companyId}:{$employee->id}:{$dateStr}", 15);

        if (!$lock->get()) {
            return response()->json([
                'ok' => false,
                'code' => 'already_processing',
                'message' => tr('Your attendance request is already being processed.'),
            ], 409);
        }

        try {
            $res = DB::transaction(function () use ($companyId, $employee, $dateStr, $data, $locationMeta) {
                $log = AttendanceDailyLog::where('saas_company_id', $companyId)
                    ->where('employee_id', $employee->id)
                    ->where('attendance_date', $dateStr)
                    ->lockForUpdate()
                    ->first();

                if (!$log) {
                    return ['ok' => false, 'message' => tr('No record found for today.')];
                }

                return $this->attendanceService->recordCheckOut($log, $data['method'], $data['lat'] ?? null, $data['lng'] ?? null, $locationMeta);
            });
        } finally {
            optional($lock)->release();
        }

        if ($locationDecision !== null) {
            $res['location_gate'] = $locationDecision->toArray();
        }

        return response()->json($res);
    }

    protected function resolveAttendanceMethodContext(int $companyId, Employee $employee, string $method, $user = null): object
    {
        $ttl = now()->addSeconds(15);

        $methodConfig = Cache::remember(
            "attendance:config:method:{$companyId}:{$method}",
            $ttl,
            function () use ($companyId, $method): array {
                $row = AttendanceMethod::query()
                    ->where('saas_company_id', $companyId)
                    ->where('method', $method)
                    ->first(['is_enabled', 'device_count']);

                return [
                    'is_enabled' => (bool) ($row->is_enabled ?? false),
                    'device_count' => (int) ($row->device_count ?? 0),
                ];
            }
        );

        $globalEnabled = (bool) $methodConfig['is_enabled'];

        $policyConfig = Cache::remember(
            "attendance:config:policies:{$companyId}",
            $ttl,
            function () use ($companyId): array {
                $rows = DB::table('attendance_policies')
                    ->where('saas_company_id', $companyId)
                    ->orderByDesc('is_default')
                    ->orderByDesc('id')
                    ->get(['id', 'tracking_mode', 'is_default']);

                return [
                    'default' => (string) ($rows->first()->tracking_mode ?? 'check_in_out'),
                    'by_id' => $rows
                        ->mapWithKeys(fn ($row) => [
                            (int) $row->id => (string) $row->tracking_mode,
                        ])
                        ->all(),
                ];
            }
        );

        $trackingMode = $policyConfig['default'] ?: 'check_in_out';

        $groups = EmployeeGroup::query()
            ->where('saas_company_id', $companyId)
            ->whereHas(
                'employees',
                fn ($query) => $query->where('employees.id', (int) $employee->id)
            )
            ->get(['id', 'applied_policy_id']);

        if ($groups->isEmpty()) {
            $allowed = $globalEnabled;
        } else {
            $appliedPolicyId = (int) (
                $groups
                    ->first(fn ($group) => (int) ($group->applied_policy_id ?? 0) > 0)
                    ?->applied_policy_id ?? 0
            );

            if ($appliedPolicyId > 0) {
                $trackingMode = $policyConfig['by_id'][$appliedPolicyId]
                    ?? $trackingMode;
            }

            $allowed = EmployeeGroup::query()
                ->whereIn('id', $groups->pluck('id'))
                ->whereHas(
                    'allowedMethods',
                    fn ($query) => $query
                        ->where('method', $method)
                        ->where('is_allowed', true)
                )
                ->exists();
        }

        $gpsLocations = collect();

        if ($method === 'gps') {
            $allLocations = Cache::remember(
                "attendance:config:gps-locations:{$companyId}",
                $ttl,
                fn (): array => AttendanceGpsLocation::query()
                    ->where('saas_company_id', $companyId)
                    ->where('is_active', true)
                    ->get([
                        'id',
                        'name',
                        'lat',
                        'lng',
                        'radius_meters',
                        'geofence_type',
                        'boundary_geojson',
                        'employee_group_id',
                        'branch_id',
                    ])
                    ->map(fn ($location) => [
                        'id' => (int) $location->id,
                        'name' => (string) $location->name,
                        'lat' => (float) $location->lat,
                        'lng' => (float) $location->lng,
                        'radius_meters' => (int) $location->radius_meters,
                        'geofence_type' => (string) ($location->geofence_type ?: AttendanceGpsLocation::GEOFENCE_TYPE_CIRCLE),
                        'boundary_geojson' => $location->boundary_geojson,
                        'employee_group_id' => $location->employee_group_id !== null
                            ? (int) $location->employee_group_id
                            : null,
                        'branch_id' => $location->branch_id !== null
                            ? (int) $location->branch_id
                            : null,
                    ])
                    ->all()
            );

            $employeeGroupIds = $groups
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            /*
             * Branch access and attendance-method availability are two
             * separate concerns.
             *
             * null  = all branches
             * array = only these branches
             */
            $allowedBranchIds = $this->resolveAttendanceBranchIds(
                $user,
                $employee,
                $companyId
            );

            $attendanceBranchScope = (string) (
                $user->access_scope ?? 'all_branches'
            );

            /*
             * First apply Employee Group rules independently.
             *
             * Important:
             * An employee without groups keeps the previous behaviour of
             * seeing group locations, but branch restrictions must still
             * be enforced.
             */
            $groupFilteredLocations = collect($allLocations)
                ->filter(function (array $location) use ($employeeGroupIds): bool {
                    return empty($employeeGroupIds)
                        || $location['employee_group_id'] === null
                        || in_array(
                            (int) $location['employee_group_id'],
                            $employeeGroupIds,
                            true
                        );
                })
                ->values();

            /*
             * Keep the group-compatible locations from all branches.
             * They are used only to distinguish:
             * - outside every attendance location
             * - inside a location belonging to a forbidden branch
             */
            $allBranchGpsLocationIds = $groupFilteredLocations
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $gpsLocations = $groupFilteredLocations
                ->filter(function (array $location) use ($allowedBranchIds): bool {
                    if ($location['branch_id'] === null) {
                        return true;
                    }

                    if ($allowedBranchIds === null) {
                        return true;
                    }

                    return in_array(
                        (int) $location['branch_id'],
                        $allowedBranchIds,
                        true
                    );
                })
                ->map(fn (array $location) => (object) [
                    'id' => $location['id'],
                    'name' => $location['name'],
                    'lat' => $location['lat'],
                    'lng' => $location['lng'],
                    'radius_meters' => $location['radius_meters'],
                    'geofence_type' => $location['geofence_type'],
                    'boundary_geojson' => $location['boundary_geojson'],
                ])
                ->values();
        }

        return (object) [
            'ok' => true,
            'data' => (object) [
                'tracking_mode' => $trackingMode,
                'methods' => (object) [
                    $method => (object) [
                        'enabled' => $globalEnabled,
                        'allowed' => $allowed,
                        'effective' => $globalEnabled && $allowed,
                        'device_count' => (int) $methodConfig['device_count'],
                    ],
                ],
                'gps_locations' => $gpsLocations,
                'gps_location_ids_all_branches' => $allBranchGpsLocationIds ?? [],
                'attendance_branch_scope' => $attendanceBranchScope ?? 'all_branches',
                'allowed_branch_ids' => $allowedBranchIds ?? null,
            ],
        ];
    }

    /**
     * Resolve the branches in which this user may record attendance.
     *
     * null means unrestricted / all branches.
     */
    protected function resolveAttendanceBranchIds(
        $user,
        Employee $employee,
        int $companyId
    ): ?array {
        if (! $user) {
            $branchId = (int) ($employee->branch_id ?? 0);

            return $branchId > 0 ? [$branchId] : [];
        }

        /*
         * Prefer the application's canonical restriction helper when it
         * exists so attendance follows the same access-control rules as
         * the rest of the system.
         */
        if (method_exists($user, 'restrictedBranchIds')) {
            $restricted = $user->restrictedBranchIds();

            if ($restricted === null) {
                return null;
            }

            return collect($restricted)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        if (
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole([
                'saas-admin',
                'super-admin',
                'company-admin',
                'system-admin',
            ])
        ) {
            return null;
        }

        $scope = (string) ($user->access_scope ?? 'all_branches');

        if ($scope === 'all_branches') {
            return null;
        }

        if (in_array($scope, ['my_branch', 'branch'], true)) {
            $branchId = (int) (
                $employee->branch_id
                ?? $user->branch_id
                ?? 0
            );

            return $branchId > 0 ? [$branchId] : [];
        }

        return DB::table('branch_user_access')
            ->where('user_id', (int) $user->id)
            ->where('saas_company_id', $companyId)
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Return a branch-specific error only when the submitted GPS
     * coordinates actually match an attendance location belonging to
     * another permitted company/group branch.
     */
    protected function attendanceBranchScopeViolationResponse(
        $context,
        int $companyId,
        Employee $employee,
        array $payload
    ) {
        $scope = (string) (
            $context->data->attendance_branch_scope
            ?? 'all_branches'
        );

        if ($scope === 'all_branches') {
            return null;
        }

        $allBranchLocationIds = collect(
            $context->data->gps_location_ids_all_branches ?? []
        )
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($allBranchLocationIds)) {
            return null;
        }

        $anyBranchDecision = $this->locationGateService->evaluateOnline(
            companyId: $companyId,
            employee: $employee,
            payload: $payload,
            allowedLocationIds: $allBranchLocationIds,
        );

        /*
         * The location does not match any company/group attendance
         * location, so preserve the original geofence error.
         */
        if (! $anyBranchDecision->allowed) {
            return null;
        }

        $isArabic = str_starts_with(
            strtolower((string) app()->getLocale()),
            'ar'
        );

        $ownBranchOnly = in_array(
            $scope,
            ['my_branch', 'branch'],
            true
        );

        $message = $isArabic
            ? (
                $ownBranchOnly
                    ? 'لا يمكنك التحضير إلا في فرعك المحدد.'
                    : 'لا يمكنك التحضير إلا في الفروع المصرح لك بها.'
            )
            : (
                $ownBranchOnly
                    ? 'You can only check in at your assigned branch.'
                    : 'You can only check in at your allowed branches.'
            );

        return response()->json([
            'ok' => false,
            'code' => 'branch_not_allowed',
            'message' => $message,
        ], 403);
    }

    protected function attendanceMethodUnavailableResponse($prep, $method)
    {
        $methods = $prep->data->methods ?? null;
        
        if (!$methods) {
            return response()->json([
                'ok' => false,
                'code' => 'method_unavailable',
                'message' => tr('This attendance method is not available.')
            ], 422);
        }

        $methodObj = $methods->{$method} ?? null;

        if (!$methodObj || empty($methodObj->effective)) {
            return response()->json([
                'ok' => false,
                'code' => 'method_unavailable',
                'message' => tr('This attendance method is not available.')
            ], 422);
        }

        return null;
    }
}