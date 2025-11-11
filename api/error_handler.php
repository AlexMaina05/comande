<?php
/**
 * Centralized Error Handler for API
 * 
 * Questo file imposta handler globali per gestire eccezioni, errori PHP
 * e fatal errors in modo uniforme, restituendo sempre risposte JSON
 * con il formato standard.
 * 
 * Da includere all'inizio di ogni endpoint API dopo response.php:
 *   require_once __DIR__ . '/response.php';
 *   require_once __DIR__ . '/error_handler.php';
 * 
 * Issue #6 - Uniform API responses and error handling
 */

// Assicurati che response.php sia già incluso
if (!function_exists('json_response')) {
    require_once __DIR__ . '/response.php';
}

/**
 * Handler per le eccezioni non gestite
 * Converte tutte le eccezioni in risposte JSON uniformi
 */
set_exception_handler(function (Throwable $exception) {
    // Log dell'errore per il server
    error_log(
        "API Exception: " . $exception->getMessage() . 
        " in " . $exception->getFile() . 
        " line " . $exception->getLine()
    );
    
    // Determina il codice HTTP appropriato
    $httpCode = 500;
    if (method_exists($exception, 'getCode')) {
        $exceptionCode = $exception->getCode();
        // Se il codice è un HTTP code valido (4xx, 5xx), usalo
        if ($exceptionCode >= 400 && $exceptionCode < 600) {
            $httpCode = $exceptionCode;
        }
    }
    
    // Invia risposta di errore
    json_response(
        false,
        null,
        api_error(5000, 'Errore interno del server'),
        $httpCode
    );
});

/**
 * Handler per gli errori PHP (warning, notice, ecc.)
 * Converte gli errori PHP in eccezioni per la gestione uniforme
 */
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    // Non gestire errori soppressi con @
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    // Log dell'errore
    error_log("PHP Error [$errno]: $errstr in $errfile line $errline");
    
    // Lancia un'eccezione che verrà gestita dall'exception handler
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

/**
 * Handler per fatal errors (shutdown function)
 * Cattura fatal errors che non vengono gestiti dall'error handler normale
 */
register_shutdown_function(function () {
    $error = error_get_last();
    
    // Controlla se c'è un fatal error
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Log dell'errore
        error_log(
            "Fatal Error: {$error['message']} in {$error['file']} line {$error['line']}"
        );
        
        // Pulisci eventuali output buffer
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Invia risposta di errore
        json_response(
            false,
            null,
            api_error(5001, 'Errore critico del server'),
            500
        );
    }
});
