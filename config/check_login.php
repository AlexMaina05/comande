<?php
/**
 * Questo script gestisce il processo di login.
 * Non ha output HTML, il suo unico scopo è controllare la password
 * e reindirizzare l'utente.
 */

// Imposta parametri cookie sessione PRIMA di session_start()
// In produzione setta 'secure' => true se servi via HTTPS
$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// 1. AVVIA LA SESSIONE
// La sessione è un meccanismo che permette al server di "ricordare"
// informazioni su un utente tra una pagina e l'altra.
// È fondamentale per tenere traccia del fatto che l'utente ha effettuato il login.
session_start();


// 2. DEFINISCI LA PASSWORD DI ACCESSO
// Questa è l'unica riga che devi modificare per cambiare la password.
// Per un uso su rete locale (LAN), questo metodo è sufficientemente sicuro.
$password_corretta = "admin123"; 


// 3. CONTROLLA COME L'UTENTE È ARRIVATO A QUESTA PAGINA
// Verifichiamo che la richiesta sia di tipo 'POST' (cioè inviata dal nostro form)
// e che il campo 'password' esista e non sia vuoto.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password'])) {
    
    // La richiesta è valida, procediamo.
    $password_inserita = (string) ($_POST['password']);

    // 4. CONFRONTA LE PASSWORD
    // Confrontiamo la password inviata dall'utente con quella che abbiamo definito.
    if (hash_equals($password_corretta, $password_inserita)) {
        // Login riuscito: rigenera sessione per prevenire session fixation
        session_regenerate_id(true);
        
        // --- CASO SUCCESSO: PASSWORD CORRETTA ---

        // A. Registra nella sessione che l'utente è autenticato.
        // Questo è il "timbro sulla mano" che gli permetterà di accedere ad admin.php.
        $_SESSION['loggedin'] = true;
        
        // B. (Opzionale) Rimuovi eventuali messaggi di errore precedenti dalla sessione.
        unset($_SESSION['login_error']);

        // C. Reindirizza il browser dell'utente alla pagina di amministrazione.
        header("Location: ../public/admin.php");
        
        // D. Interrompi l'esecuzione dello script. È importante dopo un reindirizzamento.
        exit;

    } else {
        
        // --- CASO FALLIMENTO: PASSWORD SBAGLIATA ---

        // A. Registra un messaggio di errore nella sessione.
        // Questo messaggio verrà poi mostrato nella pagina di login.
        $_SESSION['login_error'] = "Password non corretta. Riprova.";
        
        // B. Reindirizza il browser dell'utente di nuovo alla pagina di login.
        header("Location: ../public/login.php");
        
        // C. Interrompi l'esecuzione.
        exit;
    }

} else {
    
    // 5. GESTISCI ACCESSO DIRETTO
    // Se un utente prova ad accedere a questo file scrivendo l'URL nel browser
    // (quindi non tramite il form), lo reindirizziamo alla home page.
    header("Location: ../public/index.html");
    exit;
}
?>


