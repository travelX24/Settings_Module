<?php

namespace Athka\SystemSettings\Livewire\Approvals;

use Athka\SystemSettings\Models\ApprovalPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;

class ApprovalSequenceSettings extends Component
{
    use WithPagination;

    public string $tab = 'leaves';

    public string $search = '';
    public string $filterStatus = 'all'; // all|active|inactive

    public string $filterBranchId = ''; // '' => all branches
    public bool $showModal = false;
    public ?int $editingId = null;

    // Form
    public string $name = '';
    public bool $is_active = true;

    public string $scope_type = 'all'; // all|department|job_title|branch|employee
    public array $scope_ids = [];      // متعدد حسب النوع

    /**
     * Steps:
     * - direct_manager: approver_id = 0
     * - user: approver_id = employee_id
     */
    public array $steps = []; // [['approver_type'=>'direct_manager|user','approver_id'=>..], ...]

    // Lists
    public array $departments = [];
    public array $jobTitles = [];
    public array $branches = [];
    public array $employees = [];

    // for debug/consistency
    public string $employeesTable = 'employees';

    public function mount(): void
    {
        $this->loadLookups();
        $this->resetSteps();
    }

    private function companyId(): int
    {
        if (app()->bound('currentCompany') && app('currentCompany')) {
            return (int) app('currentCompany')->id;
        }

        return (int) (Auth::user()->saas_company_id ?? 0);
    }

    private function isRtl(): bool
    {
        $locale = app()->getLocale();
        return in_array(substr($locale, 0, 2), ['ar', 'fa', 'ur', 'he']);
    }

    private function labelCandidates(): array
    {
        // Prefer Arabic labels on RTL, else English
        if ($this->isRtl()) {
            return ['name_ar', 'name', 'name_en', 'title_ar', 'title', 'title_en'];
        }

        return ['name_en', 'name', 'name_ar', 'title_en', 'title', 'title_ar'];
    }

