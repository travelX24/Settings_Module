<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'attendance_policies',
            'attendance_grace_settings',
            'attendance_methods',
            'attendance_gps_locations',
            'attendance_devices',
            'attendance_penalty_policies',
            'unexcused_absence_policies',
            'employee_groups',
            'work_schedules'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'saas_company_id')) {
                        $table->foreignId('saas_company_id')
                              ->nullable()
                              ->after('id')
                              ->constrained('saas_companies')
                              ->onDelete('cascade');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'attendance_policies',
            'attendance_grace_settings',
            'attendance_methods',
            'attendance_gps_locations',
            'attendance_devices',
            'attendance_penalty_policies',
            'unexcused_absence_policies',
            'employee_groups',
            'work_schedules'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'saas_company_id')) {
                        $table->dropForeign([$tableName . '_saas_company_id_foreign']);
                        $table->dropColumn('saas_company_id');
                    }
                });
            }
        }
    }
};





