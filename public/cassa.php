<?php
// cassa.php (aggiornato e corretto)
// Content Security Policy in report-only mode per monitorare violazioni
header("Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

require_once '../config/db_connection.php';

$tavoli = [];
$errore_caricamento = null; // Variabile per tracciare l'errore

try {
    $stmt = $conn->prepare("SELECT ID_Tavolo, Nome_Tavolo FROM TAVOLI ORDER BY Nome_Tavolo");
    $stmt->execute();
    $tavoli = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Non considerare errore la lista vuota; gestiamo il fallback in HTML
    // if ($tavoli === false || count($tavoli) === 0) {
    //     throw new Exception("Nessun tavolo trovato nel database. Verificare che la tabella 'TAVOLI' sia popolata.");
    // }
} catch (Exception $e) {
    // Log dettagliato per il server
    error_log("Errore in cassa.php: " . $e->getMessage());
    // Imposta la variabile di errore invece di usare die()
    $errore_caricamento = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cassa - Creazione Ordine</title>
    <link rel="stylesheet" href="assets/css/style.css">
    
    <?php if ($errore_caricamento): ?>
    <style>
        /* Stile per il box di errore, per assicurarsi che sia visibile */
        .errore-box {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 20px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            border-radius: 8px;
            margin: 20px auto;
            max-width: 1400px;
        }
        .errore-box h2 { color: #721c24; }
        .errore-box small { word-wrap: break-word; }
        .link-indietro { margin-top: 15px; display: inline-block; }
    </style>
    <?php endif; ?>
</head>
<body>

    <header>
        <h1>🧾 Creazione Ordine</h1>
        <p>Compila i campi e aggiungi i prodotti</p>
    </header>

    <main class="cassa-container">

        <?php if ($errore_caricamento): ?>
            
            <div class="errore-box">
                <h2>Errore Critico ⚠️</h2>
                <p>Impossibile caricare i dati necessari (es. Tavoli) per avviare la cassa.</p>
                <p>Controllare la connessione al database e che le anagrafiche siano state caricate correttamente.</p>
                <hr>
                <p><small><b>Dettaglio tecnico (per admin):</b> "<?php echo $errore_caricamento; ?>"</small></p>
                <a href="index.html" class="link-indietro">&larr; Torna al menu principale</a>
            </div>

        <?php else: ?>

            <div class="form-sezione" id="input-sezione">
                
                <form id="form-testata">
                    <h2>1. Dati Principali</h2>
                    <label for="input-nome">Nome Cliente:</label>
                    <input type="text" id="input-nome" placeholder="Es. Mario Rossi" required>
                    
                    <label for="select-tavolo">Tavolo / Asporto:</label>
                    <select id="select-tavolo" name="tavolo" required>
                        <option value="" disabled selected>-- Seleziona tavolo --</option>
                        <?php
                        if (!empty($tavoli)) {
                            foreach ($tavoli as $tavolo) {
                                $id = htmlspecialchars($tavolo['ID_Tavolo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $nome = htmlspecialchars($tavolo['Nome_Tavolo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                echo "<option value='{$id}'>{$nome}</option>";
                            }
                        } else {
                            // Fallback utile: Asporto / Ritiro
                            echo "<option value='ASPORTO'>Asporto / Ritiro</option>";
                        }
                        ?>
                    </select>

                    <label for="input-coperti">Numero Coperti:</label>
                    <input type="number" id="input-coperti" name="coperti" value="1" min="0">
                    
                    <div style="margin-top: 15px;">
                        <label for="input-staff" style="display: inline-flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" id="input-staff" name="staff" style="margin-right: 8px;">
                            Ordine Staff (totale a 0, escluso dai report)
                        </label>
                    </div>
                </form>

                <form id="form-aggiungi-prodotto">
                    <h2>2. Aggiungi Prodotti</h2>
                    <label for="input-codice">Codice Prodotto:</label>
                    <input type="text" id="input-codice" placeholder="Digita codice (es. PZ01)" required>
                    
                    <label for="input-quantita">Quantità:</label>
                    <input type="number" id="input-quantita" value="1" min="1">
                    
                    <button type="submit" class="btn-primario">Aggiungi Prodotto</button>
                </form>

            </div>

            <div class="form-sezione" id="riepilogo-sezione">
                <h2>Riepilogo Comanda</h2>
                
                <div class="comanda-vuota" id="comanda-vuota-msg">
                    Nessun prodotto aggiunto.
                </div>

                <table id="tabella-comanda">
                    <thead>
                        <tr>
                            <th>Prodotto</th>
                            <th>Q.tà</th>
                            <th>Totale</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="corpo-tabella-comanda">
                        </tbody>
                </table>

                <div id="totale-container">
                    <div class="totale-row">
                        <span>Subtotale:</span>
                        <span id="subtotale-display">0.00</span> €
                    </div>
                    <div class="totale-row" id="coperti-row">
                        <span>Coperti:</span>
                        <span id="coperti-display">0.00</span> €
                    </div>
                    <div class="totale-row sconto-row">
                        <label for="input-sconto">Sconto:</label>
                        <input type="number" id="input-sconto" step="0.01" min="0" value="0" placeholder="0.00"> €
                    </div>
                    <div class="totale-row totale-finale">
                        <strong>TOTALE:</strong>
                        <strong><span id="totale-display">0.00</span> €</strong>
                    </div>
                </div>

                <button id="btn-salva-stampa" class="btn-successo">Salva e Stampa Comande</button>
                
                <a href="index.html" class="link-indietro">&larr; Torna al menu principale</a>
            </div>

        <?php endif; ?>

    </main>

    <?php if (!$errore_caricamento): ?>
        <script>
            // Blocca i coperti a 0 quando viene selezionato "ASPORTO"
            document.addEventListener('DOMContentLoaded', function () {
                const selectTavolo = document.getElementById('select-tavolo');
                const inputCoperti = document.getElementById('input-coperti');
                if (!selectTavolo || !inputCoperti) return;

                function aggiornaCoperti() {
                    if (selectTavolo.value === 'ASPORTO') {
                        inputCoperti.value = 0;
                        inputCoperti.setAttribute('readonly', '');
                        inputCoperti.setAttribute('aria-disabled', 'true');
                    } else {
                        inputCoperti.removeAttribute('readonly');
                        inputCoperti.removeAttribute('aria-disabled');
                        // se prima era 0, riportalo a 1 per comodità
                        if (parseInt(inputCoperti.value, 10) === 0) {
                            inputCoperti.value = 1;
                        }
                    }
                }

                selectTavolo.addEventListener('change', aggiornaCoperti);
                // stato iniziale
                aggiornaCoperti();
            });
        </script>
        <script src="assets/js/cassa.js"></script>
    <?php endif; ?>

</body>
</html>
