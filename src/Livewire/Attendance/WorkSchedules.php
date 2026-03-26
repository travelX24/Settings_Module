<?php

namespace Athka\SystemSettings\Livewire\Attendance;

use Livewire\Component;
use Livewire\WithPagination;
use Athka\SystemSettings\Models\WorkSchedule;
use Athka\SystemSettings\Services\WorkScheduleService;
use Illuminate\Support\Facades\DB;

class WorkSchedules extends Component
{
    use WithPagination;

    public $activeTab = 'schedules'; 
    public $search = '';
    public $filterStatus = 'all'; 
    public $filterType = 'all'; 
    public $filterPeriod = 'all';
    public $filterDateStart = '';
    public $filterDateEnd = '';

    public $showScheduleModal = false;
    public $isEditing = false;
    public $selectedId = null;

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

    protected $service;

    public function boot(WorkScheduleService $service)
    {
        $this->service = $service;
    }

    public function mount()
    {
        $this->authorize('settings.attendance.view');
        $this->addPeriod();
    }

    public function addPeriod() {
        if (count($this->scheduleData['periods']) < 4) {
            $this->scheduleData['periods'][] = ['start_time' => '08:00', 'end_time' => '17:00', 'is_night_shift' => false];
        }
    }

    public function removePeriod($index) {
        unset($this->scheduleData['periods'][$index]);
        $this->scheduleData['periods'] = array_values($this->scheduleData['periods']);
    }

    public function addException() {
        $this->scheduleData['exceptions'][] = ['day_of_week' => 'friday', 'start_time' => '08:00', 'end_time' => '12:00', 'is_night_shift' => false, 'is_active' => true];
    }

    public function removeException($index) {
        unset($this->scheduleData['exceptions'][$index]);
        $this->scheduleData['exceptions'] = array_values($this->scheduleData['exceptions']);
    }

    public function openScheduleModal($id = null)
    {
        $this->resetValidation();
        if ($id) {
            $this->isEditing = true;
            $this->selectedId = $id;
            $companyId = auth()->user()->saas_company_id;
            $schedule = WorkSchedule::where('saas_company_id', $companyId)
                ->with(['periods', 'exceptions'])
                ->findOrFail($id);
            $this->scheduleData = [
                'name' => $schedule->name,
                'description' => $schedule->description,
                'schedule_type' => $schedule->schedule_type,
                'week_start_day' => $schedule->week_start_day,
                'week_end_day' => $schedule->week_end_day,
                'work_days' => (array)$schedule->work_days,
                'is_default' => (bool)$schedule->is_default,
                'is_active' => (bool)$schedule->is_active,
                'periods' => $schedule->periods->map(fn($p) => ['start_time' => substr($p->start_time, 0, 5), 'end_time' => substr($p->end_time, 0, 5), 'is_night_shift' => (bool)$p->is_night_shift])->toArray(),
                'exceptions' => $schedule->exceptions->map(fn($e) => ['day_of_week' => $e->day_of_week, 'start_time' => substr($e->start_time, 0, 5), 'end_time' => substr($e->end_time, 0, 5), 'is_night_shift' => (bool)$e->is_night_shift, 'is_active' => (bool)$e->is_active])->toArray(),
            ];
        } else {
            $this->reset(['isEditing', 'selectedId']);
            $this->scheduleData = [
                'name' => '', 'description' => '', 'schedule_type' => 'full_time',
                'week_start_day' => 'saturday', 'week_end_day' => 'friday',
                'work_days' => ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
                'is_default' => false, 'is_active' => true,
                'periods' => [['start_time' => '08:00', 'end_time' => '17:00', 'is_night_shift' => false]],
                'exceptions' => [],
            ];
        }
        $this->showScheduleModal = true;
    }

    public function save()
    {
        $this->authorize('settings.attendance.manage');
        $this->validate(['scheduleData.name' => 'required|min:3', 'scheduleData.periods' => 'required|array|min:1']);

        $companyId = auth()->user()->saas_company_id;
        $this->service->saveSchedule($companyId, $this->scheduleData, $this->selectedId);
 
        $this->showScheduleModal = false;
        $this->resetPage(); 
        $this->dispatch('toast', type: 'success', message: tr('Work schedule saved successfully.'));
    }
 
    public function clearAllFilters()
    {
        $this->reset(['search', 'filterStatus', 'filterType', 'filterPeriod', 'filterDateStart', 'filterDateEnd']);
        $this->resetPage();
    }
 
