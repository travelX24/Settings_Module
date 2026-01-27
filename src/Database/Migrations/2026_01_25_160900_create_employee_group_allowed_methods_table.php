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
        Schema::create('employee_group_allowed_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('employee_groups')->cascadeOnDelete();
            $table->enum('method', ['gps', 'fingerprint', 'nfc']);
            $table->boolean('is_allowed')->default(true);
            
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['group_id', 'method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_group_allowed_methods');
    }
};





