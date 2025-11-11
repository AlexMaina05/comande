# Standardizzazione Risposte API - Sommario Implementazione

## Obiettivo
Implementare un formato di risposta JSON uniforme per tutte le API e normalizzare la gestione degli errori nel repository AlexMaina05/RICEVUTE.

## Modifiche Implementate

### 1. Helper per Risposte API
**File**: `api/response.php`

Creata classe `ApiResponse` con tre metodi statici:
- `sendSuccess($data, $httpCode)`: Invia risposta di successo con formato standardizzato
- `sendError($message, $code, $httpCode, $details)`: Invia risposta di errore con formato standardizzato
- `setJsonHeaders()`: Imposta header JSON e disabilita caching

**Schema di risposta**:
```json
{
  "success": true|false,
  "data": {...},        // solo se success=true
  "error": {            // solo se success=false
    "code": 1001,
    "message": "...",
    "details": [...]    // opzionale
  }
}
```

### 2. Refactoring Endpoint API
Tutti gli endpoint sono stati aggiornati per usare l'helper:

#### api/gestisci_dati.php
- ✅ Include `api/response.php`
- ✅ Usa `ApiResponse::sendError()` per errori di validazione (codici 1001-1003)
- ✅ Usa `ApiResponse::sendSuccess()` per risposta con dati report
- ✅ Gestione errori database con codice 2001

#### api/cerca_prodotto.php
- ✅ Include `api/response.php`
- ✅ Usa `ApiResponse::sendError()` per errori validazione (codici 1004-1007)
- ✅ Usa `ApiResponse::sendError()` per prodotto non trovato (codice 1008, HTTP 404)
- ✅ Usa `ApiResponse::sendSuccess()` per prodotto trovato
- ✅ Gestione errori database con codice 2002

#### api/salva_ordine.php
- ✅ Include `api/response.php`
- ✅ Usa `ApiResponse::sendError()` per errori validazione (codici 1009-1022)
- ✅ Usa `ApiResponse::sendSuccess()` con message e order_id
- ✅ Gestione errori database con codice 2003

#### api/ripeti_comanda.php
- ✅ Include `api/response.php`
- ✅ Usa `ApiResponse::sendError()` per errori validazione (codici 1023-1026)
- ✅ Usa `ApiResponse::sendError()` per comanda non trovata (codice 1027, HTTP 404)
- ✅ Usa `ApiResponse::sendSuccess()` per ristampa riuscita
- ✅ Gestione errori server (codici 2004-2009)

#### api/genera_report.php
- ✅ Include `api/response.php`
- ✅ Usa `ApiResponse::sendError()` per errori validazione (codici 1028-1029)
- ✅ Usa `ApiResponse::sendSuccess()` per risposta con dati report
- ✅ Gestione errori database con codice 2010

### 3. Correzione db_connection.php
- ✅ Separazione responsabilità: non stampa più JSON direttamente
- ✅ Rilevamento contesto API tramite:
  - Costante `IS_API`
  - URI contenente `/api/`
  - Header `Accept: application/json`
- ✅ In contesto API: usa ApiResponse helper
- ✅ In contesto HTML: lancia eccezione che può essere catturata

### 4. Aggiornamento Client-Side JavaScript

#### cassa.js
- ✅ Parsing risposta con campo `success`
- ✅ Estrazione dati da `result.data`
- ✅ Estrazione errori da `result.error.message`

#### report.js
- ✅ Parsing risposta con campo `success`
- ✅ Estrazione dati da `result.data`
- ✅ Gestione errori standardizzata

#### admin.js
- ✅ Supporto retrocompatibilità: verifica campo `success`
- ✅ Estrazione dati considerando entrambi i formati (vecchio e nuovo)

### 5. Test di Integrazione

#### tools/tests/api_responses_test.sh
Script bash per test end-to-end:
- ✅ 10 test case per vari scenari (success, 400, 404, 500)
- ✅ Verifica presenza campo `success`
- ✅ Verifica struttura `data` per success=true
- ✅ Verifica struttura `error` con `code` e `message` per success=false
- ✅ Verifica codici HTTP appropriati
- ✅ Usa `jq` per parsing JSON
- ✅ Output colorato con conteggio pass/fail

