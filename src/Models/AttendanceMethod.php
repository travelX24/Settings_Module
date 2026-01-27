<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'method',
        'is_enabled',
        'device_count',
        'saas_company_id',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'device_count' => 'integer',
    ];

    /**
     * Scopes
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOfType($query, string $method)
    {
        return $query->where('method', $method);
    }

    /**
     * Check if a specific method is enabled
     */
    public static function isMethodEnabled(string $method): bool
    {
        return static::ofType($method)->enabled()->exists();
    }

    /**
     * Update device count for a method
     */
    public function updateDeviceCount(): void
    {
        if ($this->method === 'gps') {
            $this->device_count = AttendanceGpsLocation::active()->count();
        } else {
            $this->device_count = AttendanceDevice::active()
                ->where('device_type', $this->method)
                ->count();
        }
        $this->save();
    }
}






