<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('attendance_mission_requests') &&
            !Schema::hasIndex(
                'attendance_mission_requests',
                'mission_emp_status_range'
            )
        ) {
            Schema::table(
                'attendance_mission_requests',
                function (Blueprint $table): void {
                    $table->index(
                        [
                            'employee_id',
                            'status',
                            'start_date',
                            'end_date',
                        ],
                        'mission_emp_status_range'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('attendance_mission_requests') &&
            Schema::hasIndex(
                'attendance_mission_requests',
                'mission_emp_status_range'
            )
        ) {
            Schema::table(
                'attendance_mission_requests',
                function (Blueprint $table): void {
                    $table->dropIndex('mission_emp_status_range');
                }
            );
        }
    }
};