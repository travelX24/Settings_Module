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
        Schema::table('attendance_methods', function (Blueprint $table) {
            // Drop old unique constraint
            $table->dropUnique(['method']);
            
            // Add new composite unique constraint
            $table->unique(['method', 'saas_company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_methods', function (Blueprint $table) {
            $table->dropUnique(['method', 'saas_company_id']);
            $table->unique(['method']);
        });
    }
};





