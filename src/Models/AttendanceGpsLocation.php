<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use Athka\SystemSettings\Models\Department;

class AttendanceGpsLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address_text',
        'lat',
        'lng',
        'radius_meters',
        'branch_id',
        'employee_group_id',
        'is_active',
        'saas_company_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'radius_meters' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'branch_id');
    }

    public function employeeGroup(): BelongsTo
    {
        return $this->belongsTo(EmployeeGroup::class, 'employee_group_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if coordinates are within this location's geofence
     */
    public function isWithinGeofence(float $lat, float $lng): bool
    {
        $earthRadius = 6371000; // meters

        $latFrom = deg2rad($this->lat);
        $lngFrom = deg2rad($this->lng);
        $latTo = deg2rad($lat);
        $lngTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($latFrom) * cos($latTo) *
            sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return $distance <= $this->radius_meters;
    }
}






