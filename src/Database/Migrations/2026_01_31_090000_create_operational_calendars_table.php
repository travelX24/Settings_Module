<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operational_calendars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('timezone', 64)->default(config('app.timezone', 'Asia/Aden'));
            $table->unsignedTinyInteger('week_starts_on')->default(6);
            $table->json('working_days');
            $table->time('work_start')->default('09:00:00');
            $table->time('work_end')->default('17:00:00');
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_calendars');
    }
};
