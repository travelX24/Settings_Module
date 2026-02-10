<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // MySQL-safe: no doctrine/dbal needed
        DB::statement("ALTER TABLE `approval_policy_steps` MODIFY `approver_type` VARCHAR(20) NOT NULL");
        DB::statement("ALTER TABLE `approval_policy_steps` MODIFY `approver_id` BIGINT UNSIGNED NOT NULL DEFAULT 0");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `approval_policy_steps` MODIFY `approver_type` VARCHAR(10) NOT NULL");
        DB::statement("ALTER TABLE `approval_policy_steps` MODIFY `approver_id` BIGINT UNSIGNED NOT NULL");
    }
};
