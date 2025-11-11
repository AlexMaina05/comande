document.addEventListener("DOMContentLoaded", () => {
    // --- ELEMENTI HTML ---
    const dataInput = document.getElementById("input-data");               // match report.php
    const selectData = document.getElementById("select-data");
    const generaBtn = document.getElementById("btn-genera-report");
    const stampaBtn = document.getElementById("btn-stampa-report");
    const downloadBtn = document.getElementById("btn-download-pdf");
    const risultatiSezione = document.getElementById("risultati-sezione"); // mostra/nasconde l'intera sezione
    const reportContent = document.getElementById("report-content");      // qui scriviamo i risultati
    const loadingMsg = document.getElementById("loading-msg");
    const noDataMsg = document.getElementById("no-data-msg");
    const dataReportDisplay = document.getElementById("data-report-display");
    
    // sincronizza select -> input
    if (selectData && dataInput) {
        selectData.addEventListener('change', () => {
            if (selectData.value) {
                dataInput.value = selectData.value;
            }
            // lascia all'utente la possibilità di modificare manualmente la data
        });
        // se l'utente modifica il datepicker, aggiorna il select se corrisponde
        dataInput.addEventListener('change', () => {
            const v = dataInput.value;
            if (!selectData) return;
            const opt = Array.from(selectData.options).find(o => o.value === v);
            selectData.value = opt ? v : ''; // se non c'è corrispondenza seleziona "Personalizza"
        });
    }
    // safety: assicurati che i pulsanti/esempio esistano
    if (!generaBtn) return;
    if (stampaBtn) stampaBtn.disabled = true;
    if (downloadBtn) downloadBtn.disabled = true;
    if (risultatiSezione) risultatiSezione.style.display = 'none';

    // utilità: escape semplice per testo inserito in innerHTML
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // --- GESTIONE EVENTI ---
    generaBtn.addEventListener("click", async () => {
        const dataSelezionata = dataInput ? dataInput.value : '';
        if (!dataSelezionata) {
            alert("Per favore, seleziona una data.");
            return;
        }

        if (loadingMsg) loadingMsg.style.display = 'block';
        if (noDataMsg) noDataMsg.style.display = 'none';
        if (reportContent) reportContent.innerHTML = '';
        if (risultatiSezione) risultatiSezione.style.display = 'block';
        generaBtn.disabled = true;
        if (stampaBtn) stampaBtn.disabled = true;

        try {
            const response = await fetch(`api/genera_report.php?data=${encodeURIComponent(dataSelezionata)}`);

            // Parse JSON response
            const result = await response.json();
            
            if (!response.ok || !result.success) {
                // Gestione errore standardizzata
                const errorMsg = result.error?.message || result.error || 'Errore nel recupero del report.';
                throw new Error(errorMsg);
            }

            const dati = result.data;

            if (loadingMsg) loadingMsg.style.display = 'none';

            mostraRisultati(dati, dataSelezionata);
            if (stampaBtn) stampaBtn.disabled = false;

        } catch (error) {
            console.error("Errore durante la generazione del report:", error);
            if (loadingMsg) loadingMsg.style.display = 'none';
            if (noDataMsg) {
                noDataMsg.style.display = 'block';
                noDataMsg.innerHTML = `<p class="errore"><strong>Errore:</strong> ${escapeHtml(error.message)}</p>`;
            }
            if (reportContent) reportContent.innerHTML = '';
        } finally {
            generaBtn.disabled = false;
        }
    });

    if (stampaBtn) {
        stampaBtn.addEventListener("click", () => window.print());
    }

    // Gestione download PDF client-side con html2pdf.js
    if (downloadBtn) {
        downloadBtn.addEventListener("click", () => {
            const el = document.getElementById("report-stampabile") || reportContent;
            if (!el || el.innerHTML.trim() === "") {
                return alert("Nessun contenuto da esportare.");
            }
            // Usa la data visualizzata per il filename, fallback a today
            const filenameDate = (dataReportDisplay && dataReportDisplay.textContent) ? dataReportDisplay.textContent.replace(/\s+/g,'_') : new Date().toISOString().slice(0,10);
            const filename = `report-${filenameDate}.pdf`;
            const opt = {
                margin:       8,
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.95 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            // disabilita bottone durante la generazione
            downloadBtn.disabled = true;
            try {
                html2pdf().set(opt).from(el).save().then(() => {
                    downloadBtn.disabled = false;
                }).catch(err => {
                    console.error("Errore creazione PDF:", err);
                    alert("Errore durante la creazione del PDF.");
                    downloadBtn.disabled = false;
                });
            } catch (err) {
                console.error("Errore html2pdf:", err);
                alert("Impossibile generare il PDF.");
                downloadBtn.disabled = false;
            }
        });
    }

    // --- FUNZIONE PER MOSTRARE I RISULTATI ---
    function mostraRisultati(dati, dataScelta) {
        // Formatta la data
        const dataFormattata = new Date(dataScelta + 'T00:00:00').toLocaleDateString('it-IT', {
            day: '2-digit', month: '2-digit', year: 'numeric'
        });

        if (dataReportDisplay) dataReportDisplay.textContent = dataFormattata;

        const riepilogo = Array.isArray(dati.riepilogo_servizio) ? dati.riepilogo_servizio : [];
        const dettaglio = Array.isArray(dati.dettaglio_prodotti) ? dati.dettaglio_prodotti : [];

        const datiSala = riepilogo.find(r => r.Tipo_Servizio === 'SALA') || {};
        const datiAsporto = riepilogo.find(r => r.Tipo_Servizio === 'ASPORTO') || {};

        const incSala = parseFloat(datiSala.Incasso_Parziale) || 0;
        const copertiSala = parseInt(datiSala.Coperti_Parziali, 10) || 0;
        const incAsporto = parseFloat(datiAsporto.Incasso_Parziale) || 0;
        const totaleGenerale = incSala + incAsporto;

        // Costruisce markup in modo sicuro utilizzando escapeHtml per i testi
        let html = '';
        html += `<div id="report-stampabile">`;
        html += `<h2>Riepilogo Giornaliero</h2>`;
        html += `<p class="data-report">Data: <strong>${escapeHtml(dataFormattata)}</strong></p>`;

        html += `<div class="card-container">`;
        html += `<div class="report-card"><h3>Servizio in Sala</h3><p class="valore">${incSala.toFixed(2)} €</p><p class="dettaglio">${copertiSala} Coperti</p></div>`;
        html += `<div class="report-card"><h3>Servizio Asporto</h3><p class="valore">${incAsporto.toFixed(2)} €</p></div>`;
        html += `<div class="report-card totale"><h3>Incasso Totale</h3><p class="valore">${totaleGenerale.toFixed(2)} €</p></div>`;
        html += `</div><hr><h3>Dettaglio Prodotti Venduti</h3>`;

        if (dettaglio.length > 0) {
            html += `<table class="tabella-report"><thead><tr><th>Prodotto</th><th>Quantità Venduta</th></tr></thead><tbody>`;
            dettaglio.forEach(prodotto => {
                const descr = prodotto.Descrizione || prodotto.descrizione || '';
                const qty = Number(prodotto.Totale_Venduto ?? prodotto.totale_venduto ?? 0) || 0;
                html += `<tr><td>${escapeHtml(descr)}</td><td>${escapeHtml(String(qty))}</td></tr>`;
            });
            html += `</tbody></table>`;
        } else {
            html += `<p>Nessun prodotto venduto in questa data.</p>`;
        }

        html += `</div>`; // chiude report-stampabile

        if (reportContent) reportContent.innerHTML = html;
        if (loadingMsg) loadingMsg.style.display = 'none';
        if (noDataMsg) noDataMsg.style.display = 'none';
        if (risultatiSezione) risultatiSezione.style.display = 'block';
    }
});
