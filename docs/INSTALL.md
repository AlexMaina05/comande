---
layout: default 
title: INSTALLAZIONE (Dettagliata)
permalink: /docs/install
---

# INSTALLAZIONE E DEPLOY (Guida completa per ambiente locale / NAS / VPS)

Questa guida descrive i passi pratici per installare, configurare e mettere in produzione l'app "RICEVUTE" in un ambiente LAMP (Linux, Apache/Nginx, PHP, MariaDB/MySQL). Contiene istruzioni operative, suggerimenti di sicurezza e opzioni per la stampa.

Indice rapido
- Requisiti software/hardware
- Clonazione repo e preparazione
- Configurazione database + import schema
- Seed iniziali obbligatori
- Configurazione applicazione (variabili / config/db_connection.php)
- PHP / Webserver / Permessi
- Configurazione CUPS e stampa
- Worker (CLI / systemd / cron)
- API principali e test end‑to‑end
- Backup, logging e troubleshooting
- Sicurezza e hardening
- Esempi utili e comandi rapidi

Requisiti minimi
- Server Linux (Debian/Ubuntu consigliati) o NAS con supporto PHP+MySQL
- PHP 8.0+ con estensioni: pdo_mysql, mbstring, json, openssl, fileinfo (se usi upload)
- Web server: Apache o Nginx
- MariaDB / MySQL (10.x consigliato)
- CUPS per stampa (opzionale, per stampa comande)
- Accesso SSH per configurazioni e installazioni

1) Clona il repository
    git clone https://github.com/AlexMaina05/RICEVUTE.git
    cd RICEVUTE

2) Configurazione database
- Crea il DB e un utente con privilegi limitati (esempio MySQL CLI):
    mysql -u root -p
    CREATE DATABASE ristorante_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER 'app_user'@'localhost' IDENTIFIED BY 'strong_password';
    GRANT ALL PRIVILEGES ON ristorante_db.* TO 'app_user'@'localhost';
    FLUSH PRIVILEGES;

- Importa lo schema SQL:
    mysql -u app_user -p ristorante_db < sql/schema.sql

Note sullo schema
- Assicurati che i campi stringa usino utf8mb4; per indici unici su VARCHAR lunghi usa VARCHAR(191) se il server ha limiti su index key length.
- Se le tabelle hanno vincoli NOT NULL e non ci sono seed, inserisci i record minimi (vedi punto 3).

3) Seed iniziali (obbligatori se previsto)
- Se lo script utilities/creazione_tabelle.sql o sql/schema.sql non inserisce dati iniziali, crea almeno:
  - 1 o più categorie in CATEGORIE
  - 1 reparto in REPARTI (impostare Nome_Stampante_LAN valido)
  - 1 o più tavoli in TAVOLI (o opzione ASPORTO)
  - Alcuni PRODOTTI correlati a categorie/reparti
- Puoi usare phpMyAdmin o INSERT SQL.

4) Configura la connessione al DB
- Modifica file config/db_connection.php con le tue credenziali oppure prepara variabili d'ambiente (.env) e fai leggere il file da config/db_connection.php.
- Esempio (suggerito):
    $servername = getenv('DB_HOST') ?: 'localhost';
    $username = getenv('DB_USER') ?: 'app_user';
    $password = getenv('DB_PASS') ?: 'strong_password';
    $dbname = getenv('DB_NAME') ?: 'ristorante_db';

- Consiglio: mantieni le credenziali fuori dal repo. Se usi credenziali hardcoded per ambiente locale, documentalo chiaramente.

5) Impostazioni PHP e Webserver
- Abilita le estensioni: pdo_mysql, mbstring, json.
- Verifica in php.ini:
    memory_limit = 128M
    max_execution_time = 60
- Verifica disable_functions: se `exec`/`shell_exec` è disabilitato, la stampa via lp da PHP non funzionerà. Puoi eseguire la stampa tramite worker CLI con permessi appropriati.
- Configura VirtualHost (Apache) o server block (Nginx) puntando alla cartella del progetto.

6) Configura CUPS e stampanti
- Installa CUPS (es. apt install cups).
- Accedi a http://localhost:631 e aggiungi le stampanti (rete o locali).
- Assicurati che il valore REPARTI.Nome_Stampante_LAN corrisponda al nome della queue CUPS.
- Test stampa da shell:
    echo "test" > /tmp/test_print.txt
    lp -d nome_queue /tmp/test_print.txt
