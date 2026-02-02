<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_policy_years')) {
            return;
        }

        Schema::create('leave_policy_years', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedSmallInteger('year')->index();

            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['company_id', 'year'], 'leave_policy_years_company_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policy_years');
    }
};
