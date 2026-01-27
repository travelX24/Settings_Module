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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('saas_company_id')->constrained('departments')->nullOnDelete();
            $table->foreignId('job_title_id')->nullable()->after('department_id')->constrained('job_titles')->nullOnDelete();
            
            // Indexes
            $table->index('department_id');
            $table->index('job_title_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('job_title_id');
        });
    }
};





