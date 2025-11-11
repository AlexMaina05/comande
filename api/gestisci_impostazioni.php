<?php
// API per gestire le impostazioni dell'applicazione

// PROTEZIONE: Richiede autenticazione admin
require_once __DIR__ . '/require_admin.php';

require_once '../config/db_connection.php';
require_once __DIR__ . '/../src/Utils/InputValidator.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/error_handler.php';

// Determina l'azione richiesta
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Recupera tutte le impostazioni o una specifica
        $chiave = isset($_GET['chiave']) ? trim($_GET['chiave']) : null;
        
        if ($chiave) {
            // Recupera impostazione specifica
            $stmt = $conn->prepare("SELECT Chiave, Valore, Descrizione, Tipo FROM IMPOSTAZIONI WHERE Chiave = ?");
            $stmt->execute([$chiave]);
            $impostazione = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$impostazione) {
                ApiResponse::sendError("Impostazione non trovata", 4001, 404);
            }
            
            ApiResponse::sendSuccess($impostazione);
        } else {
            // Recupera tutte le impostazioni
            $stmt = $conn->query("SELECT Chiave, Valore, Descrizione, Tipo FROM IMPOSTAZIONI ORDER BY Chiave");
            $impostazioni = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ApiResponse::sendSuccess($impostazioni);
        }
    } elseif ($method === 'PUT' || $method === 'POST') {
        // Aggiorna un'impostazione esistente
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            ApiResponse::sendError('JSON non valido o vuoto', 4002, 400);
        }
        
        // Verifica campi richiesti
        $errors = InputValidator::require_fields(['chiave', 'valore'], $input);
        if ($errors) {
            ApiResponse::sendError('Dati mancanti', 4003, 400, $errors);
        }
        
        $chiave = trim($input['chiave']);
        $valore = trim($input['valore']);
        
        // Validazione lunghezza chiave
        if (!InputValidator::validate_length($chiave, 100)) {
            ApiResponse::sendError('Chiave troppo lunga (max 100 caratteri)', 4004, 400);
        }
        
        // Aggiorna l'impostazione
        $stmt = $conn->prepare("UPDATE IMPOSTAZIONI SET Valore = ? WHERE Chiave = ?");
        $stmt->execute([$valore, $chiave]);
        
        if ($stmt->rowCount() === 0) {
            ApiResponse::sendError("Impostazione non trovata o valore invariato", 4005, 404);
        }
        
        ApiResponse::sendSuccess(['message' => 'Impostazione aggiornata con successo', 'chiave' => $chiave]);
    } else {
        ApiResponse::sendError('Metodo non supportato', 4006, 405);
    }
} catch (PDOException $e) {
    error_log("Errore gestione impostazioni: " . $e->getMessage());
    ApiResponse::sendError('Errore del server durante la gestione delle impostazioni', 4007, 500);
}
?>
