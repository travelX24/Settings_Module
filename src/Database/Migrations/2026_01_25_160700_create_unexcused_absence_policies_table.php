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
        Schema::create('unexcused_absence_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->nullable()->constrained('attendance_policies')->cascadeOnDelete();
            
            $table->enum('absence_reason_type', [
                'no_notice',
                'repetitive',
                'consecutive',
                'late_early',
                'after_rejection'
            ]);
            
            $table->enum('day_selector_type', ['single', 'range']);
            $table->unsignedInteger('day_from');
            $table->unsignedInteger('day_to');
            
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
            
            // Special case fields (for late_early type)
            $table->unsignedInteger('late_minutes')->nullable();
            $table->unsignedInteger('early_leave_minutes')->nullable();
            $table->unsignedTinyInteger('recurrence_count')->nullable();
            
            $table->boolean('is_active')->default(true);
            
            // Audit fields
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['policy_id', 'absence_reason_type']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unexcused_absence_policies');
    }
};





