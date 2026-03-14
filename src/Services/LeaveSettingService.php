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
    public function getPolicies(array $filters, int $perPage = 10)
    {
        return LeavePolicy::query()
            ->when($filters['search'], fn($q) => $q->where('name', 'like', "%{$filters['search']}%"))
            ->when($filters['status'] !== 'all', fn($q) => $q->where('is_active', $filters['status'] === 'active'))
            ->when($filters['gender'] !== 'all', fn($q) => $q->where('gender', $filters['gender']))
            ->latest()
            ->paginate($perPage);
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

    /**
     * Save/Update Permission Settings.
     */
    public function savePermissionSettings(array $data): PermissionPolicy
    {
        $policy = PermissionPolicy::firstOrNew(['saas_company_id' => auth()->user()->saas_company_id]);
        $policy->fill($data);
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
}
