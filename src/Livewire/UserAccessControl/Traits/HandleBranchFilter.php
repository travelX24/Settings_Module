<?php

namespace Athka\SystemSettings\Livewire\UserAccessControl\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait HandleBranchFilter
{
    public string $filterBranchId = '';
    public array $branches = [];
    public bool $lockBranchFilter = false;

    protected function initBranchFilter()
    {
        $metadata = $this->uacService->getBranchMetadata($this->getCompanyId());
        $this->branches = $metadata['list'];
        
        $scope = Auth::user()->access_scope ?? 'all_branches';
        if ($scope === 'my_branch') {
            $this->lockBranchFilter = true;
            $employeeId = Auth::user()->employee_id;
            if ($employeeId && $metadata['col']) {
                $bid = DB::table('employees')->where('id', $employeeId)->value($metadata['col']);
                $this->filterBranchId = $bid ? (string)$bid : '';
            }
        }
    }

    protected function getEffectiveBranchId(): ?int
    {
        return $this->filterBranchId === '' ? null : (int)$this->filterBranchId;
    }

    protected function getCompanyId(): int
    {
        return Auth::user()->saas_company_id ?? 0;
    }
}
