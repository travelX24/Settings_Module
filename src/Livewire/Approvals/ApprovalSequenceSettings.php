<?php

namespace Athka\SystemSettings\Livewire\Approvals;

use Livewire\Component;
use Livewire\WithPagination;
use Athka\SystemSettings\Models\ApprovalPolicy;
use Athka\SystemSettings\Services\ApprovalSettingService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Athka\Employees\Support\EmployeeStatus;

class ApprovalSequenceSettings extends Component
{
    use WithPagination;

    public string $tab = 'leaves';
    public string $search = '';
    public string $filterStatus = 'all';
    public string $filterBranchId = '';
    public string $employeeStatus = EmployeeStatus::ACTIVE;
    public bool $showModal = false;
    public ?int $editingId = null;
    public ?int $confirmedPolicyId = null;

    public function updatingSearch() { $this->resetPage(); }
    public function updatingTab() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }
    public function updatingFilterBranchId() { $this->resetPage(); }
    public function updatingEmployeeStatus() { $this->resetPage(); }

    public function clearAllFilters()
    {
        $this->search = '';
        $this->filterStatus = 'all';
        $this->filterBranchId = '';
        $this->employeeStatus = EmployeeStatus::ACTIVE;
        $this->resetPage();
    }

    // Form
    public string $name = '';
    public bool $is_active = true;
    public string $scope_type = 'all';
    public array $scope_ids = [];
    public array $steps = [];

    protected $approvalService;

    public function boot(ApprovalSettingService $service)
    {
        $this->approvalService = $service;
    }

    public function mount(): void
    {
        $this->authorizeView();
        $this->resetSteps();
    }

    private function authorizeView(): void
    {
        abort_unless(auth()->user()?->can('settings.approval.view') || auth()->user()?->can('settings.approval.manage'), 403);
    }

    private function authorizeManage(): void
    {
        $this->authorize('settings.approval.manage');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->is_active = true;
        $this->scope_type = 'all';
        $this->scope_ids = [];
        $this->resetSteps();
    }

    public function openCreate(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeManage();
        $policy = ApprovalPolicy::where('company_id', auth()->user()->saas_company_id)->findOrFail($id);
        $this->editingId = $id;
        $this->name = $policy->name;
        $this->is_active = $policy->is_active;
        $this->scope_type = $policy->scope_type;
        $this->scope_ids = $policy->scopes()->pluck('scope_id')->toArray();
        $this->steps = $policy->steps()->orderBy('position')->get()->toArray();
        $this->showModal = true;
    }

    protected function messages(): array
    {
        return [
            'name.required'   => tr('The policy name is required'),
            'name.max'        => tr('The policy name must not exceed 255 characters'),
            'steps.required'  => tr('You must add at least one approver to the sequence'),
            'steps.min'       => tr('You must add at least one approver to the sequence'),
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name'       => tr('Policy Name'),
            'steps'      => tr('Approval Sequence'),
            'scope_type' => tr('Scope Type'),
            'scope_ids'  => tr('Values'),
        ];
    }

    public function save(): void
    {
        $this->authorizeManage();
        $this->validate([
            'name'  => 'required|string|max:255',
            'steps' => 'required|array|min:1',
        ]);

        if (!$this->validateApprovalSteps()) {
            return;
        }

        $this->approvalService->savePolicy(auth()->user()->saas_company_id, $this->tab, [
            'name' => $this->name,
            'is_active' => $this->is_active,
            'scope_type' => $this->scope_type,
            'scope_ids' => $this->scope_ids,
            'steps' => $this->steps,
        ], $this->editingId);

        $this->closeModal();
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Operation successful')]);
    }

    private function validateApprovalSteps(): bool
    {
        $companyId = (int) auth()->user()->saas_company_id;

        if (!Schema::hasColumn('users', 'employee_id')) {
            $this->addError('steps', tr('Cannot validate approvers because users are not linked to employees in this installation.'));
            return false;
        }

        foreach ($this->steps as $index => $step) {
            $type = (string) ($step['approver_type'] ?? 'direct_manager');

            if ($type !== 'user') {
                continue;
            }

            $userId = (int) ($step['approver_id'] ?? 0);
            $hasLinkedEmployee = $userId > 0 && DB::table('users')
                ->when(Schema::hasColumn('users', 'saas_company_id'), fn ($q) => $q->where('saas_company_id', $companyId))
                ->when(!Schema::hasColumn('users', 'saas_company_id') && Schema::hasColumn('users', 'company_id'), fn ($q) => $q->where('company_id', $companyId))
                ->where('id', $userId)
                ->whereNotNull('employee_id')
                ->where('employee_id', '>', 0)
                ->exists();

            if (!$hasLinkedEmployee) {
                $this->addError("steps.$index.approver_id", tr('The selected approver user is not linked to an employee.'));
                return false;
            }
        }

        return true;
    }
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function addStep(): void
    {
        $this->authorizeManage();
        $this->steps[] = [
            '_key' => Str::random(8),
            'approver_type' => 'direct_manager',
            'approver_id' => 0,
            'follow_standard' => false,
        ];
    }

    public function removeStep(int $index): void
    {
        $this->authorizeManage();
        if (count($this->steps) > 1) {
            unset($this->steps[$index]);
            $this->steps = array_values($this->steps);
        }
    }

    public function moveStepUp(int $index): void
    {
        $this->authorizeManage();
        if ($index > 0) {
            $prev = $this->steps[$index - 1];
            $this->steps[$index - 1] = $this->steps[$index];
            $this->steps[$index] = $prev;
        }
    }

    public function moveStepDown(int $index): void
    {
        $this->authorizeManage();
        if ($index < count($this->steps) - 1) {
            $next = $this->steps[$index + 1];
            $this->steps[$index + 1] = $this->steps[$index];
            $this->steps[$index] = $next;
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->confirmedPolicyId = $id;
        $this->dispatch('open-confirm-delete-policy-dialog');
    }

    public function deletePolicy(): void
    {
        $this->authorizeManage();
        if ($this->confirmedPolicyId) {
            $policy = ApprovalPolicy::where('company_id', auth()->user()->saas_company_id)->find($this->confirmedPolicyId);
            if ($policy) {
                $policy->delete();
                $this->dispatch('toast', ['type' => 'success', 'message' => tr('Policy deleted successfully')]);
            }
        }
        $this->confirmedPolicyId = null;
    }

    private function resetSteps(): void
    {
        $this->steps = [['approver_type' => 'direct_manager', 'approver_id' => 0]];
    }

    public function getTabsProperty(): array
    {
        return [
            'leaves'           => tr('Leaves'),
            'leave_exceptions' => tr('Leave Exceptions'),
            'permissions'      => tr('Permissions'),
            'missions'         => tr('Missions'),
            'overtime'         => tr('Overtime'),
            'expenses'         => tr('Expenses'),
        ];
    }

    public function render()
    {
        $companyId = auth()->user()->saas_company_id;
        $lookups = $this->approvalService->getLookups($companyId);
        $lookups['employees'] = $this->employeeLookupForCompany($companyId);

        $policies = ApprovalPolicy::where('company_id', $companyId)
            ->where('operation_key', $this->tab)
            ->with(['steps', 'scopes'])
            ->withCount(['steps', 'scopes'])
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filterStatus !== 'all', function($q) {
                $q->where('is_active', $this->filterStatus === 'active' ? 1 : 0);
            })
            ->when($this->filterBranchId, function($q) {
                $q->where(function($sub) {
                    $sub->where('scope_type', 'all')
                        ->orWhere(function($scopeQuery) {
                            $scopeQuery->where('scope_type', 'branch')
                                ->whereHas('scopes', fn($s) => $s->where('scope_id', $this->filterBranchId));
                        });
                });
            })
            ->paginate(10);

        $employeeRows = $this->approvalEmployeesForCompany($companyId);

        foreach ($policies as $p) {
            if (($p->scope_type ?? 'all') === 'all') {
                $p->scope_names_list = tr('All Employees');
            } else {
                $lookupKey = $p->scope_type === 'employee' ? 'employees' : ($p->scope_type . 's');
                $map = collect($lookups[$lookupKey] ?? [])->pluck('name', 'id');
                $p->scope_names_list = $p->scopes->map(fn($s) => $map[$s->scope_id] ?? ('#' . $s->scope_id))->implode(', ');
            }

            $affectedEmployees = $this->resolveAffectedEmployeesForPolicy($p, $employeeRows);
            $p->affected_employees_count = $affectedEmployees->count();
            $p->affected_employee_names = $affectedEmployees->pluck('name')->values()->all();

            $userMap = collect($lookups['users'] ?? [])->pluck('name', 'id');
            $p->step_names_list = $p->steps->map(function($s, $idx) use ($userMap) {
                $typeLabel = $s->approver_type === 'direct_manager' ? tr('Direct Manager') : ($userMap[$s->approver_id] ?? ('#' . $s->approver_id));
                return ($idx + 1) . '. ' . $typeLabel;
            })->implode("\n");
        }

        $counts = ApprovalPolicy::where('company_id', $companyId)
            ->select('operation_key', DB::raw('count(*) as count'))
            ->groupBy('operation_key')
            ->pluck('count', 'operation_key')
            ->toArray();

        return view('system-settings::livewire.approvals.approval-sequence-settings', [
            'policies' => $policies,
            'lookups'  => $lookups,
            'counts'   => $counts,
        ])->layout('layouts.company-admin');
    }

    private function approvalEmployeesForCompany(int $companyId)
    {
        if (!Schema::hasTable('employees')) {
            return collect();
        }

        $nameSelect = $this->employeeNameExpression();

        $query = DB::table('employees')
            ->select([
                'id',
                'branch_id',
                'department_id',
                'job_title_id',
                'status',
                DB::raw($nameSelect . ' as name'),
            ]);

        if (Schema::hasColumn('employees', 'saas_company_id')) {
            $query->where('saas_company_id', $companyId);
        } elseif (Schema::hasColumn('employees', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('employees', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('employees', 'status') && $this->employeeStatus !== 'all') {
            $query->where('status', $this->employeeStatus);
        }

        return $query->orderBy('name')->get();
    }

    private function employeeLookupForCompany(int $companyId): array
    {
        if (!Schema::hasTable('employees')) {
            return [];
        }

        $nameSelect = $this->employeeNameExpression();
        $query = DB::table('employees')
            ->select(['id', 'status', DB::raw($nameSelect . ' as name')]);

        if (Schema::hasColumn('employees', 'saas_company_id')) {
            $query->where('saas_company_id', $companyId);
        } elseif (Schema::hasColumn('employees', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('employees', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('employees', 'status') && $this->employeeStatus !== 'all') {
            $query->where('status', $this->employeeStatus);
        }

        return $query->orderBy('name')->get()->map(function ($employee) {
            $row = (array) $employee;
            if (($row['status'] ?? EmployeeStatus::ACTIVE) !== EmployeeStatus::ACTIVE) {
                $row['name'] = $row['name'] . ' - ' . EmployeeStatus::label($row['status'] ?? null);
            }

            return $row;
        })->toArray();
    }

    private function employeeNameExpression(): string
    {
        $columns = [];

        foreach (['name_ar', 'name_en', 'name', 'full_name', 'employee_no'] as $column) {
            if (Schema::hasColumn('employees', $column)) {
                $columns[] = $column;
            }
        }

        if (empty($columns)) {
            return 'CAST(id AS CHAR)';
        }

        $quoted = array_map(fn($column) => "`{$column}`", $columns);

        return 'COALESCE(' . implode(', ', $quoted) . ', CAST(id AS CHAR))';
    }

    private function resolveAffectedEmployeesForPolicy(ApprovalPolicy $policy, $employeeRows)
    {
        $scopeType = $policy->scope_type ?? 'all';
        $scopeIds = $policy->scopes->pluck('scope_id')->map(fn($id) => (int) $id)->all();

        if ($scopeType === 'all') {
            return $employeeRows;
        }

        if (empty($scopeIds)) {
            return collect();
        }

        return $employeeRows->filter(function ($employee) use ($scopeType, $scopeIds) {
            return match ($scopeType) {
                'employee' => in_array((int) $employee->id, $scopeIds, true),
                'department' => in_array((int) ($employee->department_id ?? 0), $scopeIds, true),
                'job_title' => in_array((int) ($employee->job_title_id ?? 0), $scopeIds, true),
                'branch' => in_array((int) ($employee->branch_id ?? 0), $scopeIds, true),
                default => false,
            };
        })->values();
    }
}
