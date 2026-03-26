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
use Athka\SystemSettings\Livewire\Attendance\Traits\HandleGroupSettings;

class AttendanceSettings extends Component
{
    use WithPagination, HandlePenaltySettings, HandleGpsSettings, HandleGroupSettings;

    public $activeTab = 'policies';
    public $defaultPolicy;
    public $graceSettings;
    public $trackingPolicy;
    public $prepMethods = [];
    public $selectedId = null;

    // View States
    public $showGpsModal = false;
    public $showSavedLocationsModal = false;
    public $showSavedFingerprintModal = false;
    public $showSavedNfcModal = false;
    public $showGroupModal = false;
    public $showPenaltyModal = false;
    public $showAbsenceModal = false;

    public $isEditingGroup = false;
    public $isEditingPenalty = false;
    public $isEditingAbsence = false;
    public $isEditing = false; // For GPS

    // Form Bundles
    public $gracePeriods = [];
    public $basicLatePenalty = [];
    public $basicEarlyPenalty = [];
    public $basicAbsencePenalty = [];
    
    public $newGroup = [
        'name' => '',
        'description' => '',
        'policy' => 'general',
        'tracking_mode' => 'check_in_out',
        'methods' => [],
        'grace_periods_type' => 'use_global',
        'custom_grace_periods' => ['late_arrival' => 0, 'early_departure' => 0, 'auto_departure' => 0],
        'employee_ids' => []
    ];

    public $newPenalty = [
        'violation_type' => 'late_arrival',
        'recurrence_count' => 2,
        'threshold_minutes' => 0,
        'penalty_action' => 'deduction',
        'deduction_type' => 'percentage',
        'deduction_value' => 0,
        'notification_message' => ''
    ];

    public $newAbsencePolicy = [
        'absence_reason_type' => 'no_notice',
        'day_selector_type' => 'single',
        'day_from' => 1,
        'day_to' => 1,
        'late_minutes' => 0,
        'recurrence_count' => 1,
        'penalty_action' => 'notification',
        'deduction_type' => 'fixed',
        'deduction_value' => 0
    ];

    public $deviceForm = [
        'id' => null,
        'name' => '',
        'branch' => 'main',
        'location_inside' => '',
        'serial_number' => ''
    ];

    // Data Lists
    public $policyTypes = [];
    public $absenceTypes = [];
    public $availableEmployees = [];
    public $branches = [];
    public $geographicLocations = [];
    public $fingerprintDevices = [];
    public $nfcDevices = [];

    // GPS Specific
    public $gpsTarget = 'branch';
    public $selectedBranch = null;
    public $selectedGroups = [];
    public $locationName = '';
    public $filterBranchId = null;
    public $lockBranchFilter = false;
    public $branchesOptions = [];
    public $showFingerprintModal = false;
    public $showNfcModal = false;

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

        // Initialize Constants
        $this->policyTypes = [
            'general' => tr('General Company Policy'),
            'specific' => tr('Specific For This Group')
        ];

        $this->absenceTypes = [
            'no_notice' => tr('No Notice Absence'),
            'late_early' => tr('Late/Early Cumulative')
        ];

        // Load Employees with localized names based on the Employee model directly
        $isAr = app()->getLocale() === 'ar';
        $this->availableEmployees = \Athka\Employees\Models\Employee::where('saas_company_id', $companyId)
            ->get()
            ->map(function($e) use ($isAr) {
                $name = $isAr ? ($e->name_ar ?? $e->name_en) : ($e->name_en ?? $e->name_ar);
                return [
                    'id' => (string)$e->id,
                    'name' => $name ?: tr('Unnamed Employee')
                ];
            })
            ->toArray();

        // Load Branches
        $this->branches = \Athka\Saas\Models\Branch::where('saas_company_id', $companyId)
            ->get(['id', 'name'])
            ->toArray();

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
        $late = $this->defaultPolicy->penalties()->where('violation_type', 'late_arrival')->where('recurrence_count', 1)->first();
        $this->basicLatePenalty = [
            'enabled' => $late ? (bool)$late->is_enabled : false,
            'grace_minutes' => $late ? $late->threshold_minutes : 0,
            'interval_minutes' => $late ? $late->interval_minutes : 0,
            'deduction_type' => $late ? $late->deduction_type : 'percentage',
            'deduction_value' => $late ? $late->deduction_value : 0,
        ];

        $this->trackingPolicy = $this->defaultPolicy->tracking_mode;

        // Load Prep Methods
        $methodsList = ['gps', 'fingerprint', 'nfc'];
        $this->prepMethods = [];
        foreach ($methodsList as $mType) {
            $m = \Athka\SystemSettings\Models\AttendanceMethod::firstOrCreate(
                ['saas_company_id' => $companyId, 'method' => $mType],
                ['is_enabled' => ($mType === 'gps'), 'device_count' => 0]
            );
            
            // Auto update device counts
            $m->updateDeviceCount();

            $this->prepMethods[$mType] = [
                'enabled' => (bool)$m->is_enabled,
                'device_count' => $m->device_count,
            ];
        }

