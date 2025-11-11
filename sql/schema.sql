-- Schema "tutti NOT NULL" per RICEVUTE
-- NOTA: alcune colonne FK sono impostate con DEFAULT 1: assicurati di inserire i record seed
--       corrispondenti (es. CATEGORIE.ID_Categoria = 1, REPARTI.ID_Reparto = 1, TAVOLI.ID_Tavolo = 1, ORDINI.ID_Ordine = 1)
--       prima di inserire dati che si affidano ai default. Questo schema è volutamente "opinionated".

CREATE TABLE CATEGORIE (
  ID_Categoria INT AUTO_INCREMENT PRIMARY KEY,
  Nome_Categoria VARCHAR(150) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE REPARTI (
  ID_Reparto INT AUTO_INCREMENT PRIMARY KEY,
  Nome_Reparto VARCHAR(150) NOT NULL DEFAULT '',
  Nome_Stampante_LAN VARCHAR(150) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE PRODOTTI (
  ID_Prodotto INT AUTO_INCREMENT PRIMARY KEY,
  Descrizione VARCHAR(255) NOT NULL DEFAULT '',
  Prezzo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  Codice_Prodotto VARCHAR(191) NOT NULL DEFAULT '',
  ID_Categoria INT NOT NULL DEFAULT 1,
  ID_Reparto INT NOT NULL DEFAULT 1,
  FOREIGN KEY (ID_Categoria) REFERENCES CATEGORIE(ID_Categoria) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (ID_Reparto) REFERENCES REPARTI(ID_Reparto) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_prodotto_categoria (ID_Categoria),
  INDEX idx_prodotto_reparto (ID_Reparto),
  UNIQUE KEY ux_prodotto_codice (Codice_Prodotto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE TAVOLI (
  ID_Tavolo INT AUTO_INCREMENT PRIMARY KEY,
  Nome_Tavolo VARCHAR(150) NOT NULL DEFAULT '',
  Tipo_Servizio ENUM('SALA','ASPORTO') NOT NULL DEFAULT 'SALA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ORDINI (
  ID_Ordine INT AUTO_INCREMENT PRIMARY KEY,
  Nome_Cliente VARCHAR(255) NOT NULL DEFAULT '',
  ID_Tavolo INT NOT NULL DEFAULT 1,
  Numero_Coperti INT NOT NULL DEFAULT 0,
  Totale_Ordine DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  Sconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  Data_Ora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Staff TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (ID_Tavolo) REFERENCES TAVOLI(ID_Tavolo) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_ordini_data (Data_Ora),
  INDEX idx_ordini_tavolo (ID_Tavolo),
  INDEX idx_ordini_staff (Staff),
  INDEX idx_ordini_sconto (Sconto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE DETTAGLI_ORDINE (
  ID_Dettaglio INT AUTO_INCREMENT PRIMARY KEY,
  ID_Ordine INT NOT NULL DEFAULT 1,
  ID_Prodotto INT NOT NULL DEFAULT 1,
  Quantita INT NOT NULL DEFAULT 1,
  Prezzo_Bloccato DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (ID_Ordine) REFERENCES ORDINI(ID_Ordine) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (ID_Prodotto) REFERENCES PRODOTTI(ID_Prodotto) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_dettaglio_ordine (ID_Ordine),
  INDEX idx_dettaglio_prodotto (ID_Prodotto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- COMANDE: Table for tracking print jobs to be sent to printers
-- Data_Invio semantics (Issue #9):
--   NULL = comanda not yet sent (pending/processing/error state)
--   Non-NULL = actual timestamp when comanda was successfully sent (sent state)
CREATE TABLE COMANDE (
  ID_Comanda INT AUTO_INCREMENT PRIMARY KEY,
  ID_Ordine INT NOT NULL DEFAULT 1,
  Nome_Stampante_LAN VARCHAR(150) NOT NULL DEFAULT '',
  Testo_Comanda TEXT NOT NULL,
  Stato ENUM('pending','processing','sent','error') NOT NULL DEFAULT 'pending',
  Tentativi INT NOT NULL DEFAULT 0,
  Error_Message TEXT NOT NULL,
  Data_Creazione DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Data_Invio DATETIME DEFAULT NULL,
  FOREIGN KEY (ID_Ordine) REFERENCES ORDINI(ID_Ordine) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_comande_stato (Stato),
  INDEX idx_comande_stampante (Nome_Stampante_LAN),
  INDEX idx_comande_data_invio (Data_Invio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IMPOSTAZIONI: Table for application settings
CREATE TABLE IMPOSTAZIONI (
  Chiave VARCHAR(100) PRIMARY KEY,
  Valore TEXT NOT NULL,
  Descrizione VARCHAR(255) NOT NULL DEFAULT '',
  Tipo ENUM('string', 'number', 'boolean') NOT NULL DEFAULT 'string',
  Data_Modifica TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;