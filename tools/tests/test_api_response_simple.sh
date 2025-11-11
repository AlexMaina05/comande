#!/bin/bash
# Simple test to verify ApiResponse helper produces correct JSON format
# Run: bash tools/tests/test_api_response_simple.sh

echo "Testing ApiResponse JSON Output Format"
echo "======================================="
echo ""

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

TESTS_PASSED=0
TESTS_FAILED=0

# Test 1: Success response
echo -n "Test 1: Success response format... "
OUTPUT=$(php -r "require '$PROJECT_ROOT/api/response.php'; ApiResponse::sendSuccess(['test' => 'value']);" 2>/dev/null)
if echo "$OUTPUT" | jq -e '.success == true and .data.test == "value"' > /dev/null 2>&1; then
    echo "PASS"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo "FAIL"
    echo "Output: $OUTPUT"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# Test 2: Error response
echo -n "Test 2: Error response format... "
OUTPUT=$(php -r "require '$PROJECT_ROOT/api/response.php'; ApiResponse::sendError('Test error', 1001);" 2>/dev/null)
if echo "$OUTPUT" | jq -e '.success == false and .error.code == 1001 and .error.message == "Test error"' > /dev/null 2>&1; then
    echo "PASS"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo "FAIL"
    echo "Output: $OUTPUT"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# Test 3: Error with details
echo -n "Test 3: Error with details... "
OUTPUT=$(php -r "require '$PROJECT_ROOT/api/response.php'; ApiResponse::sendError('Validation failed', 1002, 400, ['field' => 'required']);" 2>/dev/null)
if echo "$OUTPUT" | jq -e '.success == false and .error.details.field == "required"' > /dev/null 2>&1; then
    echo "PASS"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo "FAIL"
    echo "Output: $OUTPUT"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# Test 4: UTF-8 handling
echo -n "Test 4: UTF-8 character handling... "
OUTPUT=$(php -r "require '$PROJECT_ROOT/api/response.php'; ApiResponse::sendSuccess(['nome' => 'Caffè']);" 2>/dev/null)
if echo "$OUTPUT" | jq -e '.data.nome == "Caffè"' > /dev/null 2>&1; then
    echo "PASS"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo "FAIL"
    echo "Output: $OUTPUT"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

echo ""
echo "======================================="
echo "Total tests: $((TESTS_PASSED + TESTS_FAILED))"
echo "Passed: $TESTS_PASSED"
echo "Failed: $TESTS_FAILED"

if [ $TESTS_FAILED -eq 0 ]; then
    echo ""
    echo "All tests passed!"
    exit 0
else
    echo ""
    echo "Some tests failed!"
    exit 1
fi
