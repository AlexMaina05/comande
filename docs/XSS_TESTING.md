# Test di Sicurezza XSS - Issue #7

## Obiettivo
Verificare che le pagine admin.php e report.php siano protette contro attacchi XSS (Cross-Site Scripting).

## Protezioni Implementate

### 1. Content Security Policy (CSP)
- **admin.php**: CSP in modalità report-only
- **report.php**: CSP in modalità report-only con supporto per CDN html2pdf.js

### 2. Escaping Client-Side
- **admin.js**: Utilizza `textContent` per inserire dati nel DOM (già sicuro)
- **report.js**: Utilizza la funzione `escapeHtml()` per tutti i dati provenienti dal DB

### 3. Escaping Server-Side
- **admin.php**: Utilizza `htmlspecialchars()` per variabili PHP
- **report.php**: Utilizza `htmlspecialchars()` per variabili PHP

## Test Manuali

### Test 1: XSS tramite Descrizione Prodotto in Report

**Setup:**
1. Inserire nel database un prodotto con descrizione contenente script:
   ```sql
   INSERT INTO PRODOTTI (Codice_Prodotto, Descrizione, Prezzo, ID_Categoria, ID_Reparto) 
   VALUES ('XSS01', '<script>alert("XSS")</script>', 10.00, 1, 1);
   ```

2. Creare un ordine contenente quel prodotto
   ```sql
   INSERT INTO ORDINI (ID_Tavolo, Data_Ora, Totale_Ordine, Numero_Coperti) 
   VALUES (1, NOW(), 10.00, 1);
   
   INSERT INTO DETTAGLI_ORDINE (ID_Ordine, ID_Prodotto, Quantita, Prezzo_Unitario) 
   VALUES (LAST_INSERT_ID(), (SELECT ID_Prodotto FROM PRODOTTI WHERE Codice_Prodotto = 'XSS01'), 1, 10.00);
   ```

**Esecuzione Test:**
1. Aprire report.php nel browser
2. Selezionare la data odierna
3. Cliccare "Genera Report"

**Risultato Atteso:**
- Lo script NON deve essere eseguito
- Il testo `<script>alert("XSS")</script>` deve apparire come testo normale nella tabella
- Nella console del browser NON deve apparire alcun alert

**Risultato se NON protetto:**
- Apparirebbe un alert popup con il messaggio "XSS"

### Test 2: XSS tramite Descrizione Prodotto in Admin

**Setup:**
Utilizzare lo stesso prodotto creato nel Test 1.

**Esecuzione Test:**
1. Aprire admin.php nel browser (dopo login)
2. Navigare alla tab "Prodotti Menu"
3. Verificare che la lista dei prodotti sia caricata

**Risultato Atteso:**
- Lo script NON deve essere eseguito
- Il testo `<script>alert("XSS")</script>` deve apparire come testo normale nella colonna Descrizione
- Nella console del browser NON deve apparire alcun alert

### Test 3: Verifica CSP Headers

**Esecuzione Test:**
1. Aprire admin.php o report.php nel browser
2. Aprire Developer Tools (F12)
3. Navigare alla tab "Network"
4. Ricaricare la pagina
5. Cliccare sulla richiesta alla pagina principale
6. Verificare gli headers nella sezione "Response Headers"

**Risultato Atteso:**
- Deve essere presente l'header: `Content-Security-Policy-Report-Only`
- Il valore deve corrispondere alla policy impostata

### Test 4: XSS tramite URL Parameters

**Esecuzione Test:**
1. Tentare di iniettare script tramite parametri URL:
   ```
   report.php?test=<script>alert('XSS')</script>
   ```

**Risultato Atteso:**
- Lo script NON deve essere eseguito
- La pagina deve funzionare normalmente o mostrare un errore di validazione

## Cleanup

Dopo i test, rimuovere i dati di test dal database:
```sql
DELETE FROM DETTAGLI_ORDINE WHERE ID_Prodotto IN (SELECT ID_Prodotto FROM PRODOTTI WHERE Codice_Prodotto = 'XSS01');
DELETE FROM ORDINI WHERE ID_Ordine NOT IN (SELECT DISTINCT ID_Ordine FROM DETTAGLI_ORDINE);
DELETE FROM PRODOTTI WHERE Codice_Prodotto = 'XSS01';
```

## Note sulla Sicurezza

- Le protezioni XSS sono efficaci solo se applicate consistentemente a TUTTI i punti di output
- La CSP in modalità report-only NON blocca gli attacchi ma aiuta a identificarli
- In produzione, considerare di passare a CSP in modalità enforcement (rimuovere `-Report-Only`)
- Validare e sanitizzare SEMPRE gli input lato server prima di salvarli nel database
