<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_exceptional_days', function (Blueprint $table) {
            $table->id();

            // لو نظامكم multi-tenant:
            $table->unsignedBigInteger('company_id')->index();

            $table->string('name');                 // اسم اليوم
            $table->text('description')->nullable();// الوصف

            // single | range
            $table->string('period_type')->default('single');

            $table->date('start_date')->index();
            $table->date('end_date')->index();

            // working_days | all_days
            $table->string('apply_on')->default('working_days');

            // مضاعفات الخصم (1.00 إلى 10.00)
            $table->decimal('absence_multiplier', 5, 2)->default(1.00);
            $table->decimal('late_multiplier', 5, 2)->default(1.00);

            // ساعات السماح (قبل تطبيق المضاعف على التأخير)
            $table->unsignedSmallInteger('grace_hours')->default(0);

            // all | limited
            $table->string('scope_type')->default('all');

            // قوائم نطاق التطبيق (IDs) + استثناءات
            $table->json('include')->nullable(); // departments/sections/employees
            $table->json('exclude')->nullable(); // departments/sections/employees + reasons

            // سياسة الإشعار
            // none | days_3 | week_1 | weeks_2
            $table->string('notify_policy')->default('none');
            $table->text('notify_message')->nullable();
            $table->timestamp('notified_at')->nullable();

            // retroactive: from_created | full_period
            $table->string('retroactive')->default('from_created');

            // مفعل/معطل (أما "منتهي/مستقبلي" نطلعها computed من التواريخ)
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_exceptional_days');
    }
};
