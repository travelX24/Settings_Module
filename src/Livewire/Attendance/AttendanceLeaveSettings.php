<?php

namespace Athka\SystemSettings\Livewire\Attendance;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Athka\SystemSettings\Services\LeaveSettingService;
use Athka\SystemSettings\Livewire\Attendance\Traits\HandleLeavePolicies;
use Athka\SystemSettings\Livewire\Attendance\Traits\HandlePermissionPolicies;

class AttendanceLeaveSettings extends Component
{
    use WithPagination, WithFileUploads, HandleLeavePolicies, HandlePermissionPolicies;

    public string $search = '';
    public string $tab = 'leaves';
    public string $filterStatus = 'all';
    public string $filterGender = 'all';

    // Form fields
    public $name, $leave_type = 'annual', $days_per_year = 30, $editingId;
    public $gender = 'all', $is_active = true, $show_in_app = true, $requires_attachment = false, $description = '';
    
    // Settings fields
    public $accrual_method = 'annual_grant', $monthly_accrual_rate = 2.5, $allow_carryover = true, $carryover_days = 15;
    public $weekend_policy = 'exclude', $deduction_policy = 'balance_only';

    // Modals
    public bool $createOpen = false, $editOpen = false, $deleteOpen = false;

    // Permission state
    public bool $perm_approval_required = true, $perm_show_in_app = true, $perm_requires_attachment = false;
    public string $perm_monthly_limit_hours = '0', $perm_max_request_hours = '0', $perm_deduction_policy = 'not_allowed_after_limit';
    public array $perm_attachment_types = ['pdf', 'jpg', 'png'];

    protected $leaveSettingService;

    public function boot(LeaveSettingService $service)
    {
        $this->leaveSettingService = $service;
    }

    public function mount()
    {
        $this->authorize('settings.attendance.view');
        // Initial load logic can go here
    }

    public function render()
    {
        $filters = [
            'search' => $this->search,
            'status' => $this->filterStatus,
            'gender' => $this->filterGender,
        ];

        return view('systemsettings::livewire.attendance.attendance-leave-settings', [
            'policies' => $this->leaveSettingService->getPolicies($filters),
        ])->layout('layouts.company-admin');
    }
}
