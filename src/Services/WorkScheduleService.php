<?php

namespace Athka\SystemSettings\Services;

use Athka\SystemSettings\Models\WorkSchedule;
use Athka\SystemSettings\Models\OfficialHolidayOccurrence;
use Athka\Employees\Models\Employee;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WorkScheduleService
{
    /**
     * Get the effective schedule for a company/employee.
     */
    public function getEffectiveSchedule(int $companyId, ?Employee $employee = null): ?WorkSchedule
    {
        // 1. Fallback to default company schedule
        $schedule = WorkSchedule::query()
            ->with(['periods', 'exceptions'])
            ->where('saas_company_id', $companyId)
            ->where('is_default', true)
            ->first();
        
        if ($schedule) return $schedule;
        
        // 2. Fallback to latest schedule
        return WorkSchedule::query()
            ->with(['periods', 'exceptions'])
            ->where('saas_company_id', $companyId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Get holidays in a specific date range.
     */
    public function getHolidays(int $companyId, string $from, string $to)
    {
        return OfficialHolidayOccurrence::query()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get();
    }

    /**
     * Save/Update a work schedule through a service.
     */
    public function saveSchedule(int $companyId, array $data, ?int $id = null): WorkSchedule
    {
        return DB::transaction(function () use ($companyId, $data, $id) {
            if (!empty($data['is_default'])) {
                WorkSchedule::where('saas_company_id', $companyId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $schedule = $id ? WorkSchedule::find($id) : new WorkSchedule();
            
            $schedule->fill(array_merge($data, [
                'saas_company_id' => $companyId,
                'created_by_user_id' => auth()->id(),
            ]));
            
            $schedule->save();

            // Sync Periods
            if (isset($data['periods'])) {
                $schedule->periods()->delete();
                foreach ($data['periods'] as $index => $period) {
                    $schedule->periods()->create([
                        'start_time' => $period['start_time'],
                        'end_time' => $period['end_time'],
                        'is_night_shift' => $period['is_night_shift'] ?? false,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Sync Exceptions
            if (isset($data['exceptions'])) {
                $schedule->exceptions()->delete();
                foreach ($data['exceptions'] as $exc) {
                    $schedule->exceptions()->create([
                        'day_of_week' => $exc['day_of_week'],
                        'start_time' => $exc['start_time'],
                        'end_time' => $exc['end_time'],
                        'is_night_shift' => $exc['is_night_shift'] ?? false,
                        'is_active' => $exc['is_active'] ?? true,
                    ]);
                }
            }

            return $schedule;
        });
    }

    /**
     * Safely delete a schedule.
     */
    public function deleteSchedule(int $id): bool
    {
        $schedule = WorkSchedule::find($id);
        if (!$schedule || $schedule->is_default) return false;
        
        return $schedule->delete();
    }

    /**
     * Get the day key (e.g., 'sunday', 'monday').
     */
    public function getDayKey(Carbon $date): string
    {
        $map = [
            0 => 'sunday', 1 => 'monday', 2 => 'tuesday', 3 => 'wednesday',
            4 => 'thursday', 5 => 'friday', 6 => 'saturday',
        ];
        return $map[(int) $date->dayOfWeek] ?? 'sunday';
    }

    /**
     * Get metrics for a specific date (internal usage).
     */
    public function getMetricsForDate(string $dateStr, ?WorkSchedule $schedule, $holidays): array
    {
        $dayKey = $this->getDayKey(Carbon::parse($dateStr));
        $isHoliday = $holidays->first(fn($h) => $dateStr >= (string)$h->start_date && $dateStr <= (string)$h->end_date);

        $scheduledMinutes = 0;
        $periodsOut = collect();
        $isWorkday = false;
        $holidayName = $isHoliday ? ($isHoliday->template?->name ?? 'Holiday') : null;

        if ($schedule && !$isHoliday) {
            $isWorkday = in_array($dayKey, (array)$schedule->work_days);
            
            if ($schedule->exceptions) {
                $exceptions = $schedule->exceptions->filter(function($e) use ($dateStr, $dayKey){
                    return $e->is_active && (($e->specific_date && (string)$e->specific_date === $dateStr) || ($e->day_of_week === $dayKey));
                });
                
                if ($exceptions->isNotEmpty()) {
                    $isWorkday = true;
                    $periodsOut = $exceptions->map(fn($e) => $this->formatPeriod($e));
                }
            }

            if ($periodsOut->isEmpty() && $isWorkday && $schedule->periods) {
                $periodsOut = $schedule->periods->map(fn($p) => $this->formatPeriod($p));
            }

            foreach ($periodsOut as $p) {
                $scheduledMinutes += $this->calculateMinutes($dateStr, $p['start_time'], $p['end_time'], $p['is_night_shift']);
            }
        }

        return [
            'status' => $isHoliday ? 'holiday' : ($isWorkday && $periodsOut->isNotEmpty() ? 'workday' : 'off'),
            'is_holiday' => (bool)$isHoliday,
            'holiday_name' => $holidayName,
            'is_workday' => $isWorkday,
            'total_minutes' => $scheduledMinutes,
            'periods' => $periodsOut,
        ];
    }

    protected function formatPeriod($p): array
    {
        return [
            'start_time' => substr((string)$p->start_time, 0, 5),
            'end_time' => substr((string)$p->end_time, 0, 5),
            'is_night_shift' => (bool)$p->is_night_shift,
        ];
    }

    protected function calculateMinutes(string $date, string $start, string $end, bool $isNight): int
    {
        $s = Carbon::parse("$date $start");
        $e = Carbon::parse("$date $end");
        if ($isNight || $e->lt($s)) $e->addDay();
        return (int) $s->diffInMinutes($e);
    }
}
