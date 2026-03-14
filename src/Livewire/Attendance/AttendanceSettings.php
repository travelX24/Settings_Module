<?php

namespace Athka\SystemSettings\Livewire\Attendance;

use Livewire\Component;
use Livewire\WithPagination;
use Athka\SystemSettings\Models\AttendancePolicy;
use Athka\SystemSettings\Models\AttendanceGraceSetting;
use Athka\SystemSettings\Models\AttendanceGpsLocation;
use Athka\SystemSettings\Services\AttendanceSettingService;
use Athka\SystemSettings\Livewire\Attendance\Traits\HandlePenaltySettings;
use Athka\SystemSettings\Livewire\Attendance\Traits\HandleGpsSettings;

class AttendanceSettings extends Component
{
    use WithPagination, HandlePenaltySettings, HandleGpsSettings;

    public $activeTab = 'policies';
    public $defaultPolicy;
    public $graceSettings;
    public $selectedId = null;

    // View States
    public $showGpsModal = false;

    // Form Bundles
    public $gracePeriods = [];
    public $basicLatePenalty = [];
    public $basicEarlyPenalty = [];
    public $basicAbsencePenalty = [];

    protected $attendanceSettingService;

    public function boot(AttendanceSettingService $service)
    {
        $this->attendanceSettingService = $service;
    }

    public function mount()
    {
        $this->authorize('settings.attendance.view');
        $companyId = auth()->user()->saas_company_id;

        $this->defaultPolicy = $this->attendanceSettingService->getDefaultPolicy($companyId);
        $this->graceSettings = $this->attendanceSettingService->getGraceSettings($companyId);

        $this->refreshData();
    }

    public function refreshData()
    {
        $companyId = auth()->user()->saas_company_id;

        // Load Grace Periods to Form
        $this->gracePeriods = [
            'late_arrival' => $this->graceSettings->late_grace_minutes,
            'early_departure' => $this->graceSettings->early_leave_grace_minutes,
            'auto_departure' => $this->graceSettings->auto_checkout_after_minutes,
            'auto_departure_penalty_enabled' => (bool)$this->graceSettings->auto_checkout_penalty_enabled,
            'auto_departure_penalty_amount' => $this->graceSettings->auto_checkout_penalty_amount,
            'auto_checkout_deduction_type' => $this->graceSettings->auto_checkout_deduction_type,
        ];

        // Load Basic Penalties
        $late = $this->defaultPolicy->penalties()->where('violation_type', 'late_arrival')->first();
        $this->basicLatePenalty = [
            'enabled' => $late ? (bool)$late->is_enabled : false,
            'grace_minutes' => $late ? $late->threshold_minutes : 0,
            'interval_minutes' => $late ? $late->interval_minutes : 0,
            'deduction_type' => $late ? $late->deduction_type : 'percentage',
            'deduction_value' => $late ? $late->deduction_value : 0,
        ];

        // ... repeat for early and absence (can be further optimized)
    }

    public function setTrackingPolicy($value)
    {
        $this->authorize('settings.attendance.manage');
        $this->defaultPolicy->update(['tracking_mode' => $value]);
        $this->dispatch('toast', type: 'success', message: tr('Tracking mode updated.'));
    }

    public function saveGracePeriods()
    {
        $this->authorize('settings.attendance.manage');
        $this->graceSettings->update([
            'late_grace_minutes' => $this->gracePeriods['late_arrival'],
            'early_leave_grace_minutes' => $this->gracePeriods['early_departure'],
            'auto_checkout_after_minutes' => $this->gracePeriods['auto_departure'],
        ]);
        $this->dispatch('toast', type: 'success', message: tr('Grace periods saved.'));
    }

    public function render()
    {
        $companyId = auth()->user()->saas_company_id;
        return view('systemsettings::livewire.attendance.attendance-settings', [
            'gpsLocations' => AttendanceGpsLocation::where('saas_company_id', $companyId)->get(),
        ])->layout('layouts.company-admin');
    }
}
