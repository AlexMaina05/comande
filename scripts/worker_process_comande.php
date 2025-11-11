<?php
/**
 * Worker CLI per processare le comande pendenti nella tabella COMANDE.
 * Usage: php scripts/worker_process_comande.php [--limit=10] [--max-tries=5] [--sleep-ms=200] [--dry-run] [--retry-lock=3]
 *
 * Options:
 *   --limit=N         Maximum number of comande to process per run (default: 10)
 *   --max-tries=N     Maximum attempts per comanda before giving up (default: 5)
 *   --sleep-ms=N      Milliseconds to sleep between processing each comanda (default: 200)
 *   --dry-run         Mock mode: don't execute lp commands, only simulate (for testing)
 *   --retry-lock=N    Number of retries for GET_LOCK with exponential backoff (default: 3)
 *
 * Note:
 * - Questo worker usa SELECT ... FOR UPDATE SKIP LOCKED quando disponibile (MySQL 8+).
 * - Se SKIP LOCKED non è disponibile, prova un fallback con GET_LOCK per evitare race condition.
 * - In caso di fallimento GET_LOCK, riprova con exponential backoff invece di uscire immediatamente.
 */

require_once __DIR__ . '/../config/db_connection.php';

// -------------------- Parametri CLI --------------------
$options = [];
foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $options['limit'] = intval(substr($arg, 8));
    }
    if (strpos($arg, '--max-tries=') === 0) {
        $options['max_tries'] = intval(substr($arg, 12));
    }
    if (strpos($arg, '--sleep-ms=') === 0) {
        $options['sleep_ms'] = intval(substr($arg, 11));
    }
    if ($arg === '--dry-run') {
        $options['dry_run'] = true;
    }
    if (strpos($arg, '--retry-lock=') === 0) {
        $options['retry_lock'] = intval(substr($arg, 13));
    }
}
$limit = max(1, $options['limit'] ?? 10);
$maxTries = max(1, $options['max_tries'] ?? 5);
$sleepMs = max(0, $options['sleep_ms'] ?? 200);
$dryRun = $options['dry_run'] ?? false;
$retryLock = max(1, $options['retry_lock'] ?? 3);

// Identificatore worker (utile per logging)
$workerId = gethostname() . ':' . getmypid();

if ($dryRun) {
    echo "[" . date('Y-m-d H:i:s') . "] [$workerId] MODALITÀ DRY-RUN ATTIVA: nessuna stampa verrà effettivamente eseguita.\n";
}

// Verifica disponibilità di lp (ma non usciamo subito: gestiamo il caso)
$lpPath = '';
if (!$dryRun) {
    if (function_exists('shell_exec')) {
        $lpPath = trim(shell_exec('command -v lp 2>/dev/null') ?: '');
    } elseif (function_exists('exec')) {
        $whichOutput = [];
        $whichReturn = null;
        exec('command -v lp 2>/dev/null', $whichOutput, $whichReturn);
        $lpPath = trim(implode("\n", $whichOutput));
        if ($whichReturn !== 0 && $whichReturn !== null) {
            error_log("[$workerId] Errore durante la ricerca del comando lp: exit code $whichReturn");
        }
    }
}

if ($lpPath === '' && !$dryRun) {
    error_log("[$workerId] Attenzione: comando 'lp' non trovato. Il worker continuerà ma lascerà comande in pending.");
    $lpAvailable = false;
} else {
    $lpAvailable = true;
}

// Funzione helper per usleep con ms
function msleep($ms) {
    if ($ms > 0) usleep($ms * 1000);
}

