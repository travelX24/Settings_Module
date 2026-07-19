<?php

use Illuminate\Support\Facades\Route;
use Athka\SystemSettings\Http\Controllers\Api\Employee\AttendancePrepController;
use Athka\SystemSettings\Http\Controllers\Api\Employee\WorkScheduleController;
use Athka\SystemSettings\Http\Controllers\Api\Employee\DailyAttendanceController;
use Athka\SystemSettings\Http\Controllers\Api\Company\ApprovalPolicyController;
use Athka\SystemSettings\Http\Controllers\Api\Employee\ApprovalInboxController;
use Athka\SystemSettings\Http\Controllers\Api\Employee\BranchController;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/employee')
    ->group(function () {

        Route::get('branches', [BranchController::class, 'index']);
        Route::get('attendance/prep', [AttendancePrepController::class, 'show']);

        Route::get('work-schedule', [WorkScheduleController::class, 'index']);

        Route::get('attendance/daily', [DailyAttendanceController::class, 'index']);
        Route::get('attendance/today', [DailyAttendanceController::class, 'today']);

        Route::post('attendance/check-in', [DailyAttendanceController::class, 'checkIn'])->middleware('throttle:attendance-action');
        Route::post('attendance/check-out', [DailyAttendanceController::class, 'checkOut'])->middleware('throttle:attendance-action');

        Route::prefix('approvals')->group(function () {
            Route::get('meta', [ApprovalInboxController::class, 'meta']);
            Route::get('summary', [ApprovalInboxController::class, 'summary']);
            Route::get('inbox', [ApprovalInboxController::class, 'inbox']);

            Route::post('{type}/{id}/approve', [ApprovalInboxController::class, 'approve'])->middleware('throttle:approval-action');
            Route::post('{type}/{id}/reject', [ApprovalInboxController::class, 'reject'])->middleware('throttle:approval-action');

            Route::get('{type}/{id}/timeline', [ApprovalInboxController::class, 'timeline']);
        });
    });



    Route::middleware(['api', 'auth:sanctum'])
        ->prefix('api/company/approvals')
        ->group(function () {

            Route::get('tabs', [ApprovalPolicyController::class, 'tabs']);
            Route::get('lookups', [ApprovalPolicyController::class, 'lookups']);

            Route::get('policies', [ApprovalPolicyController::class, 'index']);
            Route::post('policies', [ApprovalPolicyController::class, 'store'])->middleware('throttle:write-action');

            Route::get('policies/effective', [ApprovalPolicyController::class, 'effective']);

            Route::get('policies/{id}', [ApprovalPolicyController::class, 'show']);
            Route::put('policies/{id}', [ApprovalPolicyController::class, 'update'])->middleware('throttle:write-action');
            Route::delete('policies/{id}', [ApprovalPolicyController::class, 'destroy'])->middleware('throttle:write-action');
        });
