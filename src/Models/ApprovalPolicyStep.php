<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalPolicyStep extends Model
{
    protected $table = 'approval_policy_steps';

    protected $fillable = [
        'policy_id',
        'position',
        'approver_type',
        'approver_id',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(ApprovalPolicy::class, 'policy_id');
    }
}
