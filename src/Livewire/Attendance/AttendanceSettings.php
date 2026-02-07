<?php

namespace Athka\SystemSettings\Livewire\Attendance;

use Livewire\Component;
use Livewire\WithPagination;
use Athka\SystemSettings\Models\AttendancePolicy;
use Athka\SystemSettings\Models\AttendanceGraceSetting;
use Athka\SystemSettings\Models\AttendanceMethod;
use Athka\SystemSettings\Models\AttendanceGpsLocation;
use Athka\SystemSettings\Models\AttendanceDevice;
use Athka\SystemSettings\Models\AttendancePenaltyPolicy;
use Athka\SystemSettings\Models\UnexcusedAbsencePolicy;
use Athka\SystemSettings\Models\EmployeeGroup;
use Athka\Employees\Models\Employee;

class AttendanceSettings extends Component
{
    use WithPagination;

    public $activeTab = 'policies'; 

    // Global Settings Models
    public $defaultPolicy;
    public $graceSettings;

    // Section 1: Attendance Tracking Policy
    public $trackingPolicy; 

    // Section 2: Preparation Methods
    public $prepMethods = [];

    // Section 3: Grace Periods
    public $gracePeriods = [];

    // Collections (Loaded in render/mount)
    public $geographicLocations = [];
    public $fingerprintDevices = [];
    public $nfcDevices = [];
    public $penalties = [];
    public $absencePolicies = [];
    public $groups = [];
    public $allPolicies = [];
    // Geographic Locations
    public $branches = [];
    public $availableGroups = []; 
    public $trackingModeLabels = [];

    // Modal Visibility States
    public $showGpsModal = false;
    public $showSavedLocationsModal = false;
    public $showFingerprintModal = false;
    public $showNfcModal = false;
    public $showSavedFingerprintModal = false;
    public $showSavedNfcModal = false;
    public $showGroupModal = false;
    public $showPenaltyModal = false;
    public $showAbsenceModal = false;

    // Basic Penalties Form State
    public $basicLatePenalty = [
        'enabled' => false,
        'grace_minutes' => 0,
        'interval_minutes' => 0,
        'deduction_type' => 'percentage',
        'deduction_value' => 0,
    ];

    public $basicEarlyPenalty = [
        'enabled' => false,
        'grace_minutes' => 0,
        'interval_minutes' => 0,
        'deduction_type' => 'percentage',
        'deduction_value' => 0,
    ];

    public $basicAbsencePenalty = [
        'enabled' => false,
        'threshold_minutes' => 0,
        'notification_message' => '',
        'deduction_type' => 'percentage',
        'deduction_value' => 0,
    ];

    public function mount()
    {
        $companyId = auth()->user()->saas_company_id;

        // 1. Load Default Policy (or create per company)
        $this->defaultPolicy = AttendancePolicy::firstOrCreate(
            ['is_default' => true, 'saas_company_id' => $companyId],
            ['name' => tr('Default Policy'), 'tracking_mode' => 'check_in_out']
        );
        $this->trackingPolicy = $this->defaultPolicy->tracking_mode;

        // 2. Load Grace Settings
        $this->graceSettings = AttendanceGraceSetting::where('saas_company_id', $companyId)
            ->where('is_global_default', true)
            ->first() ?? AttendanceGraceSetting::create([
                'late_grace_minutes' => 15,
                'early_leave_grace_minutes' => 10,
                'auto_checkout_after_minutes' => 120,
                'auto_checkout_penalty_enabled' => false,
                'auto_checkout_penalty_amount' => 0,
                'is_global_default' => true,
                'saas_company_id' => $companyId,
            ]);
        $this->gracePeriods = [
            'late_arrival' => $this->graceSettings->late_grace_minutes,
            'early_departure' => $this->graceSettings->early_leave_grace_minutes,
            'auto_departure' => $this->graceSettings->auto_checkout_after_minutes,
            'auto_departure_penalty_enabled' => $this->graceSettings->auto_checkout_penalty_enabled,
            'auto_departure_penalty_amount' => $this->graceSettings->auto_checkout_penalty_amount,
        ];

        // 3. Load Methods
        $this->loadMethods();
        
        // 4. Load initial data lists
        $this->refreshData();

        $this->trackingModeLabels = [
            'check_in_only' => tr('Attendance Only'),
            'check_in_out' => tr('Attendance & Departure'),
            'manual' => tr('Manual Entry'),
        ];
    }

