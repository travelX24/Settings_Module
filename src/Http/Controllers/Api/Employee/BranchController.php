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

        $employee = $this->employeeService->resolve($user);
        $scope = $user->access_scope ?? 'all_branches';

        $query = Branch::where('saas_company_id', $companyId)
            ->where('is_active', true);

        if ($scope === 'my_branch') {
            $branchId = $employee->branch_id ?? null;
            if (!$branchId) return response()->json(['ok' => true, 'data' => []]);
            $query->where('id', $branchId);
        } elseif ($scope === 'selected_branches') {
            if (method_exists($user, 'allowedBranches')) {
                $branchIds = $user->allowedBranches()->pluck('branches.id')->toArray();
                $query->whereIn('id', $branchIds);
            } else {
                $branchId = $employee->branch_id ?? null;
                if ($branchId) $query->where('id', $branchId);
            }
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
}
