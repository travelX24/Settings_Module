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

use Livewire\Attributes\On;

class Users extends Component
{
    use WithPagination, HandleBranchFilter;

    public $search = '';
    public $viewMode = 'table';
    public $showModal = false;
    public $showPermModal = false;
    public $editingId = null;
    public $permUserId = null;

    public function updatingSearch() { $this->resetPage(); }

    // Form Fields
    public $name = '', $email = '', $role = '', $access_scope = 'all_branches', $access_type = 'system_and_app', $is_active = true;
    public $selectedEmployeeId = null;
    public array $allowed_branch_ids = [];
    public bool $is_locked_role = false;
    public bool $needs_employee_link = false;

    // Custom permissions
    public array $customPermissions = [];
    public ?string $permUserName = null;
    public ?string $permReferencRole = null;
    public bool $permUserHasCustom = false;

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

    #[On('open-add-user-modal')]
    public function openAddModal()
    {
        $this->authorize('uac.users.manage');
        $this->reset(['name', 'email', 'role', 'editingId', 'selectedEmployeeId']);
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $this->edit($id);
    }

    public function updatedSelectedEmployeeId($id)
    {
        if (!$id) {
            $this->reset(['name', 'email']);
            return;
        }
        $emp = Employee::findOrFail($id);
        $this->name = $emp->name_ar ?? $emp->name_en;
        $this->email = $emp->email_work ?? $emp->email_personal ?? '';
    }

    public function selectEmployee($id)
    {
        $this->selectedEmployeeId = $id;
        $this->updatedSelectedEmployeeId($id);
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
        $user = User::where('saas_company_id', $this->getCompanyId())->with(['roles', 'allowedBranches'])->findOrFail($id);
        
        $this->editingId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->roles->first()?->name ?? '';
        $this->is_locked_role = in_array($this->role, ['company-admin', 'saas-admin']);
        $this->access_scope = $user->access_scope ?? 'all_branches';
        $this->access_type = $user->access_type ?? 'system_and_app';
        $this->is_active = $user->is_active;
        $this->selectedEmployeeId = $user->employee_id;
        $this->needs_employee_link = empty($user->employee_id);
        $this->allowed_branch_ids = $user->allowedBranches->pluck('id')->map(fn($id) => (string)$id)->toArray();
        
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save()
    {
        $this->authorize('uac.users.manage');
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->editingId,
            'access_type' => 'required',
            'access_scope' => 'required',
        ];

        if ($this->access_type === 'system_and_app') {
            $rules['role'] = 'required';
        }

        $this->validate($rules, [
            'required' => tr('This field is required'),
            'email' => tr('Invalid email format'),
            'unique' => tr('This email is already registered'),
            'max' => tr('Value is too long'),
        ], [
            'name' => tr('Name'),
            'email' => tr('Email'),
            'role' => tr('Role'),
            'access_type' => tr('License'),
            'access_scope' => tr('Scope'),
        ]);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'access_scope' => $this->access_scope,
            'access_type' => $this->access_type,
            'is_active' => $this->is_active,
            'employee_id' => $this->selectedEmployeeId,
            'allowed_branch_ids' => $this->allowed_branch_ids,
        ];

