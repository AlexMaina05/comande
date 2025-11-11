# Documentazione Export / Backup

## Panoramica

Il sistema di export/backup consente agli amministratori di esportare i dati critici delle tabelle del database in formati CSV o SQL per scopi di backup, analisi o migrazione.

## Accesso alla Funzionalità

La funzione di export è accessibile esclusivamente agli utenti autenticati come amministratori tramite:
- **Interfaccia Web**: Nella pagina `admin.php`, tab "Export / Backup"
- **API Diretta**: Endpoint `api/gestisci_dati.php?action=export`

## Tabelle Supportate

Il sistema permette l'export delle seguenti tabelle:

1. **Prodotti** (`prodotti`) - Anagrafica dei prodotti del menu
2. **Ordini** (`ordini`) - Storico completo degli ordini
3. **Comande** (`comande`) - Registro delle comande inviate alle stampanti

## Formati di Export

### CSV (Comma-Separated Values)

**Caratteristiche:**
- Encoding: UTF-8 con BOM
- Separatore: virgola (`,`)
- Prima riga: intestazione con nomi delle colonne
- Ideale per: analisi in Excel, LibreOffice Calc, Google Sheets

**Esempio di utilizzo:**
```
api/gestisci_dati.php?action=export&table=prodotti&format=csv
```

**Output:**
```csv
ID_Prodotto,Descrizione,Prezzo,Codice_Prodotto,ID_Categoria,ID_Reparto
1,Caffè,1.50,CAFFE01,1,1
2,Cappuccino,2.00,CAP01,1,1
...
```

### SQL (SQL INSERT Statements)

**Caratteristiche:**
- Istruzioni INSERT pronte all'uso
- Batch di 100 righe per ottimizzare le performance
- Valori properly escaped per sicurezza
- Ideale per: backup e restore del database, migrazione dati

**Esempio di utilizzo:**
```
api/gestisci_dati.php?action=export&table=ordini&format=sql
```

**Output:**
```sql
-- Export SQL per tabella: ORDINI
-- Generato il: 2025-10-23 15:30:00
-- Formato: INSERT statements

INSERT INTO ORDINI (ID_Ordine, Nome_Cliente, ID_Tavolo, Numero_Coperti, Totale_Ordine, Data_Ora) VALUES
(1, 'Mario Rossi', 5, 2, 35.50, '2025-10-23 12:30:00'),
(2, 'Lucia Bianchi', 3, 4, 72.00, '2025-10-23 13:15:00');
```

## Utilizzo dall'Interfaccia Web

1. Accedi alla pagina amministrativa (`admin.php`)
2. Clicca sul tab **"Export / Backup"**
3. Scegli la tabella da esportare
4. Clicca sul pulsante del formato desiderato (CSV o SQL)
5. Conferma l'operazione nella finestra di dialogo
6. Il file verrà scaricato automaticamente nel browser

## Utilizzo via API

### Parametri Richiesti

- `action` (string): deve essere `"export"`
- `table` (string): nome della tabella (`prodotti`, `ordini`, `comande`)
- `format` (string): formato di export (`csv`, `sql`)

### Parametri Opzionali

- `limit` (integer): limita il numero di righe esportate (utile per test)

### Autenticazione

L'endpoint richiede una sessione PHP valida con privilegi di amministratore. L'accesso non autorizzato restituisce un errore HTTP 403.

### Esempio di chiamata

```bash
# Export CSV dei prodotti
curl -b cookies.txt "http://localhost/api/gestisci_dati.php?action=export&table=prodotti&format=csv" > prodotti.csv

# Export SQL degli ordini (primi 1000 record)
curl -b cookies.txt "http://localhost/api/gestisci_dati.php?action=export&table=ordini&format=sql&limit=1000" > ordini.sql
```

## Sicurezza

### Controlli Implementati

1. **Autenticazione Obbligatoria**: Verifica della sessione PHP e ruolo admin
2. **Whitelist delle Tabelle**: Solo le tabelle esplicitamente consentite possono essere esportate
3. **Validazione dei Parametri**: Controllo rigoroso dei valori di input
4. **Protezione SQL Injection**: Uso di prepared statements e PDO::quote()
5. **Nessun Path Traversal**: I parametri table sono mappati a nomi di tabella fissi
6. **Logging**: Tutte le operazioni di export vengono registrate

