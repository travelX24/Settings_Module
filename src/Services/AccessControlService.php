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
            ],
            'General Settings' => [
                'settings.general.view' => 'View General Settings',
                'settings.lists.view' => 'View Lists Settings',
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

            // --- Employee Module ---
            'Employees - List & Profile' => [
                'employees.view' => 'View Employees List',
                'employees.view.all' => 'View All Employees',
                'employees.view-details' => 'View Employee Full Profile',
            ],
            'Employees - Management Actions' => [
                'employees.create' => 'Add New Employee',
                'employees.edit' => 'Edit Employee Details',
                'employees.delete' => 'Delete / Archive Employee',
                'employees.status.manage' => 'Manage Employment Status',
            ],
            'Employees - Contracts & Payroll' => [
                'employees.contracts.manage' => 'Manage Employee Contract, Salary, and Leave Balance Adjustments',
            ],
            'Employees - Documents' => [
                'employees.documents.manage' => 'Manage Employee Documents',
            ],
            'Employees - Import & Export' => [
                'employees.export' => 'Export Employee Data',
                'employees.import' => 'Import Employee Data',
            ],

            // --- Operations ---
            'Daily Attendance' => [
                'attendance.daily.view' => 'View Daily Attendance',
                'attendance.daily.view-subordinates' => 'View Subordinates Attendance',
                'attendance.daily.manage' => 'Manage Daily Attendance',
            ],
            'Work Schedules' => [
                'attendance.schedules.view' => 'View Work Schedules',
                'attendance.schedules.view-subordinates' => 'View Subordinates Schedules',
                'attendance.schedules.manage' => 'Manage Work Schedules',
            ],
            'Requests Management' => [
                'attendance.leaves.view' => 'View Leaves & Permissions',
                'attendance.leaves.view-subordinates' => 'View Subordinates Leaves & Permissions',
                'attendance.leaves.manage' => 'Manage Leaves & Permissions',
            ],
            'Discipline & Penalties' => [
                'attendance.penalties.view' => 'View Attendance Penalties',
                'attendance.penalties.view-subordinates' => 'View Subordinates Penalties',
                'attendance.penalties.manage' => 'Manage Attendance Penalties',
            ],
            // --- Attendance Settings ---
            'Attendance Settings - General' => [
                'settings.attendance.view' => 'View General Attendance Settings',
                'settings.attendance.manage' => 'Manage General Attendance Settings',
            ],
            'Attendance Settings - Work Schedules' => [
                'settings.attendance.schedules.view' => 'View Work Schedule Settings',
                'settings.attendance.schedules.manage' => 'Manage Work Schedule Settings',
            ],
            'Attendance Settings - Leave Settings' => [
                'settings.attendance.leaves.view' => 'View Leave Settings',
                'settings.attendance.leaves.manage' => 'Manage Leave Settings',
            ],
            'Attendance Settings - Official Holidays' => [
                'settings.attendance.holidays.view' => 'View Official Holidays',
                'settings.attendance.holidays.manage' => 'Manage Official Holidays',
            ],
            'Attendance Settings - Exceptional Days' => [
                'settings.attendance.exceptional.view' => 'View Exceptional Days',
                'settings.attendance.exceptional.manage' => 'Manage Exceptional Days',
            ],
        ];
    }
    /**
     * Permissions kept for backward compatibility only. They are synced in the
     * database but hidden from the role/user permission picker.
     */
    public function getLegacyPermissionGroups(): array
    {
        return [
            'Attendance Legacy' => [
                'attendance.dashboard.view' => 'View Attendance Dashboard',
                'attendance.daily.manual-entry' => 'Add Manual Attendance Entry',
                'attendance.daily.export' => 'Export Daily Attendance Reports',
                'attendance.logs.view' => 'View Attendance Logs',
                'attendance.logs.sync' => 'Sync Fingerprint Devices Data',
                'attendance.schedules.assign' => 'Assign Schedules',
                'attendance.schedules.bulk-assign' => 'Bulk Assign Schedules',
                'shifts.view' => 'View Shifts',
                'shifts.manage' => 'Manage Shifts & Rotations',
                'holidays.manage' => 'Manage Public Holidays',
                'requests.leaves.view' => 'View Leave Requests',
                'requests.leaves.create' => 'Submit Leave Request',
                'requests.leaves.approve' => 'Approve/Reject Leave Requests',
                'attendance.leaves.approve' => 'Approve Leave Requests (Old Path)',
                'requests.permissions.view' => 'View Short Permission Requests',
                'requests.permissions.manage' => 'Manage Permission Requests',
                'requests.overtime.view' => 'View Overtime Requests',
                'requests.overtime.manage' => 'Manage Overtime Requests',
                'requests.business-trip.manage' => 'Manage Business Trip Requests',
                'attendance.missions.manage' => 'Manage Work Missions',
                'attendance.penalties.waive' => 'Waive/Cancel Penalties',
                'attendance.penalties.export' => 'Export Attendance Penalties',
            ],
        ];
    }

    public function getPermissionLabels(): array
    {
        return collect($this->getPermissionGroups())
            ->merge($this->getLegacyPermissionGroups())
            ->flatMap(fn ($group) => $group)
            ->toArray();
    }

    public function normalizePermissionSelection(array $permissions): array
    {
        $visiblePermissions = collect($this->getPermissionGroups())
            ->flatMap(fn ($group) => array_keys($group))
            ->flip();

        $legacyAliases = [
            'attendance.dashboard.view' => ['attendance.daily.view'],
            'attendance.daily.manual-entry' => ['attendance.daily.manage'],
            'attendance.daily.export' => ['attendance.daily.manage'],
            'attendance.logs.view' => ['attendance.daily.view'],
            'attendance.logs.sync' => ['attendance.daily.manage'],
            'attendance.schedules.assign' => ['attendance.schedules.manage'],
            'attendance.schedules.bulk-assign' => ['attendance.schedules.manage'],
            'shifts.view' => ['attendance.schedules.view'],
            'shifts.manage' => ['attendance.schedules.manage'],
            'holidays.manage' => ['attendance.schedules.manage'],
            'requests.leaves.view' => ['attendance.leaves.view'],
            'requests.leaves.create' => ['attendance.leaves.manage'],
            'requests.leaves.approve' => ['attendance.leaves.manage'],
            'attendance.leaves.approve' => ['attendance.leaves.manage'],
            'requests.permissions.view' => ['attendance.leaves.view'],
            'requests.permissions.manage' => ['attendance.leaves.manage'],
            'requests.overtime.view' => ['attendance.leaves.view'],
            'requests.overtime.manage' => ['attendance.leaves.manage'],
            'requests.business-trip.manage' => ['attendance.leaves.manage'],
            'attendance.missions.manage' => ['attendance.leaves.manage'],
            'attendance.penalties.waive' => ['attendance.penalties.manage'],
            'attendance.penalties.export' => ['attendance.penalties.manage'],
        ];

        $normalized = [];
        foreach ($permissions as $permission) {
            if ($visiblePermissions->has($permission)) {
                $normalized[] = $permission;
                continue;
            }

            foreach ($legacyAliases[$permission] ?? [] as $alias) {
                $normalized[] = $alias;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Ensure all permissions exist in the database.
     */
    public function syncPermissionDefinitions(): void
    {
        foreach (array_merge($this->getPermissionGroups(), $this->getLegacyPermissionGroups()) as $group => $permissions) {
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

                if ($role->saas_company_id !== null && (int) $role->saas_company_id !== (int) $companyId) {
                    throw new \Exception(tr('Unauthorized operation.'));
                }
                
                // Ã°Å¸â€ºâ€˜ Block editing protected system roles
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
                $role->syncPermissions($this->normalizePermissionSelection($data['permissions']));
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

        if ($role->saas_company_id !== null && (int) $role->saas_company_id !== (int) $companyId) {
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
            // Remove role assignment Ã¢â‚¬â€ only direct permissions are now authoritative
            $user->syncRoles([]);
            // Assign direct permissions
            $user->syncPermissions($this->normalizePermissionSelection($permissions));
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
                ],
            ],
            'hr' => [
                'label' => tr('HR Management'),
                'icon' => 'fa-users',
                'groups' => [
                    'Employees - List & Profile' => $groups['Employees - List & Profile'],
                    'Employees - Management Actions' => $groups['Employees - Management Actions'],
                    'Employees - Contracts & Payroll' => $groups['Employees - Contracts & Payroll'],
                    'Employees - Documents' => $groups['Employees - Documents'],
                    'Employees - Import & Export' => $groups['Employees - Import & Export'],
                ],
            ],
            'operations' => [
                'label' => tr('Operations'),
                'icon' => 'fa-calendar-check',
                'groups' => [
                    'Daily Attendance' => $groups['Daily Attendance'],
                    'Work Schedules' => $groups['Work Schedules'],
                    'Requests Management' => $groups['Requests Management'],
                    'Discipline & Penalties' => $groups['Discipline & Penalties'],
                ],
            ],
            'attendance-settings' => [
                'label' => tr('Attendance Settings'),
                'icon' => 'fa-clock',
                'groups' => [
                    'Attendance Settings - General' => $groups['Attendance Settings - General'],
                    'Attendance Settings - Work Schedules' => $groups['Attendance Settings - Work Schedules'],
                    'Attendance Settings - Leave Settings' => $groups['Attendance Settings - Leave Settings'],
                    'Attendance Settings - Official Holidays' => $groups['Attendance Settings - Official Holidays'],
                    'Attendance Settings - Exceptional Days' => $groups['Attendance Settings - Exceptional Days'],
                ],
            ],
        ];
    }
}

