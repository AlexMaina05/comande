<?php
// Content Security Policy in report-only mode per monitorare violazioni
header("Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// Imposta il fuso orario corretto
date_default_timezone_set('Europe/Rome');
// Ottiene la data di oggi nel formato YYYY-MM-DD per l'input date
$today = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Vendite</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header>
        <h1>📈 Controllo Dati</h1>
        <p>Seleziona una data per analizzare le vendite</p>
    </header>

    <main class="report-container">

        <!-- Sezione 1: Filtri -->
        <div class="report-sezione" id="filtri-sezione">
            <h2>Filtri Report</h2>
            <?php
            // genera ultime 14 date (oggi, ieri, ...)
            $quickDates = [];
            for ($i = 0; $i < 14; $i++) {
                $d = (new DateTime())->modify("-{$i} days");
                $quickDates[] = $d;
            }
            ?>
            <label for="select-data">Selezione rapida:</label>
            <select id="select-data" aria-label="Seleziona una data veloce">
                <option value="">-- Personalizza --</option>
                <?php foreach ($quickDates as $d): 
                    $val = $d->format('Y-m-d');
                    $label = ($val === $today) ? 'Oggi (' . $d->format('d/m/Y') . ')' : $d->format('d/m/Y');
                ?>
                    <option value="<?php echo $val; ?>" <?php echo ($val === $today) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="input-data" class="sr-only">Seleziona data personalizzata</label>
            <input type="date" id="input-data" name="data" value="<?php echo htmlspecialchars($today, ENT_QUOTES); ?>" />
             <div class="controls">
                 <button id="btn-genera-report" class="btn-primario" type="button">Genera Report</button>
                 <button id="btn-stampa-report" class="btn-secondario" type="button">🖨️ Stampa</button>
                 <button id="btn-download-pdf" class="btn-primario" type="button">⬇️ Scarica PDF</button>
             </div>
         </div>

        <!-- Sezione 2: Risultati (inizialmente nascosta) -->
        <div class="report-sezione" id="risultati-sezione" style="display:none;">
            <div id="report-header">
                <h2>Riepilogo del <span id="data-report-display"></span></h2>
            </div>
            <div id="loading-msg" style="display:none">Caricamento...</div>
            <div id="no-data-msg" style="display:none"></div>
            <div id="report-content"></div>
        </div>
        
        <a href="index.html" class="link-indietro">&larr; Torna al menu principale</a>
    </main>

    <!-- html2pdf per esportazione client-side (usa CDN) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js" crossorigin="anonymous"></script>
    <script src="assets/js/report.js"></script>
</body>
</html>