    public function loadMethods()
    {
        $companyId = auth()->user()->saas_company_id;
        $methods = ['gps', 'fingerprint', 'nfc'];
        foreach ($methods as $m) {
            $methodModel = AttendanceMethod::firstOrCreate([
                'method' => $m,
                'saas_company_id' => $companyId
            ]);
            $this->prepMethods[$m] = [
                'enabled' => $methodModel->is_enabled,
                'device_count' => $methodModel->device_count
            ];
        }
    }

    public function refreshData()
    {
        $companyId = auth()->user()->saas_company_id;

        $mainDeptId = \Athka\SystemSettings\Models\Department::where('saas_company_id', $companyId)->orderBy('id')->first()?->id;

        $this->geographicLocations = AttendanceGpsLocation::active()
            ->where('saas_company_id', $companyId)
            ->get()->map(function($loc) use ($mainDeptId) {
                $target = '';
                if ($loc->employee_group_id) {
                    $target = $loc->employeeGroup?->name;
                } elseif ($loc->branch_id) {
                    $target = ((int)$loc->branch_id === (int)$mainDeptId) ? tr('Main Branch HQ') : ($loc->branch?->name ?? tr('N/A'));
                }
                return array_merge($loc->toArray(), ['target_name' => $target]);
            })->toArray();

        $this->fingerprintDevices = AttendanceDevice::with('branch')->fingerprint()->active()
            ->where('saas_company_id', $companyId)
            ->get()
            ->map(fn($d) => array_merge($d->toArray(), [
                'branch_name' => ((int)$d->branch_id === (int)$mainDeptId) ? tr('Main Branch HQ') : ($d->branch?->name ?? tr('N/A'))
            ]))
            ->toArray();
            
        $this->nfcDevices = AttendanceDevice::with('branch')->nfc()->active()
            ->where('saas_company_id', $companyId)
            ->get()
            ->map(fn($d) => array_merge($d->toArray(), [
                'branch_name' => ((int)$d->branch_id === (int)$mainDeptId) ? tr('Main Branch HQ') : ($d->branch?->name ?? tr('N/A'))
            ]))
            ->toArray();
        
        // Load Penalties linked to default policy
        $this->penalties = AttendancePenaltyPolicy::where('policy_id', $this->defaultPolicy->id)
            ->where('saas_company_id', $companyId)
            ->where('recurrence_count', '>', 1)
            ->active()
            ->get()
            ->toArray();

        // Load Basic Penalties (Ensure single record)
        $late = AttendancePenaltyPolicy::where('policy_id', $this->defaultPolicy->id)
            ->where('saas_company_id', $companyId)
            ->where('violation_type', 'late_arrival')
            ->where('recurrence_count', 1)
            ->orderBy('id', 'desc')
            ->get();

        if ($late->count() > 1) {
            // Cleanup duplicates, keep the most recent one
            $keep = $late->first();
            AttendancePenaltyPolicy::where('policy_id', $this->defaultPolicy->id)
                ->where('saas_company_id', $companyId)
                ->where('violation_type', 'late_arrival')
                ->where('recurrence_count', 1)
                ->where('id', '!=', $keep->id)
                ->delete();
            $late = $keep;
        } else {
            $late = $late->first();
        }

        if ($late) {
            $this->basicLatePenalty = [
                'enabled' => $late->is_enabled,
                'grace_minutes' => $late->threshold_minutes,
                'interval_minutes' => $late->interval_minutes,
                'deduction_type' => $late->deduction_type ?? 'percentage',
                'deduction_value' => (float)$late->deduction_value,
            ];
        }

        $early = AttendancePenaltyPolicy::where('policy_id', $this->defaultPolicy->id)
            ->where('saas_company_id', $companyId)
            ->where('violation_type', 'early_departure')
            ->where('recurrence_count', 1)
            ->orderBy('id', 'desc')
            ->get();

        if ($early->count() > 1) {
            $keep = $early->first();
            AttendancePenaltyPolicy::where('policy_id', $this->defaultPolicy->id)
                ->where('saas_company_id', $companyId)
                ->where('violation_type', 'early_departure')
                ->where('recurrence_count', 1)
                ->where('id', '!=', $keep->id)
                ->delete();
            $early = $keep;
        } else {
            $early = $early->first();
        }

        if ($early) {
            $this->basicEarlyPenalty = [
                'enabled' => $early->is_enabled,
                'grace_minutes' => $early->threshold_minutes,
                'interval_minutes' => $early->interval_minutes,
                'deduction_type' => $early->deduction_type ?? 'percentage',
                'deduction_value' => (float)$early->deduction_value,
            ];
        }

        // Load Absence Policies
        $this->absencePolicies = UnexcusedAbsencePolicy::where('policy_id', $this->defaultPolicy->id)
            ->where('saas_company_id', $companyId)
            ->where('absence_reason_type', '!=', 'no_notice')
            ->active()
            ->get()
            ->toArray();

        // Load Basic Absence
        $absence = UnexcusedAbsencePolicy::where('policy_id', $this->defaultPolicy->id)
            ->where('saas_company_id', $companyId)
            ->where('absence_reason_type', 'no_notice')
            ->orderBy('id', 'desc')
            ->get();

        if ($absence->count() > 1) {
            $keep = $absence->first();
            UnexcusedAbsencePolicy::where('policy_id', $this->defaultPolicy->id)
                ->where('saas_company_id', $companyId)
                ->where('absence_reason_type', 'no_notice')
                ->where('id', '!=', $keep->id)
                ->delete();
            $absence = $keep;
        } else {
            $absence = $absence->first();
        }

        if ($absence) {
            $this->basicAbsencePenalty = [
                'enabled' => $absence->is_enabled,
                'threshold_minutes' => $absence->late_minutes,
                'notification_message' => $absence->notification_message,
                'deduction_type' => $absence->deduction_type ?? 'percentage',
                'deduction_value' => (float)$absence->deduction_value,
            ];
        }

        // Load Groups
        $this->groups = EmployeeGroup::with(['appliedPolicy', 'employees'])
            ->where('saas_company_id', $companyId)
            ->active()
            ->get()->transform(function($group) {
                $locale = app()->getLocale();
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'policy' => $group->applied_policy_id == $this->defaultPolicy->id ? 'general' : 'special',
                    'employee_count' => $group->employees->count(),
                    'employee_names' => $group->employees->map(fn($e) => $locale == 'ar' ? $e->name_ar : ($e->name_en ?: $e->name_ar))->toArray(),
                    'methods' => $group->allowedMethods()->where('is_allowed', true)->pluck('method')->toArray(),
                ];
            })->toArray();