try {
    // -------------------- Claim delle comande (atomico) --------------------
    // Strategia:
    // - In MySQL 8+ useremo SKIP LOCKED: selezioniamo gli ID con FOR UPDATE SKIP LOCKED
    // - Aggiorniamo le righe prese a Stato='processing' e incrementiamo Tentativi
    // - Commit, poi processiamo le righe
    //
    // Fallback: se SKIP LOCKED non è disponibile, proviamo ad acquisire un lock globale MySQL GET_LOCK
    // e poi eseguire una SELECT/UPDATE semplice per prendere le righe.

    // 1) Proviamo a prendere righe con SKIP LOCKED in una transazione
    $conn->beginTransaction();

    // Nota: non bindiamo LIMIT come placeholder in tutti gli ambienti, costruiamo la stringa in sicurezza
    $safeLimit = (int)$limit;

    $useSkipLocked = true;
    $ids = [];

    try {
        // Se MySQL supporta SKIP LOCKED, questa query darà gli ID non ancora lockati
        $sqlSelect = "
            SELECT ID_Comanda
            FROM COMANDE
            WHERE Stato = 'pending' AND (Tentativi IS NULL OR Tentativi < :maxTries)
            ORDER BY Data_Creazione ASC
            FOR UPDATE SKIP LOCKED
            LIMIT {$safeLimit}
        ";
        $stmt = $conn->prepare($sqlSelect);
        $stmt->execute([':maxTries' => $maxTries]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!empty($rows)) {
            $ids = array_map('intval', $rows);
        }
    } catch (PDOException $e) {
        // Probabilmente SKIP LOCKED non supportato => fallback
        error_log("[$workerId] SKIP LOCKED non disponibile o errore: " . $e->getMessage());
        $useSkipLocked = false;
        $conn->rollBack();
    }

    if (!$useSkipLocked) {
        // fallback: proviamo ad usare GET_LOCK per creare una sezione critica con retry ed exponential backoff
        $gotLock = false;
        $lockAttempt = 0;
        $lockTimeout = 2; // timeout iniziale in secondi per GET_LOCK
        
        while ($lockAttempt < $retryLock && !$gotLock) {
            $lockAttempt++;
            try {
                $stmtLock = $conn->query("SELECT GET_LOCK('worker_comande_lock', $lockTimeout) AS lk");
                $res = $stmtLock->fetch(PDO::FETCH_ASSOC);
                $gotLock = !empty($res['lk']);
                
                if (!$gotLock) {
                    $sleepTime = pow(2, $lockAttempt - 1); // exponential backoff: 1s, 2s, 4s, ...
                    error_log("[$workerId] Tentativo $lockAttempt/$retryLock: impossibile acquisire lock, attendo {$sleepTime}s prima di riprovare...");
                    sleep($sleepTime);
                }
            } catch (Exception $e) {
                error_log("[$workerId] Eccezione durante GET_LOCK (tentativo $lockAttempt/$retryLock): " . $e->getMessage());
                $gotLock = false;
                if ($lockAttempt < $retryLock) {
                    $sleepTime = pow(2, $lockAttempt - 1);
                    sleep($sleepTime);
                }
            }
        }

        if ($gotLock) {
            // Con lock globale, possiamo fare una SELECT normale e poi UPDATE per 'claim'
            $conn->beginTransaction();
            $sqlSel = "
                SELECT ID_Comanda
                FROM COMANDE
                WHERE Stato = 'pending' AND (Tentativi IS NULL OR Tentativi < :maxTries)
                ORDER BY Data_Creazione ASC
                LIMIT {$safeLimit}
            ";
            $stmt = $conn->prepare($sqlSel);
            $stmt->execute([':maxTries' => $maxTries]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            $ids = array_map('intval', $rows);
            // Mark as processing and increment Tentativi
            if (!empty($ids)) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $sqlUpd = "UPDATE COMANDE SET Stato = 'processing', Tentativi = COALESCE(Tentativi,0) + 1 WHERE ID_Comanda IN ({$in})";
                $stmtUpd = $conn->prepare($sqlUpd);
                $stmtUpd->execute($ids);
            }
            $conn->commit();
            // rilascia GET_LOCK
            try { $conn->query("SELECT RELEASE_LOCK('worker_comande_lock')"); } catch(Exception $e){}
        } else {
            // Dopo tutti i retry non siamo riusciti ad acquisire lock; non processiamo nulla ora ma non crashiamo
            error_log("[$workerId] Impossibile acquisire lock globale dopo $retryLock tentativi, esco senza processare comande.");
            echo "[" . date('Y-m-d H:i:s') . "] [$workerId] Impossibile acquisire lock dopo $retryLock tentativi.\n";
            exit(0);
        }
    } else {
        // Se abbiamo preso ids con SKIP LOCKED, aggiorniamo quelle righe (incrementiamo Tentativi e imposta processing)
        if (!empty($ids)) {
            // Prepared UPDATE con IN (...)
            $in = implode(',', array_fill(0, count($ids), '?'));
            $sqlUpd = "UPDATE COMANDE SET Stato = 'processing', Tentativi = COALESCE(Tentativi,0) + 1 WHERE ID_Comanda IN ({$in})";
            $stmtUpd = $conn->prepare($sqlUpd);
            $stmtUpd->execute($ids);
        }
        $conn->commit();
    }

    if (empty($ids)) {
        echo "[" . date('Y-m-d H:i:s') . "] [$workerId] Nessuna comanda pending da processare.\n";
        exit(0);
    }

    // 2) Recupera i dettagli delle comande che abbiamo claimato
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmtFetch = $conn->prepare("SELECT * FROM COMANDE WHERE ID_Comanda IN ({$in}) ORDER BY Data_Creazione ASC");
    $stmtFetch->execute($ids);
    $comande = $stmtFetch->fetchAll(PDO::FETCH_ASSOC);

    // Prepara statement per aggiornamenti finali
    $stmtFinalUpdate = $conn->prepare(
        "UPDATE COMANDE
         SET Stato = :stato, Error_Message = :err, Data_Invio = CASE WHEN :stato = 'sent' THEN NOW() ELSE Data_Invio END
         WHERE ID_Comanda = :id_comanda"
    );

    // -------------------- Processamento di ciascuna comanda --------------------
    foreach ($comande as $c) {
        $id = (int)$c['ID_Comanda'];
        $nome_stampante = $c['Nome_Stampante_LAN'] ?? '';
        $testo = $c['Testo_Comanda'] ?? '';

        echo "[" . date('Y-m-d H:i:s') . "] [$workerId] Processing comanda $id -> printer '$nome_stampante'\n";

        // Validazione nome stampante
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $nome_stampante)) {
            $err = "Nome stampante non valido: $nome_stampante";
            error_log("[$workerId] $err (ID $id)");
            $stmtFinalUpdate->execute([':stato' => 'error', ':err' => $err, ':id_comanda' => $id]);
            continue;
        }

        // Modalità dry-run: simula la stampa senza eseguire lp
        if ($dryRun) {
            echo "[" . date('Y-m-d H:i:s') . "] [$workerId] [DRY-RUN] Simulazione stampa comanda $id su '$nome_stampante' (lunghezza testo: " . strlen($testo) . " bytes)\n";
            $stmtFinalUpdate->execute([':stato' => 'sent', ':err' => null, ':id_comanda' => $id]);
            msleep($sleepMs);
            continue;
        }

        // Se lp non è disponibile, non tentiamo la stampa: lasciamo la comanda in 'pending'
        if (!$lpAvailable) {
            $msg = "Comanda $id lasciata in pending: comando 'lp' non trovato.";
            error_log("[$workerId] $msg");
            // riportiamo a pending così un worker con lp disponibile potrà riprovare
            // Decrementiamo tentativi perché non è un vero fallimento di stampa
            $stmtRevertPending = $conn->prepare(
                "UPDATE COMANDE SET Stato = 'pending', Error_Message = :err, Tentativi = GREATEST(0, COALESCE(Tentativi,1) - 1) WHERE ID_Comanda = :id_comanda"
            );
            $stmtRevertPending->execute([':err' => 'lp non disponibile', ':id_comanda' => $id]);
            continue;
        }

        // Crea file temporaneo in modo sicuro
        $temp_file = tempnam(sys_get_temp_dir(), 'comanda_');
        if ($temp_file === false) {
            $err = "Impossibile creare file temporaneo per comanda $id";
            error_log("[$workerId] $err");
            // Riportiamo a pending invece di error per dare un'altra possibilità
            $stmtFinalUpdate->execute([':stato' => 'pending', ':err' => $err, ':id_comanda' => $id]);
            continue;
        }

        // Scrivi contenuto comanda
        $written = file_put_contents($temp_file, $testo);
        if ($written === false) {
            $err = "Impossibile scrivere su file temporaneo $temp_file per comanda $id";
            error_log("[$workerId] $err");
            @unlink($temp_file);
            // Riportiamo a pending invece di error per dare un'altra possibilità
            $stmtFinalUpdate->execute([':stato' => 'pending', ':err' => $err, ':id_comanda' => $id]);
            continue;
        }

        // Esegui il comando lp in modo sicuro
        // usa escapeshellarg sul path del binario e sugli argomenti
        $cmd = escapeshellarg($lpPath) . " -d " . escapeshellarg($nome_stampante) . " " . escapeshellarg($temp_file) . " 2>&1";
        $output = [];
        $returnVar = null;
        exec($cmd, $output, $returnVar);

        if ($returnVar === 0) {
            echo "[" . date('Y-m-d H:i:s') . "] [$workerId] Comanda $id inviata con successo a '$nome_stampante'.\n";
            $stmtFinalUpdate->execute([':stato' => 'sent', ':err' => null, ':id_comanda' => $id]);
        } else {
            $errText = implode("\n", $output);
            error_log("[$workerId] Errore stampa comanda $id su '$nome_stampante' (exit=$returnVar): $errText");
            // Imposta stato error; la comanda potrà essere riprocessata in seguito (Tentativi già incrementato)
            $stmtFinalUpdate->execute([':stato' => 'error', ':err' => $errText, ':id_comanda' => $id]);
        }

        if (file_exists($temp_file)) {
            @unlink($temp_file);
        }

        // Piccola pausa per non sovraccaricare CUPS
        msleep($sleepMs);
    }

    echo "[" . date('Y-m-d H:i:s') . "] [$workerId] Fine batch, processate " . count($comande) . " comande.\n";
    exit(0);

} catch (Exception $e) {
    // In caso di errore si tenta il rollback se possibile
    try { if ($conn->inTransaction()) $conn->rollBack(); } catch(Exception $xx){}
    error_log("[$workerId] Worker exception: " . $e->getMessage());
    echo "Worker exception: " . $e->getMessage() . "\n";
    exit(1);
}
?>
