<?php

namespace Athka\SystemSettings\Livewire\Attendance;

use Livewire\Component;
use Livewire\WithPagination;
use Athka\SystemSettings\Models\WorkSchedule;
use Athka\SystemSettings\Models\WorkSchedulePeriod;
use Athka\SystemSettings\Models\WorkScheduleException;
use Athka\SystemSettings\Models\WorkScheduleShift;
use Illuminate\Support\Facades\DB;

class WorkSchedules extends Component
{
    use WithPagination;

    public $activeTab = 'schedules'; // schedules, shifts
    public $search = '';
    public $filterType = 'all'; // all, full_time, part_time, shifts, custom
    public $filterStatus = 'all'; // all, active, inactive, archived
    public $filterPeriod = 'all'; // all, morning, night, mixed
    public $filterDateStart = '';
    public $filterDateEnd = '';

    // Modal State
    public $showScheduleModal = false;
    public $isEditing = false;
    public $selectedScheduleId = null;

    // Form Data
    public $scheduleData = [
        'name' => '',
        'description' => '',
        'schedule_type' => 'full_time',
        'week_start_day' => 'saturday',
        'week_end_day' => 'friday',
        'work_days' => ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
        'is_default' => false,
        'is_active' => true,
        'periods' => [],
        'exceptions' => [],
    ];

    public $daysOfWeek = [];

    public function mount()
    {
        $this->daysOfWeek = [
            'saturday' => tr('Saturday'),
            'sunday' => tr('Sunday'),
            'monday' => tr('Monday'),
            'tuesday' => tr('Tuesday'),
            'wednesday' => tr('Wednesday'),
            'thursday' => tr('Thursday'),
            'friday' => tr('Friday'),
        ];

        $this->addPeriod(); // Start with one period
    }

    public function toggleAllDays()
    {
        if (count($this->scheduleData['work_days']) === 7) {
            $this->scheduleData['work_days'] = [];
        } else {
            $this->scheduleData['work_days'] = array_keys($this->daysOfWeek);
        }
    }

    public function clearAllFilters()
    {
        $this->reset(['search', 'filterStatus', 'filterType', 'filterPeriod', 'filterDateStart', 'filterDateEnd']);
        $this->resetPage();
    }

    public function copySchedule($id)
    {
        $schedule = WorkSchedule::with(['periods', 'exceptions'])->find($id);
        if ($schedule) {
            $this->openScheduleModal(); // Reset to fresh state
            $this->isEditing = false;
            $this->selectedScheduleId = null;
            
            $this->scheduleData = [
                'name' => $schedule->name . ' (' . tr('Copy') . ')',
                'description' => $schedule->description,
                'schedule_type' => $schedule->schedule_type ?? 'full_time',
                'week_start_day' => $schedule->week_start_day,
                'week_end_day' => $schedule->week_end_day,
                'work_days' => $schedule->work_days,
                'is_default' => false, // Copy should not be default by default
                'is_active' => $schedule->is_active,
                'periods' => $schedule->periods->map(fn($p) => [
                    'start_time' => substr($p->start_time, 0, 5),
                    'end_time' => substr($p->end_time, 0, 5),
                    'is_night_shift' => $p->is_night_shift,
                ])->toArray(),
                'exceptions' => $schedule->exceptions->map(fn($e) => [
                    'day_of_week' => $e->day_of_week ?? 'friday',
                    'start_time' => substr($e->start_time, 0, 5),
                    'end_time' => substr($e->end_time, 0, 5),
                    'is_night_shift' => (bool)$e->is_night_shift,
                    'is_active' => (bool)($e->is_active ?? true),
                ])->toArray(),
            ];
            $this->showScheduleModal = true;
        }
    }

    public function exportSchedules()
    {
        // Build query with same filters as render() method
        $companyId = auth()->user()->saas_company_id;
        $query = WorkSchedule::query()->where('saas_company_id', $companyId);
        
        // Apply existing filters
        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }
        
        if ($this->filterStatus !== 'all') {
            $query->where('is_active', $this->filterStatus === 'active');
        }
        
        if ($this->filterType !== 'all') {
            $query->where('schedule_type', $this->filterType);
        }
        
