---
layout: default
title: Guida Test Stampa e Comande
permalink: /docs/print-testing
---

# GUIDA AL TEST DELLA STAMPA E GESTIONE COMANDE

Questa guida fornisce procedure di test manuali per verificare il comportamento della stampa CUPS in diversi ambienti e scenari.

## Indice
1. [Prerequisiti](#prerequisiti)
2. [Test Ambiente: Verifica Disponibilità Componenti](#test-ambiente)
3. [Test Scenario A: lp Disponibile](#test-scenario-a)
4. [Test Scenario B: lp Non Disponibile](#test-scenario-b)
5. [Test Scenario C: Funzioni Shell Disabilitate](#test-scenario-c)
6. [Test Worker Procesamento Comande](#test-worker)
7. [Test Riprocessamento Comande Pendenti](#test-riprocessamento)
8. [Test End-to-End Completo](#test-e2e)
9. [Checklist Validazione](#checklist)

---

## 1. Prerequisiti {#prerequisiti}

Prima di iniziare i test, assicurati di avere:

- [ ] Accesso SSH al server/NAS
- [ ] Accesso al database MySQL/MariaDB
- [ ] Client MySQL (mysql-client o equivalente)
- [ ] Editor di testo (vim/nano)
- [ ] Permessi di esecuzione script PHP
- [ ] (Opzionale) CUPS installato e configurato
- [ ] (Opzionale) Stampante di test configurata in CUPS

### Strumenti necessari

```bash
# Verifica strumenti installati
which php
which mysql
which curl
which jq  # Opzionale per parsing JSON

# Verifica versione PHP
php --version

# Verifica connessione DB
mysql -u user -p -e "SELECT 1;"
```

---

## 2. Test Ambiente: Verifica Disponibilità Componenti {#test-ambiente}

### 2.1 Verifica comando lp

```bash
# Test 1: Verifica se lp è installato
command -v lp
# Output atteso se disponibile: /usr/bin/lp
# Output atteso se NON disponibile: (nessun output)

# Test 2: Verifica versione CUPS
lpstat -r 2>/dev/null && echo "CUPS running" || echo "CUPS not running"

# Test 3: Lista stampanti configurate
lpstat -p -d
# Output atteso: lista di stampanti disponibili
```

**Risultato atteso:**
- Se `lp` presente: procedere con Test Scenario A
- Se `lp` assente: procedere con Test Scenario B

### 2.2 Verifica funzioni PHP shell

Crea script di test: `/tmp/test_php_shell.php`

```php
<?php
/**
 * Script di test per verificare disponibilità funzioni shell in PHP
 */

echo "=== Test Funzioni PHP Shell ===\n\n";

// Test 1: Verifica function_exists
echo "1. Verifica function_exists:\n";
echo "   exec(): " . (function_exists('exec') ? '✓ Disponibile' : '✗ NON disponibile') . "\n";
echo "   shell_exec(): " . (function_exists('shell_exec') ? '✓ Disponibile' : '✗ NON disponibile') . "\n\n";

// Test 2: Verifica se sono davvero eseguibili
echo "2. Test esecuzione:\n";
if (function_exists('exec')) {
    $output = [];
    @exec('echo "test exec"', $output);
    echo "   exec output: " . (empty($output) ? '✗ Bloccata' : '✓ Funzionante - ' . implode('', $output)) . "\n";
}

if (function_exists('shell_exec')) {
    $output = @shell_exec('echo "test shell_exec"');
    echo "   shell_exec output: " . ($output === null ? '✗ Bloccata' : '✓ Funzionante - ' . trim($output)) . "\n";
}

echo "\n3. Test rilevamento lp:\n";
if (function_exists('shell_exec')) {
    $lpPath = trim(@shell_exec('command -v lp 2>/dev/null') ?: '');
    echo "   Percorso lp: " . ($lpPath === '' ? '✗ NON trovato' : '✓ ' . $lpPath) . "\n";
} else if (function_exists('exec')) {
    $output = [];
    @exec('command -v lp 2>/dev/null', $output);
    $lpPath = trim(implode("\n", $output));
    echo "   Percorso lp: " . ($lpPath === '' ? '✗ NON trovato' : '✓ ' . $lpPath) . "\n";
} else {
    echo "   ✗ Impossibile rilevare lp (nessuna funzione shell disponibile)\n";
}

echo "\n4. Informazioni ambiente:\n";
echo "   PHP SAPI: " . php_sapi_name() . "\n";
echo "   Utente: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'N/A') . "\n";
echo "   disable_functions: " . (ini_get('disable_functions') ?: '(nessuna)') . "\n";

echo "\n=== Fine Test ===\n";
?>
```

Esegui il test:

```bash
# Test da CLI (PHP CLI)
php /tmp/test_php_shell.php

# Test da Web (PHP-FPM/Apache)
# Copia lo script nella webroot e accedi via browser
cp /tmp/test_php_shell.php /var/www/html/test_shell.php
curl http://localhost/test_shell.php
# Rimuovi dopo il test per sicurezza
rm /var/www/html/test_shell.php
```

**Documenta i risultati:**
- [ ] CLI - exec disponibile: SI / NO
- [ ] CLI - shell_exec disponibile: SI / NO
- [ ] CLI - lp rilevato: SI / NO
- [ ] Web - exec disponibile: SI / NO
- [ ] Web - shell_exec disponibile: SI / NO
- [ ] Web - lp rilevato: SI / NO

### 2.3 Verifica directory temporanea e permessi

```bash
# Test 1: Percorso directory temp
php -r "echo 'Temp dir: ' . sys_get_temp_dir() . PHP_EOL;"
# Output atteso: /tmp o simile

# Test 2: Verifica permessi directory
ls -ld $(php -r "echo sys_get_temp_dir();")
# Output atteso: drwxrwxrwt (sticky bit settato)

# Test 3: Test creazione file temporaneo
php -r "
\$temp = tempnam(sys_get_temp_dir(), 'test_');
if (\$temp) {
    echo 'File creato: ' . \$temp . PHP_EOL;
    file_put_contents(\$temp, 'test content');
    echo 'Contenuto scritto: ' . (file_get_contents(\$temp) === 'test content' ? 'OK' : 'FAIL') . PHP_EOL;
    unlink(\$temp);
    echo 'File rimosso: OK' . PHP_EOL;
} else {
    echo 'ERRORE: Impossibile creare file temporaneo' . PHP_EOL;
}
"

# Test 4: Spazio disco disponibile
df -h $(php -r "echo sys_get_temp_dir();")
```

**Documenta i risultati:**
- [ ] Directory temp: _______________
- [ ] Permessi corretti: SI / NO
- [ ] Creazione file temp: OK / FAIL
- [ ] Spazio disponibile: _____ MB/GB

---

## 3. Test Scenario A: lp Disponibile {#test-scenario-a}

**Precondizioni:**
- CUPS installato
- Comando `lp` disponibile
- Funzioni shell PHP abilitate
- Almeno una stampante configurata

### 3.1 Test stampa manuale con lp

```bash
# Test 1: Creazione file di test
echo "=== TEST STAMPA ===" > /tmp/test_print.txt
echo "Data: $(date)" >> /tmp/test_print.txt
echo "Ordine: #12345" >> /tmp/test_print.txt
echo "==================" >> /tmp/test_print.txt

# Test 2: Lista stampanti disponibili
lpstat -p -d

# Test 3: Stampa su stampante specifica
# Sostituisci 'nome_stampante' con il nome della tua stampante
lp -d nome_stampante /tmp/test_print.txt

# Test 4: Verifica job in coda
lpstat -o

# Test 5: Pulizia
rm /tmp/test_print.txt
```

**Documenta i risultati:**
- [ ] Stampante utilizzata: _______________
- [ ] Job ID: _______________
- [ ] Stampa completata: SI / NO
- [ ] Errori rilevati: _______________

### 3.2 Test API salva_ordine.php con stampa

```bash
# Prepara dati di test
cat > /tmp/test_order.json << 'EOF'
{
  "nome_cliente": "Test Stampa",
  "id_tavolo": 1,
  "numero_coperti": 2,
  "totale": 15.50,
  "sconto": 0.00,
  "staff": false,
  "dettagli": [
    {
      "id_prodotto": 1,
      "quantita": 2,
      "prezzo_unitario": 5.00,
      "descrizione": "Pizza Margherita"
    },
    {
      "id_prodotto": 2,
      "quantita": 1,
      "prezzo_unitario": 5.50,
      "descrizione": "Coca Cola"
    }
  ]
}
EOF

# Esegui richiesta
curl -X POST http://localhost/api/salva_ordine.php \
  -H 'Content-Type: application/json' \
  -d @/tmp/test_order.json \
  | jq

# Cleanup
rm /tmp/test_order.json
```

**Verifica risposta:**
```json
{
  "success": true,
  "data": {
    "message": "Ordine #... salvato con successo!",
    "order_id": 123,
    "print_status": {
      "sent": X,
      "pending": Y,
      "error": Z
    }
  }
}
```

### 3.3 Verifica comande in database

```sql
-- Query 1: Verifica ultima comanda creata
SELECT ID_Comanda, ID_Ordine, Nome_Stampante_LAN, Stato, Tentativi, Error_Message, Data_Creazione
FROM COMANDE
ORDER BY Data_Creazione DESC
LIMIT 5;

-- Query 2: Conta comande per stato
SELECT Stato, COUNT(*) as Totale
FROM COMANDE
WHERE Data_Creazione >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY Stato;
```

**Documenta i risultati:**
- [ ] Comande create: ___
- [ ] Comande in stato 'sent': ___
- [ ] Comande in stato 'pending': ___
- [ ] Comande in stato 'error': ___
- [ ] Messaggi di errore (se presenti): _______________

---

## 4. Test Scenario B: lp Non Disponibile {#test-scenario-b}

**Precondizioni:**
- CUPS NON installato o `lp` non nel PATH
- Funzioni shell PHP abilitate

### 4.1 Simula assenza di lp

```bash
# Opzione 1: Rinomina temporaneamente lp (richiede root)
sudo mv /usr/bin/lp /usr/bin/lp.backup

# Opzione 2: Modifica PATH per nascondere lp (non-root)
export PATH=/usr/local/bin:/usr/bin:/bin
# Rimuovi /usr/bin se lp è lì

# Verifica che lp non sia più trovabile
command -v lp
# Output atteso: (nessun output)
```

### 4.2 Test comportamento API senza lp

```bash
# Invia ordine di test
curl -X POST http://localhost/api/salva_ordine.php \
  -H 'Content-Type: application/json' \
  -d '{
    "nome_cliente": "Test No LP",
    "id_tavolo": 1,
    "numero_coperti": 1,
    "totale": 10.00,
    "dettagli": [{"id_prodotto": 1, "quantita": 1, "prezzo_unitario": 10.00, "descrizione": "Test"}]
  }' | jq
```

### 4.3 Verifica log PHP

```bash
# Cerca messaggi di log relativi a lp
sudo tail -f /var/log/php-fpm/error.log | grep -i "lp\|stampa\|cups"

# Oppure log Apache
sudo tail -f /var/log/apache2/error.log | grep -i "lp\|stampa\|cups"
```

**Output atteso:**
```
[...] Stampa CUPS: comando 'lp' non disponibile. Impossibile stampare ricevuta immediata.
```

### 4.4 Verifica stato comande in DB

```sql
-- Tutte le comande dovrebbero essere in 'pending'
SELECT ID_Comanda, Stato, Error_Message
FROM COMANDE
WHERE Data_Creazione >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
ORDER BY Data_Creazione DESC;
```

**Risultato atteso:**
- Tutte le comande in stato 'pending'
- Nessuna comanda in stato 'sent'
- Error_Message può essere NULL o "lp non disponibile" (se processate dal worker)

### 4.5 Ripristina lp

```bash
# Se hai rinominato lp
sudo mv /usr/bin/lp.backup /usr/bin/lp

# Verifica ripristino
command -v lp
```

**Documenta i risultati:**
- [ ] Log errore rilevato: SI / NO
- [ ] Comande rimaste in pending: SI / NO
- [ ] API ha restituito successo comunque: SI / NO

---

## 5. Test Scenario C: Funzioni Shell Disabilitate {#test-scenario-c}

**Precondizioni:**
- Accesso a php.ini
- Possibilità di riavviare PHP-FPM/Apache

### 5.1 Disabilita funzioni shell (Web PHP)

```bash
# Backup configurazione attuale
sudo cp /etc/php/8.1/fpm/php.ini /etc/php/8.1/fpm/php.ini.backup

# Modifica php.ini per PHP-FPM
sudo nano /etc/php/8.1/fpm/php.ini

# Trova e modifica la riga:
# disable_functions = 
# In:
# disable_functions = exec,shell_exec,system,passthru

# Riavvia PHP-FPM
sudo systemctl restart php8.1-fpm

# Oppure Apache (se mod_php)
sudo systemctl restart apache2
```

### 5.2 Verifica disabilitazione

```bash
# Test rapido
php -r "echo function_exists('exec') ? 'exec OK' : 'exec DISABLED';" 

# Test via web
curl http://localhost/test_shell.php
```

### 5.3 Test API con shell disabilitate

```bash
# Invia ordine
curl -X POST http://localhost/api/salva_ordine.php \
  -H 'Content-Type: application/json' \
  -d '{
    "nome_cliente": "Test No Shell",
    "id_tavolo": 1,
    "numero_coperti": 1,
    "totale": 10.00,
    "dettagli": [{"id_prodotto": 1, "quantita": 1, "prezzo_unitario": 10.00, "descrizione": "Test"}]
  }' | jq
```

### 5.4 Verifica comportamento

```sql
-- Verifica comande create
SELECT ID_Comanda, Stato, Data_Creazione
FROM COMANDE
WHERE Data_Creazione >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
ORDER BY Data_Creazione DESC;
```

**Risultato atteso:**
- Comande create in stato 'pending'
- API risponde con successo
- Nessun tentativo di stampa immediata

### 5.5 Test worker CLI (dovrebbe funzionare)

```bash
# PHP CLI usa php.ini separato
php --ini | grep "Loaded Configuration"

# Verifica che CLI NON abbia disable_functions
php -r "echo function_exists('exec') ? 'CLI: exec OK' : 'CLI: exec DISABLED';"

# Esegui worker
php scripts/worker_process_comande.php --limit=5
```

**Risultato atteso:**
- Worker CLI può eseguire funzioni shell
- Worker processa le comande pending
- Comande passano a stato 'sent' o 'error'

### 5.6 Ripristina configurazione

```bash
# Ripristina php.ini
sudo mv /etc/php/8.1/fpm/php.ini.backup /etc/php/8.1/fpm/php.ini
sudo systemctl restart php8.1-fpm
```

**Documenta i risultati:**
- [ ] Web PHP: funzioni shell disabilitate correttamente: SI / NO
- [ ] CLI PHP: funzioni shell ancora disponibili: SI / NO
- [ ] Worker ha processato comande: SI / NO
- [ ] Comande aggiornate a 'sent': SI / NO

---

## 6. Test Worker Procesamento Comande {#test-worker}

### 6.1 Crea comande di test manualmente

```sql
-- Inserisci comande di test direttamente in DB
INSERT INTO COMANDE (ID_Ordine, Nome_Stampante_LAN, Testo_Comanda, Stato, Tentativi)
VALUES
  (1, 'cucina', 'TEST COMANDA 1\nOrdine: #999\nTavolo: Test\n', 'pending', 0),
  (1, 'bar', 'TEST COMANDA 2\nOrdine: #999\nTavolo: Test\n', 'pending', 0),
  (1, 'pizzeria', 'TEST COMANDA 3\nOrdine: #999\nTavolo: Test\n', 'pending', 0);

-- Verifica inserimento
SELECT ID_Comanda, Nome_Stampante_LAN, Stato FROM COMANDE ORDER BY ID_Comanda DESC LIMIT 3;
```

### 6.2 Esegui worker con output dettagliato

```bash
# Esecuzione con parametri di test
php scripts/worker_process_comande.php --limit=10 --max-tries=3 --sleep-ms=500

# Output atteso:
# [2025-10-24 12:00:00] [hostname:12345] Processing comanda 42 -> printer 'cucina'
# [2025-10-24 12:00:01] [hostname:12345] Comanda 42 inviata con successo.
# [2025-10-24 12:00:01] [hostname:12345] Processing comanda 43 -> printer 'bar'
# [2025-10-24 12:00:02] [hostname:12345] Comanda 43 inviata con successo.
# [2025-10-24 12:00:02] [hostname:12345] Fine batch, processate 3 comande.
```

### 6.3 Verifica aggiornamenti in DB

```sql
-- Verifica stato finale comande
SELECT ID_Comanda, Nome_Stampante_LAN, Stato, Tentativi, Error_Message, Data_Invio
FROM COMANDE
WHERE ID_Ordine = 1
ORDER BY ID_Comanda DESC;
```

**Documenta i risultati:**
- [ ] Worker completato senza errori: SI / NO
- [ ] Comande processate: ___
- [ ] Comande in stato 'sent': ___
- [ ] Comande in stato 'error': ___
- [ ] Tentativi incrementati: SI / NO
- [ ] Data_Invio popolato per 'sent': SI / NO

---

## 7. Test Riprocessamento Comande Pendenti {#test-riprocessamento}

### 7.1 Crea comande in vari stati

```sql
-- Setup: comande in stati diversi
INSERT INTO COMANDE (ID_Ordine, Nome_Stampante_LAN, Testo_Comanda, Stato, Tentativi, Error_Message)
VALUES
  (1, 'test1', 'Comanda pending', 'pending', 0, NULL),
  (1, 'test2', 'Comanda error 1 tentativo', 'error', 1, 'Stampante offline'),
  (1, 'test3', 'Comanda error 3 tentativi', 'error', 3, 'Stampante offline'),
  (1, 'test4', 'Comanda error max tentativi', 'error', 5, 'Max retry raggiunto'),
  (1, 'test5', 'Comanda già inviata', 'sent', 1, NULL);

-- Verifica
SELECT ID_Comanda, Stato, Tentativi FROM COMANDE ORDER BY ID_Comanda DESC LIMIT 5;
```

### 7.2 Test worker con max-tries

```bash
# Worker con max-tries=5
php scripts/worker_process_comande.php --limit=10 --max-tries=5
```

**Comportamento atteso:**
- Comanda pending (0 tentativi): PROCESSATA
- Comanda error (1 tentativo): PROCESSATA
- Comanda error (3 tentativi): PROCESSATA
- Comanda error (5 tentativi): NON PROCESSATA (max raggiunto)
- Comanda sent: NON PROCESSATA (già inviata)

### 7.3 Reset manuale tentativi

```sql
-- Reset tentativi per comande che hanno raggiunto il max
UPDATE COMANDE
SET Tentativi = 0, Stato = 'pending', Error_Message = NULL
WHERE Stato = 'error' AND Tentativi >= 5;

-- Verifica
SELECT ID_Comanda, Stato, Tentativi FROM COMANDE WHERE Error_Message IS NULL AND Stato = 'pending';
```

### 7.4 Riprocessa dopo reset

```bash
# Riprocessa
php scripts/worker_process_comande.php --limit=20 --max-tries=5
```

**Documenta i risultati:**
- [ ] Comande riprocessate dopo reset: ___
- [ ] Comande passate a 'sent': ___
- [ ] Comande ancora in errore: ___

---

## 8. Test End-to-End Completo {#test-e2e}

### 8.1 Scenario completo: Ordine → Comande → Stampa

**Step 1: Crea ordine via API**

```bash
curl -X POST http://localhost/api/salva_ordine.php \
  -H 'Content-Type: application/json' \
  -d '{
    "nome_cliente": "Test E2E",
    "id_tavolo": 1,
    "numero_coperti": 4,
    "totale": 45.00,
    "sconto": 5.00,
    "staff": false,
    "dettagli": [
      {"id_prodotto": 1, "quantita": 2, "prezzo_unitario": 10.00, "descrizione": "Pizza Margherita"},
      {"id_prodotto": 5, "quantita": 4, "prezzo_unitario": 3.00, "descrizione": "Coca Cola"},
      {"id_prodotto": 10, "quantita": 1, "prezzo_unitario": 15.00, "descrizione": "Tiramisù"}
    ]
  }' > /tmp/order_response.json

# Visualizza risposta
cat /tmp/order_response.json | jq
```

**Step 2: Estrai ID ordine**

```bash
ORDER_ID=$(cat /tmp/order_response.json | jq -r '.data.order_id')
echo "ID Ordine creato: $ORDER_ID"
```

**Step 3: Verifica comande create**

```sql
-- Usa $ORDER_ID ottenuto sopra
SELECT ID_Comanda, Nome_Stampante_LAN, Stato, LENGTH(Testo_Comanda) as Lunghezza_Testo
FROM COMANDE
WHERE ID_Ordine = ?;  -- Sostituisci ? con ORDER_ID
```

**Step 4: Verifica print_status in risposta**

```bash
cat /tmp/order_response.json | jq '.data.print_status'
```

**Step 5: Attendi worker (se configurato) o esegui manualmente**

```bash
# Manuale
php scripts/worker_process_comande.php --limit=10

# Oppure attendi timer systemd/cron
sleep 60
```

**Step 6: Verifica stato finale**

```sql
SELECT ID_Comanda, Nome_Stampante_LAN, Stato, Tentativi, Data_Invio
FROM COMANDE
WHERE ID_Ordine = ?;  -- Sostituisci con ORDER_ID
```

**Step 7: Verifica stampa fisica**

- [ ] Ricevuta stampata su 'cassa': SI / NO
- [ ] Comanda stampata su stampante reparto 1: SI / NO
- [ ] Comanda stampata su stampante reparto 2: SI / NO
- [ ] Tutte le comande in stato 'sent': SI / NO

**Cleanup:**

```bash
rm /tmp/order_response.json
```

---

## 9. Checklist Validazione {#checklist}

### Checklist Generale

- [ ] Documentazione INSTALL.md aggiornata con scenari stampa
- [ ] Tutti gli scenari testati (A, B, C)
- [ ] Worker processa correttamente comande pending
- [ ] Worker gestisce correttamente max retry
- [ ] Stati COMANDE aggiornati correttamente
- [ ] Log errori informativi e completi
- [ ] Nessuna perdita di dati ordini anche con stampa fallita

### Checklist Scenario A (lp disponibile)

- [ ] `lp` rilevato correttamente
- [ ] Ricevuta cassa stampata immediatamente
- [ ] Comande inserite con stato 'pending'
- [ ] Comande processate passano a 'sent'
- [ ] Errori stampa registrati in Error_Message
- [ ] Data_Invio popolata per comande 'sent'

### Checklist Scenario B (lp non disponibile)

- [ ] Assenza `lp` rilevata correttamente
- [ ] Log contiene messaggio "lp non disponibile"
- [ ] Nessuna stampa tentata
- [ ] Comande rimangono in 'pending'
- [ ] API risponde con successo comunque
- [ ] Worker logga avviso lp non disponibile

### Checklist Scenario C (shell disabilitate)

- [ ] Funzioni shell disabilitate solo per Web PHP
- [ ] CLI PHP mantiene funzioni shell
- [ ] API Web non tenta stampa
- [ ] Comande create in 'pending'
- [ ] Worker CLI processa normalmente
- [ ] Comande passano a 'sent' tramite worker

### Checklist Riprocessamento

- [ ] Comande pending riprocessabili
- [ ] Comande error riprocessabili (se Tentativi < max)
- [ ] Comande con max retry non riprocessate
- [ ] Reset manuale Tentativi funziona
- [ ] Aggiornamento manuale stati funziona
- [ ] Query diagnostiche forniscono info utili

### Checklist Diagnostica

- [ ] Test verifica lp funzionante
- [ ] Test verifica funzioni PHP shell
- [ ] Test verifica directory temp
- [ ] Test verifica permessi utente
- [ ] Query DB per stato comande
- [ ] Log CUPS consultabili
- [ ] Output worker dettagliato e comprensibile

---

## Note Finali

- Conserva questa guida insieme alla documentazione tecnica
- Aggiorna la guida quando vengono modificate API o worker
- Usa questa guida per onboarding nuovi membri del team
- Esegui questi test dopo ogni modifica alla logica di stampa
- Documenta eventuali nuovi scenari o edge case scoperti

Per domande o problemi: consulta `docs/INSTALL.md` sezione 11 "Stampa con CUPS e gestione comande".

---
**Versione:** 1.0  
**Data:** 2025-10-24  
**Autore:** Team RICEVUTE
