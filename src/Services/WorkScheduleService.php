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
    public function getEffectiveSchedule(int $companyId, ?Employee $employee = null, ?string $date = null, bool $fallback = true): ?WorkSchedule
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

        if (!$fallback) return null;

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
            ->with('template')
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
     * Get employee requests (Leaves, Missions, Permissions) for a range.
     */
    public function getEmployeeRequests(int $employeeId, string $from, string $to): array
    {
        $requests = [
            'leaves' => collect(),
            'missions' => collect(),
            'permissions' => collect(),
        ];

        // Leaves
        if (class_exists(\Athka\Attendance\Models\AttendanceLeaveRequest::class)) {
            $requests['leaves'] = \Athka\Attendance\Models\AttendanceLeaveRequest::query()
                ->where('employee_id', $employeeId)
                ->where('status', 'approved')
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('start_date', [$from, $to])
                      ->orWhereBetween('end_date', [$from, $to])
                      ->orWhere(function ($qq) use ($from, $to) {
                          $qq->where('start_date', '<=', $from)->where('end_date', '>=', $to);
                      });
                })
                ->with('policy')
                ->get();
        }

        // Missions
        if (class_exists(\Athka\Attendance\Models\AttendanceMissionRequest::class)) {
            $requests['missions'] = \Athka\Attendance\Models\AttendanceMissionRequest::query()
                ->where('employee_id', $employeeId)
                ->where('status', 'approved')
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('start_date', [$from, $to])
                      ->orWhereBetween('end_date', [$from, $to])
                      ->orWhere(function ($qq) use ($from, $to) {
                          $qq->where('start_date', '<=', $from)->where('end_date', '>=', $to);
                      });
                })
                ->get();
        }

        // Permissions (Exits)
        if (class_exists(\Athka\Attendance\Models\AttendancePermissionRequest::class)) {
            $requests['permissions'] = \Athka\Attendance\Models\AttendancePermissionRequest::query()
                ->where('employee_id', $employeeId)
                ->where('status', 'approved')
                ->whereBetween('permission_date', [$from, $to])
                ->get();
        }

        return $requests;
    }

    /**
     * Get metrics for a specific date (internal usage).
     */
    public function getMetricsForDate(string $dateStr, ?WorkSchedule $schedule, $holidays, ?Employee $employee = null, array $requests = []): array
    {
        $dayKey = $this->getDayKey(Carbon::parse($dateStr));
        $isHoliday = $holidays->first(fn($h) => $dateStr >= Carbon::parse($h->start_date)->toDateString() && $dateStr <= Carbon::parse($h->end_date)->toDateString());
        
        // Also check for Company-Wide Exceptional Days
        $companyId = $schedule ? $schedule->saas_company_id : ($employee ? $employee->saas_company_id : null);
        $exceptionalDay = $companyId ? $this->getExceptionalDay((int)$companyId, $dateStr, $employee) : null;

        $scheduledMinutes = 0;
        $periodsOut = collect();
        $isWorkday = false;
        
        $holidayName = $isHoliday ? ($isHoliday->template?->name ?? tr('Holiday')) : ($exceptionalDay ? ($exceptionalDay->name_ar ?: $exceptionalDay->name) : null);
        $isHolidayFinal = $isHoliday || $exceptionalDay;

        // --- Handle Leaves ---
        $leaves = $requests['leaves'] ?? collect();
        $leave = $leaves->first(function($l) use ($dateStr) {
            $d = Carbon::parse($dateStr);
            return $d->between(Carbon::parse($l->start_date)->startOfDay(), Carbon::parse($l->end_date)->startOfDay());
        });

        // --- Handle Missions ---
        $missions = $requests['missions'] ?? collect();
        $mission = $missions->first(function($m) use ($dateStr) {
            $d = Carbon::parse($dateStr);
            return $d->between(Carbon::parse($m->start_date)->startOfDay(), Carbon::parse($m->end_date)->startOfDay());
        });

        // --- Handle Permissions (Exits) ---
        $dayPermissions = ($requests['permissions'] ?? collect())->filter(fn($p) => (string)$p->permission_date === $dateStr);

        $leaveName = null;

        if ($leave) {
            $leaveName = $leave->policy?->name ?: tr('Leave');
        } elseif ($mission) {
            $missionLabel = tr('Mission') . ($mission->destination ? ': ' . $mission->destination : '');
            $holidayName = $missionLabel;
        }

        if ($schedule && !$isHolidayFinal && !$leave && !$mission) {
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

        // If it's a partial leave, we can augment the periods
        if ($leave && $leave->type === 'partial' && $leave->from_time && $leave->to_time) {
            $periodsOut = $periodsOut->map(function($p) use ($leave) {
                // Simplified: If the period overlaps with leave, mark it? 
                // Actually Flutter expects each period to be either work or leave.
                // For now, we'll just keep it simple as the mobile UI handles full-day status too.
                return $p;
            });
        }

        $permissionsOut = $dayPermissions->map(fn($p) => [
            'from_time' => substr((string)$p->from_time, 0, 5),
            'to_time' => substr((string)$p->to_time, 0, 5),
            'minutes' => (int)$p->total_minutes,
        ])->values();

        // Final Status Determination (Priority)
        // Order: Leave > Mission > Holiday/Exceptional > Workday > Off
        // If no schedule is assigned and no other events exist, set to 'no_schedule' to show empty in UI
        $finalStatus = ($schedule === null && !$isHolidayFinal && !$leave && !$mission) ? 'no_schedule' : 'off';
        
        if ($leave) {
            $finalStatus = 'on_leave';
        } elseif ($mission) {
            $finalStatus = 'mission';
        } elseif ($isHolidayFinal) {
            $finalStatus = 'holiday';
        } elseif ($isWorkday && $periodsOut->isNotEmpty()) {
            $finalStatus = 'workday';
        }

        return [
            'status' => $finalStatus,
            'is_holiday' => (bool)$isHolidayFinal,
            'holiday_name' => $holidayName,
            'leave_name' => $leaveName,
            'is_workday' => $isWorkday,
            'day_key' => $dayKey,
            'total_minutes' => $scheduledMinutes,
            'hours' => $scheduledMinutes > 0 ? ($scheduledMinutes / 60) : null,
            'check_in' => $firstCheckIn,
            'check_out' => $lastCheckOut,
            'periods' => $periodsOut,
            'permissions' => $permissionsOut,
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