        // Load periods and exceptions relationships
        $schedules = $query->with(['periods', 'exceptions'])->orderBy('name')->get();
        
        if ($schedules->isEmpty()) {
            $this->dispatch('toast',
                type: 'warning',
                message: tr('No schedules found to export.')
            );
            return;
        }
        
        return $this->exportToCsv($schedules);
    }

    private function exportToCsv($schedules)
    {
        $filename = 'work_schedules_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($schedules) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8 (Excel friendly)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header row
            fputcsv($file, [
                tr('Schedule Name'),
                tr('Description'),
                tr('Timing Slot'),
                tr('Week Matrix'),
                tr('Exceptions'),
                tr('Week Start'),
                tr('Week End'),
                tr('State'),
                tr('Created At'),
            ]);

            // Data rows
            foreach ($schedules as $schedule) {
                // Format timing slot from periods relationship
                $timingSlot = '';
                $periods = $schedule->periods;
                if ($periods && $periods->count() > 0) {
                    $times = [];
                    foreach ($periods as $period) {
                        $start = substr($period->start_time ?? '', 0, 5);
                        $end = substr($period->end_time ?? '', 0, 5);
                        if ($start && $end) {
                            $times[] = $start . ' - ' . $end;
                        } elseif ($start) {
                            $times[] = $start;
                        }
                    }
                    $timingSlot = implode(' | ', $times);
                }

                // Format week matrix from work_days array
                $weekMatrix = '';
                $daysMap = [
                    'saturday' => tr('Saturday'),
                    'sunday' => tr('Sunday'),
                    'monday' => tr('Monday'),
                    'tuesday' => tr('Tuesday'),
                    'wednesday' => tr('Wednesday'),
                    'thursday' => tr('Thursday'),
                    'friday' => tr('Friday'),
                ];
                
                $activeDays = [];
                if (is_array($schedule->work_days)) {
                    // Sorting days according to the week order if possible, 
                    // or just follow the order in work_days
                    foreach ($schedule->work_days as $day) {
                        if (isset($daysMap[$day])) {
                            $activeDays[] = $daysMap[$day];
                        }
                    }
                }
                $weekMatrix = implode(', ', $activeDays);

                // Format exceptions
                $exceptionsText = '';
                $exceptions = $schedule->exceptions;
                if ($exceptions && $exceptions->count() > 0) {
                    $exceptionsList = [];
                    foreach ($exceptions as $exception) {
                        $dayName = isset($daysMap[$exception->day_of_week]) ? $daysMap[$exception->day_of_week] : ucfirst($exception->day_of_week ?? '');
                        $exceptionDetail = $dayName;
                        
                        // Time range
                        if ($exception->start_time && $exception->end_time) {
                            $exceptionDetail .= ' (' . substr($exception->start_time, 0, 5) . ' - ' . substr($exception->end_time, 0, 5) . ')';
                        }
                        
                        // Status
                        if (!$exception->is_active) {
                            $exceptionDetail .= ' [' . tr('Disabled') . ']';
                        }
                        
                        if ($exceptionDetail) {
                            $exceptionsList[] = $exceptionDetail;
                        }
                    }
                    $exceptionsText = implode(' | ', $exceptionsList);
                }

                // State
                $state = $schedule->is_active ? tr('Active') : tr('Inactive');

                fputcsv($file, [
                    $schedule->name,
                    $schedule->description ?? '',
                    $timingSlot,
                    $weekMatrix,
                    $exceptionsText,
                    $daysMap[$schedule->week_start_day] ?? ucfirst($schedule->week_start_day ?? ''),
                    $daysMap[$schedule->week_end_day] ?? ucfirst($schedule->week_end_day ?? ''),
                    $state,
                    $schedule->created_at ? $schedule->created_at->format('Y-m-d H:i') : 'N/A',
                ]);
            }
            fclose($file);
        };

        $this->dispatch('toast',
            type: 'success',
            message: tr('Export completed successfully!')
        );

        return response()->stream($callback, 200, $headers);
    }

    public function addPeriod()
    {
        if (count($this->scheduleData['periods']) >= 4) {
            $this->dispatch('toast', type: 'warning', message: tr('Maximum of 4 periods allowed per day.'));
            return;
        }

        $this->scheduleData['periods'][] = [
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_night_shift' => false,
        ];
    }

    public function removePeriod($index)
    {
        unset($this->scheduleData['periods'][$index]);
        $this->scheduleData['periods'] = array_values($this->scheduleData['periods']);
    }

    public function addException()
    {
        $this->scheduleData['exceptions'][] = [
            'day_of_week' => 'friday',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'is_night_shift' => false,
            'is_active' => true,
        ];
    }

    public function removeException($index)
    {
        unset($this->scheduleData['exceptions'][$index]);
        $this->scheduleData['exceptions'] = array_values($this->scheduleData['exceptions']);
    }

    public function openScheduleModal($id = null)
    {
        $this->resetValidation();
        if ($id) {
            $this->isEditing = true;
            $this->selectedScheduleId = $id;
            $schedule = WorkSchedule::with(['periods', 'exceptions'])->find($id);
            $this->scheduleData = [
                'name' => $schedule->name,
                'description' => $schedule->description,
                'schedule_type' => $schedule->schedule_type ?? 'full_time',
                'week_start_day' => $schedule->week_start_day,
                'week_end_day' => $schedule->week_end_day,
                'work_days' => $schedule->work_days,
                'is_default' => $schedule->is_default,
                'is_active' => $schedule->is_active,
                'periods' => $schedule->periods->map(fn($p) => [
                    'start_time' => substr($p->start_time, 0, 5),
                    'end_time' => substr($p->end_time, 0, 5),
                    'is_night_shift' => $p->is_night_shift,
                ])->toArray(),
                'exceptions' => $schedule->exceptions->map(fn($e) => [
                    'day_of_week' => $e->day_of_week ?? 'friday',
                    'start_time' => substr($e->start_time, 0, 5),
                    'end_time' => substr($e->end_time, 0, 5),
                    'is_night_shift' => (bool)$e->is_night_shift,
                    'is_active' => (bool)($e->is_active ?? true),
                ])->toArray(),
            ];
        } else {
            $this->isEditing = false;
            $this->selectedScheduleId = null;
            $this->scheduleData = [
                'name' => '',
                'description' => '',
                'schedule_type' => 'full_time',
                'week_start_day' => 'saturday',
                'week_end_day' => 'friday',
                'work_days' => ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
                'is_default' => false,
                'is_active' => true,
                'periods' => [
                    ['start_time' => '08:00', 'end_time' => '17:00', 'is_night_shift' => false]
                ],
                'exceptions' => [],
            ];
        }
        $this->showScheduleModal = true;
    }

    public function saveSchedule()
    {
        $this->validate([
            'scheduleData.name' => 'required|min:3',
            'scheduleData.periods' => 'required|array|min:1|max:4',
            'scheduleData.periods.*.start_time' => 'required',
            'scheduleData.periods.*.end_time' => 'required',
        ]);

        // Custom Validation for Durations and Night Shifts
        foreach ($this->scheduleData['periods'] as $index => $period) {
            $start = \Carbon\Carbon::parse($period['start_time']);
            $end = \Carbon\Carbon::parse($period['end_time']);
            
            if ($period['is_night_shift']) {
                if (!$end->lessThan($start)) {
                    $this->addError("scheduleData.periods.{$index}.end_time", tr('Night shift end time must be before start time (e.g. 22:00 to 06:00).'));
                    return;
                }
                $end->addDay();
            } else {
                if ($end->lessThan($start)) {
                    $this->addError("scheduleData.periods.{$index}.end_time", tr('End time must be after start time for standard shifts.'));
                    return;
                }
            }

            $durationMinutes = $end->diffInMinutes($start);
            if ($durationMinutes < 30) {
                $this->addError("scheduleData.periods.{$index}.end_time", tr('Minimum period duration is 30 minutes.'));
                return;
            }
            if ($durationMinutes > 720) { // 12 hours
                $this->addError("scheduleData.periods.{$index}.end_time", tr('Maximum period duration is 12 hours.'));
                return;
            }
        }

        DB::transaction(function () {
            if ($this->scheduleData['is_default']) {
                WorkSchedule::where('saas_company_id', $companyId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $schedule = $this->isEditing 
                ? WorkSchedule::find($this->selectedScheduleId) 
                : new WorkSchedule();

            $schedule->fill([
                'name' => $this->scheduleData['name'],
                'description' => $this->scheduleData['description'],
                'schedule_type' => $this->scheduleData['schedule_type'] ?? 'full_time',
                'week_start_day' => $this->scheduleData['week_start_day'],
                'week_end_day' => $this->scheduleData['week_end_day'],
                'work_days' => $this->scheduleData['work_days'],
                'is_default' => $this->scheduleData['is_default'],
                'is_active' => $this->scheduleData['is_active'],
                'saas_company_id' => $companyId,
                'created_by_user_id' => auth()->id(),
            ]);
            $schedule->save();

            // Sync Periods
            $schedule->periods()->delete();
            foreach ($this->scheduleData['periods'] as $index => $period) {
                $schedule->periods()->create([
                    'start_time' => $period['start_time'],
                    'end_time' => $period['end_time'],
                    'is_night_shift' => $period['is_night_shift'],
                    'sort_order' => $index,
                ]);
            }

            // Sync Exceptions
            $schedule->exceptions()->delete();
            foreach ($this->scheduleData['exceptions'] as $exception) {
                $schedule->exceptions()->create([
                    'day_of_week' => $exception['day_of_week'],
                    'specific_date' => null, // Removed as per request
                    'start_time' => $exception['start_time'],
                    'end_time' => $exception['end_time'],
                    'is_night_shift' => $exception['is_night_shift'],
                    'is_active' => $exception['is_active'] ?? true,
                ]);
            }
        });

        $this->showScheduleModal = false;
        $this->dispatch('toast', type: 'success', message: $this->isEditing ? tr('Work schedule updated successfully.') : tr('New work schedule created successfully.'));
    }

    public function deleteSchedule($id)
    {
        // Add check if linked to employees later
        WorkSchedule::destroy($id);
        $this->dispatch('toast', type: 'success', message: tr('Work schedule removed successfully.'));
    }

    public function toggleStatus($id)
    {
        $schedule = WorkSchedule::find($id);
        if ($schedule) {
            $schedule->is_active = !$schedule->is_active;
            $schedule->save();
        }
    }

    public function render()
    {
        $companyId = auth()->user()->saas_company_id;
        $schedules = WorkSchedule::with(['periods', 'exceptions'])
            ->where('saas_company_id', $companyId)
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->filterStatus !== 'all', function($q) {
                if ($this->filterStatus === 'archived') return $q->onlyTrashed();
                return $q->where('is_active', $this->filterStatus === 'active');
            })
            ->when($this->filterType !== 'all', fn($q) => $q->where('schedule_type', $this->filterType))
            ->when($this->filterDateStart, fn($q) => $q->whereDate('created_at', '>=', $this->filterDateStart))
            ->when($this->filterDateEnd, fn($q) => $q->whereDate('created_at', '<=', $this->filterDateEnd))
            ->when($this->filterPeriod !== 'all', function($q) {
                // This logic depends on period timing, but we'll approximate 
                // based on existing periods if needed or a dedicated column
                // For now filtering by is_night_shift or mixed
                if ($this->filterPeriod === 'mixed') {
                    $q->whereHas('periods', fn($pq) => $pq->where('is_night_shift', true))
                      ->whereHas('periods', fn($pq) => $pq->where('is_night_shift', false));
                } elseif ($this->filterPeriod === 'night') {
                    $q->whereHas('periods', fn($pq) => $pq->where('is_night_shift', true))
                      ->whereDoesntHave('periods', fn($pq) => $pq->where('is_night_shift', false));
                } elseif ($this->filterPeriod === 'morning') {
                    $q->whereDoesntHave('periods', fn($pq) => $pq->where('is_night_shift', true));
                }
            })
            ->paginate(10);

        return view('systemsettings::livewire.attendance.work-schedules', [
            'schedules' => $schedules
        ])->layout('layouts.company-admin');
    }
}





