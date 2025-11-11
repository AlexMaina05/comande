-- Migration: Add Staff flag to ORDINI table
-- Date: 2025-10-23
-- Description: Adds a Staff column to track orders for staff members that should not count in reports

-- Add Staff column to ORDINI table
ALTER TABLE ORDINI 
ADD COLUMN Staff TINYINT(1) NOT NULL DEFAULT 0 AFTER Data_Ora;

-- Add index for efficient filtering
ALTER TABLE ORDINI
ADD INDEX idx_ordini_staff (Staff);
