<?php
// --- BLOCCO DI SICUREZZA ---
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Content Security Policy in report-only mode per monitorare violazioni
header("Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

require_once '../config/db_connection.php';

$categorie = [];
$reparti = [];
$errore_caricamento = null; // Variabile per tracciare l'errore

try {
    $stmt = $conn->prepare("SELECT ID_Categoria, Nome_Categoria FROM CATEGORIE ORDER BY Nome_Categoria");
    $stmt->execute();
    $categorie = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $conn->prepare("SELECT ID_Reparto, Nome_Reparto, Nome_Stampante_LAN FROM REPARTI ORDER BY Nome_Reparto");
    $stmt2->execute();
    $reparti = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Non consideriamo errore la lista vuota: l'admin può aggiungere nuovi record.
} catch (Exception $e) {
    // Log dettagliato per il server
    error_log("Errore in admin.php: " . $e->getMessage());
    // Imposta la variabile di errore invece di usare die()
    $errore_caricamento = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manutenzione Dati</title>
    <link rel="stylesheet" href="assets/css/style.css">
    
    <?php if ($errore_caricamento): ?>
    <style>
        /* Stile per il box di errore */
        .errore-box {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 20px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            border-radius: 8px;
            margin: 20px auto;
            max-width: 1200px;
        }
        .errore-box h2 { color: #721c24; }
        .errore-box small { word-wrap: break-word; }
        .errore-box a { color: #721c24 !important; }
        .admin-footer { margin-top: 20px; }
    </style>
    <?php endif; ?>
</head>
<body>

    <header>
        <h1>⚙️ Manutenzione</h1>
        <p>Gestisci le anagrafiche della tua applicazione</p>
    </header>

    <main class="admin-container">

        <?php if ($errore_caricamento): ?>

            <div class="errore-box">
                <h2>Errore Critico ⚠️</h2>
                <p>Impossibile caricare i dati necessari (es. Categorie o Reparti) per l'area Manutenzione.</p>
                <p>Controllare la connessione al database e che le anagrafiche siano state caricate correttamente.</p>
                <hr>
                <?php if (defined('DEBUG') && DEBUG): ?>
                    <p><small><b>Dettaglio tecnico:</b> "<?php echo $errore_caricamento; ?>"</small></p>
                <?php else: ?>
                    <p><small>Contatta l'amministratore per assistenza.</small></p>
                <?php endif; ?>
            </div>
            
            <div class="admin-footer">
                <a href="index.html" class="link-indietro">&larr; Torna al menu principale</a>
                <a href="logout.php" class="btn-logout">Esci (Logout)</a>
            </div>

        <?php else: ?>

            <div class="admin-tabs">
                <button class="tab-link active" data-tab="tab-prodotti">Prodotti Menu</button>
                <button class="tab-link" data-tab="tab-tavoli">Tavoli</button>
                <button class="tab-link" data-tab="tab-categorie">Categorie</button>
                <button class="tab-link" data-tab="tab-reparti">Reparti Stampa</button>
                <button class="tab-link" data-tab="tab-impostazioni">Impostazioni</button>
                <button class="tab-link" data-tab="tab-export">Export / Backup</button>
            </div>

            <div id="tab-prodotti" class="tab-content active">
                <h2>Gestione Prodotti</h2>
                <button class="btn-successo btn-add" data-tipo="prodotto">+ Aggiungi Nuovo Prodotto</button>
                <table class="admin-table" id="table-prodotti">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Codice</th>
                            <th>Descrizione</th>
                            <th>Prezzo</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="table-body-prodotti"></tbody>
                </table>
            </div>

            <div id="tab-tavoli" class="tab-content">
                <h2>Gestione Tavoli</h2>
                <button class="btn-successo btn-add" data-tipo="tavolo">+ Aggiungi Nuovo Tavolo</button>
                <table class="admin-table" id="table-tavoli">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome Tavolo</th>
                            <th>Tipo Servizio</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="table-body-tavoli"></tbody>
                </table>
            </div>
            
            <div id="tab-categorie" class="tab-content">
                <h2>Gestione Categorie Prodotti</h2>
                <button class="btn-successo btn-add" data-tipo="categoria">+ Aggiungi Nuova Categoria</button>
                <table class="admin-table" id="table-categorie">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome Categoria</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="table-body-categorie"></tbody>
                </table>
            </div>

            <div id="tab-reparti" class="tab-content">
                <h2>Gestione Reparti e Stampanti</h2>
                <button class="btn-successo btn-add" data-tipo="reparto">+ Aggiungi Nuovo Reparto</button>
                <table class="admin-table" id="table-reparti">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome Reparto</th>
                            <th>Nome Stampante di Rete</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="table-body-reparti"></tbody>
                </table>
            </div>

            <div id="tab-impostazioni" class="tab-content">
                <h2>⚙️ Impostazioni Sistema</h2>
                <p>Configura i parametri dell'applicazione</p>
                
                <div class="settings-section">
                    <h3>Costo Coperto</h3>
                    <p>Definisci il costo per coperto che verrà aggiunto automaticamente al totale dell'ordine</p>
                    <form id="form-costo-coperto" class="settings-form">
                        <label for="input-costo-coperto">Costo per Coperto (€):</label>
                        <input type="number" id="input-costo-coperto" step="0.01" min="0" max="999.99" required>
                        <button type="submit" class="btn-primario">Salva Costo Coperto</button>
                        <span id="status-costo-coperto" class="status-message"></span>
                    </form>
                </div>
            </div>

            <div id="tab-export" class="tab-content">
                <h2>📦 Export / Backup Dati</h2>
                <p>Esporta i dati delle tabelle critiche per backup o analisi esterna.</p>
                
                <div class="export-section">
                    <h3>Prodotti</h3>
                    <p>Esporta l'anagrafica dei prodotti del menu.</p>
                    <button class="btn-primario export-btn" data-table="prodotti" data-format="csv">📄 Scarica CSV</button>
                    <button class="btn-primario export-btn" data-table="prodotti" data-format="sql">💾 Scarica SQL</button>
                </div>
                
                <div class="export-section">
                    <h3>Ordini</h3>
                    <p>Esporta tutti gli ordini registrati nel sistema.</p>
                    <button class="btn-primario export-btn" data-table="ordini" data-format="csv">📄 Scarica CSV</button>
                    <button class="btn-primario export-btn" data-table="ordini" data-format="sql">💾 Scarica SQL</button>
                </div>
                
                <div class="export-section">
                    <h3>Comande</h3>
                    <p>Esporta lo storico delle comande inviate alle stampanti.</p>
                    <button class="btn-primario export-btn" data-table="comande" data-format="csv">📄 Scarica CSV</button>
                    <button class="btn-primario export-btn" data-table="comande" data-format="sql">💾 Scarica SQL</button>
                </div>
                
                <div style="margin-top: 30px; padding: 15px; background-color: #f0f0f0; border-radius: 5px;">
                    <h4>ℹ️ Note sull'export</h4>
                    <ul>
                        <li><strong>CSV:</strong> Formato tabulare, ideale per Excel/LibreOffice. Encoding UTF-8 con BOM.</li>
                        <li><strong>SQL:</strong> Istruzioni INSERT pronte per importazione in database.</li>
                        <li>I file vengono scaricati direttamente dal browser tramite streaming.</li>
                        <li>Per tabelle molto grandi, l'operazione potrebbe richiedere alcuni secondi.</li>
                        <li>Consulta <a href="docs/EXPORT.md" target="_blank">docs/EXPORT.md</a> per maggiori informazioni.</li>
                    </ul>
                </div>
            </div>
            
            <div class="admin-footer">
                <a href="index.html" class="link-indietro">&larr; Torna al menu principale</a>
                <a href="logout.php" class="btn-logout">Esci (Logout)</a>
            </div>

        <?php endif; ?>
    </main>

    <?php if (!$errore_caricamento): ?>
        <div id="edit-modal" class="modal-overlay" style="display:none;">
            <div class="modal-content">
                <button class="close-button">&times;</button>
                <h2 id="modal-title">Aggiungi/Modifica</h2>
                <form id="modal-form">
                    <input type="hidden" id="edit-id" name="id">
                    <div id="modal-body">
                        </div>
                    <button type="submit" class="btn-primario">Salva Modifiche</button>
                </form>
            </div>
        </div>

        <script src="assets/js/admin.js"></script>
    <?php endif; ?>

</body>
</html>
