# Staff Flag Feature Documentation

## Overview

The Staff Flag feature allows restaurants to create orders for staff members that do not count towards daily revenue reports. When an order is marked as "staff", the total cost is automatically set to 0 and the order is excluded from all financial reports.

## Implementation Details

### Database Schema Changes

A new column `Staff` has been added to the `ORDINI` table:

```sql
ALTER TABLE ORDINI 
ADD COLUMN Staff TINYINT(1) NOT NULL DEFAULT 0 AFTER Data_Ora;

ALTER TABLE ORDINI
ADD INDEX idx_ordini_staff (Staff);
```

**Migration Script**: `sql/migrations/2025-10-23_add_staff_flag.sql`

### API Changes

#### POST /api/salva_ordine.php

**New Request Parameter:**
- `staff` (optional, default: `false`)
  - Type: Boolean
  - If `true`, the order is marked as a staff order
  - The total is automatically set to 0.00
  - The order is excluded from reports

**Example Request:**
```json
{
  "nome_cliente": "Staff Member",
  "id_tavolo": 5,
  "numero_coperti": 1,
  "totale": 25.50,
  "staff": true,
  "dettagli": [
    {
      "id_prodotto": 1,
      "quantita": 1,
      "prezzo_unitario": 12.00,
      "descrizione": "Pizza Margherita"
    }
  ]
}
```

**Backend Behavior:**
1. Validates that `staff` is a boolean value (accepts: `true`, `false`, `1`, `0`, `"1"`, `"0"`)
2. If `staff` is `true`, automatically sets `totale_ordine` to `0.00`
3. Stores the staff flag in the database
4. Error code 1030 is returned if staff validation fails

#### GET /api/genera_report.php

**Updated Behavior:**
- Staff orders (where `Staff = 1`) are now excluded from:
  - Total revenue calculations (`Incasso_Parziale`)
  - Cover count calculations (`Coperti_Parziali`)
  - Product sales statistics (`Totale_Venduto`)

**SQL Changes:**
```sql
-- Revenue summary query now includes:
WHERE o.Data_Ora >= :start AND o.Data_Ora < :end
  AND o.Staff = 0  -- Exclude staff orders

-- Product details query now includes:
WHERE o.Data_Ora >= :start AND o.Data_Ora < :end
  AND o.Staff = 0  -- Exclude staff orders
```

### UI Changes

#### cassa.php (Order Creation Page)

A new checkbox has been added to the order form:

```html
<div style="margin-top: 15px;">
    <label for="input-staff">
        <input type="checkbox" id="input-staff" name="staff">
        Ordine Staff (totale a 0, escluso dai report)
    </label>
</div>
```

#### cassa.js (Frontend Logic)

**New Functionality:**
1. Reads the staff checkbox state when saving an order
2. Includes the `staff` flag in the order data sent to the API
3. Updates the total display dynamically:
   - When staff checkbox is checked: displays "0.00" in green/bold
   - When staff checkbox is unchecked: displays the actual total
4. Resets the staff checkbox when starting a new order

**Event Handling:**
- The checkbox change event triggers a UI update
- The total display is styled differently for staff orders (green color, bold font)

## Usage

### Creating a Staff Order

1. Open the Cassa (order creation) page
2. Fill in customer name and table selection
3. Add products to the order as usual
4. **Check the "Ordine Staff" checkbox** before saving
5. The total will automatically display as 0.00
6. Click "Salva e Stampa Comande"

### Viewing Reports

When generating daily reports:
- Staff orders will **NOT** appear in revenue totals
- Staff orders will **NOT** appear in product sales statistics
- Only regular customer orders are included in financial calculations

## Validation Rules

The `staff` parameter is validated as follows:

**Valid Values:**
- `true` (boolean)
- `false` (boolean)
- `1` (integer)
- `0` (integer)
- `"1"` (string)
- `"0"` (string)

**Invalid Values:**
- `"yes"`, `"no"`, `"true"`, `"false"` (strings)
- `null`
- Any other non-boolean value

**Error Response (Code 1030):**
```json
{
  "success": false,
  "error": {
    "code": 1030,
    "message": "Staff deve essere un valore booleano"
  }
}
```

## Business Logic

### Total Calculation
When `staff = true`:
- The backend **ignores** the `totale` value sent by the frontend
- The backend **forces** `Totale_Ordine` to `0.00` in the database
- This prevents any manipulation of staff order totals

### Report Exclusion
Staff orders are excluded from reports using SQL filtering:
```sql
WHERE o.Staff = 0
```

This ensures:
- Staff orders never appear in daily revenue totals
- Staff orders never count towards product sales statistics
- Historical data integrity is maintained (staff orders remain in the database for audit purposes)

## Testing

### Manual Testing

1. **Create a regular order:**
   - Uncheck "Ordine Staff"
   - Add products
   - Verify total is calculated correctly
   - Save order
   - Generate report and verify order appears

2. **Create a staff order:**
   - Check "Ordine Staff"
   - Add products
   - Verify total displays as 0.00
   - Save order
   - Generate report and verify order does NOT appear

3. **Validation testing:**
   - Try to send invalid staff values via API
   - Verify error code 1030 is returned

### Automated Testing

Run the validation logic test:
```bash
php /tmp/test_staff_flag.php
```

Expected output: All tests should PASS

## Migration Guide

For existing installations, run the migration script:

```bash
mysql -u username -p database_name < sql/migrations/2025-10-23_add_staff_flag.sql
```

This will:
1. Add the `Staff` column to existing `ORDINI` table
2. Set default value to `0` for all existing orders (non-staff)
3. Create an index for efficient filtering

## Files Modified

### Backend
- `api/salva_ordine.php` - Added staff parameter handling and validation
- `api/genera_report.php` - Excluded staff orders from reports
- `sql/schema.sql` - Updated schema with Staff column
- `sql/migrations/2025-10-23_add_staff_flag.sql` - Migration script

### Frontend
- `cassa.php` - Added staff checkbox to UI
- `cassa.js` - Added staff flag handling and UI updates

### Documentation
- `docs/API.md` - Updated API documentation
- `docs/STAFF_FLAG_FEATURE.md` - This feature documentation

## Error Codes

| Code | Description |
|------|-------------|
| 1030 | Staff deve essere un valore booleano |

## Future Enhancements

Potential improvements for future versions:

1. **Staff Order Report**: Create a separate report for staff orders only
2. **User Permissions**: Restrict staff order creation to specific user roles
3. **Audit Trail**: Log who created each staff order
4. **Bulk Operations**: Mark multiple existing orders as staff orders
5. **Analytics**: Track staff consumption patterns separately

## Support

For issues or questions related to the Staff Flag feature, please refer to:
- API Documentation: `docs/API.md`
- Database Schema: `sql/schema.sql`
- Migration Scripts: `sql/migrations/`
