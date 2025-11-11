-- Migration: Add discount field to ORDINI table
-- This field stores the discount amount that was applied to the order
-- The Totale_Ordine already reflects the final price after discount

ALTER TABLE ORDINI 
ADD COLUMN Sconto DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER Totale_Ordine;

-- Add index for potential reporting queries
ALTER TABLE ORDINI 
ADD INDEX idx_ordini_sconto (Sconto);
