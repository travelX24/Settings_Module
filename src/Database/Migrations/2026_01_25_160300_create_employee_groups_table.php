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
        Schema::create('employee_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            
            $table->foreignId('applied_policy_id')->constrained('attendance_policies')->cascadeOnDelete();
            
            $table->enum('grace_source', ['use_global', 'custom'])->default('use_global');
            $table->foreignId('grace_setting_id')->nullable()->constrained('attendance_grace_settings')->nullOnDelete();
            
            $table->boolean('is_active')->default(true);
            
            // Audit fields
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            // Indexes
            $table->index('is_active');
            $table->index('applied_policy_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_groups');
    }
};





