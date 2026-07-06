<?php

namespace Athka\SystemSettings\Observers;

use Illuminate\Support\Facades\Cache;
use Athka\SystemSettings\Models\Department;

/**
 * Observer: يمسح كاش الأقسام فور أي تغيير (إضافة/تعديل/حذف).
 * هذا يضمن أن المستخدمين يرون الأقسام المحدثة فوراً.
 */
class DepartmentCacheObserver
{
    /**
     * مفاتيح الكاش المرتبطة بالأقسام لشركة معينة.
     */
    private function clearCache(Department $department): void
    {
        $companyId = $department->saas_company_id;

        Cache::forget("dept_options_{$companyId}");
        Cache::forget("root_depts_{$companyId}");
        Cache::forget("parent_depts_{$companyId}");
        Cache::forget("import_dept_map_{$companyId}");
        Cache::forget("managers_list_{$companyId}");
        Cache::forget("managers_options_{$companyId}");
    }

    public function saved(Department $department): void
    {
        $this->clearCache($department);
    }

    public function deleted(Department $department): void
    {
        $this->clearCache($department);
    }

    public function restored(Department $department): void
    {
        $this->clearCache($department);
    }
}
