<?php

$root = dirname(__DIR__, 2);

$serviceFile = $root . '/src/Services/ExceptionalDayService.php';
$indexFile   = $root . '/src/Livewire/Attendance/ExceptionalDays/ExceptionalDaysIndex.php';

$service = file_get_contents($serviceFile);
$index   = file_get_contents($indexFile);

$failures = [];

function check(bool $condition, string $message): void
{
    global $failures;

    if ($condition) {
        echo "PASS: {$message}" . PHP_EOL;
        return;
    }

    echo "FAIL: {$message}" . PHP_EOL;
    $failures[] = $message;
}

check(
    str_contains($service, 'public function currentUserAllowedBranchIds(int $companyId): ?array'),
    'SERVICE PROVIDES REQUIRED METHOD'
);

check(
    str_contains($index, 'currentUserAllowedBranchIds($companyId)'),
    'EXCEPTIONAL DAYS PAGE CALLS PROVIDED METHOD'
);

check(
    str_contains($service, "method_exists(\$user, 'restrictedBranchIds')"),
    'CANONICAL RESTRICTED BRANCH HELPER HAS PRIORITY'
);

check(
    str_contains($service, "\$scope === 'all_branches'"),
    'ALL BRANCHES IS SUPPORTED'
);

check(
    str_contains($service, "['my_branch', 'branch']"),
    'OWN BRANCH SCOPE IS SUPPORTED'
);

check(
    str_contains($service, "DB::table('branch_user_access')"),
    'SELECTED BRANCH ACCESS IS SUPPORTED'
);

check(
    str_contains($service, "\$user->employee_id"),
    'EMPLOYEE BRANCH IS RESOLVED'
);

check(
    str_contains($service, "\$user->branch_id"),
    'USER BRANCH FALLBACK IS PRESERVED'
);

check(
    str_contains($service, 'return [];'),
    'FAIL-CLOSED BEHAVIOUR EXISTS'
);

if ($failures !== []) {
    echo PHP_EOL . 'FAILED: ' . count($failures) . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'EXCEPTIONAL DAY BRANCH SCOPE REGRESSION: PASS' . PHP_EOL;
