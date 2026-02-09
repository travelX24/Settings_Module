<?php

namespace Athka\SystemSettings\Services\Approvals;

use App\Models\User;
use Athka\Employees\Models\Employee;
use Athka\SystemSettings\Models\ApprovalPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalApproverResolver
{
    /**
     * يرجّع قائمة User IDs للموافقين بالترتيب حسب policy steps.
     * - direct_manager: يجيب مدير الموظف (employees.manager_id) وإذا فاضي fallback إلى departments.manager_id
     * - user: approver_id مخزّن عندك كـ employee_id ➜ نحوله لـ user_id عبر users.employee_id
     */
    public function resolveApproverUserIds(ApprovalPolicy $policy, User $requesterUser): array
    {
        $companyId = (int) $policy->company_id;

        $requesterEmployee = $requesterUser->employee;
        if (!$requesterEmployee || !($requesterEmployee instanceof Employee)) {
            return [];
        }

        $cursorEmployee = $requesterEmployee;

        $result = [];

        foreach ($policy->steps()->orderBy('position')->get() as $step) {
            $type = strtolower((string) ($step->approver_type ?? 'direct_manager'));

            if (str_starts_with($type, 'direct')) {
                $type = 'direct_manager';
            }

            if ($type === 'direct_manager') {
                $managerUserId = $this->resolveDirectManagerUserId($cursorEmployee, $companyId);

                if ($managerUserId) {
                    $result[] = (int) $managerUserId;

                    // escalation: لو فيه خطوة direct_manager ثانية، تصير فوق المدير الحالي
                    $managerUser = User::query()
                        ->where('saas_company_id', $companyId)
                        ->find($managerUserId);

                    if ($managerUser && $managerUser->employee instanceof Employee) {
                        $cursorEmployee = $managerUser->employee;
                    }
                }

                continue;
            }

            if ($type === 'user') {
                // عندك approver_id مخزّن Employee ID
                $approverEmployeeId = (int) ($step->approver_id ?? 0);
                if ($approverEmployeeId > 0) {
                    $uid = $this->userIdFromEmployeeId($approverEmployeeId, $companyId);
                    if ($uid) $result[] = (int) $uid;
                }

                continue;
            }
        }

        // unique مع الحفاظ على الترتيب
        $unique = [];
        foreach ($result as $id) {
            if (!in_array($id, $unique, true)) $unique[] = $id;
        }

        return $unique;
    }

    private function resolveDirectManagerUserId(Employee $employee, int $companyId): ?int
    {
        // 1) manager from employees.manager_id
        $mid = (int) ($employee->manager_id ?? 0);
        if ($mid > 0) {
            // غالباً employee id
            $managerEmployee = Employee::query()->where('saas_company_id', $companyId)->find($mid);
            if ($managerEmployee) {
                return $this->userIdFromEmployeeId((int) $managerEmployee->id, $companyId);
            }

            // fallback لو كان manager_id مخزّن كـ user id
            $managerUser = User::query()->where('saas_company_id', $companyId)->find($mid);
            if ($managerUser) {
                return (int) $managerUser->id;
            }
        }

        // 2) fallback: department manager from departments.manager_id
        $deptId = (int) ($employee->department_id ?? 0);
        if ($deptId <= 0) return null;

        if (!Schema::hasTable('departments') || !Schema::hasColumn('departments', 'manager_id')) {
            return null;
        }

        // ملاحظة: من صورك departments فيها saas_company_id
        $deptManagerId = (int) DB::table('departments')
            ->where('saas_company_id', $companyId)
            ->where('id', $deptId)
            ->value('manager_id');

        if ($deptManagerId <= 0) return null;

        // غالباً employee id
        $deptManagerEmployee = Employee::query()->where('saas_company_id', $companyId)->find($deptManagerId);
        if ($deptManagerEmployee) {
            return $this->userIdFromEmployeeId((int) $deptManagerEmployee->id, $companyId);
        }

        // fallback لو كان user id
        $deptManagerUser = User::query()->where('saas_company_id', $companyId)->find($deptManagerId);
        if ($deptManagerUser) {
            return (int) $deptManagerUser->id;
        }

        return null;
    }

    private function userIdFromEmployeeId(int $employeeId, int $companyId): ?int
    {
        return User::query()
            ->where('saas_company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->value('id');
    }
}
