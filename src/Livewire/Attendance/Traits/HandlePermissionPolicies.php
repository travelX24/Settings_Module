<?php

namespace Athka\SystemSettings\Livewire\Attendance\Traits;

use Athka\SystemSettings\Models\PermissionPolicy;

trait HandlePermissionPolicies
{
    public function savePermissionSettings()
    {
        $this->authorize('settings.attendance.manage');
        
        $this->leaveSettingService->savePermissionSettings([
            'approval_required' => $this->perm_approval_required,
            'monthly_limit_minutes' => (int)($this->perm_monthly_limit_hours * 60),
            'max_request_minutes' => (int)($this->perm_max_request_hours * 60),
            'deduction_policy' => $this->perm_deduction_policy,
            'show_in_app' => $this->perm_show_in_app,
            'requires_attachment' => $this->perm_requires_attachment,
            'attachment_types' => $this->perm_attachment_types,
        ]);

        $this->dispatch('toast', type: 'success', message: tr('Permission settings updated successfully.'));
    }

    public function togglePermApp($val)
    {
        $this->perm_show_in_app = (bool)$val;
        $this->savePermissionSettings();
    }
}
