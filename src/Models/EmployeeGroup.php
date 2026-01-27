<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;
use Athka\Employees\Models\Employee;

class EmployeeGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'applied_policy_id',
        'grace_source',
        'grace_setting_id',
        'is_active',
        'saas_company_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function appliedPolicy(): BelongsTo
    {
        return $this->belongsTo(AttendancePolicy::class, 'applied_policy_id');
    }

    public function graceSetting(): BelongsTo
    {
        return $this->belongsTo(AttendanceGraceSetting::class, 'grace_setting_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_group_members', 'group_id', 'employee_id')
            ->withPivot('assigned_at');
    }

    public function allowedMethods(): HasMany
    {
        return $this->hasMany(EmployeeGroupAllowedMethod::class, 'group_id');
    }

    public function gpsLocations(): HasMany
    {
        return $this->hasMany(AttendanceGpsLocation::class, 'employee_group_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get effective grace settings for this group
     */
    public function getEffectiveGraceSettings(): AttendanceGraceSetting
    {
        if ($this->grace_source === 'custom' && $this->graceSetting) {
            return $this->graceSetting;
        }

        return AttendanceGraceSetting::getGlobalDefault();
    }

    /**
     * Check if a method is allowed for this group
     */
    public function isMethodAllowed(string $method): bool
    {
        return $this->allowedMethods()
            ->where('method', $method)
            ->where('is_allowed', true)
            ->exists();
    }
}






