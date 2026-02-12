<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            // أسماء الفهارس الافتراضية في Laravel غالباً هكذا:
            $table->dropUnique('currencies_company_id_name_unique');
            $table->dropUnique('currencies_company_id_symbol_unique');
            $table->dropUnique('currencies_company_id_code_unique');

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropUnique('currencies_company_id_code_unique');

            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'symbol']);
            $table->unique(['company_id', 'code']);
        });
    }
};
