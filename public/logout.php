<?php
// Avvia la sessione per poterla manipolare
session_start();

// Pulisce le variabili di sessione
$_SESSION = [];

// Se la sessione usa cookie, elimina il cookie sul client
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Distrugge la sessione sul server
session_unset();
session_destroy();

// Reindirizza l'utente alla pagina di login
header("Location: login.php");
exit;
?>