### Limitazioni di Sicurezza

- Non è possibile esportare tabelle non incluse nella whitelist
- Non è possibile specificare percorsi di file o directory
- Non è possibile eseguire query SQL arbitrarie

## Performance e Limiti

### Streaming

L'export utilizza il metodo di **streaming** per evitare problemi di memoria (OOM - Out Of Memory) con tabelle grandi:

- I dati vengono letti e inviati in chunk progressivi
- Non viene mai caricato l'intero dataset in memoria
- Adatto per tabelle con migliaia o decine di migliaia di righe

### Considerazioni

- **Tabelle Piccole** (<1000 righe): Export praticamente istantaneo
- **Tabelle Medie** (1000-10000 righe): Pochi secondi
- **Tabelle Grandi** (>10000 righe): Può richiedere 10-30 secondi

Per tabelle molto grandi, considera l'uso del parametro `limit` per esportare i dati in blocchi separati.

## Logging

Tutte le operazioni di export vengono registrate nel file `logs/export.log` con il seguente formato:

```
[2025-10-23 15:30:45] SUCCESS - User: admin, Table: prodotti, Format: csv
[2025-10-23 15:31:12] SUCCESS - User: admin, Table: ordini, Format: sql
[2025-10-23 15:32:00] FAILED - User: admin, Table: invalid, Format: csv - Error: Invalid table name
```

### Informazioni Registrate

- Timestamp dell'operazione
- Stato (SUCCESS/FAILED)
- Utente che ha eseguito l'export
- Tabella e formato richiesti
- Messaggio di errore (in caso di fallimento)

## Backup e Restore

### Procedura di Backup Completo

1. Esporta tutte le tabelle critiche in formato SQL
2. Salva i file in un percorso sicuro (esterno al server web)
3. Conserva i backup con data e ora nel nome del file
4. Pianifica backup regolari (giornalieri, settimanali, mensili)

### Procedura di Restore

Per ripristinare dati da un backup SQL:

```bash
# Connessione al database
mysql -u root -p ristorante_db < ordini_2025-10-23_153000.sql
```

O tramite phpMyAdmin:
1. Accedi a phpMyAdmin
2. Seleziona il database
3. Vai su "Import"
4. Carica il file SQL
5. Esegui

## Troubleshooting

### Errore: "Accesso non autorizzato"

**Causa**: La sessione non è autenticata o non hai privilegi admin.

**Soluzione**: Effettua il login come amministratore tramite `login.php`.

### Errore: "Tabella non valida"

**Causa**: Il nome della tabella specificato non è nella whitelist.

**Soluzione**: Verifica di usare uno dei nomi consentiti: `prodotti`, `ordini`, `comande`.

### Errore: "Formato non valido"

**Causa**: Il formato specificato non è supportato.

**Soluzione**: Usa `csv` o `sql` come valore del parametro `format`.

### File CSV non si apre correttamente in Excel

**Causa**: Problema di encoding o configurazione di Excel.

**Soluzione**: 
- I file CSV sono salvati con UTF-8 + BOM per compatibilità Excel
- Se il problema persiste, apri Excel, usa "Dati" > "Da testo/CSV" e specifica UTF-8

### Export molto lento

**Causa**: Tabella con molti dati o server sotto carico.

**Soluzione**:
- Usa il parametro `limit` per esportare in blocchi più piccoli
- Esegui l'export in orari di basso traffico
- Considera un export diretto dal database via mysqldump

## Best Practices

1. **Backup Regolari**: Pianifica export periodici automatici
2. **Conservazione Sicura**: Salva i backup in posizioni sicure e ridondanti
3. **Test di Restore**: Verifica periodicamente che i backup siano ripristinabili
4. **Pulizia Periodica**: Elimina backup vecchi secondo policy aziendali
5. **Monitoraggio Log**: Controlla regolarmente `logs/export.log` per anomalie

## Riferimenti

- Schema Database: `sql/schema.sql`
- API Response Helper: `api/response.php`
- Input Validator: `src/Utils/InputValidator.php`
- Sicurezza: `summary/SECURITY_SUMMARY.md`
