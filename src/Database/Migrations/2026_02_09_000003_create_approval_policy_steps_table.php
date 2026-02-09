<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('approval_policy_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->index();

            $table->unsignedInteger('position')->index(); // 1..N

            // approver_type: role | user
            $table->string('approver_type', 10);
            $table->unsignedBigInteger('approver_id');

            $table->timestamps();

            $table->unique(['policy_id', 'position']);
            $table->index(['approver_type', 'approver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_policy_steps');
    }
};
