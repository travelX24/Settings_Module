<?php

namespace Athka\SystemSettings\Services;

use Athka\SystemSettings\Models\ApprovalPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalSettingService
{
    /**
     * Get lookup lists for approval settings.
     */
    public function getLookups(int $companyId): array
    {
        return [
            'departments' => $this->simpleList('departments', $companyId),
            'job_titles' => $this->simpleList('job_titles', $companyId),
            'branches' => $this->simpleList('branches', $companyId),
            'employees' => $this->simpleList('employees', $companyId),
        ];
    }

    /**
     * Save or update an approval policy.
     */
    public function savePolicy(int $companyId, string $operationKey, array $data, ?int $id = null): ApprovalPolicy
    {
        return DB::transaction(function () use ($companyId, $operationKey, $data, $id) {
            $policy = ApprovalPolicy::updateOrCreate(
                ['id' => $id, 'company_id' => $companyId, 'operation_key' => $operationKey],
                [
                    'name' => $data['name'],
                    'is_active' => $data['is_active'] ?? true,
                    'scope_type' => $data['scope_type'] ?? 'all',
                ]
            );

            // Scopes
            $policy->scopes()->delete();
            if ($data['scope_type'] !== 'all' && !empty($data['scope_ids'])) {
                $policy->scopes()->insert(array_map(fn($sid) => [
                    'policy_id' => $policy->id,
                    'scope_id' => $sid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $data['scope_ids']));
            }

            // Steps
            $policy->steps()->delete();
            foreach ($data['steps'] as $index => $step) {
                $policy->steps()->create([
                    'position' => $index + 1,
                    'approver_type' => $step['approver_type'],
                    'approver_id' => $step['approver_id'] ?? 0,
                    'follow_standard' => $step['follow_standard'] ?? false,
                ]);
            }

            return $policy;
        });
    }

    private function simpleList(string $table, int $companyId): array
    {
        if (!Schema::hasTable($table)) return [];
        
        $query = DB::table($table)->select('id', 'name');
        
        // Handle common company columns
        if (Schema::hasColumn($table, 'saas_company_id')) {
            $query->where('saas_company_id', $companyId);
        } elseif (Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query->orderBy('name')->get()->toArray();
    }
}
