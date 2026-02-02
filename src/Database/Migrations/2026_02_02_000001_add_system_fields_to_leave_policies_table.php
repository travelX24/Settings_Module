<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $db = DB::getDatabaseName();
            $rows = DB::select(
                "SELECT COUNT(1) AS cnt
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND INDEX_NAME = ?",
                [$db, $table, $indexName]
            );

            return (int) ($rows[0]->cnt ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function up(): void
    {
        if (! Schema::hasTable('leave_policies')) {
            return;
        }

        Schema::table('leave_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_policies', 'code')) {
                $table->string('code', 50)->nullable()->after('policy_year_id');
            }

            if (! Schema::hasColumn('leave_policies', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('code');
            }

            if (! Schema::hasColumn('leave_policies', 'is_deletable')) {
                $table->boolean('is_deletable')->default(true)->after('is_system');
            }
        });

        // Unique: company + year + code (يسمح بتعدد NULL)
        if (! $this->indexExists('leave_policies', 'leave_policies_company_year_code_unique')) {
            Schema::table('leave_policies', function (Blueprint $table) {
                $table->unique(['company_id', 'policy_year_id', 'code'], 'leave_policies_company_year_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_policies')) {
            return;
        }

        if ($this->indexExists('leave_policies', 'leave_policies_company_year_code_unique')) {
            Schema::table('leave_policies', function (Blueprint $table) {
                $table->dropUnique('leave_policies_company_year_code_unique');
            });
        }

        Schema::table('leave_policies', function (Blueprint $table) {
            if (Schema::hasColumn('leave_policies', 'is_deletable')) {
                $table->dropColumn('is_deletable');
            }
            if (Schema::hasColumn('leave_policies', 'is_system')) {
                $table->dropColumn('is_system');
            }
            if (Schema::hasColumn('leave_policies', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};
