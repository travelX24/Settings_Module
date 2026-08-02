<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Employee;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Athka\Saas\Models\Branch;
use Athka\SystemSettings\Services\EmployeeService;

class BranchController extends Controller
{
    protected $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

    /**
     * Get branches the user has access to.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $this->employeeService->getCompanyId($user);

        if (!$companyId) {
            return response()->json(['ok' => false, 'message' => 'Company context not found'], 422);
        }

        $query = Branch::where('saas_company_id', $companyId)
            ->where('is_active', true);

        $restrictedBranchIds = $this->restrictedBranchIds($user, $companyId);

        if (is_array($restrictedBranchIds)) {
            $query->whereIn('id', $restrictedBranchIds);
        }

        $branches = $query->get()->map(fn($b) => [
            'id' => $b->id,
            'name' => $b->name,
            'code' => $b->code,
        ]);

        return response()->json([
            'ok' => true,
            'data' => $branches
        ]);
    }

    private function restrictedBranchIds($user, int $companyId): ?array
    {
        if (method_exists($user, 'restrictedBranchIds')) {
            return $user->restrictedBranchIds();
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['saas-admin', 'super-admin', 'company-admin', 'system-admin'])) {
            return null;
        }

        $scope = $user->access_scope ?? 'all_branches';

        if ($scope === 'all_branches') {
            return null;
        }

        if ($scope === 'my_branch') {
            $employee = $this->employeeService->resolve($user);
            $branchId = (int) ($employee->branch_id ?? $user->branch_id ?? 0);

            return $branchId > 0 ? [$branchId] : [];
        }

        if ($scope === 'selected_branches' && method_exists($user, 'allowedBranches')) {
            return $user->allowedBranches()
                ->wherePivot('saas_company_id', $companyId)
                ->pluck('branches.id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }
}
