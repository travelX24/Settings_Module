<?php

namespace Athka\SystemSettings\Livewire\Attendance\ExceptionalDays;

use Athka\SystemSettings\Models\AttendanceExceptionalDay;
use Athka\SystemSettings\Services\ExceptionalDayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ExceptionalDaysIndex extends Component
{
    use WithPagination;

    public int $year;
    public ?int $month = null;

    public string $status = 'all';
    public string $search = '';

    public string $deductionType = 'all'; 
    public ?float $minMultiplier = null;  
    public ?float $maxMultiplier = null; 
    public ?int $departmentId = null;
    public ?int $branchId = null;
    public ?string $contractType = null;

    public bool $showModal = false;
    public ?int $editingId = null;

    public array $selected = [];
    public bool $selectPage = false;

    public bool $showCopyModal = false;
    public int $copyFromYear;
    public int $copyToYear;
    public ?int $copyFromCount = null;

    public array $copySelected = [];
    public bool $copySelectAll = false;

    public int $perPage = 10;

    public array $form = [
        'name' => '',
        'description' => null,

        'period_type' => 'single',
        'start_date' => null,
        'end_date' => null,

        'apply_on' => 'absence', 

        'deduction_mode' => 'with', 

        'deduction_percent' => 100.0, 

        'absence_multiplier' => 1.00,
        'late_multiplier' => 1.00,

        'grace_hours' => 0,

        'scope_type' => 'all', 
        'include' => [
            'departments' => [],
            'sections' => [],
            'branches' => [],
            'contract_types' => [],
            'employees' => [],
        ],

        'notify_policy' => 'none',
        'notify_message' => null,

        'retroactive' => 'from_created',

        'is_active' => true,
    ];

    protected ExceptionalDayService $exceptionalDayService;

    public function boot(ExceptionalDayService $exceptionalDayService): void
    {
        $this->exceptionalDayService = $exceptionalDayService;
    }

    public function mount(): void
    {
        $this->authorize('settings.attendance.view');
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;

        $this->copyFromYear = (int) now()->subYear()->year;
        $this->copyToYear   = (int) $this->year;
        $this->copyFromCount = null;
    }

    public function updatingYear() { $this->resetPage(); }
    public function updatingMonth() { $this->resetPage(); }
    public function updatingStatus() { $this->resetPage(); }
    public function updatingSearch() { $this->resetPage(); }
    public function updatingDeductionType() { $this->resetPage(); }
    public function updatingMinMultiplier() { $this->resetPage(); }
    public function updatingMaxMultiplier() { $this->resetPage(); }
    public function updatingDepartmentId() { $this->resetPage(); }
    public function updatingBranchId() { $this->resetPage(); }
    public function updatingContractType() { $this->resetPage(); }

    public function updatedFormPeriodType($value): void
    {
        if (($value ?? 'single') === 'single') {
            $this->form['end_date'] = $this->form['start_date'];
        } else {
            if (empty($this->form['end_date'])) {
                $this->form['end_date'] = $this->form['start_date'];
            }
        }
    }

    public function updatedFormStartDate($value): void
    {
        if (($this->form['period_type'] ?? 'single') === 'single') {
            $this->form['end_date'] = $value;
        }
    }

    public function updatedFormApplyOn($value): void
    {
        $v = (string) $value;

        if (!in_array($v, ['late', 'absence'], true)) {
            $v = 'absence';
            $this->form['apply_on'] = 'absence';
        }

        $mode = (string) ($this->form['deduction_mode'] ?? 'with');

        if ($mode === 'without') {
            $this->form['grace_hours'] = 0;
            $this->form['deduction_percent'] = 0.0;
            $this->form['retroactive'] = 'from_created';
            return;
        }

        if ($v === 'late') {
            $this->form['grace_hours'] = (int) ($this->form['grace_hours'] ?? 0);
            if (!isset($this->form['deduction_percent']) || $this->form['deduction_percent'] === null) {
                $this->form['deduction_percent'] = 100.0;
            }
        } else { 
            $this->form['grace_hours'] = 0;
            if (!isset($this->form['deduction_percent']) || $this->form['deduction_percent'] === null) {
                $this->form['deduction_percent'] = 100.0;
            }
        }
    }

    public function updatedFormDeductionMode($value): void
    {
        $mode = (string) $value;

        if ($mode === 'without') {
            $this->form['deduction_percent'] = 0.0;
            $this->form['grace_hours'] = 0;
            $this->form['retroactive'] = 'from_created';
            return;
        }

        if (!isset($this->form['deduction_percent']) || $this->form['deduction_percent'] === null) {
            $this->form['deduction_percent'] = 100.0;
        }

        $this->updatedFormApplyOn($this->form['apply_on'] ?? 'absence');
    }

    public function updatedFormScopeType($value): void
    {
        $type = (string) $value;

        if ($type === 'all') {
            $this->form['include'] = ['departments'=>[], 'sections'=>[], 'employees'=>[]];
            return;
        }

        if ($type === 'departments' || $type === 'branches' || $type === 'contract_types') {
            if ($type === 'departments') {
                $this->form['include']['branches'] = [];
                $this->form['include']['contract_types'] = [];
                $this->form['include']['employees'] = [];
            } elseif ($type === 'branches') {
                $this->form['include']['departments'] = [];
                $this->form['include']['sections'] = [];
                $this->form['include']['contract_types'] = [];
                $this->form['include']['employees'] = [];
            } elseif ($type === 'contract_types') {
                $this->form['include']['departments'] = [];
                $this->form['include']['sections'] = [];
                $this->form['include']['branches'] = [];
                $this->form['include']['employees'] = [];
            }
            return;
        }

        if ($type === 'employees') {
            $this->form['include']['departments'] = [];
            $this->form['include']['sections'] = [];
            $this->form['include']['branches'] = [];
            $this->form['include']['contract_types'] = [];
            return;
        }
    }

    protected function rules(): array
    {
        $mode = (string) ($this->form['deduction_mode'] ?? 'with');
        $hasDeduction = ($mode === 'with');

        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],

            'form.period_type' => ['required', Rule::in(['single', 'range'])],
            'form.start_date' => ['required', 'date'],

            'form.end_date' => [
                Rule::requiredIf(fn () => ($this->form['period_type'] ?? 'single') === 'range'),
                'nullable',
                'date',
                'after_or_equal:form.start_date',
            ],

            'form.apply_on' => ['required', Rule::in(['absence', 'late'])],

            'form.deduction_mode' => ['required', Rule::in(['with', 'without'])],

            'form.deduction_percent' => [
                Rule::requiredIf(fn () => $hasDeduction),
                'nullable',
                'numeric',
                'min:0',
                'max:1000',
            ],

            'form.grace_hours' => [
                Rule::requiredIf(fn () => $hasDeduction && (($this->form['apply_on'] ?? 'absence') === 'late')),
                'nullable',
                'integer',
                'min:0',
                'max:24',
            ],

            'form.scope_type' => ['required', Rule::in(['all', 'departments', 'employees', 'branches', 'contract_types'])],
            'form.include' => ['nullable', 'array'],

            'form.notify_policy' => ['required', Rule::in(['none', 'after_deduction'])],
            'form.notify_message' => ['nullable', 'string', 'max:2000'],

            'form.retroactive' => ['nullable', Rule::in(['from_created', 'full_period'])],

            'form.is_active' => ['boolean'],
        ];
    }
    
    private function companyId(): int
    {
        if (app()->bound('currentCompany') && app('currentCompany')) {
            return (int) app('currentCompany')->id;
        }

        return (int) (auth()->user()->saas_company_id ?? auth()->user()->company_id ?? 0);
    }

    private function getFilters(): array
    {
        return [
            'year'          => $this->year,
            'month'         => $this->month,
            'search'        => $this->search,
            'status'        => $this->status,
            'deductionType' => $this->deductionType,
            'minMultiplier' => $this->minMultiplier,
            'maxMultiplier' => $this->maxMultiplier,
            'departmentId'  => $this->departmentId,
            'branchId'      => $this->branchId,
            'contractType'  => $this->contractType,
        ];
    }

    private function validateScopeSelections(): bool
    {
        $type = (string) ($this->form['scope_type'] ?? 'all');
        $inc  = $this->form['include'] ?? ['departments'=>[], 'sections'=>[], 'employees'=>[]];

        if ($type === 'all') {
            return true;
        }

        if ($type === 'departments') {
            $hasAny = !empty($inc['departments']) || !empty($inc['sections']);
            if (!$hasAny) {
                $this->addError('form.include.departments', tr('Please select at least one department or sub department.'));
                return false;
            }
            return true;
        }

        if ($type === 'branches') {
            if (empty($inc['branches'])) {
                $this->addError('form.include.branches', tr('Please select at least one branch.'));
                return false;
            }
            return true;
        }

        if ($type === 'contract_types') {
            if (empty($inc['contract_types'])) {
                $this->addError('form.include.contract_types', tr('Please select at least one contract type.'));
                return false;
            }
            return true;
        }

        if ($type === 'employees') {
            if (empty($inc['employees'])) {
                $this->addError('form.include.employees', tr('Please select at least one employee.'));
                return false;
            }
            return true;
        }

        return true;
    }

    private function normalizeApplyAndRates(): void
    {
        $mode = (string) ($this->form['deduction_mode'] ?? 'with');
        $apply = (string) ($this->form['apply_on'] ?? 'absence');

        if ($mode === 'without') {
            $this->form['deduction_percent'] = 0.0;
            $this->form['grace_hours'] = 0;
            $this->form['retroactive'] = 'from_created';

            if ($apply === 'late') {
                $this->form['late_multiplier'] = 0.0;
                $this->form['absence_multiplier'] = 1.00;
            } else {
                $this->form['absence_multiplier'] = 0.0;
                $this->form['late_multiplier'] = 1.00;
            }

            return;
        }

        $percent = (float) ($this->form['deduction_percent'] ?? 0.0);
        $factor = $percent / 100.0;

        if ($factor < 0) $factor = 0;
        if ($factor > 10) $factor = 10;

        if ($apply === 'absence') {
            $this->form['absence_multiplier'] = $factor;
            $this->form['late_multiplier'] = 1.00;
            $this->form['grace_hours'] = 0;
        } else {
            $this->form['late_multiplier'] = $factor;
            $this->form['absence_multiplier'] = 1.00;
            $this->form['grace_hours'] = (int) ($this->form['grace_hours'] ?? 0);
        }
    }

    private function payloadForSave(int $companyId): array
    {
        $apply = (string) ($this->form['apply_on'] ?? 'absence');
        if (!in_array($apply, ['absence', 'late'], true)) {
            $apply = 'absence';
        }

        return [
            'company_id' => $companyId,
            'name' => $this->form['name'] ?? '',
            'description' => $this->form['description'] ?? null,

            'period_type' => $this->form['period_type'] ?? 'single',
            'start_date' => $this->form['start_date'] ?? null,
            'end_date' => $this->form['end_date'] ?? null,

            'apply_on' => $apply,

            'absence_multiplier' => (float) ($this->form['absence_multiplier'] ?? 1.0),
            'late_multiplier' => (float) ($this->form['late_multiplier'] ?? 1.0),
            'grace_hours' => (int) ($this->form['grace_hours'] ?? 0),

            'scope_type' => $this->form['scope_type'] ?? 'all',
            'include' => $this->form['include'] ?? ['departments'=>[], 'sections'=>[], 'branches'=>[], 'contract_types'=>[], 'employees'=>[]],

            'notify_policy' => $this->form['notify_policy'] ?? 'none',
            'notify_message' => $this->form['notify_message'] ?? null,
            'retroactive' => $this->form['retroactive'] ?? 'from_created',

            'is_active' => (bool) ($this->form['is_active'] ?? true),

            'created_by' => auth()->id(),
        ];
    }

    public function openCreate(): void
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();
        $this->editingId = null;

        $this->form = [
            'name' => '',
            'description' => null,

            'period_type' => 'single',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),

            'apply_on' => 'absence',
            'deduction_mode' => 'with',
            'deduction_percent' => 100.0,

            'absence_multiplier' => 1.00,
            'late_multiplier' => 1.00,
            'grace_hours' => 0,

            'scope_type' => 'all',
            'include' => ['departments'=>[], 'sections'=>[], 'branches'=>[], 'contract_types'=>[], 'employees'=>[]],

            'notify_policy' => 'none',
            'notify_message' => null,

            'retroactive' => 'from_created',

            'is_active' => true,
        ];

        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();
        $this->editingId = $id;

        $row = AttendanceExceptionalDay::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $applyStored = (string) ($row->apply_on ?? 'absence');
        if (!in_array($applyStored, ['absence', 'late', 'none'], true)) {
            $applyStored = 'absence';
        }

        $deductionMode = 'with';
        if ($applyStored === 'none') {
            $deductionMode = 'without';
        } elseif ($applyStored === 'absence' && ((float) $row->absence_multiplier) <= 0) {
            $deductionMode = 'without';
        } elseif ($applyStored === 'late' && ((float) $row->late_multiplier) <= 0) {
            $deductionMode = 'without';
        }

        $applyForUi = ($applyStored === 'none') ? 'absence' : $applyStored;

        $percent = 0.0;
        if ($applyStored === 'absence') $percent = (float) $row->absence_multiplier * 100.0;
        if ($applyStored === 'late') $percent = (float) $row->late_multiplier * 100.0;

        if ($deductionMode === 'without') {
            $percent = 0.0;
        }

        $include = $row->include ?? ['departments'=>[], 'sections'=>[], 'employees'=>[]];
        $scopeType = (string) ($row->scope_type ?? 'all');

        if (!in_array($scopeType, ['all', 'departments', 'employees', 'branches', 'contract_types'], true)) {
            if (!empty($include['employees'])) {
                $scopeType = 'employees';
            } elseif (!empty($include['branches'])) {
                $scopeType = 'branches';
            } elseif (!empty($include['contract_types'])) {
                $scopeType = 'contract_types';
            } elseif (!empty($include['departments']) || !empty($include['sections'])) {
                $scopeType = 'departments';
            } else {
                $scopeType = 'all';
            }
        }

        $notifyPolicy = (string) ($row->notify_policy ?? 'none');
        if (in_array($notifyPolicy, ['days_3', 'week_1', 'weeks_2'], true)) {
            $notifyPolicy = 'after_deduction';
        }
        if (!in_array($notifyPolicy, ['none', 'after_deduction'], true)) {
            $notifyPolicy = 'none';
        }

        $this->form = [
            'name' => $row->name,
            'description' => $row->description,

            'period_type' => $row->period_type,
            'start_date' => $row->start_date?->toDateString(),
            'end_date' => $row->end_date?->toDateString(),

            'apply_on' => $applyForUi,
            'deduction_mode' => $deductionMode,
            'deduction_percent' => round($percent, 2),

            'absence_multiplier' => (float) $row->absence_multiplier,
            'late_multiplier' => (float) $row->late_multiplier,
            'grace_hours' => (int) $row->grace_hours,

            'scope_type' => $scopeType,
            'include' => $include,

            'notify_policy' => $notifyPolicy,
            'notify_message' => $row->notify_message,

            'retroactive' => $row->retroactive ?? 'from_created',

            'is_active' => (bool) $row->is_active,
        ];

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('settings.attendance.manage');
        $this->validate();

        if (($this->form['period_type'] ?? 'single') === 'single') {
            $this->form['end_date'] = $this->form['start_date'];
        } else {
            if (empty($this->form['end_date'])) {
                $this->form['end_date'] = $this->form['start_date'];
            }
        }

        if (!$this->validateScopeSelections()) {
            return;
        }

        $this->normalizeApplyAndRates();

        $start = $this->form['start_date'];
        $end   = $this->form['end_date'];

        $companyId = $this->companyId();
        
        if ($this->exceptionalDayService->checkOverlap($companyId, $start, $end, $this->editingId)) {
            $this->addError('form.start_date', tr('Date range overlaps with another exceptional day.'));
            return;
        }

        $type = (string) ($this->form['scope_type'] ?? 'all');

        if ($type === 'all') {
            $this->form['include'] = ['departments'=>[], 'sections'=>[], 'branches'=>[], 'contract_types'=>[], 'employees'=>[]];
        } elseif ($type === 'departments') {
            $this->form['include']['branches'] = [];
            $this->form['include']['contract_types'] = [];
            $this->form['include']['employees'] = [];
        } elseif ($type === 'branches') {
            $this->form['include']['departments'] = [];
            $this->form['include']['sections'] = [];
            $this->form['include']['contract_types'] = [];
            $this->form['include']['employees'] = [];
        } elseif ($type === 'contract_types') {
            $this->form['include']['departments'] = [];
            $this->form['include']['sections'] = [];
            $this->form['include']['branches'] = [];
            $this->form['include']['employees'] = [];
        } elseif ($type === 'employees') {
            $this->form['include']['departments'] = [];
            $this->form['include']['sections'] = [];
            $this->form['include']['branches'] = [];
            $this->form['include']['contract_types'] = [];
        }

        AttendanceExceptionalDay::updateOrCreate(
            [
                'id' => $this->editingId,
                'company_id' => $companyId,
            ],
            $this->payloadForSave($companyId)
        );

        $this->showModal = false;
        $this->resetValidation();
        $this->resetPage();
        
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Operation successful')]);
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('settings.attendance.manage');
        $row = AttendanceExceptionalDay::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $row->update(['is_active' => ! $row->is_active]);
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Status updated')]);
    }

    public function deleteRow(int $id): void
    {
        $this->authorize('settings.attendance.manage');
        AttendanceExceptionalDay::query()
            ->where('company_id', $this->companyId())
            ->where('id', $id)
            ->delete();

        $this->resetPage();
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Deleted successfully')]);
    }

    public function updatedSelectPage($value): void
    {
        if ($value) {
            $companyId = $this->companyId();
            $page = (int) $this->getPage(); 

            $ids = $this->exceptionalDayService->getRowsQuery($companyId, $this->getFilters())
                ->forPage($page, $this->perPage)
                ->pluck('id')
                ->toArray();

            $this->selected = $ids;
        } else {
            $this->selected = [];
        }
    }


    public function deleteSelected(): void
    {
        $this->authorize('settings.attendance.manage');
        if (empty($this->selected)) return;

        AttendanceExceptionalDay::query()
            ->where('company_id', $this->companyId())
            ->whereIn('id', $this->selected)
            ->delete();

        $this->selected = [];
        $this->selectPage = false;
        $this->resetPage();
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Deleted successfully')]);
    }

    public function setSelectedActive(bool $active): void
    {
        $this->authorize('settings.attendance.manage');
        if (empty($this->selected)) return;

        AttendanceExceptionalDay::query()
            ->where('company_id', $this->companyId())
            ->whereIn('id', $this->selected)
            ->update(['is_active' => $active]);

        $this->selected = [];
        $this->selectPage = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Status updated')]);
    }

    public function updatedCopyFromYear($value): void
    {
        $from = (int) $value;
        $companyId = $this->companyId();

        $this->copyFromCount = AttendanceExceptionalDay::query()
            ->where('company_id', $companyId)
            ->whereYear('start_date', $from)
            ->count();

        $this->copySelected = [];
        $this->copySelectAll = false;
    }

    public function openCopyModal(): void
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();

        $this->copyToYear = (int) $this->year;

        $this->updatedCopyFromYear($this->copyFromYear);

        $this->showCopyModal = true;
    }

    public function updatedCopySelectAll($value): void
    {
        $this->copySelectAll = (bool) $value;

        if (!$this->showCopyModal) {
            $this->copySelected = [];
            return;
        }

        if ($this->copySelectAll) {
            $companyId = $this->companyId();

            $ids = AttendanceExceptionalDay::query()
                ->where('company_id', $companyId)
                ->whereYear('start_date', (int) $this->copyFromYear)
                ->orderBy('start_date')
                ->limit(200)
                ->pluck('id')
                ->toArray();

            $this->copySelected = $ids;
        } else {
            $this->copySelected = [];
        }
    }

    public function copyOneDay(int $id): void
    {
        $this->copySelected = [$id];
        $this->copySelectedDays();
    }

    public function copySelectedDays(): void
    {
        $this->authorize('settings.attendance.manage');
        $companyId = $this->companyId();

        $from = (int) $this->copyFromYear;
        $to   = (int) $this->year; 
        $this->copyToYear = $to;

        if ($from < 2000 || $to < 2000) return;

        if (empty($this->copySelected)) {
            $this->addError('copySelected', tr('Please select at least one day to copy.'));
            return;
        }

        $diffYears = $to - $from;
        
        $result = $this->exceptionalDayService->copyDays($companyId, $this->copySelected, $diffYears);

        $this->copySelected = [];
        $this->copySelectAll = false;
        $this->showCopyModal = false;
        $this->resetPage();

        if ($result['copied'] > 0 || $result['skipped'] > 0) {
            $msg = tr('Copied') . ": {$result['copied']} | " . tr('Skipped') . ": {$result['skipped']}";
            $this->dispatch('toast', ['type' => 'success', 'message' => $msg]);
        }
    }

    public function exportCsv()
    {
        $companyId = $this->companyId();
        $rows = $this->exceptionalDayService->getRowsQuery($companyId, $this->getFilters())->get();

        $filename = 'exceptional-days-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            echo "\xEF\xBB\xBF";

            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Name', 'Description', 'Period Type', 'Start Date', 'End Date',
                'Apply', 'Deduction %', 'Grace Hours', 'Scope Type',
                'Notify Policy', 'Notified At', 'Is Active', 'Created By', 'Created At',
            ]);

            foreach ($rows as $r) {
                $apply = (string) $r->apply_on;

                $percent = 0.0;
                if ($apply === 'absence') $percent = (float) $r->absence_multiplier * 100.0;
                if ($apply === 'late') $percent = (float) $r->late_multiplier * 100.0;

                fputcsv($out, [
                    $r->name,
                    $r->description,
                    $r->period_type,
                    optional($r->start_date)->toDateString(),
                    optional($r->end_date)->toDateString(),
                    $apply,
                    number_format($percent, 2, '.', ''),
                    (string) ((int) $r->grace_hours),
                    (string) ($r->scope_type ?? 'all'),
                    $r->notify_policy,
                    optional($r->notified_at)?->toDateTimeString(),
                    $r->is_active ? 1 : 0,
                    $r->created_by,
                    optional($r->created_at)?->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, $filename);
    }

    public function render()
    {
        $companyId = $this->companyId();

        $rows = $this->exceptionalDayService->getRowsQuery($companyId, $this->getFilters())->paginate($this->perPage);

        $createdByMap = [];
        if (Schema::hasTable('users')) {
            $ids = $rows->getCollection()->pluck('created_by')->filter()->unique()->values()->all();
            if (!empty($ids)) {
                $createdByMap = DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->toArray();
            }
        }

        $stats = [
            'total_year' => AttendanceExceptionalDay::query()
                ->where('company_id', $companyId)
                ->whereYear('start_date', $this->year)
                ->count(),

            'active_now' => AttendanceExceptionalDay::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date', '>=', now()->toDateString())
                ->count(),

            'upcoming_month' => AttendanceExceptionalDay::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereYear('start_date', now()->year)
                ->whereMonth('start_date', now()->month)
                ->whereDate('start_date', '>', now()->toDateString())
                ->count(),

            'cost_estimate' => null,
        ];

        $allowedBranchIds = $this->exceptionalDayService->currentUserAllowedBranchIds($companyId);
        $opts = $this->exceptionalDayService->loadScopeOptions($companyId, app()->getLocale(), $allowedBranchIds);

        $copyRows = collect();
        if ($this->showCopyModal) {
            $copyRows = AttendanceExceptionalDay::query()
                ->where('company_id', $companyId)
                ->whereYear('start_date', (int) $this->copyFromYear)
                ->orderBy('start_date')
                ->limit(200)
                ->get();
        }

        return view('settings-module::livewire.attendance.exceptional-days.index', [
            'rows' => $rows,
            'stats' => $stats,

            'departmentsOptions' => $opts['departments'],
            'sectionsOptions' => $opts['sections'],
            'employeesOptions' => $opts['employees'],
            'branchesOptions' => $opts['branches'],
            'contractTypesOptions' => $opts['contractTypes'],

            'createdByMap' => $createdByMap,

            'copyRows' => $copyRows,
        ])->layout('layouts.company-admin');
    }
}
