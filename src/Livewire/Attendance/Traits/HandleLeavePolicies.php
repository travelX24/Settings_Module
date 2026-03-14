<?php

namespace Athka\SystemSettings\Livewire\Attendance\Traits;

use Athka\SystemSettings\Models\LeavePolicy;

trait HandleLeavePolicies
{
    public function openCreate()
    {
        $this->resetValidation();
        $this->reset(['name', 'leave_type', 'days_per_year', 'editingId', 'description']);
        $this->createOpen = true;
    }

    public function savePolicy()
    {
        $this->authorize('settings.attendance.manage');
        $this->validate([
            'name' => 'required|min:2',
            'days_per_year' => 'required|numeric',
        ]);

        $data = [
            'name' => $this->name,
            'leave_type' => $this->leave_type,
            'days_per_year' => $this->days_per_year,
            'gender' => $this->gender,
            'is_active' => $this->is_active,
            'show_in_app' => $this->show_in_app,
            'requires_attachment' => $this->requires_attachment,
            'description' => $this->description,
            'settings' => [
                'accrual_method' => $this->accrual_method,
                'monthly_accrual_rate' => $this->monthly_accrual_rate,
                'allow_carryover' => $this->allow_carryover,
                'carryover_days' => $this->carryover_days,
                'weekend_policy' => $this->weekend_policy,
                'deduction_policy' => $this->deduction_policy,
            ]
        ];

        $this->leaveSettingService->savePolicy($data, $this->editingId ?: null);

        $this->reset(['createOpen', 'editOpen', 'editingId']);
        $this->dispatch('toast', type: 'success', message: tr('Policy saved successfully.'));
    }

    public function openEdit($id)
    {
        $this->authorize('settings.attendance.manage');
        $policy = LeavePolicy::findOrFail($id);
        $this->editingId = $id;
        $this->name = $policy->name;
        $this->leave_type = $policy->leave_type;
        $this->days_per_year = $policy->days_per_year;
        $this->gender = $policy->gender;
        $this->is_active = $policy->is_active;
        $this->show_in_app = $policy->show_in_app;
        $this->requires_attachment = $policy->requires_attachment;
        $this->description = $policy->description;
        
        $settings = $policy->settings ?? [];
        $this->accrual_method = $settings['accrual_method'] ?? 'annual_grant';
        $this->monthly_accrual_rate = $settings['monthly_accrual_rate'] ?? 2.5;
        $this->allow_carryover = $settings['allow_carryover'] ?? true;
        $this->carryover_days = $settings['carryover_days'] ?? 15;
        $this->weekend_policy = $settings['weekend_policy'] ?? 'exclude';
        $this->deduction_policy = $settings['deduction_policy'] ?? 'balance_only';

        $this->editOpen = true;
    }

    public function confirmDelete($id)
    {
        $this->editingId = $id;
        $this->deleteOpen = true;
    }

    public function copyPolicy($id)
    {
        $this->leaveSettingService->copyPolicy($id, $this->name . ' (Copy)');
        $this->dispatch('toast', type: 'success', message: tr('Policy copied.'));
    }

    public function deletePolicy($id)
    {
        $this->authorize('settings.attendance.manage');
        LeavePolicy::destroy($id);
        $this->dispatch('toast', type: 'success', message: tr('Policy deleted.'));
    }
}