- Assicurati che l'utente che esegue i worker (es. www-data) abbia permessi di stampa:
    sudo usermod -aG lpadmin www-data

7) Worker per processare comande (CLI / systemd / cron)
- Test manuale:
    php scripts/worker_process_comande.php --limit=10 --max-tries=5

- Cron (es. ogni minuto):
    * * * * * /usr/bin/php /percorso/REPO/scripts/worker_process_comande.php --limit=20 --max-tries=5 >> /var/log/comande_worker.log 2>&1

- Systemd timer (raccomandato per server Linux):
  - Crea unità .service e .timer (verifica paths e utente)
  - Commands:
      sudo systemctl daemon-reload
      sudo systemctl enable --now worker_comande.timer
      systemctl status worker_comande.service
      journalctl -u worker_comande.service -f

8) API principali e test end‑to‑end
- Endpoint utili:
  - GET api/cerca_prodotto.php?codice=CODICE
  - GET api/genera_report.php?data=YYYY-MM-DD
  - GET api/gestisci_dati.php?section=prodotti|tavoli|categorie|reparti
  - POST api/salva_ordine.php (JSON body)
  - POST api/ripeti_comanda.php (JSON body {id_comanda})
- Esempi curl:
    curl -s "http://localhost/api/cerca_prodotto.php?codice=PZ01" | jq
    curl -s -X POST "http://localhost/api/salva_ordine.php" -H 'Content-Type: application/json' -d '{"nome_cliente":"Mario","id_tavolo":1,"numero_coperti":2,"totale":12.50,"dettagli":[{"id_prodotto":1,"quantita":2,"prezzo_unitario":3.5}]}' | jq

9) Backup, log e manutenzione
- Esegui mysqldump regolarmente e archivia in posizione sicura.
- Rotazione log (logrotate) per /var/log/comande_worker.log e altri log custom.
- Verifica i log di systemd via journalctl per problemi worker.

10) Sicurezza e hardening
- Non committare credenziali in repo; usa .env e .gitignore.
- Servi il sito via HTTPS (Let's Encrypt).
- Proteggi le pagine admin con sessione e idealmente con password hash in DB.
- Configura cookie sessioni: secure, httponly, samesite.
- Proteggi azioni critiche con CSRF token.
- In produzione, limita l'accesso a cartelle sensibili e disabilita directory listing.

11) Stampa con CUPS e gestione comande: scenari e fallback

Questa sezione documenta in dettaglio come funziona la stampa tramite CUPS (comando `lp`) in diversi scenari e come gestire le comande quando alcuni componenti non sono disponibili.

**Contesto tecnico**
- L'endpoint `api/salva_ordine.php` e lo script worker `scripts/worker_process_comande.php` utilizzano il comando `lp` di CUPS per stampare ricevute e comande.
- La stampa viene effettuata tramite funzioni PHP `exec()` o `shell_exec()` che possono essere disabilitate in alcuni ambienti (es. NAS, shared hosting).
- Le comande vengono salvate nella tabella `COMANDE` con uno stato che traccia il ciclo di vita della stampa.

**11.1) Scenari operativi**

**Scenario A: lp disponibile e funzioni shell abilitate (configurazione ideale)**
- Ambiente: server Linux con CUPS installato, PHP non ha `exec`/`shell_exec` in `disable_functions`.
- Comportamento:
  * `api/salva_ordine.php`: 
    - Verifica disponibilità `lp` con `command -v lp`
    - Stampa immediatamente la ricevuta (cassa)
    - Inserisce comande in tabella COMANDE con stato 'pending'
    - Tenta stampa immediata delle comande e aggiorna stato a 'sent' se successo, 'error' se fallimento
  * `worker_process_comande.php`:
    - Trova comande con stato 'pending' o 'error' (con Tentativi < max)
    - Tenta stampa e aggiorna stato a 'sent' o 'error'
- Stati in COMANDE:
  * 'pending': comanda salvata ma non ancora stampata
  * 'processing': comanda presa in carico dal worker (temporaneo)
  * 'sent': comanda stampata con successo
  * 'error': stampa fallita (errore CUPS, stampante offline, file temporaneo non scrivibile, etc.)

