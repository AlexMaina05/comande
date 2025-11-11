# Gestione Costo Coperti

## Panoramica

Il sistema RICEVUTE supporta la gestione automatica del costo per coperto, che viene aggiunto al totale di ogni ordine in base al numero di coperti specificato.

## Funzionalità

### 1. Configurazione del Costo Coperto

Il costo per coperto è configurabile dal pannello amministrativo:

1. Accedi al pannello admin (`admin.php`)
2. Seleziona la tab "Impostazioni"
3. Imposta il costo per coperto (es. 2.00 EUR)
4. Clicca "Salva Costo Coperto"

Il valore viene memorizzato nella tabella `IMPOSTAZIONI` con chiave `costo_coperto`.

### 2. Calcolo Automatico

Quando si crea un ordine dalla cassa:

1. Il sistema carica automaticamente il costo coperto configurato
2. Calcola: `Totale Coperti = Numero Coperti × Costo per Coperto`
3. Il totale dell'ordine diventa: `Totale = Subtotale Prodotti + Totale Coperti - Sconto`

**Nota:** Per ordini staff (flag `staff = true`), il costo coperti viene azzerato automaticamente.

### 3. Visualizzazione in Cassa

L'interfaccia cassa (`cassa.php`) mostra il dettaglio del calcolo:

```
Subtotale:    15.00 €
Coperti:       4.00 €  (2 coperti × 2.00 €)
Sconto:        0.00 €
------------------------
TOTALE:       19.00 €
```

Il campo coperti si aggiorna automaticamente quando si modifica il numero di coperti.

### 4. Stampa Ricevuta

La ricevuta stampata include il dettaglio del costo coperti:

```
--- RICEVUTA CLIENTE ---
Ordine: #123
Data: 23/10/2025 20:30:15
Cliente: Mario Rossi
Tavolo: T5
Coperti: 2
----------------------------------
1   x Pizza Margherita     8.00
1   x Acqua Naturale       2.00
----------------------------------
SUBTOTALE:                10.00 EUR
COPERTI (2 x 2.00):        4.00 EUR
----------------------------------
TOTALE:                   14.00 EUR
--- Grazie e Arrivederci ---
```

### 5. Comande Reparti

Le comande inviate ai reparti (cucina, bar, etc.) mostrano sempre il numero di coperti per informare il personale:

```
COMANDA REPARTO: CUCINA
Ordine: #123 | Tavolo: T5
Cliente: Mario Rossi | Coperti: 2
----------------------------------
1   x Pizza Margherita
1   x Pasta Carbonara
----------------------------------
Inviato: 23/10/2025 20:30:15
```

Questo permette al personale di sapere per quante persone preparare il servizio.

## Struttura Database

### Tabella IMPOSTAZIONI

```sql
CREATE TABLE IMPOSTAZIONI (
  Chiave VARCHAR(100) PRIMARY KEY,
  Valore TEXT NOT NULL,
  Descrizione VARCHAR(255) NOT NULL DEFAULT '',
  Tipo ENUM('string', 'number', 'boolean') NOT NULL DEFAULT 'string',
  Data_Modifica TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Record Costo Coperto

```sql
INSERT INTO IMPOSTAZIONI (Chiave, Valore, Descrizione, Tipo) 
VALUES ('costo_coperto', '2.00', 'Costo per coperto da aggiungere al totale ordine (EUR)', 'number');
```

## API Endpoints

### GET api/gestisci_impostazioni.php

Recupera le impostazioni dell'applicazione.

**Parametri:**
- `chiave` (opzionale): Chiave specifica da recuperare

**Risposta di successo:**
```json
{
  "success": true,
  "data": {
    "Chiave": "costo_coperto",
    "Valore": "2.00",
    "Descrizione": "Costo per coperto da aggiungere al totale ordine (EUR)",
    "Tipo": "number"
  }
}
```

### POST/PUT api/gestisci_impostazioni.php

Aggiorna un'impostazione esistente.

**Body:**
```json
{
  "chiave": "costo_coperto",
  "valore": "2.50"
}
```

**Risposta di successo:**
```json
{
  "success": true,
  "data": {
    "message": "Impostazione aggiornata con successo",
    "chiave": "costo_coperto"
  }
}
```

## Migrazione Database

Per installare la funzionalità su un database esistente, eseguire:

```bash
mysql -u username -p database_name < sql/migrations/2025-10-23_add_impostazioni_table.sql
```

Oppure eseguire manualmente lo script SQL dal pannello phpMyAdmin.

## Casi d'Uso

### Scenario 1: Ordine Normale
- Cliente: 4 persone
- Costo coperto: 2.00 EUR
- Prodotti: 40.00 EUR
- **Totale: 48.00 EUR** (40 + 4×2)

### Scenario 2: Ordine con Sconto
- Cliente: 2 persone
- Costo coperto: 2.00 EUR
- Prodotti: 30.00 EUR
- Sconto: 5.00 EUR
- **Totale: 29.00 EUR** (30 + 2×2 - 5)

### Scenario 3: Ordine Staff
- Flag staff: ✓
- Cliente: 3 persone
- Costo coperto: 2.00 EUR
- Prodotti: 25.00 EUR
- **Totale: 0.00 EUR** (ordine staff gratis)

### Scenario 4: Asporto (0 coperti)
- Cliente: Asporto
- Coperti: 0
- Costo coperto: 2.00 EUR
- Prodotti: 20.00 EUR
- **Totale: 20.00 EUR** (0×2 = 0, nessun costo coperti)

## Note Implementative

1. **Retrocompatibilità**: Se la tabella IMPOSTAZIONI non esiste o il valore non è impostato, il sistema usa costo_coperto = 0.00 senza errori.

2. **Validazione**: Il costo coperto deve essere un numero >= 0 e <= 999.99 EUR.

3. **Precisione**: I calcoli usano aritmetica decimale con 2 cifre decimali per evitare errori di arrotondamento.

4. **Performance**: Il costo coperto viene caricato una sola volta all'apertura della pagina cassa, riducendo le chiamate al database.

5. **Sicurezza**: L'API di gestione impostazioni include validazione input completa per prevenire SQL injection e XSS.

## Risoluzione Problemi

### Il costo coperto non viene applicato
- Verificare che la tabella IMPOSTAZIONI esista nel database
- Controllare che il record con Chiave='costo_coperto' sia presente
- Verificare i log PHP per eventuali errori di connessione al database

### Il totale è errato
- Verificare che il JavaScript abbia caricato correttamente il costo coperto (controllare console browser)
- Assicurarsi che il numero di coperti sia corretto
- Verificare che non ci siano sconti o flag staff attivi

### Non riesco a salvare il costo coperto
- Verificare di essere autenticati come admin
- Controllare i permessi sul database per UPDATE sulla tabella IMPOSTAZIONI
- Verificare che il valore inserito sia un numero valido

## Estensioni Future

Possibili miglioramenti alla funzionalità:

1. Costo coperto variabile per fasce orarie (es. pranzo vs cena)
2. Costo coperto diverso per tipologia tavolo (sala vs veranda)
3. Esenzione coperti per bambini (campo aggiuntivo nell'ordine)
4. Report specifico sul totale coperti incassati per periodo
5. Storico modifiche del costo coperto nel tempo

## Riferimenti

- Schema database: `sql/schema.sql`
- Migration: `sql/migrations/2025-10-23_add_impostazioni_table.sql`
- API: `api/gestisci_impostazioni.php`
- API ordini: `api/salva_ordine.php`
- Frontend cassa: `cassa.js`, `cassa.php`
- Frontend admin: `admin.js`, `admin.php`
