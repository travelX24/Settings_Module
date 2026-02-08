<?php

namespace Athka\SystemSettings\Livewire\Attendance;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

use Athka\SystemSettings\Models\LeavePolicyYear;
use Athka\SystemSettings\Models\LeavePolicy;
use Athka\SystemSettings\Models\PermissionPolicy;

class AttendanceLeaveSettings extends Component
{
    use WithPagination;
    use WithFileUploads;

    public string $search = '';
    public string $filterStatus = 'all'; // all|active|inactive
    public string $filterGender = 'all'; // all|male|female
    public string $filterShowInApp = 'all'; // all|yes|no
    public string $filterAttachments = 'all'; // all|yes|no
    public string $filterYearId = 'all'; // all|{id}

    public int $perPage = 10;

    public ?int $selectedYearId = null;
    public bool $showAllYears = false;

    public bool $createOpen = false;
    public bool $editOpen = false;
    public bool $yearsOpen = false;
    public bool $deleteOpen = false;

    public bool $compareOpen = false;
    public bool $copyOpen = false;
    // ✅ Tabs
    public string $tab = 'leaves'; // leaves | permissions

    // =======================
    // Permission Settings
    // =======================
    public bool $perm_approval_required = true;
    public string $perm_monthly_limit_hours = '0';  // 0 = unlimited
    public string $perm_max_request_hours = '0';    // 0 = unlimited

    public string $perm_deduction_policy = 'not_allowed_after_limit'; 
    // not_allowed_after_limit | salary_after_limit | allow_without_deduction

    public bool $perm_show_in_app = true;

    public bool $perm_requires_attachment = false;
    public array $perm_attachment_types = ['pdf', 'jpg', 'png'];
    public string $perm_attachment_max_mb = '2'; // fixed

    protected $queryString = [
        'tab' => ['except' => 'leaves'],
    ];
        // Create/Edit fields
    public int $editingId = 0;

    // ✅ Default system policy (Annual) - name locked
    public string $annualDefaultName = 'سنوية';
    public bool $editingNameLocked = false;

    public string $name = '';
    public string $leave_type = 'annual';

    public string $days_per_year = '30';
    public string $gender = 'all';
    public bool $is_active = true;
    public bool $show_in_app = true;
    public bool $requires_attachment = false;
    public string $description = '';

    // Advanced settings (stored into settings JSON)
    public string $accrual_method = 'annual_grant'; // annual_grant|monthly
    public string $monthly_accrual_rate = '2.5';
    public string $workday_accrual_rate = '0';

    public string $min_accrual = '0.5';
    public string $max_balance = '45';
    public bool $allow_carryover = true;

    public string $carryover_days = '15';

    // ✅ تم إلغاء (months) — نخليه 0 للتوافق فقط
    public string $carryover_expire_months = '0';


    public string $weekend_policy = 'exclude'; // exclude|include|employee_choice
    public string $deduction_policy = 'balance_only'; // balance_only|salary_after_balance
    public string $duration_unit = 'full_day'; // full_day|half_day


    public string $notice_min_days = '3';
    public string $notice_max_advance_days = '90';
    public bool $allow_retroactive = false;

    public bool $note_required = false;
    public string $note_text = '';
    public bool $note_ack_required = false;

    public array $attachment_types = ['pdf', 'jpg', 'png'];
    public string $attachment_max_mb = '2';

    // Year modal
    public string $newYear = '';
    public ?int $copyFromYearId = null;

    // Compare modal
    public ?int $compareYearAId = null;
    public ?int $compareYearBId = null;

    // ✅ Compare details (expand row)
    public array $compareExpanded = []; // ['key' => true]


    // Copy/Import modal
    public ?int $copyPoliciesSourceYearId = null;
    public ?int $copyPoliciesDestYearId = null;
    public bool $copyOverwrite = false;
    public $importFile = null; // TemporaryUploadedFile|null

