<?php

namespace Athka\SystemSettings\Livewire\Attendance\Traits;

use Athka\SystemSettings\Models\AttendancePenaltyPolicy;
use Athka\SystemSettings\Models\UnexcusedAbsencePolicy;

trait HandlePenaltySettings
{
    public function saveBasicLatePenalty()
    {
        $this->authorize('settings.attendance.manage');
        
        $this->attendanceSettingService->savePenalty(
            auth()->user()->saas_company_id,
            $this->defaultPolicy->id,
            'late_arrival',
            [
                'is_enabled' => $this->basicLatePenalty['enabled'],
                'threshold_minutes' => $this->basicLatePenalty['grace_minutes'],
                'interval_minutes' => $this->basicLatePenalty['interval_minutes'],
                'deduction_type' => $this->basicLatePenalty['deduction_type'],
                'deduction_value' => $this->basicLatePenalty['deduction_value'],
            ]
        );

        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Late arrival basic penalties updated.'));
    }

    public function saveBasicEarlyPenalty()
    {
        $this->authorize('settings.attendance.manage');

        $this->attendanceSettingService->savePenalty(
            auth()->user()->saas_company_id,
            $this->defaultPolicy->id,
            'early_departure',
            [
                'is_enabled' => $this->basicEarlyPenalty['enabled'],
                'threshold_minutes' => $this->basicEarlyPenalty['grace_minutes'],
                'interval_minutes' => $this->basicEarlyPenalty['interval_minutes'],
                'deduction_type' => $this->basicEarlyPenalty['deduction_type'],
                'deduction_value' => $this->basicEarlyPenalty['deduction_value'],
            ]
        );

        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Early departure basic penalties updated.'));
    }

    public function saveBasicAbsencePenalty()
    {
        $this->authorize('settings.attendance.manage');

        $this->attendanceSettingService->saveAbsencePolicy(
            auth()->user()->saas_company_id,
            $this->defaultPolicy->id,
            [
                'is_enabled' => $this->basicAbsencePenalty['enabled'],
                'late_minutes' => $this->basicAbsencePenalty['threshold_minutes'],
                'notification_message' => $this->basicAbsencePenalty['notification_message'],
                'deduction_type' => $this->basicAbsencePenalty['deduction_type'],
                'deduction_value' => $this->basicAbsencePenalty['deduction_value'],
            ]
        );

        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Absence basic penalties updated.'));
    }
}
