<?php

namespace Athka\SystemSettings\Livewire\Attendance;

use Livewire\Component;
use Livewire\WithPagination;
use App\Services\ExcelExportService;
use Livewire\WithFileUploads;
use Livewire\Attributes\Url;
use Athka\SystemSettings\Services\LeaveSettingService;
use Athka\SystemSettings\Models\LeavePolicyYear;
use Athka\SystemSettings\Models\LeavePolicy;
use Athka\SystemSettings\Models\PermissionPolicy;
use Athka\SystemSettings\Livewire\Attendance\Traits\HandleLeavePolicies;
use Athka\SystemSettings\Livewire\Attendance\Traits\HandlePermissionPolicies;
use Athka\Saas\Models\SaasCompanyOtherinfo;
use Athka\Attendance\Models\AttendanceLeaveBalance;
use Athka\Attendance\Models\AttendanceLeaveRequest;

class AttendanceLeaveSettings extends Component
{
    use WithPagination, WithFileUploads, HandleLeavePolicies, HandlePermissionPolicies;

    protected function getCompanyId()
    {
        return auth()->user()->saas_company_id;
    }

    private function getCompanyCalendarType(): string
    {
        $companyId = $this->getCompanyId();
        return \Illuminate\Support\Facades\Cache::remember("company_calendar_type_{$companyId}", 3600, function () use ($companyId) {
            $row = \Illuminate\Support\Facades\DB::table('operational_calendars')
                ->where('company_id', $companyId)
                ->first(['calendar_type']);
            return strtolower((string) ($row->calendar_type ?? 'gregorian'));
        });
    }

    public function getAvailableYearsProperty()
    {
        $companyId = auth()->user()->saas_company_id;
        $existingYears = LeavePolicyYear::where('company_id', $companyId)->pluck('year')->toArray();

        $type = $this->getCompanyCalendarType();
        $years = [];

        if ($type === 'hijri') {
            // Hijri range e.g., 1440 to 1460
            for ($i = 1440; $i <= 1460; $i++) {
                if (!in_array($i, $existingYears)) {
                    $years[] = ['value' => $i, 'label' => (string)$i];
                }
            }
        } else {
            // Gregorian range e.g., 2020 to 2040
            for ($i = 2020; $i <= 2040; $i++) {
                if (!in_array($i, $existingYears)) {
                    $years[] = ['value' => $i, 'label' => (string)$i];
                }
            }
        }
        return $years;
    }
    protected function applyCalendarTypeToYearQuery($query)
    {
        $type = $this->getCompanyCalendarType();

        return $type === 'hijri'
            ? $query->whereBetween('year', [1300, 1600])
            : $query->whereBetween('year', [1900, 2500]);
    }

    public function getCurrentCalendarYearProperty(): int
    {
        if ($this->getCompanyCalendarType() === 'hijri' && class_exists(\IntlCalendar::class)) {
            $tz = \IntlTimeZone::createTimeZone('UTC');
            $cal = \IntlCalendar::createInstance($tz, 'en_US@calendar=islamic-umalqura');

            return (int) $cal->get(\IntlCalendar::FIELD_YEAR);
        }

        return (int) now()->year;
    }

    public function getYearRangeHintProperty(): string
    {
        return $this->getCompanyCalendarType() === 'hijri'
            ? tr('Dates are auto-set according to the selected Hijri year. Only the current company year can be active.')
            : tr('Dates are auto-set to Jan 1 → Dec 31. Only the current company year can be active.');
    }

    protected function ensureAnnualDefaultPolicyForYear(LeavePolicyYear $year): void
    {
        $annualConstraint = function ($query) {
            $query->where('settings->meta->system_key', 'annual_default')
                ->orWhere('name', 'سنوية')
                ->orWhere('name', 'Annual');
        };

        $exists = LeavePolicy::where('company_id', $year->company_id)
            ->where('policy_year_id', $year->id)
            ->where($annualConstraint)
            ->exists();

        if ($exists) {
            return;
        }

        $source = LeavePolicy::where('company_id', $year->company_id)
            ->where('policy_year_id', '!=', $year->id)
            ->where($annualConstraint)
            ->orderByDesc('policy_year_id')
            ->first();

        if (!$source) {
            $this->leaveSettingService->ensureDefaultConfiguration($year->company_id);

            $source = LeavePolicy::where('company_id', $year->company_id)
                ->where('policy_year_id', '!=', $year->id)
                ->where($annualConstraint)
                ->orderByDesc('policy_year_id')
                ->first();
        }

        if (!$source) {
            return;
        }

        $newPolicy = $source->replicate();
        $newPolicy->company_id = $year->company_id;
        $newPolicy->policy_year_id = $year->id;

        $settings = (array) ($newPolicy->settings ?? []);
        $settings['meta'] = array_merge((array) data_get($settings, 'meta', []), [
            'system_key' => 'annual_default',
        ]);

        $newPolicy->settings = $settings;
        $newPolicy->save();
    }
    public string $search = '';

