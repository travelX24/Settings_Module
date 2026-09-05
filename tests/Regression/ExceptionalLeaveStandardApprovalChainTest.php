<?php

$root = dirname(__DIR__, 2);
$serviceFile = $root . '/src/Services/Approvals/ApprovalService.php';

$service = file_get_contents($serviceFile);

if ($service === false) {
    fwrite(STDERR, "FAIL: ApprovalService.php could not be read\n");
    exit(1);
}

/*
 * Keep the test independent from Windows CRLF / Unix LF
 * and from harmless indentation differences.
 */
$service = str_replace(["\r\n", "\r"], "\n", $service);

$checks = [
    'FOLLOW STANDARD FLAG IS READ' =>
        str_contains($service, 'policyFollowsStandard'),

    'EXCEPTIONAL WORKFLOW VALIDATES STANDARD LEAVE WORKFLOW' =>
        preg_match(
            '/hasApproversForEmployee\s*\(\s*[\'"]leaves[\'"]\s*,\s*\$employeeId\s*,\s*\$companyId\s*,\s*\$standardReason\s*\)/s',
            $service
        ) === 1,

    'STANDARD TASKS ARE APPENDED' =>
        str_contains($service, 'appendStandardLeaveApprovalTasks'),

    'STANDARD POLICY IS RESOLVED FOR SAME EMPLOYEE' =>
        preg_match(
            '/resolvePolicyForEmployee\s*\(\s*[\'"]leaves[\'"]\s*,\s*\$employeeId\s*,\s*\$companyId\s*\)/s',
            $service
        ) === 1,

    'STANDARD POSITIONS FOLLOW EXCEPTIONAL POSITIONS' =>
        str_contains(
            $service,
            '$positionOffset + (int) $step->position'
        ),

    'STANDARD TASKS WAIT FOR EXCEPTIONAL APPROVALS' =>
        preg_match(
            '/[\'"]status[\'"]\s*=>\s*[\'"]waiting[\'"]/',
            $service
        ) === 1,
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;

    if (!$ok) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);