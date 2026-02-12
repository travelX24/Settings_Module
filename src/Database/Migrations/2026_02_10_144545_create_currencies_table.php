<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();

            // لو نظامك multi-company: خليه موجود. لو ما تحتاجه اتركه nullable وما بيأثر.
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->string('name');          // اسم العملة (مطلوب)
            $table->string('symbol', 12);    // رمز العملة (مطلوب) مثل: ر.س, $, €
            $table->char('code', 3);         // كود 3 أحرف مثل: KWD
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            // تمييز/تفرد داخل نفس الشركة
            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'symbol']);
            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
