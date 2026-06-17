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
            // --- Core System ---
            'Dashboard' => [
                'dashboard.view' => 'View Dashboard Statistics',
                'dashboard.reports' => 'Access Reports Dashboard',
            ],
            'General Settings' => [
                'settings.general.view' => 'View General Settings',
                'settings.general.edit' => 'Edit General Settings',
                'settings.general.manage' => 'Manage Overall Settings',
                'settings.branches.manage' => 'Manage Company Branches',
                'settings.lists.manage' => 'Manage Dynamic Selection Lists',
            ],
            'Organizational Structure' => [
                'settings.organizational.view' => 'View Organizational Structure',
                'settings.organizational.manage' => 'Manage Departments & Job Titles',
            ],
            'User Access Control' => [
                'uac.users.view' => 'View System Users',
                'uac.users.manage' => 'Manage System Users',
                'uac.roles.view' => 'View Roles & Groups',
                'uac.roles.manage' => 'Manage Roles & Permissions',
            ],
            'Approval Workflows' => [
                'settings.approval.view' => 'View Approval Workflows',
                'settings.approval.manage' => 'Manage Approval Workflows',
            ],
            'System Logs & Backup' => [
                'logs.view' => 'View System Activity Logs',
                'logs.export' => 'Export Activity Logs',
                'settings.backup.view' => 'View System Backups',
                'settings.backup.manage' => 'Manage Multi-Backups',
                'settings.branding.view' => 'View Branding Settings',
                'settings.branding.manage' => 'Manage System Branding',
            ],
            'Currency & Calendar' => [
                'settings.calendar.manage' => 'Manage System Calendar',
                'settings.currencies.manage' => 'Manage Currencies',
            ],

            // --- HR Modules ---
            'Employee Master Data' => [
                'employees.view' => 'View Employees List',
                'employees.view-details' => 'View Employee Full Profile',
                'employees.create' => 'Add New Employee',
                'employees.edit' => 'Edit Employee Details',
                'employees.delete' => 'Delete / Archive Employee',
                'employees.status.manage' => 'Manage Employment Status',
                'employees.contracts.manage' => 'Manage Employee Contracts',
                'employees.documents.manage' => 'Manage Employee Documents',
                'employees.export' => 'Export Employee Data',
                'employees.import' => 'Import Employee Data',
            ],

            // --- Operations ---
            'Daily Attendance' => [
                'attendance.dashboard.view' => 'View Attendance Dashboard',
                'attendance.daily.view' => 'View Daily Attendance (All)',
                'attendance.daily.view-subordinates' => 'View Subordinates Attendance',
                'attendance.daily.manage' => 'Manage Daily Attendance Record',
                'attendance.daily.manual-entry' => 'Add Manual Attendance Entry',
                'attendance.logs.view' => 'View Attendance Logs',
                'attendance.logs.sync' => 'Sync Fingerprint Devices Data',
            ],
            'Work Schedules' => [
                'attendance.schedules.view' => 'View Work Schedules',
                'attendance.schedules.view-subordinates' => 'View Subordinates Schedules',
                'attendance.schedules.manage' => 'Manage Work Schedules',
                'attendance.schedules.assign' => 'Assign Schedules',
                'attendance.schedules.bulk-assign' => 'Bulk Assign Schedules',
                'shifts.view' => 'View Shifts',
                'shifts.manage' => 'Manage Shifts & Rotations',
                'holidays.manage' => 'Manage Public Holidays',
                'settings.attendance.view' => 'View Global Attendance Rules',
                'settings.attendance.manage' => 'Manage Global Attendance Policies',
                'settings.attendance.schedules.manage' => 'Manage Master Schedules Settings',
                'settings.attendance.holidays.manage' => 'Manage Global Holiday Policies',
                'settings.attendance.exceptional.manage' => 'Manage Exceptional Attendance Rules',
            ],
            'Requests Management' => [
                'requests.leaves.view' => 'View Leave Requests',
                'requests.leaves.create' => 'Submit Leave Request',
                'requests.leaves.approve' => 'Approve/Reject Leave Requests',
                'attendance.leaves.view' => 'View Leave Balance & History',
                'attendance.leaves.view-subordinates' => 'View Subordinates Leave Balances',
                'attendance.leaves.manage' => 'Manage Leaves Manually',
                'attendance.leaves.approve' => 'Approve Leave Requests (Old Path)',
                'requests.permissions.view' => 'View Short Permission Requests',
                'requests.permissions.manage' => 'Manage Permission Requests',
                'requests.overtime.view' => 'View Overtime Requests',
                'requests.overtime.manage' => 'Manage Overtime Requests',
                'requests.business-trip.manage' => 'Manage Business Trip Requests',
                'attendance.missions.manage' => 'Manage Work Missions',
                'settings.attendance.leaves.manage' => 'Manage Global Leave Policies',
            ],
            'Discipline & Penalties' => [
                'attendance.penalties.view' => 'View Attendance Penalties',
                'attendance.penalties.view-subordinates' => 'View Subordinates Penalties',
                'attendance.penalties.manage' => 'Process Attendance Penalties',
                'attendance.penalties.waive' => 'Waive/Cancel Penalties',
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
    public function saveRole(array $data, ?int $id = null, ?int $companyId = null): Role
    {
        return DB::transaction(function () use ($data, $id, $companyId) {
            if ($id) {
                $role = Role::findOrFail($id);
                
                // ðŸ›‘ Block editing protected system roles
                if (in_array($role->name, ['company-admin', 'saas-admin', 'super-admin', 'system-admin']) || is_null($role->saas_company_id)) {
                    throw new \Exception(tr('System roles cannot be edited.'));
                }

                $role->name = $data['name'];
                $role->save();
            } else {
                $role = new Role(['name' => $data['name'], 'guard_name' => 'web']);
                $role->saas_company_id = $companyId;
                $role->save();
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

        if ($role->saas_company_id !== null && $role->saas_company_id !== $companyId) {
            return ['ok' => false, 'message' => tr('Unauthorized operation.')];
        }
        
        if (in_array($role->name, ['company-admin', 'saas-admin', 'super-admin', 'system-admin']) || $role->saas_company_id === null) {
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

            // Sync Selected Branches
            if ($data['access_scope'] === 'selected_branches' && isset($data['allowed_branch_ids'])) {
                $syncData = [];
                foreach ($data['allowed_branch_ids'] as $branchId) {
                    $syncData[$branchId] = ['saas_company_id' => $companyId];
                }
                $user->allowedBranches()->sync($syncData);
            } else {
                $user->allowedBranches()->sync([]);
            }

            return $user;
        });
    }

    /**
     * Save custom permissions for a specific user (overrides their role).
     * Detaches the role, assigns direct permissions, and stores the reference role name.
     */
    public function saveCustomPermissions(User $user, string $referencRoleName, array $permissions): void
    {
        DB::transaction(function () use ($user, $referencRoleName, $permissions) {
            // Remove role assignment â€” only direct permissions are now authoritative
            $user->syncRoles([]);
            // Assign direct permissions
            $user->syncPermissions($permissions);
            // Store reference so we can reset later
            $user->update([
                'reference_role' => $referencRoleName,
                'has_custom_permissions' => true,
            ]);
        });
    }

    /**
     * Reset a user's permissions back to the defaults of their reference role.
     */
    public function resetToRoleDefault(User $user): array
    {
        $roleName = $user->reference_role;

        if (!$roleName) {
            return ['ok' => false, 'message' => tr('No reference role found for this user.')];
        }

        $role = Role::where('name', $roleName)->first();

        if (!$role) {
            return ['ok' => false, 'message' => tr('The reference role no longer exists.')];
        }

        DB::transaction(function () use ($user, $role) {
            // Remove direct permissions
            $user->syncPermissions([]);
            // Restore the role
            $user->syncRoles([$role->name]);
            // Clear custom flag
            $user->update([
                'has_custom_permissions' => false,
            ]);
        });

        return ['ok' => true];
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
                ->map(fn($item) => (array)$item)
                ->toArray();
        }

        return [
            'col' => $employeeBranchCol,
            'list' => $branches
        ];
    }

    public function getPermissionTabs(): array
    {
        $groups = $this->getPermissionGroups();
        
        return [
            'core' => [
                'label' => tr('Core Settings'),
                'icon' => 'fa-cog',
                'groups' => [
                    'Dashboard' => $groups['Dashboard'],
                    'General Settings' => $groups['General Settings'],
                    'Organizational Structure' => $groups['Organizational Structure'],
                    'User Access Control' => $groups['User Access Control'],
                    'Approval Workflows' => $groups['Approval Workflows'],
                    'System Logs & Backup' => $groups['System Logs & Backup'],
                    'Currency & Calendar' => $groups['Currency & Calendar'],
                ]
            ],
            'hr' => [
                'label' => tr('HR Management'),
                'icon' => 'fa-users',
                'groups' => [
                    'Employee Master Data' => $groups['Employee Master Data'],
                ]
            ],
            'operations' => [
                'label' => tr('Operations'),
                'icon' => 'fa-calendar-check',
                'groups' => [
                    'Daily Attendance' => $groups['Daily Attendance'],
                    'Work Schedules' => $groups['Work Schedules'],
                    'Requests Management' => $groups['Requests Management'],
                    'Discipline & Penalties' => $groups['Discipline & Penalties'],
                ]
            ],

        ];
    }
}

