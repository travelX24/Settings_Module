<?php

namespace Athka\SystemSettings\Livewire\Attendance\Traits;

use Athka\SystemSettings\Models\EmployeeGroup;
use Athka\SystemSettings\Models\AttendancePolicy;
use Athka\SystemSettings\Models\AttendanceGraceSetting;

trait HandleGroupSettings
{
    public function openGroupModal($id = null)
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();
        
        $companyId = auth()->user()->saas_company_id;
        
        if ($id) {
            $group = EmployeeGroup::where('saas_company_id', $companyId)
                ->with(['employees', 'allowedMethods', 'graceSetting'])
                ->findOrFail($id);
            $this->selectedId = $id;
            $this->isEditingGroup = true;
            
            $policyKey = 'general';
            if ($group->applied_policy_id && $group->applied_policy_id != $this->defaultPolicy->id) {
                $policyKey = 'specific';
            }

            $this->newGroup = [
                'name' => $group->name,
                'description' => $group->description,
                'policy' => $policyKey,
                'tracking_mode' => $group->appliedPolicy ? $group->appliedPolicy->tracking_mode : 'check_in_out',
                'methods' => $group->allowedMethods->where('is_allowed', true)->pluck('method')->toArray(),
                'grace_periods_type' => $group->grace_source ?? 'general',
                'custom_grace_periods' => [
                    'late_arrival' => $group->graceSetting ? $group->graceSetting->late_grace_minutes : 0,
                    'early_departure' => $group->graceSetting ? $group->graceSetting->early_leave_grace_minutes : 0,
                    'auto_departure' => $group->graceSetting ? $group->graceSetting->auto_checkout_after_minutes : 0,
                ],
                'employee_ids' => $group->employees->pluck('id')->map(fn($id) => (string)$id)->toArray()
            ];
        } else {
            $this->selectedId = null;
            $this->isEditingGroup = false;
            $this->newGroup = [
                'name' => '',
                'description' => '',
                'policy' => 'general',
                'tracking_mode' => 'check_in_out',
                'methods' => [],
                'grace_periods_type' => 'general',
                'custom_grace_periods' => ['late_arrival' => 0, 'early_departure' => 0, 'auto_departure' => 0],
                'employee_ids' => []
            ];
        }
        $this->showGroupModal = true;
    }

    public function editGroup($id)
    {
        $this->openGroupModal($id);
    }

    public function saveGroup()
    {
        $this->authorize('settings.attendance.manage');
        
        $rules = [
            'newGroup.name' => 'required|min:3',
            'newGroup.policy' => 'required',
        ];

        if ($this->newGroup['policy'] === 'specific') {
            $rules['newGroup.tracking_mode'] = 'required';
        }

        $this->validate($rules);

        $companyId = auth()->user()->saas_company_id;

        // 1. Handle Policy
        $policyId = $this->defaultPolicy->id;
        if ($this->newGroup['policy'] === 'specific') {
            $policy = AttendancePolicy::updateOrCreate(
                [
                    'saas_company_id' => $companyId,
                    'is_default' => false,
                    'name' => 'Policy for Group: ' . $this->newGroup['name']
                ],
                ['tracking_mode' => $this->newGroup['tracking_mode']]
            );
            $policyId = $policy->id;
        }

        // 2. Handle Grace Settings
        $graceId = null;
        if ($this->newGroup['grace_periods_type'] === 'custom') {
            $grace = AttendanceGraceSetting::create([
                'saas_company_id' => $companyId,
                'late_grace_minutes' => $this->newGroup['custom_grace_periods']['late_arrival'] ?? 0,
                'early_leave_grace_minutes' => $this->newGroup['custom_grace_periods']['early_departure'] ?? 0,
                'auto_checkout_after_minutes' => $this->newGroup['custom_grace_periods']['auto_departure'] ?? 0,
            ]);
            $graceId = $grace->id;
        }

        // 3. Save Group
        $group = $this->selectedId 
            ? EmployeeGroup::where('saas_company_id', $companyId)->findOrFail($this->selectedId) 
            : new EmployeeGroup();
        $group->fill([
            'saas_company_id' => $companyId,
            'name' => $this->newGroup['name'],
            'description' => $this->newGroup['description'],
            'applied_policy_id' => $policyId,
            'grace_source' => $this->newGroup['grace_periods_type'],
            'grace_setting_id' => $graceId,
            'created_by_user_id' => auth()->id(),
            'is_active' => true,
        ]);
        $group->save();

        // 4. Sync Employees
        $group->employees()->sync($this->newGroup['employee_ids']);

        // 5. Sync Methods
        $group->allowedMethods()->delete();
        foreach ($this->newGroup['methods'] as $method) {
            $group->allowedMethods()->create([
                'method' => $method,
                'is_allowed' => true
            ]);
        }

        $this->showGroupModal = false;
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Employee group saved successfully.'));
    }

    public function deleteGroup($id)
    {
        $this->authorize('settings.attendance.manage');
        $companyId = auth()->user()->saas_company_id;
        EmployeeGroup::where('saas_company_id', $companyId)->where('id', $id)->delete();
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Employee group deleted.'));
    }
}
