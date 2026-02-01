<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update Attendance Grace Settings
        Schema::table('attendance_grace_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_grace_settings', 'auto_checkout_penalty_enabled')) {
                $table->boolean('auto_checkout_penalty_enabled')->default(false)->after('auto_checkout_after_minutes');
            }
            if (!Schema::hasColumn('attendance_grace_settings', 'auto_checkout_penalty_amount')) {
                $table->decimal('auto_checkout_penalty_amount', 10, 2)->nullable()->after('auto_checkout_penalty_enabled');
            }
        });

        // 2. Update Attendance Penalty Policies
        Schema::table('attendance_penalty_policies', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_penalty_policies', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('policy_id');
            }
            if (!Schema::hasColumn('attendance_penalty_policies', 'interval_minutes')) {
                $table->unsignedInteger('interval_minutes')->nullable()->after('minutes_to');
            }
            if (!Schema::hasColumn('attendance_penalty_policies', 'wage_unit')) {
                $table->string('wage_unit')->nullable()->after('deduction_type'); // 'minute', 'day'
            }
            if (!Schema::hasColumn('attendance_penalty_policies', 'include_basic_penalty')) {
                $table->boolean('include_basic_penalty')->default(false)->after('notification_message');
            }
            if (!Schema::hasColumn('attendance_penalty_policies', 'recurrence_count')) {
                $table->unsignedTinyInteger('recurrence_count')->default(1)->after('recurrence_to');
            }
            if (!Schema::hasColumn('attendance_penalty_policies', 'threshold_minutes')) {
                $table->unsignedInteger('threshold_minutes')->nullable()->after('interval_minutes');
            }
        });

        // 3. Update Unexcused Absence Policies
        Schema::table('unexcused_absence_policies', function (Blueprint $table) {
            if (!Schema::hasColumn('unexcused_absence_policies', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('policy_id');
            }
            if (!Schema::hasColumn('unexcused_absence_policies', 'notification_message')) {
                $table->text('notification_message')->nullable()->after('early_leave_minutes');
            }
            if (!Schema::hasColumn('unexcused_absence_policies', 'wage_unit')) {
                $table->string('wage_unit')->default('day')->after('deduction_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_grace_settings', function (Blueprint $table) {
            $table->dropColumn(['auto_checkout_penalty_enabled', 'auto_checkout_penalty_amount']);
        });

        Schema::table('attendance_penalty_policies', function (Blueprint $table) {
            $table->dropColumn([
                'is_enabled', 'interval_minutes', 'wage_unit', 
                'include_basic_penalty', 'recurrence_count', 'threshold_minutes'
            ]);
        });

        Schema::table('unexcused_absence_policies', function (Blueprint $table) {
            $table->dropColumn(['is_enabled', 'notification_message', 'wage_unit']);
        });
    }
};
