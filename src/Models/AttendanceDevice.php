<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use Athka\SystemSettings\Models\Department;

class AttendanceDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_type',
        'name',
        'branch_id',
        'location_in_branch',
        'serial_no',
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
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'branch_id');
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

    public function scopeFingerprint($query)
    {
        return $query->where('device_type', 'fingerprint');
    }

    public function scopeNfc($query)
    {
        return $query->where('device_type', 'nfc');
    }
}






