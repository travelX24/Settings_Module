<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_policy_years')) {
            return;
        }

        Schema::table('leave_policy_years', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_policy_years', 'is_closed')) {
                $table->boolean('is_closed')->default(false)->after('is_active');
            }

            if (! Schema::hasColumn('leave_policy_years', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('is_closed');
            }

            if (! Schema::hasColumn('leave_policy_years', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('closed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_policy_years')) {
            return;
        }

        Schema::table('leave_policy_years', function (Blueprint $table) {
            if (Schema::hasColumn('leave_policy_years', 'published_at')) {
                $table->dropColumn('published_at');
            }
            if (Schema::hasColumn('leave_policy_years', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
            if (Schema::hasColumn('leave_policy_years', 'is_closed')) {
                $table->dropColumn('is_closed');
            }
        });
    }
};
