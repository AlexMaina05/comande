<?php
/**
 * Unit tests for worker improvements
 * Run: php tools/tests/test_worker_improvements.php
 * 
 * This test verifies the code improvements without requiring database:
 * - Removal of @ operator from exec calls
 * - Proper error logging
 * - Dry-run mode support
 * - Retry logic configuration
 */

echo "Worker Code Improvements Test Suite\n";
echo "====================================\n\n";

$tests_passed = 0;
$tests_failed = 0;

// Test 1: Verify @ operator removed from api/salva_ordine.php
echo "Test 1: @ operator removed from api/salva_ordine.php... ";
$salvaOrdineContent = file_get_contents(__DIR__ . '/../../api/salva_ordine.php');
// Check for @exec patterns that should not exist
$hasAtExec = preg_match('/@exec\s*\(/', $salvaOrdineContent);
// Check for @shell_exec patterns that should not exist (except comments)
$lines = explode("\n", $salvaOrdineContent);
$foundAtShellExec = false;
foreach ($lines as $line) {
    $trimmed = trim($line);
    // Skip comments
    if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0) {
        continue;
    }
    if (preg_match('/@shell_exec\s*\(/', $line)) {
        $foundAtShellExec = true;
        break;
    }
}

if (!$hasAtExec && !$foundAtShellExec) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (found @ operator on exec/shell_exec)\n";
    $tests_failed++;
}

// Test 2: Verify @ operator removed from api/ripeti_comanda.php
echo "Test 2: @ operator removed from api/ripeti_comanda.php... ";
$ripetiComandaContent = file_get_contents(__DIR__ . '/../../api/ripeti_comanda.php');
$hasAtExec = preg_match('/@exec\s*\(/', $ripetiComandaContent);
$lines = explode("\n", $ripetiComandaContent);
$foundAtShellExec = false;
foreach ($lines as $line) {
    $trimmed = trim($line);
    if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0) {
        continue;
    }
    if (preg_match('/@shell_exec\s*\(/', $line)) {
        $foundAtShellExec = true;
        break;
    }
}

if (!$hasAtExec && !$foundAtShellExec) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (found @ operator on exec/shell_exec)\n";
    $tests_failed++;
}

// Test 3: Verify enhanced logging in api/salva_ordine.php
echo "Test 3: Enhanced logging in api/salva_ordine.php... ";
$hasExitCodeLogging = strpos($salvaOrdineContent, 'exit=$returnVar') !== false || 
                      strpos($salvaOrdineContent, 'exit=$returnVar_ricevuta') !== false;
$hasSuccessLogging = strpos($salvaOrdineContent, 'completata con successo') !== false;

if ($hasExitCodeLogging && $hasSuccessLogging) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (missing enhanced logging)\n";
    $tests_failed++;
}

// Test 4: Verify enhanced logging in api/ripeti_comanda.php
echo "Test 4: Enhanced logging in api/ripeti_comanda.php... ";
$hasExitCodeLogging = strpos($ripetiComandaContent, 'exit=$ret') !== false;
$hasSuccessLogging = strpos($ripetiComandaContent, 'ristampata con successo') !== false;

if ($hasExitCodeLogging && $hasSuccessLogging) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (missing enhanced logging)\n";
    $tests_failed++;
}

// Test 5: Verify dry-run mode support in worker
echo "Test 5: Dry-run mode support in worker... ";
$workerContent = file_get_contents(__DIR__ . '/../../scripts/worker_process_comande.php');
$hasDryRunFlag = strpos($workerContent, '--dry-run') !== false;
$hasDryRunVariable = strpos($workerContent, '$dryRun') !== false;
$hasDryRunCheck = strpos($workerContent, 'if ($dryRun)') !== false;
$hasDryRunMessage = strpos($workerContent, 'DRY-RUN') !== false;

if ($hasDryRunFlag && $hasDryRunVariable && $hasDryRunCheck && $hasDryRunMessage) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (dry-run mode not properly implemented)\n";
    $tests_failed++;
}

