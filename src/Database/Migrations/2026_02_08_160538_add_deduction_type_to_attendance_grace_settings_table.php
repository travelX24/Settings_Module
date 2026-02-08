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
        Schema::table('attendance_grace_settings', function (Blueprint $table) {
            $table->enum('auto_checkout_deduction_type', ['fixed', 'percentage', 'hourly', 'daily'])
                  ->default('fixed')
                  ->after('auto_checkout_penalty_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_grace_settings', function (Blueprint $table) {
            $table->dropColumn('auto_checkout_deduction_type');
        });
    }
};
