<?php
/**
 * Simple unit test for ApiResponse helper
 * Run: php -d error_reporting=0 tools/tests/test_api_response.php
 * 
 * Note: Run with error_reporting=0 to suppress header warnings in CLI mode
 */

require_once __DIR__ . '/../../api/response.php';

echo "Testing ApiResponse Helper\n";
echo "==========================\n\n";

$tests_passed = 0;
$tests_failed = 0;

// Override exit to prevent script termination during tests
class ExitException extends Exception {}
function exit_handler() {
    throw new ExitException();
}

// Test helper to capture output and handle exit
function test_api_call($callback) {
    ob_start();
    try {
        $callback();
    } catch (ExitException $e) {
        // Expected - ApiResponse calls exit()
    }
    return ob_get_clean();
}

// Test 1: Success response structure
echo "Test 1: Success response structure... ";
$output = test_api_call(function() {
    register_shutdown_function('exit_handler');
    ApiResponse::sendSuccess(['test' => 'data', 'value' => 123]);
});
$json = json_decode($output, true);
if ($json && 
    isset($json['success']) && $json['success'] === true && 
    isset($json['data']) && 
    is_array($json['data']) &&
    $json['data']['test'] === 'data' &&
    $json['data']['value'] === 123) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL\n";
    $tests_failed++;
}

// Test 2: Success with null data
echo "Test 2: Success with null data... ";
$output = test_api_call(function() {
    ApiResponse::sendSuccess(null);
});
$json = json_decode($output, true);
if ($json && 
    isset($json['success']) && $json['success'] === true && 
    array_key_exists('data', $json) && 
    $json['data'] === null) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL\n";
    $tests_failed++;
}

// Test 3: Error response structure
echo "Test 3: Error response structure... ";
$output = test_api_call(function() {
    ApiResponse::sendError('Test error', 1001, 400);
});
$json = json_decode($output, true);
if ($json && 
    isset($json['success']) && $json['success'] === false && 
    isset($json['error']) && 
    is_array($json['error']) &&
    isset($json['error']['code']) && $json['error']['code'] === 1001 &&
    isset($json['error']['message']) && $json['error']['message'] === 'Test error') {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL\n";
    $tests_failed++;
}

// Test 4: Error with details
echo "Test 4: Error with details... ";
$output = test_api_call(function() {
    ApiResponse::sendError('Validation failed', 1002, 400, ['field1' => 'required']);
});
$json = json_decode($output, true);
if ($json && 
    isset($json['success']) && $json['success'] === false && 
    isset($json['error']['details']) && 
    is_array($json['error']['details']) &&
    isset($json['error']['details']['field1']) &&
    $json['error']['details']['field1'] === 'required') {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL\n";
    $tests_failed++;
}

// Test 5: UTF-8 character handling
echo "Test 5: UTF-8 character handling... ";
$output = test_api_call(function() {
    ApiResponse::sendSuccess(['nome' => 'Caffè Latte', 'prezzo' => '€3.50']);
});
$json = json_decode($output, true);
if ($json && 
    isset($json['data']['nome']) && 
    $json['data']['nome'] === 'Caffè Latte' &&
    isset($json['data']['prezzo']) &&
    strpos($json['data']['prezzo'], '€') !== false) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL\n";
    $tests_failed++;
}

// Test 6: Valid JSON output
echo "Test 6: Valid JSON output... ";
$output = test_api_call(function() {
    ApiResponse::sendSuccess(['complex' => ['nested' => 'data', 'array' => [1, 2, 3]]]);
});
$valid_json = json_decode($output, true) !== null && json_last_error() === JSON_ERROR_NONE;
if ($valid_json) {
    echo "PASS\n";
    $tests_passed++;
} else {
    echo "FAIL\n";
    $tests_failed++;
}

echo "\n==========================\n";
echo "Total tests: " . ($tests_passed + $tests_failed) . "\n";
echo "Passed: $tests_passed\n";
echo "Failed: $tests_failed\n";

if ($tests_failed === 0) {
    echo "\nAll tests passed!\n";
    exit(0);
} else {
    echo "\nSome tests failed!\n";
    exit(1);
}
?>
