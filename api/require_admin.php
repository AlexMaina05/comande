<?php
/**
 * Helper per verificare l'autenticazione admin nelle API
 * 
 * Include questo file all'inizio di qualsiasi API che richiede
 * autenticazione admin per prevenire accessi non autorizzati.
 * 
 * Uso:
 *   require_once __DIR__ . '/require_admin.php';
 */

// Avvia la sessione se non è già stata avviata
if (session_status() === PHP_SESSION_NONE) {
    // Imposta parametri cookie sessione sicuri
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Verifica se l'utente è autenticato come admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // L'utente non è autenticato
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    
    $error_response = [
        'success' => false,
        'error' => [
            'code' => 4001,
            'message' => 'Accesso non autorizzato. Autenticazione richiesta.'
        ]
    ];
    
    echo json_encode($error_response);
    exit;
}

// Se lo script arriva qui, l'utente è autenticato e può proseguire
?>
