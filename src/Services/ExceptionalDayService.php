<?php

namespace Athka\SystemSettings\Services;

use Athka\SystemSettings\Models\AttendanceExceptionalDay;
use Athka\Employees\Support\EmployeeStatus;
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
        $yearStartDate = $filters['yearStartDate'] ?? null;
        $yearEndDate = $filters['yearEndDate'] ?? null;
        $monthStartDate = $filters['monthStartDate'] ?? null;
        $monthEndDate = $filters['monthEndDate'] ?? null;
        $dateFrom = $filters['dateFrom'] ?? null;
        $dateTo = $filters['dateTo'] ?? null;
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

        $q->when($yearStartDate && $yearEndDate, function ($qq) use ($yearStartDate, $yearEndDate) {
            $qq->whereBetween('start_date', [$yearStartDate, $yearEndDate]);
        })
            ->when($monthStartDate && $monthEndDate, function ($qq) use ($monthStartDate, $monthEndDate) {
                $qq->whereBetween('start_date', [$monthStartDate, $monthEndDate]);
            })
            ->when($dateFrom || $dateTo, function ($qq) use ($dateFrom, $dateTo) {
                $from = $dateFrom ?: '0001-01-01';
                $to = $dateTo ?: '9999-12-31';

                $qq->where(function ($q2) use ($from, $to) {
                    $q2->whereDate('start_date', '<=', $to)
                        ->where(function ($q3) use ($from) {
                            $q3->whereDate('end_date', '>=', $from)
                                ->orWhere(function ($q4) use ($from) {
                                    $q4->whereNull('end_date')
                                        ->whereDate('start_date', '>=', $from);
                                });
                        });
                });
            })
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
                $deptIdInt = (int) $departmentId;
                $deptIdStr = (string) $departmentId;

                $qq->where('scope_type', 'departments')
                    ->where(function ($q2) use ($deptIdInt, $deptIdStr) {
                        $q2->whereJsonContains('include->departments', $deptIdStr)
                            ->orWhereJsonContains('include->departments', $deptIdInt);
                    });
            })
            ->when($branchId, function ($qq) use ($branchId) {
                $branchIdInt = (int) $branchId;
                $branchIdStr = (string) $branchId;

                $qq->where('scope_type', 'branches')
                    ->where(function ($q2) use ($branchIdInt, $branchIdStr) {
                        $q2->whereJsonContains('include->branches', $branchIdStr)
                            ->orWhereJsonContains('include->branches', $branchIdInt);
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
     * Check if a date range overlaps with existing exceptional days for the same violation type
     */
    public function checkOverlap(
        int $companyId,
        string $start,
        string $end,
        ?int $ignoreId = null,
        string $scopeType = 'all',
        array $include = [],
        ?string $applyOn = null
    ): bool {
        $rows = AttendanceExceptionalDay::query()
            ->where('company_id', $companyId)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->when($applyOn, fn($q) => $q->where('apply_on', $applyOn))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->get(['id', 'apply_on', 'scope_type', 'include']);

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
    public function loadScopeOptions(int $companyId, string $locale = 'en', ?array $allowedBranchIds = null, ?array $parentDeptIds = null, string $employeeStatus = EmployeeStatus::ACTIVE): array
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
            
            // Main Departments (No parent)
            $departments = DB::table('departments')
                ->where('is_active', 1)
                ->whereNull('parent_id')
                ->when($companyCol, fn($q) => $q->where($companyCol, $companyId))
                ->select('id', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->get()->toArray();

            // Sections/Sub-departments (Have parent)
            $sections = DB::table('departments')
                ->where('is_active', 1)
                ->whereNotNull('parent_id')
                ->when($parentDeptIds, fn($q) => $q->whereIn('parent_id', $parentDeptIds))
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
                ->when($employeeStatus !== 'all', fn ($q) => $q->where('status', $employeeStatus))
                ->when($companyCol, fn($q) => $q->where($companyCol, $companyId))
                ->when($allowedBranchIds !== null, function ($q) use ($allowedBranchIds, $branchCol) {
                    if (!$branchCol) {
                        $q->whereRaw('1=0');
                        return;
                    }
                    $q->whereIn($branchCol, $allowedBranchIds);
                })
                ->select('id', 'status', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->limit(300)
                ->get()
                ->map(function ($employee) {
                    if (($employee->status ?? EmployeeStatus::ACTIVE) !== EmployeeStatus::ACTIVE) {
                        $employee->name .= ' - ' . EmployeeStatus::label($employee->status);
                    }

                    return $employee;
                })
                ->toArray();

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
                ->when($allowedBranchIds !== null, fn($q) => $q->whereIn('id', $allowedBranchIds))
                ->select('id', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->get()->toArray();
        }

        return [
            'departments' => $departments,
            'sections' => $sections,
            'employees' => $employees,
            'branches' => $branches,
            'contract_types' => $contractTypes,
        ];
    }

    private function companyColumnFor(string $table): ?string
    {
        if (Schema::hasColumn($table, 'saas_company_id')) return 'saas_company_id';
        if (Schema::hasColumn($table, 'company_id')) return 'company_id';
        return null;
    }

    private function employeeBranchColumn(): ?string
    {
        if (Schema::hasColumn('employees', 'branch_id')) return 'branch_id';
        if (Schema::hasColumn('employees', 'primary_branch_id')) return 'primary_branch_id';
        return null;
    }

    private function coalesceNameExpr(string $table, array $columns): string
    {
        $existing = array_filter($columns, fn($col) => Schema::hasColumn($table, $col));
        if (empty($existing)) {
            return "'N/A'";
        }
        return 'COALESCE(' . implode(', ', $existing) . ')';
    }
}
