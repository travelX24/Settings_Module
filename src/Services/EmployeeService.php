<?php

namespace Athka\SystemSettings\Services;

use Athka\Employees\Models\Employee;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class EmployeeService
{
    /**
     * Resolve the employee associated with a user.
     */
    public function resolve($user = null): ?Employee
    {
        $user = $user ?: Auth::user();
        if (!$user) return null;

        $employee = null;

        // 1. Check if user has explicit employee_id
        if (property_exists($user, 'employee_id') && (int) ($user->employee_id ?? 0) > 0) {
            $employee = Employee::find((int) $user->employee_id);
        }

        // 2. Try relationship
        if (!$employee && method_exists($user, 'employee')) {
            $employee = $user->employee;
        }

        // 3. Try lookup by user_id in employees table
        if (!$employee && Schema::hasTable('employees') && Schema::hasColumn('employees', 'user_id')) {
            $employee = Employee::where('user_id', (int) $user->id)->first();
        }

        return $employee;
    }

    /**
     * Get company ID from user or context.
     */
    public function getCompanyId($user = null): int
    {
        $user = $user ?: Auth::user();
        if (!$user) return 0;

        return (int) ($user->saas_company_id ?? $user->company_id ?? 0);
    }
}
