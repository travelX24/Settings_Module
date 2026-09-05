<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('attendance_exceptional_days')
            && ! Schema::hasColumn(
                'attendance_exceptional_days',
                'early_departure_multiplier'
            )
        ) {
            Schema::table('attendance_exceptional_days', function (Blueprint $table) {
                $table->decimal('early_departure_multiplier', 5, 2)
                    ->default(1.00)
                    ->after('late_multiplier');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('attendance_exceptional_days')
            && Schema::hasColumn(
                'attendance_exceptional_days',
                'early_departure_multiplier'
            )
        ) {
            Schema::table('attendance_exceptional_days', function (Blueprint $table) {
                $table->dropColumn('early_departure_multiplier');
            });
        }
    }
};