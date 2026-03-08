<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('approval_policy_steps', function (Blueprint $table) {
            $table->boolean('follow_standard')->default(false)->after('approver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_policy_steps', function (Blueprint $table) {
            $table->dropColumn('follow_standard');
        });
    }
};
