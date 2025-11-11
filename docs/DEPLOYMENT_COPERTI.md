# Deployment Guide - Cover Charge Feature

## Quick Start

Follow these steps to deploy the cover charge feature to your production system.

## Prerequisites

- Database access (MySQL/MariaDB)
- Web server access to update files
- Admin credentials for the application

## Deployment Steps

### 1. Backup Current System

```bash
# Backup database
mysqldump -u username -p database_name > backup_before_coperti_$(date +%Y%m%d).sql

# Backup files
tar -czf backup_files_$(date +%Y%m%d).tar.gz /path/to/RICEVUTE/
```

### 2. Run Database Migration

**Option A: Using MySQL command line**
```bash
mysql -u username -p database_name < sql/migrations/2025-10-23_add_impostazioni_table.sql
```

**Option B: Using phpMyAdmin**
1. Login to phpMyAdmin
2. Select your database
3. Go to SQL tab
4. Copy and paste contents of `sql/migrations/2025-10-23_add_impostazioni_table.sql`
5. Click "Go"

**Expected Result:**
```
Query OK, 0 rows affected
Query OK, 1 row affected
```

### 3. Verify Migration

```sql
-- Check table exists
SHOW TABLES LIKE 'IMPOSTAZIONI';

-- Check default setting
SELECT * FROM IMPOSTAZIONI WHERE Chiave = 'costo_coperto';
```

**Expected Output:**
```
Chiave         | Valore | Descrizione                                           | Tipo
costo_coperto  | 2.00   | Costo per coperto da aggiungere al totale ordine (EUR)| number
```

### 4. Update Application Files

Upload/update the following files to your server:

**New Files:**
- `api/gestisci_impostazioni.php`
- `sql/migrations/2025-10-23_add_impostazioni_table.sql`
- `docs/COSTO_COPERTI.md`
- `tools/tests/test_impostazioni_api.php`
- `summary/COPERTI_IMPLEMENTATION_SUMMARY.md`
- `summary/SECURITY_SUMMARY_COPERTI.md`

**Modified Files:**
- `api/salva_ordine.php`
- `admin.php`
- `admin.js`
- `cassa.php`
- `cassa.js`
- `style.css`
- `sql/schema.sql`

### 5. Set File Permissions

```bash
# Ensure proper permissions
chmod 644 api/gestisci_impostazioni.php
chmod 644 api/salva_ordine.php
chmod 644 admin.php
chmod 644 cassa.php
chmod 644 style.css

# Make test script executable (optional)
chmod +x tools/tests/test_impostazioni_api.php
```

### 6. Clear Cache

**Browser Cache:**
- Press Ctrl+F5 (Windows/Linux) or Cmd+Shift+R (Mac) to hard refresh
- Or clear browser cache manually

**Server Cache (if applicable):**
```bash
# If using OpCache
php -r "opcache_reset();"

# Or restart PHP-FPM
sudo systemctl restart php-fpm
# OR
sudo systemctl restart php7.4-fpm  # adjust version number
```

### 7. Test the Feature

#### Test 1: Admin Configuration
1. Login to admin panel
2. Click on "Impostazioni" tab
3. Verify cover charge field shows 2.00
4. Change value to 2.50
5. Click "Salva Costo Coperto"
6. Verify success message appears

#### Test 2: Order Creation
1. Go to Cassa page
2. Enter customer name: "Test Cliente"
3. Select a table
4. Set covers to 2
5. Add a product (e.g., pizza)
6. Verify "Coperti" row shows: 5.00 € (2 × 2.50)
7. Verify total includes cover charge

#### Test 3: Receipt Check
1. Complete the test order
2. Check printed receipt (if printer configured)
3. Verify receipt shows:
   - SUBTOTALE: [product total]
   - COPERTI (2 x 2.50): 5.00 EUR
   - TOTALE: [subtotal + coperti]

### 8. Rollback Plan (If Needed)

If you encounter issues:

```bash
# 1. Restore database backup
mysql -u username -p database_name < backup_before_coperti_YYYYMMDD.sql

# 2. Restore files
cd /path/to/RICEVUTE/
tar -xzf backup_files_YYYYMMDD.tar.gz --strip-components=1

# 3. Clear cache again
# Follow step 6 above
```

## Troubleshooting

### Issue: "Tabella IMPOSTAZIONI non trovata"
**Solution:** Run the migration script again (step 2)

### Issue: Cover charge not showing in cassa
**Solution:**
1. Clear browser cache (Ctrl+F5)
2. Check browser console for JavaScript errors
3. Verify API endpoint: navigate to `api/gestisci_impostazioni.php?chiave=costo_coperto`
   - Should return JSON with success=true

### Issue: Cannot save cover charge in admin
**Solution:**
1. Check you're logged in as admin
2. Verify database user has UPDATE permissions on IMPOSTAZIONI table
3. Check PHP error logs: `/var/log/apache2/error.log` or `/var/log/php-fpm/error.log`

### Issue: Total calculation is wrong
**Solution:**
1. Verify number of covers is correct
2. Check cover charge value in admin panel
3. Verify subtotal calculation (products only)
4. Formula: Total = Subtotal + (Covers × CostPerCover) - Discount

### Issue: Receipt doesn't show cover charge
**Solution:**
1. Verify printer is configured correctly
2. Check if CUPS is working: `lpstat -p -d`
3. Verify order has non-zero covers
4. Check PHP error log for printing errors

## Verification Checklist

After deployment, verify:

- [ ] Migration completed successfully
- [ ] IMPOSTAZIONI table exists with costo_coperto record
- [ ] Admin panel shows "Impostazioni" tab
- [ ] Can view and edit cover charge in admin
- [ ] Cassa shows cover charge calculation
- [ ] Cover charge updates in real-time when changing covers
- [ ] Orders save correctly with cover charge in total
- [ ] Receipts print with cover charge line item
- [ ] Staff orders have zero cover charge
- [ ] Asporto (0 covers) has no cover charge
- [ ] Existing orders still work correctly

## Support

For issues or questions:

1. Check `docs/COSTO_COPERTI.md` for detailed documentation
2. Review `summary/COPERTI_IMPLEMENTATION_SUMMARY.md` for technical details
3. Check `summary/SECURITY_SUMMARY_COPERTI.md` for security information
4. Open an issue on GitHub

## Performance Notes

- The cover charge setting is cached in JavaScript (loaded once per page load)
- Minimal database impact: one SELECT per order
- No impact on existing indexes or queries
- Scalable to thousands of orders per day

## Next Steps

After successful deployment:

1. Monitor the system for the first few orders
2. Collect feedback from staff
3. Adjust cover charge as needed from admin panel
4. Consider implementing suggested future enhancements (see summary/COPERTI_IMPLEMENTATION_SUMMARY.md)

---

**Deployment Complete!** 🎉

The cover charge feature is now active and ready to use.
