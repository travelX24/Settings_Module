<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            
            $table->string('week_start_day')->default('saturday');
            $table->string('week_end_day')->default('friday');
            $table->json('work_days')->nullable(); // Array of days ['saturday', 'sunday', ...]
            
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('work_schedule_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained('work_schedules')->cascadeOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_night_shift')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('work_schedule_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('fixed'); // fixed, rotating, split
            $table->json('config')->nullable(); // Stores times, rotation rules
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('work_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained('work_schedules')->cascadeOnDelete();
            $table->string('day_of_week')->nullable(); // If for a specific day
            $table->date('specific_date')->nullable(); // If for a specific date
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_night_shift')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedule_exceptions');
        Schema::dropIfExists('work_schedule_shifts');
        Schema::dropIfExists('work_schedule_periods');
        Schema::dropIfExists('work_schedules');
    }
};