    private function pickLabelColumn(string $table, array $candidates, string $fallback = 'name'): string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) return $col;
        }

        return Schema::hasColumn($table, $fallback) ? $fallback : $candidates[0] ?? $fallback;
    }

    private function detectCompanyColumn(string $table): ?string
    {
        foreach (['saas_company_id', 'company_id'] as $c) {
            if (Schema::hasColumn($table, $c)) return $c;
        }
        return null;
    }

    public function loadLookups(): void
    {
        $companyId = $this->companyId();

        // departments
        if (Schema::hasTable('departments')) {
            $deptLabel = $this->pickLabelColumn('departments', $this->labelCandidates(), 'name');
            $this->departments = $this->simpleList('departments', 'id', $deptLabel, $companyId);
        } else {
            $this->departments = [];
        }

        // job_titles
        if (Schema::hasTable('job_titles')) {
            $jtLabel = $this->pickLabelColumn('job_titles', $this->labelCandidates(), 'name');
            $this->jobTitles = $this->simpleList('job_titles', 'id', $jtLabel, $companyId);
        } else {
            $this->jobTitles = [];
        }

        // branches
        if (Schema::hasTable('branches')) {
            $brLabel = $this->pickLabelColumn('branches', $this->labelCandidates(), 'name');
            $this->branches = $this->simpleList('branches', 'id', $brLabel, $companyId);
        } else {
            $this->branches = [];
        }

        // employees (prefer employees table, fallback users)
        if (Schema::hasTable('employees')) {
            $this->employeesTable = 'employees';
            $empLabel = $this->pickLabelColumn('employees', $this->labelCandidates(), 'name');
            $this->employees = $this->simpleList('employees', 'id', $empLabel, $companyId);
        } elseif (Schema::hasTable('users')) {
            $this->employeesTable = 'users';
            $empLabel = $this->pickLabelColumn('users', ['name', 'email'], 'name');
            // users غالباً company column = saas_company_id
            $this->employees = $this->simpleList('users', 'id', $empLabel, $companyId, 'saas_company_id', true);
        } else {
            $this->employees = [];
        }
    }

    private function simpleList(
        string $table,
        string $idCol,
        string $labelCol,
        int $companyId,
        ?string $companyCol = null,
        bool $checkCompanyCol = true
    ): array {
        if (!Schema::hasTable($table)) return [];
        if (!Schema::hasColumn($table, $idCol)) return [];
        if (!Schema::hasColumn($table, $labelCol)) return [];

        $q = DB::table($table)->select([$idCol . ' as id', $labelCol . ' as name']);

        if ($checkCompanyCol) {
            $companyCol = $companyCol ?: $this->detectCompanyColumn($table);
            if ($companyCol && Schema::hasColumn($table, $companyCol)) {
                $q->where($companyCol, $companyId);
            }
        }

        return $q->orderBy($labelCol)
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])
            ->all();
    }

   public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function updatedFilterBranchId(): void
    {
        $this->resetPage();
    }

    public function updatedScopeType(): void
    {
        // prevent mixed selections
        $this->scope_ids = [];
    }

    /**
     * Livewire array update hook.
     * $name example: "0.approver_type"
     */
    public function updatedSteps($value, $name): void
    {
        if (!is_string($name)) return;

        if (str_ends_with($name, '.approver_type')) {
            $parts = explode('.', $name);
            $i = (int) ($parts[0] ?? 0);

            if (($value ?? '') === 'direct_manager') {
                $this->steps[$i]['approver_id'] = 0;
            }
        }
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->is_active = true;

        if (($this->filterBranchId ?? '') !== '') {
            $this->scope_type = 'branch';
            $this->scope_ids  = [(int) $this->filterBranchId];
        } else {
            $this->scope_type = 'all';
            $this->scope_ids = [];
        }

        $this->resetSteps();

        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $p = ApprovalPolicy::query()
            ->where('company_id', $this->companyId())
            ->where('operation_key', $this->tab)
            ->findOrFail($id);

        $this->editingId = $p->id;
        $this->name = (string) $p->name;
        $this->is_active = (bool) $p->is_active;

        $this->scope_type = $p->scope_type ?? 'all';
        $this->scope_ids = $p->scopes()
            ->pluck('scope_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $this->steps = $p->steps()
            ->orderBy('position')
            ->get()
            ->map(function ($s) {
             $type = strtolower((string) $s->approver_type);

                // backward safety
                if ($type === 'role') {
                    $type = 'user';
                }

                // لو كان متقطّع بسبب VARCHAR(10)
                if (str_starts_with($type, 'direct')) {
                    $type = 'direct_manager';
                }

                if (!in_array($type, ['direct_manager', 'user'], true)) {
                    $type = 'direct_manager';
                }

              return $this->makeStep(
                    $type,
                    (int) ($s->approver_id ?? 0),
                    'db-' . (int) $s->id
                );


            })->all();

        if (count($this->steps) === 0) {
            $this->resetSteps();
        }

        $this->showModal = true;
    }

    private function resetSteps(): void
    {
        $this->steps = [
            $this->makeStep('direct_manager', 0),
        ];
    }

    public function addStep(): void
    {
        $this->steps[] = $this->makeStep('direct_manager', 0);
    }


    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);

        if (count($this->steps) === 0) {
            $this->resetSteps();
        }
    }

    public function moveStepUp(int $index): void
    {
        if ($index <= 0) return;
        [$this->steps[$index - 1], $this->steps[$index]] = [$this->steps[$index], $this->steps[$index - 1]];
    }

    public function moveStepDown(int $index): void
    {
        if ($index >= count($this->steps) - 1) return;
        [$this->steps[$index + 1], $this->steps[$index]] = [$this->steps[$index], $this->steps[$index + 1]];
    }

    public function save(): void
    {
        $companyId = $this->companyId();

        // normalize steps first (direct_manager => approver_id=0)
        foreach ($this->steps as $i => $s) {
            $t = $s['approver_type'] ?? 'direct_manager';
            if ($t === 'direct_manager') {
                $this->steps[$i]['approver_id'] = 0;
            }
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'scope_type' => ['required', 'in:all,department,job_title,branch,employee'],
            'scope_ids'  => ['array'],

            'steps' => ['required', 'array', 'min:1'],
            'steps.*.approver_type' => ['required', 'in:direct_manager,user'],
            'steps.*.approver_id'   => ['nullable', 'integer', 'min:0'],
        ];

        if ($this->scope_type !== 'all') {
            $rules['scope_ids'] = ['required', 'array', 'min:1'];
        }

        $this->validate($rules);

        // custom validation: if user => approver_id must be >=1
        foreach ($this->steps as $i => $s) {
            $t = $s['approver_type'] ?? '';
            $id = (int) ($s['approver_id'] ?? 0);

            if ($t === 'user' && $id < 1) {
                $this->addError("steps.$i.approver_id", tr('This field is required'));
                return;
            }
        }

        DB::transaction(function () use ($companyId) {
            $policy = ApprovalPolicy::updateOrCreate(
                [
                    'id' => $this->editingId,
                    'company_id' => $companyId,
                    'operation_key' => $this->tab,
                ],
                [
                    'name' => $this->name,
                    'is_active' => $this->is_active,
                    'scope_type' => $this->scope_type,
                    'created_by' => Auth::id(),
                ]
            );

            // scopes
            $policy->scopes()->delete();
            if ($this->scope_type !== 'all') {
                $now = now();

                $rows = collect($this->scope_ids)
                    ->filter(fn ($sid) => $sid !== null && (string) $sid !== '')
                    ->map(fn ($sid) => [
                        'policy_id'  => (int) $policy->id,
                        'scope_id'   => (int) $sid,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->unique('scope_id')
                    ->values()
                    ->all();

                if (!empty($rows)) {
                    $policy->scopes()->insert($rows);
                }
            }

            // steps
            $policy->steps()->delete();
            $pos = 1;

            foreach ($this->steps as $s) {
                $type = $s['approver_type'] ?? 'direct_manager';

                $policy->steps()->create([
                    'position' => $pos++,
                    'approver_type' => $type,
                    'approver_id'   => ($type === 'direct_manager') ? 0 : (int) ($s['approver_id'] ?? 0),
                ]);
            }
        });

        $this->showModal = false;
        $this->resetPage();
    }

    public function deletePolicy(int $id): void
    {
        $p = ApprovalPolicy::query()
            ->where('company_id', $this->companyId())
            ->where('operation_key', $this->tab)
            ->findOrFail($id);

        $relatedCount = 0;

        if ($relatedCount > 0) {
            $p->update(['is_active' => false]);
            return;
        }

        DB::transaction(function () use ($p) {
            $p->steps()->delete();
            $p->scopes()->delete();
            $p->delete();
        });

        $this->resetPage();
    }
    private function makeStep(string $type = 'direct_manager', int $id = 0, ?string $key = null): array
    {
        return [
            '_key' => $key ?: (string) Str::uuid(),
            'approver_type' => $type,
            'approver_id' => $id,
        ];
    }


    public function getTabsProperty(): array
    {
        return [
            'leaves'         => tr('Leaves'),
            'overtime'       => tr('Overtime'),
            'compensations'  => tr('Compensations'),
            'advances'       => tr('Advances'),
            'terminations'   => tr('Employee Terminations'),
        ];
    }

    public function render()
    {
        $companyId = $this->companyId();

         $q = ApprovalPolicy::query()
            ->where('company_id', $companyId)
            ->where('operation_key', $this->tab);

        if ($this->search !== '') {
            $q->where('name', 'like', '%' . $this->search . '%');
        }

        if ($this->filterStatus === 'active') {
            $q->where('is_active', true);
        } elseif ($this->filterStatus === 'inactive') {
            $q->where('is_active', false);
        }

        if (($this->filterBranchId ?? '') !== '') {
            $bid = (int) $this->filterBranchId;

            $q->where(function ($qq) use ($bid) {
                $qq->where('scope_type', 'all')
                ->orWhere(function ($bq) use ($bid) {
                    $bq->where('scope_type', 'branch')
                        ->whereHas('scopes', fn ($sq) => $sq->where('scope_id', $bid));
                });
            });
        }

        $policies = $q->withCount('steps')
            ->latest('id')
            ->paginate(10);

        $counts = ApprovalPolicy::query()
            ->where('company_id', $companyId)
            ->selectRaw('operation_key, count(*) as c')
            ->groupBy('operation_key')
            ->pluck('c', 'operation_key')
            ->all();

        return view('system-settings::livewire.approvals.approval-sequence-settings', [
            'policies' => $policies,
            'counts'   => $counts,
        ])->layout('layouts.company-admin');
    }
}
