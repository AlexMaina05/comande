<?php
// PROTEZIONE: Richiede autenticazione admin per ristampare comande
require_once __DIR__ . '/require_admin.php';

require_once '../config/db_connection.php';

// Include la classe InputValidator per validazione input (Issue #5)
require_once __DIR__ . '/../src/Utils/InputValidator.php';

// Include l'helper per le risposte API
require_once __DIR__ . '/response.php';

// Include il gestore centralizzato degli errori (Issue #6)
require_once __DIR__ . '/error_handler.php';

// --- VALIDAZIONE INPUT (Issue #5) ---
// Input: { "id_comanda": 123 }
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    ApiResponse::sendError('JSON non valido o vuoto', 1023, 400);
}

// Verifica campi richiesti
$errors = InputValidator::require_fields(['id_comanda'], $input);
if ($errors) {
    ApiResponse::sendError('ID comanda mancante', 1024, 400, $errors);
}

// Validazione tipo e range
if (!InputValidator::validate_type($input['id_comanda'], 'int')) {
    ApiResponse::sendError('ID comanda deve essere un intero', 1025, 400);
}

$id_comanda = intval($input['id_comanda']);

if (!InputValidator::validate_range($id_comanda, 1, 999999999)) {
    ApiResponse::sendError('ID comanda deve essere un intero positivo valido', 1026, 400);
}

try {
    // Recupera la comanda
    $stmt = $conn->prepare("SELECT ID_Comanda, ID_Ordine, Nome_Stampante_LAN, Testo_Comanda FROM COMANDE WHERE ID_Comanda = ?");
    $stmt->execute([$id_comanda]);
    $comanda = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$comanda) {
        ApiResponse::sendError('Comanda non trovata', 1027, 404);
    }

    // Verifica disponibilità funzioni shell
    if (!function_exists('exec') && !function_exists('shell_exec')) {
        ApiResponse::sendError('Funzioni shell non disponibili', 2004, 500);
    }

    // Trova lp
    $lpPath = '';
    if (function_exists('shell_exec')) {
        $lpPath = trim(shell_exec('command -v lp 2>/dev/null') ?: '');
    } elseif (function_exists('exec')) {
        $whichOutput = [];
        $whichReturn = null;
        exec('command -v lp 2>/dev/null', $whichOutput, $whichReturn);
        $lpPath = trim(implode("\n", $whichOutput));
        if ($whichReturn !== 0 && $whichReturn !== null) {
            error_log("ripeti_comanda.php: errore durante la ricerca del comando lp: exit code $whichReturn");
        }
    }
    if ($lpPath === '') {
        error_log("ripeti_comanda.php: comando lp non trovato sul server");
        ApiResponse::sendError('Comando lp non trovato sul server', 2005, 500);
    }

    // Prepara file temporaneo
    $temp_file = tempnam(sys_get_temp_dir(), 'comanda_');
    if ($temp_file === false) {
        ApiResponse::sendError('Impossibile creare file temporaneo', 2006, 500);
    }

    if (file_put_contents($temp_file, $comanda['Testo_Comanda']) === false) {
        @unlink($temp_file);
        ApiResponse::sendError('Impossibile scrivere nel file temporaneo', 2007, 500);
    }

    $nome_stampante = $comanda['Nome_Stampante_LAN'];

    // Costruisci comando in modo sicuro
    $cmd = escapeshellarg($lpPath) . " -d " . escapeshellarg($nome_stampante) . " " . escapeshellarg($temp_file) . " 2>&1";

    $output = [];
    $ret = null;

    exec($cmd, $output, $ret);

    // Aggiorna stato in tabella
    $stmtUpdate = $conn->prepare("
      UPDATE COMANDE
      SET Stato = :stato, Error_Message = :err, Tentativi = COALESCE(Tentativi,0) + 1, Data_Invio = CASE WHEN :stato = 'sent' THEN NOW() ELSE Data_Invio END
      WHERE ID_Comanda = :id_comanda
    ");

    if ($ret === 0) {
        error_log("ripeti_comanda.php: comanda ID {$id_comanda} ristampata con successo");
        $stmtUpdate->execute([':stato' => 'sent', ':err' => null, ':id_comanda' => $id_comanda]);
        @unlink($temp_file);
        ApiResponse::sendSuccess(['message' => 'Comanda ristampata con successo']);
    } else {
        $errMsg = implode("\n", $output);
        // Log server-side with full details
        error_log("ripeti_comanda.php: errore lp per comanda ID {$id_comanda} (exit=$ret): $errMsg");
        $stmtUpdate->execute([':stato' => 'error', ':err' => $errMsg, ':id_comanda' => $id_comanda]);
        @unlink($temp_file);
        ApiResponse::sendError('Errore durante ristampa: ' . $errMsg, 2008, 500);
    }
} catch (Exception $e) {
    error_log("ripeti_comanda.php exception: " . $e->getMessage());
    ApiResponse::sendError('Errore server', 2009, 500);
}
?>
