<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Employee;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Carbon\Carbon;

use Athka\SystemSettings\Models\WorkSchedule;
use Athka\SystemSettings\Models\OfficialHolidayOccurrence;

class WorkScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = (int) ($user->saas_company_id ?? $user->company_id ?? 0);
        if ($companyId <= 0) {
            return response()->json(['ok' => false, 'message' => 'Company context not found'], 422);
        }

        $start = $request->query('start');
        $end   = $request->query('end');

        try {
            $from = $start ? Carbon::parse($start)->startOfDay() : now()->startOfMonth();
            $to   = $end ? Carbon::parse($end)->startOfDay() : now()->endOfMonth();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Invalid date range'], 422);
        }

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        // حد أقصى 62 يوم (حتى ما يصير payload ضخم)
        if ($from->diffInDays($to) > 62) {
            $to = (clone $from)->addDays(62);
        }

        $schedule = WorkSchedule::query()
            ->with(['periods', 'exceptions'])
            ->where('saas_company_id', $companyId)
            ->where('is_default', true)
            ->first();

        if (! $schedule) {
            $schedule = WorkSchedule::query()
                ->with(['periods', 'exceptions'])
                ->where('saas_company_id', $companyId)
                ->orderByDesc('id')
                ->first();
        }

        $holidays = OfficialHolidayOccurrence::query()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get();

        $days = [];
        $cursor = (clone $from);

        while ($cursor->lte($to)) {
            $dateStr = $cursor->toDateString();
            $dayKey = $this->dayKey($cursor); // saturday..friday

            $isHoliday = $holidays->first(function ($h) use ($dateStr) {
                return $dateStr >= (string) $h->start_date && $dateStr <= (string) $h->end_date;
            });

            $isWorkday = false;
            $periodsOut = [];
            $totalMinutes = 0;

            if ($schedule) {
                $workDays = is_array($schedule->work_days) ? $schedule->work_days : [];
                $isWorkday = in_array($dayKey, $workDays, true);

                // exceptions: specific_date first, then day_of_week
                $exceptions = $schedule->exceptions
                    ? $schedule->exceptions->filter(function ($e) use ($dateStr, $dayKey) {
                        if (! $e->is_active) return false;
                        if ($e->specific_date) return (string) $e->specific_date === $dateStr;
                        return (string) ($e->day_of_week ?? '') === $dayKey;
                    })->values()
                    : collect();

                $sourcePeriods = null;

                if ($exceptions->count() > 0) {
                    // treat exceptions as override periods
                    $sourcePeriods = $exceptions->map(fn ($e) => [
                        'start_time' => substr((string) $e->start_time, 0, 5),
                        'end_time' => substr((string) $e->end_time, 0, 5),
                        'is_night_shift' => (bool) $e->is_night_shift,
                    ]);
                    $isWorkday = true; // لو فيه استثناء فعّال نحسبه يوم عمل
                } elseif ($isWorkday && $schedule->periods) {
                    $sourcePeriods = $schedule->periods
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($p) => [
                            'start_time' => substr((string) $p->start_time, 0, 5),
                            'end_time' => substr((string) $p->end_time, 0, 5),
                            'is_night_shift' => (bool) $p->is_night_shift,
                        ]);
                } else {
                    $sourcePeriods = collect();
                }

                foreach ($sourcePeriods as $p) {
                    $st = (string) ($p['start_time'] ?? '');
                    $et = (string) ($p['end_time'] ?? '');

                    if ($st === '' || $et === '') continue;

                    $startDt = Carbon::parse($dateStr . ' ' . $st . ':00');
                    $endDt   = Carbon::parse($dateStr . ' ' . $et . ':00');

                    // night shift handling
                    if (!empty($p['is_night_shift']) || $endDt->lt($startDt)) {
                        $endDt->addDay();
                    }

                    $mins = abs($startDt->diffInMinutes($endDt, false)); // ✅ always positive
                    $totalMinutes += $mins;

                    $periodsOut[] = [
                        'start' => $startDt->toIso8601String(),
                        'end' => $endDt->toIso8601String(),
                        'start_time' => $st,
                        'end_time' => $et,
                        'is_night_shift' => (bool) ($p['is_night_shift'] ?? false),
                        'minutes' => $mins,
                    ];
                }
            }

            $status = 'off';
            if ($isHoliday) $status = 'holiday';
            elseif ($isWorkday && count($periodsOut) > 0) $status = 'workday';

            $days[] = [
                'date' => $dateStr,
                'day_key' => $dayKey,
                'status' => $status,
                'is_holiday' => (bool) $isHoliday,
                'holiday_name' => $isHoliday ? (string) ($isHoliday->template?->name ?? '') : null,
                'is_workday' => (bool) $isWorkday,
                'total_minutes' => (int) $totalMinutes,
                'periods' => $periodsOut,
            ];

            $cursor->addDay();
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'range' => [
                    'start' => $from->toDateString(),
                    'end' => $to->toDateString(),
                ],
                'schedule' => $schedule ? [
                    'id' => (int) $schedule->id,
                    'name' => (string) $schedule->name,
                    'week_start_day' => (string) $schedule->week_start_day,
                    'week_end_day' => (string) $schedule->week_end_day,
                    'work_days' => is_array($schedule->work_days) ? $schedule->work_days : [],
                    'is_default' => (bool) $schedule->is_default,
                ] : null,
                'days' => $days,
            ],
        ]);
    }

    protected function dayKey(Carbon $date): string
    {
        // Carbon: 0=Sunday..6=Saturday
        $map = [
            0 => 'sunday',
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
        ];
        return $map[(int) $date->dayOfWeek] ?? 'sunday';
    }
}