    public function mount(): void
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) return;

        $currentYear = (int) now()->year;

        // Ensure a year exists for current year
        $year = LeavePolicyYear::query()
            ->where('company_id', $companyId)
            ->where('year', $currentYear)
            ->first();

        if (! $year) {
            $year = LeavePolicyYear::create([
                'company_id' => $companyId,
                'year' => $currentYear,
                'starts_on' => Carbon::create($currentYear, 1, 1)->toDateString(),
                'ends_on' => Carbon::create($currentYear, 12, 31)->toDateString(),
                'is_active' => true,
            ]);
        }

        // Ensure there is an active year (single active)
        DB::transaction(function () use ($companyId, $year) {
            LeavePolicyYear::query()
                ->where('company_id', $companyId)
                ->update(['is_active' => false]);

            LeavePolicyYear::query()
                ->where('company_id', $companyId)
                ->where('id', $year->id)
                ->update(['is_active' => true]);
        });
        $this->selectedYearId = (int) $year->id;

        // ✅ normalize tab
        $this->tab = in_array($this->tab, ['leaves', 'permissions'], true) ? $this->tab : 'leaves';

        // ✅ Ensure default annual policy exists for the selected year
        // ✅ Ensure default annual policy exists for the selected year
        $this->ensureAnnualDefaultPolicy($companyId, (int) $this->selectedYearId);

        // ✅ Ensure/load permission settings for selected year
        $this->ensurePermissionPolicy($companyId, (int) $this->selectedYearId);
        $this->loadPermissionSettings($companyId, (int) $this->selectedYearId);

    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterGender(): void { $this->resetPage(); }
    public function updatedFilterShowInApp(): void { $this->resetPage(); }
    public function updatedFilterAttachments(): void { $this->resetPage(); }
    public function updatedFilterYearId(): void
    {
        if ($this->filterYearId !== 'all') {
            $this->showAllYears = true;
        }
        $this->resetPage();
    }

    protected function resolveCompanyId(): int
    {
        $user = auth()->user();

        $id = (int) ($user->company_id ?? 0);
        if ($id > 0) return $id;

        $id = (int) ($user->company?->id ?? 0);
        if ($id > 0) return $id;

        foreach (['company_id', 'current_company_id', 'saas_company_id', 'current_saas_company_id'] as $key) {
            $val = session($key);
            if (is_numeric($val) && (int) $val > 0) return (int) $val;
            if (is_object($val) && isset($val->id) && (int) $val->id > 0) return (int) $val->id;
        }

        $host = request()->getHost();
        $slug = Str::before($host, '.');

        if (Schema::hasTable('saas_companies')) {
            if ($slug && ! in_array($slug, ['localhost', '127', 'www'], true)) {
                $found = DB::table('saas_companies')->where('slug', $slug)->value('id');
                if ($found) return (int) $found;
            }

            $found = DB::table('saas_companies')->where('primary_domain', $host)->value('id');
            if ($found) return (int) $found;
        }

        return 0;
    }

    public function getYearsProperty()
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) return collect();

        return LeavePolicyYear::query()
            ->where('company_id', $companyId)
            ->orderByDesc('year')
            ->get();
    }

    public function getRowsProperty()
    {
        $companyId = $this->resolveCompanyId();
        $q = LeavePolicy::query()->with('year');

        if ($companyId > 0) {
            $q->where('company_id', $companyId);
        }

        if ($this->filterYearId !== 'all' && is_numeric($this->filterYearId)) {
            $q->where('policy_year_id', (int) $this->filterYearId);
        } else {
            if (! $this->showAllYears && $this->selectedYearId) {
                $q->where('policy_year_id', $this->selectedYearId);
            }
        }

        if ($this->search !== '') {
            $q->where('name', 'like', '%' . $this->search . '%');
        }

        if ($this->filterStatus !== 'all') {
            $q->where('is_active', $this->filterStatus === 'active');
        }

        if ($this->filterGender !== 'all') {
            $q->where('gender', $this->filterGender);
        }

        if ($this->filterShowInApp !== 'all') {
            $q->where('show_in_app', $this->filterShowInApp === 'yes');
        }

        if ($this->filterAttachments !== 'all') {
            $q->where('requires_attachment', $this->filterAttachments === 'yes');
        }

        return $q->orderBy('name')->paginate($this->perPage);
    }

    // ---------------------------
    // Year navigation
    // ---------------------------
    public function toggleAllYears(): void
    {
        $this->showAllYears = ! $this->showAllYears;

        if (! $this->showAllYears) {
            $this->filterYearId = 'all';
        }

        $this->resetPage();
    }

    public function selectYear(int $id): void
    {
        $this->selectedYearId = $id;
        $this->showAllYears = false;
        $this->filterYearId = 'all';

        // ✅ ensure annual policy exists for ANY selected year
        $companyId = $this->resolveCompanyId();
        if ($companyId > 0) {
            $this->ensureAnnualDefaultPolicy($companyId, (int) $id);

            $this->ensurePermissionPolicy($companyId, (int) $id);
            $this->loadPermissionSettings($companyId, (int) $id);
        }


        $this->resetPage();
    }

    public function prevYear(): void
    {
        $years = $this->years->values();
        $idx = $years->search(fn ($y) => (int) $y->id === (int) $this->selectedYearId);
        if ($idx === false) return;

        $next = $years->get($idx + 1); // because sorted desc
        if ($next) $this->selectYear((int) $next->id);
    }

    public function nextYear(): void
    {
        $years = $this->years->values();
        $idx = $years->search(fn ($y) => (int) $y->id === (int) $this->selectedYearId);
        if ($idx === false) return;

        $prev = $years->get($idx - 1);
        if ($prev) $this->selectYear((int) $prev->id);
    }

    // ✅ Tabs
    public function switchTab(string $tab): void
    {
        $tab = trim($tab);
        $tab = in_array($tab, ['leaves', 'permissions'], true) ? $tab : 'leaves';

        if ($this->tab === $tab) return;

        $this->tab = $tab;

        // ✅ close modals when switching tabs
        $this->createOpen = false;
        $this->editOpen = false;
        $this->yearsOpen = false;
        $this->deleteOpen = false;
        $this->compareOpen = false;
        $this->copyOpen = false;
        $this->resetValidation();
        $this->resetPage();

        if ($this->tab === 'permissions') {
            $companyId = $this->resolveCompanyId();
            if ($companyId > 0 && $this->selectedYearId) {
                $this->ensurePermissionPolicy($companyId, (int) $this->selectedYearId);
                $this->loadPermissionSettings($companyId, (int) $this->selectedYearId);
            }
        }

    }

    // ---------------------------
    // Create/Edit Leave Policy
    // ---------------------------
    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $this->resetValidation();
        $this->editingId = 0;
        $this->editingNameLocked = false;

        $this->name = '';
        $this->leave_type = 'annual';
        $this->days_per_year = '30';
        $this->gender = 'all';
        $this->is_active = true;
        $this->show_in_app = true;
        $this->requires_attachment = false;
        $this->description = '';

        $this->resetAdvancedDefaults();
        $this->syncMonthlyAccrualRate();

        $this->createOpen = true;
    }

    public function closeCreate(): void
    {
        $this->createOpen = false;
    }

    public function saveCreate(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) {
            session()->flash('error', tr('Company context not found'));
            return;
        }

        if (! $this->selectedYearId) {
            session()->flash('error', tr('Please select a year first'));
            return;
                }
        $this->attachment_max_mb = '2';

        $this->syncMonthlyAccrualRate();
        $data = $this->validate($this->policyRules());

        $settings = $this->buildSettingsFromValidated($data);

        LeavePolicy::create([
            'company_id' => $companyId,
            'policy_year_id' => (int) $this->selectedYearId,

            'name' => $data['name'],
            'leave_type' => 'annual',
            'days_per_year' => $data['days_per_year'],

            'gender' => $data['gender'],
            'is_active' => (bool) $data['is_active'],
            'show_in_app' => (bool) $data['show_in_app'],
            'requires_attachment' => (bool) $data['requires_attachment'],

            'description' => $data['description'] ?? null,
            'settings' => $settings,
        ]);

        session()->flash('success', tr('Saved successfully'));
        $this->closeCreate();
        $this->resetPage();
    }

    public function openEdit(int $id): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $companyId = $this->resolveCompanyId();

        $row = LeavePolicy::query()
            ->where('id', $id)
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->firstOrFail();

        $this->editingId = (int) $row->id;

        // ✅ lock name if this is the system annual policy
        $this->editingNameLocked = $this->isAnnualDefaultPolicy($row);

        $this->name = $this->editingNameLocked ? $this->annualDefaultName : (string) $row->name;

        $this->leave_type = 'annual';

        $this->days_per_year = (string) $row->days_per_year;

        $this->gender = $this->editingNameLocked ? 'all' : (string) $row->gender;
        $this->is_active = (bool) $row->is_active;
        $this->show_in_app = (bool) $row->show_in_app;
        $this->requires_attachment = (bool) $row->requires_attachment;

        $this->description = (string) ($row->description ?? '');

        $this->hydrateAdvancedFromRow($row);
        $this->syncMonthlyAccrualRate();

        $this->resetValidation();
        $this->editOpen = true;
    }

    public function closeEdit(): void
    {
        $this->editOpen = false;
        $this->editingId = 0;
        $this->editingNameLocked = false;
    }

    public function saveEdit(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $companyId = $this->resolveCompanyId();
        $id = (int) $this->editingId;

        $row = LeavePolicy::query()
            ->where('id', $id)
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->firstOrFail();

        if ($this->editingNameLocked) {
            $this->name = $this->annualDefaultName;
        }
        if ($this->editingNameLocked) {
            $this->gender = 'all';
        }

        if (! $this->allow_carryover) {
            $this->carryover_days = '0';
            $this->carryover_expire_months = '0';
        }
        $this->attachment_max_mb = '2';

        $this->syncMonthlyAccrualRate();
        $data = $this->validate($this->policyRules());


        $settings = $this->buildSettingsFromValidated($data);

        if ($this->editingNameLocked) {
            data_set($settings, 'meta.system_key', 'annual_default');
            data_set($settings, 'meta.lock_name', true);
        }

        $row->update([
            'name' => $this->editingNameLocked ? $this->annualDefaultName : $data['name'],
            'leave_type' => 'annual',
            'days_per_year' => $data['days_per_year'],
            'gender' => $data['gender'],
            'is_active' => (bool) $data['is_active'],
            'show_in_app' => (bool) $data['show_in_app'],
            'requires_attachment' => (bool) $data['requires_attachment'],
            'description' => $data['description'] ?? null,
            'settings' => $settings,
        ]);

        session()->flash('success', tr('Saved successfully'));
        $this->closeEdit();
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);
        $companyId = $this->resolveCompanyId();

        $row = LeavePolicy::query()
            ->where('id', $id)
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->first();

        if ($row && $this->isAnnualDefaultPolicy($row)) {
            session()->flash('error', tr('You cannot delete the annual system policy.'));
            return;
        }

        $this->editingId = $id;
        $this->deleteOpen = true;
    }

    public function closeDelete(): void
    {
        $this->deleteOpen = false;
        $this->editingId = 0;
    }

    public function deleteNow(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $companyId = $this->resolveCompanyId();
        $id = (int) $this->editingId;

        LeavePolicy::query()
            ->where('id', $id)
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->delete();

        session()->flash('success', tr('Deleted successfully'));

        $this->closeDelete();
        $this->resetPage();
    }

    // ---------------------------
    // Manage Years + Copy (Create Year)
    // ---------------------------
    public function openYears(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $this->resetValidation();
        $this->newYear = (string) now()->year;
        $this->copyFromYearId = null;

        $this->yearsOpen = true;
    }

    public function closeYears(): void
    {
        $this->yearsOpen = false;
    }

    public function saveYear(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) {
            session()->flash('error', tr('Company context not found'));
            return;
        }

        $data = $this->validate([
            'newYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'copyFromYearId' => ['nullable', 'integer'],
        ]);

        $yearInt = (int) $data['newYear'];

        $year = LeavePolicyYear::query()
            ->where('company_id', $companyId)
            ->where('year', $yearInt)
            ->first();

        if ($year) {
            session()->flash('error', tr('Year already exists'));
            return;
        }

        $isCurrentYear = $yearInt === (int) now()->year;

        DB::transaction(function () use ($companyId, $yearInt, $data, $isCurrentYear, &$year) {
            if ($isCurrentYear) {
                LeavePolicyYear::query()
                    ->where('company_id', $companyId)
                    ->update(['is_active' => false]);
            }

            $year = LeavePolicyYear::create([
                'company_id' => $companyId,
                'year' => $yearInt,
                'starts_on' => Carbon::create($yearInt, 1, 1)->toDateString(),
                'ends_on' => Carbon::create($yearInt, 12, 31)->toDateString(),
                'is_active' => $isCurrentYear,
            ]);

            $fromId = isset($data['copyFromYearId']) ? (int) $data['copyFromYearId'] : 0;

            if ($fromId > 0) {
                $source = LeavePolicy::query()
                    ->where('company_id', $companyId)
                    ->where('policy_year_id', $fromId)
                    ->get();

                foreach ($source as $row) {
                    LeavePolicy::create([
                        'company_id' => $companyId,
                        'policy_year_id' => (int) $year->id,

                        'name' => $row->name,
                        'leave_type' => $row->leave_type,
                        'days_per_year' => $row->days_per_year,

                        'gender' => $row->gender,
                        'is_active' => $row->is_active,
                        'show_in_app' => $row->show_in_app,
                        'requires_attachment' => $row->requires_attachment,

                        'description' => $row->description,
                        'settings' => $row->settings ?? [],
                    ]);
                }
            }

            // ✅ ensure annual policy for the newly created year
            $this->ensureAnnualDefaultPolicy($companyId, (int) $year->id);
        });

        session()->flash('success', tr('Saved successfully'));

        $this->closeYears();
        $this->selectYear((int) $year->id);
    }

    public function setYearActive(int $id): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) return;

        $year = LeavePolicyYear::query()
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (! $year) return;

        if ((int) $year->year !== (int) now()->year) {
            session()->flash('error', tr('Only the current year can be active.'));
            return;
        }

        DB::transaction(function () use ($companyId, $id) {
            LeavePolicyYear::query()
                ->where('company_id', $companyId)
                ->update(['is_active' => false]);

            LeavePolicyYear::query()
                ->where('company_id', $companyId)
                ->where('id', $id)
                ->update(['is_active' => true]);
        });

        session()->flash('success', tr('Saved successfully'));
    }

    public function deleteYear(int $id): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) return;

        $year = LeavePolicyYear::query()
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (! $year) return;

        if ($year->is_active) {
            session()->flash('error', tr('You cannot delete the active year. Set another year active first.'));
            return;
        }

        $hasPolicies = LeavePolicy::query()
            ->where('company_id', $companyId)
            ->where('policy_year_id', $id)
            ->exists();

        if ($hasPolicies) {
            session()->flash('error', tr('You cannot delete a year that has leave policies.'));
            return;
        }

        $year->delete();

        if ((int) $this->selectedYearId === (int) $id) {
            $fallback = LeavePolicyYear::query()
                ->where('company_id', $companyId)
                ->orderByDesc('year')
                ->first();
            $this->selectedYearId = $fallback ? (int) $fallback->id : null;
        }

        session()->flash('success', tr('Deleted successfully'));
    }

    // ---------------------------
    // Compare Years
    // ---------------------------
    public function openCompare(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $this->resetValidation();

        // ✅ reset expanded rows
        $this->compareExpanded = [];

        $years = $this->years->values();

        $this->compareYearAId = $this->selectedYearId ?: ($years->first()?->id ?? null);

                $alt = null;
            if ($this->compareYearAId) {
                $idx = $years->search(fn ($y) => (int) $y->id === (int) $this->compareYearAId);
                $alt = $idx !== false ? ($years->get($idx + 1) ?: $years->get($idx - 1)) : null;
            }
            $this->compareYearBId = $alt ? (int) $alt->id : ($years->get(1)?->id ?? null);

            $this->compareOpen = true;
        }
    public function updatedCompareYearAId(): void { $this->compareExpanded = []; }
    public function updatedCompareYearBId(): void { $this->compareExpanded = []; }

    public function closeCompare(): void
    {
        $this->compareOpen = false;
        $this->compareYearAId = null;
        $this->compareYearBId = null;

        // ✅ reset expanded rows
        $this->compareExpanded = [];
    }

    public function toggleCompareDetails(string $key): void
    {
        if (isset($this->compareExpanded[$key])) {
            unset($this->compareExpanded[$key]);
            return;
        }

        // لو تبغى يسمح بواحد فقط مفتوح في نفس الوقت:
        $this->compareExpanded = [$key => true];

        // لو تبغى يسمح بعدة صفوف مفتوحة، بدل السطرين فوق بهذا:
        // $this->compareExpanded[$key] = true;
    }

    public function getCompareRowsProperty()
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) return collect();

        $a = (int) ($this->compareYearAId ?? 0);
        $b = (int) ($this->compareYearBId ?? 0);
        if ($a <= 0 || $b <= 0) return collect();

        $policies = LeavePolicy::query()
            ->where('company_id', $companyId)
            ->whereIn('policy_year_id', [$a, $b])
            ->get();

        $byYear = [
            $a => [],
            $b => [],
        ];

        foreach ($policies as $p) {
            $key = $this->policyCompareKey($p->name, $p->leave_type);
            $byYear[(int) $p->policy_year_id][$key] = $p;
        }

        $keys = collect(array_keys($byYear[$a]))
            ->merge(array_keys($byYear[$b]))
            ->unique()
            ->values();

        $rows = $keys->map(function ($key) use ($byYear, $a, $b) {
            $pa = $byYear[$a][$key] ?? null;
            $pb = $byYear[$b][$key] ?? null;

            $name = $pa?->name ?? $pb?->name ?? '';
            $type = $pa?->leave_type ?? $pb?->leave_type ?? '';

            return [
                'key' => $key,
                'name' => $name,
                'leave_type' => $type,
                'a' => $pa,
                'b' => $pb,
            ];
        });

        return $rows;
    }

    protected function policyCompareKey(string $name, string $leaveType): string
    {
        $n = trim(mb_strtolower($name));
        $t = trim(mb_strtolower($leaveType));
        return $n . '|' . $t;
    }

    // ---------------------------
    // Copy / Import / Export Policies
    // ---------------------------
    public function openCopyPolicies(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $this->resetValidation();
        $this->copyPoliciesSourceYearId = null;
        $this->copyPoliciesDestYearId = $this->selectedYearId;
        $this->copyOverwrite = false;
        $this->importFile = null;

        $this->copyOpen = true;
    }

    public function closeCopyPolicies(): void
    {
        $this->copyOpen = false;
        $this->copyPoliciesSourceYearId = null;
        $this->copyPoliciesDestYearId = null;
        $this->copyOverwrite = false;
        $this->importFile = null;
    }

    public function copyPoliciesNow(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) {
            session()->flash('error', tr('Company context not found'));
            return;
        }

        $data = $this->validate([
            'copyPoliciesSourceYearId' => ['required', 'integer'],
            'copyPoliciesDestYearId' => ['required', 'integer', 'different:copyPoliciesSourceYearId'],
            'copyOverwrite' => ['boolean'],
        ]);

        $fromId = (int) $data['copyPoliciesSourceYearId'];
        $toId = (int) $data['copyPoliciesDestYearId'];

        $source = LeavePolicy::query()
            ->where('company_id', $companyId)
            ->where('policy_year_id', $fromId)
            ->get();

        if ($source->isEmpty()) {
            session()->flash('error', tr('No policies found in the source year'));
            return;
        }

        DB::transaction(function () use ($companyId, $source, $toId, $data) {
            foreach ($source as $row) {
                $match = LeavePolicy::query()
                    ->where('company_id', $companyId)
                    ->where('policy_year_id', $toId)
                    ->where('name', $row->name)
                    ->where('leave_type', $row->leave_type)
                    ->where('gender', $row->gender)
                    ->first();

                $payload = [
                    'company_id' => $companyId,
                    'policy_year_id' => $toId,

                    'name' => $row->name,
                    'leave_type' => $row->leave_type,
                    'days_per_year' => $row->days_per_year,

                    'gender' => $row->gender,
                    'is_active' => $row->is_active,
                    'show_in_app' => $row->show_in_app,
                    'requires_attachment' => $row->requires_attachment,

                    'description' => $row->description,
                    'settings' => $row->settings ?? [],
                ];

                if ($match) {
                    if (!empty($data['copyOverwrite'])) {
                        $match->update($payload);
                    }
                } else {
                    LeavePolicy::create($payload);
                }
            }
        });

        session()->flash('success', tr('Saved successfully'));
        $this->closeCopyPolicies();
        $this->resetPage();
    }

    public function exportPolicies()
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) {
            session()->flash('error', tr('Company context not found'));
            return null;
        }

        $yearId = (int) ($this->selectedYearId ?? 0);
        if ($yearId <= 0) {
            session()->flash('error', tr('Please select a year first'));
            return null;
        }

        $year = LeavePolicyYear::query()
            ->where('company_id', $companyId)
            ->where('id', $yearId)
            ->first();

        $policies = LeavePolicy::query()
            ->where('company_id', $companyId)
            ->where('policy_year_id', $yearId)
            ->orderBy('name')
            ->get();

        // ✅ Excel (CSV) export بدون أي packages إضافية
        $csvSafe = function ($value): string {
            if ($value === null) return '';
            if (is_bool($value)) return $value ? '1' : '0';
            if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE);

            $v = (string) $value;

            // حماية من Excel Formula Injection (=, +, -, @)
            $trim = ltrim($v);
            if ($trim !== '' && preg_match('/^[=\-+@]/', $trim)) {
                $v = "'" . $v;
            }

            return $v;
        };

        $filename = 'leave-policies-' . ($year?->year ?? $yearId) . '.csv';

        // ✅ إذا عندك جهاز يفتح CSV كله في عمود واحد: خلها ';'
        $delimiter = ',';

        // =========================
        // ✅ Dynamic flatten settings into columns
        // =========================

        // 1) Decode settings safely
        $decodeSettings = function ($value): array {
            if (is_array($value)) return $value;
            if (is_string($value)) {
                $d = json_decode($value, true);
                return is_array($d) ? $d : [];
            }
            return [];
        };

        // 2) Normalize old/new formats:
        // - if settings has ["annual" => [...]] use it as base
        // - merge root keys (except annual) into base
        $normalizeSettings = function (array $settings): array {
            if (isset($settings['annual']) && is_array($settings['annual'])) {
                $base = $settings['annual'];

                foreach ($settings as $k => $v) {
                    if ($k === 'annual') continue;

                    // لو meta موجود في root نخليه داخل base.meta
                    if ($k === 'meta' && is_array($v)) {
                        $base['meta'] = $v;
                        continue;
                    }

                    // أي مفاتيح أخرى على مستوى root ندمجها (لو احتجتها مستقبلاً)
                    if (!array_key_exists($k, $base)) {
                        $base[$k] = $v;
                    }
                }

                return $base;
            }

            return $settings;
        };

        // 3) Flatten assoc arrays to dot keys, lists -> joined by |
        $flatten = null;
        $flatten = function (array $arr, string $prefix = '') use (&$flatten): array {
            $out = [];

            foreach ($arr as $k => $v) {
                $k = (string) $k;
                $key = $prefix === '' ? $k : ($prefix . '.' . $k);

                if (is_array($v)) {
                    // list -> single cell
                    if (array_is_list($v)) {
                        $out[$key] = implode('|', array_map(
                            fn ($x) => (is_scalar($x) || $x === null) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE),
                            $v
                        ));
                    } else {
                        // assoc -> recurse
                        $out = array_replace($out, $flatten($v, $key));
                    }
                } else {
                    $out[$key] = $v;
                }
            }

            return $out;
        };

        // 4) Build union of all keys across policies + cache flattened settings per policy
        $flatSettingsById = [];
        $settingsKeys = [];

        foreach ($policies as $p) {
            /** @var \Athka\SystemSettings\Models\LeavePolicy $p */
            $s = $decodeSettings($p->settings ?? []);
            $s = $normalizeSettings($s);
            $flat = $flatten($s);

            $flatSettingsById[(int) $p->id] = $flat;
            $settingsKeys = array_values(array_unique(array_merge($settingsKeys, array_keys($flat))));
        }

        // stable order
        sort($settingsKeys);

        // header names: settings_meta_system_key instead of settings.meta.system_key
        $settingsHeader = array_map(function ($k) {
            return 'settings_' . str_replace(['.', '-'], '_', $k);
        }, $settingsKeys);

        // =========================
        // ✅ Stream CSV
        // =========================
        return response()->streamDownload(function () use (
            $policies,
            $year,
            $companyId,
            $yearId,
            $csvSafe,
            $delimiter,
            $settingsHeader,
            $settingsKeys,
            $flatSettingsById
        ) {
            $out = fopen('php://output', 'w');

            // ✅ UTF-8 BOM عشان العربية تظهر صح في Excel Windows
            fwrite($out, "\xEF\xBB\xBF");

            // معلومات أعلى الملف (اختياري)
            fputcsv($out, ['exported_at', now()->toIso8601String()], $delimiter);
            fputcsv($out, ['company_id', (string) $companyId], $delimiter);
            fputcsv($out, ['policy_year_id', (string) $yearId], $delimiter);
            fputcsv($out, ['policy_year', (string) ($year?->year ?? '')], $delimiter);
            fputcsv($out, [], $delimiter); // سطر فاضي

            // Header
            $baseHeader = [
                'name',
                'leave_type',
                'days_per_year',
                'gender',
                'is_active',
                'show_in_app',
                'requires_attachment',
                'description',
            ];

            fputcsv($out, array_merge($baseHeader, $settingsHeader), $delimiter);

            foreach ($policies as $p) {
                /** @var \Athka\SystemSettings\Models\LeavePolicy $p */

                $row = [
                    $csvSafe($p->name),
                    $csvSafe($p->leave_type),
                    $csvSafe($p->days_per_year),
                    $csvSafe($p->gender),
                    $csvSafe((bool) $p->is_active),
                    $csvSafe((bool) $p->show_in_app),
                    $csvSafe((bool) $p->requires_attachment),
                    $csvSafe($p->description),
                ];

                $flat = $flatSettingsById[(int) $p->id] ?? [];

                foreach ($settingsKeys as $k) {
                    $row[] = $csvSafe($flat[$k] ?? '');
                }

                fputcsv($out, $row, $delimiter);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }


    public function importPoliciesFromFile(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) {
            session()->flash('error', tr('Company context not found'));
            return;
        }

        $data = $this->validate([
            'copyPoliciesDestYearId' => ['required', 'integer'],
            'copyOverwrite' => ['boolean'],
            'importFile' => ['required', 'file', 'mimes:json,txt', 'max:2048'],
        ]);

        $toId = (int) $data['copyPoliciesDestYearId'];

        $raw = file_get_contents($this->importFile->getRealPath());
        $json = json_decode($raw, true);

        if (!is_array($json) || !isset($json['policies']) || !is_array($json['policies'])) {
            session()->flash('error', tr('Invalid file format'));
            return;
        }

        $rows = $json['policies'];

        DB::transaction(function () use ($companyId, $toId, $rows, $data) {
            foreach ($rows as $r) {
                if (!is_array($r)) continue;

                $name = (string) ($r['name'] ?? '');
                $leaveType = (string) ($r['leave_type'] ?? '');
                $gender = (string) ($r['gender'] ?? 'all');

                if ($name === '' || $leaveType === '') continue;

                $payload = [
                    'company_id' => $companyId,
                    'policy_year_id' => $toId,

                    'name' => $name,
                    'leave_type' => $leaveType,
                    'days_per_year' => (float) ($r['days_per_year'] ?? 0),

                    'gender' => in_array($gender, ['all', 'male', 'female'], true) ? $gender : 'all',
                    'is_active' => (bool) ($r['is_active'] ?? true),
                    'show_in_app' => (bool) ($r['show_in_app'] ?? true),
                    'requires_attachment' => (bool) ($r['requires_attachment'] ?? false),

                    'description' => $r['description'] ?? null,
                    'settings' => is_array($r['settings'] ?? null) ? $r['settings'] : [],
                ];

                $match = LeavePolicy::query()
                    ->where('company_id', $companyId)
                    ->where('policy_year_id', $toId)
                    ->where('name', $name)
                    ->where('leave_type', $leaveType)
                    ->where('gender', $payload['gender'])
                    ->first();

                if ($match) {
                    if (!empty($data['copyOverwrite'])) {
                        $match->update($payload);
                    }
                } else {
                    LeavePolicy::create($payload);
                }
            }
        });

        session()->flash('success', tr('Saved successfully'));
        $this->closeCopyPolicies();
        $this->resetPage();
    }

    // ---------------------------
    // Helpers: rules + settings json
    // ---------------------------
    protected function policyRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'days_per_year' => ['required', 'numeric', 'min:0', 'max:366'],
            'gender' => ['required', 'in:all,male,female'],
            'is_active' => ['boolean'],
            'show_in_app' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],

            // ✅ آلية الاستحقاق خيارين فقط
            'accrual_method' => ['required', 'in:annual_grant,monthly'],
            'monthly_accrual_rate' => ['nullable', 'numeric', 'min:0', 'max:366', 'required_if:accrual_method,monthly'],

            'min_accrual' => ['required', 'numeric', 'min:0', 'max:366'],
            'max_balance' => ['required', 'numeric', 'min:0', 'max:999'],
            'allow_carryover' => ['boolean'],

            'carryover_days' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999',
                Rule::requiredIf(fn () => (bool) $this->allow_carryover),
            ],

            // ✅ تم إلغاء carryover_expire_months من التحقق (UI removed)


            'weekend_policy' => ['required', 'in:exclude,include,employee_choice'],
            'deduction_policy' => ['required', 'in:balance_only,salary_after_balance'],
            'duration_unit' => ['required', 'in:full_day,half_day'],


            'notice_min_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'notice_max_advance_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'allow_retroactive' => ['boolean'],

            'note_required' => ['boolean'],
            'note_text' => ['nullable', 'string', 'max:500'],
            'note_ack_required' => ['boolean'],

            'attachment_types' => ['array'],
            'attachment_types.*' => ['in:pdf,jpg,png'],
            'attachment_max_mb' => ['required', 'numeric', Rule::in([2, '2'])],
        ];
    }

    protected function buildSettingsFromValidated(array $data): array
    {
        // ✅ General Constraints removed بالكامل
        return [
            'accrual' => [
                'method' => $data['accrual_method'],
                'monthly_rate' => $data['accrual_method'] === 'monthly'
                    ? (float) ($data['monthly_accrual_rate'] ?? 0)
                    : null,
                'min_unit' => (float) $data['min_accrual'],
                'max_balance' => (float) $data['max_balance'],
                'carryover_days' => !empty($data['allow_carryover'])
                    ? (float) ($data['carryover_days'] ?? 0)
                    : 0.0,

                // ✅ تم إلغاء (months) — نخليها 0 دائماً
                'carryover_expire_months' => 0,

            ],

            'weekend_policy' => $data['weekend_policy'],
            'deduction_policy' => $data['deduction_policy'],
            'duration_unit' => $data['duration_unit'],

            'notice' => [
                'min_days' => (int) $data['notice_min_days'],
                'max_advance_days' => (int) $data['notice_max_advance_days'],
                'allow_retroactive' => (bool) $data['allow_retroactive'],
            ],

            'note' => [
                'required' => (bool) $data['note_required'],
                'text' => $data['note_text'] ?? null,
                'ack_required' => (bool) $data['note_ack_required'],
            ],

           'attachments' => [
                'types' => array_values(is_array($data['attachment_types'] ?? null) ? $data['attachment_types'] : []),
                'max_mb' => 2,
            ],

        ];
    }

    protected function resetAdvancedDefaults(): void
    {
        $this->accrual_method = 'annual_grant';
        $this->monthly_accrual_rate = '2.5';
        $this->workday_accrual_rate = '0';

        $this->min_accrual = '0.5';
        $this->max_balance = '45';
        $this->allow_carryover = true;

        $this->carryover_days = '15';
        $this->carryover_expire_months = '0';


        $this->weekend_policy = 'exclude';
        $this->deduction_policy = 'balance_only';
        $this->duration_unit = 'full_day';

        $this->notice_min_days = '3';
        $this->notice_max_advance_days = '90';
        $this->allow_retroactive = false;

        $this->note_required = false;
        $this->note_text = '';
        $this->note_ack_required = false;

        $this->attachment_types = ['pdf', 'jpg', 'png'];
        $this->attachment_max_mb = '2';
    }

    protected function hydrateAdvancedFromRow(LeavePolicy $row): void
    {
        $s = is_array($row->settings ?? null) ? $row->settings : [];

        $this->accrual_method = (string) data_get($s, 'accrual.method', 'annual_grant');
        $this->monthly_accrual_rate = (string) data_get($s, 'accrual.monthly_rate', '2.5');
        $this->workday_accrual_rate = (string) data_get($s, 'accrual.workday_rate', '0');

        $this->min_accrual = (string) data_get($s, 'accrual.min_unit', '0.5');
        $this->max_balance = (string) data_get($s, 'accrual.max_balance', '45');
        $this->carryover_days = (string) data_get($s, 'accrual.carryover_days', '15');

        $this->allow_carryover = is_numeric($this->carryover_days)
            ? ((float) $this->carryover_days > 0)
            : false;

        if (! $this->allow_carryover) {
            $this->carryover_days = '0';
        }

        // ✅ تم إلغاء (months)
        $this->carryover_expire_months = '0';


       $this->weekend_policy = (string) data_get($s, 'weekend_policy', 'exclude');

        $ded = (string) data_get($s, 'deduction_policy', 'balance_only');
        $this->deduction_policy = in_array($ded, ['balance_only', 'salary_after_balance'], true)
            ? $ded
            : 'balance_only';

        $dur = (string) data_get($s, 'duration_unit', 'full_day');
        $this->duration_unit = in_array($dur, ['full_day', 'half_day'], true)
            ? $dur
            : 'full_day';


        $this->notice_min_days = (string) data_get($s, 'notice.min_days', '3');
        $this->notice_max_advance_days = (string) data_get($s, 'notice.max_advance_days', '90');
        $this->allow_retroactive = (bool) data_get($s, 'notice.allow_retroactive', false);

        $this->note_required = (bool) data_get($s, 'note.required', false);
        $this->note_text = (string) data_get($s, 'note.text', '');
        $this->note_ack_required = (bool) data_get($s, 'note.ack_required', false);

        $types = data_get($s, 'attachments.types', ['pdf', 'jpg', 'png']);
        $this->attachment_types = is_array($types) ? $types : ['pdf', 'jpg', 'png'];
        $this->attachment_max_mb = '2';
    }

    public function render()
    {
        return view('systemsettings::livewire.attendance.leave-settings', [
            'rows' => $this->rows,
            'years' => $this->years,
            'compareRows' => $this->compareRows,
        ])->layout('layouts.company-admin');
    }

    public function updatedDaysPerYear($value = null): void
    {
        $this->syncMonthlyAccrualRate();
    }

    public function updatedAccrualMethod($value = null): void
    {
        $this->syncMonthlyAccrualRate();
    }

    protected function syncMonthlyAccrualRate(): void
    {
        $days = is_numeric($this->days_per_year) ? (float) $this->days_per_year : 0.0;
        $rate = $days / 12;
        $formatted = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
        $this->monthly_accrual_rate = $formatted === '' ? '0' : $formatted;
    }

    protected function isAnnualDefaultPolicy(LeavePolicy $row): bool
    {
        $key = (string) data_get($row->settings ?? [], 'meta.system_key', '');
        if ($key === 'annual_default') {
            return true;
        }

        // fallback (older records) if name already "سنوية"
        return trim((string) $row->name) === $this->annualDefaultName;
    }

    protected function defaultAnnualSettings(): array
    {
        // ✅ General Constraints removed بالكامل
        return [
            'meta' => [
                'system_key' => 'annual_default',
                'lock_name' => true,
            ],

            'accrual' => [
                'method' => 'annual_grant',
                'monthly_rate' => null,
                'min_unit' => 0.5,
                'max_balance' => 45,
                'carryover_days' => 15,
                'carryover_expire_months' => 0,
            ],

            'weekend_policy' => 'exclude',
            'deduction_policy' => 'balance_only',
            'duration_unit' => 'full_day',

            'notice' => [
                'min_days' => 3,
                'max_advance_days' => 90,
                'allow_retroactive' => false,
            ],

            'note' => [
                'required' => false,
                'text' => null,
                'ack_required' => false,
            ],

          'attachments' => [
                'types' => ['pdf', 'jpg', 'png'],
                'max_mb' => 2,
            ],

        ];
    }

    protected function ensureAnnualDefaultPolicy(int $companyId, int $policyYearId): void
    {
        // 1) find by meta key
        $row = LeavePolicy::query()
            ->where('company_id', $companyId)
            ->where('policy_year_id', $policyYearId)
            ->where('settings->meta->system_key', 'annual_default')
            ->first();

        // 2) fallback find by name (older records)
        if (! $row) {
            $row = LeavePolicy::query()
                ->where('company_id', $companyId)
                ->where('policy_year_id', $policyYearId)
                ->where('name', $this->annualDefaultName)
                ->first();
        }

        $payload = [
            'company_id' => $companyId,
            'policy_year_id' => $policyYearId,

            'name' => $this->annualDefaultName,
            'leave_type' => 'annual',
            'days_per_year' => 30,

            'gender' => 'all',
            'is_active' => true,
            'show_in_app' => true,
            'requires_attachment' => false,

            'description' => null,
            'settings' => $this->defaultAnnualSettings(),
        ];

        if ($row) {
            // ✅ لا نمسح إعدادات المستخدم: نضمن فقط meta key + lock_name + name
            $existing = is_array($row->settings ?? null) ? $row->settings : [];
            $defaults = $this->defaultAnnualSettings();

            $merged = array_replace_recursive($defaults, $existing);
            data_set($merged, 'attachments.max_mb', 2);

            // ✅ تطبيع القيم لو كانت قديمة/غير مسموحة
            $ded = (string) data_get($merged, 'deduction_policy', 'balance_only');
            if (!in_array($ded, ['balance_only', 'salary_after_balance'], true)) {
                data_set($merged, 'deduction_policy', 'balance_only');
            }

            $dur = (string) data_get($merged, 'duration_unit', 'full_day');
            if (!in_array($dur, ['full_day', 'half_day'], true)) {
                data_set($merged, 'duration_unit', 'full_day');
            }

            data_set($merged, 'meta.system_key', 'annual_default');
            data_set($merged, 'meta.lock_name', true);


            $row->update([
                'name' => $payload['name'],
                'leave_type' => $payload['leave_type'],
                'settings' => $merged,
            ]);
            return;
        }

        LeavePolicy::create($payload);
    }

    public function updatedAllowCarryover($value = null): void
    {
        if (! $this->allow_carryover) {
            $this->carryover_days = '0';
            $this->carryover_expire_months = '0';
            return;
        }

        // إذا رجّع التشغيل وكان الرقم صفر، نعطيه قيمة افتراضية
        if (!is_numeric($this->carryover_days) || (float) $this->carryover_days <= 0) {
            $this->carryover_days = '15';
        }
    }
