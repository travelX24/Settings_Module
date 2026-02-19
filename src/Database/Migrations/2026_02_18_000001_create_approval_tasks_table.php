<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approval_tasks')) return;

        Schema::create('approval_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id')->index();
            $table->string('operation_key', 60)->index(); // leaves, overtime...

            // نوع الطلب: leaves | permissions (مفتاح بسيط)
            $table->string('approvable_type', 40)->index();
            $table->unsignedBigInteger('approvable_id')->index();

            // صاحب الطلب (Employee)
            $table->unsignedBigInteger('request_employee_id')->nullable()->index();

            // ترتيب الخطوة من policy
            $table->unsignedInteger('position')->default(1);

            // الموظف المكلّف بالموافقة لهذه الخطوة
            $table->unsignedBigInteger('approver_employee_id')->nullable()->index();

            // waiting | pending | approved | rejected | canceled | skipped
            $table->string('status', 20)->default('waiting')->index();

            // action info
            $table->unsignedBigInteger('acted_by_employee_id')->nullable()->index();
            $table->timestamp('acted_at')->nullable();
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->unique(['approvable_type', 'approvable_id', 'position'], 'approval_tasks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_tasks');
    }
};
