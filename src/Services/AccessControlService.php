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
                'dashboard.view' => tr('View Dashboard Statistics'),
                'dashboard.reports' => tr('Access Reports Dashboard'),
            ],
            'General Settings' => [
                'settings.general.view' => tr('View General Settings'),
                'settings.general.edit' => tr('Edit General Settings'),
                'settings.general.manage' => tr('Manage Overall Settings'),
                'settings.branches.manage' => tr('Manage Company Branches'),
                'settings.lists.manage' => tr('Manage Dynamic Selection Lists'),
            ],
            'Organizational Structure' => [
                'settings.organizational.view' => tr('View Organizational Structure'),
                'settings.organizational.manage' => tr('Manage Departments & Job Titles'),
            ],
            'User Access Control' => [
                'uac.users.view' => tr('View System Users'),
                'uac.users.manage' => tr('Manage System Users'),
                'uac.roles.view' => tr('View Roles & Groups'),
                'uac.roles.manage' => tr('Manage Roles & Permissions'),
            ],
            'Approval Workflows' => [
                'settings.approval.view' => tr('View Approval Workflows'),
                'settings.approval.manage' => tr('Manage Approval Workflows'),
            ],
            'System Logs & Backup' => [
                'logs.view' => tr('View System Activity Logs'),
                'logs.export' => tr('Export Activity Logs'),
                'settings.backup.view' => tr('View System Backups'),
                'settings.backup.manage' => tr('Manage Multi-Backups'),
                'settings.branding.view' => tr('View Branding Settings'),
                'settings.branding.manage' => tr('Manage System Branding'),
            ],
            'Currency & Calendar' => [
                'settings.calendar.manage' => tr('Manage System Calendar'),
                'settings.currencies.manage' => tr('Manage Currencies'),
            ],

            // --- HR Modules ---
            'Employee Master Data' => [
                'employees.view' => tr('View Employees List'),
                'employees.view-details' => tr('View Employee Full Profile'),
                'employees.create' => tr('Add New Employee'),
                'employees.edit' => tr('Edit Employee Details'),
                'employees.delete' => tr('Delete / Archive Employee'),
                'employees.status.manage' => tr('Manage Employment Status'),
                'employees.contracts.manage' => tr('Manage Employee Contracts'),
                'employees.documents.manage' => tr('Manage Employee Documents'),
                'employees.export' => tr('Export Employee Data'),
                'employees.import' => tr('Import Employee Data'),
            ],

            // --- Operations ---
            'Daily Attendance' => [
                'attendance.dashboard.view' => tr('View Attendance Dashboard'),
                'attendance.daily.view' => tr('View Daily Attendance (All)'),
                'attendance.daily.view-subordinates' => tr('View Subordinates Attendance'),
                'attendance.daily.manage' => tr('Manage Daily Attendance Record'),
                'attendance.daily.manual-entry' => tr('Add Manual Attendance Entry'),
                'attendance.logs.view' => tr('View Attendance Logs'),
                'attendance.logs.sync' => tr('Sync Fingerprint Devices Data'),
            ],
            'Work Schedules' => [
                'attendance.schedules.view' => tr('View Work Schedules'),
                'attendance.schedules.view-subordinates' => tr('View Subordinates Schedules'),
                'attendance.schedules.manage' => tr('Manage Work Schedules'),
                'attendance.schedules.assign' => tr('Assign Schedules'),
                'attendance.schedules.bulk-assign' => tr('Bulk Assign Schedules'),
                'shifts.view' => tr('View Shifts'),
                'shifts.manage' => tr('Manage Shifts & Rotations'),
                'holidays.manage' => tr('Manage Public Holidays'),
                'settings.attendance.view' => tr('View Global Attendance Rules'),
                'settings.attendance.manage' => tr('Manage Global Attendance Policies'),
                'settings.attendance.schedules.manage' => tr('Manage Master Schedules Settings'),
                'settings.attendance.holidays.manage' => tr('Manage Global Holiday Policies'),
                'settings.attendance.exceptional.manage' => tr('Manage Exceptional Attendance Rules'),
            ],
            'Requests Management' => [
                'requests.leaves.view' => tr('View Leave Requests'),
                'requests.leaves.create' => tr('Submit Leave Request'),
                'requests.leaves.approve' => tr('Approve/Reject Leave Requests'),
                'attendance.leaves.view' => tr('View Leave Balance & History'),
                'attendance.leaves.view-subordinates' => tr('View Subordinates Leave Balances'),
                'attendance.leaves.manage' => tr('Manage Leaves Manually'),
                'attendance.leaves.approve' => tr('Approve Leave Requests (Old Path)'),
                'requests.permissions.view' => tr('View Short Permission Requests'),
                'requests.permissions.manage' => tr('Manage Permission Requests'),
                'requests.overtime.view' => tr('View Overtime Requests'),
                'requests.overtime.manage' => tr('Manage Overtime Requests'),
                'requests.business-trip.manage' => tr('Manage Business Trip Requests'),
                'attendance.missions.manage' => tr('Manage Work Missions'),
                'settings.attendance.leaves.manage' => tr('Manage Global Leave Policies'),
            ],
            'Discipline & Penalties' => [
                'attendance.penalties.view' => tr('View Attendance Penalties'),
                'attendance.penalties.view-subordinates' => tr('View Subordinates Penalties'),
                'attendance.penalties.manage' => tr('Process Attendance Penalties'),
                'attendance.penalties.waive' => tr('Waive/Cancel Penalties'),
            ],

            // --- Locations ---
            'Geographic Management' => [
                'locations.view' => tr('View Work Locations'),
                'locations.manage' => tr('Manage Work Locations'),
                'geofencing.manage' => tr('Manage Geofencing Rules'),
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
                
                // 🛑 Block editing protected system roles
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
                $user->allowedBranches()->sync($data['allowed_branch_ids']);
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
            // Remove role assignment — only direct permissions are now authoritative
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
            'locations' => [
                'label' => tr('Locations'),
                'icon' => 'fa-map-marker-alt',
                'groups' => [
                    'Geographic Management' => $groups['Geographic Management'],
                ]
            ],
        ];
    }
}