#### tools/tests/test_api_response_simple.sh
Script bash per test unitari ApiResponse:
- ✅ Test formato risposta successo
- ✅ Test formato risposta errore
- ✅ Test errore con dettagli
- ✅ Test gestione UTF-8
- ✅ Output con conteggio pass/fail

### 6. Documentazione

#### docs/API.md
- ✅ Aggiunta sezione "Formato Uniforme delle Risposte API"
- ✅ Schema completo della risposta JSON
- ✅ Tabella codici di errore con descrizioni
- ✅ Esempi aggiornati per tutti gli endpoint con nuovo formato
- ✅ Sezione testing aggiornata con nuovi script
- ✅ Esempi test manuali con curl

#### tools/tests/README.md
- ✅ Documentazione suite di test
- ✅ Istruzioni di esecuzione
- ✅ Requisiti (PHP, jq, curl)
- ✅ Istruzioni installazione jq
- ✅ Riferimenti alla documentazione API

## Codici di Errore Assegnati

### Validazione Input (1000-1999)
- 1001-1003: gestisci_dati.php
- 1004-1008: cerca_prodotto.php
- 1009-1022: salva_ordine.php
- 1023-1027: ripeti_comanda.php
- 1028-1029: genera_report.php

### Errori Server (2000-2999)
- 2001: Errore DB gestisci_dati.php
- 2002: Errore DB cerca_prodotto.php
- 2003: Errore DB salva_ordine.php
- 2004-2009: Errori server ripeti_comanda.php
- 2010: Errore DB genera_report.php

### Infrastruttura (3000-3999)
- 3001: Errore connessione database

## Criteri di Accettazione

✅ **Tutti gli endpoint API principali usano l'helper e restituiscono JSON coerente**
- Tutti i 5 endpoint API aggiornati (gestisci_dati, cerca_prodotto, salva_ordine, ripeti_comanda, genera_report)

✅ **db_connection.php non stampa JSON in contesto HTML**
- Implementato rilevamento contesto con gestione appropriata

✅ **Test di integrazione inclusi**
- Script bash `api_responses_test.sh` con 10 test case
- Script bash `test_api_response_simple.sh` con 4 test unitari

✅ **README/Documentazione aggiornata**
- `docs/API.md` completamente aggiornato con nuovo formato
- `tools/tests/README.md` con documentazione suite test

## Test Eseguiti

### Unit Test (ApiResponse)
```bash
bash tools/tests/test_api_response_simple.sh
```
**Risultato**: ✅ 4/4 test passati

### Syntax Check
```bash
php -l api/*.php
```
**Risultato**: ✅ Nessun errore di sintassi

## Note per il Deployment

1. **Retrocompatibilità**: Client JavaScript aggiornati supportano sia vecchio che nuovo formato
2. **Database**: Nessuna modifica schema richiesta
3. **Configurazione**: Nessuna configurazione aggiuntiva richiesta
4. **Dipendenze**: Nessuna nuova dipendenza PHP richiesta

## Prossimi Passi Raccomandati

1. **Test manuale**: Testare endpoint in ambiente di staging/produzione
2. **Monitoraggio**: Verificare log errori dopo deploy per eventuali problemi
3. **Cleanup**: Eventualmente rimuovere vecchi metodi di gestione errori da InputValidator se non più usati altrove
4. **Estensione**: Applicare stesso pattern ad altri endpoint API se presenti

## File Modificati

### Nuovi file:
- `api/response.php`
- `tools/tests/api_responses_test.sh`
- `tools/tests/test_api_response_simple.sh`
- `tools/tests/test_api_response.php`
- `tools/tests/README.md`

### File modificati:
- `api/gestisci_dati.php`
- `api/cerca_prodotto.php`
- `api/salva_ordine.php`
- `api/ripeti_comanda.php`
- `api/genera_report.php`
- `db_connection.php`
- `cassa.js`
- `report.js`
- `admin.js`
- `docs/API.md`

**Totale**: 5 nuovi file, 10 file modificati
