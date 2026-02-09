<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('approval_policy_scopes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->index();

            // نخزن ids حسب scope_type في policy (يعني هنا مجرد values)
            $table->unsignedBigInteger('scope_id')->index();

            $table->timestamps();

            $table->unique(['policy_id', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_policy_scopes');
    }
};
