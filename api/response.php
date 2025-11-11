<?php
/**
 * API Response Helper
 * 
 * Fornisce un formato di risposta JSON uniforme per tutte le API
 * e normalizza la gestione degli errori.
 * 
 * Schema di risposta:
 * {
 *   "success": true/false,
 *   "data": {...},        // presente solo se success=true
 *   "error": {            // presente solo se success=false
 *     "code": 500,
 *     "message": "..."
 *   }
 * }
 * 
 * Uso:
 *   // Funzioni procedurali (Issue #6)
 *   json_response(true, ['result' => 'ok'], null, 200);
 *   json_response(false, null, api_error(1001, 'Invalid input'), 400);
 * 
 *   // Oppure tramite classe (metodo precedente, ancora supportato)
 *   ApiResponse::sendSuccess(['result' => 'ok']);
 *   ApiResponse::sendError('Invalid input', 1001, 400);
 */

/**
 * Crea un array di errore con la struttura standard
 * 
 * @param int $code Codice di errore applicativo
 * @param string $message Messaggio di errore
 * @return array Array con struttura error standard
 */
function api_error(int $code, string $message): array
{
    return [
        'code' => $code,
        'message' => $message
    ];
}

/**
 * Invia una risposta JSON uniforme e termina lo script
 * 
 * @param bool $success True per successo, false per errore
 * @param mixed $data Dati da restituire (solo se success=true)
 * @param array|null $error Array di errore da api_error() (solo se success=false)
 * @param int $status Codice HTTP di risposta (default 200)
 * @return void
 */
function json_response(bool $success, $data = null, ?array $error = null, int $status = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    http_response_code($status);
    
    $response = ['success' => $success];
    
    if ($success) {
        $response['data'] = $data;
    } else {
        $response['error'] = $error;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

class ApiResponse
{
    /**
     * Imposta gli header JSON e disabilita la cache
     */
    public static function setJsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * Invia una risposta di successo
     * 
     * @param mixed $data Dati da restituire (può essere array, oggetto, stringa, ecc.)
     * @param int $httpCode Codice HTTP (default 200)
     */
    public static function sendSuccess($data = null, int $httpCode = 200): void
    {
        json_response(true, $data, null, $httpCode);
    }

    /**
     * Invia una risposta di errore
     * 
     * @param string $message Messaggio di errore
     * @param int $code Codice di errore applicativo (default 500)
     * @param int $httpCode Codice HTTP (default 400)
     * @param array|null $details Dettagli aggiuntivi opzionali
     */
    public static function sendError(string $message, int $code = 500, int $httpCode = 400, array $details = null): void
    {
        $error = api_error($code, $message);
        if ($details !== null && !empty($details)) {
            $error['details'] = $details;
        }
        json_response(false, null, $error, $httpCode);
    }
}
?>
