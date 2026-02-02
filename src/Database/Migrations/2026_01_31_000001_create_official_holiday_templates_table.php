<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('official_holiday_templates', function (Blueprint $table) {
            $table->id();

            // Keep it FK-free for compatibility (avoid breaking if table names differ).
            $table->unsignedBigInteger('company_id')->index();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();

            $table->enum('calendar_type', ['gregorian', 'hijri'])->default('gregorian');
            $table->enum('repeat_type', ['annual', 'once', 'moon_sighting'])->default('annual');

            // For Hijri annual rules
            $table->unsignedTinyInteger('hijri_month')->nullable();
            $table->unsignedTinyInteger('hijri_day')->nullable();

            // For Gregorian annual rules
            $table->unsignedTinyInteger('greg_month')->nullable();
            $table->unsignedTinyInteger('greg_day')->nullable();

            // For once rules (stored as Gregorian ISO)
            $table->date('once_start_date')->nullable();

            $table->unsignedSmallInteger('duration_days')->default(1);

            $table->enum('scope_type', ['company', 'branch', 'sector', 'employees', 'country_mandatory'])->default('company');
            $table->json('branch_ids')->nullable();
            $table->json('excluded_group_ids')->nullable();

            $table->enum('payroll_effect', ['paid', 'unpaid', 'half_paid'])->default('paid');
            $table->enum('overtime_policy', ['blocked', 'double', 'normal'])->default('blocked');
            $table->unsignedSmallInteger('notify_days')->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'calendar_type']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_holiday_templates');
    }
};