        if (!$this->editingId) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make(Str::random(10));
        }

        $user = $this->uacService->saveUser($this->getCompanyId(), $data, $this->editingId);

        if (!$this->editingId && method_exists($user, 'sendWithAuthKitPasswordReset')) {
            $user->sendWithAuthKitPasswordReset();
        }

        $this->showModal = false;
        $this->resetPage();
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Operation successful')]);
    }

    public function toggleStatus($id)
    {
        $this->authorize('uac.users.manage');
        $user = User::where('saas_company_id', $this->getCompanyId())->findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);
        $this->resetPage();
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Status updated')]);
    }

    public function openPermModal(int $userId)
    {
        $this->authorize('uac.users.manage');
        $user = User::where('saas_company_id', $this->getCompanyId())
            ->with('roles')
            ->findOrFail($userId);

        $this->permUserId = $userId;
        $this->permUserName = $user->name;
        $this->permUserHasCustom = (bool) $user->has_custom_permissions;

        // Determine reference role name
        if ($user->has_custom_permissions) {
            $this->permReferencRole = $user->reference_role;
        } else {
            $this->permReferencRole = $user->roles->first()?->name;
        }

        // Load currently active permissions on the user
        $this->customPermissions = $user->getAllPermissions()->pluck('name')->toArray();

        $this->showPermModal = true;
    }

    public function saveCustomPermissions()
    {
        $this->authorize('uac.users.manage');

        if (!$this->permUserId || !$this->permReferencRole) {
            $this->dispatch('toast', ['type' => 'error', 'message' => tr('Missing required data.')]);
            return;
        }

        $user = User::where('saas_company_id', $this->getCompanyId())->findOrFail($this->permUserId);

        $this->uacService->saveCustomPermissions($user, $this->permReferencRole, $this->customPermissions);

        $this->showPermModal = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Custom permissions saved.')]);
    }

    public function resetToRoleDefault()
    {
        $this->authorize('uac.users.manage');

        $user = User::where('saas_company_id', $this->getCompanyId())->findOrFail($this->permUserId);
        $result = $this->uacService->resetToRoleDefault($user);

        if ($result['ok']) {
            $this->showPermModal = false;
            $this->dispatch('toast', ['type' => 'success', 'message' => tr('Permissions reset to role default.')]);
        } else {
            $this->dispatch('toast', ['type' => 'error', 'message' => $result['message']]);
        }
    }

    public function toggleGroupCustom(string $groupName)
    {
        $groups = $this->uacService->getPermissionGroups();
        if (!isset($groups[$groupName])) return;
        $groupKeys = array_keys($groups[$groupName]);
        $alreadySelected = array_intersect($groupKeys, $this->customPermissions);
        if (count($alreadySelected) === count($groupKeys)) {
            $this->customPermissions = array_values(array_diff($this->customPermissions, $groupKeys));
        } else {
            $this->customPermissions = array_values(array_unique(array_merge($this->customPermissions, $groupKeys)));
        }
    }

    public function toggleTabCustom(string $tabKey)
    {
        $tabs = $this->uacService->getPermissionTabs();
        if (!isset($tabs[$tabKey])) return;

        $tabPermissions = collect($tabs[$tabKey]['groups'])->flatMap(fn($g) => array_keys($g))->toArray();
        $alreadySelected = array_intersect($tabPermissions, $this->customPermissions);

        if (count($alreadySelected) === count($tabPermissions)) {
            // Deselect all in tab
            $this->customPermissions = array_values(array_diff($this->customPermissions, $tabPermissions));
        } else {
            // Select all in tab
            $this->customPermissions = array_values(array_unique(array_merge($this->customPermissions, $tabPermissions)));
        }
    }

    public function render()
    {
        $companyId = $this->getCompanyId();
        $metadata = $this->uacService->getBranchMetadata($companyId);
        
        $users = User::where('saas_company_id', $companyId)
            ->with(['roles', 'employee'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterBranchId, function ($query) use ($metadata) {
                $query->whereHas('employee', function ($q) use ($metadata) {
                    $q->where($metadata['col'], $this->filterBranchId);
                });
            })
            ->paginate(10);

        $branchesById = collect($metadata['list'])->pluck('name', 'id')->toArray();

        // Detailed info for selected employee
        $selectedEmp = null;
        if ($this->selectedEmployeeId) {
            $selectedEmp = Employee::with(['department', 'jobTitle'])->find($this->selectedEmployeeId);
        }

        return view('systemsettings::livewire.user-access-control.users', [
            'users' => $users,
            'roles' => Role::where('name', '!=', 'saas-admin')
                ->where(function ($q) use ($companyId) {
                    $q->where('saas_company_id', $companyId)
                      ->orWhereNull('saas_company_id');
                })->get(),
            'foundEmployees' => Employee::forCompany($companyId)
                ->where(function($query) {
                    $query->whereDoesntHave('user')
                        ->orWhere('id', $this->selectedEmployeeId);
                })->get(),
            'employeeBranchCol' => $metadata['col'],
            'branchesById' => $branchesById,
            'display_name' => $selectedEmp ? ($selectedEmp->name_ar ?? $selectedEmp->name_en) : '',
            'display_phone' => $selectedEmp ? ($selectedEmp->mobile ?? '') : '',
            'display_department' => $selectedEmp?->department?->name ?? '-',
            'display_job_title' => $selectedEmp?->jobTitle?->name ?? '-',
            'permissionGroups' => $this->uacService->getPermissionGroups(),
            'permissionsMap' => collect($this->uacService->getPermissionGroups())->flatMap(fn($g) => $g)->toArray(),
            'permissionTabs' => $this->uacService->getPermissionTabs()
        ]);
    }
}
