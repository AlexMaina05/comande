# Manual Test Checklist for Data_Invio NULL Migration

This document provides a checklist for manually testing the Data_Invio standardization to NULL.

**Note:** Replace `{BASE_URL}` with your server URL (e.g., `http://localhost` or `http://your-server`).

## Pre-Migration Tests

Before applying the migration, verify the current state:

### 1. Check Current Data_Invio Values
```sql
-- Count records with '1970-01-01' default
SELECT COUNT(*) as old_default_count 
FROM COMANDE 
WHERE DATE(Data_Invio) = '1970-01-01';

-- Show distribution of Data_Invio values
SELECT 
  CASE 
    WHEN DATE(Data_Invio) = '1970-01-01' THEN '1970-01-01 (old default)'
    WHEN Data_Invio IS NULL THEN 'NULL'
    ELSE 'Valid timestamp'
  END as status,
  COUNT(*) as count
FROM COMANDE
GROUP BY status;
```

### 2. Check Current Table Schema
```sql
SHOW CREATE TABLE COMANDE;
-- Verify Data_Invio is currently: DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00'
```

## Migration Steps

### 1. Backup Database
```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Apply Migration
```bash
mysql -u username -p database_name < sql/migrations/2025-10-23_migrate_data_invio_to_null.sql
```

### 3. Verify Migration Success
The migration script includes verification queries. Check the output:
- `old_default_count` should be 0
- Distribution should show only 'NULL' and 'Valid timestamp'

## Post-Migration Tests

### 1. Test New Comanda Creation (API)
```bash
# Test creating a new order via API
curl -X POST {BASE_URL}/api/salva_ordine.php \
  -H "Content-Type: application/json" \
  -d '{
    "nome_cliente": "Test Cliente",
    "id_tavolo": 1,
    "numero_coperti": 2,
    "totale": 25.50,
    "dettagli": [
      {
        "id_prodotto": 1,
        "descrizione": "Test Product",
        "quantita": 1,
        "prezzo_unitario": 25.50
      }
    ]
  }'
```

**Expected Result:** 
- Order saved successfully
- New COMANDE records have `Data_Invio = NULL` (not '1970-01-01')

**Verification Query:**
```sql
-- Check the most recent comanda
SELECT ID_Comanda, Stato, Data_Invio, Data_Creazione 
FROM COMANDE 
ORDER BY Data_Creazione DESC 
LIMIT 5;
```

### 2. Test Worker Processing
```bash
# Run the worker manually
php scripts/worker_process_comande.php --limit=5 --max-tries=3
```

**Expected Result:**
- Worker processes pending comande
- After successful send, `Data_Invio` is set to current timestamp
- Failed comande keep `Data_Invio = NULL`

**Verification Query:**
```sql
-- Check comande processed by worker
SELECT ID_Comanda, Stato, Data_Invio, Error_Message 
FROM COMANDE 
WHERE Stato IN ('sent', 'error')
ORDER BY Data_Creazione DESC 
LIMIT 10;
```

### 3. Test Comanda Retry (API)
```bash
# Get a comanda ID from error state
curl -X POST {BASE_URL}/api/ripeti_comanda.php \
  -H "Content-Type: application/json" \
  -d '{"id_comanda": 123}'
```

**Expected Result:**
- On success: `Data_Invio` set to current timestamp
- On failure: `Data_Invio` remains NULL

### 4. Test Query for Unsent Comande
```sql
-- This query should find all unsent comande
SELECT ID_Comanda, Stato, Data_Invio 
FROM COMANDE 
WHERE Data_Invio IS NULL;

-- This should return 0 rows (old check that won't work anymore)
SELECT COUNT(*) 
FROM COMANDE 
WHERE Data_Invio = '1970-01-01 00:00:00';
```

### 5. Check Reports Still Work
- Navigate to the reports page
- Generate a daily report
- Verify all data displays correctly

## Rollback Procedure

If issues are found:

1. Stop the worker service:
   ```bash
   sudo systemctl stop worker_comande.service
   ```

2. Restore from backup:
   ```bash
   mysql -u username -p database_name < backup_YYYYMMDD_HHMMSS.sql
   ```

3. Restart services:
   ```bash
   sudo systemctl start worker_comande.service
   ```

## Success Criteria

- ✅ No records have Data_Invio = '1970-01-01'
- ✅ New comande are created with Data_Invio = NULL
- ✅ Worker successfully processes comande and sets Data_Invio on success
- ✅ Failed comande keep Data_Invio = NULL
- ✅ Retry functionality works correctly
- ✅ Reports display correctly
- ✅ No errors in PHP logs or worker logs

## Notes

- The PHP code already handles NULL correctly with CASE statements
- No code changes were needed beyond the schema changes
- The semantics are now clear: NULL = not sent, timestamp = sent
