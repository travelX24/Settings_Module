<?php

namespace Athka\SystemSettings\Livewire\UserAccessControl;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class Roles extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editingId = null;

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
        $this->loadPermissionGroups();
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
                'employees.view' => 'View Employees List',
                'employees.create' => 'Add New Employee',
                'employees.edit' => 'Edit Employee Details',
                'employees.delete' => 'Delete Employee',
                'employees.export' => 'Export Employee Data',
                'employees.documents.manage' => 'Manage Employee Documents',
            ],
            'Attendance & Shifts' => [
                'attendance.view' => 'View Daily Attendance',
                'attendance.manage' => 'Manage Manual Entry & Corrections',
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
                'settings.attendance.manage' => 'Manage Shifts & Rules',
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
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
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
                'message' => tr('Cannot delete role. It is assigned to :count users.', ['count' => $usersCount])
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
        $roles = Role::where('name', '!=', 'saas-admin')
            ->when($this->search, function($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            })
            ->withCount(['users' => function($query) {
                $query->where('saas_company_id', auth()->user()->saas_company_id);
            }])
            ->paginate(10);

        return view('systemsettings::livewire.user-access-control.roles', [
            'roles' => $roles
        ]);
    }
}





