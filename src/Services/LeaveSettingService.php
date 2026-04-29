<?php

namespace Athka\SystemSettings\Services;

use Athka\SystemSettings\Models\LeavePolicy;
use Athka\SystemSettings\Models\LeavePolicyYear;
use Athka\SystemSettings\Models\PermissionPolicy;
use Illuminate\Support\Facades\DB;

class LeaveSettingService
{
    /**
     * Get filtered policies.
     */
    public function getPolicies(int $companyId, array $filters, int $perPage = 10)
    {
        $calendarType = $this->getCompanyCalendarType($companyId);

        return LeavePolicy::query()
            ->where('company_id', $companyId)
            ->when($filters['search'], fn($q) => $q->where('name', 'like', "%{$filters['search']}%"))
            ->when($filters['status'] !== 'all', fn($q) => $q->where('is_active', $filters['status'] === 'active'))
            ->when($filters['gender'] !== 'all', fn($q) => $q->where('gender', $filters['gender']))
            ->when(
                isset($filters['show_in_app']) && $filters['show_in_app'] !== 'all',
                fn($q) => $q->where('show_in_app', $filters['show_in_app'] === 'yes')
            )
            ->when(
                isset($filters['requires_attachment']) && $filters['requires_attachment'] !== 'all',
                fn($q) => $q->where('requires_attachment', $filters['requires_attachment'] === 'yes')
            )
            ->when(
                isset($filters['year_id']) && $filters['year_id'] !== 'all',
                fn($q) => $q->where('policy_year_id', $filters['year_id']),
                function ($q) use ($calendarType) {
                    // When year_id is 'all', we still restrict to the company's calendar type
                    $q->whereHas('year', function ($yq) use ($calendarType) {
                        if ($calendarType === 'hijri') {
                            $yq->whereBetween('year', [1300, 1600]);
                        } else {
                            $yq->whereBetween('year', [1900, 2500]);
                        }
                    });
                }
            )
            ->latest()
            ->paginate($perPage);
    }

    private function getCompanyCalendarType(int $companyId): string
    {
        return \Illuminate\Support\Facades\Cache::remember("company_calendar_type_{$companyId}", 3600, function () use ($companyId) {
            $row = \Illuminate\Support\Facades\DB::table('operational_calendars')
                ->where('company_id', $companyId)
                ->first(['calendar_type']);
            return strtolower((string) ($row->calendar_type ?? 'gregorian'));
        });
    }

    public function getCurrentCalendarYear(int $companyId): int
    {
        if ($this->getCompanyCalendarType($companyId) === 'hijri' && class_exists(\IntlCalendar::class)) {
            $tz = \IntlTimeZone::createTimeZone('UTC');
            $cal = \IntlCalendar::createInstance($tz, 'en_US@calendar=islamic-umalqura');

            return (int) $cal->get(\IntlCalendar::FIELD_YEAR);
        }

        return (int) now()->year;
    }

    /**
     * Save/Update a Leave Policy.
     */
    public function savePolicy(array $data, ?int $id = null): LeavePolicy
    {
        $policy = $id ? LeavePolicy::find($id) : new LeavePolicy();
        $policy->fill($data);
        $policy->save();
        return $policy;
    }

    public function savePermissionSettings(array $data, int $yearId): PermissionPolicy
    {
        $companyId = auth()->user()->saas_company_id;
        $policy = PermissionPolicy::firstOrNew([
            'company_id' => $companyId,
            'policy_year_id' => $yearId
        ]);
        $policy->fill(array_merge($data, [
            'company_id' => $companyId,
            'policy_year_id' => $yearId
        ]));
        $policy->save();
        return $policy;
    }

    /**
     * Copy a policy with all its settings.
     */
    public function copyPolicy(int $id, string $newName): LeavePolicy
    {
        $original = LeavePolicy::findOrFail($id);
        $new = $original->replicate();
        $new->name = $newName;
        $new->save();
        return $new;
    }

    /**
     * Ensure default year and annual leave policy exist for a company.
     */
    public function ensureDefaultConfiguration(int $companyId): void
    {
        $currentYearValue = $this->getCurrentCalendarYear($companyId);
        $calendarType = $this->getCompanyCalendarType($companyId);

        // 1. Check if ANY year of the same calendar type exists
        $anyYearExists = LeavePolicyYear::where('company_id', $companyId)
            ->where(function ($q) use ($calendarType) {
                if ($calendarType === 'hijri') {
                    $q->whereBetween('year', [1300, 1600]);
                } else {
                    $q->whereBetween('year', [1900, 2500]);
                }
            })->exists();

        // 2. Ensure current year exists for the specific calendar type
        $yearRecord = LeavePolicyYear::where('company_id', $companyId)
            ->where('year', $currentYearValue)
            ->where(function ($q) use ($calendarType) {
                if ($calendarType === 'hijri') {
                    $q->whereBetween('year', [1300, 1600]);
                } else {
                    $q->whereBetween('year', [1900, 2500]);
                }
            })
            ->first();

        if (!$yearRecord) {
            // Determine if it should be active (only if no other year of this type is active)
            $hasActiveOfSameType = LeavePolicyYear::where('company_id', $companyId)
                ->where('is_active', true)
                ->where(function ($q) use ($calendarType) {
                    if ($calendarType === 'hijri') {
                        $q->whereBetween('year', [1300, 1600]);
                    } else {
                        $q->whereBetween('year', [1900, 2500]);
                    }
                })->exists();

            $yearRecord = LeavePolicyYear::create([
                'company_id' => $companyId,
                'year' => $currentYearValue,
                'starts_on' => "$currentYearValue-01-01",
                'ends_on' => $calendarType === 'hijri' ? "$currentYearValue-12-29" : "$currentYearValue-12-31",
                'is_active' => !$hasActiveOfSameType // Only make it active if none exists
            ]);
        }

        // 3. Ensure default Annual Leave exists for this year
        $exists = LeavePolicy::where('policy_year_id', $yearRecord->id)
            ->where(function ($q) {
                $q->where('name', 'like', '%Annual%')
                    ->orWhere('name', 'like', '%إجازة سنوية%')
                    ->orWhere('name', 'like', '%سنوية%')
                    ->orWhere('leave_type', 'annual');
            })
            ->exists();

        if (!$exists) {
            $otherinfo = \Athka\Saas\Models\SaasCompanyOtherinfo::where('company_id', $companyId)->first();
            $days = $otherinfo->default_annual_leave_days ?? 30;

            LeavePolicy::create([
                'company_id' => $companyId,
                'policy_year_id' => $yearRecord->id,
                'name' => 'إجازة سنوية',
                'leave_type' => 'annual',
                'days_per_year' => $days,
                'gender' => 'all',
                'is_active' => true,
                'show_in_app' => true,
                'requires_attachment' => false,
                'description' => 'إجازة سنوية افتراضية (' . $days . ' يوماً)',
                'settings' => [
                    'accrual_method' => 'annual_grant',
                    'monthly_accrual_rate' => round($days / 12, 2),
                    'allow_carryover' => true,
                    'carryover_days' => 15,
                    'weekend_policy' => 'exclude',
                    'deduction_policy' => 'balance_only',
                    'meta' => ['system_key' => 'annual_default']
                ]
            ]);
        }
    }
}