**Scenario B: lp NON disponibile (es. CUPS non installato)**
- Ambiente: server/NAS senza CUPS o `lp` non nel PATH.
- Comportamento:
  * `api/salva_ordine.php`:
    - Rileva che `lp` non è disponibile (comando `command -v lp` ritorna stringa vuota)
    - NON tenta la stampa immediata della ricevuta
    - Inserisce comande in tabella COMANDE con stato 'pending'
    - Logga messaggio: "Stampa CUPS: comando 'lp' non disponibile. Impossibile stampare ricevuta immediata."
  * `worker_process_comande.php`:
    - Rileva che `lp` non è disponibile
    - Logga: "Attenzione: comando 'lp' non trovato. Il worker continuerà ma non tenterà la stampa."
    - Lascia le comande in stato 'pending' (o riporta da 'processing' a 'pending')
    - Aggiorna campo Error_Message con "lp non disponibile"
- Stati in COMANDE:
  * 'pending': tutte le comande rimangono pendenti
  * NESSUNA comanda viene marcata 'sent'
- Soluzione: installare CUPS e configurare stampanti, poi riprocessare le comande pendenti (vedi 11.3).

**Scenario C: Funzioni shell disabilitate in php.ini**
- Ambiente: PHP ha `exec` e/o `shell_exec` in `disable_functions` (comune in shared hosting, alcuni NAS).
- Comportamento:
  * `api/salva_ordine.php`:
    - Rileva che funzioni shell non esistono (`function_exists('exec')` e `function_exists('shell_exec')` ritornano false)
    - Salta completamente il rilevamento e tentativo di stampa
    - Inserisce comande in tabella COMANDE con stato 'pending'
    - NON logga errori specifici (assumiamo che il worker CLI avrà accesso)
  * `worker_process_comande.php`:
    - Eseguito da CLI, tipicamente ha accesso alle funzioni shell
    - Se eseguito come script CLI, PHP CLI spesso ha un php.ini separato senza `disable_functions`
    - Processa normalmente le comande pendenti se `lp` è disponibile
- Stati in COMANDE:
  * 'pending': comande inserite da API web
  * 'sent' o 'error': aggiornate dal worker CLI
- Soluzione: eseguire il worker da linea di comando (cron/systemd) su una macchina con PHP CLI e CUPS disponibili.

**11.2) Tabella COMANDE: campi e stati**

La tabella COMANDE traccia ogni tentativo di stampa:

```sql
CREATE TABLE COMANDE (
  ID_Comanda INT AUTO_INCREMENT PRIMARY KEY,
  ID_Ordine INT NOT NULL,
  Nome_Stampante_LAN VARCHAR(150) NOT NULL,
  Testo_Comanda TEXT NOT NULL,
  Stato ENUM('pending','processing','sent','error') NOT NULL DEFAULT 'pending',
  Tentativi INT NOT NULL DEFAULT 0,
  Error_Message TEXT,
  Data_Creazione DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Data_Invio DATETIME DEFAULT NULL,
  FOREIGN KEY (ID_Ordine) REFERENCES ORDINI(ID_Ordine) ON DELETE CASCADE
);
```

**Campi:**
- `Stato`: ciclo di vita della stampa
  * 'pending': in attesa di stampa
  * 'processing': presa in carico dal worker (lock temporaneo)
  * 'sent': stampata con successo
  * 'error': stampa fallita
- `Tentativi`: contatore incrementato ad ogni tentativo (usato per evitare retry infiniti)
- `Error_Message`: messaggio di errore dell'ultima esecuzione (output di lp o errore sistema)
- `Data_Invio`: timestamp di invio riuscito a CUPS (NULL se mai inviata)

**11.3) Riprocessare comande pendenti**

Se le comande sono rimaste in stato 'pending' o 'error' (es. CUPS non era disponibile, stampante offline), è possibile riprocessarle:

**Opzione A: Worker automatico (raccomandato)**
1. Assicurarsi che CUPS sia installato e le stampanti configurate:
   ```bash
   lpstat -p -d
   ```

2. Verificare che `lp` sia nel PATH:
   ```bash
   command -v lp
   # Dovrebbe stampare: /usr/bin/lp (o simile)
   ```

