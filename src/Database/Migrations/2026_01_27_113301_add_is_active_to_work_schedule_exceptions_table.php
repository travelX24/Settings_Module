php artisan make:migration add_is_active_to_work_schedule_exceptions_table --table=work_schedule_exceptions

   INFO  Migration [C:\xampp\htdocs\Laravel\Athka_HR\HrWithModules\database\migrations\2026_01_27_113202_add_is_active_to_work_schedule_exceptions_table.php] created successfully.

PS C:\xampp\htdocs\Laravel\Athka_HR\HrWithModules> php artisan make:migration add_is_active_to_work_schedule_exceptions_table --table=work_schedule_exceptions

   INFO  Migration [C:\xampp\htdocs\Laravel\Athka_HR\HrWithModules\database\migrations\2026_01_27_113301_add_is_active_to_work_schedule_exceptions_table.php] created successfully.  <?php

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
        Schema::table('work_schedule_exceptions', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_night_shift');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_schedule_exceptions', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};





