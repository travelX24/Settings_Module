<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalPolicy extends Model
{
    protected $table = 'approval_policies';

    protected $fillable = [
        'company_id',
        'operation_key',
        'name',
        'is_active',
        'scope_type',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopes(): HasMany
    {
        return $this->hasMany(ApprovalPolicyScope::class, 'policy_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalPolicyStep::class, 'policy_id')->orderBy('position');
    }

    public function scopePriority(): int
    {
        // أولوية اختيار السياسة الأكثر تحديداً (حسب التحليل) :contentReference[oaicite:2]{index=2}
        return match ($this->scope_type) {
            'employee'   => 1,
            'department' => 2,
            'job_title'  => 3,
            'branch'     => 4,
            default      => 5, // all
        };
    }
}
