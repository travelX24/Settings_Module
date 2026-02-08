<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permission_policies', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('policy_year_id')->index();

            $table->boolean('approval_required')->default(true);

            // نخزن بالدقائق لتجنب مشاكل الـ float
            $table->integer('monthly_limit_minutes')->default(0); // 0 = unlimited
            $table->integer('max_request_minutes')->default(0);   // 0 = unlimited

            $table->string('deduction_policy', 60)->default('not_allowed_after_limit');
            $table->boolean('show_in_app')->default(true);

            $table->boolean('requires_attachment')->default(false);
            $table->json('attachment_types')->nullable();
            $table->unsignedSmallInteger('attachment_max_mb')->default(2);

            $table->json('settings')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'policy_year_id']);

            // لو جدولك اسمه مختلف عدّل هنا
            $table->foreign('policy_year_id')
                ->references('id')
                ->on('leave_policy_years')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_policies');
    }
};