// =======================
// Permission Settings Logic
// =======================
protected function permissionDefaults(): array
{
    return [
        'approval_required' => true,
        'monthly_limit_minutes' => 0,
        'max_request_minutes' => 0,
        'deduction_policy' => 'not_allowed_after_limit',
        'show_in_app' => true,
        'requires_attachment' => false,
        'attachment_types' => ['pdf', 'jpg', 'png'],
        'attachment_max_mb' => 2,
        'settings' => [],
    ];
}

protected function ensurePermissionPolicy(int $companyId, int $policyYearId): void
{
    PermissionPolicy::query()->firstOrCreate(
        [
            'company_id' => $companyId,
            'policy_year_id' => $policyYearId,
        ],
        array_merge(
            ['company_id' => $companyId, 'policy_year_id' => $policyYearId],
            $this->permissionDefaults()
        )
    );
}

protected function loadPermissionSettings(int $companyId, int $policyYearId): void
{
    $row = PermissionPolicy::query()
        ->where('company_id', $companyId)
        ->where('policy_year_id', $policyYearId)
        ->first();

    if (! $row) {
        $d = $this->permissionDefaults();
        $this->perm_approval_required = (bool) $d['approval_required'];
        $this->perm_monthly_limit_hours = '0';
        $this->perm_max_request_hours = '0';
        $this->perm_deduction_policy = (string) $d['deduction_policy'];
        $this->perm_show_in_app = (bool) $d['show_in_app'];
        $this->perm_requires_attachment = (bool) $d['requires_attachment'];
        $this->perm_attachment_types = (array) $d['attachment_types'];
        $this->perm_attachment_max_mb = '2';
        return;
    }

    $this->perm_approval_required = (bool) $row->approval_required;
    $this->perm_monthly_limit_hours = (string) rtrim(rtrim(number_format(($row->monthly_limit_minutes ?? 0) / 60, 2, '.', ''), '0'), '.');
    $this->perm_max_request_hours = (string) rtrim(rtrim(number_format(($row->max_request_minutes ?? 0) / 60, 2, '.', ''), '0'), '.');

    $this->perm_deduction_policy = (string) ($row->deduction_policy ?? 'not_allowed_after_limit');
    $this->perm_show_in_app = (bool) $row->show_in_app;

    $this->perm_requires_attachment = (bool) $row->requires_attachment;
    $this->perm_attachment_types = is_array($row->attachment_types) ? $row->attachment_types : ['pdf', 'jpg', 'png'];
    $this->perm_attachment_max_mb = '2';
}

