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
        Schema::create('attendance_gps_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address_text')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedInteger('radius_meters')->default(100);
            
            // Target (branch OR employee_group - only one can be set)
            $table->foreignId('branch_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('employee_group_id')->nullable()->constrained('employee_groups')->nullOnDelete();
            
            $table->boolean('is_active')->default(true);
            
            // Audit fields
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            // Indexes
            $table->index('is_active');
            $table->index(['lat', 'lng']);
            $table->index('branch_id');
            $table->index('employee_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_gps_locations');
    }
};





