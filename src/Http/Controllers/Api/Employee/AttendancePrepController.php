<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Employee;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;

use Athka\SystemSettings\Models\AttendancePolicy;
use Athka\SystemSettings\Models\AttendanceGraceSetting;
use Athka\SystemSettings\Models\AttendanceMethod;
use Athka\SystemSettings\Models\AttendanceGpsLocation;
use Athka\SystemSettings\Models\AttendanceDevice;
use Athka\SystemSettings\Models\EmployeeGroup;

use Athka\Employees\Models\Employee;

class AttendancePrepController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $companyId = (int) ($user->saas_company_id ?? $user->company_id ?? 0);
        if ($companyId <= 0) {
            return response()->json(['ok' => false, 'message' => 'Company context not found'], 422);
        }

        // ✅ resolve employee (عدة احتمالات بدون ما نكسر)
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

        // ✅ default policy
        $defaultPolicy = AttendancePolicy::query()
            ->where('saas_company_id', $companyId)
            ->where('is_default', true)
            ->first();

        if (! $defaultPolicy) {
            // لا ننشئ تلقائياً من الـ API حتى ما نسبب بيانات بدون قصد
            $defaultPolicy = AttendancePolicy::query()
                ->where('saas_company_id', $companyId)
                ->orderByDesc('id')
                ->first();
        }

        $trackingMode = (string) ($defaultPolicy->tracking_mode ?? 'check_in_out');

        // ✅ global grace
        $grace = AttendanceGraceSetting::query()
            ->where('saas_company_id', $companyId)
            ->where('is_global_default', true)
            ->first();

        $graceData = [
            'late_grace_minutes' => (int) ($grace->late_grace_minutes ?? 15),
            'early_leave_grace_minutes' => (int) ($grace->early_leave_grace_minutes ?? 10),
            'auto_checkout_after_minutes' => (int) ($grace->auto_checkout_after_minutes ?? 120),
            'auto_checkout_penalty_enabled' => (bool) ($grace->auto_checkout_penalty_enabled ?? false),
            'auto_checkout_penalty_amount' => (float) ($grace->auto_checkout_penalty_amount ?? 0),
            'auto_checkout_deduction_type' => (string) ($grace->auto_checkout_deduction_type ?? 'fixed'),
        ];

        // ✅ employee groups (لو الموظف معروف)
        $groups = collect();
        if ($employee) {
            $groups = EmployeeGroup::query()
                ->with(['appliedPolicy', 'graceSetting'])
                ->where('saas_company_id', $companyId)
                ->whereHas('employees', fn ($q) => $q->where('employees.id', (int) $employee->id))
                ->get();
        }

        // ✅ لو فيه مجموعة سياسة خاصة نفضلها
        $special = $groups->first(fn ($g) => (int) ($g->applied_policy_id ?? 0) > 0);
        if ($special && $special->appliedPolicy) {
            $trackingMode = (string) ($special->appliedPolicy->tracking_mode ?? $trackingMode);
        }

        // ✅ لو فيه grace مخصص للمجموعة نستخدمه
        if ($special && (string) ($special->grace_source ?? '') === 'custom' && $special->graceSetting) {
            $graceData['late_grace_minutes'] = (int) ($special->graceSetting->late_grace_minutes ?? $graceData['late_grace_minutes']);
            $graceData['early_leave_grace_minutes'] = (int) ($special->graceSetting->early_leave_grace_minutes ?? $graceData['early_leave_grace_minutes']);
            $graceData['auto_checkout_after_minutes'] = (int) ($special->graceSetting->auto_checkout_after_minutes ?? $graceData['auto_checkout_after_minutes']);
        }

        // ✅ methods enabled globally
        $methodModels = AttendanceMethod::query()
            ->where('saas_company_id', $companyId)
            ->get()
            ->keyBy('method');

        $globalEnabled = [
            'gps' => (bool) ($methodModels['gps']->is_enabled ?? false),
            'fingerprint' => (bool) ($methodModels['fingerprint']->is_enabled ?? false),
            'nfc' => (bool) ($methodModels['nfc']->is_enabled ?? false),
        ];

        // ✅ allowed methods from groups (union)
        $allowed = [
            'gps' => false,
            'fingerprint' => false,
            'nfc' => false,
        ];

        if ($groups->isEmpty()) {
            // لو ما في مجموعات: نخليها تعتمد على تفعيل الشركة
            $allowed = $globalEnabled;
        } else {
            foreach ($groups as $g) {
                if (method_exists($g, 'allowedMethods')) {
                    $arr = $g->allowedMethods()->where('is_allowed', true)->pluck('method')->toArray();
                    foreach ($arr as $m) {
                        if (isset($allowed[$m])) $allowed[$m] = true;
                    }
                }
            }
        }

        // ✅ effective methods = enabled AND allowed
        $methods = [];
        foreach (['gps', 'fingerprint', 'nfc'] as $m) {
            $methods[$m] = [
                'enabled' => (bool) ($globalEnabled[$m] ?? false),
                'allowed' => (bool) ($allowed[$m] ?? false),
                'effective' => (bool) (($globalEnabled[$m] ?? false) && ($allowed[$m] ?? false)),
                'device_count' => (int) ($methodModels[$m]->device_count ?? 0),
            ];
        }

        // ✅ gps locations filter (حسب المجموعة أو الفرع)
        $gpsQ = AttendanceGpsLocation::query()
            ->where('saas_company_id', $companyId)
            ->where('is_active', true);

        $employeeGroupIds = $groups->pluck('id')->map(fn ($x) => (int) $x)->values()->all();

        if (!empty($employeeGroupIds)) {
            $gpsQ->where(function ($q) use ($employeeGroupIds, $employee) {
                $q->whereIn('employee_group_id', $employeeGroupIds);

                // branch match لو عند الموظف department_id
                if ($employee && isset($employee->department_id)) {
                    $q->orWhere('branch_id', (int) $employee->department_id);
                }
            });
        }

        $gpsLocations = $gpsQ->get()->map(fn ($l) => [
            'id' => (int) $l->id,
            'name' => (string) $l->name,
            'lat' => (float) $l->lat,
            'lng' => (float) $l->lng,
            'radius_meters' => (int) $l->radius_meters,
            'address_text' => (string) ($l->address_text ?? ''),
            'branch_id' => $l->branch_id ? (int) $l->branch_id : null,
            'employee_group_id' => $l->employee_group_id ? (int) $l->employee_group_id : null,
        ])->values();

        // ✅ devices
        $devices = AttendanceDevice::query()
            ->where('saas_company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('device_type')
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                'id' => (int) $d->id,
                'type' => (string) $d->device_type, // fingerprint|nfc
                'name' => (string) $d->name,
                'serial_no' => (string) ($d->serial_no ?? ''),
                'branch_id' => $d->branch_id ? (int) $d->branch_id : null,
                'location_in_branch' => (string) ($d->location_in_branch ?? ''),
            ])->values();

        // ✅ exceptional day check (today)
        $exceptionalDayInfo = null;
        if ($employee && class_exists(\Athka\SystemSettings\Services\WorkScheduleService::class)) {
            $wsService = app(\Athka\SystemSettings\Services\WorkScheduleService::class);
            $exceptionalDay = $wsService->getExceptionalDay($companyId, now()->toDateString(), $employee);
            $officialHolidays = $wsService->getHolidays($companyId, now()->toDateString(), now()->toDateString());
            $officialHoliday = $officialHolidays->first();

            if ($exceptionalDay || $officialHoliday) {
                $isHoliday = $exceptionalDay ? (bool)($exceptionalDay->is_holiday ?? true) : true;
                $name = $exceptionalDay ? $exceptionalDay->name : ($officialHoliday->template?->name ?? 'Holiday');
                $msgPart = (app()->getLocale() == 'ar' ? 'اليوم هو يوم استثنائي (عطلة)' : tr('Today is an exceptional day'));

                $exceptionalDayInfo = [
                    'id'   => $exceptionalDay ? $exceptionalDay->id : $officialHoliday->id,
                    'name' => $name,
                    'is_holiday' => $isHoliday,
                    'message' => $msgPart . ': ' . $name
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'tracking_mode' => $trackingMode,
                'grace' => $graceData,
                'methods' => $methods,
                'gps_locations' => $gpsLocations,
                'devices' => $devices,
                'exceptional_day' => $exceptionalDayInfo,
            ],
        ]);
    }
}
