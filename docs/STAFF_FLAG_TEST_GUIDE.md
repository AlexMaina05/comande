# Staff Flag Feature - Testing Guide

This guide provides step-by-step instructions for manually testing the Staff Flag feature implementation.

## Prerequisites

1. Database has been updated with the migration script:
   ```bash
   mysql -u username -p database_name < sql/migrations/2025-10-23_add_staff_flag.sql
   ```

2. Web server is running with the updated code

3. Test products exist in the database

## Test Cases

### Test Case 1: Create a Regular (Non-Staff) Order

**Objective:** Verify that regular orders work as before

**Steps:**
1. Navigate to the Cassa page (cassa.php)
2. Fill in the order details:
   - Nome Cliente: "Test Customer"
   - Tavolo: Select any table
   - Numero Coperti: 2
3. Add at least 2 products to the order
4. **IMPORTANT:** Leave the "Ordine Staff" checkbox UNCHECKED
5. Verify that the total displays the actual sum of products
6. Click "Salva e Stampa Comande"

**Expected Results:**
- Order is saved successfully
- Receipt is printed (if printer is available)
- Success message shows order ID
- Total is NOT 0.00

**Verification:**
1. Go to the Report page (report.php)
2. Generate a report for today's date
3. Verify the order appears in the report with the correct total

---

### Test Case 2: Create a Staff Order

**Objective:** Verify that staff orders set total to 0 and are excluded from reports

**Steps:**
1. Navigate to the Cassa page (cassa.php)
2. Fill in the order details:
   - Nome Cliente: "Staff Member"
   - Tavolo: Select any table
   - Numero Coperti: 1
3. Add at least 2 products to the order
4. **CHECK** the "Ordine Staff" checkbox
5. Verify that the total displays as "0.00" (in green/bold)
6. Click "Salva e Stampa Comande"

**Expected Results:**
- Order is saved successfully
- Success message shows order ID
- Total is displayed as 0.00

**Verification:**
1. Go to the Report page (report.php)
2. Generate a report for today's date
3. **Verify the staff order does NOT appear in:**
   - Total revenue (Incasso Totale)
   - Service breakdown (Servizio in Sala / Servizio Asporto)
   - Product statistics (Dettaglio Prodotti Venduti)

---

### Test Case 3: UI Behavior - Total Display Update

**Objective:** Verify that the total updates dynamically when staff checkbox changes

**Steps:**
1. Navigate to the Cassa page (cassa.php)
2. Fill in the order details
3. Add products totaling, for example, 25.50€
4. Observe the displayed total: should be 25.50€
5. **CHECK** the "Ordine Staff" checkbox
6. Observe the displayed total changes to 0.00€ (green/bold)
7. **UNCHECK** the "Ordine Staff" checkbox
8. Observe the displayed total returns to 25.50€

**Expected Results:**
- Total updates immediately when checkbox is toggled
- Staff orders show 0.00 in green/bold
- Regular orders show actual total in default styling

---

### Test Case 4: Staff Checkbox Reset

**Objective:** Verify that the staff checkbox resets after saving an order

**Steps:**
1. Create a staff order (with checkbox checked)
2. Save the order successfully
3. The form should reset automatically
4. Verify the "Ordine Staff" checkbox is UNCHECKED

**Expected Results:**
- After saving, all form fields are cleared
- Staff checkbox returns to unchecked state
- Ready for next order

---

### Test Case 5: API Validation - Invalid Staff Value

**Objective:** Verify that invalid staff values are rejected

**Steps:**
1. Use curl or Postman to send a POST request to `/api/salva_ordine.php`
2. Include an invalid staff value:
   ```json
   {
     "nome_cliente": "Test",
     "id_tavolo": 1,
     "numero_coperti": 1,
     "totale": 10.00,
     "staff": "invalid_value",
     "dettagli": [
       {
         "id_prodotto": 1,
         "quantita": 1,
         "prezzo_unitario": 10.00,
         "descrizione": "Test Product"
       }
     ]
   }
   ```

**Expected Results:**
```json
{
  "success": false,
  "error": {
    "code": 1030,
    "message": "Staff deve essere un valore booleano"
  }
}
```