    #[Url(except: 'leaves')]
    public string $tab = 'leaves';

    #[Url]
    public string $filterStatus = 'all';

    #[Url]
    public string $filterGender = 'all';

    #[Url]
    public string $filterShowInApp = 'all';

    #[Url]
    public string $filterAttachments = 'all';

    #[Url]
    public string $filterYearId = 'all';

    public $selectedYearId;
    public bool $showAllYears = false;

    // Form fields
    public $name, $leave_type = 'annual', $days_per_year = 30, $editingId;
    public $gender = 'all', $is_active = true, $show_in_app = true, $requires_attachment = false, $description = '';
    public bool $editingNameLocked = false;

    // Settings fields
    public $accrual_method = 'annual_grant', $monthly_accrual_rate = 2.5, $allow_carryover = true, $carryover_days = 15;
    public $weekend_policy = 'exclude', $deduction_policy = 'balance_only';
    public $max_balance = 0, $duration_unit = 'full_day', $notice_min_days = 0, $notice_max_advance_days = 0;
    public $allow_retroactive = false, $selected_leave_excluded_contract_types = [];
    public $note_required = false, $note_text = '', $note_ack_required = false;
    public $attachment_types = ['pdf', 'jpg', 'png'];

    // Modals
    public bool $createOpen = false, $editOpen = false, $deleteOpen = false;
    public bool $yearsOpen = false, $compareOpen = false;

    // Years & Comparison
    public $newYear, $copyFromYearId;
    public $compareYearAId, $compareYearBId;
    public array $compareExpanded = [];

    // Permission state
    public bool $perm_approval_required = true, $perm_show_in_app = true, $perm_is_active = true, $perm_requires_attachment = false;
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

        // Ensure tab is synced from request if not already via URL attribute
        $this->tab = request()->query('tab', $this->tab ?: 'leaves');

        $companyId = auth()->user()->saas_company_id;

        $yearQuery = $this->applyCalendarTypeToYearQuery(
            LeavePolicyYear::where('company_id', $companyId)
        );

        $activeYear = (clone $yearQuery)->where('is_active', true)->first();

        if (!$activeYear) {
            $activeYear = (clone $yearQuery)->orderBy('year', 'desc')->first();
        }

        // ✅ If still no year found, ensure default configuration is created
        if (!$activeYear) {
            $this->leaveSettingService->ensureDefaultConfiguration($companyId);

            $yearQuery = $this->applyCalendarTypeToYearQuery(
                LeavePolicyYear::where('company_id', $companyId)
            );

            $activeYear = (clone $yearQuery)->where('is_active', true)->first()
                ?: (clone $yearQuery)->orderBy('year', 'desc')->first();
        }

        $this->selectedYearId = $activeYear ? $activeYear->id : null;

