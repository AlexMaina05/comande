---
layout: default
title: API Documentation
permalink: /api
---

# API Documentation - RICEVUTE

Questa documentazione descrive gli endpoint API del sistema RICEVUTE, il formato uniforme delle risposte JSON, e le regole di validazione input implementate.

**Riferimento:** Issue #5 - [https://github.com/AlexMaina05/RICEVUTE/issues/5](https://github.com/AlexMaina05/RICEVUTE/issues/5)

## Formato Uniforme delle Risposte API

Tutte le API del sistema RICEVUTE utilizzano un formato JSON standardizzato per garantire coerenza e facilità di integrazione.

### Schema della Risposta

Ogni risposta JSON contiene sempre i seguenti campi:

```json
{
  "success": true|false,
  "data": {...},        // presente solo se success=true
  "error": {            // presente solo se success=false
    "code": 1001,
    "message": "Descrizione errore",
    "details": [...]    // opzionale, dettagli aggiuntivi
  }
}
```

### Helper per Implementare le Risposte

Il sistema fornisce helper sia procedurali che a oggetti per implementare risposte uniformi (definiti in `api/response.php`):

#### Funzioni Procedurali (Issue #6)

```php
// Includi gli helper
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/error_handler.php';

// Risposta di successo
json_response(true, ['result' => 'ok'], null, 200);

// Risposta di errore
json_response(false, null, api_error(1001, 'Input non valido'), 400);

// Helper per creare array di errore
$error = api_error(1001, 'Messaggio di errore');
// Restituisce: ['code' => 1001, 'message' => 'Messaggio di errore']
```

#### Classe ApiResponse (Metodo Precedente)

```php
// Risposta di successo
ApiResponse::sendSuccess(['result' => 'ok'], 200);

// Risposta di errore
ApiResponse::sendError('Input non valido', 1001, 400);

// Risposta di errore con dettagli
ApiResponse::sendError('Validazione fallita', 1002, 400, ['field' => 'required']);
```

### Gestione Centralizzata degli Errori

Il file `api/error_handler.php` imposta handler globali per gestire in modo uniforme:

- **Eccezioni non gestite**: convertite in risposte JSON con status 500
- **Errori PHP** (warning, notice): convertiti in eccezioni e gestiti uniformemente
- **Fatal errors**: catturati tramite shutdown function e restituiti come JSON

Gli handler sono configurati automaticamente quando si include `error_handler.php` in un endpoint API.

### Esempio Risposta di Successo

```json
{
  "success": true,
  "data": {
    "ID_Prodotto": "1",
    "Descrizione": "Pizza Margherita",
    "Prezzo": 8.50
  }
}
```

### Esempio Risposta di Errore

```json
{
  "success": false,
  "error": {
    "code": 1004,
    "message": "Codice prodotto non fornito",
    "details": ["Campo 'codice' mancante o vuoto"]
  }
}
```

## Codici di Errore

I codici di errore sono suddivisi per categoria:

- **1000-1999**: Errori di validazione input
- **2000-2999**: Errori interni del server
- **3000-3999**: Errori di connessione e infrastruttura

### Tabella Codici di Errore

| Codice | Descrizione |
|--------|-------------|
| 1001   | Data non fornita (gestisci_dati.php) |
| 1002   | Data troppo lunga (gestisci_dati.php) |
| 1003   | Formato data non valido (gestisci_dati.php) |
| 1004   | Codice prodotto non fornito (cerca_prodotto.php) |
| 1005   | Codice prodotto deve essere una stringa (cerca_prodotto.php) |
| 1006   | Codice prodotto troppo lungo (cerca_prodotto.php) |
| 1007   | Codice prodotto vuoto (cerca_prodotto.php) |
| 1008   | Prodotto non trovato (cerca_prodotto.php) |
| 1009   | JSON non valido o vuoto (salva_ordine.php) |
| 1010   | Dati ordine non validi o mancanti (salva_ordine.php) |
| 1011   | Dettagli ordine devono essere un array non vuoto (salva_ordine.php) |
| 1012-1022 | Vari errori di validazione ordine (salva_ordine.php) |
| 1023   | JSON non valido o vuoto (ripeti_comanda.php) |
| 1024   | ID comanda mancante (ripeti_comanda.php) |
| 1025   | ID comanda non intero (ripeti_comanda.php) |
| 1026   | ID comanda non positivo (ripeti_comanda.php) |
| 1027   | Comanda non trovata (ripeti_comanda.php) |
| 1028   | Data non fornita (genera_report.php) |
| 1029   | Formato data non valido (genera_report.php) |
| 1030   | Staff deve essere un valore booleano (salva_ordine.php) |
| 2001   | Errore database generazione report (gestisci_dati.php) |
| 2002   | Errore database ricerca prodotto (cerca_prodotto.php) |
| 2003   | Errore database salvataggio ordine (salva_ordine.php) |
| 2004-2009 | Errori server ristampa comanda (ripeti_comanda.php) |
| 2010   | Errore database generazione report (genera_report.php) |
| 3001   | Errore connessione database |

