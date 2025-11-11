# Campo Buono Sconto - Implementation Summary

## Overview / Panoramica

This PR implements a discount field in the order creation page as requested in the issue. The discount is subtracted from the order total, and the final price (after discount) is stored in the database.

Questa PR implementa un campo sconto nella pagina di creazione ordini come richiesto nell'issue. Lo sconto viene sottratto dal totale dell'ordine e il prezzo finale (dopo lo sconto) viene salvato nel database.

## Screenshot

![Discount Field UI](https://github.com/user-attachments/assets/b6f175b0-f435-44b6-94c2-107803f07f21)

The UI now shows:
- **Subtotale**: Sum of all products
- **Sconto**: Discount input field (can be edited)
- **TOTALE**: Final total (Subtotale - Sconto)

## Changes Made / Modifiche Apportate

### 1. Database Schema
- ✅ Added `Sconto` column to `ORDINI` table (DECIMAL(10,2))
- ✅ Created migration script: `sql/migrations/2025-10-23_add_discount_field.sql`
- ✅ Updated main schema: `sql/schema.sql`

### 2. User Interface (cassa.php)
- ✅ Replaced simple total display with detailed breakdown
- ✅ Added discount input field with validation (min="0", step="0.01")
- ✅ Shows: Subtotale → Sconto → TOTALE

### 3. Frontend Logic (cassa.js)
- ✅ Real-time calculation: `Total = Subtotal - Discount`
- ✅ Disabled discount field for staff orders
- ✅ Validation: Discount cannot exceed subtotal (total forced to 0 if negative)
- ✅ Reset discount field when creating new order

### 4. Backend API (api/salva_ordine.php)
- ✅ Added discount parameter validation
- ✅ Validates: must be numeric, 0 ≤ discount ≤ 999999.99
- ✅ For staff orders: discount forced to 0
- ✅ Stores both `Totale_Ordine` (final price) and `Sconto` (discount amount)

### 5. Receipt Printing
- ✅ Updated receipt to show discount breakdown when applicable:
  ```
  SUBTOTALE:     27.00 EUR
  SCONTO:         2.00 EUR
  ---------------------------
  TOTALE:        25.00 EUR
  ```
- ✅ When discount is 0, only shows final TOTALE

### 6. Styling (style.css)
- ✅ Added modern, responsive layout for total container
- ✅ Flex layout for proper alignment
- ✅ Clear visual distinction between subtotal, discount, and final total

### 7. Testing
- ✅ Created test script: `scripts/test_discount_validation.sh`
- ✅ Tests validation rules (negative, non-numeric, too large, etc.)
- ✅ All syntax checks passed (PHP and JavaScript)

### 8. Security & Quality
- ✅ Code review completed and feedback addressed
- ✅ CodeQL security scan: 0 vulnerabilities found
- ✅ Input validation using existing `InputValidator` class
- ✅ SQL injection protection via prepared statements

## Usage / Utilizzo

### Normal Order with Discount / Ordine Normale con Sconto
1. Add products to order / Aggiungi prodotti all'ordine
2. Subtotal is calculated automatically / Il subtotale viene calcolato automaticamente
3. Enter discount amount (e.g., 2.00€) / Inserisci l'importo dello sconto (es. 2.00€)
4. Total updates automatically: 27.00€ - 2.00€ = 25.00€
5. Save order → Discount is stored in database / Salva ordine → Lo sconto viene salvato nel database

### Staff Order / Ordine Staff
- Discount field is **disabled** and forced to 0
- Total remains 0 (existing behavior preserved)

## Database Migration / Migrazione Database

Run this SQL before using the feature:
```sql
ALTER TABLE ORDINI 
ADD COLUMN Sconto DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER Totale_Ordine;

ALTER TABLE ORDINI 
ADD INDEX idx_ordini_sconto (Sconto);
```

Or execute: `sql/migrations/2025-10-23_add_discount_field.sql`

## Files Changed / File Modificati

- `sql/schema.sql` - Updated schema
- `sql/migrations/2025-10-23_add_discount_field.sql` - New migration
- `cassa.php` - Updated UI
- `cassa.js` - Updated frontend logic
- `style.css` - Updated styles
- `api/salva_ordine.php` - Updated backend API
- `scripts/test_discount_validation.sh` - New test script
- `DISCOUNT_FIELD_IMPLEMENTATION.md` - Detailed documentation

## Testing / Test

### Manual Testing / Test Manuali
1. Navigate to order creation page (cassa.php)
2. Add products to order
3. Enter discount amount
4. Verify total updates correctly
5. Save order and verify database record
6. Check printed receipt shows discount

### Automated Testing / Test Automatici
```bash
./scripts/test_discount_validation.sh http://your-server/RICEVUTE
```

## Compatibility / Compatibilità

- ✅ **Backward compatible**: Existing orders will have `Sconto = 0.00` (default)
- ✅ **Reports**: Existing reports continue to work (they use `Totale_Ordine` which already includes final price)
- ✅ **Staff orders**: Existing behavior preserved (total = 0, discount = 0)

## Documentation / Documentazione

See `DISCOUNT_FIELD_IMPLEMENTATION.md` for detailed technical documentation.

Vedi `DISCOUNT_FIELD_IMPLEMENTATION.md` per la documentazione tecnica dettagliata.

---

**Issue**: #[issue number] - Campo buono sconto  
**Status**: ✅ Ready for Review / Pronto per la Revisione
