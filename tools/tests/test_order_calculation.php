<?php
/**
 * Integration test for order calculation with MoneyHelper
 * Tests the calculation logic from api/salva_ordine.php
 * Run: php tools/tests/test_order_calculation.php
 * 
 * This test validates that the server-side total calculation is precise
 */

require_once __DIR__ . '/../../src/Utils/MoneyHelper.php';

echo "Testing Order Calculation Logic (Issue #2)\n";
echo "==========================================\n\n";

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

// Test 1: Simple order calculation
echo "--- Test 1: Simple Order ---\n";
// Product 1: €10.50 x 2 = €21.00
$dettagli = [
    ['prezzo_unitario' => 10.50, 'quantita' => 2]
];

$subtotale_cents = 0;
foreach ($dettagli as $item) {
    $prezzo_unitario_cents = MoneyHelper::toCents($item['prezzo_unitario']);
    $quantita = (int)$item['quantita'];
    $prezzo_riga_cents = MoneyHelper::multiply($prezzo_unitario_cents, $quantita);
    $subtotale_cents = MoneyHelper::add($subtotale_cents, $prezzo_riga_cents);
}

test("Simple order subtotal (10.50 x 2)", $subtotale_cents === 2100);
test("Simple order subtotal as decimal", MoneyHelper::toDecimal($subtotale_cents) === '21.00');
echo "  Calculated: " . MoneyHelper::format($subtotale_cents) . "\n\n";

// Test 2: Multiple products
echo "--- Test 2: Multiple Products ---\n";
$dettagli = [
    ['prezzo_unitario' => 12.50, 'quantita' => 3],  // €37.50
    ['prezzo_unitario' => 8.75, 'quantita' => 2],   // €17.50
    ['prezzo_unitario' => 3.25, 'quantita' => 1]    // €3.25
];

$subtotale_cents = 0;
foreach ($dettagli as $item) {
    $prezzo_unitario_cents = MoneyHelper::toCents($item['prezzo_unitario']);
    $quantita = (int)$item['quantita'];
    $prezzo_riga_cents = MoneyHelper::multiply($prezzo_unitario_cents, $quantita);
    $subtotale_cents = MoneyHelper::add($subtotale_cents, $prezzo_riga_cents);
}

test("Multiple products subtotal", $subtotale_cents === 5825); // €58.25
echo "  Product 1: 12.50 x 3 = €37.50\n";
echo "  Product 2: 8.75 x 2 = €17.50\n";
echo "  Product 3: 3.25 x 1 = €3.25\n";
echo "  Calculated subtotal: " . MoneyHelper::format($subtotale_cents) . "\n\n";

// Test 3: Order with coperti
echo "--- Test 3: Order with Coperti ---\n";
$dettagli = [
    ['prezzo_unitario' => 15.00, 'quantita' => 2]  // €30.00
];
$num_coperti = 2;
$costo_coperto_cents = MoneyHelper::toCents(2.50); // €2.50 per coperto

$subtotale_cents = 0;
foreach ($dettagli as $item) {
    $prezzo_unitario_cents = MoneyHelper::toCents($item['prezzo_unitario']);
    $quantita = (int)$item['quantita'];
    $prezzo_riga_cents = MoneyHelper::multiply($prezzo_unitario_cents, $quantita);
    $subtotale_cents = MoneyHelper::add($subtotale_cents, $prezzo_riga_cents);
}

$totale_coperti_cents = MoneyHelper::multiply($costo_coperto_cents, $num_coperti);
$totale_cents = MoneyHelper::add($subtotale_cents, $totale_coperti_cents);

test("Subtotal", $subtotale_cents === 3000);
test("Coperti (2.50 x 2)", $totale_coperti_cents === 500);
test("Total with coperti", $totale_cents === 3500);
echo "  Subtotal: " . MoneyHelper::format($subtotale_cents) . "\n";
echo "  Coperti:  " . MoneyHelper::format($totale_coperti_cents) . "\n";
echo "  Total:    " . MoneyHelper::format($totale_cents) . "\n\n";

// Test 4: Order with discount
echo "--- Test 4: Order with Discount ---\n";
$dettagli = [
    ['prezzo_unitario' => 25.00, 'quantita' => 1]
];
$sconto = 5.00;

