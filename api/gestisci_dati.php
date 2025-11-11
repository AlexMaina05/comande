<?php
/**
 * API per gestione dati admin e funzionalità di export/backup
 * 
 * Supporta due modalità:
 * 1. ?section=<table> - Ritorna dati per admin panel
 * 2. ?action=export&table=<table>&format=<csv|sql> - Export/backup dati
 */

session_start();

// Include il file di connessione al database
require_once '../config/db_connection.php';

// Include la classe InputValidator per validazione input (Issue #5)
require_once __DIR__ . '/../src/Utils/InputValidator.php';

// Include l'helper per le risposte API
require_once __DIR__ . '/response.php';

// Include il gestore centralizzato degli errori (Issue #6)
require_once __DIR__ . '/error_handler.php';

// --- FUNZIONE HELPER PER VERIFICARE SE L'UTENTE È ADMIN ---
function is_admin() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

// --- FUNZIONE PER LOGGARE LE ESPORTAZIONI ---
function log_export($table, $format, $success = true, $error_msg = null) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/export.log';
    $timestamp = date('Y-m-d H:i:s');
    $user = isset($_SESSION['loggedin']) ? 'admin' : 'unknown';
    $status = $success ? 'SUCCESS' : 'FAILED';
    $error_info = $error_msg ? " - Error: $error_msg" : '';
    
    $log_entry = "[$timestamp] $status - User: $user, Table: $table, Format: $format$error_info\n";
    error_log($log_entry, 3, $log_file);
}

// --- WHITELIST DELLE TABELLE SUPPORTATE ---
$table_whitelist = [
    'prodotti' => 'PRODOTTI',
    'ordini' => 'ORDINI',
    'comande' => 'COMANDE'
];

// --- GESTIONE DELLE RICHIESTE ---

// Determina il tipo di richiesta
$action = $_GET['action'] ?? null;
$section = $_GET['section'] ?? null;

// --- MODALITÀ 1: EXPORT ---
if ($action === 'export') {
    
    // Verifica autenticazione admin
    if (!is_admin()) {
        log_export('unknown', 'unknown', false, 'Unauthorized access attempt');
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => ['code' => 4001, 'message' => 'Accesso non autorizzato']]);
        exit;
    }
    
    // Validazione parametri
    $table_key = $_GET['table'] ?? '';
    $format = $_GET['format'] ?? '';
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : null;
    
    // Validazione whitelist table
    if (!isset($table_whitelist[$table_key])) {
        log_export($table_key, $format, false, 'Invalid table name');
        ApiResponse::sendError('Tabella non valida', 4002, 400);
    }
    
    $table_name = $table_whitelist[$table_key];
    
    // Validazione formato
    if (!in_array($format, ['csv', 'sql'], true)) {
        log_export($table_key, $format, false, 'Invalid format');
        ApiResponse::sendError('Formato non valido. Usa csv o sql', 4003, 400);
    }
    
    try {
        // Ottieni struttura della tabella per export
        $stmt = $conn->prepare("DESCRIBE " . $table_name);
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($columns)) {
            throw new Exception("Impossibile ottenere struttura tabella");
        }
        
        $column_names = array_column($columns, 'Field');
        
        // --- EXPORT CSV ---
        if ($format === 'csv') {
            // Set headers per download CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $table_key . '_' . date('Y-m-d_His') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Output diretto per streaming
            $output = fopen('php://output', 'w');
            
            // BOM per UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Intestazione CSV
            fputcsv($output, $column_names);
            
            // Query con limit opzionale
            $sql = "SELECT * FROM " . $table_name;
            if ($limit !== null) {
                $sql .= " LIMIT " . $limit;
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            // Stream delle righe
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            log_export($table_key, $format, true);
            exit;
        }
        
        // --- EXPORT SQL ---
        if ($format === 'sql') {
            // Set headers per download SQL
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $table_key . '_' . date('Y-m-d_His') . '.sql"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Output diretto
            echo "-- Export SQL per tabella: $table_name\n";
            echo "-- Generato il: " . date('Y-m-d H:i:s') . "\n";
            echo "-- Formato: INSERT statements\n\n";
            
            // Query con limit opzionale
            $sql = "SELECT * FROM " . $table_name;
            if ($limit !== null) {
                $sql .= " LIMIT " . $limit;
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            // Genera INSERT statements
            $batch_size = 100;
            $batch_count = 0;
            $values = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $escaped_values = array_map(function($val) use ($conn) {
                    if ($val === null) return 'NULL';
                    return $conn->quote($val);
                }, $row);
                
                $values[] = '(' . implode(', ', $escaped_values) . ')';
                $batch_count++;
                
                // Output batch ogni 100 righe
                if ($batch_count >= $batch_size) {
                    echo "INSERT INTO $table_name (" . implode(', ', $column_names) . ") VALUES\n";
                    echo implode(",\n", $values) . ";\n\n";
                    
                    $values = [];
                    $batch_count = 0;
                    flush();
                }
            }
            
            // Output ultimo batch
            if (!empty($values)) {
                echo "INSERT INTO $table_name (" . implode(', ', $column_names) . ") VALUES\n";
                echo implode(",\n", $values) . ";\n";
            }
            
            log_export($table_key, $format, true);
            exit;
        }
        
    } catch (Exception $e) {
        log_export($table_key, $format, false, $e->getMessage());
        error_log("Errore export: " . $e->getMessage());
        ApiResponse::sendError('Errore durante l\'export: ' . $e->getMessage(), 5001, 500);
    }
}

// --- MODALITÀ 2: CARICAMENTO DATI PER ADMIN PANEL ---
if ($section) {
    
    // Verifica autenticazione admin
    if (!is_admin()) {
        ApiResponse::sendError('Accesso non autorizzato', 4001, 403);
    }
    
    try {
        $data = [];
        
        switch ($section) {
            case 'prodotti':
                $stmt = $conn->prepare("SELECT ID_Prodotto, Codice_Prodotto, Descrizione, Prezzo FROM PRODOTTI ORDER BY Descrizione");
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'tavoli':
                $stmt = $conn->prepare("SELECT ID_Tavolo, Nome_Tavolo, Tipo_Servizio FROM TAVOLI ORDER BY Nome_Tavolo");
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'categorie':
                $stmt = $conn->prepare("SELECT ID_Categoria, Nome_Categoria FROM CATEGORIE ORDER BY Nome_Categoria");
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'reparti':
                $stmt = $conn->prepare("SELECT ID_Reparto, Nome_Reparto, Nome_Stampante_LAN FROM REPARTI ORDER BY Nome_Reparto");
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            default:
                ApiResponse::sendError('Sezione non valida', 4004, 400);
        }
        
        ApiResponse::sendSuccess($data);
        
    } catch (PDOException $e) {
        error_log("Errore caricamento dati admin: " . $e->getMessage());
        ApiResponse::sendError('Errore interno del server', 5002, 500);
    }
}

// Se nessuna azione riconosciuta
ApiResponse::sendError('Parametri richiesta non validi. Usa action=export o section=<nome>', 4005, 400);
?>
