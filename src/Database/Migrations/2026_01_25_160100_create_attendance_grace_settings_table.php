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
        Schema::create('attendance_grace_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('late_grace_minutes')->default(15);
            $table->unsignedInteger('early_leave_grace_minutes')->default(10);
            $table->unsignedInteger('auto_checkout_after_minutes')->default(120);
            $table->boolean('is_global_default')->default(false);
            
            $table->timestamps();
            
            // Indexes
            $table->index('is_global_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_grace_settings');
    }
};





