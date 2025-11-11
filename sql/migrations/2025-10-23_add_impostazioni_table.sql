-- Migration: Add IMPOSTAZIONI table for application settings
-- Date: 2025-10-23
-- Description: Creates a settings table to store configurable values like cover charge

CREATE TABLE IF NOT EXISTS IMPOSTAZIONI (
  Chiave VARCHAR(100) PRIMARY KEY,
  Valore TEXT NOT NULL,
  Descrizione VARCHAR(255) NOT NULL DEFAULT '',
  Tipo ENUM('string', 'number', 'boolean') NOT NULL DEFAULT 'string',
  Data_Modifica TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default cover charge setting (2.00 EUR per cover)
INSERT INTO IMPOSTAZIONI (Chiave, Valore, Descrizione, Tipo) 
VALUES ('costo_coperto', '2.00', 'Costo per coperto da aggiungere al totale ordine (EUR)', 'number')
ON DUPLICATE KEY UPDATE Descrizione = 'Costo per coperto da aggiungere al totale ordine (EUR)';
