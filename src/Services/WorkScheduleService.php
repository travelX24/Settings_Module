<?php

namespace Athka\SystemSettings\Services;

use Athka\SystemSettings\Models\WorkSchedule;
use Athka\SystemSettings\Models\OfficialHolidayOccurrence;
use Athka\SystemSettings\Models\AttendanceExceptionalDay;
use Athka\Employees\Models\Employee;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WorkScheduleService
{
    /**
     * Get the effective schedule for a company/employee.
     */
    public function getEffectiveSchedule(int $companyId, ?Employee $employee = null, ?string $date = null): ?WorkSchedule
    {
        $resolveDate = $date ?: now()->toDateString();

        // 1. Try employee-specific assignment
        if ($employee) {
            $assignment = DB::table('employee_work_schedules')
                ->where('employee_id', $employee->id)
                ->where('start_date', '<=', $resolveDate)
                ->where(function ($q) use ($resolveDate) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', $resolveDate);
                })
                ->orderByDesc('start_date')
                ->first();

            if ($assignment) {
                $schedule = WorkSchedule::query()
                    ->with(['periods', 'exceptions'])
                    ->find($assignment->work_schedule_id);
                
                if ($schedule) return $schedule;
            }
        }

        // 2. Fallback to default company schedule
        $schedule = WorkSchedule::query()
            ->with(['periods', 'exceptions'])
            ->where('saas_company_id', $companyId)
            ->where('is_default', true)
            ->first();
        
        if ($schedule) return $schedule;
        
        // 3. Fallback to latest schedule
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
     * Check if a specific date is a company-wide exceptional day for an employee.
     */
    public function getExceptionalDay(int $companyId, string $date, $employee = null)
    {
        $day = Carbon::parse($date);

        // ✅ Check Official Holidays first
        if (class_exists(\Athka\SystemSettings\Models\OfficialHolidayOccurrence::class)) {
            $holiday = \Athka\SystemSettings\Models\OfficialHolidayOccurrence::query()
                ->where('company_id', $companyId)
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->first();

            if ($holiday) {
                return (object) [
                    'id'                  => $holiday->id,
                    'name'                => $holiday->name,
                    'name_ar'             => $holiday->name_ar ?? $holiday->name,
                    'name_en'             => $holiday->name_en ?? $holiday->name,
                    'is_holiday'          => true,
                    'is_official_holiday' => true,
                ];
            }
        }
        
        $exceptions = AttendanceExceptionalDay::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get();

        foreach ($exceptions as $ce) {
            $applyOn = $ce->apply_on ?: 'everyone';
            if ($applyOn === 'everyone') return $ce;

            if (!$employee) continue;

            $include = is_array($ce->include) ? $ce->include : (json_decode($ce->include, true) ?: []);
            
            if ($applyOn === 'employees' || $applyOn === 'absence') {
                $targetIds = $include['employees'] ?? [];
                if (in_array((string)$employee->id, $targetIds)) return $ce;
            }
            
            if ($applyOn === 'departments') {
                $targetIds = $include['departments'] ?? [];
                if (in_array((string)$employee->department_id, $targetIds)) return $ce;
            }

            if ($applyOn === 'locations' || $applyOn === 'branches') {
                $targetIds = $include['branches'] ?? $include['locations'] ?? [];
                if (in_array((string)$employee->branch_id, $targetIds)) return $ce;
            }
        }

        return null;
    }

    /**
     * Get metrics for a specific date (internal usage).
     */
    public function getMetricsForDate(string $dateStr, ?WorkSchedule $schedule, $holidays, ?Employee $employee = null): array
    {
        $dayKey = $this->getDayKey(Carbon::parse($dateStr));
        $isHoliday = $holidays->first(fn($h) => $dateStr >= Carbon::parse($h->start_date)->toDateString() && $dateStr <= Carbon::parse($h->end_date)->toDateString());
        
        // Also check for Company-Wide Exceptional Days
        $companyId = $schedule ? $schedule->saas_company_id : ($employee ? $employee->saas_company_id : null);
        $exceptionalDay = $companyId ? $this->getExceptionalDay((int)$companyId, $dateStr, $employee) : null;

        $scheduledMinutes = 0;
        $periodsOut = collect();
        $isWorkday = false;
        
        $holidayName = $isHoliday ? ($isHoliday->template?->name ?? 'Holiday') : ($exceptionalDay ? '⭐ ' . tr('Exceptional Day') . ': ' . $exceptionalDay->name : null);
        $isHolidayFinal = $isHoliday || $exceptionalDay;

        if ($schedule && !$isHolidayFinal) {
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

        $firstCheckIn = $periodsOut->isNotEmpty() ? $periodsOut->first()['start_time'] : null;
        $lastCheckOut = $periodsOut->isNotEmpty() ? $periodsOut->last()['end_time'] : null;

        return [
            'status' => $isHolidayFinal ? 'holiday' : ($isWorkday && $periodsOut->isNotEmpty() ? 'workday' : 'off'),
            'is_holiday' => (bool)$isHolidayFinal,
            'holiday_name' => $holidayName,
            'is_workday' => $isWorkday,
            'day_key' => $dayKey,
            'total_minutes' => $scheduledMinutes,
            'hours' => $scheduledMinutes > 0 ? ($scheduledMinutes / 60) : null,
            'check_in' => $firstCheckIn,
            'check_out' => $lastCheckOut,
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
