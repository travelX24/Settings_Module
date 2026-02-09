<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('approval_policies', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')->index();

            // العملية: leaves | overtime | compensations | advances | terminations
            $table->string('operation_key', 50)->index();

            $table->string('name');
            $table->boolean('is_active')->default(true)->index();

            // نطاق التطبيق (نوع واحد لكل سياسة) + القيم في جدول scopes
            $table->string('scope_type', 30)->default('all')->index(); // all|department|job_title|branch|employee

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['company_id', 'operation_key', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_policies');
    }
};
