<?php

namespace Athka\SystemSettings\Services;

use Athka\SystemSettings\Models\AttendanceExceptionalDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExceptionalDayService
{
    /**
     * Get the base query for exceptional days with applied filters
     */
    public function getRowsQuery(int $companyId, array $filters): Builder
    {
        $q = AttendanceExceptionalDay::query()->where('company_id', $companyId);

        $today = now()->toDateString();
        $year = $filters['year'] ?? null;
        $month = $filters['month'] ?? null;
        $search = $filters['search'] ?? '';
        $status = $filters['status'] ?? 'all';
        $deductionType = $filters['deductionType'] ?? 'all';
        $departmentId = $filters['departmentId'] ?? null;
        $branchId = $filters['branchId'] ?? null;
        $contractType = $filters['contractType'] ?? null;

        $minPercent = ($filters['minMultiplier'] ?? null) !== null ? (float) $filters['minMultiplier'] : null;
        $maxPercent = ($filters['maxMultiplier'] ?? null) !== null ? (float) $filters['maxMultiplier'] : null;

        $minFactor = ($minPercent !== null) ? ($minPercent / 100.0) : null;
        $maxFactor = ($maxPercent !== null) ? ($maxPercent / 100.0) : null;

        $q->when($year, fn($qq) => $qq->whereYear('start_date', $year))
            ->when($month, fn($qq) => $qq->whereMonth('start_date', $month))
            ->when($search !== '', function ($qq) use ($search) {
                $s = trim($search);
                $qq->where(function ($q2) use ($s) {
                    $q2->where('name', 'like', "%{$s}%")
                        ->orWhere('description', 'like', "%{$s}%");
                });
            })
            ->when($status !== 'all', function ($qq) use ($today, $status) {
                if ($status === 'current') {
                    $qq->whereDate('start_date', '<=', $today)
                        ->whereDate('end_date', '>=', $today);
                } elseif ($status === 'upcoming') {
                    $qq->whereDate('start_date', '>', $today);
                } elseif ($status === 'ended') {
                    $qq->whereDate('end_date', '<', $today);
                }
            })
            ->when($deductionType !== 'all', function ($qq) use ($deductionType) {
                $type = (string) $deductionType;
                if (in_array($type, ['absence', 'late'], true)) {
                    $qq->where('apply_on', $type);
                    return;
                }
                if ($type === 'without') {
                    $qq->where(function ($w) {
                        $w->orWhere('apply_on', 'none')
                            ->orWhere(function ($a) {
                                $a->where('apply_on', 'absence')->where('absence_multiplier', '<=', 0);
                            })
                            ->orWhere(function ($l) {
                                $l->where('apply_on', 'late')->where('late_multiplier', '<=', 0);
                            });
                    });
                }
            })
            ->when($minFactor !== null && $minFactor !== 0.0, function ($qq) use ($minFactor) {
                $qq->where(function ($w) use ($minFactor) {
                    $w->where(function ($a) use ($minFactor) {
                        $a->where('apply_on', 'absence')->where('absence_multiplier', '>=', $minFactor);
                    })->orWhere(function ($l) use ($minFactor) {
                        $l->where('apply_on', 'late')->where('late_multiplier', '>=', $minFactor);
                    });
                });
            })
            ->when($maxFactor !== null && $maxFactor !== 0.0, function ($qq) use ($maxFactor) {
                $qq->where(function ($w) use ($maxFactor) {
                    $w->where(function ($a) use ($maxFactor) {
                        $a->where('apply_on', 'absence')->where('absence_multiplier', '<=', $maxFactor);
                    })->orWhere(function ($l) use ($maxFactor) {
                        $l->where('apply_on', 'late')->where('late_multiplier', '<=', $maxFactor);
                    })->orWhere(function ($n) {
                        $n->where('apply_on', 'none');
                    });
                });
            })
            ->when($departmentId, function ($qq) use ($departmentId) {
                $deptId = (int) $departmentId;
                $qq->where(function ($q2) use ($deptId) {
                    $q2->where('scope_type', 'all')
                        ->orWhere(function ($q3) use ($deptId) {
                            $q3->where('scope_type', 'departments')
                                ->whereJsonContains('include->departments', $deptId);
                        });
                });
            })
            ->when($branchId, function ($qq) use ($branchId) {
                $bid = (int) $branchId;
                $qq->where(function ($q2) use ($bid) {
                    $q2->where('scope_type', 'all')
                        ->orWhere(function ($q3) use ($bid) {
                            $q3->where('scope_type', 'branches')
                                ->whereJsonContains('include->branches', $bid);
                        });
                });
            })
            ->when($contractType, function ($qq) use ($contractType) {
                $qq->where(function ($q2) use ($contractType) {
                    $q2->where('scope_type', 'all')
                        ->orWhere(function ($q3) use ($contractType) {
                            $q3->where('scope_type', 'contract_types')
                                ->whereJsonContains('include->contract_types', $contractType);
                        });
                });
            })
            ->orderBy('start_date', 'desc');

        return $q;
    }

    /**
     * Check if a date range overlaps with existing exceptional days
     */
    public function checkOverlap(
        int $companyId,
        string $start,
        string $end,
        ?int $ignoreId = null,
        string $scopeType = 'all',
        array $include = []
    ): bool {
        $rows = AttendanceExceptionalDay::query()
            ->where('company_id', $companyId)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->get(['id', 'scope_type', 'include']);

        $incomingScopeType = $scopeType ?: 'all';
        $incomingInclude = $this->normalizeScopeInclude($include);

        foreach ($rows as $row) {
            $existingScopeType = (string) ($row->scope_type ?: 'all');
            $existingInclude = $this->normalizeScopeInclude((array) ($row->include ?? []));

            if ($this->scopesConflict($existingScopeType, $existingInclude, $incomingScopeType, $incomingInclude)) {
                return true;
            }
        }

        return false;
    }
    private function normalizeScopeInclude(array $include): array
    {
        return [
            'departments' => array_values(array_unique(array_map('strval', $include['departments'] ?? []))),
            'sections' => array_values(array_unique(array_map('strval', $include['sections'] ?? []))),
            'branches' => array_values(array_unique(array_map('strval', $include['branches'] ?? []))),
            'contract_types' => array_values(array_unique(array_map('strval', $include['contract_types'] ?? []))),
            'employees' => array_values(array_unique(array_map('strval', $include['employees'] ?? []))),
        ];
    }

    private function scopesConflict(
        string $existingType,
        array $existingInclude,
        string $incomingType,
        array $incomingInclude
    ): bool {
        if ($existingType === 'all' || $incomingType === 'all') {
            return true;
        }

        if ($existingType !== $incomingType) {
            return false;
        }

        return match ($existingType) {
            'departments' => $this->hasIntersection(
                array_merge($existingInclude['departments'], $existingInclude['sections']),
                array_merge($incomingInclude['departments'], $incomingInclude['sections'])
            ),
            'branches' => $this->hasIntersection(
                $existingInclude['branches'],
                $incomingInclude['branches']
            ),
            'contract_types' => $this->hasIntersection(
                $existingInclude['contract_types'],
                $incomingInclude['contract_types']
            ),
            'employees' => $this->hasIntersection(
                $existingInclude['employees'],
                $incomingInclude['employees']
            ),
            default => true,
        };
    }

    private function hasIntersection(array $left, array $right): bool
    {
        return !empty(array_intersect($left, $right));
    }
    /**
     * Load scope options (departments, employees, etc) for dropdowns
     */
    public function loadScopeOptions(int $companyId, string $locale = 'en', ?array $allowedBranchIds = null): array
    {
        $departments = [];
        $sections = [];
        $employees = [];
        $branches = [];
        $contractTypes = [];

        $isArabic = $locale === 'ar';

        if (Schema::hasTable('departments')) {
            $companyCol = $this->companyColumnFor('departments');
            $nameExpr = $this->coalesceNameExpr('departments', $isArabic ? ['name_ar', 'name', 'name_en'] : ['name_en', 'name', 'name_ar']);
            $departments = DB::table('departments')
                ->when($companyCol, fn($q) => $q->where($companyCol, $companyId))
                ->select('id', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->get()->toArray();
        }

        if (Schema::hasTable('sections')) {
            $companyCol = $this->companyColumnFor('sections');
            $nameExpr = $this->coalesceNameExpr('sections', $isArabic ? ['name_ar', 'name', 'name_en'] : ['name_en', 'name', 'name_ar']);
            $sections = DB::table('sections')
                ->when($companyCol, fn($q) => $q->where($companyCol, $companyId))
                ->select('id', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->get()->toArray();
        }

        if (Schema::hasTable('employees')) {
            $companyCol = $this->companyColumnFor('employees');
            $nameExpr = $this->coalesceNameExpr('employees', $isArabic ? ['name_ar', 'name', 'full_name', 'name_en', 'employee_no'] : ['name_en', 'name', 'full_name', 'name_ar', 'employee_no']);
            $branchCol = $this->employeeBranchColumn();

            $employees = DB::table('employees')
                ->when($companyCol, fn($q) => $q->where($companyCol, $companyId))
                ->when($allowedBranchIds !== null, function ($q) use ($allowedBranchIds, $branchCol) {
                    if (!$branchCol) {
                        $q->whereRaw('1=0');
                        return;
                    }
                    $q->whereIn($branchCol, $allowedBranchIds);
                })
                ->select('id', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->limit(300)
                ->get()->toArray();

            $contractTypes = DB::table('employees')
                ->where('saas_company_id', $companyId)
                ->whereNotNull('contract_type')
                ->where('contract_type', '!=', '')
                ->distinct()
                ->pluck('contract_type')
                ->map(fn($t) => (object) ['id' => $t, 'name' => $t])
                ->toArray();
        }

        if (Schema::hasTable('branches')) {
            $companyCol = $this->companyColumnFor('branches');
            $nameExpr = $this->coalesceNameExpr('branches', $isArabic ? ['name_ar', 'name', 'name_en'] : ['name_en', 'name', 'name_ar']);
            $branches = DB::table('branches')
                ->when($companyCol, fn($q) => $q->where($companyCol, $companyId))
                ->select('id', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->get()->toArray();
        }

        return compact('departments', 'sections', 'employees', 'branches', 'contractTypes');
    }

    /**
     * Copy selected exceptional days to another year
     */
    public function copyDays(int $companyId, array $sourceIds, int $diffYears): array
    {
        $rows = AttendanceExceptionalDay::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $sourceIds)
            ->get();

        $copied = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            $start = $r->start_date?->copy();
            if (!$start) {
                $skipped++;
                continue;
            }

            $end = ($r->end_date ?? $r->start_date)?->copy() ?? $start->copy();

            $newStart = $start->copy()->addYears($diffYears);
            $newEnd = $end->copy()->addYears($diffYears);

            if (
                $this->checkOverlap(
                    $companyId,
                    $newStart->toDateString(),
                    $newEnd->toDateString(),
                    null,
                    (string) ($r->scope_type ?? 'all'),
                    (array) ($r->include ?? [])
                )
            ) {
                $skipped++;
                continue;
            }

            AttendanceExceptionalDay::create([
                'company_id' => $companyId,
                'name' => $r->name,
                'description' => $r->description,
                'period_type' => $r->period_type,
                'start_date' => $newStart->toDateString(),
                'end_date' => $newEnd->toDateString(),
                'apply_on' => $r->apply_on,
                'absence_multiplier' => $r->absence_multiplier,
                'late_multiplier' => $r->late_multiplier,
                'grace_hours' => $r->grace_hours,
                'scope_type' => $r->scope_type,
                'include' => $r->include,
                'notify_policy' => $r->notify_policy,
                'notify_message' => $r->notify_message,
                'retroactive' => $r->retroactive,
                'is_active' => false,
                'created_by' => auth()->id(),
            ]);

            $copied++;
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    /**
     * Get allowed branch ids for current user
     */
    public function currentUserAllowedBranchIds(int $companyId): ?array
    {
        $user = auth()->user();
        if (!$user)
            return [];

        $scope = (string) ($user->access_scope ?? 'all_branches');
        if (!in_array($scope, ['all_branches', 'my_branch', 'selected_branches'], true)) {
            $scope = 'all_branches';
        }

        if ($scope === 'all_branches')
            return null;

        $branchesCompanyCol = null;
        if (Schema::hasTable('branches')) {
            foreach (['saas_company_id', 'company_id'] as $c) {
                if (Schema::hasColumn('branches', $c)) {
                    $branchesCompanyCol = $c;
                    break;
                }
            }
        }

        if ($scope === 'my_branch') {
            $branchCol = $this->employeeBranchColumn();
            if (!$branchCol)
                return [];

            $bid = (int) ($user->employee?->{$branchCol} ?? 0);
            if ($bid <= 0 && Schema::hasTable('employees') && !empty($user->employee_id)) {
                $bid = (int) DB::table('employees')->where('id', (int) $user->employee_id)->value($branchCol);
            }
            return $bid > 0 ? [$bid] : [];
        }

        if ($scope === 'selected_branches') {
            if (!method_exists($user, 'allowedBranches'))
                return [];

            $ids = $user->allowedBranches()
                ->pluck('branches.id')
                ->map(fn($v) => (int) $v)
                ->filter()->unique()->values()->all();

            if (empty($ids))
                return [];

            if ($branchesCompanyCol) {
                $ids = DB::table('branches')
                    ->where($branchesCompanyCol, $companyId)
                    ->whereIn('id', $ids)
                    ->pluck('id')
                    ->map(fn($v) => (int) $v)
                    ->all();
            }

            return $ids;
        }

        return null;
    }

    private function companyColumnFor(string $table): ?string
    {
        if (!Schema::hasTable($table))
            return null;
        if (Schema::hasColumn($table, 'company_id'))
            return 'company_id';
        if (Schema::hasColumn($table, 'saas_company_id'))
            return 'saas_company_id';
        return null;
    }

    private function coalesceNameExpr(string $table, array $preferredColumns, string $idColumn = 'id'): string
    {
        $cols = [];
        foreach ($preferredColumns as $col) {
            if (Schema::hasColumn($table, $col))
                $cols[] = $col;
        }
        $cols[] = "CAST({$idColumn} AS CHAR)";
        return 'COALESCE(' . implode(', ', $cols) . ')';
    }

    private function employeeBranchColumn(): ?string
    {
        if (!Schema::hasTable('employees'))
            return null;
        if (Schema::hasColumn('employees', 'branch_id'))
            return 'branch_id';
        return null;
    }
}
