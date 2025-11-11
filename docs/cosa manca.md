# TODO - LIST: COSA MANCA (aggiornata)

Nota: ho rimosso le voci già implementate nel repository. Rimangono i compiti operativi ancora da svolgere prima di avere un'installazione completamente funzionante.

1) Creare il database e importare lo schema
- Creare il database (es. `ristorante_db`) via phpMyAdmin o CLI.
- Importare lo schema SQL: `mysql -u app_user -p ristorante_db < sql/schema.sql`
- Verificare `CHARSET utf8mb4` e, se usi UNIQUE su varchar lunghi, usare VARCHAR(191) o configurare InnoDB per supportare indici lunghi.

2) Popolare le anagrafiche iniziali (obbligatorio per schema "NOT NULL")
- Inserire almeno:
  - 1 o più categorie in `CATEGORIE`
  - 1 reparto in `REPARTI` (impostare `Nome_Stampante_LAN`)
  - 1 o più tavoli in `TAVOLI` (oppure prevedere l'opzione "ASPORTO")
  - Alcuni prodotti in `PRODOTTI` collegati a categorie/reparti
- Puoi creare uno script SQL separato `utilities/seed.sql` per questi INSERT.

3) Completare eventuali API amministrative mancanti
- Verificare `api/gestisci_dati.php`: la logica di gestione per Prodotti e Tavoli è presente; controlla e, se necessario, aggiungi POST/PUT/DELETE analoghi per `CATEGORIE` e `REPARTI`.
- Assicurati che tutte le risposte API usino JSON con `charset=utf-8` e gestiscano errori con codici HTTP corretti.

4) Test end-to-end e debugging
- Testare le pagine principali: cassa, admin, report.
- Testare le API principali:
  - `GET api/cerca_prodotto.php?codice=...`
  - `POST api/salva_ordine.php` (salvataggio ordine + generazione comande)
  - `POST api/ripeti_comanda.php` (ristampa comanda)
  - `GET api/genera_report.php?data=YYYY-MM-DD`
- Verificare log PHP / journalctl per eventuali errori runtime.

5) Verificare stampa / worker
- Configurare CUPS e verificare che `Nome_Stampante_LAN` corrisponda alle queue CUPS.
- Testare stampa manuale da shell: `lp -d nome_queue /tmp/test.txt`
- Eseguire il worker manualmente e controllare comportamento:
  - `php scripts/worker_process_comande.php --limit=10 --max-tries=5`
- Installare e abilitare il timer systemd (se previsto) sostituendo i percorsi in `docs/*.service` e `docs/*.timer`.

6) Sicurezza minima e configurazione ambiente
- Confermare scelta credenziali (hardcoded vs .env). Se usi hardcoded, documentare chiaramente e proteggere il repo.
- Configurare `db_connection.php` e `check_login.php` con cookie/session param adeguati (secure, httponly, samesite).
- Abilitare HTTPS in produzione / rete locale se possibile.
- Considerare protezione CSRF per azioni sensibili nell'area admin.

7) Operazioni di produzione / manutenzione
- Configurare rotazione log (logrotate) per i log dei worker.
- Pianificare backup DB (es. `mysqldump`) e test restore.
- Documentare comandi di deploy/upgrade (migrazioni o script di aggiornamento schema).

8) Pulizie e miglioramenti opzionali
- Spostare file sensibili fuori dalla webroot o usare `.env` con `.gitignore`.
- Aggiungere seed SQL separato (`utilities/seed.sql`) invece di dipendere da default NOT NULL.
- Eventuale miglioramento: PDF server-side per report (Dompdf / wkhtmltopdf) se serve qualità di stampa riproducibile.
