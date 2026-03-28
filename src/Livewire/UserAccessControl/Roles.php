<?php

namespace Athka\SystemSettings\Livewire\UserAccessControl;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Athka\SystemSettings\Services\AccessControlService;
use Athka\SystemSettings\Livewire\UserAccessControl\Traits\HandleBranchFilter;

use Livewire\Attributes\On;

class Roles extends Component
{
    use WithPagination, HandleBranchFilter;

    public $search = '';
    public $showModal = false;
    public $editingId = null;
    
    // Form Fields
    public $name = '';
    public $description = '';
    public $selectedPermissions = [];

    protected $uacService;

    public function boot(AccessControlService $service)
    {
        $this->uacService = $service;
    }

    public function mount()
    {
        $this->authorize('uac.roles.view');
        $this->initBranchFilter();
        $this->uacService->syncPermissionDefinitions();
    }

    #[On('open-add-role-modal')]
    public function openAddModal()
    {
        $this->authorize('uac.roles.manage');
        $this->reset(['name', 'description', 'selectedPermissions', 'editingId']);
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $this->edit($id);
    }

    public function edit($id)
    {
        $this->authorize('uac.roles.manage');
        $role = Role::findOrFail($id);

        if ($role->saas_company_id !== null && $role->saas_company_id !== $this->getCompanyId()) {
            abort(403, tr('Unauthorized operation.'));
        }

        if (in_array($role->name, ['company-admin', 'saas-admin', 'super-admin', 'system-admin']) || $role->saas_company_id === null) {
            abort(403, tr('System roles cannot be edited.'));
        }

        $this->editingId = $id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save()
    {
        $this->authorize('uac.roles.manage');
        
        $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('saas_company_id', $this->getCompanyId())->where('guard_name', 'web'))
                    ->ignore($this->editingId)
            ]
        ]);

        $this->uacService->saveRole([
            'name' => $this->name,
            'permissions' => $this->selectedPermissions
        ], $this->editingId, $this->getCompanyId());

        $this->showModal = false;
        $this->resetPage();
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Operation successful')]);
    }

    public function toggleGroup($groupName)
    {
        $this->authorize('uac.roles.manage');
        $groups = $this->uacService->getPermissionGroups();
        if (!isset($groups[$groupName])) return;

        $groupPermissions = array_keys($groups[$groupName]);
        $alreadySelected = array_intersect($groupPermissions, $this->selectedPermissions);

        if (count($alreadySelected) === count($groupPermissions)) {
            // Deselect all
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $groupPermissions));
        } else {
            // Select all
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $groupPermissions)));
        }
    }

    public function toggleTab($tabKey)
    {
        $this->authorize('uac.roles.manage');
        $tabs = $this->uacService->getPermissionTabs();
        if (!isset($tabs[$tabKey])) return;

        $tabPermissions = collect($tabs[$tabKey]['groups'])->flatMap(fn($g) => array_keys($g))->toArray();
        $alreadySelected = array_intersect($tabPermissions, $this->selectedPermissions);

        if (count($alreadySelected) === count($tabPermissions)) {
            // Deselect all in tab
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $tabPermissions));
        } else {
            // Select all in tab
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $tabPermissions)));
        }
    }

    public function copyRole($id)
    {
        $this->authorize('uac.roles.manage');
        $role = Role::findOrFail($id);
        
        if ($role->saas_company_id !== null && $role->saas_company_id !== $this->getCompanyId()) {
            abort(403, tr('Unauthorized operation.'));
        }

        $this->reset(['editingId']);
        $this->name = $role->name . ' - ' . tr('Copy');
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function delete($id)
    {
        $this->authorize('uac.roles.manage');
        $res = $this->uacService->deleteRole($id, $this->getCompanyId());
        
        if ($res['ok']) {
            $this->resetPage();
            $this->dispatch('toast', ['type' => 'success', 'message' => tr('Deleted successfully')]);
        } else {
            $this->dispatch('toast', ['type' => 'error', 'message' => $res['message']]);
        }
    }

    public function render()
    {
        $roles = Role::where('name', '!=', 'saas-admin')
            ->where(function ($q) {
                $q->where('saas_company_id', $this->getCompanyId())
                  ->orWhereNull('saas_company_id');
            })
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->withCount(['users' => fn($q) => $q->where('saas_company_id', $this->getCompanyId())])
            ->paginate(10);

        return view('systemsettings::livewire.user-access-control.roles', [
            'roles' => $roles,
            'permissionGroups' => $this->uacService->getPermissionGroups(),
            'permissionTabs' => $this->uacService->getPermissionTabs()
        ]);
    }
}