protected function minutesFromHours($hours): int
{
    if (!is_numeric($hours)) return 0;
    $h = (float) $hours;
    if ($h <= 0) return 0;
    return (int) round($h * 60);
}

public function savePermissionSettings(): void
{
    abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

    $companyId = $this->resolveCompanyId();
    if ($companyId <= 0) {
        session()->flash('error', tr('Company context not found'));
        return;
    }

    $yearId = (int) ($this->selectedYearId ?? 0);
    if ($yearId <= 0) {
        session()->flash('error', tr('Please select a year first'));
        return;
    }

    $data = $this->validate([
        'perm_approval_required' => ['boolean'],
        'perm_monthly_limit_hours' => ['required', 'numeric', 'min:0', 'max:744'],
        'perm_max_request_hours' => ['required', 'numeric', 'min:0', 'max:24'],
        'perm_deduction_policy' => ['required', 'in:not_allowed_after_limit,salary_after_limit,allow_without_deduction'],
        'perm_show_in_app' => ['boolean'],

        'perm_requires_attachment' => ['boolean'],
        'perm_attachment_types' => ['array'],
        'perm_attachment_types.*' => ['in:pdf,jpg,png'],
    ]);

    $types = !empty($data['perm_requires_attachment'])
        ? array_values($data['perm_attachment_types'] ?? [])
        : [];

    PermissionPolicy::query()->updateOrCreate(
        ['company_id' => $companyId, 'policy_year_id' => $yearId],
        [
            'approval_required' => (bool) ($data['perm_approval_required'] ?? true),
            'monthly_limit_minutes' => $this->minutesFromHours($data['perm_monthly_limit_hours']),
            'max_request_minutes' => $this->minutesFromHours($data['perm_max_request_hours']),
            'deduction_policy' => (string) $data['perm_deduction_policy'],
            'show_in_app' => (bool) ($data['perm_show_in_app'] ?? true),

            'requires_attachment' => (bool) ($data['perm_requires_attachment'] ?? false),
            'attachment_types' => $types,
            'attachment_max_mb' => 2,
        ]
    );

    session()->flash('success', tr('Saved successfully'));
}

}
