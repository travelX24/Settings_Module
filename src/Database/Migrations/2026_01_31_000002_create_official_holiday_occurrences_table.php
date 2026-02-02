<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('official_holiday_occurrences', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('template_id')->index();

            $table->unsignedSmallInteger('year_greg')->index();
            $table->unsignedSmallInteger('year_hijri')->nullable()->index();

            $table->date('start_date')->index();
            $table->date('end_date')->index();

            $table->unsignedSmallInteger('duration_days')->default(1);

            // Prepared display (optional): e.g. "01 Shawwal 1447 - 04 Shawwal 1447"
            $table->string('display_hijri')->nullable();

            $table->boolean('is_tentative')->default(false);
            $table->boolean('is_overridden')->default(false);

            $table->timestamps();

            $table->unique(['company_id', 'template_id', 'year_greg'], 'uniq_holiday_occ_per_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_holiday_occurrences');
    }
};
