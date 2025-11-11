# Test Suite per API Standardizzate

Questa directory contiene i test per verificare il formato uniforme delle risposte API del sistema RICEVUTE.

## Script di Test Disponibili

### 1. test_api_response_simple.sh
Test unitari per la classe `ApiResponse` che verificano:
- Formato risposta di successo (`success: true`, campo `data`)
- Formato risposta di errore (`success: false`, campo `error` con `code` e `message`)
- Gestione dettagli errore
- Supporto caratteri UTF-8

**Esecuzione:**
```bash
bash tools/tests/test_api_response_simple.sh
```

### 2. test_api_responses.sh (Issue #6)
Script di smoke test manuale per verificare lo schema di risposta uniforme:
- Visualizza risposte complete per ispezione manuale
- Verifica schema { success, data, error }
- Mostra HTTP status codes
- Test per vari scenari (successo, errori di validazione, 404, 500)

**Esecuzione:**
```bash
bash tools/tests/test_api_responses.sh http://localhost/RICEVUTE
```

### 3. api_responses_test.sh
Test di integrazione end-to-end che verificano gli endpoint API reali:
- Test con parametri validi (success)
- Test con parametri mancanti (error 400)
- Test con risorse inesistenti (error 404)
- Verifica codici HTTP appropriati
- Verifica struttura JSON standardizzata

**Esecuzione:**
```bash
bash tools/tests/api_responses_test.sh http://localhost/RICEVUTE
```

**Nota:** Richiede:
- Server web configurato e in esecuzione
- Database configurato
- `jq` installato per il parsing JSON
- `curl` per le richieste HTTP

### 4. test_api_authentication.php
Test unitario per verifica autenticazione API:
- Verifica che le API protette includano `require_admin.php`
- Verifica formato risposta errore 401
- Verifica che le API operazionali NON richiedano autenticazione
- Controlla la logica in `require_admin.php`

**Esecuzione:**
```bash
php tools/tests/test_api_authentication.php
```

### 5. test_api_authentication.sh
Test di integrazione per autenticazione API:
- Verifica che le API protette restituiscano HTTP 401 senza autenticazione
- Verifica che le API operazionali funzionino senza autenticazione
- Test con chiamate HTTP reali agli endpoint

**Esecuzione:**
```bash
bash tools/tests/test_api_authentication.sh http://localhost/RICEVUTE
```

**Nota:** Richiede:
- Server web configurato e in esecuzione
- `curl` per le richieste HTTP
- `jq` (opzionale, per verifica dettagliata JSON)

### 6. test_salva_ordine_total_calculation.php (Issue #1)
Test per verificare il calcolo server-side del totale ordine:
- Verifica che il server ricalcoli il totale invece di fidarsi del client
- Test con totale errato inviato dal client
- Test con sconto applicato
- Test con ordini staff (totale sempre 0)
- Test con sconto maggiore del subtotale (totale forzato a 0)
- **Aggiornato con integer arithmetic (MoneyHelper) per Issue #2**

**Esecuzione:**
```bash
php tools/tests/test_salva_ordine_total_calculation.php [BASE_URL]
# Esempio: php tools/tests/test_salva_ordine_total_calculation.php http://localhost/RICEVUTE
```

**Nota:** Richiede:
- Server web configurato e in esecuzione
- Database configurato con tabelle ORDINI e DETTAGLI_ORDINE
- `curl` disponibile tramite PHP

### 7. test_salva_ordine_manual.sh
Script per test manuale dell'API salva_ordine:
- Invia richieste di test con totali errati dal client
- Mostra risposte JSON per ispezione
- Fornisce query SQL per verificare i risultati nel database

**Esecuzione:**
```bash
bash tools/tests/test_salva_ordine_manual.sh [BASE_URL]
# Esempio: bash tools/tests/test_salva_ordine_manual.sh http://localhost/RICEVUTE
```

**Nota:** Richiede:
- Server web configurato e in esecuzione
- Database configurato
- `curl` per le richieste HTTP
- `jq` per formattare JSON (opzionale)

### 8. test_money_helper.php (Issue #2)
Test unitario per la classe `MoneyHelper` che gestisce calcoli monetari precisi con integer arithmetic:
- Conversione EUR → cents e cents → EUR
- Operazioni aritmetiche (add, subtract, multiply)
- Formattazione e validazione
- Test di precisione che dimostrano i problemi con float

**Esecuzione:**
```bash
php tools/tests/test_money_helper.php
```

### 9. test_order_calculation.php (Issue #2)
Test di integrazione per la logica di calcolo ordini con integer arithmetic:
- Calcolo subtotali con più prodotti
- Gestione coperti
- Applicazione sconti
- Ordini completi (prodotti + coperti - sconto)
- Edge cases e ordini staff

**Esecuzione:**
```bash
php tools/tests/test_order_calculation.php
```

### 10. test_worker_improvements.php (Issue #3)
Test unitario per verificare i miglioramenti al worker di processamento comande:
- Verifica rimozione dell'operatore `@` da chiamate `exec()`
- Verifica logging migliorato con codici di uscita
- Verifica supporto modalità dry-run (`--dry-run`)
- Verifica logica di retry con exponential backoff per GET_LOCK
- Verifica che il worker ritorni comande a 'pending' su errori transitori
- Verifica sintassi PHP di tutti i file modificati
- Verifica documentazione aggiornata

**Esecuzione:**
```bash
php tools/tests/test_worker_improvements.php
```

**Nota:** Questo test non richiede database o server web, verifica solo il codice e la logica implementata.

## Formato Risposta Standardizzato

Tutte le API restituiscono JSON nel seguente formato:

**Successo:**
```json
{
  "success": true,
  "data": { ... }
}
```

**Errore:**
```json
{
  "success": false,
  "error": {
    "code": 1001,
    "message": "Descrizione errore",
    "details": [ ... ]  // opzionale
  }
}
```

## Requisiti

- PHP 7.4 o superiore
- jq (per test con bash)
- curl (per test di integrazione)

## Installazione jq

**Debian/Ubuntu:**
```bash
sudo apt-get install jq
```

**macOS:**
```bash
brew install jq
```

## Esecuzione Rapida di Tutti i Test

```bash
# Test unitari
bash tools/tests/test_api_response_simple.sh

# Test autenticazione (unitario)
php tools/tests/test_api_authentication.php

# Test di integrazione (modifica l'URL se necessario)
bash tools/tests/api_responses_test.sh http://localhost/RICEVUTE
bash tools/tests/test_api_authentication.sh http://localhost/RICEVUTE
```

## Codici di Errore

Vedi `docs/API.md` per la documentazione completa dei codici di errore e degli endpoint API.
