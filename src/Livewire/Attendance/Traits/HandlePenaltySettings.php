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
    public function openPenaltyModal($id = null)
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();
        
        $companyId = auth()->user()->saas_company_id;
        if ($id) {
            $penalty = AttendancePenaltyPolicy::where('saas_company_id', $companyId)->findOrFail($id);
            $this->selectedId = $id;
            $this->isEditingPenalty = true;
            $this->newPenalty = [
                'violation_type' => $penalty->violation_type,
                'recurrence_count' => $penalty->recurrence_count,
                'threshold_minutes' => $penalty->threshold_minutes,
                'penalty_action' => $penalty->penalty_action,
                'deduction_type' => $penalty->deduction_type,
                'deduction_value' => $penalty->deduction_value,
                'notification_message' => $penalty->notification_message,
            ];
        } else {
            $this->selectedId = null;
            $this->isEditingPenalty = false;
            $this->newPenalty = [
                'violation_type' => 'late_arrival',
                'recurrence_count' => 2,
                'threshold_minutes' => 0,
                'penalty_action' => 'deduction',
                'deduction_type' => 'percentage',
                'deduction_value' => 0,
                'notification_message' => ''
            ];
        }
        $this->showPenaltyModal = true;
    }

    public function editPenalty($id)
    {
        $this->openPenaltyModal($id);
    }

    public function savePenalty()
    {
        $this->authorize('settings.attendance.manage');
        
        $this->validate([
            'newPenalty.violation_type' => 'required',
            'newPenalty.recurrence_count' => 'required|integer|min:1',
            'newPenalty.threshold_minutes' => 'required|integer',
            'newPenalty.penalty_action' => 'required',
        ]);

        $data = $this->newPenalty;
        $data['policy_id'] = $this->defaultPolicy->id;
        $data['saas_company_id'] = auth()->user()->saas_company_id;
        $data['is_active'] = true;
        $data['is_enabled'] = true;
        $data['recurrence_from'] = $data['recurrence_count'];
        $data['recurrence_to'] = $data['recurrence_count'];
        $data['minutes_from'] = $data['threshold_minutes'];
        $data['minutes_to'] = 9999; // Set a high upper limit if not specified

        if ($this->selectedId) {
            AttendancePenaltyPolicy::where('saas_company_id', auth()->user()->saas_company_id)
                ->where('id', $this->selectedId)
                ->firstOrFail()
                ->update($data);
        } else {
            AttendancePenaltyPolicy::create($data);
        }

        $this->showPenaltyModal = false;
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Penalty policy saved.'));
    }

    public function deletePenalty($id)
    {
        $this->authorize('settings.attendance.manage');
        $companyId = auth()->user()->saas_company_id;
        AttendancePenaltyPolicy::where('saas_company_id', $companyId)->where('id', $id)->delete();
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Penalty policy deleted.'));
    }

    public function openAbsenceModal($id = null)
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();
        
        $companyId = auth()->user()->saas_company_id;
        if ($id) {
            $policy = UnexcusedAbsencePolicy::where('saas_company_id', $companyId)->findOrFail($id);
            $this->selectedId = $id;
            $this->isEditingAbsence = true;
            $this->newAbsencePolicy = [
                'absence_reason_type' => $policy->absence_reason_type,
                'day_selector_type' => $policy->day_selector_type,
                'day_from' => $policy->day_from,
                'day_to' => $policy->day_to,
                'late_minutes' => $policy->late_minutes,
                'recurrence_count' => $policy->recurrence_count,
                'penalty_action' => $policy->penalty_action,
                'deduction_type' => $policy->deduction_type,
                'deduction_value' => $policy->deduction_value,
                'notification_message' => $policy->notification_message,
            ];
        } else {
            $this->selectedId = null;
            $this->isEditingAbsence = false;
            $this->newAbsencePolicy = [
                'absence_reason_type' => 'no_notice',
                'day_selector_type' => 'single',
                'day_from' => 1,
                'day_to' => 1,
                'late_minutes' => 0,
                'recurrence_count' => 1,
                'penalty_action' => 'notification',
                'deduction_type' => 'fixed',
                'deduction_value' => 0,
                'notification_message' => ''
            ];
        }
        $this->showAbsenceModal = true;
    }

    public function editAbsencePolicy($id)
    {
        $this->openAbsenceModal($id);
    }

    public function saveAbsencePolicy()
    {
        $this->authorize('settings.attendance.manage');
        
        $this->validate([
            'newAbsencePolicy.absence_reason_type' => 'required',
            'newAbsencePolicy.day_from' => 'required|integer|min:1',
            'newAbsencePolicy.penalty_action' => 'required',
        ]);

        $data = $this->newAbsencePolicy;
        $data['policy_id'] = $this->defaultPolicy->id;
        $data['saas_company_id'] = auth()->user()->saas_company_id;
        $data['is_active'] = true;
        $data['is_enabled'] = true;

        if ($this->selectedId) {
            UnexcusedAbsencePolicy::where('saas_company_id', auth()->user()->saas_company_id)
                ->where('id', $this->selectedId)
                ->firstOrFail()
                ->update($data);
        } else {
            UnexcusedAbsencePolicy::create($data);
        }

        $this->showAbsenceModal = false;
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Absence policy saved.'));
    }

    public function deleteAbsencePolicy($id)
    {
        $this->authorize('settings.attendance.manage');
        $companyId = auth()->user()->saas_company_id;
        UnexcusedAbsencePolicy::where('saas_company_id', $companyId)->where('id', $id)->delete();
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Absence policy deleted.'));
    }
}
