-- Migration: Standardize Data_Invio to NULL for unsent comande
-- Issue: #9
-- Date: 2025-10-23
-- Description: 
--   This migration standardizes the Data_Invio column to use NULL as the canonical value
--   for "not sent" comande. Previously, some scripts used '1970-01-01 00:00:00' as default.
--
-- Steps:
--   1. Update existing records with '1970-01-01' dates to NULL
--   2. Alter table to remove NOT NULL constraint and change default to NULL
--
-- IMPORTANT: Run this migration during a maintenance window to avoid conflicts
-- Make a backup of the database before running this migration!

-- Step 1: Update existing data where Data_Invio is '1970-01-01' (any time) to NULL
UPDATE COMANDE 
SET Data_Invio = NULL 
WHERE Data_Invio = '1970-01-01 00:00:00' 
   OR Data_Invio = '1970-01-01'
   OR DATE(Data_Invio) = '1970-01-01';

-- Step 2: Alter table to change column definition
-- Remove NOT NULL constraint and change default to NULL
ALTER TABLE COMANDE 
MODIFY COLUMN Data_Invio DATETIME DEFAULT NULL;

-- Verify migration
-- This query should return 0 rows if migration was successful
SELECT COUNT(*) as old_default_count 
FROM COMANDE 
WHERE DATE(Data_Invio) = '1970-01-01';

-- This query shows the distribution of Data_Invio values
SELECT 
  CASE 
    WHEN Data_Invio IS NULL THEN 'NULL (not sent)'
    ELSE 'HAS DATE (sent)'
  END as status,
  COUNT(*) as count
FROM COMANDE
GROUP BY status;
