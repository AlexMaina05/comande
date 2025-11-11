<?php
// Include il file di connessione al database.
require_once '../config/db_connection.php';

// Include la classe InputValidator per validazione input (Issue #5)
require_once __DIR__ . '/../src/Utils/InputValidator.php';

// Include la classe MoneyHelper per calcoli monetari precisi (Issue #2)
require_once __DIR__ . '/../src/Utils/MoneyHelper.php';

// Include l'helper per le risposte API
require_once __DIR__ . '/response.php';

// Include il gestore centralizzato degli errori (Issue #6)
require_once __DIR__ . '/error_handler.php';

// --- LETTURA DEI DATI INVIATI DAL FRONTEND ---
// Legge il corpo della richiesta POST, che contiene i dati dell'ordine in formato JSON.
$input = json_decode(file_get_contents('php://input'), true);

// --- VALIDAZIONE BASE DEI DATI RICEVUTI (Issue #5) ---
if (!$input) {
    ApiResponse::sendError('JSON non valido o vuoto', 1009, 400);
}

// Verifica campi richiesti
$errors = InputValidator::require_fields(['dettagli'], $input);
if ($errors) {
    ApiResponse::sendError('Dati dell\'ordine non validi o mancanti', 1010, 400, $errors);
}

// Verifica che dettagli sia un array non vuoto
if (!is_array($input['dettagli']) || empty($input['dettagli'])) {
    ApiResponse::sendError('Dettagli ordine devono essere un array non vuoto', 1011, 400);
}

// Estrae i dati principali per comodità
$nome_cliente = isset($input['nome_cliente']) ? trim($input['nome_cliente']) : 'Cliente';
$id_tavolo = $input['id_tavolo'] ?? null;
$num_coperti = $input['numero_coperti'] ?? 0;
$totale_ordine = $input['totale'] ?? 0.00;
$sconto = $input['sconto'] ?? 0.00;
$staff = $input['staff'] ?? false; // Flag per ordini staff
$dettagli = $input['dettagli']; // Questo array contiene anche le descrizioni

// Recupera il costo coperto dalle impostazioni (in centesimi per precisione)
$costo_coperto_cents = 0;
try {
    $stmtCosto = $conn->prepare("SELECT Valore FROM IMPOSTAZIONI WHERE Chiave = 'costo_coperto'");
    $stmtCosto->execute();
    $costoCopertoRow = $stmtCosto->fetch(PDO::FETCH_ASSOC);
    if ($costoCopertoRow) {
        $costo_coperto_cents = MoneyHelper::toCents($costoCopertoRow['Valore']);
    }
} catch (Exception $e) {
    error_log("Impossibile recuperare costo coperto: " . $e->getMessage());
    // Continua con costo_coperto_cents = 0
}

// Calcola il costo totale dei coperti (in centesimi)
// Se è un ordine staff, i coperti sono gratis
if ($staff) {
    $totale_coperti_cents = 0;
} else {
    $totale_coperti_cents = MoneyHelper::multiply($costo_coperto_cents, $num_coperti);
}

// Validazione nome_cliente
if (!InputValidator::validate_length($nome_cliente, 100)) {
    ApiResponse::sendError('Nome cliente troppo lungo (max 100 caratteri)', 1012, 400);
}

// Validazione id_tavolo (se presente)
if ($id_tavolo !== null && !InputValidator::validate_type($id_tavolo, 'int')) {
    ApiResponse::sendError('ID tavolo deve essere un intero', 1013, 400);
}

// Validazione numero_coperti
if (!InputValidator::validate_type($num_coperti, 'int')) {
    ApiResponse::sendError('Numero coperti deve essere un intero', 1014, 400);
}
if (!InputValidator::validate_range($num_coperti, 0, 999)) {
    ApiResponse::sendError('Numero coperti deve essere tra 0 e 999', 1015, 400);
}

// Validazione totale_ordine
if (!InputValidator::validate_type($totale_ordine, 'float') && !InputValidator::validate_type($totale_ordine, 'int')) {
    ApiResponse::sendError('Totale ordine deve essere un numero', 1016, 400);
}
if (!InputValidator::validate_range($totale_ordine, 0, 999999.99)) {
    ApiResponse::sendError('Totale ordine deve essere tra 0 e 999999.99', 1017, 400);
}

