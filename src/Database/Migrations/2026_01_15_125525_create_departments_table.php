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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('saas_company_id')->constrained('saas_companies')->cascadeOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('parent_id');
            $table->index('saas_company_id');
            $table->index('is_active');
            
            // Unique constraints per company
            $table->unique(['name', 'saas_company_id']);
            $table->unique(['code', 'saas_company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};





