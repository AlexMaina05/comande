<?php
// Avvia la sessione per poter gestire i messaggi di errore
session_start();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accesso Area Riservata</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">

    <div class="login-container">
        <h1>🔐 Area Riservata</h1>
        <p>Inserisci la password per continuare.</p>

        <?php
        // Mostra un messaggio di errore se il login precedente è fallito
        if (isset($_SESSION['login_error'])) {
            // Escapa l'output e segnala ai lettori di schermo che è un messaggio urgente
            $msg = htmlspecialchars($_SESSION['login_error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<div class="error-message" role="alert" aria-live="assertive">' . $msg . '</div>';
             // Pulisci il messaggio di errore per non mostrarlo di nuovo
             unset($_SESSION['login_error']);
         }
        ?>

        <form action="../config/check_login.php" method="POST" class="login-form">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required autofocus>
            <button type="submit" class="btn-primario">Accedi</button>
        </form>
        <a href="index.html" class="link-indietro">&larr; Torna alla Home</a>
    </div>

</body>
</html>

