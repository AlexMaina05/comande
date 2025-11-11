<?php
// PROTEZIONE: Richiede autenticazione admin per accedere ai report
require_once __DIR__ . '/require_admin.php';

require_once '../config/db_connection.php';

// Include la classe InputValidator per validazione input
require_once __DIR__ . '/../src/Utils/InputValidator.php';

// Include l'helper per le risposte API
require_once __DIR__ . '/response.php';

// Include il gestore centralizzato degli errori (Issue #6)
require_once __DIR__ . '/error_handler.php';

// Input validation: data YYYY-MM-DD
$errors = InputValidator::require_fields(['data'], $_GET);
if ($errors) {
    ApiResponse::sendError('Data non fornita', 1028, 400, $errors);
}

$data_selezionata = $_GET['data'];

// Validazione formato data YYYY-MM-DD
if (!InputValidator::validate_type($data_selezionata, 'date')) {
    ApiResponse::sendError('Formato data non valido. Usa YYYY-MM-DD', 1029, 400);
}

// usa range per sfruttare eventuali indici sulla colonna Data_Ora
$dt = DateTime::createFromFormat('Y-m-d', $data_selezionata);
$start = $data_selezionata . ' 00:00:00';
$endDt = (clone $dt)->modify('+1 day');
$end = $endDt->format('Y-m-d') . ' 00:00:00';

try {
    // Riepilogo: LEFT JOIN per includere ordini senza tavolo (ASPORTO)
    // Esclude ordini staff dal totale
    $sql_riepilogo = "SELECT 
                        COALESCE(t.Tipo_Servizio, 'ASPORTO') AS Tipo_Servizio,
                        SUM(o.Totale_Ordine) AS Incasso_Parziale,
                        SUM(o.Numero_Coperti) AS Coperti_Parziali
                      FROM 
                        ORDINI o
                      LEFT JOIN 
                        TAVOLI t ON o.ID_Tavolo = t.ID_Tavolo
                      WHERE 
                        o.Data_Ora >= :start AND o.Data_Ora < :end
                        AND o.Staff = 0
                      GROUP BY 
                        COALESCE(t.Tipo_Servizio, 'ASPORTO')";

    $stmt_riepilogo = $conn->prepare($sql_riepilogo);
    $stmt_riepilogo->bindValue(':start', $start, PDO::PARAM_STR);
    $stmt_riepilogo->bindValue(':end', $end, PDO::PARAM_STR);
    $stmt_riepilogo->execute();
    $riepilogo_servizio = $stmt_riepilogo->fetchAll(PDO::FETCH_ASSOC);

    // Dettaglio prodotti venduti (range identico)
    // Esclude prodotti da ordini staff
    $sql_dettaglio = "SELECT 
                        p.Descrizione, 
                        SUM(d.Quantita) AS Totale_Venduto
                      FROM 
                        DETTAGLI_ORDINE d
                      JOIN 
                        ORDINI o ON d.ID_Ordine = o.ID_Ordine
                      JOIN 
                        PRODOTTI p ON d.ID_Prodotto = p.ID_Prodotto
                      WHERE 
                        o.Data_Ora >= :start AND o.Data_Ora < :end
                        AND o.Staff = 0
                      GROUP BY 
                        p.Descrizione
                      ORDER BY 
                        Totale_Venduto DESC";

    $stmt_dettaglio = $conn->prepare($sql_dettaglio);
    $stmt_dettaglio->bindValue(':start', $start, PDO::PARAM_STR);
    $stmt_dettaglio->bindValue(':end', $end, PDO::PARAM_STR);
    $stmt_dettaglio->execute();
    $dettaglio_prodotti = $stmt_dettaglio->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'riepilogo_servizio' => $riepilogo_servizio,
        'dettaglio_prodotti' => $dettaglio_prodotti
    ];

    ApiResponse::sendSuccess($response);

} catch (PDOException $e) {
    error_log("Errore generazione report: " . $e->getMessage());
    ApiResponse::sendError('Errore interno del server durante la generazione del report.', 2010, 500);
}
?>