        // Load All Available Policies
        $this->allPolicies = AttendancePolicy::active()
            ->where('saas_company_id', $companyId)
            ->get()->toArray();

        // Load Branches
        $this->branches = \Athka\SystemSettings\Models\Department::where('saas_company_id', $companyId)
            ->get()->toArray();

        // Already loading groups above, but let's make sure it's available for select
        $this->availableGroups = $this->groups;

        // Load Employees
        $this->availableEmployees = Employee::forCompany($companyId)
            ->select('id', 'name_ar', 'name_en')
            ->get()
            ->map(function($e) {
                $name = app()->getLocale() == 'ar' ? $e->name_ar : ($e->name_en ?: $e->name_ar);
                return ['id' => (string)$e->id, 'name' => $name];
            })->toArray();
    }

    public function setTrackingPolicy($value)
    {
        $this->trackingPolicy = $value;
        $this->defaultPolicy->update(['tracking_mode' => $value]);
        $this->dispatch('toast', type: 'success', message: tr('Attendance tracking mode (Check-in/Out) has been updated successfully.'));
    }

    public function saveGracePeriods()
    {
        $this->graceSettings->update([
            'late_grace_minutes' => $this->gracePeriods['late_arrival'],
            'early_leave_grace_minutes' => $this->gracePeriods['early_departure'],
            'auto_checkout_after_minutes' => $this->gracePeriods['auto_departure'],
            'auto_checkout_penalty_enabled' => $this->gracePeriods['auto_departure_penalty_enabled'],
            'auto_checkout_penalty_amount' => $this->gracePeriods['auto_departure_penalty_amount'],
        ]);
        $this->dispatch('toast', type: 'success', message: tr('Grace periods and auto-departure settings have been saved.'));
    }

    public function saveBasicLatePenalty()
    {
        $companyId = auth()->user()->saas_company_id;
        AttendancePenaltyPolicy::updateOrCreate(
            [
                'policy_id' => $this->defaultPolicy->id,
                'saas_company_id' => $companyId,
                'violation_type' => 'late_arrival',
                'recurrence_count' => 1,
            ],
            [
                'is_enabled' => $this->basicLatePenalty['enabled'],
                'threshold_minutes' => $this->basicLatePenalty['grace_minutes'],
                'interval_minutes' => $this->basicLatePenalty['interval_minutes'],
                'deduction_type' => $this->basicLatePenalty['deduction_type'],
                'deduction_value' => $this->basicLatePenalty['deduction_value'],
                'penalty_action' => 'deduction',
                'minutes_from' => 0,
                'minutes_to' => 0,
                'is_active' => true,
            ]
        );
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Late arrival basic penalties updated.'));
    }

    public function saveBasicEarlyPenalty()
    {
        $companyId = auth()->user()->saas_company_id;
        AttendancePenaltyPolicy::updateOrCreate(
            [
                'policy_id' => $this->defaultPolicy->id,
                'saas_company_id' => $companyId,
                'violation_type' => 'early_departure',
                'recurrence_count' => 1,
            ],
            [
                'is_enabled' => $this->basicEarlyPenalty['enabled'],
                'threshold_minutes' => $this->basicEarlyPenalty['grace_minutes'],
                'interval_minutes' => $this->basicEarlyPenalty['interval_minutes'],
                'deduction_type' => $this->basicEarlyPenalty['deduction_type'],
                'deduction_value' => $this->basicEarlyPenalty['deduction_value'],
                'penalty_action' => 'deduction',
                'minutes_from' => 0,
                'minutes_to' => 0,
                'is_active' => true,
            ]
        );
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Early departure basic penalties updated.'));
    }

    public function saveBasicAbsencePenalty()
    {
        $companyId = auth()->user()->saas_company_id;
        UnexcusedAbsencePolicy::updateOrCreate(
            [
                'policy_id' => $this->defaultPolicy->id,
                'saas_company_id' => $companyId,
                'absence_reason_type' => 'no_notice',
            ],
            [
                'is_enabled' => $this->basicAbsencePenalty['enabled'],
                'late_minutes' => $this->basicAbsencePenalty['threshold_minutes'],
                'notification_message' => $this->basicAbsencePenalty['notification_message'],
                'deduction_type' => $this->basicAbsencePenalty['deduction_type'],
                'deduction_value' => $this->basicAbsencePenalty['deduction_value'],
                'penalty_action' => 'deduction',
                'day_selector_type' => 'single',
                'day_from' => 1,
                'day_to' => 1,
                'is_active' => true,
            ]
        );
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Unexcused absence basic penalties updated.'));
    }

    public function togglePrepMethod($method)
    {
        $companyId = auth()->user()->saas_company_id;
        $methodModel = AttendanceMethod::where('method', $method)
            ->where('saas_company_id', $companyId)
            ->first();
        if ($methodModel) {
            $methodModel->is_enabled = !$methodModel->is_enabled;
            $methodModel->save();
            $this->prepMethods[$method]['enabled'] = $methodModel->is_enabled;
            $this->dispatch('toast', type: 'success', message: tr('Attendance preparation method status updated successfully.'));
        }
    }

    // Absence Types
    public $absenceTypes = [
        'no_notice' => 'التغيب دون إعلام مسبق',
        'repetitive' => 'الغياب المتكرر',
        'consecutive' => 'الغياب لفترات متواصلة',
        'late_early' => 'الحضور متأخرًا والذهاب مبكرًا',
        'after_rejection' => 'الغياب بعد رفض الإجازة',
    ];

    public $availableEmployees = [];

    public $policyTypes = [
        'general' => 'سياسة عامة',
        'special' => 'سياسة خاصة',
    ];




    // Section 6: Geographic Locations (Geofences)
    public $isEditing = false;
    public $editingLocationId = null;

    // GPS Modal Fields
    public $gpsTarget = 'branch'; 
    public $selectedBranch = '';
    public $selectedGroups = [];
    public $locationName = '';
    public $gpsData = [
        'lat' => 15.37946,
        'lng' => 44.17241,
        'address' => '',
        'radius' => 100,
    ];

    public function openGpsModal()
    {
        $this->isEditing = false;
        $this->editingLocationId = null;
        $this->reset(['locationName']);
        $this->selectedBranch = 'main';
        
        // Set default group if available
        if (!empty($this->groups)) {
            $this->selectedGroups = [$this->groups[0]['id']];
        } else {
            $this->selectedGroups = [];
        }

        $this->gpsData = [
            'lat' => 15.37946,
            'lng' => 44.17241,
            'radius' => 100,
            'country' => 'Yemen',
            'city' => "Sana'a",
            'region' => '60th Street',
        ];
        $this->showGpsModal = true;
    }

    public function editGpsLocation($id)
    {
        $location = AttendanceGpsLocation::find($id);
        if ($location) {
            $this->isEditing = true;
            $this->editingLocationId = $id;
            $this->locationName = $location->name;
            $this->gpsTarget = $location->employee_group_id ? 'groups' : 'branch';
            if ($this->gpsTarget === 'groups') {
                $this->selectedGroups = [$location->employee_group_id];
            } else {
                $this->selectedBranch = $location->branch_id;
            }
            $this->gpsData = [
                'lat' => $location->lat,
                'lng' => $location->lng,
                'radius' => $location->radius_meters,
            ];
            $this->showSavedLocationsModal = false;
            $this->showGpsModal = true;
        }
    }

    public function deleteGpsLocation($id)
    {
        AttendanceGpsLocation::destroy($id);
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Geographic location has been permanently removed from the system.'));
    }

    public function saveGpsLocation()
    {
        $this->validate([
            'locationName' => 'required|string|max:255',
            'gpsTarget' => 'required|in:branch,groups',
            'gpsData.lat' => 'required|numeric',
            'gpsData.lng' => 'required|numeric',
            'gpsData.radius' => 'required|numeric|min:1',
        ], [
            'locationName.required' => tr('Please enter a location name.'),
            'gpsData.lat.required' => tr('Please select a point on the map.'),
            'gpsData.lng.required' => tr('Please select a point on the map.'),
        ]);

        $companyId = auth()->user()->saas_company_id;
        
        $branchId = null;
        $employeeGroupId = null;

        if ($this->gpsTarget === 'branch') {
            if ($this->selectedBranch === 'main' || empty($this->selectedBranch)) {
                $branchId = \Athka\SystemSettings\Models\Department::where('saas_company_id', $companyId)->first()?->id;
            } else {
                $branchId = $this->selectedBranch;
            }
        } else {
            $employeeGroupId = $this->selectedGroups[0] ?? null;
        }

        $data = [
            'name' => $this->locationName,
            'lat' => $this->gpsData['lat'],
            'lng' => $this->gpsData['lng'],
            'radius_meters' => $this->gpsData['radius'],
            'address_text' => $this->gpsData['address'] ?? '',
            'branch_id' => $branchId,
            'employee_group_id' => $employeeGroupId,
            'saas_company_id' => $companyId,
        ];

        if ($this->isEditing) {
            AttendanceGpsLocation::where('id', $this->editingLocationId)->update($data);
        } else {
            AttendanceGpsLocation::create($data);
        }

        $this->showGpsModal = false;
        $this->reset(['locationName', 'selectedBranch', 'selectedGroups', 'isEditing', 'editingLocationId']);
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: $this->isEditing ? tr('Geographic location details have been updated successfully.') : tr('New geographic location has been registered and activated.'));
    }

    /**
     * Proxied Reverse Geocoding to avoid CORS and 403 issues from browser
     */
    public function reverseGeocode($lat, $lng)
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'AthkaHR-App/1.0',
            ])->get("https://nominatim.openstreetmap.org/reverse", [
                'format' => 'json',
                'lat' => $lat,
                'lon' => $lng,
                'zoom' => 18,
                'addressdetails' => 1,
                'accept-language' => app()->getLocale(),
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Geocoding Proxy Error: ' . $e->getMessage());
        }

        return null;
    }


    // Device Form State
    public $deviceForm = [
        'id' => null,
        'type' => 'fingerprint', // fingerprint, nfc
        'name' => '',
        'branch' => 'main',
        'location_inside' => '',
        'serial_number' => '',
    ];

    public function openDeviceModal($type)
    {
        $this->deviceForm = [
            'id' => null,
            'type' => $type,
            'name' => '',
            'branch' => 'main',
            'location_inside' => '',
            'serial_number' => '',
        ];
        
        if ($type === 'fingerprint') $this->showFingerprintModal = true;
        else $this->showNfcModal = true;
    }

    public function editDevice($id)
    {
        $device = AttendanceDevice::find($id);
        if ($device) {
            $this->deviceForm = [
                'id' => $device->id,
                'type' => $device->device_type,
                'name' => $device->name,
                'branch' => $device->branch_id,
                'location_inside' => $device->location_in_branch,
                'serial_number' => $device->serial_no,
            ];
            
            $this->showSavedFingerprintModal = false;
            $this->showSavedNfcModal = false;
            
            if ($device->device_type === 'fingerprint') $this->showFingerprintModal = true;
            else $this->showNfcModal = true;
        }
    }

    public function deleteDevice($id)
    {
        AttendanceDevice::where('id', $id)->where('saas_company_id', auth()->user()->saas_company_id)->delete();
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('The device has been successfully removed.'));
    }

    public function saveDevice()
    {
        $data = [
            'device_type' => $this->deviceForm['type'],
            'name' => $this->deviceForm['name'],
            'branch_id' => ($this->deviceForm['branch'] === 'main' || empty($this->deviceForm['branch'])) 
                ? \Athka\SystemSettings\Models\Department::where('saas_company_id', auth()->user()->saas_company_id)->first()?->id 
                : $this->deviceForm['branch'],
            'location_in_branch' => $this->deviceForm['location_inside'],
            'serial_no' => $this->deviceForm['serial_number'],
            'is_active' => true,
            'saas_company_id' => auth()->user()->saas_company_id,
        ];

        if ($this->deviceForm['id']) {
            AttendanceDevice::where('id', $this->deviceForm['id'])->update($data);
        } else {
            AttendanceDevice::create($data);
        }

        if ($this->deviceForm['type'] === 'fingerprint') {
            $this->showFingerprintModal = false;
        } else {
            $this->showNfcModal = false;
        }

        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('The device/terminal has been registered and is now ready for use.'));
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }
    
    // Penalties CRUD
    public $newPenalty = [
        'violation_type' => 'late_arrival', 
        'threshold_minutes' => 0,
        'recurrence_count' => 2,
        'penalty_action' => 'deduction',
        'deduction_type' => 'percentage',
        'deduction_value' => 0,
        'notification_message' => '',
    ];

    public $isEditingPenalty = false;
    public $editingPenaltyId = null;

    public function openPenaltyModal()
    {
        $this->isEditingPenalty = false;
        $this->editingPenaltyId = null;
        $this->newPenalty = [
            'violation_type' => 'late_arrival',
            'threshold_minutes' => 0,
            'recurrence_count' => 2,
            'penalty_action' => 'deduction',
            'deduction_type' => 'percentage',
            'deduction_value' => 0,
            'notification_message' => '',
        ];
        $this->showPenaltyModal = true;
    }

    public function editPenalty($id)
    {
        $penalty = AttendancePenaltyPolicy::find($id);
        if ($penalty) {
            $this->isEditingPenalty = true;
            $this->editingPenaltyId = $id;
            $this->newPenalty = [
                'violation_type' => $penalty->violation_type,
                'threshold_minutes' => $penalty->threshold_minutes,
                'recurrence_count' => $penalty->recurrence_count,
                'penalty_action' => $penalty->penalty_action,
                'deduction_type' => $penalty->deduction_type ?? 'percentage',
                'deduction_value' => $penalty->deduction_value,
                'notification_message' => $penalty->notification_message,
            ];
            $this->showPenaltyModal = true;
        }
    }

    public function savePenalty()
    {
        $companyId = auth()->user()->saas_company_id;

        // 1. Validation to prevent duplicate recurrence count for the same violation type
        if ($this->newPenalty['violation_type'] === 'unexcused_absence') {
            $query = UnexcusedAbsencePolicy::where('policy_id', $this->defaultPolicy->id)
                ->where('saas_company_id', $companyId)
                ->where('absence_reason_type', 'repetitive')
                ->where('recurrence_count', $this->newPenalty['recurrence_count']);
            
            if ($this->isEditingPenalty && str_contains($this->editingPenaltyId, 'abs_')) {
                $query->where('id', '!=', str_replace('abs_', '', $this->editingPenaltyId));
            }

            if ($query->exists()) {
                $this->dispatch('toast', type: 'error', message: tr('This recurrence level already exists for this violation type. You can only edit it from the list.'));
                return;
            }
        } else {
            $query = AttendancePenaltyPolicy::where('policy_id', $this->defaultPolicy->id)
                ->where('saas_company_id', $companyId)
                ->where('violation_type', $this->newPenalty['violation_type'])
                ->where('recurrence_count', $this->newPenalty['recurrence_count']);

            if ($this->isEditingPenalty && !str_contains($this->editingPenaltyId, 'abs_')) {
                $query->where('id', '!=', $this->editingPenaltyId);
            }

            if ($query->exists()) {
                $this->dispatch('toast', type: 'error', message: tr('This recurrence level already exists for this violation type. You can only edit it from the list.'));
                return;
            }
        }
        
        // 2. Save Data
        if ($this->newPenalty['violation_type'] === 'unexcused_absence') {
            $data = [
                'policy_id' => $this->defaultPolicy->id,
                'saas_company_id' => $companyId,
                'absence_reason_type' => 'repetitive', // Mapping recurring to repetitive type
                'recurrence_count' => $this->newPenalty['recurrence_count'],
                'late_minutes' => $this->newPenalty['threshold_minutes'],
                'penalty_action' => $this->newPenalty['penalty_action'],
                'deduction_type' => $this->newPenalty['deduction_type'],
                'deduction_value' => $this->newPenalty['deduction_value'],
                'notification_message' => $this->newPenalty['notification_message'],
                'is_enabled' => true,
                'day_selector_type' => 'single',
                'day_from' => 1,
                'day_to' => 1,
            ];

            if ($this->isEditingPenalty && str_contains($this->editingPenaltyId, 'abs_')) {
                UnexcusedAbsencePolicy::where('id', str_replace('abs_', '', $this->editingPenaltyId))->update($data);
            } else {
                UnexcusedAbsencePolicy::create($data);
            }
        } else {
            $data = array_merge($this->newPenalty, [
                'policy_id' => $this->defaultPolicy->id,
                'saas_company_id' => $companyId,
                'is_enabled' => true,
                'minutes_from' => 0,
                'minutes_to' => 0,
                'include_basic_penalty' => false, // Recurrence penalties are independent, no basic penalty overlay
            ]);

            if ($this->isEditingPenalty && !str_contains($this->editingPenaltyId, 'abs_')) {
                AttendancePenaltyPolicy::where('id', $this->editingPenaltyId)->update(
                    collect($data)->except(['id', 'created_at', 'updated_at', 'deleted_at'])->toArray()
                );
            } else {
                AttendancePenaltyPolicy::create($data);
            }
        }

        $this->showPenaltyModal = false;
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: $this->isEditingPenalty ? tr('Recurring violation updated.') : tr('New recurring violation added.'));
    }

    // Absence Modal
    public $newAbsencePolicy = [
        'absence_reason_type' => 'no_notice', 
        'day_selector_type' => 'single', 
        'day_from' => 1,
        'day_to' => 1,
        'penalty_action' => 'notification',
        'deduction_type' => 'fixed',
        'deduction_value' => 0,
        'late_minutes' => 0,
        'recurrence_count' => 1,
    ];

    public $isEditingAbsence = false;
    public $editingAbsenceId = null;

    public function openAbsenceModal()
    {
        $this->isEditingAbsence = false;
        $this->editingAbsenceId = null;
        $this->newAbsencePolicy = [
            'absence_reason_type' => 'no_notice',
            'day_selector_type' => 'single',
            'day_from' => 1,
            'day_to' => 1,
            'penalty_action' => 'notification',
            'deduction_type' => 'fixed',
            'deduction_value' => 0,
            'late_minutes' => 0,
            'recurrence_count' => 1,
        ];
        $this->showAbsenceModal = true;
    }

    public function editAbsencePolicy($id)
    {
        $policy = UnexcusedAbsencePolicy::find($id);
        if ($policy) {
            $this->isEditingPenalty = true;
            $this->editingPenaltyId = 'abs_' . $id;
            $this->newPenalty = [
                'violation_type' => 'unexcused_absence',
                'threshold_minutes' => $policy->late_minutes,
                'recurrence_count' => $policy->recurrence_count ?? 1,
                'penalty_action' => $policy->penalty_action,
                'deduction_type' => $policy->deduction_type ?? 'percentage',
                'deduction_value' => $policy->deduction_value,
                'include_basic_penalty' => false, // Absences don't have basic penalty toggle in analysis logic same way
                'notification_message' => $policy->notification_message,
            ];
            $this->showPenaltyModal = true;
        }
    }

    public function deleteAbsencePolicy($id)
    {
        UnexcusedAbsencePolicy::destroy($id);
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Unexcused absence policy has been removed.'));
    }

    public function saveAbsencePolicy()
    {
        $companyId = auth()->user()->saas_company_id;
        $data = array_merge($this->newAbsencePolicy, [
            'policy_id' => $this->defaultPolicy->id,
            'day_to' => $this->newAbsencePolicy['day_selector_type'] === 'single' ? $this->newAbsencePolicy['day_from'] : $this->newAbsencePolicy['day_to'],
            'saas_company_id' => $companyId,
        ]);

        if ($this->isEditingAbsence) {
            UnexcusedAbsencePolicy::where('id', $this->editingAbsenceId)->update(
                collect($data)->except(['id', 'created_at', 'updated_at', 'deleted_at'])->toArray()
            );
        } else {
            UnexcusedAbsencePolicy::create($data);
        }

        $this->showAbsenceModal = false;
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: $this->isEditingAbsence ? tr('Absence policy details updated successfully.') : tr('New absence policy has been established.'));
    }

    // Groups CRUD
    public $newGroup = [
        'name' => '',
        'description' => '',
        'policy' => 'general',
        'tracking_mode' => 'check_in_out',
        'methods' => [],
        'grace_periods_type' => 'general',
        'custom_grace_periods' => [
            'late_arrival' => 0,
            'early_departure' => 0,
            'auto_departure' => 0,
        ],
        'employee_ids' => [],
    ];

    public $isEditingGroup = false;
    public $editingGroupId = null;

    public function openGroupModal()
    {
        $this->isEditingGroup = false;
        $this->editingGroupId = null;
        $this->newGroup = [
            'name' => '',
            'description' => '',
            'policy' => 'general',
            'tracking_mode' => $this->trackingPolicy,
            'methods' => [],
            'grace_periods_type' => 'general',
            'custom_grace_periods' => [
                'late_arrival' => $this->gracePeriods['late_arrival'],
                'early_departure' => $this->gracePeriods['early_departure'],
                'auto_departure' => $this->gracePeriods['auto_departure'],
            ],
            'employee_ids' => [],
        ];
        $this->showGroupModal = true;
    }

    public function editGroup($id)
    {
        $group = EmployeeGroup::with(['employees', 'allowedMethods', 'graceSetting'])->find($id);
        if ($group) {
            $this->isEditingGroup = true;
            $this->editingGroupId = $id;
            
            $graceType = $group->grace_source;
            $graceData = $group->graceSetting ? [
                'late_arrival' => $group->graceSetting->late_grace_minutes,
                'early_departure' => $group->graceSetting->early_leave_grace_minutes,
                'auto_departure' => $group->graceSetting->auto_checkout_after_minutes,
            ] : ['late_arrival' => 15, 'early_departure' => 10, 'auto_departure' => 120];

            $this->newGroup = [
                'name' => $group->name,
                'description' => $group->description,
                'policy' => $group->applied_policy_id == $this->defaultPolicy->id ? 'general' : 'special',
                'tracking_mode' => $group->appliedPolicy ? $group->appliedPolicy->tracking_mode : 'check_in_out',
                'methods' => $group->allowedMethods()->where('is_allowed', true)->pluck('method')->toArray(),
                'grace_periods_type' => $graceType,
                'custom_grace_periods' => $graceData,
                'employee_ids' => $group->employees->pluck('id')->map(fn($id) => (string)$id)->toArray(),
            ];
            $this->showGroupModal = true;
        }
    }

    public function deleteGroup($id)
    {
        EmployeeGroup::destroy($id);
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('Employee group and its assignments have been deleted.'));
    }

    public function saveGroup()
    {
        $companyId = auth()->user()->saas_company_id;
        $graceId = null;
        if ($this->newGroup['grace_periods_type'] === 'custom') {
            $grace = AttendanceGraceSetting::create([
                'late_grace_minutes' => $this->newGroup['custom_grace_periods']['late_arrival'],
                'early_leave_grace_minutes' => $this->newGroup['custom_grace_periods']['early_departure'],
                'auto_checkout_after_minutes' => $this->newGroup['custom_grace_periods']['auto_departure'],
                'is_global_default' => false,
                'saas_company_id' => $companyId,
            ]);
            $graceId = $grace->id;
        }

        // 2. Decide Policy ID
        if ($this->newGroup['policy'] === 'general') {
            $policyId = $this->defaultPolicy->id;
        } else {
            // Find or Create a policy for this specific tracking mode (Scoped to company)
            $policy = AttendancePolicy::firstOrCreate(
                [
                    'tracking_mode' => $this->newGroup['tracking_mode'], 
                    'is_default' => false,
                    'saas_company_id' => $companyId,
                    'name' => $this->trackingModeLabels[$this->newGroup['tracking_mode']] . ' (Custom)'
                ]
            );
            $policyId = $policy->id;
        }

        $groupData = [
            'name' => $this->newGroup['name'],
            'description' => $this->newGroup['description'],
            'applied_policy_id' => $policyId,
            'grace_source' => $this->newGroup['grace_periods_type'] === 'general' ? 'use_global' : 'custom',
            'grace_setting_id' => $graceId,
            'saas_company_id' => $companyId,
        ];

        if ($this->isEditingGroup) {
            $group = EmployeeGroup::find($this->editingGroupId);
            $group->update($groupData);
        } else {
            $group = EmployeeGroup::create($groupData);
        }

        // 3. Sync Employees
        $group->employees()->sync($this->newGroup['employee_ids']);

        // 4. Update Methods
        $group->allowedMethods()->delete();
        foreach ($this->newGroup['methods'] as $method) {
            $group->allowedMethods()->create(['method' => $method, 'is_allowed' => true]);
        }

        $this->showGroupModal = false;
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: $this->isEditingGroup ? tr('Employee group settings and members have been updated.') : tr('New employee group has been created and configured.'));
    }

    public function render()
    {
        return view('systemsettings::livewire.attendance.attendance-settings')
            ->layout('layouts.company-admin');
    }
}





