# Database Migrations

This directory contains SQL migration scripts for the RICEVUTE database.

## Migration Files

- `2025-10-23_migrate_data_invio_to_null.sql` - Standardizes Data_Invio column to use NULL for unsent comande

## How to Apply Migrations

1. **Backup the database** before applying any migration:
   ```bash
   mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Review the migration script** to understand what changes will be made

3. **Apply the migration**:
   ```bash
   mysql -u username -p database_name < sql/migrations/YYYY-MM-DD_migration_name.sql
   ```

4. **Verify the migration** by checking the output queries in the migration script

## Data_Invio Column Semantics

After the 2025-10-23 migration:
- **NULL** = comanda not yet sent (pending or error state)
- **Non-NULL timestamp** = actual date/time when comanda was successfully sent

The application code uses this logic when updating comande:
```sql
Data_Invio = CASE WHEN Stato = 'sent' THEN NOW() ELSE Data_Invio END
```

This ensures Data_Invio is only set to a real timestamp when the comanda is successfully sent.

## Rollback

If you need to rollback a migration:
1. Restore from the backup made before the migration
2. Investigate why the migration failed
3. Fix any issues and re-apply if needed

**Note:** Always test migrations in a staging environment before applying to production!
