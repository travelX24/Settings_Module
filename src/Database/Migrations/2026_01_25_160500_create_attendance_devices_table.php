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
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->enum('device_type', ['fingerprint', 'nfc']);
            $table->string('name');
            $table->foreignId('branch_id')->constrained('departments')->cascadeOnDelete();
            $table->string('location_in_branch')->nullable();
            $table->string('serial_no')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Audit fields
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['device_type', 'serial_no'], 'unique_device_serial');
            
            // Indexes
            $table->index('is_active');
            $table->index('branch_id');
            $table->index('device_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_devices');
    }
};





