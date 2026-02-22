<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Employee;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

use Athka\Employees\Models\Employee;
use Athka\SystemSettings\Models\WorkSchedule;
use Athka\SystemSettings\Models\OfficialHolidayOccurrence;
use Athka\SystemSettings\Models\AttendanceGraceSetting;

class DailyAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = (int) ($user->saas_company_id ?? $user->company_id ?? 0);
        if ($companyId <= 0) {
            return response()->json(['ok' => false, 'message' => 'Company context not found'], 422);
        }

        $employee = $this->resolveEmployee($user);
        if (! $employee) {
            return response()->json(['ok' => false, 'message' => 'Employee not found'], 403);
        }

        \Illuminate\Support\Facades\Log::info('Daily Attendance Request', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'resolved_employee_id' => $employee->id,
            'resolved_employee_name' => $employee->first_name . ' ' . $employee->last_name,
        ]);

        $start  = $request->query('start');
        $end    = $request->query('end');
        $ensure = (int) ($request->query('ensure', 1)) === 1;

        try {
            $from = $start ? Carbon::parse($start)->startOfDay() : now()->startOfDay();
            $to   = $end ? Carbon::parse($end)->startOfDay() : (clone $from);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Invalid date range'], 422);
        }

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        // max 62 days
        if ($from->diffInDays($to) > 62) {
            $to = (clone $from)->addDays(62);
        }

        $schedule = $this->getCompanySchedule($companyId, $employee);
        $holidays = $this->getHolidays($companyId, $from->toDateString(), $to->toDateString());

        if ($ensure) {
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $dateStr = $cursor->toDateString();
                $this->ensureLog($companyId, (int) $employee->id, $dateStr, $schedule, $holidays);
                $cursor->addDay();
            }
        }

        $logsQ = DB::table('attendance_daily_logs')
            ->where('saas_company_id', $companyId)
            ->where('employee_id', (int) $employee->id)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->orderBy('attendance_date');

        if ($request->filled('status')) {
            $logsQ->where('attendance_status', (string) $request->query('status'));
        }

        // ── Fetch approved leaves for this range to detect partial/full day leaves ──
        $approvedLeaves = DB::table('attendance_leave_requests')
            ->join('leave_policies', 'attendance_leave_requests.leave_policy_id', '=', 'leave_policies.id')
            ->where('attendance_leave_requests.employee_id', (int) $employee->id)
            ->where('attendance_leave_requests.status', 'approved')
            ->where('attendance_leave_requests.start_date', '<=', $to->toDateString())
            ->where('attendance_leave_requests.end_date',   '>=', $from->toDateString())
            ->get([
                'attendance_leave_requests.start_date',
                'attendance_leave_requests.end_date',
                'attendance_leave_requests.from_time',
                'attendance_leave_requests.to_time',
                'attendance_leave_requests.duration_unit',
                'leave_policies.name as leave_name',
            ]);

        $leaveLookup = [];
        foreach ($approvedLeaves as $leave) {
            $lStart = Carbon::parse($leave->start_date);
            $lEnd   = Carbon::parse($leave->end_date);
            $c = $lStart->copy();
            while ($c->lte($lEnd)) {
                $leaveLookup[$c->toDateString()][] = [
                    'name' => $leave->leave_name,
                    'from' => $leave->from_time ? substr($leave->from_time, 0, 5) : null,
                    'to'   => $leave->to_time ? substr($leave->to_time, 0, 5) : null,
                    'is_full' => ($leave->duration_unit === 'full_day') || (!$leave->from_time && !$leave->to_time),
                ];
                $c->addDay();
            }
        }

        // ── Fetch approved PERMISSIONS for this range ──────────────────────────
        $permissionLookup = []; // date => [{from_time, to_time, minutes}]
        if (Schema::hasTable('attendance_permission_requests')) {
            $permCols = Schema::getColumnListing('attendance_permission_requests');
            // Detect employee key column
            $pKeyCol = in_array('employee_id', $permCols, true) ? 'employee_id'
                : (in_array('user_id', $permCols, true) ? 'user_id' : null);
            // Detect date column
            $pDateCol = in_array('permission_date', $permCols, true) ? 'permission_date'
                : (in_array('date', $permCols, true) ? 'date' : null);

            if ($pKeyCol && $pDateCol) {
                $pKeyVal = ($pKeyCol === 'employee_id') ? (int) $employee->id : ($user->id ?? null);
                if ($pKeyVal) {
                    $approvedPerms = DB::table('attendance_permission_requests')
                        ->where($pKeyCol, $pKeyVal)
                        ->where('status', 'approved')
                        ->whereBetween($pDateCol, [$from->toDateString(), $to->toDateString()])
                        ->get([$pDateCol . ' as perm_date', 'from_time', 'to_time', 'minutes']);

                    foreach ($approvedPerms as $perm) {
                        $dk = substr((string)($perm->perm_date ?? ''), 0, 10);
                        if (!$dk) continue;
                        $permissionLookup[$dk][] = [
                            'from_time' => substr((string)($perm->from_time ?? ''), 0, 5),
                            'to_time'   => substr((string)($perm->to_time ?? ''), 0, 5),
                            'minutes'   => (int)($perm->minutes ?? 0),
                        ];
                    }
                }
            }
        }

        $rows = $logsQ->get();
        
        $logIds = $rows->pluck('id')->toArray();
        $allDetails = DB::table('attendance_daily_details')
            ->whereIn('daily_log_id', $logIds)
            ->get()
            ->groupBy('daily_log_id');

        $toMins = fn($t) => (int)substr($t, 0, 2) * 60 + (int)substr($t, 3, 2);

        $days = $rows->map(function ($r) use ($schedule, $allDetails, $leaveLookup, $permissionLookup, $toMins) {
            $dateObj = Carbon::parse($r->attendance_date);
            $dateStr = $r->attendance_date;
            $dayKey  = $this->dayKey($dateObj);
            
            $periodsOut = [];
            $workSchedulePeriods = collect();

            $dayLeaves = $leaveLookup[$dateStr] ?? [];
            $fullDayLeave = collect($dayLeaves)->firstWhere('is_full', true);
            $hasPartialLeave = collect($dayLeaves)->where('is_full', false)->isNotEmpty();

            // We show periods if (it's a workday) OR (there are partial leaves)
            // Even if scheduled_hours <= 0 (sometimes happens after deduction)
            if ($schedule && ($r->scheduled_hours != 0 || $hasPartialLeave || !empty($r->scheduled_check_in))) {
                // ✅ Handle Exceptions
                $dayExceptions = $schedule->exceptions
                    ? $schedule->exceptions->filter(function ($e) use ($r, $dayKey) {
                        if (!$e->is_active) return false;
                        if ($e->specific_date) return (string) $e->specific_date === (string) $r->attendance_date;
                        return (string) ($e->day_of_week ?? '') === $dayKey;
                    })->values()
                    : collect();

                if ($dayExceptions->count() > 0) {
                    $workSchedulePeriods = $dayExceptions->map(fn($e) => [
                        'start_time' => substr((string) $e->start_time, 0, 5),
                        'end_time' => substr((string) $e->end_time, 0, 5),
                    ]);
                } elseif ($schedule->periods) {
                    $workSchedulePeriods = $schedule->periods
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($p) => [
                            'start_time' => substr((string) $p->start_time, 0, 5),
                            'end_time' => substr((string) $p->end_time, 0, 5),
                        ]);
                }
                
                foreach ($workSchedulePeriods as $p) {
                    $pFrom = $toMins($p['start_time']);
                    $pTo   = $toMins($p['end_time']);
                    if ($pTo <= $pFrom) $pTo += 1440;

                    $leaveMatch = null;
                    if (!$fullDayLeave) {
                        foreach ($dayLeaves as $pl) {
                            if ($pl['is_full'] || !$pl['from'] || !$pl['to']) continue;
                            $lFrom = $toMins($pl['from']);
                            $lTo   = $toMins($pl['to']);
                            if ($lTo <= $lFrom) $lTo += 1440;
                            if ($lFrom < $pTo && $lTo > $pFrom) {
                                $leaveMatch = $pl;
                                break;
                            }
                        }
                    }

                    if ($fullDayLeave) {
                        $periodsOut[] = "إجازة: " . $fullDayLeave['name'];
                    } elseif ($leaveMatch) {
                        $periodsOut[] = $p['start_time'] . " - " . $p['end_time'] . " (إجازة: " . $leaveMatch['name'] . ")";
                    } else {
                        $periodsOut[] = $p['start_time'] . ' - ' . $p['end_time'];
                    }
                }
            }

            $details = $allDetails->get($r->id, collect())->map(function($d) {
                return [
                    'check_in' => $this->timeToHm($d->check_in_time),
                    'check_out' => $this->timeToHm($d->check_out_time),
                    'status' => $d->attendance_status,
                    'period_id' => $d->work_schedule_period_id,
                ];
            });

            // Status Logic
            $status = (string) $r->attendance_status;
            if ($fullDayLeave) {
                $status = 'on_leave';
            } elseif ($hasPartialLeave || count($periodsOut) > 0) {
                if ($status === 'on_leave' || $status === 'day_off' || $status === 'absent') {
                    $status = 'working'; 
                }
            }

            return [
                'date' => (string) $r->attendance_date,
                'day_key' => $dayKey,
                'work_schedule_id' => $r->work_schedule_id ? (int) $r->work_schedule_id : null,
                'scheduled_hours' => $r->scheduled_hours !== null ? (float) $r->scheduled_hours : null,
                'scheduled_check_in' => $r->scheduled_check_in,
                'scheduled_check_out' => $r->scheduled_check_out,
                'periods' => $periodsOut,
                'check_in_time' => $this->timeToHm($r->check_in_time),
                'check_out_time' => $this->timeToHm($r->check_out_time),
                'attendance_status' => $status,
                'approval_status' => (string) $r->approval_status,
                'compliance_percentage' => $r->compliance_percentage !== null ? (float) $r->compliance_percentage : null,
                'punches' => $details,
                'is_edited' => (bool) $r->is_edited,
                'source' => (string) $r->source,
                'leave_name' => $fullDayLeave ? $fullDayLeave['name'] : (collect($dayLeaves)->first()['name'] ?? null),
                'permissions' => $permissionLookup[$dateStr] ?? [],
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'range' => [
                    'start' => $from->toDateString(),
                    'end' => $to->toDateString(),
                ],
                'days' => $days,
            ],
        ]);
    }

    public function today(Request $request)
    {
        $today = now()->toDateString();
        $request->query->set('start', $today);
        $request->query->set('end', $today);
        $request->query->set('ensure', '1');

        return $this->index($request);
    }

    public function checkIn(Request $request)
    {
        $user = $request->user();
        $companyId = (int) ($user->saas_company_id ?? $user->company_id ?? 0);
        if ($companyId <= 0) {
            return response()->json(['ok' => false, 'message' => 'Company context not found'], 422);
        }

        $employee = $this->resolveEmployee($user);
        if (! $employee) {
            return response()->json(['ok' => false, 'message' => 'Employee not found'], 403);
        }

        $data = $request->validate([
            'method' => ['required', 'in:gps,fingerprint,nfc'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        // ✅ 1. Check if method is allowed for this employee
        $prep = app(AttendancePrepController::class)->show($request)->getData();
        if (!$prep->ok) {
            return response()->json(['ok' => false, 'message' => 'Could not verify attendance settings'], 422);
        }

        $methodInfo = $prep->data->methods->{$data['method']} ?? null;
        if (!$methodInfo || !$methodInfo->effective) {
            return response()->json([
                'ok' => false, 
                'message' => 'هذه الطريقة ('. $data['method'] .') غير مفعلة لك حالياً.'
            ], 403);
        }

        // ✅ 2. If GPS, check Geofence
        if ($data['method'] === 'gps') {
            if (!isset($data['lat']) || !isset($data['lng'])) {
                return response()->json(['ok' => false, 'message' => 'Location coordinates are required for GPS attendance'], 422);
            }

            $allowedLocations = $prep->data->gps_locations ?? [];
            $isInside = false;

            foreach ($allowedLocations as $loc) {
                // We use the model's logic to verify
                $locModel = \Athka\SystemSettings\Models\AttendanceGpsLocation::find($loc->id);
                if ($locModel && $locModel->isWithinGeofence((float)$data['lat'], (float)$data['lng'])) {
                    $isInside = true;
                    break;
                }
            }

            if (!$isInside) {
                return response()->json([
                    'ok' => false, 
                    'code' => 'geofence_error',
                    'message' => 'أنت خارج النطاق الجغرافي المسموح به للتحضير.'
                ], 403);
            }
        }

        $dateStr = now()->toDateString();
        $schedule = $this->getCompanySchedule($companyId, $employee);
        $holidays = $this->getHolidays($companyId, $dateStr, $dateStr);

        $log = $this->ensureLog($companyId, (int) $employee->id, $dateStr, $schedule, $holidays);

        // 🟢 Check for FULL DAY or PARTIAL LEAVE overlaps right now
        $approvedLeaves = DB::table('attendance_leave_requests')
            ->where('employee_id', (int) $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->get();

        $nowTimeStr = now()->format('H:i');
        $nowMins = (int)substr($nowTimeStr, 0, 2) * 60 + (int)substr($nowTimeStr, 3, 2);

        foreach ($approvedLeaves as $leave) {
            $isFull = ($leave->duration_unit === 'full_day') || (!$leave->from_time && !$leave->to_time);
            if ($isFull) {
                return response()->json([
                    'ok' => false, 
                    'code' => 'on_leave', 
                    'message' => 'أنت في إجازة اليوم.'
                ], 403);
            }

            // Partial leave
            if ($leave->from_time && $leave->to_time) {
                $lFrom = (int)substr($leave->from_time, 0, 2) * 60 + (int)substr($leave->from_time, 3, 2);
                $lTo   = (int)substr($leave->to_time, 0, 2) * 60 + (int)substr($leave->to_time, 3, 2);
                if ($lTo <= $lFrom) $lTo += 1440;

                if ($nowMins >= $lFrom && $nowMins <= $lTo) {
                    return response()->json([
                        'ok' => false, 
                        'code' => 'on_leave_period', 
                        'message' => 'لا يمكنك تسجيل الحضور لأنك في فترة إجازة حالياً.'
                    ], 403);
                }
            }
        }

        if ($log->scheduled_hours === null) {
            return response()->json(['ok' => false, 'code' => 'not_workday', 'message' => 'ليس يوم عمل'], 409);
        }

        // Check if there is an open session
        $openSession = DB::table('attendance_daily_details')
            ->where('daily_log_id', $log->id)
            ->whereNull('check_out_time')
            ->first();

        if ($openSession) {
            return response()->json([
                'ok' => false, 
                'code' => 'already_checked_in',
                'message' => 'لديك جلسة حضور مفتوحة بالفعل.'
            ], 409);
        }

        $nowTime = now()->format('H:i:s');
        $nowCarbon = now();

        // ✅ Smart Period Matching
        $isWithinPeriod = false;
        $status = 'present';

        if ($schedule && $schedule->periods) {
            $matchedPeriod = null;
            foreach ($schedule->periods as $p) {
                // ✅ Update: Only allow 30 minutes before start
                $pStartAllowed = Carbon::parse($dateStr . ' ' . $p->start_time)->subMinutes(30);
                $pEnd = Carbon::parse($dateStr . ' ' . $p->end_time);
                
                if ($nowCarbon->between($pStartAllowed, $pEnd)) {
                    $matchedPeriod = $p;
                    $isWithinPeriod = true;
                    
                    // ❌ IMPROVED CHECK: Check if this period was already used (including legacy match)
                    $pStart = $p->start_time;
                    $pEnd = $p->end_time;

                    $alreadyUsed = DB::table('attendance_daily_details')
                        ->where('daily_log_id', $log->id)
                        ->where(function($q) use ($p, $pStart, $pEnd) {
                            $q->where('work_schedule_period_id', $p->id)
                              ->orWhereBetween('check_in_time', [$pStart, $pEnd]);
                        })
                        ->exists();

                    if ($alreadyUsed) {
                        return response()->json([
                            'ok' => false,
                            'code' => 'period_already_completed',
                            'message' => 'لقد قمت بإكمال تسجيل الحضور والانصراف لهذه الفترة مسبقاً.'
                        ], 403);
                    }

                    // Reset to real start for delay calculation
                    $graceMins = (int) ($prep->data->grace->late_grace_minutes ?? 15);
                    $realStart = Carbon::parse($dateStr . ' ' . $p->start_time);
                    if ($nowCarbon->greaterThan($realStart->addMinutes($graceMins))) {
                        $status = 'late';
                    }
                    break;
                }
            }

            if (!$isWithinPeriod) {
                return response()->json([
                    'ok' => false,
                    'code' => 'too_early_for_checkin',
                    'message' => 'لا يمكنك التحضير قبل بداية الفترة بأكثر من 30 دقيقة.'
                ], 403);
            }

            // Create Detail Record with Period ID
            DB::table('attendance_daily_details')->insert([
                'daily_log_id' => $log->id,
                'work_schedule_period_id' => $matchedPeriod->id, // ✅ Record the specific period
                'check_in_time' => $nowTime,
                'attendance_status' => $status,
                'meta_data' => json_encode([
                    'method' => $data['method'],
                    'lat' => $data['lat'] ?? null,
                    'lng' => $data['lng'] ?? null,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // If no periods defined, use old logic but still into details
            DB::table('attendance_daily_details')->insert([
                'daily_log_id' => $log->id,
                'check_in_time' => $nowTime,
                'attendance_status' => $status,
                'meta_data' => json_encode([
                    'method' => $data['method'],
                    'lat' => $data['lat'] ?? null,
                    'lng' => $data['lng'] ?? null,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Update Main Log with status precedence
        $newStatus = $status;
        $currentLogStatus = (string) ($log->attendance_status ?? 'absent');
        
        // Precedence: late wins over present
        if ($currentLogStatus === 'late' || $status === 'late') {
            $newStatus = 'late';
        }

        $updateData = [
            'attendance_status' => $newStatus,
            'updated_at' => now(),
        ];
        if (empty($log->check_in_time)) {
            $updateData['check_in_time'] = $nowTime;
        }

        DB::table('attendance_daily_logs')
            ->where('id', $log->id)
            ->update($updateData);

        return response()->json([
            'ok' => true,
            'data' => [
                'date' => $dateStr,
                'check_in_time' => $this->timeToHm($nowTime),
                'attendance_status' => $status,
            ],
        ]);
    }

    public function checkOut(Request $request)
    {
        $user = $request->user();
        $companyId = (int) ($user->saas_company_id ?? $user->company_id ?? 0);
        if ($companyId <= 0) {
            return response()->json(['ok' => false, 'message' => 'Company context not found'], 422);
        }

        $employee = $this->resolveEmployee($user);
        if (! $employee) {
            return response()->json(['ok' => false, 'message' => 'Employee not found'], 403);
        }

        $data = $request->validate([
            'method' => ['required', 'in:gps,fingerprint,nfc'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        // ✅ 1. Check if method is allowed for this employee
        $prep = app(AttendancePrepController::class)->show($request)->getData();
        if (!$prep->ok) {
            return response()->json(['ok' => false, 'message' => 'Could not verify attendance settings'], 422);
        }

        $methodInfo = $prep->data->methods->{$data['method']} ?? null;
        if (!$methodInfo || !$methodInfo->effective) {
            return response()->json([
                'ok' => false, 
                'message' => 'هذه الطريقة ('. $data['method'] .') غير مفعلة لك حالياً.'
            ], 403);
        }

        // ✅ 2. If GPS, check Geofence
        if ($data['method'] === 'gps') {
            if (!isset($data['lat']) || !isset($data['lng'])) {
                return response()->json(['ok' => false, 'message' => 'Location coordinates are required for GPS attendance'], 422);
            }

            $allowedLocations = $prep->data->gps_locations ?? [];
            $isInside = false;

            foreach ($allowedLocations as $loc) {
                $locModel = \Athka\SystemSettings\Models\AttendanceGpsLocation::find($loc->id);
                if ($locModel && $locModel->isWithinGeofence((float)$data['lat'], (float)$data['lng'])) {
                    $isInside = true;
                    break;
                }
            }

            if (!$isInside) {
                return response()->json([
                    'ok' => false, 
                    'code' => 'geofence_error',
                    'message' => 'أنت خارج النطاق الجغرافي المسموح به للانصراف.'
                ], 403);
            }
        }

        $dateStr = now()->toDateString();
        $nowTime = now()->format('H:i:s');

        $log = DB::table('attendance_daily_logs')
            ->where('saas_company_id', $companyId)
            ->where('employee_id', (int) $employee->id)
            ->whereDate('attendance_date', $dateStr)
            ->first();

        if (!$log) {
            return response()->json(['ok' => false, 'message' => 'لا يوجد سجل حضور اليوم.'], 422);
        }

        $openSession = DB::table('attendance_daily_details')
            ->where('daily_log_id', $log->id)
            ->whereNull('check_out_time')
            ->orderByDesc('id')
            ->first();

        if (!$openSession) {
            // Fallback: If we have a check_in_time in the main log but no detail record (legacy/transition)
            if (!empty($log->check_in_time)) {
                $detailId = DB::table('attendance_daily_details')->insertGetId([
                    'daily_log_id' => $log->id,
                    'check_in_time' => $log->check_in_time,
                    'attendance_status' => $log->attendance_status ?? 'present',
                    'meta_data' => json_encode(['note' => 'auto-created for legacy check-out']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $openSession = DB::table('attendance_daily_details')->find($detailId);
            } else {
                return response()->json([
                    'ok' => false, 
                    'code' => 'no_check_in_record', 
                    'message' => 'لا يوجد سجل حضور مفتوح حالياً'
                ], 422);
            }
        }

        // Update Detail
        DB::table('attendance_daily_details')
            ->where('id', $openSession->id)
            ->update([
                'check_out_time' => $nowTime,
                'updated_at' => now(),
            ]);

        // Recalculate Totals
        $allDetails = DB::table('attendance_daily_details')
            ->where('daily_log_id', $log->id)
            ->whereNotNull('check_out_time')
            ->get();

        $totalMinutes = 0;
        foreach ($allDetails as $session) {
            $totalMinutes += $this->minutesBetweenTimes((string)$session->check_in_time, (string)$session->check_out_time);
        }
        $actualHours = round($totalMinutes / 60, 2);

        // Compliance
        $compliance = null;
        if ($log->scheduled_hours > 0) {
            $compliance = round(min(100, ($totalMinutes / ($log->scheduled_hours * 60)) * 100), 2);
        }

        DB::table('attendance_daily_logs')
            ->where('id', $log->id)
            ->update([
                'check_out_time' => $nowTime,
                'actual_hours' => $actualHours,
                'compliance_percentage' => $compliance,
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'date' => $dateStr,
                'check_out_time' => $this->timeToHm($nowTime),
                'actual_hours' => $actualHours,
            ],
        ]);
    }

    // ================= Helpers =================

    protected function resolveEmployee($user): ?Employee
    {
        $employee = null;

        if (property_exists($user, 'employee_id') && (int) ($user->employee_id ?? 0) > 0) {
            $employee = Employee::query()->where('id', (int) $user->employee_id)->first();
        }

        if (! $employee && method_exists($user, 'employee')) {
            $employee = $user->employee;
        }

        if (! $employee && Schema::hasColumn('employees', 'user_id')) {
            $employee = Employee::query()->where('user_id', (int) $user->id)->first();
        }

        return $employee ?: null;
    }

    protected function getCompanySchedule(int $companyId, ?Employee $employee = null): ?WorkSchedule
    {
        // 1. Fallback to default company schedule
        $schedule = WorkSchedule::query()
            ->with(['periods', 'exceptions'])
            ->where('saas_company_id', $companyId)
            ->where('is_default', true)
            ->first();
        
        if ($schedule) return $schedule;
        
        // 2. Fallback to latest schedule if no default
        $schedule = WorkSchedule::query()
            ->with(['periods', 'exceptions'])
            ->where('saas_company_id', $companyId)
            ->orderByDesc('id')
            ->first();
        
        return $schedule ?: null;
    }

    protected function getHolidays(int $companyId, string $from, string $to)
    {
        return OfficialHolidayOccurrence::query()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get();
    }

    protected function computeScheduledHoursFromTimes(string $dateStr, ?string $schedIn, ?string $schedOut): ?float
    {
        if (!$schedIn || !$schedOut) return null;

        $st = Carbon::parse($dateStr . ' ' . substr($schedIn, 0, 5) . ':00');
        $et = Carbon::parse($dateStr . ' ' . substr($schedOut, 0, 5) . ':00');

        if ($et->lt($st)) {
            $et->addDay();
        }

        $mins = $st->diffInMinutes($et);
        return $mins > 0 ? round($mins / 60, 2) : null;
    }

    protected function ensureLog(int $companyId, int $employeeId, string $dateStr, ?WorkSchedule $schedule, $holidays)
    {
        $existing = DB::table('attendance_daily_logs')
            ->where('saas_company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $dateStr)
            ->first();

        if ($existing) {
            $needsFill =
                is_null($existing->work_schedule_id) ||
                is_null($existing->scheduled_hours) ||
                is_null($existing->scheduled_check_in) ||
                is_null($existing->scheduled_check_out);

            if ($needsFill && $schedule) {
                $dayKey = $this->dayKey(Carbon::parse($dateStr));

                $isHoliday = $holidays->first(function ($h) use ($dateStr) {
                    return $dateStr >= (string) $h->start_date && $dateStr <= (string) $h->end_date;
                });

                $scheduledMinutes = 0;
                $schedIn = null;
                $schedOut = null;
                $workScheduleId = $schedule ? (int) $schedule->id : null;

                if ($schedule && !$isHoliday) {
                    $workDays = is_array($schedule->work_days) ? $schedule->work_days : [];
                    $isWorkday = in_array($dayKey, $workDays, true);

                    $periods = collect();

                    if ($schedule->exceptions) {
                        $exceptions = $schedule->exceptions->filter(function ($e) use ($dateStr, $dayKey) {
                            if (! $e->is_active) return false;
                            if ($e->specific_date) return (string) $e->specific_date === $dateStr;
                            return (string) ($e->day_of_week ?? '') === $dayKey;
                        })->values();

                        if ($exceptions->count() > 0) {
                            $isWorkday = true;
                            $periods = $exceptions->map(fn ($e) => [
                                'start_time' => substr((string) $e->start_time, 0, 5),
                                'end_time' => substr((string) $e->end_time, 0, 5),
                                'is_night_shift' => (bool) $e->is_night_shift,
                            ]);
                        }
                    }

                    if ($periods->isEmpty() && $isWorkday && $schedule->periods) {
                        $periods = $schedule->periods
                            ->sortBy('sort_order')
                            ->values()
                            ->map(fn ($p) => [
                                'start_time' => substr((string) $p->start_time, 0, 5),
                                'end_time' => substr((string) $p->end_time, 0, 5),
                                'is_night_shift' => (bool) $p->is_night_shift,
                            ]);
                    }

                    if ($periods->isNotEmpty()) {
                        $starts = [];
                        $ends = [];

                        foreach ($periods as $p) {
                            $st = (string) ($p['start_time'] ?? '');
                            $et = (string) ($p['end_time'] ?? '');
                            if ($st === '' || $et === '') continue;

                            $starts[] = $st;
                            $ends[] = $et;

                            $startDt = Carbon::parse($dateStr . ' ' . $st . ':00');
                            $endDt   = Carbon::parse($dateStr . ' ' . $et . ':00');

                            if (!empty($p['is_night_shift']) || $endDt->lt($startDt)) {
                                $endDt->addDay();
                            }

                            $scheduledMinutes += $startDt->diffInMinutes($endDt);
                        }

                        sort($starts);
                        sort($ends);

                        $schedIn = $starts[0] ?? null;
                        $schedOut = $ends[count($ends) - 1] ?? null;
                    }
                }

                $scheduledHours = $scheduledMinutes > 0 ? round($scheduledMinutes / 60, 2) : null;

                $update = [];
                if (is_null($existing->work_schedule_id)) $update['work_schedule_id'] = $workScheduleId;
                if (is_null($existing->scheduled_hours)) $update['scheduled_hours'] = $scheduledHours;
                if (is_null($existing->scheduled_check_in)) $update['scheduled_check_in'] = $schedIn ? ($schedIn . ':00') : null;
                if (is_null($existing->scheduled_check_out)) $update['scheduled_check_out'] = $schedOut ? ($schedOut . ':00') : null;

                if (!empty($update)) {
                    $update['updated_at'] = now();
                    DB::table('attendance_daily_logs')->where('id', (int) $existing->id)->update($update);
                    $existing = DB::table('attendance_daily_logs')->where('id', (int) $existing->id)->first();
                }
            }

            return $existing;
        }

        $dayKey = $this->dayKey(Carbon::parse($dateStr));

        $isHoliday = $holidays->first(function ($h) use ($dateStr) {
            return $dateStr >= (string) $h->start_date && $dateStr <= (string) $h->end_date;
        });

        $scheduledMinutes = 0;
        $schedIn = null;
        $schedOut = null;
        $workScheduleId = $schedule ? (int) $schedule->id : null;

        if ($schedule && !$isHoliday) {
            $workDays = is_array($schedule->work_days) ? $schedule->work_days : [];
            $isWorkday = in_array($dayKey, $workDays, true);

            $periods = collect();

            if ($schedule->exceptions) {
                $exceptions = $schedule->exceptions->filter(function ($e) use ($dateStr, $dayKey) {
                    if (! $e->is_active) return false;
                    if ($e->specific_date) return (string) $e->specific_date === $dateStr;
                    return (string) ($e->day_of_week ?? '') === $dayKey;
                })->values();

                if ($exceptions->count() > 0) {
                    $isWorkday = true;
                    $periods = $exceptions->map(fn ($e) => [
                        'start_time' => substr((string) $e->start_time, 0, 5),
                        'end_time' => substr((string) $e->end_time, 0, 5),
                        'is_night_shift' => (bool) $e->is_night_shift,
                    ]);
                }
            }

            if ($periods->isEmpty() && $isWorkday && $schedule->periods) {
                $periods = $schedule->periods
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn ($p) => [
                        'start_time' => substr((string) $p->start_time, 0, 5),
                        'end_time' => substr((string) $p->end_time, 0, 5),
                        'is_night_shift' => (bool) $p->is_night_shift,
                    ]);
            }

            if ($periods->isNotEmpty()) {
                $starts = [];
                $ends = [];

                foreach ($periods as $p) {
                    $st = (string) ($p['start_time'] ?? '');
                    $et = (string) ($p['end_time'] ?? '');
                    if ($st === '' || $et === '') continue;

                    $starts[] = $st;
                    $ends[] = $et;

                    $startDt = Carbon::parse($dateStr . ' ' . $st . ':00');
                    $endDt   = Carbon::parse($dateStr . ' ' . $et . ':00');

                    if (!empty($p['is_night_shift']) || $endDt->lt($startDt)) {
                        $endDt->addDay();
                    }

                    $scheduledMinutes += $startDt->diffInMinutes($endDt);
                }

                sort($starts);
                sort($ends);

                $schedIn = $starts[0] ?? null;
                $schedOut = $ends[count($ends) - 1] ?? null;
            }
        }

        $scheduledHours = $scheduledMinutes > 0 ? round($scheduledMinutes / 60, 2) : null;

        $id = DB::table('attendance_daily_logs')->insertGetId([
            'saas_company_id' => $companyId,
            'employee_id' => $employeeId,
            'attendance_date' => $dateStr,
            'work_schedule_id' => $workScheduleId,
            'scheduled_hours' => $scheduledHours,
            'scheduled_check_in' => $schedIn ? ($schedIn . ':00') : null,
            'scheduled_check_out' => $schedOut ? ($schedOut . ':00') : null,
            'attendance_status' => ($scheduledHours === null) ? 'day_off' : 'absent',
            'approval_status' => 'pending',
            'source' => 'automatic',
            'meta_data' => json_encode([
                'generated_by' => 'api',
                'is_holiday' => (bool) $isHoliday,
                'day_key' => $dayKey,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('attendance_daily_logs')->where('id', (int) $id)->first();
    }

    protected function dayKey(Carbon $date): string
    {
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

    protected function timeToHm($value): ?string
    {
        if (!$value) return null;
        return substr((string) $value, 0, 5);
    }

    // ✅ FIXED: correct direction + supports H:i and H:i:s + overnight
    protected function minutesBetweenTimes(string $startTime, string $endTime): int
    {
        $startTime = substr($startTime, 0, 8);
        $endTime   = substr($endTime, 0, 8);

        if (strlen($startTime) === 5) $startTime .= ':00';
        if (strlen($endTime) === 5)   $endTime   .= ':00';

        $st = Carbon::createFromFormat('H:i:s', $startTime);
        $et = Carbon::createFromFormat('H:i:s', $endTime);

        $mins = $st->diffInMinutes($et, false); // ✅ الصحيح
        if ($mins < 0) {
            $mins += 24 * 60;
        }
        return (int) $mins;
    }
}