## Validazione Input

Tutti gli endpoint API implementano validazione server-side tramite la classe `InputValidator` per garantire:
- Coerenza dei dati nel database
- Risposte JSON uniformi
- Gestione errori con codici HTTP appropriati (principalmente 400 per input non validi)

### Formato Risposte di Errore (Legacy)

Per retrocompatibilità, alcune funzioni utilizzano ancora il vecchio formato:

```json
{
  "error": "Messaggio di errore principale",
  "details": ["Lista opzionale di dettagli errore"]
}
```

Questo formato è deprecato e verrà sostituito dal formato standardizzato.

---

## Endpoints API

### 1. GET /api/gestisci_dati.php

Restituisce il riepilogo dei dati per una data specifica (report giornaliero).

#### Parametri Query String

| Parametro | Tipo   | Obbligatorio | Descrizione                    |
|-----------|--------|--------------|--------------------------------|
| `data`    | string | Sì           | Data nel formato YYYY-MM-DD    |

#### Regole di Validazione

- **data** (obbligatorio):
  - Deve essere presente e non vuoto
  - Lunghezza massima: 10 caratteri
  - Formato: YYYY-MM-DD (es. 2024-01-15)
  - Deve essere una data valida

#### Esempio Richiesta Valida

```bash
GET /api/gestisci_dati.php?data=2024-01-15
```

#### Esempio Risposta Successo (200 OK)

**Nuovo formato standardizzato:**
```json
{
  "success": true,
  "data": {
    "riepilogo_servizio": [
      {
        "Tipo_Servizio": "SALA",
        "Incasso_Parziale": "450.50",
        "Coperti_Parziali": "25"
      },
      {
        "Tipo_Servizio": "ASPORTO",
        "Incasso_Parziale": "125.00",
        "Coperti_Parziali": "8"
      }
    ],
    "dettaglio_prodotti": [
      {
        "Descrizione": "Pizza Margherita",
        "Totale_Venduto": "15"
      }
    ]
  }
}
```

#### Esempi Risposte di Errore

**Data mancante (400):**
```json
{
  "success": false,
  "error": {
    "code": 1001,
    "message": "Data non fornita",
    "details": ["Campo 'data' mancante o vuoto"]
  }
}
```

**Formato data non valido (400):**
```json
{
  "success": false,
  "error": {
    "code": 1003,
    "message": "Formato data non valido. Usa YYYY-MM-DD"
  }
}
```

---

### 2. GET /api/cerca_prodotto.php

Cerca un prodotto tramite il suo codice.

#### Parametri Query String

| Parametro | Tipo   | Obbligatorio | Descrizione          |
|-----------|--------|--------------|----------------------|
| `codice`  | string | Sì           | Codice del prodotto  |

#### Regole di Validazione

- **codice** (obbligatorio):
  - Deve essere presente e non vuoto
  - Deve essere una stringa
  - Lunghezza massima: 64 caratteri
  - Non può essere una stringa vuota dopo trim

#### Esempio Richiesta Valida

```bash
GET /api/cerca_prodotto.php?codice=PIZZA001
```

#### Esempio Risposta Successo (200 OK)

**Nuovo formato standardizzato:**
```json
{
  "success": true,
  "data": {
    "ID_Prodotto": "1",
    "Descrizione": "Pizza Margherita",
    "Prezzo": 8.50
  }
}
```

#### Esempi Risposte di Errore

