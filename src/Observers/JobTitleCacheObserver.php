<?php

namespace Athka\SystemSettings\Observers;

use Illuminate\Support\Facades\Cache;
use Athka\SystemSettings\Models\JobTitle;

/**
 * Observer: يمسح كاش المسميات الوظيفية فور أي تغيير (إضافة/تعديل/حذف).
 * هذا يضمن أن المستخدمين يرون المسميات المحدثة فوراً.
 */
class JobTitleCacheObserver
{
    /**
     * مفاتيح الكاش المرتبطة بالمسميات الوظيفية لشركة معينة.
     */
    private function clearCache(JobTitle $jobTitle): void
    {
        $companyId = $jobTitle->saas_company_id;

        Cache::forget("job_options_{$companyId}");
        Cache::forget("import_job_map_{$companyId}");
    }

    public function saved(JobTitle $jobTitle): void
    {
        $this->clearCache($jobTitle);
    }

    public function deleted(JobTitle $jobTitle): void
    {
        $this->clearCache($jobTitle);
    }

    public function restored(JobTitle $jobTitle): void
    {
        $this->clearCache($jobTitle);
    }
}