// Validazione sconto
if (!InputValidator::validate_type($sconto, 'float') && !InputValidator::validate_type($sconto, 'int')) {
    ApiResponse::sendError('Sconto deve essere un numero', 1031, 400);
}
if (!InputValidator::validate_range($sconto, 0, 999999.99)) {
    ApiResponse::sendError('Sconto deve essere tra 0 e 999999.99', 1032, 400);
}

// Validazione staff
if (!is_bool($staff) && !in_array($staff, [0, 1, '0', '1', true, false], true)) {
    ApiResponse::sendError('Staff deve essere un valore booleano', 1030, 400);
}
// Normalizza staff a booleano
$staff = (bool)$staff;

// Validazione dettagli ordine
foreach ($dettagli as $idx => $item) {
    if (!isset($item['id_prodotto']) || !InputValidator::validate_type($item['id_prodotto'], 'int')) {
        ApiResponse::sendError("Dettaglio[$idx]: id_prodotto mancante o non valido", 1018, 400);
    }
    if (!isset($item['quantita']) || !InputValidator::validate_type($item['quantita'], 'int')) {
        ApiResponse::sendError("Dettaglio[$idx]: quantita mancante o non valida", 1019, 400);
    }
    if (!InputValidator::validate_range($item['quantita'], 1, 9999)) {
        ApiResponse::sendError("Dettaglio[$idx]: quantita deve essere tra 1 e 9999", 1020, 400);
    }
    if (!isset($item['prezzo_unitario']) || (!InputValidator::validate_type($item['prezzo_unitario'], 'float') && !InputValidator::validate_type($item['prezzo_unitario'], 'int'))) {
        ApiResponse::sendError("Dettaglio[$idx]: prezzo_unitario mancante o non valido", 1021, 400);
    }
    if (!InputValidator::validate_range($item['prezzo_unitario'], 0, 99999.99)) {
        ApiResponse::sendError("Dettaglio[$idx]: prezzo_unitario deve essere tra 0 e 99999.99", 1022, 400);
    }
}

// --- CALCOLO TOTALE SERVER-SIDE (Issue: Il server si fida del totale inviato dal client) ---
// Ricalcola il totale lato server invece di fidarsi del valore inviato dal client
// Usa integer arithmetic (cents) per precisione (Issue #2: Uso di float per importi monetari)
$subtotale_calcolato_cents = 0;
foreach ($dettagli as $item) {
    $prezzo_unitario_cents = MoneyHelper::toCents($item['prezzo_unitario']);
    $quantita = (int)$item['quantita'];
    $prezzo_riga_cents = MoneyHelper::multiply($prezzo_unitario_cents, $quantita);
    $subtotale_calcolato_cents = MoneyHelper::add($subtotale_calcolato_cents, $prezzo_riga_cents);
}

// Calcola il totale: subtotale + coperti - sconto
// Per ordini staff, il totale è sempre 0
if ($staff) {
    $totale_ordine_db = '0.00';
    $sconto_db = '0.00';
} else {
    $sconto_cents = MoneyHelper::toCents($sconto);
    $totale_calcolato_cents = MoneyHelper::add($subtotale_calcolato_cents, $totale_coperti_cents);
    $totale_calcolato_cents = MoneyHelper::subtract($totale_calcolato_cents, $sconto_cents);
    
    // Assicura che il totale non sia negativo
    if ($totale_calcolato_cents < 0) {
        $totale_calcolato_cents = 0;
    }
    
    // Converti il totale calcolato in formato decimal per il database
    $totale_ordine_db = MoneyHelper::toDecimal($totale_calcolato_cents);
    $sconto_db = MoneyHelper::toDecimal($sconto_cents);
}

