<?php

namespace Athka\SystemSettings\Livewire\Attendance\Traits;

use Athka\SystemSettings\Models\PermissionPolicy;

trait HandlePermissionPolicies
{
    public function savePermissionSettings()
    {
        $this->authorize('settings.attendance.manage');
        
        if (!$this->selectedYearId) {
            $this->dispatch('toast', type: 'error', message: tr('يرجى اختيار سنة أولاً.'));
            return;
        }

        // Validate hours fields
        $isEmpty = function($val) { return $val === '' || $val === null; };
        if ($isEmpty($this->perm_monthly_limit_hours) || $isEmpty($this->perm_max_request_hours) || !is_numeric($this->perm_monthly_limit_hours) || !is_numeric($this->perm_max_request_hours)) {
            $errorMsg = tr('حقول الساعات إلزامية، يرجى إدخال القيم المطلوبة قبل الحفظ.');
            $this->addError('perm_monthly_limit_hours', $errorMsg);
            $this->addError('perm_max_request_hours', $errorMsg);
            $this->dispatch('toast', type: 'error', message: $errorMsg);
            return;
        }

        if ((float)$this->perm_monthly_limit_hours > 0 && (float)$this->perm_monthly_limit_hours < (float)$this->perm_max_request_hours) {
            $errorMsgConflict = tr('لا يمكن أن يكون الحد الشهري أقل من الحد الأقصى للطلب الواحد، يرجى تعديل القيم.');
            $this->addError('perm_monthly_limit_hours', $errorMsgConflict);
            $this->addError('perm_max_request_hours', $errorMsgConflict);
            $this->dispatch('toast', type: 'error', message: $errorMsgConflict);
            return;
        }

        // ✅ New Validation: Ensure at least one limit is > 0
        if ((float)$this->perm_monthly_limit_hours <= 0 && (float)$this->perm_max_request_hours <= 0) {
            $errorMsgInit = tr('يجب أن يكون أحد الحدين على الأقل (الشهري أو الأقصى للطلب) أكبر من الصفر لتهيئة الإعدادات.');
            $this->addError('perm_monthly_limit_hours', $errorMsgInit);
            $this->addError('perm_max_request_hours', $errorMsgInit);
            $this->dispatch('toast', type: 'error', message: $errorMsgInit);
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

        $this->dispatch('toast', type: 'success', message: tr('تم تحديث إعدادات الأذونات بنجاح.'));
    }

    public function togglePermApp($val)
    {
        $this->perm_show_in_app = (bool)$val;
        $this->savePermissionSettings();
    }
}
