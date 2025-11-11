# Monetary Calculations - Integer Arithmetic Implementation

## Overview

This document describes the implementation of precise monetary calculations using integer arithmetic (cents) instead of floating-point numbers.

## Problem Statement

Using IEEE 754 floating-point numbers for monetary calculations can introduce rounding errors due to the binary representation of decimal numbers. For example:

```php
// Float precision issues
$total = 0.1 + 0.2;  // Result: 0.30000000000000004 (not exactly 0.3)
$price = 12.50;
$quantity = 3;
$subtotal = $price * $quantity;  // May have small rounding errors
```

These errors, while small, can accumulate over many transactions and cause discrepancies in accounting and reports.

## Solution

All monetary calculations now use **integer arithmetic** with amounts stored in **cents** (centesimi).

### Key Principles

1. **Store in cents**: €12.50 is stored as 1250 cents (integer)
2. **Calculate with integers**: All arithmetic operations use integer math
3. **Convert at boundaries**: Convert to/from decimal only when:
   - Reading from database (DECIMAL → cents)
   - Writing to database (cents → DECIMAL)
   - Displaying to user (cents → formatted string)

### MoneyHelper Utility Class

The `MoneyHelper` class provides helper methods for monetary operations:

```php
require_once __DIR__ . '/../src/Utils/MoneyHelper.php';

// Convert EUR to cents
$cents = MoneyHelper::toCents(12.50);  // Returns: 1250

// Convert cents to EUR
$eur = MoneyHelper::toDecimal(1250);   // Returns: "12.50"

// Arithmetic operations
$sum = MoneyHelper::add(1250, 500);           // Returns: 1750
$diff = MoneyHelper::subtract(2000, 250);     // Returns: 1750
$product = MoneyHelper::multiply(1250, 3);    // Returns: 3750

// Formatting
$formatted = MoneyHelper::format(1250);       // Returns: "12.50 EUR"
```

## Implementation Details

### In `api/salva_ordine.php`

#### Before (Float arithmetic)
```php
$costo_coperto = (float)$costoCopertoRow['Valore'];
$totale_coperti = $num_coperti * $costo_coperto;

$prezzo_riga = (float)$item['prezzo_unitario'] * (int)$qta;
$subtotale += $prezzo_riga;
```

#### After (Integer arithmetic)
```php
$costo_coperto_cents = MoneyHelper::toCents($costoCopertoRow['Valore']);
$totale_coperti_cents = MoneyHelper::multiply($costo_coperto_cents, $num_coperti);

$prezzo_unitario_cents = MoneyHelper::toCents($item['prezzo_unitario']);
$prezzo_riga_cents = MoneyHelper::multiply($prezzo_unitario_cents, $qta);
$subtotale_cents = MoneyHelper::add($subtotale_cents, $prezzo_riga_cents);
```

### Server-Side Total Calculation

The total is now **always calculated on the server** to ensure precision and prevent client-side manipulation:

```php
// Calculate subtotal
$subtotale_calcolato_cents = 0;
foreach ($dettagli as $item) {
    $prezzo_unitario_cents = MoneyHelper::toCents($item['prezzo_unitario']);
    $quantita = (int)$item['quantita'];
    $prezzo_riga_cents = MoneyHelper::multiply($prezzo_unitario_cents, $quantita);
    $subtotale_calcolato_cents = MoneyHelper::add($subtotale_calcolato_cents, $prezzo_riga_cents);
}

// Add coperti and subtract discount
$totale_calcolato_cents = MoneyHelper::add($subtotale_calcolato_cents, $totale_coperti_cents);
$totale_calcolato_cents = MoneyHelper::subtract($totale_calcolato_cents, $sconto_cents);

// Convert to DECIMAL for database
$totale_ordine_db = MoneyHelper::toDecimal($totale_calcolato_cents);
```

## Database Schema

**No changes required** to the database schema. Monetary columns remain as `DECIMAL(10,2)`:

```sql
CREATE TABLE ORDINI (
  ...
  Totale_Ordine DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  Sconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ...
);

CREATE TABLE PRODOTTI (
  ...
  Prezzo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ...
);
```

## Benefits

1. **Precision**: No rounding errors in calculations
2. **Consistency**: Same results every time
3. **Security**: Server-side calculation prevents client manipulation
4. **Auditability**: Correct accounting and reports
5. **Performance**: Integer operations are faster than float operations

## Testing

Comprehensive test suites validate the implementation:

- `tools/tests/test_money_helper.php` - 32 unit tests for MoneyHelper
- `tools/tests/test_order_calculation.php` - 17 integration tests for order logic

Run tests:
```bash
php tools/tests/test_money_helper.php
php tools/tests/test_order_calculation.php
```

## Migration Guide

### For New Code

Always use `MoneyHelper` for monetary operations:

```php
require_once __DIR__ . '/../src/Utils/MoneyHelper.php';

// Read from database
$prezzo_cents = MoneyHelper::toCents($row['Prezzo']);

// Perform calculations
$totale_cents = MoneyHelper::multiply($prezzo_cents, $quantita);

// Write to database
$totale_db = MoneyHelper::toDecimal($totale_cents);
```

### For Existing Code

1. Identify monetary calculations using float arithmetic
2. Convert values to cents using `MoneyHelper::toCents()`
3. Use `MoneyHelper` methods for calculations
4. Convert back to decimal using `MoneyHelper::toDecimal()` for database/display

## References

- Issue #2 in PROBLEMS.md: "Uso di float per importi monetari"
- [Why not use Float for money?](https://stackoverflow.com/questions/3730019/why-not-use-double-or-float-to-represent-currency)
- PHP BCMath extension for arbitrary precision mathematics

## Related Issues

This implementation also addresses:
- Issue #1: Server-side total recalculation prevents client manipulation
- Improved data integrity for reports and accounting