---

### Test Case 6: API - Staff Order Total Override

**Objective:** Verify that backend forces total to 0 for staff orders

**Steps:**
1. Send a POST request to `/api/salva_ordine.php` with:
   ```json
   {
     "nome_cliente": "Test Staff",
     "id_tavolo": 1,
     "numero_coperti": 1,
     "totale": 999.99,
     "staff": true,
     "dettagli": [
       {
         "id_prodotto": 1,
         "quantita": 1,
         "prezzo_unitario": 10.00,
         "descrizione": "Test Product"
       }
     ]
   }
   ```
   Note: `totale` is set to 999.99 but `staff` is true

**Expected Results:**
- Order is saved successfully
- Query the database: `SELECT Totale_Ordine, Staff FROM ORDINI WHERE ID_Ordine = <order_id>`
- Verify `Totale_Ordine` is 0.00 (NOT 999.99)
- Verify `Staff` is 1

---

### Test Case 7: Report Exclusion with Mixed Orders

**Objective:** Verify that reports correctly separate staff and regular orders

**Steps:**
1. Create 2 regular orders totaling 50.00€
2. Create 2 staff orders totaling 30.00€ (should be 0.00€)
3. Generate a daily report

**Expected Results:**
- Report shows total revenue of 50.00€ (not 80.00€)
- Staff orders do not appear in any statistics
- Product counts only include regular orders

---

### Test Case 8: Database Query Verification

**Objective:** Verify that database queries correctly filter staff orders

**Steps:**
1. Create at least one staff order and one regular order
2. Run direct SQL queries:

**Query 1 - All Orders:**
```sql
SELECT ID_Ordine, Nome_Cliente, Totale_Ordine, Staff, Data_Ora 
FROM ORDINI 
WHERE DATE(Data_Ora) = CURDATE()
ORDER BY ID_Ordine DESC;
```

**Query 2 - Regular Orders Only (what reports use):**
```sql
SELECT SUM(Totale_Ordine) as Total, COUNT(*) as Count
FROM ORDINI 
WHERE DATE(Data_Ora) = CURDATE() AND Staff = 0;
```

**Expected Results:**
- Query 1 shows both staff (Staff=1, Totale_Ordine=0.00) and regular orders
- Query 2 only sums regular orders (Staff=0)

---

## Test Data Examples

### Valid Staff Values
- `true` (boolean)
- `false` (boolean)
- `1` (integer)
- `0` (integer)
- `"1"` (string)
- `"0"` (string)

### Invalid Staff Values
- `"yes"`
- `"no"`
- `"true"` (string, not boolean)
- `"false"` (string, not boolean)
- `null`
- `undefined`
- `"staff"`
- `2`

---

## Regression Testing

After completing all test cases, verify that existing functionality still works:

1. **Regular Order Creation**: Create orders without using the staff flag
2. **Report Generation**: Generate reports for different dates
3. **Product Search**: Search for products by code
4. **Order Details**: Verify order details are saved correctly
5. **Print Functionality**: Verify receipts and comande still print

---

## Cleanup

After testing, you may want to:
1. Delete test orders from the database
2. Or mark them appropriately for easy identification

```sql
-- View test orders
SELECT * FROM ORDINI WHERE Nome_Cliente LIKE 'Test%' OR Nome_Cliente LIKE 'Staff%';

-- Delete test orders (optional)
DELETE FROM ORDINI WHERE Nome_Cliente LIKE 'Test%' OR Nome_Cliente LIKE 'Staff%';
```

---

## Reporting Issues

If any test fails, document:
1. Which test case failed
2. Expected behavior
3. Actual behavior
4. Error messages (if any)
5. Browser console errors (for UI tests)
6. PHP error logs (for API tests)

---

## Success Criteria

All tests pass when:
- ✅ Staff orders can be created with total = 0.00
- ✅ Regular orders work as before
- ✅ Staff checkbox updates UI correctly
- ✅ Staff orders are excluded from reports
- ✅ Database stores staff flag correctly
- ✅ API validation works correctly
- ✅ No regression in existing functionality
