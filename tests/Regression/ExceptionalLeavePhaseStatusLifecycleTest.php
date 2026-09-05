<?php

$serviceFile = dirname(__DIR__, 2)
    . '/src/Services/Approvals/ApprovalService.php';

$code = file_get_contents($serviceFile);

if ($code === false) {
    fwrite(STDERR, "FAIL: ApprovalService.php could not be read\n");
    exit(1);
}

$checks = [
    'EXCEPTION PHASE STATUS HELPER EXISTS'
        => str_contains($code, 'syncExceptionalLeavePhaseStatus'),

    'PROCESS TASK CALLS STATUS HELPER'
        => preg_match(
            '/syncExceptionalLeavePhaseStatus\s*\(\s*\$freshTask\s*,\s*\$status\s*\)/',
            $code
        ) === 1,

    'EXCEPTION PHASE CAN BECOME APPROVED'
        => str_contains($code, "'exception_status' => 'approved'"),

    'EXCEPTION PHASE CAN BECOME REJECTED'
        => str_contains($code, "'exception_status' => 'rejected'"),

    'STANDARD REJECTION CANNOT REJECT EXCEPTION PHASE'
        => str_contains($code, '$taskPosition <= $exceptionLastPosition'),

    'LAST EXCEPTION POSITION MARKS PHASE APPROVED'
        => str_contains($code, '$taskPosition === $exceptionLastPosition'),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;

    if (!$ok) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);