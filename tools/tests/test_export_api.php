<?php
/**
 * Unit test for Export API functionality
 * Run: php tools/tests/test_export_api.php
 */

echo "Testing Export API Functionality\n";
echo "================================\n\n";

$tests_passed = 0;
$tests_failed = 0;

// Test 1: Check gestisci_dati.php syntax
echo "Test 1: PHP syntax validation... ";
$output = [];
$return_var = 0;
exec('php -l api/gestisci_dati.php 2>&1', $output, $return_var);
if ($return_var === 0 && strpos(implode(' ', $output), 'No syntax errors') !== false) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL\n";
    echo "  Output: " . implode("\n  ", $output) . "\n";
    $tests_failed++;
}

// Test 2: Verify export log directory exists
echo "Test 2: Export log directory... ";
if (is_dir('logs')) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (logs directory not found)\n";
    $tests_failed++;
}

// Test 3: Check if .gitkeep exists in logs
echo "Test 3: Logs .gitkeep file... ";
if (file_exists('logs/.gitkeep')) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (.gitkeep not found)\n";
    $tests_failed++;
}

// Test 4: Verify EXPORT.md documentation exists
echo "Test 4: Documentation file exists... ";
if (file_exists('docs/EXPORT.md')) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (docs/EXPORT.md not found)\n";
    $tests_failed++;
}

// Test 5: Check documentation content
echo "Test 5: Documentation content... ";
$doc_content = file_get_contents('docs/EXPORT.md');
$required_sections = ['Panoramica', 'Tabelle Supportate', 'CSV', 'SQL', 'Sicurezza', 'API'];
$all_sections_found = true;
foreach ($required_sections as $section) {
    if (strpos($doc_content, $section) === false) {
        $all_sections_found = false;
        break;
    }
}
if ($all_sections_found) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (missing required sections)\n";
    $tests_failed++;
}

// Test 6: Verify admin.php has export tab
echo "Test 6: Admin panel export tab... ";
$admin_content = file_get_contents('public/admin.php');
if (strpos($admin_content, 'tab-export') !== false && 
    strpos($admin_content, 'Export / Backup') !== false) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (export tab not found in public/admin.php)\n";
    $tests_failed++;
}

// Test 7: Verify admin.js has export handlers
echo "Test 7: Admin JS export handlers... ";
$admin_js_content = file_get_contents('public/assets/js/admin.js');
if (strpos($admin_js_content, 'export-btn') !== false && 
    strpos($admin_js_content, 'action=export') !== false) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (export handlers not found in public/assets/js/admin.js)\n";
    $tests_failed++;
}

// Test 8: Verify style.css has export section styles
echo "Test 8: Export section CSS styles... ";
$style_content = file_get_contents('public/assets/css/style.css');
if (strpos($style_content, '.export-section') !== false && 
    strpos($style_content, '.export-btn') !== false) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (export styles not found in public/assets/css/style.css)\n";
    $tests_failed++;
}

// Test 9: Check gestisci_dati.php has export action handling
echo "Test 9: Export action handling in API... ";
$api_content = file_get_contents('api/gestisci_dati.php');
if (strpos($api_content, "action === 'export'") !== false && 
    strpos($api_content, 'is_admin()') !== false &&
    strpos($api_content, 'table_whitelist') !== false) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (export action not properly implemented)\n";
    $tests_failed++;
}

// Test 10: Verify whitelist tables in API
echo "Test 10: Whitelist validation... ";
if (strpos($api_content, "'prodotti'") !== false && 
    strpos($api_content, "'ordini'") !== false &&
    strpos($api_content, "'comande'") !== false) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (whitelist tables not found)\n";
    $tests_failed++;
}

// Test 11: Check for CSV export implementation
echo "Test 11: CSV export implementation... ";
if (strpos($api_content, "format === 'csv'") !== false && 
    strpos($api_content, 'text/csv') !== false &&
    strpos($api_content, 'fputcsv') !== false) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (CSV export not properly implemented)\n";
    $tests_failed++;
}

// Test 12: Check for SQL export implementation
echo "Test 12: SQL export implementation... ";
if (strpos($api_content, "format === 'sql'") !== false && 
    strpos($api_content, 'INSERT INTO') !== false &&
    strpos($api_content, 'text/plain') !== false) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (SQL export not properly implemented)\n";
    $tests_failed++;
}

// Test 13: Check for authentication check
echo "Test 13: Authentication verification... ";
if (strpos($api_content, 'is_admin()') !== false && 
    strpos($api_content, 'session_start()') !== false) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (authentication not properly checked)\n";
    $tests_failed++;
}

// Test 14: Check for logging function
echo "Test 14: Export logging function... ";
if (strpos($api_content, 'log_export') !== false && 
    strpos($api_content, 'export.log') !== false) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (logging not implemented)\n";
    $tests_failed++;
}

// Test 15: Check for security measures
echo "Test 15: Security measures... ";
$security_checks = 0;
if (strpos($api_content, '$table_whitelist') !== false) $security_checks++;
if (strpos($api_content, 'in_array') !== false) $security_checks++;
if (strpos($api_content, '$conn->quote') !== false || strpos($api_content, 'prepare') !== false) $security_checks++;
if ($security_checks >= 3) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL (insufficient security measures)\n";
    $tests_failed++;
}

// Test 16: Check .gitignore for log files
echo "Test 16: .gitignore configuration... ";
if (file_exists('.gitignore')) {
    $gitignore_content = file_get_contents('.gitignore');
    if (strpos($gitignore_content, 'logs/*.log') !== false) {
        echo "PASS\n";
        $tests_passed++;
    } else {
        echo "FAIL (logs/*.log not in .gitignore)\n";
        $tests_failed++;
    }
} else {
    echo "FAIL (.gitignore not found)\n";
    $tests_failed++;
}

echo "\n================================\n";
echo "Total tests: " . ($tests_passed + $tests_failed) . "\n";
echo "Passed: $tests_passed\n";
echo "Failed: $tests_failed\n";

if ($tests_failed === 0) {
    echo "\n✓ All tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed!\n";
    exit(1);
}
?>
