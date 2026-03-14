<?php

namespace Athka\SystemSettings\Services;

use Athka\SystemSettings\Models\AttendancePolicy;
use Athka\SystemSettings\Models\AttendanceGraceSetting;
use Athka\SystemSettings\Models\AttendancePenaltyPolicy;
use Athka\SystemSettings\Models\UnexcusedAbsencePolicy;
use Athka\SystemSettings\Models\AttendanceGpsLocation;
use Illuminate\Support\Facades\DB;

class AttendanceSettingService
{
    /**
     * Get or create the default attendance policy for a company.
     */
    public function getDefaultPolicy(int $companyId): AttendancePolicy
    {
        return AttendancePolicy::firstOrCreate(
            ['saas_company_id' => $companyId, 'is_default' => true],
            ['name' => 'Default Policy', 'tracking_mode' => 'check_in_out']
        );
    }

    /**
     * Get or create grace settings for a company.
     */
    public function getGraceSettings(int $companyId): AttendanceGraceSetting
    {
        return AttendanceGraceSetting::firstOrCreate(
            ['saas_company_id' => $companyId],
            [
                'late_grace_minutes' => 15,
                'early_leave_grace_minutes' => 15,
                'auto_checkout_after_minutes' => 0,
            ]
        );
    }

    /**
     * Save basic penalty settings.
     */
    public function savePenalty(int $companyId, int $policyId, string $type, array $data): void
    {
        AttendancePenaltyPolicy::updateOrCreate(
            [
                'policy_id' => $policyId,
                'saas_company_id' => $companyId,
                'violation_type' => $type,
                'recurrence_count' => 1,
            ],
            array_merge($data, ['penalty_action' => 'deduction', 'is_active' => true])
        );
    }

    /**
     * Save absence policy settings.
     */
    public function saveAbsencePolicy(int $companyId, int $policyId, array $data): void
    {
        UnexcusedAbsencePolicy::updateOrCreate(
            [
                'policy_id' => $policyId,
                'saas_company_id' => $companyId,
                'absence_reason_type' => 'no_notice',
            ],
            array_merge($data, ['penalty_action' => 'deduction', 'is_active' => true])
        );
    }

    /**
     * Save/Update GPS Location.
     */
    public function saveGpsLocation(int $companyId, array $data, ?int $id = null): AttendanceGpsLocation
    {
        $location = $id ? AttendanceGpsLocation::find($id) : new AttendanceGpsLocation();
        $location->fill(array_merge($data, ['saas_company_id' => $companyId]));
        $location->save();
        return $location;
    }
}
