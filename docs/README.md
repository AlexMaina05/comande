---
layout: default 
title: DOCUMENTAZIONE (README)
permalink: /docs
---

# RICEVUTE — Web App Gestionale per Ristorante

Breve descrizione
RICEVUTE è una piccola web‑app per la gestione degli ordini/comande in ristorazione: inserimento ordini dalla cassa, invio comande alle stampanti di reparto, report giornalieri e pannello admin per anagrafiche. Progettata per funzionare in rete locale (NAS / VPS / macchina Linux).

Caratteristiche principali
- Interfaccia "cassa" per creare ordini e stampare comande.
- Worker CLI per inviare comande a stampanti CUPS.
- API REST leggere per integrazione (JSON).
- Report giornalieri con export PDF client-side.
- Area admin per gestire prodotti, reparti, categorie e tavoli.
- Schema DB compatibile MySQL/MariaDB.

Indice
- Architettura
- Installazione rapida
- Uso e endpoints
- Schema DB (sintesi)
- Sviluppo e testing
- Deployment consigliato
- Sicurezza
- Troubleshooting
- Contribuire
- Licenza e contatti

Documentazione Aggiuntiva

Guide Utente (WIKI)
- [**WIKI - Home**](../wiki/) - Documentazione wiki per gli utenti
- [**Come Creare un Ordine**](../wiki/How-to-Create-an-Order.md) - Guida completa per creare ordini (normali, staff, con sconto)

Documentazione Tecnica
- [STRUCTURE.md](../STRUCTURE.md) - Struttura dettagliata del repository dopo la riorganizzazione
- [PAGINE_WEB.md](PAGINE_WEB.md) - Screenshot e spiegazioni dettagliate di tutte le pagine web
- [API.md](API.md) - Documentazione completa degli endpoint API
- [INSTALL.md](INSTALL.md) - Guida completa all'installazione e configurazione (include scenari stampa CUPS)
- [PRINT_TESTING_GUIDE.md](PRINT_TESTING_GUIDE.md) - Guida ai test manuali per stampa e gestione comande

Architettura
- Frontend: HTML, CSS, JS (pagine PHP che servono markup + asset)
- Backend: PHP (API, connessione PDO)
- Database: MariaDB / MySQL
- Worker: script CLI PHP (scripts/worker_process_comande.php) invocato via cron o systemd timer

Installazione rapida (quickstart)
1. Clona:
    git clone https://github.com/AlexMaina05/RICEVUTE.git
2. Crea DB e importa schema:
    mysql -u root -p
    CREATE DATABASE ristorante_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER 'app_user'@'localhost' IDENTIFIED BY 'strong_password';
    GRANT ALL ON ristorante_db.* TO 'app_user'@'localhost';
    mysql -u app_user -p ristorante_db < sql/schema.sql
3. Configura config/db_connection.php (o .env)
4. Testa l'app sul browser (http://tuo-server/REPO/public)

Uso / API
Tutte le API restituiscono risposte JSON uniformi con schema { success, data, error }.
Vedi docs/API.md per documentazione completa.

- GET api/cerca_prodotto.php?codice=CODICE
  -> ritorna JSON con i dettagli del prodotto (Prezzo, Descrizione, ID_Prodotto)
- POST api/salva_ordine.php
  -> salva ordine + crea comande per reparti; body JSON: {nome_cliente, id_tavolo, numero_coperti, totale, dettagli: [...]}
- GET api/genera_report.php?data=YYYY-MM-DD
  -> ritorna riepilogo e dettaglio prodotti venduti
- GET api/gestisci_dati.php?data=YYYY-MM-DD
  -> ritorna dati per report giornaliero

Database — riepilogo tabelle principali
- CATEGORIE (ID_Categoria, Nome_Categoria)
- REPARTI (ID_Reparto, Nome_Reparto, Nome_Stampante_LAN)
- PRODOTTI (ID_Prodotto, Descrizione, Prezzo, Codice_Prodotto, FK categorie/reparti)
- TAVOLI (ID_Tavolo, Nome_Tavolo, Tipo_Servizio)
- ORDINI (ID_Ordine, ID_Tavolo, Nome_Cliente, Numero_Coperti, Totale_Ordine, Data_Ora)
- DETTAGLI_ORDINE (ID_Dettaglio, ID_Ordine, ID_Prodotto, Quantita, Prezzo_Bloccato)
- COMANDE (ID_Comanda, ID_Ordine, Nome_Stampante_LAN, Testo_Comanda, Stato, Tentativi)

Sviluppo e testing
- Requisiti locali: PHP 8.0+, Composer (opzionale), Node/NPM (se lavori su build frontend)
- Esegui il worker manualmente per test:
    php scripts/worker_process_comande.php --limit=5 --max-tries=3
- Usa DevTools per debug JS, e journalctl / logs per problemi worker.

Deployment consigliato
- Metti file sensibili fuori dalla webroot quando possibile (config, sql, scripts)
- Usa systemd timer per invocare il worker o cron come fallback
- Configura Nginx/Apache con HTTPS (Let's Encrypt)
- Imposta rotazione log e backup del DB (mysqldump)

Sicurezza (essenziali)
- Non committare credenziali; preferisci variabili d'ambiente o file di config non versione.
- Usa HTTPS
- Proteggi area admin con sessione; usate password_hash/password_verify per password persistenti.
- Abilitare cookie sessione con flags secure/httponly/samesite.
- Convalidare e sanificare sempre input sia client che server per prevenire SQLi/XSS.

Troubleshooting rapido
- 500 API: controlla i log PHP e il file db_connection.php
- Worker non stampa: esegui manualmente lp -d queue /tmp/file e verifica permessi utente
- Problemi di charset: assicurati che DB e pagine PHP usino utf8mb4
- Comande rimangono in 'pending': vedi [INSTALL.md sezione 11](INSTALL.md#11-stampa-con-cups-e-gestione-comande-scenari-e-fallback) per scenari stampa e diagnostica completa
- Problemi stampa CUPS: consulta [PRINT_TESTING_GUIDE.md](PRINT_TESTING_GUIDE.md) per test dettagliati

Contribuire
- Apri issue in GitHub per bug / richieste funzionalità
- Segui le linee guida: crea branch feature/bugfix, tests se possibile, pull request descrittive

Licenza & contatti
- Licenza: aggiungi file LICENSE (es. MIT) se vuoi permettere uso esteso
- Autore / mantenitore: Alessandro Mainardi — vedi il repository per contatti

Appendice: comandi utili
- Abilitare timer systemd:
    sudo systemctl daemon-reload
    sudo systemctl enable --now worker_comande.timer
- Verificare timer / service:
    systemctl list-timers --all
    systemctl status worker_comande.service
    journalctl -u worker_comande.service -f

Fine README

