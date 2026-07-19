<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->dropBranchForeignIfExists();

        Schema::table('attendance_gps_locations', function (Blueprint $table) {
            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropBranchForeignIfExists();

        Schema::table('attendance_gps_locations', function (Blueprint $table) {
            $table->foreign('branch_id')
                ->references('id')
                ->on('departments')
                ->onDelete('set null');
        });
    }

    private function dropBranchForeignIfExists(): void
    {
        if (! $this->hasBranchForeign()) {
            return;
        }

        Schema::table('attendance_gps_locations', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
        });
    }

    private function hasBranchForeign(): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'attendance_gps_locations')
            ->where('COLUMN_NAME', 'branch_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
