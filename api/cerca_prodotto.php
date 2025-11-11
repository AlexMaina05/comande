<?php
// Include il file di connessione al database.
require_once '../config/db_connection.php';

// Include la classe InputValidator per validazione input (Issue #5)
require_once __DIR__ . '/../src/Utils/InputValidator.php';

// Include l'helper per le risposte API
require_once __DIR__ . '/response.php';

// Include il gestore centralizzato degli errori (Issue #6)
require_once __DIR__ . '/error_handler.php';

// --- VALIDAZIONE INPUT (Issue #5) ---
// Verifica che il campo 'codice' sia presente
$errors = InputValidator::require_fields(['codice'], $_GET);
if ($errors) {
    ApiResponse::sendError('Codice prodotto non fornito', 1004, 400, $errors);
}

// Pulizia e validazione input
$codice_prodotto = trim((string)($_GET['codice']));

// Validazione tipo stringa e lunghezza
if (!InputValidator::validate_type($codice_prodotto, 'string')) {
    ApiResponse::sendError('Codice prodotto deve essere una stringa', 1005, 400);
}
if (!InputValidator::validate_length($codice_prodotto, 64)) {
    ApiResponse::sendError('Codice prodotto troppo lungo (max 64 caratteri)', 1006, 400);
}
if ($codice_prodotto === '') {
    ApiResponse::sendError('Codice prodotto non può essere vuoto', 1007, 400);
}

try {
    // --- PREPARAZIONE E ESECUZIONE DELLA QUERY ---
    // Prepara una query per selezionare il prodotto che corrisponde al codice fornito.
    // Usare un prepared statement (con il punto di domanda ?) è fondamentale per la sicurezza
    // contro attacchi di tipo SQL Injection.
    $sql = "SELECT ID_Prodotto, Descrizione, Prezzo FROM PRODOTTI WHERE Codice_Prodotto = ?";
    
    $stmt = $conn->prepare($sql);
    
    // Esegue la query, passando il codice prodotto come parametro.
    $stmt->execute([$codice_prodotto]);
    
    // Recupera il risultato. fetch() restituisce una singola riga o 'false' se non trova nulla.
    $prodotto = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- INVIO DELLA RISPOSTA ---
    if ($prodotto) {
        // Forza tipi semplici (es. prezzo come float) e invia JSON
        if (isset($prodotto['Prezzo'])) {
            $prodotto['Prezzo'] = (float) $prodotto['Prezzo'];
        }
        ApiResponse::sendSuccess($prodotto);
    } else {
        // Se fetch() ha restituito 'false', significa che il prodotto non esiste.
        // Invia un errore 404 (Not Found) al frontend.
        ApiResponse::sendError('Prodotto non trovato.', 1008, 404);
    }

} catch (PDOException $e) {
    // Gestisce eventuali errori del database durante l'esecuzione della query.
    // Logga l'errore reale sul log del server
    error_log("Errore ricerca prodotto: " . $e->getMessage());
    
    // Invia risposta di errore usando l'helper
    ApiResponse::sendError('Errore interno del server.', 2002, 500);
}
?>