**Codice mancante (400):**
```json
{
  "success": false,
  "error": {
    "code": 1004,
    "message": "Codice prodotto non fornito",
    "details": ["Campo 'codice' mancante o vuoto"]
  }
}
```

**Codice troppo lungo (400):**
```json
{
  "success": false,
  "error": {
    "code": 1006,
    "message": "Codice prodotto troppo lungo (max 64 caratteri)"
  }
}
```

**Prodotto non trovato (404):**
```json
{
  "success": false,
  "error": {
    "code": 1008,
    "message": "Prodotto non trovato."
  }
}
```

---

### 3. POST /api/salva_ordine.php

Salva un nuovo ordine e genera comande per i reparti.

#### Body Request (JSON)

```json
{
  "nome_cliente": "Mario Rossi",
  "id_tavolo": 5,
  "numero_coperti": 4,
  "totale": 45.50,
  "staff": false,
  "dettagli": [
    {
      "id_prodotto": 1,
      "quantita": 2,
      "prezzo_unitario": 8.50,
      "descrizione": "Pizza Margherita"
    },
    {
      "id_prodotto": 5,
      "quantita": 4,
      "prezzo_unitario": 3.00,
      "descrizione": "Acqua Naturale"
    }
  ]
}
```

#### Regole di Validazione

- **JSON body**:
  - Deve essere un JSON valido
  - Non può essere vuoto

- **dettagli** (obbligatorio):
  - Deve essere presente
  - Deve essere un array non vuoto

- **nome_cliente** (opzionale, default: "Cliente"):
  - Lunghezza massima: 100 caratteri

- **id_tavolo** (opzionale):
  - Se presente, deve essere un intero

- **numero_coperti** (opzionale, default: 0):
  - Deve essere un intero
  - Range: 0 - 999

- **totale** (opzionale, default: 0.00):
  - Deve essere un numero (int o float)
  - Range: 0 - 999999.99
  - Se `staff` è `true`, il totale viene forzato a 0.00

- **staff** (opzionale, default: false):
  - Deve essere un valore booleano
  - Se `true`, l'ordine viene marcato come ordine staff:
    - Il totale viene automaticamente impostato a 0.00
    - L'ordine non viene incluso nei report giornalieri