        $early = $this->defaultPolicy->penalties()->where('violation_type', 'early_departure')->where('recurrence_count', 1)->first();
        $this->basicEarlyPenalty = [
            'enabled' => $early ? (bool)$early->is_enabled : false,
            'grace_minutes' => $early ? $early->threshold_minutes : 0,
            'interval_minutes' => $early ? $early->interval_minutes : 0,
            'deduction_type' => $early ? $early->deduction_type : 'percentage',
            'deduction_value' => $early ? $early->deduction_value : 0,
        ];

        $absence = $this->defaultPolicy->absencePolicies()->where('absence_reason_type', 'no_notice')->first();
        $this->basicAbsencePenalty = [
            'enabled' => $absence ? (bool)$absence->is_enabled : false,
            'threshold_minutes' => $absence ? $absence->late_minutes : 0,
            'notification_message' => $absence ? $absence->notification_message : '',
            'deduction_type' => $absence ? $absence->deduction_type : 'percentage',
            'deduction_value' => $absence ? $absence->deduction_value : 0,
        ];

        // Load Geographic Locations
        $this->geographicLocations = AttendanceGpsLocation::where('saas_company_id', $companyId)
            ->with(['branch', 'employeeGroup'])
            ->get()
            ->toArray();

        // Load Devices
        $this->fingerprintDevices = \Athka\SystemSettings\Models\AttendanceDevice::where('saas_company_id', $companyId)
            ->where('device_type', 'fingerprint')
            ->with('branch')
            ->get()
            ->map(function($d) {
                $arr = $d->toArray();
                $arr['branch_name'] = $d->branch ? $d->branch->name : tr('Main Branch HQ');
                return $arr;
            })
            ->toArray();