3. Eseguire il worker manualmente per test:
   ```bash
   php scripts/worker_process_comande.php --limit=50 --max-tries=5
   ```
   
4. Se funziona, configurare cron o systemd timer (vedi sezione 7).

**Opzione B: Riprocessamento manuale da altro server**
Se il NAS non ha CUPS ma hai un server Linux con CUPS:

1. Esporta comande pendenti dal NAS:
   ```bash
   mysql -u user -p -e "SELECT * FROM COMANDE WHERE Stato IN ('pending','error') INTO OUTFILE '/tmp/comande_pending.csv' FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\n';" database_name
   ```

2. Trasferisci il file sul server con CUPS e importa in un DB locale.

3. Esegui il worker sul server con CUPS:
   ```bash
   php scripts/worker_process_comande.php --limit=100
   ```

4. Esporta i risultati (stato 'sent') e sincronizza nel DB del NAS.

**Opzione C: Aggiornamento manuale stati in DB**
Se decidi di NON stampare certe comande (es. troppo vecchie), puoi aggiornarle manualmente:

```sql
-- Marca come 'sent' (finto invio) comande vecchie che non vuoi più stampare
UPDATE COMANDE 
SET Stato = 'sent', Error_Message = 'Marcato manualmente come inviato', Data_Invio = NOW()
WHERE Stato IN ('pending','error') 
  AND Data_Creazione < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Oppure elimina comande vecchie
DELETE FROM COMANDE 
WHERE Stato IN ('pending','error') 
  AND Data_Creazione < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

**ATTENZIONE:** Non eliminare comande senza verificare che non siano necessarie per operatività o audit.

**11.4) Diagnostica e troubleshooting stampa**

**A) Verificare disponibilità comando lp**
```bash
# Da shell (SSH)
command -v lp
# Output atteso: /usr/bin/lp

# Test stampa manuale
echo "Test stampa" > /tmp/test.txt
lp -d nome_stampante /tmp/test.txt
# Verifica che la stampante stampi il file
```

**B) Verificare funzioni PHP shell**
```bash
# Crea script di test
cat > /tmp/test_shell.php << 'EOF'
<?php
echo "exec disponibile: " . (function_exists('exec') ? 'SI' : 'NO') . "\n";
echo "shell_exec disponibile: " . (function_exists('shell_exec') ? 'SI' : 'NO') . "\n";

if (function_exists('shell_exec')) {
    $lp = shell_exec('command -v lp 2>/dev/null');
    echo "Percorso lp: " . trim($lp) . "\n";
}
?>
EOF

# Esegui con PHP web (es. via Apache)
# Richiedi http://tuo-server/test_shell.php

# Esegui con PHP CLI
php /tmp/test_shell.php
```

**C) Verificare directory temporanea e permessi**
```bash
# Controlla directory temp
php -r "echo sys_get_temp_dir() . PHP_EOL;"
# Output tipico: /tmp

# Verifica permessi
ls -ld /tmp
# Dovrebbe essere: drwxrwxrwt (sticky bit, scrivibile da tutti)

# Test creazione file temporaneo
php -r "echo tempnam(sys_get_temp_dir(), 'test_') . PHP_EOL;"
# Dovrebbe creare e stampare path di un file temporaneo
```

**D) Verificare utente che esegue PHP/worker**
```bash
# Web (Apache/Nginx)
# Crea file test.php: <?php echo exec('whoami'); ?>
# Output tipico: www-data o apache

# CLI worker
php -r "echo exec('whoami') . PHP_EOL;"
# Output: tuo utente o utente configurato in cron/systemd

# Verifica che l'utente sia in gruppo lpadmin
groups www-data
# Dovrebbe includere 'lpadmin' se vuoi che PHP web stampi
```

**E) Verificare log CUPS**
```bash
# Log errori CUPS
tail -f /var/log/cups/error_log

# Verificare code di stampa
lpstat -o
# Mostra job in coda

# Cancellare job bloccati
cancel -a nome_stampante
```

**F) Verificare stato comande in DB**
```sql
-- Conta comande per stato
SELECT Stato, COUNT(*) as Totale 
FROM COMANDE 
GROUP BY Stato;

-- Mostra comande in errore con messaggio
SELECT ID_Comanda, ID_Ordine, Nome_Stampante_LAN, Tentativi, Error_Message, Data_Creazione
FROM COMANDE 
WHERE Stato = 'error'
ORDER BY Data_Creazione DESC
LIMIT 20;

