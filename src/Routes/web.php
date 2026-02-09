<?php

use Illuminate\Support\Facades\Route;
use Athka\SystemSettings\Http\Controllers\OrganizationalStructureController;
use Athka\SystemSettings\Livewire\Calendar\CalendarSettings;
use Athka\SystemSettings\Livewire\Approvals\ApprovalSequenceSettings;

Route::get('/general', \Athka\SystemSettings\Livewire\GeneralSettings::class)->name('general');

Route::get('/attendance', \Athka\SystemSettings\Livewire\Attendance\AttendanceLanding::class)->name('attendance');

Route::get('/attendance/settings', \Athka\SystemSettings\Livewire\Attendance\AttendanceSettings::class)->name('attendance.settings');

Route::get('/attendance/schedules', \Athka\SystemSettings\Livewire\Attendance\WorkSchedules::class)->name('attendance.schedules');

Route::get('/attendance/holidays', \Athka\SystemSettings\Livewire\Attendance\AttendanceHolidays::class)
    ->name('attendance.holidays');

Route::get('/attendance/leaves', \Athka\SystemSettings\Livewire\Attendance\AttendanceLeaveSettings::class)
    ->name('attendance.leaves');

Route::get('/location', \Athka\SystemSettings\Livewire\LocationSettings::class)->name('location');

Route::get('/organizational-structure', \Athka\SystemSettings\Livewire\OrganizationalStructure\OrganizationalStructureIndex::class)->name('organizational-structure');

Route::get('/organizational-structure/departments/employees/{id}', [OrganizationalStructureController::class, 'getEmployeesByDepartment'])
    ->name('organizational-structure.departments.employees');

Route::get('/organizational-structure/job-titles/employees/{id}', [OrganizationalStructureController::class, 'getEmployeesByJobTitle'])
    ->name('organizational-structure.job-titles.employees');

Route::get('/user-access-control', \Athka\SystemSettings\Livewire\UserAccessControl\UserAccessControlIndex::class)->name('user-access-control');

Route::get('/calendar', CalendarSettings::class)
    ->name('calendar');

Route::get('/approval-sequences', ApprovalSequenceSettings::class)
    ->name('approval-sequences');
