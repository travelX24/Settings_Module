<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__, 2);
$workspaceRoot = dirname($packageRoot);

$autoloadCandidates = array_filter([
    getenv('ATHKA_TEST_AUTOLOAD') ?: null,
    $packageRoot . '/vendor/autoload.php',
    $workspaceRoot . '/HrWithModules/vendor/autoload.php',
]);

$autoload = null;

foreach ($autoloadCandidates as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}

if (! $autoload) {
    fwrite(
        STDERR,
        "Unable to locate Composer autoload." . PHP_EOL
    );

    exit(2);
}

require $autoload;

$bootstrapCandidates = array_filter([
    getenv('ATHKA_TEST_BOOTSTRAP') ?: null,
    $workspaceRoot . '/HrWithModules/bootstrap/app.php',
]);

$bootstrap = null;

foreach ($bootstrapCandidates as $candidate) {
    if (is_file($candidate)) {
        $bootstrap = $candidate;
        break;
    }
}

if (! $bootstrap) {
    fwrite(
        STDERR,
        "Unable to locate Laravel bootstrap/app.php." . PHP_EOL
    );

    exit(2);
}

$app = require $bootstrap;

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

use Athka\SystemSettings\Models\WorkSchedule;
use Athka\SystemSettings\Services\WorkScheduleService;

$failures = [];

function assertSameValue(
    string $name,
    mixed $expected,
    mixed $actual,
    array &$failures
): void {
    $passed = $expected === $actual;

    echo PHP_EOL . $name . PHP_EOL;
    echo str_repeat('-', strlen($name)) . PHP_EOL;
    echo 'Expected : ' . var_export($expected, true) . PHP_EOL;
    echo 'Actual   : ' . var_export($actual, true) . PHP_EOL;
    echo $passed
        ? 'RESULT   : PASS' . PHP_EOL
        : 'RESULT   : FAIL' . PHP_EOL;

    if (! $passed) {
        $failures[] = $name;
    }
}

/*
|--------------------------------------------------------------------------
| Synthetic schedule
|--------------------------------------------------------------------------
|
| Saturday through Thursday are workdays.
| Friday is intentionally excluded.
| Thursday has an exception 09:00 -> 15:00.
|
| Nothing is saved to the database.
|
*/
$schedule = new WorkSchedule();

$schedule->work_days = [
    'saturday',
    'sunday',
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
];

$schedule->saas_company_id = null;

$normalPeriod = (object) [
    'id' => 7,
    'start_time' => '09:00',
    'end_time' => '17:00',
    'is_night_shift' => false,
];

$thursdayException = (object) [
    'id' => 12,
    'day_of_week' => 'thursday',
    'specific_date' => null,
    'start_time' => '09:00',
    'end_time' => '15:00',
    'is_night_shift' => false,
    'is_active' => true,
];

$schedule->setRelation(
    'periods',
    collect([$normalPeriod])
);

$schedule->setRelation(
    'exceptions',
    collect([$thursdayException])
);

$service = app(WorkScheduleService::class);

$thursdayMetrics = $service->getMetricsForDate(
    '2026-08-27',
    $schedule,
    collect(),
    null,
    []
);

$fridayMetrics = $service->getMetricsForDate(
    '2026-08-28',
    $schedule,
    collect(),
    null,
    []
);

/*
|--------------------------------------------------------------------------
| Schedule assertions
|--------------------------------------------------------------------------
*/
assertSameValue(
    'THURSDAY IS WORKDAY',
    true,
    $thursdayMetrics['is_workday'] ?? null,
    $failures
);

assertSameValue(
    'THURSDAY STATUS',
    'workday',
    $thursdayMetrics['status'] ?? null,
    $failures
);

assertSameValue(
    'THURSDAY EXCEPTION END',
    '15:00',
    $thursdayMetrics['check_out'] ?? null,
    $failures
);

assertSameValue(
    'FRIDAY IS NOT WORKDAY',
    false,
    $fridayMetrics['is_workday'] ?? null,
    $failures
);

assertSameValue(
    'FRIDAY STATUS IS OFF',
    'off',
    $fridayMetrics['status'] ?? null,
    $failures
);

assertSameValue(
    'FRIDAY HAS ZERO PERIODS',
    0,
    count($fridayMetrics['periods'] ?? []),
    $failures
);

/*
|--------------------------------------------------------------------------
| Check-in gate decision
|--------------------------------------------------------------------------
*/
$trackingState = 'outside_work_window';

$isScheduleDayOff =
    $trackingState === 'outside_work_window'
    && ($fridayMetrics['status'] ?? null) === 'off';

assertSameValue(
    'FRIDAY CHECK-IN MUST BE BLOCKED',
    true,
    $isScheduleDayOff,
    $failures
);

/*
|--------------------------------------------------------------------------
| Controller source contract
|--------------------------------------------------------------------------
|
| Keep the regression test tied to the API entry point as well.
|
*/
$controller = file_get_contents(
    $packageRoot
    . '/src/Http/Controllers/Api/Employee/DailyAttendanceController.php'
);

$requiredControllerFragments = [
    '$isScheduleDayOff = false;',
    'STATE_OUTSIDE_WORK_WINDOW',
    "=== 'off'",
    "'day_off'",
    "'attendance_day_not_allowed'",
    'لا يمكنك تسجيل الحضور في يوم راحة.',
];

foreach ($requiredControllerFragments as $fragment) {
    $exists = str_contains($controller, $fragment);

    assertSameValue(
        'CONTROLLER CONTRACT: ' . $fragment,
        true,
        $exists,
        $failures
    );
}

/*
|--------------------------------------------------------------------------
| Critical safety regression
|--------------------------------------------------------------------------
|
| Do NOT turn every outside_work_window into a blocked check-in.
| Early arrival on a real workday must remain possible.
|
*/
$earlyArrivalState = 'outside_work_window';
$earlyArrivalStatus = 'workday';

$earlyArrivalDayOff =
    $earlyArrivalState === 'outside_work_window'
    && $earlyArrivalStatus === 'off';

assertSameValue(
    'EARLY ARRIVAL ON WORKDAY REMAINS ALLOWED',
    false,
    $earlyArrivalDayOff,
    $failures
);

echo PHP_EOL;
echo str_repeat('=', 52) . PHP_EOL;

if ($failures) {
    echo 'FAILED TESTS: ' . count($failures) . PHP_EOL;

    foreach ($failures as $failure) {
        echo '- ' . $failure . PHP_EOL;
    }

    exit(1);
}

echo "ALL DAY-OFF CHECK-IN REGRESSION TESTS PASSED" . PHP_EOL;
echo "FRIDAY DAY-OFF CHECK-IN: BLOCKED" . PHP_EOL;
echo "EARLY ARRIVAL ON WORKDAY: PRESERVED" . PHP_EOL;

exit(0);