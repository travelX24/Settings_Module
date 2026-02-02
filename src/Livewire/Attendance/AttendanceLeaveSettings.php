<?php

namespace Athka\SystemSettings\Livewire\Attendance;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

use Athka\SystemSettings\Models\LeavePolicyYear;
use Athka\SystemSettings\Models\LeavePolicy;

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

    // Create/Edit fields
    public int $editingId = 0;

    public string $name = '';
    public string $leave_type = 'annual';
    public string $days_per_year = '30';
    public string $gender = 'all';
    public bool $is_active = true;
    public bool $show_in_app = true;
    public bool $requires_attachment = false;
    public string $description = '';

    // Advanced settings (stored into settings JSON)
    public string $accrual_method = 'annual_grant'; // annual_grant|monthly|by_work_days
    public string $monthly_accrual_rate = '2.5';
    public string $workday_accrual_rate = '0';

    public string $min_accrual = '0.5';
    public string $max_balance = '45';
    public string $carryover_days = '15';
    public string $carryover_expire_months = '3';

    public string $weekend_policy = 'exclude'; // exclude|include|employee_choice
    public string $deduction_policy = 'balance_only'; // balance_only|salary_after_balance|not_allowed_after_balance
    public string $duration_unit = 'full_day'; // full_day|half_day|hours

    public string $notice_min_days = '3';
    public string $notice_max_advance_days = '90';
    public bool $allow_retroactive = false;

    public bool $note_required = false;
    public string $note_text = '';
    public bool $note_ack_required = false;

    public array $attachment_types = ['pdf', 'jpg', 'png'];
    public string $attachment_max_mb = '5';

    public bool $blackout_enabled = false;
    public string $blackout_from = '12-01'; // MM-DD
    public string $blackout_to = '12-31';   // MM-DD
    public bool $blackout_exception_requires_approval = false;

    public bool $min_service_enabled = false;
    public string $min_service_months = '3';

    public bool $requires_presence_before_apply = false;

    public bool $max_consecutive_enabled = false;
    public string $max_consecutive_days = '30';

    public bool $max_total_enabled = false;
    public string $max_total_days = '45';

    // Year modal
    public string $newYear = '';
    public ?int $copyFromYearId = null;
    public string $newYearStartsOn = '';
    public string $newYearEndsOn = '';
    public bool $newYearActive = true;

    // Compare modal
    public ?int $compareYearAId = null;
    public ?int $compareYearBId = null;

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
        if (! LeavePolicyYear::query()->where('company_id', $companyId)->where('is_active', true)->exists()) {
            LeavePolicyYear::query()
                ->where('company_id', $companyId)
                ->where('id', $year->id)
                ->update(['is_active' => true]);
        }

        $this->selectedYearId = (int) $year->id;
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterGender(): void { $this->resetPage(); }
    public function updatedFilterShowInApp(): void { $this->resetPage(); }
    public function updatedFilterAttachments(): void { $this->resetPage(); }
    public function updatedFilterYearId(): void
    {
        // If user chooses a year filter, we consider it a multi-year view scenario
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

        // Year filter first (explicit)
        if ($this->filterYearId !== 'all' && is_numeric($this->filterYearId)) {
            $q->where('policy_year_id', (int) $this->filterYearId);
        } else {
            // Default behavior: selected year unless showAllYears = true
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
            // reset explicit year filter when going back to single-year view
            $this->filterYearId = 'all';
        }

        $this->resetPage();
    }

    public function selectYear(int $id): void
    {
        $this->selectedYearId = $id;
        $this->showAllYears = false;
        $this->filterYearId = 'all';
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

    // ---------------------------
    // Create/Edit Leave Policy
    // ---------------------------
    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $this->resetValidation();
        $this->editingId = 0;

        $this->name = '';
        $this->leave_type = 'annual';
        $this->days_per_year = '30';
        $this->gender = 'all';
        $this->is_active = true;
        $this->show_in_app = true;
        $this->requires_attachment = false;
        $this->description = '';

        $this->resetAdvancedDefaults();

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

        $data = $this->validate($this->policyRules());

        $settings = $this->buildSettingsFromValidated($data);

        LeavePolicy::create([
            'company_id' => $companyId,
            'policy_year_id' => (int) $this->selectedYearId,

            'name' => $data['name'],
            'leave_type' => $data['leave_type'],
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

        $this->name = (string) $row->name;
        $this->leave_type = (string) $row->leave_type;
        $this->days_per_year = (string) $row->days_per_year;

        $this->gender = (string) $row->gender;
        $this->is_active = (bool) $row->is_active;
        $this->show_in_app = (bool) $row->show_in_app;
        $this->requires_attachment = (bool) $row->requires_attachment;

        $this->description = (string) ($row->description ?? '');

        $this->hydrateAdvancedFromRow($row);

        $this->resetValidation();
        $this->editOpen = true;
    }

    public function closeEdit(): void
    {
        $this->editOpen = false;
        $this->editingId = 0;
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

        $data = $this->validate($this->policyRules());

        $settings = $this->buildSettingsFromValidated($data);

        $row->update([
            'name' => $data['name'],
            'leave_type' => $data['leave_type'],
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
        $this->newYear = '';
        $this->copyFromYearId = null;

        $defaultYear = (int) now()->year;
        $this->newYearStartsOn = Carbon::create($defaultYear, 1, 1)->toDateString();
        $this->newYearEndsOn = Carbon::create($defaultYear, 12, 31)->toDateString();
        $this->newYearActive = true;

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
            'newYearStartsOn' => ['nullable', 'date'],
            'newYearEndsOn' => ['nullable', 'date', 'after_or_equal:newYearStartsOn'],
            'newYearActive' => ['boolean'],
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

        DB::transaction(function () use ($companyId, $yearInt, $data, &$year) {
            if (!empty($data['newYearActive'])) {
                LeavePolicyYear::query()
                    ->where('company_id', $companyId)
                    ->update(['is_active' => false]);
            }

            $year = LeavePolicyYear::create([
                'company_id' => $companyId,
                'year' => $yearInt,
                'starts_on' => $data['newYearStartsOn'] ?: Carbon::create($yearInt, 1, 1)->toDateString(),
                'ends_on' => $data['newYearEndsOn'] ?: Carbon::create($yearInt, 12, 31)->toDateString(),
                'is_active' => !empty($data['newYearActive']),
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

        $years = $this->years->values();

        $this->compareYearAId = $this->selectedYearId ?: ($years->first()?->id ?? null);

        // pick another year (next in list)
        $alt = null;
        if ($this->compareYearAId) {
            $idx = $years->search(fn ($y) => (int) $y->id === (int) $this->compareYearAId);
            $alt = $idx !== false ? ($years->get($idx + 1) ?: $years->get($idx - 1)) : null;
        }
        $this->compareYearBId = $alt ? (int) $alt->id : ($years->get(1)?->id ?? null);

        $this->compareOpen = true;
    }

    public function closeCompare(): void
    {
        $this->compareOpen = false;
        $this->compareYearAId = null;
        $this->compareYearBId = null;
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

        $payload = [
            'meta' => [
                'exported_at' => now()->toIso8601String(),
                'company_id' => $companyId,
                'policy_year_id' => $yearId,
                'policy_year' => $year?->year,
            ],
            'policies' => $policies->map(function (LeavePolicy $p) {
                return [
                    'name' => $p->name,
                    'leave_type' => $p->leave_type,
                    'days_per_year' => (string) $p->days_per_year,
                    'gender' => $p->gender,
                    'is_active' => (bool) $p->is_active,
                    'show_in_app' => (bool) $p->show_in_app,
                    'requires_attachment' => (bool) $p->requires_attachment,
                    'description' => $p->description,
                    'settings' => $p->settings ?? [],
                ];
            })->values()->all(),
        ];

        $filename = 'leave-policies-' . ($year?->year ?? $yearId) . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
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
            'leave_type' => ['required', 'string', 'max:50'],
            'days_per_year' => ['required', 'numeric', 'min:0', 'max:366'],
            'gender' => ['required', 'in:all,male,female'],
            'is_active' => ['boolean'],
            'show_in_app' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],

            'accrual_method' => ['required', 'in:annual_grant,monthly,by_work_days'],
            'monthly_accrual_rate' => ['nullable', 'numeric', 'min:0', 'max:366', 'required_if:accrual_method,monthly'],
            'workday_accrual_rate' => ['nullable', 'numeric', 'min:0', 'max:10', 'required_if:accrual_method,by_work_days'],

            'min_accrual' => ['required', 'numeric', 'min:0', 'max:366'],
            'max_balance' => ['required', 'numeric', 'min:0', 'max:999'],
            'carryover_days' => ['required', 'numeric', 'min:0', 'max:999'],
            'carryover_expire_months' => ['required', 'integer', 'min:0', 'max:60'],

            'weekend_policy' => ['required', 'in:exclude,include,employee_choice'],
            'deduction_policy' => ['required', 'in:balance_only,salary_after_balance,not_allowed_after_balance'],
            'duration_unit' => ['required', 'in:full_day,half_day,hours'],

            'notice_min_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'notice_max_advance_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'allow_retroactive' => ['boolean'],

            'note_required' => ['boolean'],
            'note_text' => ['nullable', 'string', 'max:500'],
            'note_ack_required' => ['boolean'],

            'attachment_types' => ['array'],
            'attachment_types.*' => ['in:pdf,jpg,png'],
            'attachment_max_mb' => ['required', 'numeric', 'min:0.5', 'max:100'],

            'blackout_enabled' => ['boolean'],
            'blackout_from' => ['nullable', 'regex:/^\d{2}-\d{2}$/'],
            'blackout_to' => ['nullable', 'regex:/^\d{2}-\d{2}$/'],
            'blackout_exception_requires_approval' => ['boolean'],

            'min_service_enabled' => ['boolean'],
            'min_service_months' => ['nullable', 'integer', 'min:0', 'max:240', 'required_if:min_service_enabled,1,true'],

            'requires_presence_before_apply' => ['boolean'],

            'max_consecutive_enabled' => ['boolean'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:999', 'required_if:max_consecutive_enabled,1,true'],

            'max_total_enabled' => ['boolean'],
            'max_total_days' => ['nullable', 'integer', 'min:1', 'max:999', 'required_if:max_total_enabled,1,true'],
        ];
    }

    protected function buildSettingsFromValidated(array $data): array
    {
        return [
            'accrual' => [
                'method' => $data['accrual_method'],
                'monthly_rate' => $data['accrual_method'] === 'monthly' ? (float) ($data['monthly_accrual_rate'] ?? 0) : null,
                'workday_rate' => $data['accrual_method'] === 'by_work_days' ? (float) ($data['workday_accrual_rate'] ?? 0) : null,
                'min_unit' => (float) $data['min_accrual'],
                'max_balance' => (float) $data['max_balance'],
                'carryover_days' => (float) $data['carryover_days'],
                'carryover_expire_months' => (int) $data['carryover_expire_months'],
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
                'max_mb' => (float) $data['attachment_max_mb'],
            ],

            'constraints' => [
                'blackout' => [
                    'enabled' => (bool) $data['blackout_enabled'],
                    'from' => !empty($data['blackout_enabled']) ? ($data['blackout_from'] ?? null) : null,
                    'to' => !empty($data['blackout_enabled']) ? ($data['blackout_to'] ?? null) : null,
                    'exception_requires_approval' => (bool) $data['blackout_exception_requires_approval'],
                ],
                'min_service' => [
                    'enabled' => (bool) $data['min_service_enabled'],
                    'months' => !empty($data['min_service_enabled']) ? (int) ($data['min_service_months'] ?? 0) : null,
                ],
                'requires_presence_before_apply' => (bool) $data['requires_presence_before_apply'],
                'max_consecutive' => [
                    'enabled' => (bool) $data['max_consecutive_enabled'],
                    'days' => !empty($data['max_consecutive_enabled']) ? (int) ($data['max_consecutive_days'] ?? 0) : null,
                ],
                'max_total_per_year' => [
                    'enabled' => (bool) $data['max_total_enabled'],
                    'days' => !empty($data['max_total_enabled']) ? (int) ($data['max_total_days'] ?? 0) : null,
                ],
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
        $this->carryover_days = '15';
        $this->carryover_expire_months = '3';

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
        $this->attachment_max_mb = '5';

        $this->blackout_enabled = false;
        $this->blackout_from = '12-01';
        $this->blackout_to = '12-31';
        $this->blackout_exception_requires_approval = false;

        $this->min_service_enabled = false;
        $this->min_service_months = '3';

        $this->requires_presence_before_apply = false;

        $this->max_consecutive_enabled = false;
        $this->max_consecutive_days = '30';

        $this->max_total_enabled = false;
        $this->max_total_days = '45';
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
        $this->carryover_expire_months = (string) data_get($s, 'accrual.carryover_expire_months', '3');

        $this->weekend_policy = (string) data_get($s, 'weekend_policy', 'exclude');
        $this->deduction_policy = (string) data_get($s, 'deduction_policy', 'balance_only');
        $this->duration_unit = (string) data_get($s, 'duration_unit', 'full_day');

        $this->notice_min_days = (string) data_get($s, 'notice.min_days', '3');
        $this->notice_max_advance_days = (string) data_get($s, 'notice.max_advance_days', '90');
        $this->allow_retroactive = (bool) data_get($s, 'notice.allow_retroactive', false);

        $this->note_required = (bool) data_get($s, 'note.required', false);
        $this->note_text = (string) data_get($s, 'note.text', '');
        $this->note_ack_required = (bool) data_get($s, 'note.ack_required', false);

        $types = data_get($s, 'attachments.types', ['pdf', 'jpg', 'png']);
        $this->attachment_types = is_array($types) ? $types : ['pdf', 'jpg', 'png'];
        $this->attachment_max_mb = (string) data_get($s, 'attachments.max_mb', '5');

        $this->blackout_enabled = (bool) data_get($s, 'constraints.blackout.enabled', false);
        $this->blackout_from = (string) data_get($s, 'constraints.blackout.from', '12-01');
        $this->blackout_to = (string) data_get($s, 'constraints.blackout.to', '12-31');
        $this->blackout_exception_requires_approval = (bool) data_get($s, 'constraints.blackout.exception_requires_approval', false);

        $this->min_service_enabled = (bool) data_get($s, 'constraints.min_service.enabled', false);
        $this->min_service_months = (string) data_get($s, 'constraints.min_service.months', '3');

        $this->requires_presence_before_apply = (bool) data_get($s, 'constraints.requires_presence_before_apply', false);

        $this->max_consecutive_enabled = (bool) data_get($s, 'constraints.max_consecutive.enabled', false);
        $this->max_consecutive_days = (string) data_get($s, 'constraints.max_consecutive.days', '30');

        $this->max_total_enabled = (bool) data_get($s, 'constraints.max_total_per_year.enabled', false);
        $this->max_total_days = (string) data_get($s, 'constraints.max_total_per_year.days', '45');
    }

    public function render()
    {
        return view('systemsettings::livewire.attendance.leave-settings', [
            'rows' => $this->rows,
            'years' => $this->years,
            'compareRows' => $this->compareRows,
        ])->layout('layouts.company-admin');
    }
}