// Test 6: Verify retry-lock support in worker
echo "Test 6: Retry-lock parameter support in worker... ";
$hasRetryLockFlag = strpos($workerContent, '--retry-lock=') !== false;
$hasRetryLockVariable = strpos($workerContent, '$retryLock') !== false;
$hasRetryLockLoop = strpos($workerContent, 'while ($lockAttempt < $retryLock') !== false;

if ($hasRetryLockFlag && $hasRetryLockVariable && $hasRetryLockLoop) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (retry-lock not properly implemented)\n";
    $tests_failed++;
}

// Test 7: Verify exponential backoff in worker
echo "Test 7: Exponential backoff for GET_LOCK retries... ";
$hasExponentialBackoff = strpos($workerContent, 'pow(2, $lockAttempt') !== false;
$hasSleepCall = preg_match('/sleep\(\$sleepTime\)/', $workerContent);

if ($hasExponentialBackoff && $hasSleepCall) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (exponential backoff not implemented)\n";
    $tests_failed++;
}

// Test 8: Verify worker returns comande to pending on transient failures
echo "Test 8: Worker returns comande to pending on transient failures... ";
$hasRevertToPending = strpos($workerContent, "Stato = 'pending'") !== false;
$hasLpUnavailableHandling = strpos($workerContent, 'lp non disponibile') !== false;

if ($hasRevertToPending && $hasLpUnavailableHandling) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (pending revert logic not found)\n";
    $tests_failed++;
}

// Test 9: Verify proper error variable handling (no @file_put_contents in worker)
echo "Test 9: Proper error handling in worker file operations... ";
$hasFileWriteWithoutAt = preg_match('/\$written\s*=\s*file_put_contents\s*\(/', $workerContent);
$noAtOnWriteCall = !preg_match('/@file_put_contents/', $workerContent);

if ($hasFileWriteWithoutAt && $noAtOnWriteCall) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (file operations not properly handling errors)\n";
    $tests_failed++;
}

// Test 10: Verify all PHP files have valid syntax
echo "Test 10: PHP syntax validation for all modified files... ";
$files = [
    __DIR__ . '/../../api/salva_ordine.php',
    __DIR__ . '/../../api/ripeti_comanda.php',
    __DIR__ . '/../../scripts/worker_process_comande.php'
];

$allValid = true;
foreach ($files as $file) {
    $output = [];
    $return = null;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return);
    if ($return !== 0) {
        $allValid = false;
        echo "\nSyntax error in " . basename($file) . ": " . implode("\n", $output) . "\n";
        break;
    }
}

if ($allValid) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL\n";
    $tests_failed++;
}

// Test 11: Verify worker documentation is updated
echo "Test 11: Worker usage documentation updated... ";
$hasRetryLockDoc = strpos($workerContent, '--retry-lock=N') !== false;
$hasDryRunDoc = strpos($workerContent, '--dry-run') !== false;
$hasOptionsSection = strpos($workerContent, 'Options:') !== false;

if ($hasRetryLockDoc && $hasDryRunDoc && $hasOptionsSection) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (documentation not complete)\n";
    $tests_failed++;
}

// Test 12: Verify improved error logging with worker ID
echo "Test 12: Worker ID included in error logging... ";
$hasWorkerIdInLog = preg_match('/\[\$workerId\]/', $workerContent);

if ($hasWorkerIdInLog) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (worker ID not in logs)\n";
    $tests_failed++;
}

echo "\n====================================\n";
echo "Total tests: " . ($tests_passed + $tests_failed) . "\n";
echo "Passed: $tests_passed\n";
echo "Failed: $tests_failed\n";

if ($tests_failed === 0) {
    echo "\n✓ All tests passed!\n";
    echo "\nVerified improvements:\n";
    echo "  - Removed @ operator from exec calls\n";
    echo "  - Added comprehensive error logging with exit codes\n";
    echo "  - Implemented dry-run mode for testing\n";
    echo "  - Added retry logic with exponential backoff\n";
    echo "  - Worker returns comande to pending on transient failures\n";
    echo "  - All PHP files have valid syntax\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed!\n";
    exit(1);
}
?>
