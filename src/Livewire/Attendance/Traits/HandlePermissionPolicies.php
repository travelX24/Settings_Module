<?php

namespace Athka\SystemSettings\Livewire\Attendance\Traits;

use Athka\SystemSettings\Models\PermissionPolicy;

trait HandlePermissionPolicies
{
    public function savePermissionSettings()
    {
        $this->authorize('settings.attendance.manage');
        
        if (!$this->selectedYearId) {
            $this->dispatch('toast', type: 'error', message: tr('Please select a year first.'));
            return;
        }

        // Validate hours fields
        $isEmpty = function($val) { return $val === '' || $val === null; };
        if ($isEmpty($this->perm_monthly_limit_hours) || $isEmpty($this->perm_max_request_hours) || !is_numeric($this->perm_monthly_limit_hours) || !is_numeric($this->perm_max_request_hours)) {
            $errorMsg = tr('Hours fields are mandatory, please enter the required values before saving.');
            $this->addError('perm_monthly_limit_hours', $errorMsg);
            $this->addError('perm_max_request_hours', $errorMsg);
            $this->dispatch('toast', type: 'error', message: $errorMsg);
            return;
        }

        if ((float)$this->perm_monthly_limit_hours > 0 && (float)$this->perm_monthly_limit_hours < (float)$this->perm_max_request_hours) {
            $errorMsgConflict = tr('Monthly limit cannot be less than maximum per request, please adjust the values.');
            $this->addError('perm_monthly_limit_hours', $errorMsgConflict);
            $this->addError('perm_max_request_hours', $errorMsgConflict);
            $this->dispatch('toast', type: 'error', message: $errorMsgConflict);
            return;
        }

        $this->leaveSettingService->savePermissionSettings([
            'approval_required' => $this->perm_approval_required,
            'monthly_limit_minutes' => (int)($this->perm_monthly_limit_hours * 60),
            'max_request_minutes' => (int)($this->perm_max_request_hours * 60),
            'deduction_policy' => $this->perm_deduction_policy,
            'show_in_app' => $this->perm_show_in_app,
            'is_active' => $this->perm_is_active,
            'requires_attachment' => $this->perm_requires_attachment,
            'attachment_types' => $this->perm_attachment_types,
        ], $this->selectedYearId);

        $this->dispatch('toast', type: 'success', message: tr('Permission settings updated successfully.'));
    }

    public function togglePermApp($val)
    {
        $this->perm_show_in_app = (bool)$val;
        $this->savePermissionSettings();
    }
}
