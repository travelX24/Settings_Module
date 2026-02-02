<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_policies')) {
            return;
        }

        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('policy_year_id')->index();

            $table->string('name');
            $table->string('leave_type')->default('annual'); // annual|sick|emergency|...
            $table->decimal('days_per_year', 6, 2)->default(0);

            $table->string('gender')->default('all'); // all|male|female
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_app')->default(true);
            $table->boolean('requires_attachment')->default(false);

            $table->text('description')->nullable();
            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'policy_year_id'], 'leave_policies_company_year_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
    }
};
