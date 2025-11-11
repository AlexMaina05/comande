# PROBLEMS — problemi funzionali rilevati (non di sicurezza)

Questo documento elenca i problemi pratici trovati durante l'ispezione del codice (focus sul funzionamento dell'applicazione, non sulla sicurezza). Per ciascun problema trovate: descrizione, impatto pratico, file di riferimento e raccomandazioni concrete.

### TODO
Eseguire un controllo sull'impatto di questi problemi o se possono essere ignorati per il fine del progetto,

## Sintesi veloce
- Problematiche principali non‑security:
  - Il server si fida del totale inviato dal client (alto impatto).
  - Uso di float per importi monetari (precisione/arrotondamenti) (medio‑alto).
  - Dipendenza e comportamento di stampa/worker che possono lasciare comande non processate (medio).
  - Fallback del worker (GET_LOCK / exit) che può interrompere il processamento in scenari concorrenti (medio).
  - Mancanza di timezone esplicita → possibili incoerenze data/ora (basso‑medio).
  - Potenziali indici mancanti per performance su tabelle grandi (basso‑medio).
  - Alcune fragilità operative: gestione file temporanei, uso dell'operatore di soppressione `@` su exec, logging non sempre dettagliato (basso).

---

## Problemi dettagliati

### 1) Il server si fida del totale inviato dal client
- Dove: `api/salva_ordine.php`
- Descrizione: l'API valida che il campo `totale` sia numerico e dentro un range, ma non ricalcola mai il totale lato server usando i dettagli dell'ordine. Se il client invia un totale errato, questo viene salvato nel DB.
- Impatto pratico: dati di vendita errati, report e contabilità incoerenti.
- Raccomandazione: ricalcolare sempre il totale sul server (somma righe + coperti - sconto) ed ignorare/sovrascrivere il totale inviato dal client prima di eseguire l'INSERT.

### 2) Uso di float per importi monetari
- Dove: molte funzioni di calcolo in `api/salva_ordine.php` (e altri file)
- Descrizione: l'uso di float (IEEE) per denaro può introdurre errori di arrotondamento.
- Impatto pratico: differenze centesimali nei report e nella contabilità.
- Raccomandazione: preferire integer (centesimi) o usare BCMath / librerie decimal. Migrazione consigliata in più step (convertire calcoli a centesimi, poi migrare colonne DB se necessario).

### 3) Comportamento stampa/worker che può lasciare comande non processate
- Dove: `api/salva_ordine.php`, `api/ripeti_comanda.php`, `scripts/worker_process_comande.php`
- Descrizione: la logica gestisce l'assenza di `lp` lasciando comande in `pending`, ma in alcuni fallback il worker esce invece di ritentare (es. fallimento GET_LOCK → exit). Uso di `@exec` sopprime errori che rendono il debugging più difficile.
- Impatto pratico: comande non stampate, situazione non evidente agli operatori, debug più laborioso.
- Raccomandazione: rendere il worker più tollerante (retry/backoff), rimuovere l'uso di `@` su `exec` e loggare sempre stdout/stderr in caso di errore; fornire modalità di dry-run/mock per test.

### 4) Timeout / retry e gestione transazioni nel worker
- Dove: `scripts/worker_process_comande.php`
- Descrizione: il fallback che usa `GET_LOCK` può terminare il worker senza riprovare; dipendenza da `FOR UPDATE SKIP LOCKED` richiede MySQL 8+ o equivalente.
- Impatto pratico: in ambienti non compatibili o con concorrenza alta, il worker potrebbe non processare le comande come previsto.
- Raccomandazione: implementare retry con backoff quando GET_LOCK fallisce, documentare requisito MySQL o implementare alternative testate.

### 5) Timezone non esplicita
- Dove: uso di `date()` e `NOW()` sparsi (`api/salva_ordine.php`, ecc.)
- Descrizione: se PHP/DB usano timezone diverse la `Data_Ora` salvata/visualizzata può risultare incoerente.
- Impatto pratico: report giornalieri che contano ordini nel giorno sbagliato.
- Raccomandazione: impostare esplicitamente `date_default_timezone_set()` in un punto centrale di bootstrap (es. `config/db_connection.php`) o leggere la timezone da configurazione.

