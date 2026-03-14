<?php

namespace Athka\SystemSettings\Services;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class AccessControlService
{
    /**
     * Get permission groups for the system.
     */
    public function getPermissionGroups(): array
    {
        return [
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
            ],
            'Attendance & Shifts' => [
                'attendance.daily.view' => 'View Daily Attendance (All)',
                'attendance.daily.manage' => 'Manage Daily Attendance',
                'attendance.penalties.manage' => 'Manage Penalties',
                'attendance.leaves.approve' => 'Approve/Reject Requests',
                'attendance.schedules.manage' => 'Manage Schedules Assignment',
                'shifts.manage' => 'Manage Shifts & Rotation Rules',
                'holidays.manage' => 'Manage Official Holidays',
            ],
            'System Settings' => [
                'settings.general.manage' => 'Manage General Settings',
                'settings.organizational.manage' => 'Manage Departments & Job Titles',
                'settings.attendance.manage' => 'Manage Attendance Rules',
            ],
            'User Access Control' => [
                'uac.users.manage' => 'Create/Edit System Users',
                'uac.roles.manage' => 'Create/Edit Roles & Permissions',
            ],
        ];
    }

    /**
     * Ensure all permissions exist in the database.
     */
    public function syncPermissionDefinitions(): void
    {
        foreach ($this->getPermissionGroups() as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                Permission::findOrCreate($name, 'web');
            }
        }
    }

    /**
     * Save/Update Role.
     */
    public function saveRole(array $data, ?int $id = null): Role
    {
        return DB::transaction(function () use ($data, $id) {
            $role = $id ? Role::findOrFail($id) : Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            
            if ($id) {
                $role->update(['name' => $data['name']]);
            }

            if (isset($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            return $role;
        });
    }

    /**
     * Delete Role with safety checks.
     */
    public function deleteRole(int $id, int $companyId): array
    {
        $role = Role::findOrFail($id);
        
        if (in_array($role->name, ['company-admin', 'saas-admin'])) {
            return ['ok' => false, 'message' => tr('System roles cannot be deleted.')];
        }

        $usersCount = $role->users()->where('saas_company_id', $companyId)->count();
        if ($usersCount > 0) {
            return ['ok' => false, 'message' => str_replace(':count', $usersCount, tr('Cannot delete role. It is assigned to :count users.'))];
        }

        $role->delete();
        return ['ok' => true];
    }

    /**
     * Save/Update User.
     */
    public function saveUser(int $companyId, array $data, ?int $id = null): User
    {
        return DB::transaction(function () use ($companyId, $data, $id) {
            $user = $id ? User::findOrFail($id) : new User();
            
            $userData = array_intersect_key($data, array_flip([
                'name', 'email', 'employee_id', 'access_scope', 'access_type', 'is_active'
            ]));

            if (!$id && isset($data['password'])) {
                $userData['password'] = $data['password'];
            }
            
            $userData['saas_company_id'] = $companyId;
            $user->fill($userData);
            $user->save();

            // Sync Role
            if (isset($data['role'])) {
                if ($data['access_type'] === 'hr_app_only') {
                    $user->syncRoles([]);
                } else {
                    $user->syncRoles([$data['role']]);
                }
            }

            return $user;
        });
    }

    /**
     * Get shared branch metadata.
     */
    public function getBranchMetadata(int $companyId): array
    {
        $employeeBranchCol = (Schema::hasTable('employees') && Schema::hasColumn('employees', 'branch_id')) ? 'branch_id' : null;
        
        $branches = [];
        if (Schema::hasTable('branches')) {
            $branches = DB::table('branches')
                ->where('saas_company_id', $companyId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->toArray();
        }

        return [
            'col' => $employeeBranchCol,
            'list' => $branches
        ];
    }
}
