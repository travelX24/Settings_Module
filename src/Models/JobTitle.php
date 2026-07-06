<?php

namespace Athka\SystemSettings\Models;

use Athka\Saas\Models\SaasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Athka\SystemSettings\Observers\JobTitleCacheObserver;

class JobTitle extends Model
{
    /**
     * يسجل Observer لمسح الكاش فور أي تغيير في المسميات الوظيفية.
     */
    protected static function booted(): void
    {
        static::observe(JobTitleCacheObserver::class);
    }

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'saas_company_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the company that owns the job title.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(SaasCompany::class, 'saas_company_id');
    }

    /**
     * Get the employees with this job title.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(\Athka\Employees\Models\Employee::class, 'job_title_id');
    }

    /**
     * Scope a query to only include active job titles.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by company.
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('saas_company_id', $companyId);
    }

    /**
     * Check if the job title can be deleted.
     */
    public function canDelete(): bool
    {
        return $this->employees()->count() === 0;
    }

    /**
     * Get the employees count.
     */
    public function getEmployeesCountAttribute(): int
    {
        return $this->employees()->count();
    }
}




