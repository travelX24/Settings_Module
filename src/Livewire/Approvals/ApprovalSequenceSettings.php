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

    public function edit(int $id): void
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

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
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
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
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
