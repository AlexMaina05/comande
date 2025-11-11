<?php
/**
 * Unit tests for MoneyHelper
 * Run: php tools/tests/test_money_helper.php
 * 
 * Tests precision in monetary calculations using integer arithmetic
 */

require_once __DIR__ . '/../../src/Utils/MoneyHelper.php';

echo "Testing MoneyHelper - Integer Monetary Calculations\n";
echo "===================================================\n\n";

$tests_passed = 0;
$tests_failed = 0;

function test($description, $condition) {
    global $tests_passed, $tests_failed;
    echo "Test: $description... ";
    if ($condition) {
        echo "PASS\n";
        $tests_passed++;
        return true;
    } else {
        echo "FAIL\n";
        $tests_failed++;
        return false;
    }
}

// Test 1: Convert EUR to cents
test("Convert 12.50 EUR to cents", MoneyHelper::toCents(12.50) === 1250);
test("Convert 0.01 EUR to cents", MoneyHelper::toCents(0.01) === 1);
test("Convert 0.00 EUR to cents", MoneyHelper::toCents(0.00) === 0);
test("Convert 999.99 EUR to cents", MoneyHelper::toCents(999.99) === 99999);

// Test 2: Convert string EUR to cents
test("Convert string '12.50' to cents", MoneyHelper::toCents('12.50') === 1250);
test("Convert string '0.05' to cents", MoneyHelper::toCents('0.05') === 5);

// Test 3: Convert cents to decimal
test("Convert 1250 cents to decimal", MoneyHelper::toDecimal(1250) === '12.50');
test("Convert 1 cent to decimal", MoneyHelper::toDecimal(1) === '0.01');
test("Convert 0 cents to decimal", MoneyHelper::toDecimal(0) === '0.00');
test("Convert 99999 cents to decimal", MoneyHelper::toDecimal(99999) === '999.99');
test("Convert 1005 cents to decimal", MoneyHelper::toDecimal(1005) === '10.05');

// Test 4: Addition
test("Add 12.50 + 5.00 = 17.50", 
    MoneyHelper::add(
        MoneyHelper::toCents(12.50),
        MoneyHelper::toCents(5.00)
    ) === MoneyHelper::toCents(17.50)
);

test("Add multiple amounts", 
    MoneyHelper::add(1250, 500, 320) === 2070
);

// Test 5: Subtraction
test("Subtract 20.00 - 5.00 = 15.00",
    MoneyHelper::subtract(
        MoneyHelper::toCents(20.00),
        MoneyHelper::toCents(5.00)
    ) === MoneyHelper::toCents(15.00)
);

test("Subtract multiple amounts",
    MoneyHelper::subtract(2000, 500, 250) === 1250
);

// Test 6: Multiplication
test("Multiply 12.50 * 3 = 37.50",
    MoneyHelper::multiply(MoneyHelper::toCents(12.50), 3) === MoneyHelper::toCents(37.50)
);

test("Multiply 0.01 * 100 = 1.00",
    MoneyHelper::multiply(MoneyHelper::toCents(0.01), 100) === MoneyHelper::toCents(1.00)
);

// Test 7: Format
test("Format 1250 cents as EUR",
    MoneyHelper::format(1250) === '12.50 EUR'
);

test("Format with custom currency symbol",
    MoneyHelper::format(1250, '€') === '12.50 €'
);

// Test 8: Validation
test("Validate positive amount", MoneyHelper::isValidAmount(1250));
test("Validate zero amount", MoneyHelper::isValidAmount(0));
test("Reject negative amount", !MoneyHelper::isValidAmount(-100));
test("Reject amount above max", !MoneyHelper::isValidAmount(100000000));
test("Accept amount at max", MoneyHelper::isValidAmount(99999999));

// Test 9: Comparison
test("Compare 12.50 > 10.00", MoneyHelper::compare(1250, 1000) === 1);
test("Compare 10.00 < 12.50", MoneyHelper::compare(1000, 1250) === -1);
test("Compare 12.50 == 12.50", MoneyHelper::compare(1250, 1250) === 0);

// Test 10: Precision tests - problems with float
echo "\n--- Precision Tests (demonstrating float issues) ---\n";

// Classic float precision problem: 0.1 + 0.2 != 0.3
$float_result = (0.1 + 0.2) * 100; // Should be 30, but might not be
$int_result = MoneyHelper::add(MoneyHelper::toCents(0.1), MoneyHelper::toCents(0.2));
test("Integer precision: 0.1 + 0.2 = 0.3", $int_result === 30);
echo "  Float calculation:   " . $float_result . " (may have rounding error)\n";
echo "  Integer calculation: " . $int_result . " (exact)\n\n";

// Another example: repeated addition
$float_sum = 0.0;
$int_sum = 0;
for ($i = 0; $i < 100; $i++) {
    $float_sum += 0.01;
    $int_sum = MoneyHelper::add($int_sum, 1);
}
test("Integer precision: 0.01 * 100 = 1.00", $int_sum === 100);
echo "  Float sum:   " . ($float_sum * 100) . " cents (may have error)\n";
echo "  Integer sum: " . $int_sum . " cents (exact)\n\n";

// Test 11: Real-world scenario - Order calculation
echo "--- Real-world Order Calculation ---\n";
// Product 1: €12.50 x 3 = €37.50
$product1 = MoneyHelper::multiply(MoneyHelper::toCents(12.50), 3);
// Product 2: €8.75 x 2 = €17.50
$product2 = MoneyHelper::multiply(MoneyHelper::toCents(8.75), 2);
// Coperti: €2.00 x 3 = €6.00
$coperti = MoneyHelper::multiply(MoneyHelper::toCents(2.00), 3);
// Subtotale: €37.50 + €17.50 = €55.00
$subtotale = MoneyHelper::add($product1, $product2);
// Totale con coperti: €55.00 + €6.00 = €61.00
$totale_con_coperti = MoneyHelper::add($subtotale, $coperti);
// Sconto: €5.00
$sconto = MoneyHelper::toCents(5.00);
// Totale finale: €61.00 - €5.00 = €56.00
$totale_finale = MoneyHelper::subtract($totale_con_coperti, $sconto);

test("Order subtotal calculation", $subtotale === 5500);
test("Order total with coperti", $totale_con_coperti === 6100);
test("Order final total with discount", $totale_finale === 5600);
echo "  Subtotal: " . MoneyHelper::format($subtotale) . "\n";
echo "  Coperti:  " . MoneyHelper::format($coperti) . "\n";
echo "  Total:    " . MoneyHelper::format($totale_con_coperti) . "\n";
echo "  Discount: " . MoneyHelper::format($sconto) . "\n";
echo "  Final:    " . MoneyHelper::format($totale_finale) . "\n";

echo "\n===================================================\n";
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
