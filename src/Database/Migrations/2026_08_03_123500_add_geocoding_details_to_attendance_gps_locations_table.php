<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendance_gps_locations', 'country')) {
            Schema::table('attendance_gps_locations', function (Blueprint $table): void {
                $table->string('country', 150)->nullable()->after('address_text');
            });
        }

        if (! Schema::hasColumn('attendance_gps_locations', 'city')) {
            Schema::table('attendance_gps_locations', function (Blueprint $table): void {
                $table->string('city', 150)->nullable()->after('country');
            });
        }

        if (! Schema::hasColumn('attendance_gps_locations', 'region')) {
            Schema::table('attendance_gps_locations', function (Blueprint $table): void {
                $table->string('region', 190)->nullable()->after('city');
            });
        }
    }

    public function down(): void
    {
        foreach (['region', 'city', 'country'] as $column) {
            if (! Schema::hasColumn('attendance_gps_locations', $column)) {
                continue;
            }

            Schema::table('attendance_gps_locations', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }
};
