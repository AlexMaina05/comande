<?php
/**
 * Questo file gestisce la connessione al database MariaDB.
 * Deve essere incluso da tutti gli altri script che hanno bisogno di accedere al database.
 */

// --- IMPOSTAZIONI DEL DATABASE: MODIFICA QUESTE VARIABILI ---

// Indirizzo del server del database. Visto che il sito e il DB sono sullo stesso NAS,
// molto probabilmente l'indirizzo corretto è "localhost" o "127.0.0.1".
$servername = "localhost"; 

// Il nome utente per accedere al database. Spesso è "root" o un utente
// che hai creato appositamente tramite phpMyAdmin sul tuo NAS.
$username = "root"; 

// La password associata all'utente del database.
$password = "la_tua_password"; // <--- CAMBIA QUESTA PASSWORD

// Il nome del database che hai creato per questa applicazione.
$dbname = "ristorante_db"; // <--- CAMBIA SE HAI USATO UN NOME DIVERSO

// -------------------------------------------------------------------

// Tentativo di connessione al database usando PDO (PHP Data Objects).
// PDO è il metodo moderno e sicuro per connettersi ai database in PHP.
try {
    $dsn = "mysql:host={$servername};dbname={$dbname};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // PDO::ATTR_PERSISTENT => true, // abilitare solo se necessario
    ];

    $conn = new PDO($dsn, $username, $password, $options);
} catch(PDOException $e) {
    // Se la connessione fallisce, il blocco 'try' viene interrotto e viene eseguito questo blocco 'catch'.
    
    // Log dell'errore per il server
    error_log("Errore connessione DB: " . $e->getMessage());

    // Verifica se siamo in un contesto API
    $isApiContext = (
        defined('IS_API') || 
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );

    if ($isApiContext) {
        // Contesto API: usa l'helper se disponibile, altrimenti JSON generico
        if (file_exists(__DIR__ . '/api/response.php')) {
            require_once __DIR__ . '/api/response.php';
            ApiResponse::sendError('Errore di connessione al database', 3001, 500);
        } else {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => ['code' => 3001, 'message' => 'Errore di connessione al database']]);
            exit;
        }
    } else {
        // Contesto HTML: lancia un'eccezione che può essere intercettata dalla pagina
        throw new Exception("Errore di connessione al database. Contatta l'amministratore.");
    }
}

// Se lo script arriva a questo punto, significa che la variabile $conn
// contiene una connessione al database valida e pronta per essere usata.
?>
