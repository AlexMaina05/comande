-- Progettazione Database per Web App Ristorante (TUTTI I CAMPI NOT NULL)
-- Questo script crea tutte le tabelle necessarie con colonne NOT NULL e valori di default.
-- ATTENZIONE: Poiché tutti i campi sono NOT NULL e alcuni FK hanno default, è necessario eseguire gli INSERT di seed (vedi sotto).
-- Eseguire in phpMyAdmin o mysql CLI.

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
  Data_Ora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ID_Tavolo) REFERENCES TAVOLI(ID_Tavolo) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_ordini_data (Data_Ora),
  INDEX idx_ordini_tavolo (ID_Tavolo)
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

-- ======================================================
-- SEED INIZIALI (necessari perché tutte le FK e colonne hanno DEFAULT non-null)
-- Eseguire questi INSERT prima di inserire dati che facciano riferimento ai default.
-- ======================================================

INSERT INTO CATEGORIE (ID_Categoria, Nome_Categoria) VALUES (1, 'Default')
  ON DUPLICATE KEY UPDATE Nome_Categoria = Nome_Categoria;

INSERT INTO REPARTI (ID_Reparto, Nome_Reparto, Nome_Stampante_LAN) VALUES (1, 'Default', 'default_printer')
  ON DUPLICATE KEY UPDATE Nome_Reparto = Nome_Reparto;

INSERT INTO TAVOLI (ID_Tavolo, Nome_Tavolo, Tipo_Servizio) VALUES (1, 'Default', 'SALA')
  ON DUPLICATE KEY UPDATE Nome_Tavolo = Nome_Tavolo;

INSERT INTO PRODOTTI (ID_Prodotto, Descrizione, Prezzo, Codice_Prodotto, ID_Categoria, ID_Reparto)
  VALUES (1, 'Prodotto di Default', 0.00, 'DEFAULT', 1, 1)
  ON DUPLICATE KEY UPDATE Descrizione = Descrizione;

-- Nota: ORDINI e DETTAGLI_ORDINE non vengono seedati qui; i campi ID_Tavolo/ID_Prodotto hanno default 1 che punta ai record sopra.
-- ======================================================
