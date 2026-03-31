<?php

namespace Athka\SystemSettings\Livewire\Approvals;

use Livewire\Component;
use Livewire\WithPagination;
use Athka\SystemSettings\Models\ApprovalPolicy;
use Athka\SystemSettings\Services\ApprovalSettingService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ApprovalSequenceSettings extends Component
{
    use WithPagination;

    public string $tab = 'leaves';
    public string $search = '';
    public string $filterStatus = 'all';
    public string $filterBranchId = '';
    public bool $showModal = false;
    public ?int $editingId = null;
    public ?int $confirmedPolicyId = null;

    public function updatingSearch() { $this->resetPage(); }
    public function updatingTab() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }
    public function updatingFilterBranchId() { $this->resetPage(); }

    public function clearAllFilters()
    {
        $this->search = '';
        $this->filterStatus = 'all';
        $this->filterBranchId = '';
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
        $this->authorize('settings.approval.manage');
        $this->resetSteps();
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->resetSteps();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $policy = ApprovalPolicy::findOrFail($id);
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
        $this->validate([
            'name'  => 'required|string|max:255',
            'steps' => 'required|array|min:1',
        ]);

        $this->approvalService->savePolicy(auth()->user()->saas_company_id, $this->tab, [
            'name' => $this->name,
            'is_active' => $this->is_active,
            'scope_type' => $this->scope_type,
            'scope_ids' => $this->scope_ids,
            'steps' => $this->steps,
        ], $this->editingId);

        $this->showModal = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Operation successful')]);
    }

    public function addStep(): void
    {
        $this->steps[] = [
            '_key' => Str::random(8),
            'approver_type' => 'direct_manager',
            'approver_id' => 0,
            'follow_standard' => false,
        ];
    }

    public function removeStep(int $index): void
    {
        if (count($this->steps) > 1) {
            unset($this->steps[$index]);
            $this->steps = array_values($this->steps);
        }
    }

    public function moveStepUp(int $index): void
    {
        if ($index > 0) {
            $prev = $this->steps[$index - 1];
            $this->steps[$index - 1] = $this->steps[$index];
            $this->steps[$index] = $prev;
        }
    }

    public function moveStepDown(int $index): void
    {
        if ($index < count($this->steps) - 1) {
            $next = $this->steps[$index + 1];
            $this->steps[$index + 1] = $this->steps[$index];
            $this->steps[$index] = $next;
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmedPolicyId = $id;
        $this->dispatch('open-confirm-delete-policy-dialog');
    }

    public function deletePolicy(): void
    {
        if ($this->confirmedPolicyId) {
            $policy = ApprovalPolicy::find($this->confirmedPolicyId);
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
            'overtime'         => tr('Overtime'),
            'expenses'         => tr('Expenses'),
        ];
    }

    public function render()
    {
        $companyId = auth()->user()->saas_company_id;
        $lookups = $this->approvalService->getLookups($companyId);

        $policies = ApprovalPolicy::where('company_id', $companyId)
            ->where('operation_key', $this->tab)
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
}
