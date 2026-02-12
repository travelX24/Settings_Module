<?php

use Illuminate\Support\Facades\Route;
use Athka\SystemSettings\Http\Controllers\Api\Employee\AttendancePrepController;
use Athka\SystemSettings\Http\Controllers\Api\Employee\WorkScheduleController;
use Athka\SystemSettings\Http\Controllers\Api\Employee\DailyAttendanceController;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/employee')
    ->group(function () {

        Route::get('attendance/prep', [AttendancePrepController::class, 'show']);

        Route::get('work-schedule', [WorkScheduleController::class, 'index']);

        // ✅ Daily attendance logs (prep table: attendance_daily_logs)
        Route::get('attendance/daily', [DailyAttendanceController::class, 'index']);
        Route::get('attendance/today', [DailyAttendanceController::class, 'today']);

        // ✅ Actions
        Route::post('attendance/check-in', [DailyAttendanceController::class, 'checkIn']);
        Route::post('attendance/check-out', [DailyAttendanceController::class, 'checkOut']);
    });