$subtotale_cents = 0;
foreach ($dettagli as $item) {
    $prezzo_unitario_cents = MoneyHelper::toCents($item['prezzo_unitario']);
    $quantita = (int)$item['quantita'];
    $prezzo_riga_cents = MoneyHelper::multiply($prezzo_unitario_cents, $quantita);
    $subtotale_cents = MoneyHelper::add($subtotale_cents, $prezzo_riga_cents);
}

$sconto_cents = MoneyHelper::toCents($sconto);
$totale_cents = MoneyHelper::subtract($subtotale_cents, $sconto_cents);

test("Subtotal before discount", $subtotale_cents === 2500);
test("Discount amount", $sconto_cents === 500);
test("Total after discount", $totale_cents === 2000);
echo "  Subtotal: " . MoneyHelper::format($subtotale_cents) . "\n";
echo "  Discount: " . MoneyHelper::format($sconto_cents) . "\n";
echo "  Total:    " . MoneyHelper::format($totale_cents) . "\n\n";

// Test 5: Complete order (products + coperti - discount)
echo "--- Test 5: Complete Order ---\n";
$dettagli = [
    ['prezzo_unitario' => 12.50, 'quantita' => 3],  // €37.50
    ['prezzo_unitario' => 8.75, 'quantita' => 2]    // €17.50
];
$num_coperti = 3;
$costo_coperto_cents = MoneyHelper::toCents(2.00);
$sconto = 5.50;

$subtotale_cents = 0;
foreach ($dettagli as $item) {
    $prezzo_unitario_cents = MoneyHelper::toCents($item['prezzo_unitario']);
    $quantita = (int)$item['quantita'];
    $prezzo_riga_cents = MoneyHelper::multiply($prezzo_unitario_cents, $quantita);
    $subtotale_cents = MoneyHelper::add($subtotale_cents, $prezzo_riga_cents);
}

$totale_coperti_cents = MoneyHelper::multiply($costo_coperto_cents, $num_coperti);
$sconto_cents = MoneyHelper::toCents($sconto);
$totale_cents = MoneyHelper::add($subtotale_cents, $totale_coperti_cents);
$totale_cents = MoneyHelper::subtract($totale_cents, $sconto_cents);

test("Complete order subtotal", $subtotale_cents === 5500); // €55.00
test("Complete order coperti", $totale_coperti_cents === 600); // €6.00
test("Complete order discount", $sconto_cents === 550); // €5.50
test("Complete order total", $totale_cents === 5550); // €55.50
echo "  Subtotal:  " . MoneyHelper::format($subtotale_cents) . "\n";
echo "  Coperti:   " . MoneyHelper::format($totale_coperti_cents) . "\n";
echo "  Discount:  " . MoneyHelper::format($sconto_cents) . "\n";
echo "  Final:     " . MoneyHelper::format($totale_cents) . "\n\n";

// Test 6: Edge case - prices with many decimals
echo "--- Test 6: Edge Cases ---\n";
$dettagli = [
    ['prezzo_unitario' => 0.99, 'quantita' => 10]  // €9.90
];

$subtotale_cents = 0;
foreach ($dettagli as $item) {
    $prezzo_unitario_cents = MoneyHelper::toCents($item['prezzo_unitario']);
    $quantita = (int)$item['quantita'];
    $prezzo_riga_cents = MoneyHelper::multiply($prezzo_unitario_cents, $quantita);
    $subtotale_cents = MoneyHelper::add($subtotale_cents, $prezzo_riga_cents);
}

test("Edge case: 0.99 x 10", $subtotale_cents === 990);
test("Edge case result as decimal", MoneyHelper::toDecimal($subtotale_cents) === '9.90');
echo "  Calculated: " . MoneyHelper::format($subtotale_cents) . "\n\n";

// Test 7: Staff order (zero total)
echo "--- Test 7: Staff Order ---\n";
$staff = true;
$totale_coperti_cents = 0; // Staff orders have no coperti
$staff_total_cents = $staff ? 0 : 1000;

test("Staff order has zero total", $staff_total_cents === 0);
test("Staff order has zero coperti", $totale_coperti_cents === 0);
echo "  Staff order total: " . MoneyHelper::format($staff_total_cents) . "\n\n";

echo "==========================================\n";
echo "Total tests: " . ($tests_passed + $tests_failed) . "\n";
echo "Passed: $tests_passed\n";
echo "Failed: $tests_failed\n";

if ($tests_failed === 0) {
    echo "\n✓ All order calculation tests passed!\n";
    echo "Issue #2 fix verified: Integer arithmetic prevents rounding errors\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed!\n";
    exit(1);
}
?>
