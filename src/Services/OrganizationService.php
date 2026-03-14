<?php

namespace Athka\SystemSettings\Services;

use Athka\SystemSettings\Models\Department;
use Athka\SystemSettings\Models\JobTitle;
use Athka\Employees\Models\Employee;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    /**
     * Get department stats for a company.
     */
    public function getDepartmentStats(int $companyId): array
    {
        $total = Department::forCompany($companyId)->count();
        $active = Department::forCompany($companyId)->active()->count();
        $root = Department::forCompany($companyId)->root()->count();

        $distribution = Department::forCompany($companyId)
            ->withCount('employees')
            ->orderBy('employees_count', 'desc')
            ->limit(3)
            ->get()
            ->map(fn($d) => ['name' => $d->name, 'count' => $d->employees_count])
            ->toArray();

        return [
            'total' => $total, 'active' => $active, 'inactive' => $total - $active,
            'root' => $root, 'sub' => $total - $root, 'distribution' => $distribution,
        ];
    }

    /**
     * Get Job Title stats.
     */
    public function getJobTitleStats(int $companyId): array
    {
        $total = JobTitle::forCompany($companyId)->count();
        $active = JobTitle::forCompany($companyId)->active()->count();
        $distribution = JobTitle::forCompany($companyId)
            ->withCount('employees')
            ->orderBy('employees_count', 'desc')
            ->limit(3)->get()
            ->map(fn($jt) => ['name' => $jt->name, 'count' => $jt->employees_count])->toArray();

        return [
            'total' => $total, 'active' => $active, 'inactive' => $total - $active,
            'distribution' => $distribution,
        ];
    }

    /**
     * Save/Update Job Title.
     */
    public function saveJobTitle(int $companyId, array $data, ?int $id = null): JobTitle
    {
        $jt = $id ? JobTitle::findOrFail($id) : new JobTitle();
        $jt->fill(array_merge($data, ['saas_company_id' => $companyId]));
        $jt->save();
        return $jt;
    }

    /**
     * Helper methods for Departments...
     */
    public function getAllDescendantIds(int $deptId, int $companyId): array
    {
        $ids = [];
        $children = Department::forCompany($companyId)->where('parent_id', $deptId)->pluck('id')->toArray();
        foreach ($children as $childId) {
            $ids[] = $childId;
            $ids = array_merge($ids, $this->getAllDescendantIds($childId, $companyId));
        }
        return $ids;
    }

    public function getCumulativeEmployeeCount(int $deptId, int $companyId): int
    {
        $allIds = array_merge([$deptId], $this->getAllDescendantIds($deptId, $companyId));
        $empIds = Employee::whereIn('department_id', $allIds)->pluck('id')->toArray();
        $managers = Department::whereIn('id', $allIds)->whereNotNull('manager_id')->pluck('manager_id')->unique()->toArray();
        return count(array_unique(array_merge($empIds, $managers)));
    }

    public function saveDepartment(int $companyId, array $data, ?int $id = null): Department
    {
        $dept = $id ? Department::findOrFail($id) : new Department();
        $dept->fill(array_merge($data, ['saas_company_id' => $companyId]));
        $dept->save();
        return $dept;
    }

    public function getManagersList(int $companyId, string $locale = 'ar'): array
    {
        $isArabic = str_starts_with($locale, 'ar');
        return Employee::where('saas_company_id', $companyId)
            ->select('id', 'name_ar', 'name_en', 'employee_no')->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'name' => $isArabic ? ($e->name_ar ?: $e->name_en) : ($e->name_en ?: $e->name_ar),
                'employee_no' => $e->employee_no
            ])->toArray();
    }
}
