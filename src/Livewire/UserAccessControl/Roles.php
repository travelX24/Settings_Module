<?php

namespace Athka\SystemSettings\Livewire\UserAccessControl;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
class Roles extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editingId = null;
    public array $branches = [];
    public array $branchesById = [];
    public string $filterBranchId = '';
    public ?string $employeeBranchCol = null;
    public bool $lockBranchFilter = false;
    // Form Fields
    public $name = '';
    public $description = '';
    public $selectedPermissions = [];

    // Permission Groups (Mocked/Defined based on system modules)
    public $permissionGroups = [];

    protected $listeners = [
        'refreshRoles' => '$refresh',
        'open-add-role-modal' => 'openAddModal',
    ];

   public function mount()
    {
        $this->authorize('uac.roles.view');
        $this->loadPermissionGroups();
        $this->loadBranches();
        $this->initBranchFilterLock();
    }

private function loadBranches(): void
    {
        $this->branches = [];
        $this->branchesById = [];

        $this->employeeBranchCol = (Schema::hasTable('employees') && Schema::hasColumn('employees', 'branch_id'))
            ? 'branch_id'
            : null;

        if (!Schema::hasTable('branches')) {
            return;
        }

        $companyId = $this->companyId();

        // Prefer Arabic on RTL
        $labelCol = 'name';
        $locale = app()->getLocale();
        $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);

        if ($isRtl && Schema::hasColumn('branches', 'name_ar')) {
            $labelCol = 'name_ar';
        } elseif (!$isRtl && Schema::hasColumn('branches', 'name_en')) {
            $labelCol = 'name_en';
        } elseif (!Schema::hasColumn('branches', $labelCol)) {
            // fallback
            $labelCol = Schema::hasColumn('branches', 'title') ? 'title' : 'id';
        }

        // detect company col
        $companyCol = null;
        foreach (['saas_company_id', 'company_id'] as $c) {
            if (Schema::hasColumn('branches', $c)) { $companyCol = $c; break; }
        }

        $q = DB::table('branches')->select(['id', DB::raw("$labelCol as name")]);

        if ($companyCol) {
            $q->where($companyCol, $companyId);
        }

        $rows = $q->orderBy($labelCol)->get();

        $this->branches = $rows->map(fn($r) => ['id' => (int)$r->id, 'name' => (string)$r->name])->all();
        $this->branchesById = collect($this->branches)->pluck('name', 'id')->all();
    }
    private function companyId(): int
    {
        if (app()->bound('currentCompany') && app('currentCompany')) {
            return (int) app('currentCompany')->id;
        }

        return (int) (Auth::user()->saas_company_id ?? 0);
    }
    private function currentUserBranchId(): ?int
    {
        if (!$this->employeeBranchCol) return null;

        $employeeId = Auth::user()?->employee_id;
        if (!$employeeId) return null;

        $bid = DB::table('employees')->where('id', $employeeId)->value($this->employeeBranchCol);

        return $bid ? (int)$bid : null;
    }

    private function initBranchFilterLock(): void
    {
        $scope = Auth::user()?->access_scope ?? 'all_branches';

        if ($scope === 'my_branch') {
            $this->lockBranchFilter = true;
            $bid = $this->currentUserBranchId();
            $this->filterBranchId = $bid ? (string)$bid : '';
        }
    }

    private function effectiveBranchId(): ?int
    {
        if (($this->filterBranchId ?? '') === '') return null;
        return (int)$this->filterBranchId;
    }

    public function updatedFilterBranchId(): void
    {
        if ($this->lockBranchFilter) {
            $bid = $this->currentUserBranchId();
            $this->filterBranchId = $bid ? (string)$bid : '';
            return;
        }

        $this->resetPage();
    }
    public function loadPermissionGroups()
    {
        // Define system permissions and their groups based on UI modules
        $this->permissionGroups = [
            'Dashboard' => [
                'dashboard.view' => 'View Dashboard Statistics',
                'dashboard.reports' => 'Access Reports Dashboard',
            ],
            'Employee Management' => [
                'employees.view' => 'View Employees',
                'employees.create' => 'Add New Employee',
                'employees.edit' => 'Edit Employee Details',
                'employees.delete' => 'Delete / Archive Employee',
                'employees.export' => 'Export Employee Data',
                'employees.import' => 'Import Employees from CSV',
                'employees.status.manage' => 'Activate / Deactivate Status',
                'employees.documents.manage' => 'Manage Employee Documents',
            ],
            'Attendance & Shifts' => [
                'attendance.dashboard.view' => 'View Attendance Dashboard',
                'attendance.daily.view' => 'View Daily Attendance (All)',
                'attendance.daily.view-subordinates' => 'View Daily Attendance (Subordinates Only)',
                'attendance.daily.manage' => 'Manage Daily Attendance (Edit/Approve)',
                'attendance.daily.manual-entry' => 'Manual Attendance Entry',
                
                'attendance.penalties.view' => 'View Daily Penalties (All)',
                'attendance.penalties.view-subordinates' => 'View Daily Penalties (Subordinates Only)',
                'attendance.penalties.manage' => 'Manage Penalties (Confirm/Delete)',
                'attendance.penalties.waive' => 'Waive/Exempt Penalties',

                'attendance.leaves.view' => 'View Leaves & Permissions (All)',
                'attendance.leaves.view-subordinates' => 'View Leaves & Permissions (Subordinates Only)',
                'attendance.leaves.manage' => 'Manage Leaves (Create/Edit)',
                'attendance.leaves.approve' => 'Approve/Reject Requests',
                'attendance.missions.manage' => 'Manage Business Missions',

                'attendance.schedules.view' => 'View Work Schedules (All)',
                'attendance.schedules.view-subordinates' => 'View Work Schedules (Subordinates Only)',
                'attendance.schedules.manage' => 'Manage Schedules Assignment',
                'attendance.schedules.bulk-assign' => 'Bulk Schedule Assignment',

                'shifts.view' => 'View Shifts Schedule',
                'shifts.manage' => 'Manage Shifts & Rotation Rules',
                'holidays.manage' => 'Manage Official Holidays',
            ],
            'System Settings' => [
                'settings.general.view' => 'View General Settings',
                'settings.general.edit' => 'Edit General Settings',
                'settings.organizational.view' => 'View Organizational Structure',
                'settings.organizational.manage' => 'Manage Departments & Job Titles',
                'settings.attendance.view' => 'View Attendance Settings',
                'settings.attendance.manage' => 'Manage Attendance General Rules',
                'settings.attendance.holidays.manage' => 'Manage Attendance Holidays',
                'settings.attendance.schedules.manage' => 'Manage Work Schedule Settings',
                'settings.attendance.leaves.manage' => 'Manage Leave Policy Settings',
                'settings.attendance.exceptional.manage' => 'Manage Exceptional Days Settings',
            ],
            'Locations & Geofencing' => [
                'locations.view' => 'View Company Locations',
                'locations.manage' => 'Add/Edit Working Locations',
                'geofencing.manage' => 'Manage Geofencing Rules',
            ],
            'User Access Control' => [
                'uac.users.view' => 'View System Users',
                'uac.users.manage' => 'Create/Edit System Users',
                'uac.roles.view' => 'View Roles & Permissions',
                'uac.roles.manage' => 'Create/Edit Roles & Permissions',
            ],
            'System Management' => [
                'settings.approval.view' => 'View Approval Workflows',
                'settings.approval.manage' => 'Manage Approval Workflows',
                'settings.lists.manage' => 'Manage System Lists',
                'settings.currencies.manage' => 'Manage Currencies',
                'settings.calendar.manage' => 'Manage Calendar & Working Days',
            ],
            'Data & Branding' => [
                'settings.branding.view' => 'View Branding Settings',
                'settings.branding.manage' => 'Manage Logo & Themes',
                'settings.backup.view' => 'View Backup Records',
                'settings.backup.manage' => 'Perform System Backups',
            ],
            'Activity Logs' => [
                'logs.view' => 'View System Activity Logs',
                'logs.export' => 'Export Activity Logs',
            ],
        ];

        // Create a flat map for easy lookup
        $this->permissionsMap = [];
        foreach ($this->permissionGroups as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                $this->permissionsMap[$name] = $label;
                Permission::findOrCreate($name, 'web');
            }
        }
    }

    public $permissionsMap = [];

    public function openAddModal()
    {
        $this->authorize('uac.roles.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $this->authorize('uac.roles.manage');
        $role = Role::findOrFail($id);
        
        // Prevent editing system roles if needed, but for now let's follow requirements
        if ($role->name === 'company-admin' || $role->name === 'saas-admin') {
             // Maybe restrict some actions here
        }

        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->description = ''; // Spatie roles don't have description by default, might need migration or custom logic
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        
        $this->showModal = true;
    }

    public function save()
    {
        $this->authorize('uac.roles.manage');
        $this->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $this->editingId,
            'selectedPermissions' => 'array',
        ]);

        try {
            DB::transaction(function () {
                if ($this->editingId) {
                    $role = Role::findOrFail($this->editingId);
                    $role->update(['name' => $this->name]);
                } else {
                    $role = Role::create(['name' => $this->name, 'guard_name' => 'web']);
                }

                $role->syncPermissions($this->selectedPermissions);
            });

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => $this->editingId ? tr('Role updated successfully') : tr('Role created successfully')
            ]);
            
            $this->showModal = false;
            $this->resetForm();
            
        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => tr('Error: Could not save role.') . ' ' . $e->getMessage()
            ]);
        }
    }

    public function deleteRole($id)
    {
        $this->authorize('uac.roles.manage');
        $role = Role::findOrFail($id);
        
        if ($role->name === 'company-admin' || $role->name === 'saas-admin') {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => tr('System roles cannot be deleted.')
            ]);
            return;
        }

        // Check if users are attached (Only for THIS company)
        $usersCount = $role->users()->where('saas_company_id', auth()->user()->saas_company_id)->count();
        if ($usersCount > 0) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => str_replace(':count', $usersCount, tr('Cannot delete role. It is assigned to :count users.'))
            ]);
            return;
        }

        $role->delete();
        $this->dispatch('toast', [
            'type' => 'success',
            'message' => tr('Role deleted successfully')
        ]);
    }

    public function copyRole($id)
    {
        $this->authorize('uac.roles.manage');
        $role = Role::findOrFail($id);
        $this->name = $role->name . ' (Copy)';
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function resetForm()
    {
        $this->name = '';
        $this->description = '';
        $this->selectedPermissions = [];
        $this->editingId = null;
    }

    public function toggleGroup($group)
    {
        $groupPermissions = array_keys($this->permissionGroups[$group]);
        $allSelected = true;
        foreach ($groupPermissions as $p) {
            if (!in_array($p, $this->selectedPermissions)) {
                $allSelected = false;
                break;
            }
        }

        if ($allSelected) {
            $this->selectedPermissions = array_diff($this->selectedPermissions, $groupPermissions);
        } else {
            $this->selectedPermissions = array_unique(array_merge($this->selectedPermissions, $groupPermissions));
        }
    }

    public function render()
    {
         $companyId = $this->companyId();
                $branchId = $this->effectiveBranchId();

                $roles = Role::where('name', '!=', 'saas-admin')
                    ->when($this->search, function($q) {
                        $q->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->withCount(['users' => function($query) use ($companyId, $branchId) {
                        $query->where('saas_company_id', $companyId);

                        if ($branchId && $this->employeeBranchCol) {
                            $query->whereHas('employee', function ($eq) use ($branchId) {
                                $eq->where($this->employeeBranchCol, $branchId);
                            });
                        }
                    }])
                    ->paginate(10);

        return view('systemsettings::livewire.user-access-control.roles', [
            'roles' => $roles
        ]);
    }
}