    public function exportSchedules()
    {
        $this->authorize('settings.attendance.view');
        $companyId = auth()->user()->saas_company_id;

        $filename = "work_schedules_" . now()->format('Y_m_d_His') . ".csv";
        
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = [tr('Name'), tr('Type'), tr('Status'), tr('Work Days'), tr('Periods Count')];

        $callback = function() use ($companyId, $columns) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fputcsv($file, $columns);

            $schedules = WorkSchedule::where('saas_company_id', $companyId)
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->when($this->filterStatus !== 'all', fn($q) => $q->where('is_active', $this->filterStatus === 'active'))
                ->when($this->filterType !== 'all', fn($q) => $q->where('schedule_type', $this->filterType))
                ->with('periods')
                ->get();

            foreach ($schedules as $s) {
                fputcsv($file, [
                    $s->name,
                    tr(ucfirst($s->schedule_type)),
                    $s->is_active ? tr('Active') : tr('Inactive'),
                    implode(', ', array_map(fn($d) => tr(ucfirst($d)), (array)$s->work_days)),
                    $s->periods->count()
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function confirmDelete($id)
    {
        $this->authorize('settings.attendance.manage');
        $this->dispatch('open-confirm-schedule-delete', id: $id);
    }

    public function deleteSchedule($id)
    {
        $this->authorize('settings.attendance.manage');
        $companyId = auth()->user()->saas_company_id;
        
        $schedule = WorkSchedule::where('saas_company_id', $companyId)->find($id);
        
        if (!$schedule) {
            $this->dispatch('toast', type: 'error', message: tr('Schedule not found.'));
            return;
        }

        if ($schedule->is_default) {
            $this->dispatch('toast', type: 'error', message: tr('Default schedule cannot be deleted.'));
            return;
        }

        // Check if linked to employees
        $isLinked = \Athka\Attendance\Models\EmployeeWorkSchedule::where('work_schedule_id', $id)->exists();
        if ($isLinked) {
            $this->dispatch('toast', type: 'error', message: tr('Cannot delete schedule linked to employees.'));
            return;
        }

        $schedule->forceDelete();
        $this->resetPage(); 
        $this->dispatch('toast', type: 'success', message: tr('Deleted permanently from database.'));
    }

    public function toggleStatus($id)
    {
        $this->authorize('settings.attendance.manage');
        $companyId = auth()->user()->saas_company_id;
        $s = WorkSchedule::where('saas_company_id', $companyId)->find($id);
        if ($s) { $s->is_active = !$s->is_active; $s->save(); }
    }

    public function copySchedule($id)
    {
        $this->authorize('settings.attendance.manage');
        $companyId = auth()->user()->saas_company_id;
        
        $schedule = WorkSchedule::where('saas_company_id', $companyId)
            ->with(['periods', 'exceptions'])
            ->findOrFail($id);

        $newData = [
            'name' => tr('Copy of') . ' ' . $schedule->name,
            'description' => $schedule->description,
            'schedule_type' => $schedule->schedule_type,
            'week_start_day' => $schedule->week_start_day,
            'week_end_day' => $schedule->week_end_day,
            'work_days' => $schedule->work_days,
            'is_default' => false,
            'is_active' => true,
            'periods' => $schedule->periods->map(fn($p) => [
                'start_time' => substr($p->start_time, 0, 5),
                'end_time' => substr($p->end_time, 0, 5),
                'is_night_shift' => $p->is_night_shift
            ])->toArray(),
            'exceptions' => $schedule->exceptions->map(fn($e) => [
                'day_of_week' => $e->day_of_week,
                'start_time' => substr($e->start_time, 0, 5),
                'end_time' => substr($e->end_time, 0, 5),
                'is_night_shift' => $e->is_night_shift,
                'is_active' => $e->is_active
            ])->toArray(),
        ];

        $this->service->saveSchedule($companyId, $newData);
        $this->dispatch('toast', type: 'success', message: tr('Schedule duplicated successfully.'));
    }

    public function render()
    {
        $query = WorkSchedule::query()->where('saas_company_id', auth()->user()->saas_company_id);
        
        $schedules = $query->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filterStatus !== 'all', fn($q) => $q->where('is_active', $this->filterStatus === 'active'))
            ->when($this->filterType !== 'all', fn($q) => $q->where('schedule_type', $this->filterType))
            ->latest()
            ->paginate(10);

        return view('systemsettings::livewire.attendance.work-schedules', [
            'schedules' => $schedules,
            'daysOfWeek' => [
                'saturday' => tr('Saturday'), 'sunday' => tr('Sunday'), 'monday' => tr('Monday'),
                'tuesday' => tr('Tuesday'), 'wednesday' => tr('Wednesday'), 'thursday' => tr('Thursday'), 'friday' => tr('Friday'),
            ]
        ])->layout('layouts.company-admin');
    }
}