        $this->nfcDevices = \Athka\SystemSettings\Models\AttendanceDevice::where('saas_company_id', $companyId)
            ->where('device_type', 'nfc')
            ->with('branch')
            ->get()
            ->map(function($d) {
                $arr = $d->toArray();
                $arr['branch_name'] = $d->branch ? $d->branch->name : tr('Main Branch HQ');
                return $arr;
            })
            ->toArray();
    }

    public function openSavedLocationsModal()
    {
        $this->refreshData();
        $this->showSavedLocationsModal = true;
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function setTrackingPolicy($value)
    {
        $this->authorize('settings.attendance.manage');
        $this->defaultPolicy->update(['tracking_mode' => $value]);
        $this->trackingPolicy = $value;
        $this->dispatch('toast', type: 'success', message: tr('Tracking mode updated.'));
    }

    public function togglePrepMethod($method)
    {
        $this->authorize('settings.attendance.manage');
        $companyId = auth()->user()->saas_company_id;
        
        $m = \Athka\SystemSettings\Models\AttendanceMethod::where('saas_company_id', $companyId)
            ->where('method', $method)
            ->first();
            
        if ($m) {
            $m->update(['is_enabled' => !$m->is_enabled]);
            $this->refreshData();
            $this->dispatch('toast', type: 'success', message: tr('Method status updated.'));
        }
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

    
    public function validationAttributes()
    {
        return [
            // Device Form
            'deviceForm.name' => tr('Device Name'),
            'deviceForm.branch' => tr('Branch'),
            'deviceForm.location_inside' => tr('Location Inside Branch'),
            'deviceForm.serial_number' => tr('Serial Number'),
            
            // Penalty Form
            'newPenalty.violation_type' => tr('Violation Type'),
            'newPenalty.recurrence_count' => tr('Recurrence Count'),
            'newPenalty.threshold_minutes' => tr('Penalty Time'),
            'newPenalty.penalty_action' => tr('Penalty Action'),
            'newPenalty.deduction_type' => tr('Deduction Type'),
            'newPenalty.deduction_value' => tr('Deduction Value'),
            
            // Absence Form
            'newAbsencePolicy.absence_reason_type' => tr('Absence Type'),
            'newAbsencePolicy.day_from' => tr('Day Number'),
            'newAbsencePolicy.penalty_action' => tr('Penalty Action'),
            
            // Group Form
            'newGroup.name' => tr('Group Name'),
            'newGroup.policy' => tr('Policy Type'),
            'newGroup.tracking_mode' => tr('Tracking Mode'),
            'newGroup.description' => tr('Description'),
            
            // GPS Form (from trait)
            'gpsData.name' => tr('Location Name'),
            'gpsData.lat' => tr('Latitude'),
            'gpsData.lng' => tr('Longitude'),
            'gpsData.radius' => tr('Radius'),
            'selectedBranch' => tr('Branch'),
            'selectedGroups' => tr('Employee Groups'),
        ];
    }

    public function messages()
    {
        return [
            'required' => tr('The :attribute field is required.'),
            'min' => tr('The :attribute must be at least :min characters.'),
            'numeric' => tr('The :attribute must be a number.'),
            'integer' => tr('The :attribute must be an integer.'),
            'deviceForm.name.required' => tr('The :attribute field is required.'),
            'deviceForm.branch.required' => tr('The :attribute field is required.'),
            'deviceForm.location_inside.required' => tr('The :attribute field is required.'),
        ];
    }

    public function saveDevice() 
    { 
        $this->authorize('settings.attendance.manage');
        
        $this->validate([
            'deviceForm.name' => 'required|min:3',
            'deviceForm.branch' => 'required',
            'deviceForm.location_inside' => 'required',
        ]);

        $type = $this->showFingerprintModal ? 'fingerprint' : 'nfc';
        $companyId = auth()->user()->saas_company_id;

        $data = [
            'name' => $this->deviceForm['name'],
            'branch_id' => ($this->deviceForm['branch'] === 'main') ? null : $this->deviceForm['branch'],
            'location_in_branch' => $this->deviceForm['location_inside'],
            'serial_no' => $this->deviceForm['serial_number'],
            'device_type' => $type,
            'saas_company_id' => $companyId,
            'created_by_user_id' => auth()->id(),
            'is_active' => true,
        ];

        if ($this->deviceForm['id']) {
            \Athka\SystemSettings\Models\AttendanceDevice::find($this->deviceForm['id'])->update($data);
        } else {
            \Athka\SystemSettings\Models\AttendanceDevice::create($data);
        }

        $this->showFingerprintModal = false;
        $this->showNfcModal = false;
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Device registered successfully.'));
    }
    public function openDeviceModal($type) { 
        $this->deviceForm = ['id' => null, 'name' => '', 'branch' => 'main', 'location_inside' => '', 'serial_number' => ''];
        if ($type === 'fingerprint') $this->showFingerprintModal = true;
        if ($type === 'nfc') $this->showNfcModal = true;
    }
    public function editGpsLocation($id) 
    { 
        $this->isEditing = true;
        $this->openGpsModal($id);
    }
    public function editDevice($id) 
    { 
        $companyId = auth()->user()->saas_company_id;
        $dev = \Athka\SystemSettings\Models\AttendanceDevice::where('saas_company_id', $companyId)->findOrFail($id);
        $this->deviceForm = [
            'id' => $dev->id,
            'name' => $dev->name,
            'branch' => $dev->branch_id ?? 'main',
            'location_inside' => $dev->location_in_branch,
            'serial_number' => $dev->serial_no
        ];

        if ($dev->device_type === 'fingerprint') {
            $this->showSavedFingerprintModal = false;
            $this->showFingerprintModal = true;
        } else {
            $this->showSavedNfcModal = false;
            $this->showNfcModal = true;
        }
    }

    public function deleteDevice($id)
    {
        $this->authorize('settings.attendance.manage');
        $companyId = auth()->user()->saas_company_id;
        \Athka\SystemSettings\Models\AttendanceDevice::where('saas_company_id', $companyId)->where('id', $id)->delete();
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Device removed.'));
    }

    public function render()
    {
        $companyId = auth()->user()->saas_company_id;
        
        $groupsQuery = \Athka\SystemSettings\Models\EmployeeGroup::where('saas_company_id', $companyId)
            ->with(['employees', 'allowedMethods', 'appliedPolicy']);

        // Since EmployeeGroup doesn't have branch_id, we might not be able to filter by branch directly 
        // unless we join with employees and their departments, but that's complex for now.
        // We'll just load all and map them for the view's requirements.

        $isAr = app()->getLocale() === 'ar';
        $groups = $groupsQuery->get()->map(function($g) use ($isAr) {
            return [
                'id' => $g->id,
                'name' => $g->name,
                'description' => $g->description,
                'policy' => ($g->applied_policy_id && $g->applied_policy_id != $this->defaultPolicy->id) ? 'specific' : 'general',
                'employee_count' => $g->employees->count(),
                'employee_names' => $g->employees->map(function($e) use ($isAr) {
                    return $isAr ? ($e->name_ar ?? $e->name_en) : ($e->name_en ?? $e->name_ar);
                })->toArray(),
                'methods' => $g->allowedMethods->where('is_allowed', true)->pluck('method')->toArray(),
                'tracking_mode' => $g->appliedPolicy ? $g->appliedPolicy->tracking_mode : 'check_in_out',
                'branch_name' => '-' // Groups are global in this schema
            ];
        })->toArray();

        return view('systemsettings::livewire.attendance.attendance-settings', [
            'gpsLocations' => AttendanceGpsLocation::where('saas_company_id', $companyId)->get(),
            'penalties' => $this->defaultPolicy ? $this->defaultPolicy->penalties : collect(),
            'absencePolicies' => $this->defaultPolicy ? $this->defaultPolicy->absencePolicies : collect(),
            'groups' => $groups,
            'branchesOptions' => $this->branches
        ])->layout('layouts.company-admin');
    }
}
