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
        // Use raw SQL to modify ENUM column to include 'automatic'
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE attendance_policies MODIFY COLUMN tracking_mode ENUM('check_in_only', 'check_in_out', 'manual', 'automatic') DEFAULT 'check_in_out'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert ENUM column to original values
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE attendance_policies MODIFY COLUMN tracking_mode ENUM('check_in_only', 'check_in_out', 'manual') DEFAULT 'check_in_out'");
    }
};