        $this->loadPermissionSettings();
    }

    public function loadPermissionSettings()
    {
        if (!$this->selectedYearId)
            return;

        $companyId = auth()->user()->saas_company_id;
        $permPolicy = PermissionPolicy::where('company_id', $companyId)
            ->where('policy_year_id', $this->selectedYearId)
            ->first();

        if ($permPolicy) {
            $this->perm_approval_required = (bool) $permPolicy->approval_required;
            $this->perm_show_in_app = (bool) $permPolicy->show_in_app;
            $this->perm_is_active = (bool) ($permPolicy->is_active ?? true);
            $this->perm_requires_attachment = (bool) $permPolicy->requires_attachment;
            $this->perm_deduction_policy = $permPolicy->deduction_policy ?? 'not_allowed_after_limit';
            $this->perm_attachment_types = $permPolicy->attachment_types ?? ['pdf', 'jpg', 'png'];

            // Convert minutes to hours for UI
            $this->perm_monthly_limit_hours = (string) ($permPolicy->monthly_limit_minutes / 60);
            $this->perm_max_request_hours = (string) ($permPolicy->max_request_minutes / 60);
        } else {
            // Default UI Values when no settings exist for this year
            $this->perm_approval_required = true;
            $this->perm_show_in_app = true;
            $this->perm_is_active = true;
            $this->perm_requires_attachment = false;
            $this->perm_deduction_policy = 'not_allowed_after_limit';
            $this->perm_attachment_types = ['pdf', 'jpg', 'png'];
            $this->perm_monthly_limit_hours = '0';
            $this->perm_max_request_hours = '0';
        }
    }

    public function clearAllFilters()
    {
        $this->search = '';
        $this->filterStatus = 'all';
        $this->filterGender = 'all';
        $this->filterShowInApp = 'all';
        $this->filterAttachments = 'all';
        $this->filterYearId = 'all';
        $this->resetPage();
    }

    public function prevYear()
    {
        $current = LeavePolicyYear::find($this->selectedYearId);
        if ($current) {
            $prev = $this->applyCalendarTypeToYearQuery(
                LeavePolicyYear::where('company_id', $current->company_id)
            )
                ->where('year', '<', $current->year)
                ->orderBy('year', 'desc')
                ->first();
            if ($prev) {
                $this->selectedYearId = $prev->id;
                $this->loadPermissionSettings();
            }
        }
    }

    public function nextYear()
    {
        $current = LeavePolicyYear::find($this->selectedYearId);
        if ($current) {
            $next = $this->applyCalendarTypeToYearQuery(
                LeavePolicyYear::where('company_id', $current->company_id)
            )
                ->where('year', '>', $current->year)
                ->orderBy('year', 'asc')
                ->first();
            if ($next) {
                $this->selectedYearId = $next->id;
                $this->loadPermissionSettings();
            }
        }
    }

    public function toggleAllYears()
    {
        $this->showAllYears = !$this->showAllYears;
    }

    public function openYears()
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();
        $this->reset(['newYear', 'copyFromYearId']);
        $this->yearsOpen = true;
    }

    public function closeYears()
    {
        $this->yearsOpen = false;
        $this->resetValidation();
        $this->reset(['newYear', 'copyFromYearId']);
    }

    public function saveYear()
    {
        $this->authorize('settings.attendance.manage');

        $rules = [
            'newYear' => 'required|integer',
            'copyFromYearId' => 'nullable|exists:leave_policy_years,id',
        ];

        $this->validate($rules);

        $companyId = auth()->user()->saas_company_id;

        // Check if year already exists
        $exists = LeavePolicyYear::where('company_id', $companyId)
            ->where('year', $this->newYear)
            ->exists();

        if ($exists) {
            $this->addError('newYear', tr('This year already exists.'));
            return;
        }

        $type = $this->getCompanyCalendarType();
        $yearValue = (int) $this->newYear;

        // Basic date setting based on calendar type
        if ($type === 'hijri') {
            $startsOn = $yearValue . "-01-01";
            $endsOn = $yearValue . "-12-29";
        } else {
            $startsOn = $yearValue . "-01-01";
            $endsOn = $yearValue . "-12-31";
        }

        $year = LeavePolicyYear::create([
            'company_id' => $companyId,
            'year' => $yearValue,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_active' => false,
        ]);

        if ($this->copyFromYearId) {
            $policies = LeavePolicy::where('policy_year_id', $this->copyFromYearId)->get();

            foreach ($policies as $p) {
                $newP = $p->replicate();
                $newP->policy_year_id = $year->id;
                $newP->save();
            }
        }

        $this->ensureAnnualDefaultPolicyForYear($year);

        $this->selectedYearId = $year->id;
        $this->showAllYears = false;
        $this->loadPermissionSettings();

        $this->newYear = '';
        $this->copyFromYearId = '';
        $this->dispatch('toast', type: 'success', message: tr('New year configuration created successfully.'));
        $this->dispatch('year-added');
        $this->reset(['newYear', 'copyFromYearId', 'yearsOpen']);
    }

    public function deleteYear($id)
    {
        $this->authorize('settings.attendance.manage');

        // 1. Check for Leave Policies linked to this year
        if (LeavePolicy::where('policy_year_id', $id)->exists()) {
            $this->dispatch('toast', type: 'error', message: tr('Cannot delete year: It contains leave policies. Please delete the policies first.'));
            return;
        }

        // 2. Check for Employee Balances linked to this year
        if (AttendanceLeaveBalance::where('policy_year_id', $id)->exists()) {
            $this->dispatch('toast', type: 'error', message: tr('Cannot delete year: It has active employee balances linked to it.'));
            return;
        }

        // 3. Check for Leave Requests linked to this year
        if (AttendanceLeaveRequest::where('policy_year_id', $id)->exists()) {
            $this->dispatch('toast', type: 'error', message: tr('Cannot delete year: There are leave requests registered in this year.'));
            return;
        }

        // Safely delete if no dependencies found
        $deleted = LeavePolicyYear::destroy($id);

        if ($deleted) {
            $this->dispatch('toast', type: 'success', message: tr('Year deleted successfully.'));
        } else {
            $this->dispatch('toast', type: 'error', message: tr('Failed to delete year. It might have already been removed or is protected.'));
        }
    }

    public function setYearActive($id)
    {
        $this->authorize('settings.attendance.manage');
        $companyId = auth()->user()->saas_company_id;
        LeavePolicyYear::where('company_id', $companyId)->update(['is_active' => false]);
        LeavePolicyYear::where('id', $id)->update(['is_active' => true]);
        $this->selectedYearId = $id;
        $this->loadPermissionSettings();
        $this->dispatch('toast', type: 'success', message: tr('Active year updated.'));
    }

    public function openCompare()
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();
        $this->reset(['compareYearAId', 'compareYearBId', 'compareExpanded']);
        $this->compareOpen = true;
    }

    public function closeCompare()
    {
        $this->compareOpen = false;
        $this->resetValidation();
        $this->reset(['compareYearAId', 'compareYearBId', 'compareExpanded']);
    }

    public function toggleCompareDetails($key)
    {
        $this->compareExpanded[$key] = !($this->compareExpanded[$key] ?? false);
    }

    public function exportPolicies(ExcelExportService $exporter)
    {
        $companyId = auth()->user()->saas_company_id;
        $filters = [
            'search' => $this->search,
            'status' => $this->filterStatus,
            'gender' => $this->filterGender,
            'show_in_app' => $this->filterShowInApp,
            'requires_attachment' => $this->filterAttachments,
            'year_id' => $this->showAllYears ? 'all' : $this->selectedYearId,
        ];

        $policies = $this->leaveSettingService->getPolicies($companyId, $filters, 1000);

        $fileName = 'LeavePolicies_' . now()->format('Ymd_His');
        $headers = [tr('Leave'), tr('Days'), tr('Year'), tr('Gender'), tr('Status'), tr('Show in App'), tr('Attachments')];

        $data = $policies->map(function ($row) {
            return [
                $row->name,
                $row->days_per_year,
                $row->year?->year ?? '',
                $row->gender,
                $row->is_active ? tr('Active') : tr('Inactive'),
                $row->show_in_app ? tr('Yes') : tr('No'),
                $row->requires_attachment ? tr('Yes') : tr('No'),
            ];
        })->toArray();

        return $exporter->export($fileName, $headers, $data);
    }

    public function render()
    {
        $companyId = auth()->user()->saas_company_id;

        $years = $this->applyCalendarTypeToYearQuery(
            LeavePolicyYear::where('company_id', $companyId)
        )
            ->orderBy('year', 'desc')
            ->get();
        $filters = [
            'search' => $this->search,
            'status' => $this->filterStatus,
            'gender' => $this->filterGender,
            'show_in_app' => $this->filterShowInApp,
            'requires_attachment' => $this->filterAttachments,
            'year_id' => $this->showAllYears ? 'all' : $this->selectedYearId,
        ];

        // Prepare comparison data if modal is open
        $compareRows = [];
        if ($this->compareOpen && $this->compareYearAId && $this->compareYearBId) {
            $policiesA = LeavePolicy::where('policy_year_id', $this->compareYearAId)->get();
            $policiesB = LeavePolicy::where('policy_year_id', $this->compareYearBId)->get();
            $names = $policiesA->pluck('name')->merge($policiesB->pluck('name'))->unique();

            foreach ($names as $name) {
                $compareRows[] = [
                    'name' => $name,
                    'key' => md5($name),
                    'a' => $policiesA->firstWhere('name', $name),
                    'b' => $policiesB->firstWhere('name', $name),
                ];
            }
        }

        return view('systemsettings::livewire.attendance.leave-settings', [
            'rows' => $this->leaveSettingService->getPolicies($companyId, $filters),
            'years' => $years,
            'selectedYearId' => $this->selectedYearId,
            'showAllYears' => $this->showAllYears,
            'compareRows' => $compareRows,
            'compareExpanded' => $this->compareExpanded,
            'compareYearAId' => $this->compareYearAId,
            'compareYearBId' => $this->compareYearBId,
        ])->layout('layouts.company-admin');
    }
}
