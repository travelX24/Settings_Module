<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'symbol',
        'code',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected static function booted()
    {
        static::addGlobalScope('company', function ($builder) {
            $companyId = null;
            if (app()->bound('currentCompany') && app('currentCompany')) {
                $companyId = app('currentCompany')->id;
            } elseif (auth()->check()) {
                $companyId = auth()->user()->saas_company_id;
            }

            if ($companyId) {
                $builder->where('company_id', $companyId);
            }
        });

        static::creating(function ($model) {
            if (!$model->company_id) {
                if (app()->bound('currentCompany') && app('currentCompany')) {
                    $model->company_id = app('currentCompany')->id;
                } elseif (auth()->check()) {
                    $model->company_id = auth()->user()->saas_company_id;
                }
            }
        });
    }
}