### 6) Indici mancanti / ottimizzazioni query
- Dove: query frequenti su `COMANDE`, `DETTAGLI_ORDINE`, `ORDINI` (worker e report)
- Descrizione: alcune query usano `WHERE Stato`, `ORDER BY Data_Creazione` o `WHERE ID_Ordine` e potrebbero beneficiare di indici per tabelle grandi.
- Impatto pratico: degrado delle performance del worker e dei report quando il DB cresce.
- Raccomandazione: verificare e, se mancanti, aggiungere indici come:
  - `CREATE INDEX idx_comande_stato_data ON COMANDE (Stato, Data_Creazione);`
  - `CREATE INDEX idx_comande_idordine ON COMANDE (ID_Ordine);`
  - `CREATE INDEX idx_dettagli_idordine ON DETTAGLI_ORDINE (ID_Ordine);`
  - `CREATE INDEX idx_ordini_dataora ON ORDINI (Data_Ora);`

### 7) Gestione file temporanei e permessi
- Dove: `api/salva_ordine.php`, `api/ripeti_comanda.php`, `scripts/worker_process_comande.php`
- Descrizione: se `tempnam()` o `file_put_contents()` falliscono (permessi, disco pieno) la stampa fallisce. Alcuni errori sono loggati ma il comportamento operativo può risultare oscuro.
- Impatto pratico: stampe non eseguite e difficoltà di diagnostica.
- Raccomandazione: loggare con maggior dettaglio, aggiungere alert/metriche su errori di temp file e verificare permessi/rotazione log.

### 8) Uso dell'operatore di soppressione `@` su `exec`
- Dove: più file (`api/salva_ordine.php`, `api/ripeti_comanda.php`)
- Descrizione: `@exec` sopprime eventuali warning/errori; questo complica la diagnosi di problemi runtime.
- Raccomandazione: rimuovere `@`, controllare il return code e loggare output e codice di ritorno per tutte le chiamate di sistema.

### 9) Export via web per tabelle grandi
- Dove: `api/gestisci_dati.php`
- Descrizione: l'export streaming è corretto ma può essere pericoloso su tabelle molto grandi senza un limit predefinito.
- Raccomandazione: imporre un limit ragionevole per export via web (p. es. 10k righe) e raccomandare dump CLI per export completi.

---

## Azioni raccomandate (priorità)

### Alta priorità
1. Implementare il ricalcolo del totale lato server in `api/salva_ordine.php` (somma dettagli + coperti - sconto) e ignorare il valore inviato dal client.
2. (Opzionale ma consigliato) Passare i calcoli monetari a centesimi (interi) o usare BCMath per evitare problemi di arrotondamento.

### Media priorità
3. Migliorare la tolleranza del worker: retry/backoff quando GET_LOCK fallisce e non terminare immediatamente.
4. Rimuovere `@` su `exec` e loggare stdout/stderr + codice di ritorno su errori.
5. Aggiungere indici suggeriti se le tabelle crescono e si osservano degradi.

### Bassa priorità
6. Impostare `date_default_timezone_set()` in bootstrap.
7. Aggiungere log/metriche su errori di temp file e spazio disco.
8. Limitare export via web o richiedere paginazione/limit esplicito.

---

## Fix a basso rischio che posso applicare subito
- Fix A: Ricalcolo totale server-side in `api/salva_ordine.php`.
- Fix B: Impostare timezone di default in `config/db_connection.php` (es. `Europe/Rome`).
- Fix C: Rimuovere l'uso di `@` su `exec` e migliorare il logging per le chiamate `exec()` in `api/salva_ordine.php` e `api/ripeti_comanda.php`.

Se vuoi, applico A+B+C in un singolo commit (patch) e poi lancio un rapido `php -l` per verificare la sintassi.

---

## Comandi utili per testing locale
- PHP lint (PowerShell):
```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```
- Eseguire il worker manualmente:
```bash
php scripts/worker_process_comande.php --limit=5 --max-tries=3
```
- Testare `salva_ordine` con curl (esempio):
```bash
curl -X POST -H "Content-Type: application/json" -d @order.json http://localhost/api/salva_ordine.php
```
