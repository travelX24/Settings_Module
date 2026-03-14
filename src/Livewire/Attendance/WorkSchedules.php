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

    public function openModal($id = null)
    {
        $this->resetValidation();
        if ($id) {
            $this->isEditing = true;
            $this->selectedId = $id;
            $schedule = WorkSchedule::with(['periods', 'exceptions'])->find($id);
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
        $this->dispatch('toast', type: 'success', message: tr('Work schedule saved successfully.'));
    }

    public function delete($id)
    {
        $this->authorize('settings.attendance.manage');
        if ($this->service->deleteSchedule($id)) {
            $this->dispatch('toast', type: 'success', message: tr('Deleted successfully.'));
        } else {
            $this->dispatch('toast', type: 'error', message: tr('Default schedule cannot be deleted.'));
        }
    }

    public function toggleStatus($id)
    {
        $this->authorize('settings.attendance.manage');
        $s = WorkSchedule::find($id);
        if ($s) { $s->is_active = !$s->is_active; $s->save(); }
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
