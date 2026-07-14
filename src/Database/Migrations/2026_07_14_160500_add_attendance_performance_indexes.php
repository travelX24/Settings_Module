<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $detailsIndexes = [
            'idx_add_log_checkin' => ['daily_log_id', 'check_in_time'],
            'idx_add_log_checkout' => ['daily_log_id', 'check_out_time'],
            'idx_add_log_period' => ['daily_log_id', 'work_schedule_period_id'],
        ];

        foreach ($detailsIndexes as $indexName => $columns) {
            if (
                Schema::hasTable('attendance_daily_details') &&
                !Schema::hasIndex('attendance_daily_details', $indexName)
            ) {
                Schema::table(
                    'attendance_daily_details',
                    fn (Blueprint $table) => $table->index($columns, $indexName)
                );
            }
        }

        if (
            Schema::hasTable('employee_shift_rotations') &&
            !Schema::hasIndex(
                'employee_shift_rotations',
                'idx_esr_employee_dates'
            )
        ) {
            Schema::table(
                'employee_shift_rotations',
                fn (Blueprint $table) => $table->index(
                    ['employee_id', 'start_date', 'end_date'],
                    'idx_esr_employee_dates'
                )
            );
        }

        if (
            Schema::hasTable('employee_work_schedules') &&
            !Schema::hasIndex(
                'employee_work_schedules',
                'idx_ews_employee_dates'
            )
        ) {
            Schema::table(
                'employee_work_schedules',
                fn (Blueprint $table) => $table->index(
                    ['employee_id', 'start_date', 'end_date'],
                    'idx_ews_employee_dates'
                )
            );
        }
    }

    public function down(): void
    {
        foreach ([
            'idx_add_log_checkin',
            'idx_add_log_checkout',
            'idx_add_log_period',
        ] as $indexName) {
            if (
                Schema::hasTable('attendance_daily_details') &&
                Schema::hasIndex('attendance_daily_details', $indexName)
            ) {
                Schema::table(
                    'attendance_daily_details',
                    fn (Blueprint $table) => $table->dropIndex($indexName)
                );
            }
        }

        if (
            Schema::hasTable('employee_shift_rotations') &&
            Schema::hasIndex(
                'employee_shift_rotations',
                'idx_esr_employee_dates'
            )
        ) {
            Schema::table(
                'employee_shift_rotations',
                fn (Blueprint $table) =>
                    $table->dropIndex('idx_esr_employee_dates')
            );
        }

        if (
            Schema::hasTable('employee_work_schedules') &&
            Schema::hasIndex(
                'employee_work_schedules',
                'idx_ews_employee_dates'
            )
        ) {
            Schema::table(
                'employee_work_schedules',
                fn (Blueprint $table) =>
                    $table->dropIndex('idx_ews_employee_dates')
            );
        }
    }
};