try {
    // --- INIZIO DELLA TRANSAZIONE ---
    $conn->beginTransaction();

    // --- 1. SALVATAGGIO DELLA TESTATA ORDINE NELLA TABELLA 'ORDINI' ---
    $sql_ordine = "INSERT INTO ORDINI (Nome_Cliente, ID_Tavolo, Numero_Coperti, Totale_Ordine, Sconto, Data_Ora, Staff) 
                   VALUES (:nome_cliente, :id_tavolo, :num_coperti, :totale_ordine, :sconto, NOW(), :staff)";
    
    $stmt_ordine = $conn->prepare($sql_ordine);
    
    $stmt_ordine->bindParam(':nome_cliente', $nome_cliente);
    $stmt_ordine->bindParam(':id_tavolo', $id_tavolo);
    $stmt_ordine->bindParam(':num_coperti', $num_coperti);
    $stmt_ordine->bindParam(':totale_ordine', $totale_ordine_db);
    $stmt_ordine->bindParam(':sconto', $sconto_db);
    $stmt_ordine->bindParam(':staff', $staff, PDO::PARAM_BOOL);
    
    $stmt_ordine->execute();
    
    // Recupera l'ID dell'ordine appena inserito. Ci servirà per collegare i dettagli.
    $id_nuovo_ordine = $conn->lastInsertId();

    // --- 2. SALVATAGGIO DEI DETTAGLI ORDINE NELLA TABELLA 'DETTAGLI_ORDINE' ---
    $sql_dettaglio = "INSERT INTO DETTAGLI_ORDINE (ID_Ordine, ID_Prodotto, Quantita, Prezzo_Bloccato) 
                      VALUES (:id_ordine, :id_prodotto, :quantita, :prezzo)";

    $stmt_dettaglio = $conn->prepare($sql_dettaglio);

    foreach ($dettagli as $item) {
        // Usare bindValue per evitare problemi con riferimenti quando si itera
        $stmt_dettaglio->bindValue(':id_ordine', $id_nuovo_ordine);
        $stmt_dettaglio->bindValue(':id_prodotto', $item['id_prodotto']);
        $stmt_dettaglio->bindValue(':quantita', $item['quantita']);
        $stmt_dettaglio->bindValue(':prezzo', $item['prezzo_unitario']);
        $stmt_dettaglio->execute();
    }
    
    // --- FINE DELLA TRANSAZIONE PRINCIPALE: commit prima di provare a stampare ---
    $conn->commit();

    // --- 3. LOGICA DI STAMPA E SALVATAGGIO COMANDE ---
    // Eseguiamo la stampa dopo il commit: se la stampa fallisce non vogliamo annullare l'ordine.
    try {
        // Preparazione variabili
        $numero_coperti = $num_coperti;

        // Recupera il NOME del tavolo per la stampa (sia ricevuta che comande)
        $nome_tavolo = 'N/A';
        if (!empty($id_tavolo)) {
            try {
                $stmtTavolo = $conn->prepare("SELECT Nome_Tavolo FROM TAVOLI WHERE ID_Tavolo = ?");
                $stmtTavolo->execute([$id_tavolo]);
                $tavolo = $stmtTavolo->fetch(PDO::FETCH_ASSOC);
                if ($tavolo) {
                    $nome_tavolo = $tavolo['Nome_Tavolo'];
                }
            } catch (Exception $e) { 
                error_log("Impossibile recuperare nome tavolo per stampa: " . $e->getMessage());
            }
        }

        // Verifica disponibilità funzioni shell e comando lp
        $canExecuteShell = function_exists('exec') || function_exists('shell_exec');
        $lpPath = '';
        if ($canExecuteShell) {
            if (function_exists('shell_exec')) {
                $lpPath = trim(shell_exec('command -v lp 2>/dev/null'));
            } else {
                $whichOutput = [];
                $whichReturn = null;
                exec('command -v lp 2>/dev/null', $whichOutput, $whichReturn);
                $lpPath = trim(implode("\n", $whichOutput));
                if ($whichReturn !== 0 && $whichReturn !== null) {
                    error_log("Errore durante la ricerca del comando lp: exit code $whichReturn");
                }
            }
        }
        $lp_available = $canExecuteShell && !empty($lpPath);

        // Prepara statement per inserire e aggiornare comande
        $stmtInsertComanda = $conn->prepare("INSERT INTO COMANDE (ID_Ordine, Nome_Stampante_LAN, Testo_Comanda, Stato) VALUES (:id_ordine, :nome_stampante, :testo, 'pending')");
        $stmtUpdateComanda = $conn->prepare(
            "UPDATE COMANDE
             SET Stato = :stato, Error_Message = :err, Tentativi = COALESCE(Tentativi,0) + 1, Data_Invio = CASE WHEN :stato = 'sent' THEN NOW() ELSE Data_Invio END
             WHERE ID_Comanda = :id_comanda"
        );

        if ($lp_available) {
            // --- BLOCCO STAMPA RICEVUTA IMMEDIATA (CASSA) ---
            $nome_stampante_cassa = "cassa";
            
            if (preg_match('/^[A-Za-z0-9_.-]+$/', $nome_stampante_cassa)) {
                
                // 1. Costruisci testo ricevuta con grafica migliorata
                $testo_ricevuta = "==================================\n";
                $testo_ricevuta .= "     BRES & BARACA RISTORANTE     \n";
                $testo_ricevuta .= "==================================\n\n";
                $testo_ricevuta .= "           RICEVUTA CLIENTE       \n\n";
                $testo_ricevuta .= "  Ordine N.: #" . str_pad($id_nuovo_ordine, 6, '0', STR_PAD_LEFT) . "\n";
                $testo_ricevuta .= "  Data/Ora: " . date('d/m/Y H:i:s') . "\n\n";
                $testo_ricevuta .= "----------------------------------\n";
                $testo_ricevuta .= "  Cliente: {$nome_cliente}\n";
                $testo_ricevuta .= "  Tavolo: {$nome_tavolo}\n";
                $testo_ricevuta .= "  Coperti: {$numero_coperti}\n";
                $testo_ricevuta .= "==================================\n\n";

                // Calcola subtotale e aggiungi prodotti alla ricevuta (usando centesimi per precisione)
                $subtotale_cents = 0;
                foreach ($dettagli as $item) {
                    $descr = $item['descrizione'] ?? 'Prodotto Sconosciuto';
                    $qta = (int)$item['quantita'];
                    $prezzo_unitario_cents = MoneyHelper::toCents($item['prezzo_unitario'] ?? 0);
                    $prezzo_riga_cents = MoneyHelper::multiply($prezzo_unitario_cents, $qta);
                    $subtotale_cents = MoneyHelper::add($subtotale_cents, $prezzo_riga_cents);
                    // Formattazione migliorata: Qta, Desc e Prezzo ben allineati
                    $testo_ricevuta .= sprintf(" %2s x %-21.21s %7.2f\n", $qta, $descr, (float)MoneyHelper::toDecimal($prezzo_riga_cents));
                }

                $testo_ricevuta .= "\n----------------------------------\n";
                $testo_ricevuta .= sprintf("%-24s %7.2f EUR\n", "Subtotale:", (float)MoneyHelper::toDecimal($subtotale_cents));
                
                // Mostra il costo dei coperti se presente
                if ($totale_coperti_cents > 0) {
                    $testo_ricevuta .= sprintf("%-24s %7.2f EUR\n", "Coperti ({$num_coperti} x " . MoneyHelper::toDecimal($costo_coperto_cents) . "):", (float)MoneyHelper::toDecimal($totale_coperti_cents));
                }
                
                // Converti sconto in centesimi per il calcolo
                $sconto_cents = MoneyHelper::toCents($sconto);
                
                // Calcola il totale finale server-side per garantire precisione (Issue #2)
                $totale_finale_cents = MoneyHelper::add($subtotale_cents, $totale_coperti_cents);
                $totale_finale_cents = MoneyHelper::subtract($totale_finale_cents, $sconto_cents);
                
                // Se c'è uno sconto, mostralo separatamente
                if ($sconto_cents > 0) {
                    $testo_ricevuta .= sprintf("%-24s -%6.2f EUR\n", "Sconto:", (float)MoneyHelper::toDecimal($sconto_cents));
                }
                
                $testo_ricevuta .= "==================================\n";
                
                // Totale con maggiore risalto (usa il totale calcolato server-side)
                $testo_ricevuta .= sprintf("%-24s %7.2f EUR\n", "** TOTALE **", (float)MoneyHelper::toDecimal($totale_finale_cents));
                $testo_ricevuta .= "==================================\n\n";
                $testo_ricevuta .= "      Grazie per la Visita!       \n";
                $testo_ricevuta .= "          Arrivederci!            \n\n";
                $testo_ricevuta .= "            * * *                 \n\n\n";

                // 2. Scrivi file temporaneo
                $temp_file_ricevuta = tempnam(sys_get_temp_dir(), 'ricevuta_');
                if ($temp_file_ricevuta) {
                    $written_ricevuta = file_put_contents($temp_file_ricevuta, $testo_ricevuta);
                    
                    if ($written_ricevuta !== false) {
                        // 3. Esegui comando lp (non blocchiamo l'ordine se fallisce, logghiamo solo)
                        $cmd_ricevuta = escapeshellarg($lpPath) . " -d " . escapeshellarg($nome_stampante_cassa) . " " . escapeshellarg($temp_file_ricevuta) . " 2>&1";
                        $output_ricevuta = [];
                        $returnVar_ricevuta = null;
                        exec($cmd_ricevuta, $output_ricevuta, $returnVar_ricevuta);

                        if ($returnVar_ricevuta !== 0) {
                            $errorOutput = implode("\n", $output_ricevuta);
                            error_log("Errore stampa RICEVUTA (ordine $id_nuovo_ordine) su '$nome_stampante_cassa' (exit=$returnVar_ricevuta): $errorOutput");
                        } else {
                            error_log("Stampa RICEVUTA (ordine $id_nuovo_ordine) su '$nome_stampante_cassa' completata con successo");
                        }
                    } else {
                        error_log("Impossibile scrivere RICEVUTA su file temporaneo $temp_file_ricevuta");
                    }
                    @unlink($temp_file_ricevuta); // Pulisci
                } else {
                    error_log("Impossibile creare file temporaneo per RICEVUTA ordine $id_nuovo_ordine");
                }
            } else {
                error_log("Nome stampante 'cassa' non valido.");
            }
            // --- FINE BLOCCO STAMPA RICEVUTA ---
        } else {
            error_log("Stampa CUPS: comando 'lp' non disponibile. Impossibile stampare ricevuta immediata.");
        }


        // --- BLOCCO STAMPA COMANDE REPARTI (Logica esistente) ---

        // Carica whitelist delle stampanti definite in REPARTI
        $stmtPrinters = $conn->query("SELECT DISTINCT Nome_Stampante_LAN FROM REPARTI WHERE Nome_Stampante_LAN IS NOT NULL");
        $allowedPrinters = $stmtPrinters->fetchAll(PDO::FETCH_COLUMN, 0);
        $allowedPrinters = array_map('trim', $allowedPrinters);
        $allowedPrinters = array_filter($allowedPrinters);

        // Recupera i prodotti da stampare raggruppati per stampante
        $stmtStampa = $conn->prepare("SELECT p.Descrizione, d.Quantita, r.Nome_Reparto, r.Nome_Stampante_LAN 
            FROM DETTAGLI_ORDINE d
            JOIN PRODOTTI p ON d.ID_Prodotto = p.ID_Prodotto
            JOIN REPARTI r ON p.ID_Reparto = r.ID_Reparto
            WHERE d.ID_Ordine = ? 
            AND r.Nome_Stampante_LAN IS NOT NULL
            ORDER BY r.Nome_Stampante_LAN, r.Nome_Reparto;");
        $stmtStampa->execute([$id_nuovo_ordine]);
        $prodottiDaStampare = $stmtStampa->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($prodottiDaStampare)) {
            $comande = [];
            // Usa $nome_tavolo (il nome) invece del vecchio $nome_tavolo_display (che era l'ID)
            $infoTavolo = "==================================\n"; 
            $infoTavolo .= "  Ordine: #" . str_pad($id_nuovo_ordine, 6, '0', STR_PAD_LEFT) . "\n";
            $infoTavolo .= "  Tavolo: {$nome_tavolo}\n";
            $infoTavolo .= "----------------------------------\n";
            $infoTavolo .= "  Cliente: {$nome_cliente}\n";
            $infoTavolo .= "  Coperti: {$numero_coperti}\n";
            $infoTavolo .= "==================================\n\n";

            foreach ($prodottiDaStampare as $prodotto) {
                $stampante = trim($prodotto['Nome_Stampante_LAN']);
                if ($stampante === '') continue;

                if (!in_array($stampante, $allowedPrinters, true)) {
                    error_log("Stampante non autorizzata o non trovata nel DB: '$stampante' per ordine $id_nuovo_ordine");
                    continue;
                }

                if (!isset($comande[$stampante])) {
                    $nomeReparto = strtoupper($prodotto['Nome_Reparto']);
                    $comande[$stampante] = "\n  *** COMANDA {$nomeReparto} ***  \n\n";
                    $comande[$stampante] .= $infoTavolo;
                }

                $descr = preg_replace("/\r?\n/", ' ', $prodotto['Descrizione']);
                $comande[$stampante] .= sprintf(" [%2s] %s\n", $prodotto['Quantita'], $descr);
            }

            // Invia le stampe per ogni stampante e salva le comande in DB
            foreach ($comande as $nome_stampante_cups => $testo_comanda) {
                if (!preg_match('/^[A-Za-z0-9_.-]+$/', $nome_stampante_cups)) {
                    error_log("Nome stampante non valido: $nome_stampante_cups");
                    continue;
                }

                $testo_comanda .= "\n==================================\n";
                $testo_comanda .= "  Inviato: " . date('d/m/Y H:i:s') . "\n";
                $testo_comanda .= "==================================\n\n\n";

                // Inserisci la comanda in tabella COMANDE con stato pending
                try {
                    $stmtInsertComanda->execute([
                        ':id_ordine' => $id_nuovo_ordine,
                        ':nome_stampante' => $nome_stampante_cups,
                        ':testo' => $testo_comanda
                    ]);
                    $id_comanda = $conn->lastInsertId();
                } catch (Exception $e) {
                    error_log("Impossibile inserire comanda in DB per ordine $id_nuovo_ordine: " . $e->getMessage());
                    $id_comanda = null;
                }

                if ($lp_available && $id_comanda !== null) {
                    $temp_file = tempnam(sys_get_temp_dir(), 'comanda_');
                    if ($temp_file === false) {
                        error_log("Impossibile creare file temporaneo per stampa ordine $id_nuovo_ordine");
                        $stmtUpdateComanda->execute([':stato' => 'error', ':err' => 'Impossibile creare file temporaneo', ':id_comanda' => $id_comanda]);
                        continue;
                    }

                    $written = file_put_contents($temp_file, $testo_comanda);
                    if ($written === false) {
                        error_log("Impossibile scrivere comanda su file temporaneo $temp_file per stampante $nome_stampante_cups");
                        @unlink($temp_file);
                        $stmtUpdateComanda->execute([':stato' => 'error', ':err' => 'Impossibile scrivere file temporaneo', ':id_comanda' => $id_comanda]);
                        continue;
                    }

                    $cmd = escapeshellarg($lpPath) . " -d " . escapeshellarg($nome_stampante_cups) . " " . escapeshellarg($temp_file) . " 2>&1";
                    $output = [];
                    $returnVar = null;
                    exec($cmd, $output, $returnVar);

                    if ($returnVar !== 0) {
                        $errorOutput = implode("\n", $output);
                        error_log("Errore stampa CUPS (ordine $id_nuovo_ordine) su '$nome_stampante_cups' (exit=$returnVar): $errorOutput");
                        $stmtUpdateComanda->execute([':stato' => 'error', ':err' => $errorOutput, ':id_comanda' => $id_comanda]);
                    } else {
                        error_log("Stampa CUPS (ordine $id_nuovo_ordine) su '$nome_stampante_cups' completata con successo");
                        $stmtUpdateComanda->execute([':stato' => 'sent', ':err' => null, ':id_comanda' => $id_comanda]);
                    }

                    if (file_exists($temp_file)) {
                        @unlink($temp_file);
                    }
                } 
                // Se lp non disponibile, lasciamo lo stato 'pending' per il worker
            }
        }

    } catch (Exception $e) {
        error_log("Eccezione durante preparazione stampa ordine $id_nuovo_ordine: " . $e->getMessage());
        // Non blocchiamo la risposta al client: l'ordine è già stato salvato.
    }

    // --- RECUPERO INFORMAZIONI SULLO STATO DELLE COMANDE ---
    // Query ottimizzata: usa indice su ID_Ordine (FK) e Stato
    $printStatus = [
        'sent' => 0,
        'pending' => 0,
        'error' => 0
    ];
    $printStatusAvailable = true;
    
    try {
        $stmtStatus = $conn->prepare("SELECT Stato, COUNT(*) as count FROM COMANDE WHERE ID_Ordine = ? GROUP BY Stato");
        $stmtStatus->execute([$id_nuovo_ordine]);
        $statusResults = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($statusResults as $row) {
            if (isset($printStatus[$row['Stato']])) {
                $printStatus[$row['Stato']] = (int)$row['count'];
            }
        }
    } catch (Exception $e) {
        error_log("Impossibile recuperare stato comande per ordine $id_nuovo_ordine: " . $e->getMessage());
        $printStatusAvailable = false;
    }

    // --- RISPOSTA DI SUCCESSO ---
    $responseData = [
        'message' => 'Ordine #' . $id_nuovo_ordine . ' salvato con successo!',
        'order_id' => $id_nuovo_ordine
    ];
    
    // Includi print_status solo se disponibile
    if ($printStatusAvailable) {
        $responseData['print_status'] = $printStatus;
    }
    
    ApiResponse::sendSuccess($responseData);

} catch (PDOException $e) {
    // --- GESTIONE ERRORI ---
    $conn->rollBack();
    error_log("Errore salvataggio ordine: " . $e->getMessage());
    ApiResponse::sendError('Impossibile salvare l\'ordine a causa di un errore del server.', 2003, 500);
}
?>
