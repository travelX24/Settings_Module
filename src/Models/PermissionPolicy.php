<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionPolicy extends Model
{
    protected $table = 'permission_policies';

    protected $fillable = [
        'company_id',
        'policy_year_id',
        'approval_required',
        'monthly_limit_minutes',
        'max_request_minutes',
        'deduction_policy',
        'show_in_app',
        'requires_attachment',
        'attachment_types',
        'attachment_max_mb',
        'settings',
    ];

    protected $casts = [
        'approval_required' => 'boolean',
        'show_in_app' => 'boolean',
        'requires_attachment' => 'boolean',
        'attachment_types' => 'array',
        'settings' => 'array',
        'attachment_max_mb' => 'integer',
        'monthly_limit_minutes' => 'integer',
        'max_request_minutes' => 'integer',
    ];
}
