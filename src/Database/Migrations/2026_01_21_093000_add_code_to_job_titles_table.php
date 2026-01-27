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
        Schema::table('job_titles', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            // Adding unique constraint if needed, or just the column
            // $table->unique(['code', 'saas_company_id']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_titles', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};





