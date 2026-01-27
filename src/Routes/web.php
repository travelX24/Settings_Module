<?php

use Illuminate\Support\Facades\Route;
use Athka\SystemSettings\Http\Controllers\OrganizationalStructureController;

Route::get('/general', \Athka\SystemSettings\Livewire\GeneralSettings::class)->name('general');

Route::get('/attendance', \Athka\SystemSettings\Livewire\Attendance\AttendanceLanding::class)->name('attendance');

Route::get('/attendance/settings', \Athka\SystemSettings\Livewire\Attendance\AttendanceSettings::class)->name('attendance.settings');

Route::get('/attendance/schedules', \Athka\SystemSettings\Livewire\Attendance\WorkSchedules::class)->name('attendance.schedules');

Route::get('/location', \Athka\SystemSettings\Livewire\LocationSettings::class)->name('location');

Route::get('/organizational-structure', \Athka\SystemSettings\Livewire\OrganizationalStructure\OrganizationalStructureIndex::class)->name('organizational-structure');

Route::get('/organizational-structure/departments/employees/{id}', [OrganizationalStructureController::class, 'getEmployeesByDepartment'])
    ->name('organizational-structure.departments.employees');

Route::get('/organizational-structure/job-titles/employees/{id}', [OrganizationalStructureController::class, 'getEmployeesByJobTitle'])
    ->name('organizational-structure.job-titles.employees');

Route::get('/user-access-control', \Athka\SystemSettings\Livewire\UserAccessControl\UserAccessControlIndex::class)->name('user-access-control');





