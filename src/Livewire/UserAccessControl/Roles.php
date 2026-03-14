<?php

namespace Athka\SystemSettings\Livewire\UserAccessControl;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Athka\SystemSettings\Services\AccessControlService;
use Athka\SystemSettings\Livewire\UserAccessControl\Traits\HandleBranchFilter;

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

    public function openAddModal()
    {
        $this->authorize('uac.roles.manage');
        $this->reset(['name', 'description', 'selectedPermissions', 'editingId']);
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->authorize('uac.roles.manage');
        $role = Role::findOrFail($id);
        $this->editingId = $id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->showModal = true;
    }

    public function save()
    {
        $this->authorize('uac.roles.manage');
        $this->validate(['name' => 'required|string|max:255|unique:roles,name,' . $this->editingId]);

        $this->uacService->saveRole([
            'name' => $this->name,
            'permissions' => $this->selectedPermissions
        ], $this->editingId);

        $this->showModal = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Operation successful')]);
    }

    public function delete($id)
    {
        $this->authorize('uac.roles.manage');
        $res = $this->uacService->deleteRole($id, $this->getCompanyId());
        
        if ($res['ok']) {
            $this->dispatch('toast', ['type' => 'success', 'message' => tr('Deleted successfully')]);
        } else {
            $this->dispatch('toast', ['type' => 'error', 'message' => $res['message']]);
        }
    }

    public function render()
    {
        $roles = Role::where('name', '!=', 'saas-admin')
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->withCount(['users' => fn($q) => $q->where('saas_company_id', $this->getCompanyId())])
            ->paginate(10);

        return view('systemsettings::livewire.user-access-control.roles', [
            'roles' => $roles,
            'permissionGroups' => $this->uacService->getPermissionGroups()
        ]);
    }
}
