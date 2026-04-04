<?php

namespace Athka\SystemSettings\Livewire\Attendance\Traits;

use Athka\SystemSettings\Models\LeavePolicy;

trait HandleLeavePolicies
{
    protected function resetCreateLeaveForm(): void
    {
        $this->resetValidation();
        $this->resetErrorBag();

        $this->editingId = null;
        $this->editingNameLocked = false;

        // Basic information
        $this->name = '';
        $this->leave_type = 'annual';
        $this->days_per_year = 30;
        $this->description = '';
        $this->gender = 'all';
        $this->is_active = true;
        $this->show_in_app = true;
        $this->requires_attachment = false;

        // Additional settings
        $this->accrual_method = 'annual_grant';
        $this->monthly_accrual_rate = 2.5;
        $this->allow_carryover = true;
        $this->carryover_days = 15;
        $this->weekend_policy = 'exclude';
        $this->deduction_policy = 'balance_only';
        $this->max_balance = 0;
        $this->duration_unit = 'full_day';
        $this->notice_min_days = 0;
        $this->notice_max_advance_days = 0;
        $this->allow_retroactive = false;

        // Notes
        $this->note_required = false;
        $this->note_text = '';
        $this->note_ack_required = false;

        // Attachments / exclusions
        $this->attachment_types = ['pdf', 'jpg', 'png'];
        $this->selected_leave_excluded_contract_types = [];
    }

    public function openCreate()
    {
        $this->resetCreateLeaveForm();
        $this->createOpen = true;
    }

    public function closeCreate()
    {
        $this->createOpen = false;
        $this->resetCreateLeaveForm();
    }

    public function saveCreate()
    {
        $this->savePolicy();
    }

    public function saveEdit()
    {
        if (!$this->editingId) {
            return;
        }
        $this->savePolicy();
    }

    public function savePolicy()
    {
        $this->authorize('settings.attendance.manage');
        $this->validate([
            'name' => 'required|min:2',
            'days_per_year' => 'required|numeric',
        ]);

        $data = [
            'company_id' => auth()->user()->saas_company_id,
            'policy_year_id' => $this->selectedYearId,
            'name' => $this->name,
            'leave_type' => $this->leave_type,
            'days_per_year' => $this->days_per_year,
            'gender' => $this->gender,
            'is_active' => $this->is_active,
            'show_in_app' => $this->show_in_app,
            'requires_attachment' => $this->requires_attachment,
            'description' => $this->description,
            'excluded_contract_types' => $this->selected_leave_excluded_contract_types,
            'settings' => [
                'accrual_method' => $this->accrual_method,
                'monthly_accrual_rate' => $this->monthly_accrual_rate,
                'allow_carryover' => $this->allow_carryover,
                'carryover_days' => $this->carryover_days,
                'weekend_policy' => $this->weekend_policy,
                'deduction_policy' => $this->deduction_policy,
                'max_balance' => $this->max_balance,
                'duration_unit' => $this->duration_unit,
                'notice_min_days' => $this->notice_min_days,
                'notice_max_advance_days' => $this->notice_max_advance_days,
                'allow_retroactive' => $this->allow_retroactive,
                'note_required' => $this->note_required,
                'note_text' => $this->note_text,
                'note_ack_required' => $this->note_ack_required,
                'attachment_types' => $this->attachment_types,
            ]
        ];

        $this->leaveSettingService->savePolicy($data, $this->editingId ?: null);

        $this->reset(['createOpen', 'editOpen', 'editingId']);
        $this->resetCreateLeaveForm();
        $this->dispatch('toast', type: 'success', message: tr('Policy saved successfully.'));
    }

    public function openEdit($id)
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();
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

        $this->max_balance = $settings['max_balance'] ?? 0;
        $this->duration_unit = $settings['duration_unit'] ?? 'full_day';
        $this->notice_min_days = $settings['notice_min_days'] ?? 0;
        $this->notice_max_advance_days = $settings['notice_max_advance_days'] ?? 0;
        $this->allow_retroactive = $settings['allow_retroactive'] ?? false;
        $this->selected_leave_excluded_contract_types = $policy->excluded_contract_types ?? [];
        $this->note_required = $settings['note_required'] ?? false;
        $this->note_text = $settings['note_text'] ?? '';
        $this->note_ack_required = $settings['note_ack_required'] ?? false;
        $this->attachment_types = $settings['attachment_types'] ?? ['pdf', 'jpg', 'png'];

        $this->editingNameLocked = (string) data_get($settings, 'meta.system_key', '') === 'annual_default'
            || trim((string) $this->name) === 'سنوية'
            || trim((string) $this->name) === 'Annual';

        $this->editOpen = true;
    }

    public function closeEdit()
    {
        $this->editOpen = false;
        $this->resetCreateLeaveForm();
    }

    public function confirmDelete($id)
    {
        $this->editingId = $id;
        $this->deleteOpen = true;
    }

    public function closeDelete()
    {
        $this->deleteOpen = false;
        $this->reset(['editingId']);
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