-- Mostra comande pendenti vecchie (> 1 ora)
SELECT ID_Comanda, ID_Ordine, Nome_Stampante_LAN, Tentativi, Data_Creazione
FROM COMANDE 
WHERE Stato = 'pending' 
  AND Data_Creazione < DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY Data_Creazione ASC;
```

**G) Debug output worker**
```bash
# Esegui worker con output completo
php scripts/worker_process_comande.php --limit=5 --max-tries=3

# Esempio output atteso:
# [2025-10-24 12:00:00] [hostname:12345] Processing comanda 42 -> printer 'cucina'
# [2025-10-24 12:00:01] [hostname:12345] Comanda 42 inviata con successo.
# [2025-10-24 12:00:01] [hostname:12345] Fine batch, processate 1 comande.

# Se lp non disponibile:
# [hostname:12345] Attenzione: comando 'lp' non trovato. Il worker continuerà ma non tenterà la stampa.
# [hostname:12345] Comanda 42 lasciata in pending: comando 'lp' non trovato.
```

**H) Test end-to-end stampa**
```bash
# 1. Crea ordine via API
curl -X POST http://localhost/api/salva_ordine.php \
  -H 'Content-Type: application/json' \
  -d '{
    "nome_cliente":"Test",
    "id_tavolo":1,
    "numero_coperti":2,
    "totale":10.00,
    "dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10.00,"descrizione":"Pizza Margherita"}]
  }' | jq

# 2. Verifica comande create
mysql -u user -p -e "SELECT * FROM COMANDE ORDER BY Data_Creazione DESC LIMIT 5;" database_name

# 3. Esegui worker
php scripts/worker_process_comande.php --limit=10

# 4. Verifica stampa fisica o log CUPS
lpstat -o
```

**11.5) Checklist risoluzione problemi**

Problema: "Comande rimangono in pending"
- [ ] Verificare che CUPS sia installato: `systemctl status cups` o `lpstat -r`
- [ ] Verificare che `lp` sia nel PATH: `command -v lp`
- [ ] Verificare che stampanti siano configurate: `lpstat -p -d`
- [ ] Verificare che worker giri regolarmente: `systemctl status worker_comande.timer` o controllare cron
- [ ] Controllare log worker: `journalctl -u worker_comande.service -f` o file log custom
- [ ] Eseguire worker manualmente per test: `php scripts/worker_process_comande.php --limit=10`

Problema: "Errore creazione file temporaneo"
- [ ] Verificare directory temp: `php -r "echo sys_get_temp_dir();"`
- [ ] Verificare permessi: `ls -ld /tmp` (dovrebbe essere writable)
- [ ] Verificare spazio disco: `df -h /tmp`
- [ ] Controllare quote utente se presenti: `quota -s`

Problema: "Stampa fallisce con errore CUPS"
- [ ] Verificare che stampante sia online: `lpstat -p nome_stampante`
- [ ] Test stampa manuale: `echo "test" | lp -d nome_stampante`
- [ ] Controllare coda stampa: `lpstat -o`
- [ ] Verificare log CUPS: `tail /var/log/cups/error_log`
- [ ] Verificare connessione di rete alla stampante (se stampante LAN)
- [ ] Verificare credenziali/driver stampante in CUPS web UI (http://localhost:631)

Problema: "Worker non trova comande ma ci sono pending in DB"
- [ ] Verificare query SQL worker (controllare filtri Stato e Tentativi)
- [ ] Verificare che max-tries non sia stato raggiunto: controllare colonna Tentativi in DB
- [ ] Verificare lock DB: controllare se ci sono transazioni bloccate
- [ ] Tentare reset Tentativi: `UPDATE COMANDE SET Tentativi = 0 WHERE Stato = 'error' AND Tentativi >= 5;`

12) Miglioramenti consigliati (futuro)
- Centralizzare configurazione con .env + phpdotenv.
- Usare password_hash/password_verify per utenti.
- Implementare migrazioni con Phinx o simili.
- Aggiungere test automatici (unit e integrazione).

Contatti
- Per problemi relativi al codice: Alessandro Mainardi (repo owner)

-- Fine guida di installazione --
