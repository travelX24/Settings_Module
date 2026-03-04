<?php

namespace Athka\SystemSettings\Livewire\Attendance\ExceptionalDays;

use Athka\SystemSettings\Models\AttendanceExceptionalDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ExceptionalDaysIndex extends Component
{
    use WithPagination;

    public int $year;
    public ?int $month = null;

    public string $status = 'all';
    public string $search = '';

    public string $deductionType = 'all'; 
    public ?float $minMultiplier = null;  
    public ?float $maxMultiplier = null; 
    public ?int $departmentId = null;

    public bool $showModal = false;
    public ?int $editingId = null;

    public array $selected = [];
    public bool $selectPage = false;

    public bool $showCopyModal = false;
    public int $copyFromYear;
    public int $copyToYear;
    public ?int $copyFromCount = null;

    public array $copySelected = [];
    public bool $copySelectAll = false;

    public int $perPage = 10;

    public array $form = [
        'name' => '',
        'description' => null,

        'period_type' => 'single',
        'start_date' => null,
        'end_date' => null,

        'apply_on' => 'absence', 

        'deduction_mode' => 'with', 

        'deduction_percent' => 100.0, 

        'absence_multiplier' => 1.00,
        'late_multiplier' => 1.00,

        'grace_hours' => 0,

        'scope_type' => 'all', 
        'include' => [
            'departments' => [],
            'sections' => [],
            'employees' => [],
        ],

        'notify_policy' => 'none',
        'notify_message' => null,

        'retroactive' => 'from_created',

        'is_active' => true,
    ];

    public function mount(): void
    {
        $this->authorize('settings.attendance.view');
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;

        $this->copyFromYear = (int) now()->subYear()->year;
        $this->copyToYear   = (int) $this->year;
        $this->copyFromCount = null;
    }

    public function updatingYear() { $this->resetPage(); }
    public function updatingMonth() { $this->resetPage(); }
    public function updatingStatus() { $this->resetPage(); }
    public function updatingSearch() { $this->resetPage(); }
    public function updatingDeductionType() { $this->resetPage(); }
    public function updatingMinMultiplier() { $this->resetPage(); }
    public function updatingMaxMultiplier() { $this->resetPage(); }
    public function updatingDepartmentId() { $this->resetPage(); }

    public function updatedFormPeriodType($value): void
    {
        if (($value ?? 'single') === 'single') {
            $this->form['end_date'] = $this->form['start_date'];
        } else {
            if (empty($this->form['end_date'])) {
                $this->form['end_date'] = $this->form['start_date'];
            }
        }
    }

    public function updatedFormStartDate($value): void
    {
        if (($this->form['period_type'] ?? 'single') === 'single') {
            $this->form['end_date'] = $value;
        }
    }

    public function updatedFormApplyOn($value): void
    {
        $v = (string) $value;

        if (!in_array($v, ['late', 'absence'], true)) {
            $v = 'absence';
            $this->form['apply_on'] = 'absence';
        }

        $mode = (string) ($this->form['deduction_mode'] ?? 'with');

        if ($mode === 'without') {
            $this->form['grace_hours'] = 0;
            $this->form['deduction_percent'] = 0.0;
            $this->form['retroactive'] = 'from_created';
            return;
        }

        if ($v === 'late') {
            $this->form['grace_hours'] = (int) ($this->form['grace_hours'] ?? 0);
            if (!isset($this->form['deduction_percent']) || $this->form['deduction_percent'] === null) {
                $this->form['deduction_percent'] = 100.0;
            }
        } else { 
            $this->form['grace_hours'] = 0;
            if (!isset($this->form['deduction_percent']) || $this->form['deduction_percent'] === null) {
                $this->form['deduction_percent'] = 100.0;
            }
        }
    }

    public function updatedFormDeductionMode($value): void
    {
        $mode = (string) $value;

        if ($mode === 'without') {
            $this->form['deduction_percent'] = 0.0;
            $this->form['grace_hours'] = 0;
            $this->form['retroactive'] = 'from_created';
            return;
        }

        if (!isset($this->form['deduction_percent']) || $this->form['deduction_percent'] === null) {
            $this->form['deduction_percent'] = 100.0;
        }

        $this->updatedFormApplyOn($this->form['apply_on'] ?? 'absence');
    }

    public function updatedFormScopeType($value): void
    {
        $type = (string) $value;

        if ($type === 'all') {
            $this->form['include'] = ['departments'=>[], 'sections'=>[], 'employees'=>[]];
            return;
        }

        if ($type === 'departments') {
            $this->form['include']['employees'] = [];
            return;
        }

        if ($type === 'employees') {
            $this->form['include']['departments'] = [];
            $this->form['include']['sections'] = [];
            return;
        }
    }

    protected function rules(): array
    {
        $mode = (string) ($this->form['deduction_mode'] ?? 'with');
        $hasDeduction = ($mode === 'with');

        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],

            'form.period_type' => ['required', Rule::in(['single', 'range'])],
            'form.start_date' => ['required', 'date'],

            'form.end_date' => [
                Rule::requiredIf(fn () => ($this->form['period_type'] ?? 'single') === 'range'),
                'nullable',
                'date',
                'after_or_equal:form.start_date',
            ],

            'form.apply_on' => ['required', Rule::in(['absence', 'late'])],

            'form.deduction_mode' => ['required', Rule::in(['with', 'without'])],

            'form.deduction_percent' => [
                Rule::requiredIf(fn () => $hasDeduction),
                'nullable',
                'numeric',
                'min:0',
                'max:1000',
            ],

            'form.grace_hours' => [
                Rule::requiredIf(fn () => $hasDeduction && (($this->form['apply_on'] ?? 'absence') === 'late')),
                'nullable',
                'integer',
                'min:0',
                'max:24',
            ],

            'form.scope_type' => ['required', Rule::in(['all', 'departments', 'employees'])],
            'form.include' => ['nullable', 'array'],

            'form.notify_policy' => ['required', Rule::in(['none', 'after_deduction'])],
            'form.notify_message' => ['nullable', 'string', 'max:2000'],

            'form.retroactive' => ['nullable', Rule::in(['from_created', 'full_period'])],

            'form.is_active' => ['boolean'],
        ];
    }
    private function companyId(): int
    {
        if (app()->bound('currentCompany') && app('currentCompany')) {
            return (int) app('currentCompany')->id;
        }

        return (int) (auth()->user()->saas_company_id ?? auth()->user()->company_id ?? 0);
    }


    private function rowsQuery(int $companyId): Builder
    {
        $today = now()->toDateString();

        $minPercent = ($this->minMultiplier !== null) ? (float) $this->minMultiplier : null;
        $maxPercent = ($this->maxMultiplier !== null) ? (float) $this->maxMultiplier : null;

        $minFactor = ($minPercent !== null) ? ($minPercent / 100.0) : null;
        $maxFactor = ($maxPercent !== null) ? ($maxPercent / 100.0) : null;

        $q = AttendanceExceptionalDay::query()
            ->where('company_id', $companyId)
            ->when($this->year, fn ($qq) => $qq->whereYear('start_date', $this->year))
            ->when($this->month, fn ($qq) => $qq->whereMonth('start_date', $this->month))
            ->when($this->search !== '', function ($qq) {
                $s = trim($this->search);
                $qq->where(function ($q2) use ($s) {
                    $q2->where('name', 'like', "%{$s}%")
                        ->orWhere('description', 'like', "%{$s}%");
                });
            })
            ->when($this->status !== 'all', function ($qq) use ($today) {
                if ($this->status === 'current') {
                    $qq->whereDate('start_date', '<=', $today)
                        ->whereDate('end_date', '>=', $today);
                } elseif ($this->status === 'upcoming') {
                    $qq->whereDate('start_date', '>', $today);
                } elseif ($this->status === 'ended') {
                    $qq->whereDate('end_date', '<', $today);
                }
            })
            ->when($this->deductionType !== 'all', function ($qq) {
                $type = (string) $this->deductionType;

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
            ->when($this->departmentId, function ($qq) {
                $deptId = (int) $this->departmentId;

                $qq->where(function ($q2) use ($deptId) {
                    $q2->where('scope_type', 'all')
                        ->orWhere(function ($q3) use ($deptId) {
                            $q3->where('scope_type', 'departments')
                                ->whereJsonContains('include->departments', $deptId);
                        });
                });
            })
            ->orderBy('start_date', 'desc');

        return $q;
    }

    private function validateScopeSelections(): bool
    {
        $type = (string) ($this->form['scope_type'] ?? 'all');
        $inc  = $this->form['include'] ?? ['departments'=>[], 'sections'=>[], 'employees'=>[]];

        if ($type === 'all') {
            return true;
        }

        if ($type === 'departments') {
            $hasAny = !empty($inc['departments']) || !empty($inc['sections']);
            if (!$hasAny) {
                $this->addError('form.include.departments', tr('Please select at least one department or sub department.'));
                return false;
            }
            return true;
        }

        if ($type === 'employees') {
            if (empty($inc['employees'])) {
                $this->addError('form.include.employees', tr('Please select at least one employee.'));
                return false;
            }
            return true;
        }

        return true;
    }

    private function normalizeApplyAndRates(): void
    {
        $mode = (string) ($this->form['deduction_mode'] ?? 'with');
        $apply = (string) ($this->form['apply_on'] ?? 'absence');

        if ($mode === 'without') {
            $this->form['deduction_percent'] = 0.0;
            $this->form['grace_hours'] = 0;
            $this->form['retroactive'] = 'from_created';

            if ($apply === 'late') {
                $this->form['late_multiplier'] = 0.0;
                $this->form['absence_multiplier'] = 1.00;
            } else {
                $this->form['absence_multiplier'] = 0.0;
                $this->form['late_multiplier'] = 1.00;
            }

            return;
        }

        $percent = (float) ($this->form['deduction_percent'] ?? 0.0);
        $factor = $percent / 100.0;

        if ($factor < 0) $factor = 0;
        if ($factor > 10) $factor = 10;

        if ($apply === 'absence') {
            $this->form['absence_multiplier'] = $factor;
            $this->form['late_multiplier'] = 1.00;
            $this->form['grace_hours'] = 0;
        } else {
            $this->form['late_multiplier'] = $factor;
            $this->form['absence_multiplier'] = 1.00;
            $this->form['grace_hours'] = (int) ($this->form['grace_hours'] ?? 0);
        }
    }

    private function payloadForSave(int $companyId): array
    {
        $apply = (string) ($this->form['apply_on'] ?? 'absence');
        if (!in_array($apply, ['absence', 'late'], true)) {
            $apply = 'absence';
        }

        return [
            'company_id' => $companyId,
            'name' => $this->form['name'] ?? '',
            'description' => $this->form['description'] ?? null,

            'period_type' => $this->form['period_type'] ?? 'single',
            'start_date' => $this->form['start_date'] ?? null,
            'end_date' => $this->form['end_date'] ?? null,

            'apply_on' => $apply,

            'absence_multiplier' => (float) ($this->form['absence_multiplier'] ?? 1.0),
            'late_multiplier' => (float) ($this->form['late_multiplier'] ?? 1.0),
            'grace_hours' => (int) ($this->form['grace_hours'] ?? 0),

            'scope_type' => $this->form['scope_type'] ?? 'all',
            'include' => $this->form['include'] ?? ['departments'=>[], 'sections'=>[], 'employees'=>[]],

            'notify_policy' => $this->form['notify_policy'] ?? 'none',
            'notify_message' => $this->form['notify_message'] ?? null,
            'retroactive' => $this->form['retroactive'] ?? 'from_created',

            'is_active' => (bool) ($this->form['is_active'] ?? true),

            'created_by' => auth()->id(),
        ];
    }

    public function openCreate(): void
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();
        $this->editingId = null;

        $this->form = [
            'name' => '',
            'description' => null,

            'period_type' => 'single',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),

            'apply_on' => 'absence',
            'deduction_mode' => 'with',
            'deduction_percent' => 100.0,

            'absence_multiplier' => 1.00,
            'late_multiplier' => 1.00,
            'grace_hours' => 0,

            'scope_type' => 'all',
            'include' => ['departments'=>[], 'sections'=>[], 'employees'=>[]],

            'notify_policy' => 'none',
            'notify_message' => null,

            'retroactive' => 'from_created',

            'is_active' => true,
        ];

        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();
        $this->editingId = $id;

        $row = AttendanceExceptionalDay::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $applyStored = (string) ($row->apply_on ?? 'absence');
        if (!in_array($applyStored, ['absence', 'late', 'none'], true)) {
            $applyStored = 'absence';
        }

        $deductionMode = 'with';
        if ($applyStored === 'none') {
            $deductionMode = 'without';
        } elseif ($applyStored === 'absence' && ((float) $row->absence_multiplier) <= 0) {
            $deductionMode = 'without';
        } elseif ($applyStored === 'late' && ((float) $row->late_multiplier) <= 0) {
            $deductionMode = 'without';
        }

        $applyForUi = ($applyStored === 'none') ? 'absence' : $applyStored;

        $percent = 0.0;
        if ($applyStored === 'absence') $percent = (float) $row->absence_multiplier * 100.0;
        if ($applyStored === 'late') $percent = (float) $row->late_multiplier * 100.0;

        if ($deductionMode === 'without') {
            $percent = 0.0;
        }

        $include = $row->include ?? ['departments'=>[], 'sections'=>[], 'employees'=>[]];
        $scopeType = (string) ($row->scope_type ?? 'all');

        if (!in_array($scopeType, ['all', 'departments', 'employees'], true)) {
            if (!empty($include['employees'])) {
                $scopeType = 'employees';
            } elseif (!empty($include['departments']) || !empty($include['sections'])) {
                $scopeType = 'departments';
            } else {
                $scopeType = 'all';
            }
        }

        $notifyPolicy = (string) ($row->notify_policy ?? 'none');
        if (in_array($notifyPolicy, ['days_3', 'week_1', 'weeks_2'], true)) {
            $notifyPolicy = 'after_deduction';
        }
        if (!in_array($notifyPolicy, ['none', 'after_deduction'], true)) {
            $notifyPolicy = 'none';
        }

        $this->form = [
            'name' => $row->name,
            'description' => $row->description,

            'period_type' => $row->period_type,
            'start_date' => $row->start_date?->toDateString(),
            'end_date' => $row->end_date?->toDateString(),

            'apply_on' => $applyForUi,
            'deduction_mode' => $deductionMode,
            'deduction_percent' => round($percent, 2),

            'absence_multiplier' => (float) $row->absence_multiplier,
            'late_multiplier' => (float) $row->late_multiplier,
            'grace_hours' => (int) $row->grace_hours,

            'scope_type' => $scopeType,
            'include' => $include,

            'notify_policy' => $notifyPolicy,
            'notify_message' => $row->notify_message,

            'retroactive' => $row->retroactive ?? 'from_created',

            'is_active' => (bool) $row->is_active,
        ];

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('settings.attendance.manage');
        $this->validate();

        if (($this->form['period_type'] ?? 'single') === 'single') {
            $this->form['end_date'] = $this->form['start_date'];
        } else {
            if (empty($this->form['end_date'])) {
                $this->form['end_date'] = $this->form['start_date'];
            }
        }

        if (!$this->validateScopeSelections()) {
            return;
        }

        $this->normalizeApplyAndRates();

        $start = $this->form['start_date'];
        $end   = $this->form['end_date'];

        $overlap = AttendanceExceptionalDay::query()
            ->where('company_id', $this->companyId())
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                  ->orWhereBetween('end_date', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('start_date', '<=', $start)
                         ->where('end_date', '>=', $end);
                  });
            })
            ->exists();

        if ($overlap) {
            $this->addError('form.start_date', tr('Date range overlaps with another exceptional day.'));
            return;
        }

        $type = (string) ($this->form['scope_type'] ?? 'all');

        if ($type === 'all') {
            $this->form['include'] = ['departments'=>[], 'sections'=>[], 'employees'=>[]];
        } elseif ($type === 'departments') {
            $this->form['include']['employees'] = [];
        } elseif ($type === 'employees') {
            $this->form['include']['departments'] = [];
            $this->form['include']['sections'] = [];
        }

        $companyId = $this->companyId();

        AttendanceExceptionalDay::updateOrCreate(
            [
                'id' => $this->editingId,
                'company_id' => $companyId,
            ],
            $this->payloadForSave($companyId)
        );

        $this->showModal = false;
        $this->resetValidation();
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('settings.attendance.manage');
        $row = AttendanceExceptionalDay::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $row->update(['is_active' => ! $row->is_active]);
    }

    public function deleteRow(int $id): void
    {
        $this->authorize('settings.attendance.manage');
        AttendanceExceptionalDay::query()
            ->where('company_id', $this->companyId())
            ->where('id', $id)
            ->delete();

        $this->resetPage();
    }

    public function updatedSelectPage($value): void
    {
        if ($value) {
            $companyId = $this->companyId();
            $page = (int) $this->getPage(); 

            $ids = $this->rowsQuery($companyId)
                ->forPage($page, $this->perPage)
                ->pluck('id')
                ->toArray();

            $this->selected = $ids;
        } else {
            $this->selected = [];
        }
    }


    public function deleteSelected(): void
    {
        $this->authorize('settings.attendance.manage');
        if (empty($this->selected)) return;

        AttendanceExceptionalDay::query()
            ->where('company_id', $this->companyId())
            ->whereIn('id', $this->selected)
            ->delete();

        $this->selected = [];
        $this->selectPage = false;
        $this->resetPage();
    }

    public function setSelectedActive(bool $active): void
    {
        $this->authorize('settings.attendance.manage');
        if (empty($this->selected)) return;

        AttendanceExceptionalDay::query()
            ->where('company_id', $this->companyId())
            ->whereIn('id', $this->selected)
            ->update(['is_active' => $active]);

        $this->selected = [];
        $this->selectPage = false;
    }


    public function updatedCopyFromYear($value): void
    {
        $from = (int) $value;
        $companyId = $this->companyId();

        $this->copyFromCount = AttendanceExceptionalDay::query()
            ->where('company_id', $companyId)
            ->whereYear('start_date', $from)
            ->count();

        $this->copySelected = [];
        $this->copySelectAll = false;
    }

    public function openCopyModal(): void
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();

        $this->copyToYear = (int) $this->year;

        $this->updatedCopyFromYear($this->copyFromYear);

        $this->showCopyModal = true;
    }

    public function updatedCopySelectAll($value): void
    {
        $this->copySelectAll = (bool) $value;

        if (!$this->showCopyModal) {
            $this->copySelected = [];
            return;
        }

        if ($this->copySelectAll) {
            $companyId = $this->companyId();

            $ids = AttendanceExceptionalDay::query()
                ->where('company_id', $companyId)
                ->whereYear('start_date', (int) $this->copyFromYear)
                ->orderBy('start_date')
                ->limit(200)
                ->pluck('id')
                ->toArray();

            $this->copySelected = $ids;
        } else {
            $this->copySelected = [];
        }
    }

    public function copyOneDay(int $id): void
    {
        $this->copySelected = [$id];
        $this->copySelectedDays();
    }

    public function copySelectedDays(): void
    {
        $this->authorize('settings.attendance.manage');
        $companyId = $this->companyId();

        $from = (int) $this->copyFromYear;
        $to   = (int) $this->year; 
        $this->copyToYear = $to;

        if ($from < 2000 || $to < 2000) return;

        if (empty($this->copySelected)) {
            $this->addError('copySelected', tr('Please select at least one day to copy.'));
            return;
        }

        $diffYears = $to - $from;

        $rows = AttendanceExceptionalDay::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $this->copySelected)
            ->get();

        $copied = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            $start = $r->start_date?->copy();
            if (!$start) { $skipped++; continue; }

            $end = ($r->end_date ?? $r->start_date)?->copy() ?? $start->copy();

            $newStart = $start->copy()->addYears($diffYears);
            $newEnd   = $end->copy()->addYears($diffYears);

            $overlap = AttendanceExceptionalDay::query()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($newStart, $newEnd) {
                    $s = $newStart->toDateString();
                    $e = $newEnd->toDateString();

                    $q->whereBetween('start_date', [$s, $e])
                      ->orWhereBetween('end_date', [$s, $e])
                      ->orWhere(function ($q2) use ($s, $e) {
                          $q2->where('start_date', '<=', $s)
                             ->where('end_date', '>=', $e);
                      });
                })
                ->exists();

            if ($overlap) {
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

        $this->copySelected = [];
        $this->copySelectAll = false;

        $this->showCopyModal = false;
        $this->resetPage();

        if ($copied > 0 || $skipped > 0) {
            session()->flash('message', tr('Copied') . ": {$copied} | " . tr('Skipped') . ": {$skipped}");
        }
    }



    public function exportCsv()
    {
        $companyId = $this->companyId();
        $rows = $this->rowsQuery($companyId)->get();

        $filename = 'exceptional-days-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            echo "\xEF\xBB\xBF";

            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Name', 'Description', 'Period Type', 'Start Date', 'End Date',
                'Apply', 'Deduction %', 'Grace Hours', 'Scope Type',
                'Notify Policy', 'Notified At', 'Is Active', 'Created By', 'Created At',
            ]);

            foreach ($rows as $r) {
                $apply = (string) $r->apply_on;

                $percent = 0.0;
                if ($apply === 'absence') $percent = (float) $r->absence_multiplier * 100.0;
                if ($apply === 'late') $percent = (float) $r->late_multiplier * 100.0;

                fputcsv($out, [
                    $r->name,
                    $r->description,
                    $r->period_type,
                    optional($r->start_date)->toDateString(),
                    optional($r->end_date)->toDateString(),
                    $apply,
                    number_format($percent, 2, '.', ''),
                    (string) ((int) $r->grace_hours),
                    (string) ($r->scope_type ?? 'all'),
                    $r->notify_policy,
                    optional($r->notified_at)?->toDateTimeString(),
                    $r->is_active ? 1 : 0,
                    $r->created_by,
                    optional($r->created_at)?->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, $filename);
    }



    private function companyColumnFor(string $table): ?string
    {
        if (!Schema::hasTable($table)) return null;

        if (Schema::hasColumn($table, 'company_id')) return 'company_id';
        if (Schema::hasColumn($table, 'saas_company_id')) return 'saas_company_id';

        return null;
    }

    private function coalesceNameExpr(string $table, array $preferredColumns, string $idColumn = 'id'): string
    {
        $cols = [];

        foreach ($preferredColumns as $col) {
            if (Schema::hasColumn($table, $col)) {
                $cols[] = $col;
            }
        }

        $cols[] = "CAST({$idColumn} AS CHAR)";

        return 'COALESCE(' . implode(', ', $cols) . ')';
    }

    private function loadScopeOptions(int $companyId): array
    {
        $departments = [];
        $sections = [];
        $employees = [];

        if (Schema::hasTable('departments')) {
            $companyCol = $this->companyColumnFor('departments');
            $nameExpr = $this->coalesceNameExpr(
                'departments',
                app()->isLocale('ar') ? ['name_ar', 'name', 'name_en'] : ['name_en', 'name', 'name_ar']
            );

            $departments = DB::table('departments')
                ->when($companyCol, fn ($q) => $q->where($companyCol, $companyId))
                ->select('id', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->get()
                ->toArray();
        }

        if (Schema::hasTable('sections')) {
            $companyCol = $this->companyColumnFor('sections');
            $nameExpr = $this->coalesceNameExpr(
                'sections',
                app()->isLocale('ar') ? ['name_ar', 'name', 'name_en'] : ['name_en', 'name', 'name_ar']
            );

            $sections = DB::table('sections')
                ->when($companyCol, fn ($q) => $q->where($companyCol, $companyId))
                ->select('id', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->get()
                ->toArray();
        }

               if (Schema::hasTable('employees')) {
            $companyCol = $this->companyColumnFor('employees');

            $nameExpr = $this->coalesceNameExpr(
                'employees',
                app()->isLocale('ar')
                    ? ['name_ar', 'name', 'full_name', 'name_en', 'employee_no']
                    : ['name_en', 'name', 'full_name', 'name_ar', 'employee_no']
            );

            $allowedBranchIds = $this->currentUserAllowedBranchIds($companyId);
            $branchCol = $this->employeeBranchColumn();

            $employees = DB::table('employees')
                ->when($companyCol, fn ($q) => $q->where($companyCol, $companyId))
                ->when($allowedBranchIds !== null, function ($q) use ($allowedBranchIds, $branchCol) {
                    if (!$branchCol) { $q->whereRaw('1=0'); return; }

                    $q->whereIn($branchCol, $allowedBranchIds);
                })
                ->select('id', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->limit(300)
                ->get()
                ->toArray();
        }

        return compact('departments', 'sections', 'employees');
    }

    public function render()
    {
        $companyId = $this->companyId();

        $rows = $this->rowsQuery($companyId)->paginate($this->perPage);

        $createdByMap = [];
        if (Schema::hasTable('users')) {
            $ids = $rows->getCollection()->pluck('created_by')->filter()->unique()->values()->all();
            if (!empty($ids)) {
                $createdByMap = DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->toArray();
            }
        }

        $stats = [
            'total_year' => AttendanceExceptionalDay::query()
                ->where('company_id', $companyId)
                ->whereYear('start_date', $this->year)
                ->count(),

            'active_now' => AttendanceExceptionalDay::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date', '>=', now()->toDateString())
                ->count(),

            'upcoming_month' => AttendanceExceptionalDay::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereYear('start_date', now()->year)
                ->whereMonth('start_date', now()->month)
                ->whereDate('start_date', '>', now()->toDateString())
                ->count(),

            'cost_estimate' => null,
        ];

        $opts = $this->loadScopeOptions($companyId);

        $copyRows = collect();
        if ($this->showCopyModal) {
            $copyRows = AttendanceExceptionalDay::query()
                ->where('company_id', $companyId)
                ->whereYear('start_date', (int) $this->copyFromYear)
                ->orderBy('start_date')
                ->limit(200)
                ->get();
        }

        return view('settings-module::livewire.attendance.exceptional-days.index', [
            'rows' => $rows,
            'stats' => $stats,

            'departmentsOptions' => $opts['departments'],
            'sectionsOptions' => $opts['sections'],
            'employeesOptions' => $opts['employees'],

            'createdByMap' => $createdByMap,

            'copyRows' => $copyRows,
        ])->layout('layouts.company-admin');
    }

  

    private function employeeBranchColumn(): ?string
    {
        if (!Schema::hasTable('employees')) return null;

        foreach (['branch_id'] as $c) {
            if (Schema::hasColumn('employees', $c)) return $c;
        }

        return null;
    }

    // ✅ فروع المستخدم الحالي حسب access_scope
    // ترجع:
    // - null  => بدون تقييد (all_branches)
    // - []    => لا يوجد فروع مسموحة (يظهر صفر موظفين)
    // - [..]  => IDs الفروع المسموحة
    private function currentUserAllowedBranchIds(int $companyId): ?array
    {
        $user = auth()->user();
        if (!$user) return [];

        $scope = (string) ($user->access_scope ?? 'all_branches');
        if (!in_array($scope, ['all_branches', 'my_branch', 'selected_branches'], true)) {
            $scope = 'all_branches';
        }

        if ($scope === 'all_branches') {
            return null; // ✅ no restriction
        }

        // company column on branches
        $branchesCompanyCol = null;
        if (Schema::hasTable('branches')) {
            foreach (['saas_company_id', 'company_id'] as $c) {
                if (Schema::hasColumn('branches', $c)) { $branchesCompanyCol = $c; break; }
            }
        }

        // my_branch => فرع الموظف المرتبط بالمستخدم
        if ($scope === 'my_branch') {
            $branchCol = $this->employeeBranchColumn();
            if (!$branchCol) return [];

            $bid = (int) ($user->employee?->{$branchCol} ?? 0);

            if ($bid <= 0 && Schema::hasTable('employees') && !empty($user->employee_id)) {
                $bid = (int) DB::table('employees')->where('id', (int) $user->employee_id)->value($branchCol);
            }

            return $bid > 0 ? [$bid] : [];
        }

        // selected_branches => من pivot عبر allowedBranches()
        if ($scope === 'selected_branches') {
            if (!method_exists($user, 'allowedBranches')) {
                return []; // ✅ امنياً: ما نعرض شيء إذا العلاقة غير موجودة
            }

            $ids = $user->allowedBranches()
                ->pluck('branches.id')
                ->map(fn ($v) => (int) $v)
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($ids)) return [];

            // تأكيد أنها لنفس الشركة (لو branches فيها company col)
            if ($branchesCompanyCol) {
                $ids = DB::table('branches')
                    ->where($branchesCompanyCol, $companyId)
                    ->whereIn('id', $ids)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
            }

            return $ids;
        }

        return null;
    }

  
}
