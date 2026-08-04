<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_gps_locations', function (Blueprint $table) {
            $table->string('geofence_type', 20)->default('circle')->after('radius_meters');
            $table->longText('boundary_geojson')->nullable()->after('geofence_type');
            $table->index('geofence_type');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_gps_locations', function (Blueprint $table) {
            $table->dropIndex(['geofence_type']);
            $table->dropColumn(['boundary_geojson', 'geofence_type']);
        });
    }
};
