<?php

namespace Athka\SystemSettings\Models;

use Athka\Saas\Models\SaasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Department extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'manager_id',
        'parent_id',
        'is_active',
        'saas_company_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the company that owns the department.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(SaasCompany::class, 'saas_company_id');
    }

    /**
     * Get the manager of the department.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(\Athka\Employees\Models\Employee::class, 'manager_id');
    }

    /**
     * Get the parent department.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * Get the child departments.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    /**
     * Get the employees in this department.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(\Athka\Employees\Models\Employee::class, 'department_id');
    }

    /**
     * Scope a query to only include active departments.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include root departments (no parent).
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope a query to filter by company.
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('saas_company_id', $companyId);
    }

    /**
     * Check if the department can be deleted.
     */
    public function canDelete(): bool
    {
        return $this->employees()->count() === 0 && $this->children()->count() === 0;
    }

    /**
     * Get the employees count.
     */
    public function getEmployeesCountAttribute(): int
    {
        return $this->employees()->count();
    }

    /**
     * Get the children count.
     */
    public function getChildrenCountAttribute(): int
    {
        return $this->children()->count();
    }
}




