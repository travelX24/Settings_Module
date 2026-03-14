<?php

namespace Athka\SystemSettings\Http\Controllers;

use Athka\SystemSettings\Models\Department;
use Athka\SystemSettings\Models\JobTitle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;

class OrganizationalStructureController extends Controller
{
    public function getEmployeesByDepartment($id)
    {
        $companyId = $this->getCompanyId();
        // Load department and its basic children structure
        $department = Department::forCompany($companyId)->findOrFail($id);

        $isArabic = app()->getLocale() === 'ar';

        // Get all descendant department IDs recursively
        $allDeptIds = $this->getAllDescendantIds($department);
        $allDeptIds[] = $department->id;

        // ✅ موظفين الأقسام (الحالي والأبناء)
        $employees = \Athka\Employees\Models\Employee::whereIn('department_id', $allDeptIds)
            ->with(['jobTitle', 'department'])
            ->select('id', 'name_ar', 'name_en', 'email_work', 'job_title_id', 'department_id')
            ->get()
            ->map(function ($emp) use ($isArabic) {
                return [
                    'id' => $emp->id,
                    'name' => $isArabic ? ($emp->name_ar ?: $emp->name_en) : ($emp->name_en ?: $emp->name_ar),
                    'email' => $emp->email_work,
                    'job_title' => $emp->jobTitle ? $emp->jobTitle->name : '-',
                    'department_name' => $emp->department ? $emp->department->name : '-',
                ];
            });

        // ✅ إضافة المدراء للأقسام المختارة لو مش مضافين ضمن الموظفين
        $managerIds = Department::whereIn('id', $allDeptIds)
            ->whereNotNull('manager_id')
            ->pluck('manager_id')
            ->unique()
            ->toArray();

        if (!empty($managerIds)) {
            $managers = \Athka\Employees\Models\Employee::whereIn('id', $managerIds)
                ->with(['jobTitle', 'department'])
                ->select('id', 'name_ar', 'name_en', 'email_work as email', 'job_title_id', 'department_id')
                ->get()
                ->map(function ($manager) use ($isArabic) {
                    $jobTitleName = $manager->jobTitle 
                        ? ($isArabic ? $manager->jobTitle->name : $manager->jobTitle->name) 
                        : '-';

                    return [
                        'id' => $manager->id,
                        'name' => $isArabic ? ($manager->name_ar ?: $manager->name_en) : ($manager->name_en ?: $manager->name_ar),
                        'email' => $manager->email,
                        'job_title' => $jobTitleName,
                        'department_name' => $manager->department ? $manager->department->name : '-',
                    ];
                });
            
            $employees = $employees->concat($managers);
        }

        // ✅ إزالة التكرار النهائي
        $employees = $employees->unique('id')->values();

        return response()->json($employees);
    }

    /**
     * Recursively get all descendant department IDs.
     */
    protected function getAllDescendantIds($department)
    {
        $ids = [];
        $children = Department::where('parent_id', $department->id)->get();
        
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getAllDescendantIds($child));
        }
        
        return $ids;
    }


    public function getEmployeesByJobTitle($id)
    {
        $companyId = $this->getCompanyId();
        $jobTitle = JobTitle::forCompany($companyId)->findOrFail($id);
        
        $isArabic = app()->getLocale() === 'ar';

        $employees = $jobTitle->employees()
            ->with('department')
            ->select('id', 'name_ar', 'name_en', 'email_work', 'department_id', 'job_title_id')
            ->get()
            ->map(function ($emp) use ($isArabic, $jobTitle) {
                return [
                    'id' => $emp->id,
                    'name' => $isArabic ? ($emp->name_ar ?: $emp->name_en) : ($emp->name_en ?: $emp->name_ar),
                    'email' => $emp->email_work,
                    'job_title' => $jobTitle->name,
                    'department_name' => $emp->department ? $emp->department->name : '-',
                ];
            });

        return response()->json($employees);
    }

    protected function getCompanyId(): int
    {
        if (app()->bound('currentCompany')) {
            return app('currentCompany')->id;
        }
        
        return Auth::user()->saas_company_id;
    }
}





