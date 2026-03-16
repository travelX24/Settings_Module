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
                'dashboard.view' => tr('View Dashboard Statistics'),
                'dashboard.reports' => tr('Access Reports Dashboard'),
            ],
            'Employee Management' => [
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
            'Attendance & Timesheets' => [
                'attendance.dashboard.view' => tr('View Attendance Dashboard'),
                'attendance.daily.view' => tr('View Daily Attendance (All)'),
                'attendance.daily.view-subordinates' => tr('View Subordinates Attendance'),
                'attendance.daily.manage' => tr('Manage Daily Attendance Record'),
                'attendance.daily.manual-entry' => tr('Add Manual Attendance Entry'),
                'attendance.logs.view' => tr('View Attendance Logs'),
                'attendance.logs.sync' => tr('Sync Fingerprint Devices Data'),
                'attendance.schedules.view' => tr('View Work Schedules'),
                'attendance.schedules.view-subordinates' => tr('View Subordinates Schedules'),
                'attendance.schedules.manage' => tr('Manage Work Schedules'),
                'attendance.schedules.assign' => tr('Assign Schedules'),
                'attendance.schedules.bulk-assign' => tr('Bulk Assign Schedules'),
                'shifts.view' => tr('View Shifts'),
                'shifts.manage' => tr('Manage Shifts & Rotations'),
                'holidays.manage' => tr('Manage Public Holidays'),
            ],
            'Requests & Approvals' => [
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
            ],
            'Penalties & Discipline' => [
                'attendance.penalties.view' => tr('View Attendance Penalties'),
                'attendance.penalties.view-subordinates' => tr('View Subordinates Penalties'),
                'attendance.penalties.manage' => tr('Process Attendance Penalties'),
                'attendance.penalties.waive' => tr('Waive/Cancel Penalties'),
            ],
            'Payroll & Finance' => [
                'payroll.view' => tr('View Payroll Data'),
                'payroll.process' => tr('Process / Close Monthly Payroll'),
                'payroll.payslips.view' => tr('View/Download Payslips'),
                'payroll.allowances.manage' => tr('Manage Allowances & Deductions'),
                'payroll.loans.manage' => tr('Manage Employee Loans'),
                'payroll.bonuses.manage' => tr('Manage Bonuses & Incentives'),
                'payroll.bank-files.export' => tr('Generate Bank Transfer Files'),
            ],
            'Recruitment & Performance' => [
                'recruitment.jobs.manage' => tr('Manage Job Postings'),
                'recruitment.applicants.view' => tr('View Applicants List'),
                'recruitment.applicants.manage' => tr('Manage Recruitment Stages'),
                'recruitment.interviews.manage' => tr('Manage Interviewing Process'),
                'performance.evaluations.view' => tr('View Performance Results'),
                'performance.evaluations.manage' => tr('Manage Employee Evaluations'),
                'performance.kpi.manage' => tr('Manage KPI Definitions'),
            ],
            'Assets Management' => [
                'assets.view' => tr('View Assets Inventory'),
                'assets.manage' => tr('Manage Asset Records'),
                'assets.assignment.manage' => tr('Manage Assets Assignment'),
            ],
            'Locations & Geofencing' => [
                'locations.view' => tr('View Work Locations'),
                'locations.manage' => tr('Manage Work Locations'),
                'geofencing.manage' => tr('Manage Geofencing Rules'),
            ],
            'Logs & Auditing' => [
                'logs.view' => tr('View System Activity Logs'),
                'logs.export' => tr('Export Activity Logs'),
            ],
            'System Settings' => [
                'settings.general.view' => tr('View General Settings'),
                'settings.general.edit' => tr('Edit General Settings'),
                'settings.general.manage' => tr('Manage Overall Settings'),
                'settings.organizational.view' => tr('View Organizational Structure'),
                'settings.organizational.manage' => tr('Manage Departments & Job Titles'),
                'settings.attendance.view' => tr('View Attendance Rules'),
                'settings.attendance.manage' => tr('Manage Attendance Settings'),
                'settings.attendance.schedules.manage' => tr('Manage Master Schedules Settings'),
                'settings.attendance.leaves.manage' => tr('Manage Global Leave Policies'),
                'settings.attendance.holidays.manage' => tr('Manage Global Holiday Policies'),
                'settings.attendance.exceptional.manage' => tr('Manage Exceptional Attendance Rules'),
                'settings.payroll.manage' => tr('Manage Payroll Settings'),
                'settings.branches.manage' => tr('Manage Company Branches'),
                'settings.branding.view' => tr('View Branding Settings'),
                'settings.branding.manage' => tr('Manage System Branding'),
                'settings.approval.view' => tr('View Approval Workflows'),
                'settings.approval.manage' => tr('Manage Approval Workflows'),
                'settings.calendar.manage' => tr('Manage System Calendar'),
                'settings.currencies.manage' => tr('Manage Currencies'),
                'settings.backup.view' => tr('View System Backups'),
                'settings.backup.manage' => tr('Manage Multi-Backups'),
                'settings.lists.manage' => tr('Manage Dynamic Selection Lists'),
            ],
            'User Access Control' => [
                'uac.users.view' => tr('View System Users'),
                'uac.users.manage' => tr('Manage System Users'),
                'uac.roles.view' => tr('View Roles & Groups'),
                'uac.roles.manage' => tr('Manage Roles & Permissions'),
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
}