- **dettagli[]** (ogni elemento dell'array):
  - **id_prodotto** (obbligatorio):
    - Deve essere un intero
  - **quantita** (obbligatorio):
    - Deve essere un intero
    - Range: 1 - 9999
  - **prezzo_unitario** (obbligatorio):
    - Deve essere un numero (int o float)
    - Range: 0 - 99999.99

#### Esempio Risposta Successo (200 OK)

**Nuovo formato standardizzato:**
```json
{
  "success": true,
  "data": {
    "message": "Ordine #123 salvato con successo!",
    "order_id": 123
  }
}
```

#### Esempi Risposte di Errore

**JSON non valido (400):**
```json
{
  "success": false,
  "error": {
    "code": 1009,
    "message": "JSON non valido o vuoto"
  }
}
```

**Dettagli mancanti (400):**
```json
{
  "success": false,
  "error": {
    "code": 1010,
    "message": "Dati dell'ordine non validi o mancanti",
    "details": ["Campo 'dettagli' mancante o vuoto"]
  }
}
```

**Dettagli vuoti (400):**
```json
{
  "success": false,
  "error": {
    "code": 1011,
    "message": "Dettagli ordine devono essere un array non vuoto"
  }
}
```

**Totale negativo (400):**
```json
{
  "success": false,
  "error": {
    "code": 1017,
    "message": "Totale ordine deve essere tra 0 e 999999.99"
  }
}
```

**Dettaglio con quantità negativa (400):**
```json
{
  "success": false,
  "error": {
    "code": 1020,
    "message": "Dettaglio[0]: quantita deve essere tra 1 e 9999"
  }
}
```

---

### 4. POST /api/ripeti_comanda.php

Ristampa una comanda esistente.

#### Body Request (JSON)

```json
{
  "id_comanda": 123
}
```

#### Regole di Validazione

- **JSON body**:
  - Deve essere un JSON valido
  - Non può essere vuoto

- **id_comanda** (obbligatorio):
  - Deve essere presente
  - Deve essere un intero
  - Range: 1 - 999999999 (deve essere positivo)

#### Esempio Risposta Successo (200 OK)

**Nuovo formato standardizzato:**
```json
{
  "success": true,
  "data": {
    "message": "Comanda ristampata con successo"
  }
}
```

#### Esempi Risposte di Errore

**JSON non valido (400):**
```json
{
  "success": false,
  "error": {
    "code": 1023,
    "message": "JSON non valido o vuoto"
  }
}
```

**ID comanda mancante (400):**
```json
{
  "success": false,
  "error": {
    "code": 1024,
    "message": "ID comanda mancante",
    "details": ["Campo 'id_comanda' mancante o vuoto"]
  }
}
```

**ID comanda non intero (400):**
```json
{
  "success": false,
  "error": {
    "code": 1025,
    "message": "ID comanda deve essere un intero"
  }
}
```

**ID comanda non positivo (400):**
```json
{
  "success": false,
  "error": {
    "code": 1026,
    "message": "ID comanda deve essere un intero positivo valido"
  }
}
```

**Comanda non trovata (404):**
```json
{
  "success": false,
  "error": {
    "code": 1027,
    "message": "Comanda non trovata"
  }
}
```

---

## Testing

Sono disponibili script di test per verificare il formato uniforme delle risposte API:

### Test Automatizzato Completo

```bash
./tools/tests/api_responses_test.sh http://localhost/RICEVUTE
```

Script completo che esegue una serie di richieste curl con payload validi e non validi per verificare:
- Presenza campo `success` in tutte le risposte
- Struttura corretta delle risposte di successo (campo `data`)
- Struttura corretta delle risposte di errore (campo `error` con `code` e `message`)
- Codici HTTP appropriati (200, 400, 404, 500)

### Test Smoke Manuale (Issue #6)

```bash
./tools/tests/test_api_responses.sh http://localhost/RICEVUTE
```

Script di smoke test che verifica visivamente:
- Schema di risposta uniforme { success, data, error }
- HTTP status codes corretti per varie condizioni
- Gestione errori appropriata per ogni endpoint

### Test Unitario Helper

```bash
php -d error_reporting=0 tools/tests/test_api_response.php
```

Test unitario per verificare il funzionamento degli helper `ApiResponse` e delle funzioni `json_response()` e `api_error()`.

### Test Manuali con curl

Esempi di test manuali con curl:

**Test successo:**
```bash
curl -i "http://localhost/RICEVUTE/api/cerca_prodotto.php?codice=TEST"
```

**Test errore validazione:**
```bash
curl -i "http://localhost/RICEVUTE/api/cerca_prodotto.php"
```

**Test errore 404:**
```bash
curl -i "http://localhost/RICEVUTE/api/cerca_prodotto.php?codice=INESISTENTE"
```

**Test POST con JSON:**
```bash
curl -i -X POST -H "Content-Type: application/json" \
  -d '{}' \
  "http://localhost/RICEVUTE/api/salva_ordine.php"
```

---

## Codici HTTP di Risposta

| Codice | Significato           | Uso                                                    |
|--------|-----------------------|--------------------------------------------------------|
| 200    | OK                    | Richiesta elaborata con successo                       |
| 400    | Bad Request           | Input non valido (errori di validazione)               |
| 404    | Not Found             | Risorsa non trovata (es. prodotto o comanda inesistente) |
| 500    | Internal Server Error | Errore del server (es. errore database)                |

---

## Implementazione

La validazione è implementata tramite la classe utility `InputValidator` (`src/Utils/InputValidator.php`) che fornisce:

- `require_fields()` - Verifica presenza campi obbligatori
- `validate_type()` - Valida tipi di dato (int, float, string, email, date)
- `validate_length()` - Valida lunghezza stringhe
- `validate_range()` - Valida range numerici
- `sanitize_for_html()` - Sanifica output HTML
- `json_error()` - Genera risposte JSON di errore uniformi

Per maggiori dettagli sull'implementazione, consultare il codice sorgente della classe e i commenti inline.

---

## Sicurezza

- Tutti i parametri di input vengono validati server-side
- Tutte le query database utilizzano prepared statements PDO per prevenire SQL injection
- I dati vengono sanificati con `htmlspecialchars()` prima dell'output HTML quando necessario
- Gli errori del database vengono loggati server-side e non esposti al client

---

*Ultimo aggiornamento: 2024 - Issue #5*
