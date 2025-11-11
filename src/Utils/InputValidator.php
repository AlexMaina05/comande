<?php
/**
 * InputValidator - Utility per validazione e sanificazione input server-side
 * 
 * Questa classe fornisce metodi statici per validare e sanificare input utente,
 * garantire coerenza del database e generare risposte JSON uniformi.
 * 
 * Riferimento: Issue #5
 * https://github.com/AlexMaina05/RICEVUTE/issues/5
 */
class InputValidator
{
    /**
     * Verifica che tutti i campi richiesti siano presenti nell'input
     * 
     * @param array $fields Array di nomi di campi richiesti
     * @param array $input Array associativo contenente i dati di input
     * @return array|null Restituisce array di errori se mancano campi, null se tutto ok
     * 
     * Esempio d'uso:
     *   $errors = InputValidator::require_fields(['nome', 'email'], $_POST);
     *   if ($errors) {
     *       InputValidator::json_error('Campi mancanti', $errors);
     *   }
     */
    public static function require_fields(array $fields, array $input): ?array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
                $errors[] = "Campo '$field' mancante o vuoto";
            }
        }
        return empty($errors) ? null : $errors;
    }

    /**
     * Valida il tipo di un valore
     * 
     * @param mixed $value Valore da validare
     * @param string $type Tipo atteso: 'int', 'float', 'string', 'email', 'date'
     * @return bool True se il valore è del tipo corretto
     * 
     * Esempio d'uso:
     *   if (!InputValidator::validate_type($id, 'int')) {
     *       InputValidator::json_error('ID deve essere un intero');
     *   }
     */
    public static function validate_type($value, string $type): bool
    {
        switch ($type) {
            case 'int':
                return filter_var($value, FILTER_VALIDATE_INT) !== false;
            
            case 'float':
                return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
            
            case 'string':
                return is_string($value);
            
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            
            case 'date':
                // Valida formato YYYY-MM-DD
                $dt = DateTime::createFromFormat('Y-m-d', $value);
                return $dt && $dt->format('Y-m-d') === $value;
            
            default:
                return false;
        }
    }

    /**
     * Valida la lunghezza di una stringa
     * 
     * @param string $value Stringa da validare
     * @param int $max Lunghezza massima consentita
     * @return bool True se la stringa rispetta il limite
     * 
     * Esempio d'uso:
     *   if (!InputValidator::validate_length($nome, 100)) {
     *       InputValidator::json_error('Nome troppo lungo (max 100 caratteri)');
     *   }
     */
    public static function validate_length(string $value, int $max): bool
    {
        return strlen($value) <= $max;
    }

    /**
     * Valida che un valore numerico sia in un range
     * 
     * @param float|int $value Valore da validare
     * @param float|int|null $min Valore minimo (null = nessun minimo)
     * @param float|int|null $max Valore massimo (null = nessun massimo)
     * @return bool True se il valore è nel range
     * 
     * Esempio d'uso:
     *   if (!InputValidator::validate_range($prezzo, 0, 9999.99)) {
     *       InputValidator::json_error('Prezzo deve essere tra 0 e 9999.99');
     *   }
     */
    public static function validate_range($value, $min = null, $max = null): bool
    {
        if ($min !== null && $value < $min) {
            return false;
        }
        if ($max !== null && $value > $max) {
            return false;
        }
        return true;
    }

    /**
     * Sanifica una stringa per output HTML
     * 
     * @param string $value Stringa da sanificare
     * @return string Stringa sanificata con htmlspecialchars
     * 
     * Esempio d'uso:
     *   echo InputValidator::sanitize_for_html($user_input);
     */
    public static function sanitize_for_html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Invia una risposta JSON di errore e termina l'esecuzione
     * 
     * @param string $message Messaggio di errore principale
     * @param array $details Dettagli aggiuntivi (opzionale)
     * @param int $code Codice HTTP (default 400)
     * 
     * Esempio d'uso:
     *   InputValidator::json_error('Input non valido', ['campo' => 'ID mancante'], 400);
     */
    public static function json_error(string $message, array $details = [], int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = ['error' => $message];
        if (!empty($details)) {
            $response['details'] = $details;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
