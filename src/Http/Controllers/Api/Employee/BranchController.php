<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Employee;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Athka\Saas\Models\Branch;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    /**
     * Get branches the user has access to.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->saas_company_id;

        if (!$companyId) {
            return response()->json(['ok' => false, 'message' => 'Company context not found'], 422);
        }

        $scope = $user->access_scope ?? 'all_branches';

        $query = Branch::where('saas_company_id', $companyId)
            ->where('is_active', true);

        if ($scope === 'my_branch') {
            $branchId = $user->employee?->branch_id;
            if (!$branchId) {
                return response()->json(['ok' => true, 'data' => []]);
            }
            $query->where('id', $branchId);
        } elseif ($scope === 'selected_branches') {
            if (method_exists($user, 'allowedBranches')) {
                $branchIds = $user->allowedBranches()->pluck('branches.id')->toArray();
                $query->whereIn('id', $branchIds);
            } else {
                 // Fallback if relation not defined
                 $branchId = $user->employee?->branch_id;
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
