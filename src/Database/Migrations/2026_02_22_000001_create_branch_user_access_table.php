<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_user_access', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('saas_company_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('branch_id');
            $table->timestamps();

            $table->unique(['user_id', 'branch_id']);
            $table->index(['saas_company_id', 'user_id']);
            $table->index(['saas_company_id', 'branch_id']);

            // (اختياري) مفاتيح خارجية إذا قواعدك تسمح:
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user_access');
    }
};