<?php

namespace Athka\SystemSettings\Livewire\UserAccessControl;

use App\Models\User;
use Athka\Employees\Models\Employee;
use Spatie\Permission\Models\Role;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Athka\SystemSettings\Services\AccessControlService;
use Athka\SystemSettings\Livewire\UserAccessControl\Traits\HandleBranchFilter;

class Users extends Component
{
    use WithPagination, HandleBranchFilter;

    public $search = '';
    public $showModal = false;
    public $editingId = null;

    // Form Fields
    public $name = '', $email = '', $role = '', $access_scope = 'all_branches', $access_type = 'system_and_app', $is_active = true;
    public $selectedEmployeeId = null;

    protected $uacService;

    public function boot(AccessControlService $service)
    {
        $this->uacService = $service;
    }

    public function mount()
    {
        $this->authorize('uac.users.view');
        $this->initBranchFilter();
    }

    public function openAddModal()
    {
        $this->authorize('uac.users.manage');
        $this->reset(['name', 'email', 'role', 'editingId', 'selectedEmployeeId']);
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $this->edit($id);
    }

    public function selectEmployee($id)
    {
        if (!$id) return;
        $emp = Employee::findOrFail($id);
        $this->selectedEmployeeId = $id;
        $this->name = $emp->name_ar ?? $emp->name_en;
        $this->email = $emp->work_email ?? $emp->personal_email ?? '';
    }

    public function sendPasswordReset($id)
    {
        $user = User::findOrFail($id);
        if (method_exists($user, 'sendWithAuthKitPasswordReset')) {
            $user->sendWithAuthKitPasswordReset();
            $this->dispatch('toast', type: 'success', message: tr('Password reset link sent'));
        }
    }

    public function edit($id)
    {
        $this->authorize('uac.users.manage');
        $user = User::where('saas_company_id', $this->getCompanyId())->findOrFail($id);
        
        $this->editingId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->roles->first()?->name ?? '';
        $this->access_scope = $user->access_scope ?? 'all_branches';
        $this->access_type = $user->access_type ?? 'system_and_app';
        $this->is_active = $user->is_active;
        $this->selectedEmployeeId = $user->employee_id;
        
        $this->showModal = true;
    }

    public function save()
    {
        $this->authorize('uac.users.manage');
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->editingId,
            'access_type' => 'required',
            'access_scope' => 'required',
        ]);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'access_scope' => $this->access_scope,
            'access_type' => $this->access_type,
            'is_active' => $this->is_active,
            'employee_id' => $this->selectedEmployeeId,
        ];

        if (!$this->editingId) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make(Str::random(10));
        }

        $user = $this->uacService->saveUser($this->getCompanyId(), $data, $this->editingId);

        if (!$this->editingId && method_exists($user, 'sendWithAuthKitPasswordReset')) {
            $user->sendWithAuthKitPasswordReset();
        }

        $this->showModal = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Operation successful')]);
    }

    public function toggleStatus($id)
    {
        $this->authorize('uac.users.manage');
        $user = User::where('saas_company_id', $this->getCompanyId())->findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Status updated')]);
    }

    public function render()
    {
        $companyId = $this->getCompanyId();
        $users = User::where('saas_company_id', $companyId)
            ->with(['roles', 'employee'])
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%"))
            ->paginate(10);

        return view('systemsettings::livewire.user-access-control.users', [
            'users' => $users,
            'roles' => Role::where('name', '!=', 'saas-admin')->get(),
            'availableEmployees' => Employee::forCompany($companyId)->get()
        ]);
    }
}
