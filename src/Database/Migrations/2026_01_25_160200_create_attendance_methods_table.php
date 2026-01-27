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
        Schema::create('attendance_methods', function (Blueprint $table) {
            $table->id();
            $table->enum('method', ['gps', 'fingerprint', 'nfc']);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('device_count')->default(0);
            
            $table->timestamps();
            
            // Unique constraint
            $table->unique('method');
            
            // Indexes
            $table->index('is_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_methods');
    }
};





