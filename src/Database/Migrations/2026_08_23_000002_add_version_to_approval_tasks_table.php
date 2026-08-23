<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approval_tasks') && ! Schema::hasColumn('approval_tasks', 'version')) {
            Schema::table('approval_tasks', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('approval_tasks') && Schema::hasColumn('approval_tasks', 'version')) {
            Schema::table('approval_tasks', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
    }
};
