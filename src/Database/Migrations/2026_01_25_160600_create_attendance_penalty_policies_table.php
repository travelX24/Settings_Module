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
        Schema::create('attendance_penalty_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->nullable()->constrained('attendance_policies')->cascadeOnDelete();
            
            $table->enum('violation_type', ['late_arrival', 'early_departure', 'auto_checkout']);
            $table->unsignedInteger('minutes_from');
            $table->unsignedInteger('minutes_to');
            $table->unsignedTinyInteger('recurrence_from')->default(1);
            $table->unsignedTinyInteger('recurrence_to')->default(1);
            
            $table->enum('penalty_action', [
                'skip', 
                'notification', 
                'warning_verbal', 
                'warning_written', 
                'deduction', 
                'suspension', 
                'termination'
            ]);
            
            // Deduction fields
            $table->enum('deduction_type', ['fixed', 'percentage'])->nullable();
            $table->decimal('deduction_value', 10, 2)->nullable();
            
            // Suspension fields
            $table->unsignedInteger('suspension_days')->nullable();
            
            // Notification template
            $table->text('notification_message')->nullable();
            
            $table->boolean('is_active')->default(true);
            
            // Audit fields
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['policy_id', 'violation_type']);
            $table->index(['violation_type', 'minutes_from', 'minutes_to'], 'penalties_violation_minutes_index');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_penalty_policies');
    }
};





