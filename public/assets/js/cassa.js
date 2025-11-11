// Aspetta che tutta la pagina HTML sia caricata prima di eseguire il codice
document.addEventListener("DOMContentLoaded", () => {

    // --- 1. DEFINIZIONE DEGLI ELEMENTI ---
    const formAggiungi = document.getElementById("form-aggiungi-prodotto");
    const inputCodice = document.getElementById("input-codice");
    const inputQuantita = document.getElementById("input-quantita");
    
    const tabellaCorpo = document.getElementById("corpo-tabella-comanda");
    const tabella = document.getElementById("tabella-comanda");
    const msgComandaVuota = document.getElementById("comanda-vuota-msg");
    const subtotaleDisplay = document.getElementById("subtotale-display");
    const copertiDisplay = document.getElementById("coperti-display");
    const totaleDisplay = document.getElementById("totale-display");
    const inputSconto = document.getElementById("input-sconto");
    
    const btnSalvaStampa = document.getElementById("btn-salva-stampa");
    
    let comandaCorrente = [];
    let costoCoperto = 0.00; // Costo per coperto, caricato dalle impostazioni

    // Protezione: se manca un elemento critico, esci (evita errori in console)
    if (!formAggiungi || !inputCodice || !inputQuantita || !tabellaCorpo || !tabella || !msgComandaVuota || !subtotaleDisplay || !copertiDisplay || !totaleDisplay || !inputSconto || !btnSalvaStampa) {
        console.warn("cassa.js: elementi critici mancanti, script interrotto.");
        return;
    }
    
    // Carica il costo coperto dalle impostazioni
    async function loadCostoCoperto() {
        try {
            const response = await fetch('api/gestisci_impostazioni.php?chiave=costo_coperto');
            if (response.ok) {
                const result = await response.json();
                if (result.success && result.data) {
                    costoCoperto = parseFloat(result.data.Valore || 0);
                }
            }
        } catch (error) {
            console.error('Errore caricamento costo coperto:', error);
        }
    }
    
    // Carica il costo coperto all'avvio
    loadCostoCoperto();

    // Previeni submit accidentale del form 'form-testata' (Enter in campo nome)
    const formTestata = document.getElementById("form-testata");
    if (formTestata) {
        formTestata.addEventListener("submit", (e) => e.preventDefault());
    }

    // Gestione checkbox staff - aggiorna il totale quando cambia
    const inputStaffEl = document.getElementById("input-staff");
    if (inputStaffEl) {
        inputStaffEl.addEventListener("change", () => {
            aggiornaRiepilogo();
        });
    }

    // Gestione campo sconto - aggiorna il totale quando cambia
    if (inputSconto) {
        inputSconto.addEventListener("input", () => {
            aggiornaRiepilogo();
        });
    }
    
    // Gestione campo numero coperti - aggiorna il totale quando cambia
    const inputCopertiEl = document.getElementById("input-coperti");
    if (inputCopertiEl) {
        inputCopertiEl.addEventListener("input", () => {
            aggiornaRiepilogo();
        });
    }

    // --- 2. GESTIONE EVENTI ---

    // Intercetta l'invio del form "Aggiungi Prodotto"
    formAggiungi.addEventListener("submit", async (e) => {
        e.preventDefault(); 

        const codice = inputCodice.value.trim().toUpperCase();
        const quantita = parseInt(inputQuantita.value, 10);

        // Validazione robusta (gestisce NaN)
        if (!codice || !Number.isInteger(quantita) || quantita <= 0) {
            alert("Inserisci un codice e una quantità validi.");
            return;
        }

        // Cerca il prodotto chiamando lo script PHP
        try {
            const response = await fetch(`api/cerca_prodotto.php?codice=${encodeURIComponent(codice)}`);
            
            // Parse JSON response
            const result = await response.json();
            
            if (!response.ok || !result.success) {
                // Gestione errore standardizzata
                const errorMsg = result.error?.message || result.error || `Errore ${response.status}`;
                throw new Error(errorMsg);
            }
            
            const prodottoTrovato = result.data;
            
            // Aggiungi il prodotto alla comanda
            aggiungiProdottoAComanda(prodottoTrovato, quantita);
            
            inputCodice.value = "";
            inputQuantita.value = "1";
            inputCodice.focus();
        } catch (error) {
            console.error('Errore durante la ricerca del prodotto:', error);
            alert(`Errore: ${error.message}`);
            inputCodice.select();
        }
    });

    // Gestione click sul pulsante finale "Salva e Stampa"
    btnSalvaStampa.addEventListener("click", async () => {
        const nomeClienteEl = document.getElementById("input-nome");
        const selectTavoloEl = document.getElementById("select-tavolo");
        const inputCopertiEl = document.getElementById("input-coperti");
        const inputStaffEl = document.getElementById("input-staff");

        const nomeCliente = nomeClienteEl ? nomeClienteEl.value.trim() : "";
        const idTavolo = selectTavoloEl ? selectTavoloEl.value : "";
        const numCoperti = inputCopertiEl ? inputCopertiEl.value : "";
        const isStaff = inputStaffEl ? inputStaffEl.checked : false;
        const sconto = inputSconto ? parseFloat(inputSconto.value) || 0 : 0;

        if (!nomeCliente || !idTavolo) {
            alert("Per favore, compila Nome Cliente e Tavolo prima di salvare.");
            return;
        }
        if (comandaCorrente.length === 0) {
            alert("Impossibile salvare un ordine vuoto.");
            return;
        }

        // Calcola subtotale, coperti e totale con sconto
        const subtotale = calcolaTotale();
        const totaleCoperti = parseInt(numCoperti, 10) * costoCoperto;
        const totale = Math.max(0, subtotale + totaleCoperti - sconto);

        const datiOrdine = {
            nome_cliente: nomeCliente,
            id_tavolo: idTavolo,
            numero_coperti: numCoperti,
            totale: totale,
            sconto: sconto,
            staff: isStaff,
            dettagli: comandaCorrente
        };

        // Invia i dati al server per il salvataggio e la stampa
        try {
            btnSalvaStampa.disabled = true;
            btnSalvaStampa.textContent = "Salvataggio in corso...";

            const response = await fetch('api/salva_ordine.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(datiOrdine)
            });

            // Parse JSON response
            const result = await response.json();
            
            if (!response.ok || !result.success) {
                // Gestione errore standardizzata
                const errorMsg = result.error?.message || result.error || `Errore ${response.status}`;
                throw new Error(errorMsg);
            }

            // Mostra messaggio di successo con l'ID ordine e stato stampa
            const orderId = result.data?.order_id || 'N/A';
            const printStatus = result.data?.print_status;
            
            let statusMessage = `✓ Ordine #${orderId} salvato con successo!\n\n`;
            
            // Aggiungi informazioni sullo stato delle stampe se disponibili
            if (printStatus) {
                const totalComande = (printStatus.sent || 0) + (printStatus.pending || 0) + (printStatus.error || 0);
                if (totalComande > 0) {
                    statusMessage += `Stato Stampa:\n`;
                    if (printStatus.sent > 0) {
                        statusMessage += `✓ ${printStatus.sent} comanda(e) inviata(e)\n`;
                    }
                    if (printStatus.pending > 0) {
                        statusMessage += `⏳ ${printStatus.pending} comanda(e) in attesa\n`;
                    }
                    if (printStatus.error > 0) {
                        statusMessage += `⚠ ${printStatus.error} comanda(e) con errore\n`;
                    }
                } else {
                    statusMessage += `Ricevuta stampata\n`;
                }
            } else {
                statusMessage += `L'ordine è stato salvato e inviato in stampa\n`;
            }
            
            alert(statusMessage);
            
            // Resetta lo stato in memoria e l'interfaccia
            resetOrderState();

        } catch (error) {
            console.error('Errore durante il salvataggio dell\'ordine:', error);
            alert(`Errore: ${error.message}`);
            btnSalvaStampa.disabled = false;
            btnSalvaStampa.textContent = "Salva e Stampa Comande";
        }
    });

    // --- 3. FUNZIONI DI SUPPORTO (QUASI INVARIATE) ---

    function resetOrderState() {
        // Svuota la comanda corrente
        comandaCorrente = [];
        aggiornaRiepilogo();
        
        // Reset campi form testata
        const nomeClienteEl = document.getElementById("input-nome");
        const selectTavoloEl = document.getElementById("select-tavolo");
        const inputCopertiEl = document.getElementById("input-coperti");
        const inputStaffEl = document.getElementById("input-staff");
        
        if (nomeClienteEl) nomeClienteEl.value = "";
        if (selectTavoloEl) selectTavoloEl.value = "";
        if (inputCopertiEl) inputCopertiEl.value = "1";
        if (inputStaffEl) inputStaffEl.checked = false;
        
        // Reset campo sconto
        if (inputSconto) inputSconto.value = "0";
        
        // Reset campi form aggiungi prodotto
        inputCodice.value = "";
        inputQuantita.value = "1";
        
        // Riabilita il pulsante e ripristina il testo
        btnSalvaStampa.disabled = false;
        btnSalvaStampa.textContent = "Salva e Stampa Comande";
        
        // Rimetti il focus sul codice prodotto per facilitare un nuovo ordine
        inputCodice.focus();
    }

    function aggiungiProdottoAComanda(prodotto, quantita) {
        comandaCorrente.push({
            id_prodotto: prodotto.ID_Prodotto || null, // Aggiungiamo l'ID per il backend
            descrizione: prodotto.Descrizione || prodotto.nome || "Prodotto",
            prezzo_unitario: parseFloat(prodotto.Prezzo || prodotto.prezzo || 0),
            quantita: quantita
        });
        aggiornaRiepilogo();
    }

    function rimuoviProdottoDaComanda(indice) {
        comandaCorrente.splice(indice, 1);
        aggiornaRiepilogo();
    }
    
    function calcolaTotale() {
        return comandaCorrente.reduce((sum, item) => sum + (item.prezzo_unitario * item.quantita), 0);
    }

    function aggiornaRiepilogo() {
        tabellaCorpo.innerHTML = "";
        
        if (comandaCorrente.length === 0) {
            tabella.classList.remove("has-items");
            msgComandaVuota.classList.remove("has-items");
        } else {
            tabella.classList.add("has-items");
            msgComandaVuota.classList.add("has-items");

            comandaCorrente.forEach((item, index) => {
                const riga = document.createElement("tr");
                // Calcola prezzo totale per riga: prezzo_unitario × quantità
                const prezzoRiga = (item.prezzo_unitario || 0) * item.quantita;
                riga.innerHTML = `
                    <td>${escapeHtml(item.descrizione)}</td>
                    <td>${item.quantita}</td>
                    <td>${prezzoRiga.toFixed(2)} €</td>
                    <td><button class="btn-rimuovi" data-index="${index}" type="button">X</button></td>
                `;
                const btnRimuovi = riga.querySelector(".btn-rimuovi");
                if (btnRimuovi) {
                    btnRimuovi.addEventListener("click", () => rimuoviProdottoDaComanda(index));
                    btnRimuovi.setAttribute("aria-label", `Rimuovi ${item.descrizione}`);
                }
                tabellaCorpo.appendChild(riga);
            });
        }
        
        // Calcola subtotale
        const subtotale = calcolaTotale();
        
        // Ottieni numero coperti e calcola costo coperti
        const inputCopertiEl = document.getElementById("input-coperti");
        const numCoperti = inputCopertiEl ? (parseInt(inputCopertiEl.value, 10) || 0) : 0;
        const totaleCoperti = numCoperti * costoCoperto;
        
        // Ottieni sconto e gestisci ordine staff
        const inputStaffEl = document.getElementById("input-staff");
        const isStaff = inputStaffEl ? inputStaffEl.checked : false;
        const sconto = inputSconto ? (parseFloat(inputSconto.value) || 0) : 0;
        
        // Se è ordine staff, mostra 0.00
        if (isStaff) {
            subtotaleDisplay.textContent = "0.00";
            copertiDisplay.textContent = "0.00";
            totaleDisplay.textContent = "0.00";
            subtotaleDisplay.style.color = '#28a745';
            subtotaleDisplay.style.fontWeight = 'bold';
            totaleDisplay.style.color = '#28a745';
            totaleDisplay.style.fontWeight = 'bold';
            // Disabilita sconto per ordini staff
            if (inputSconto) {
                inputSconto.disabled = true;
                inputSconto.value = "0";
            }
        } else {
            // Calcola totale con coperti e sconto
            const totale = Math.max(0, subtotale + totaleCoperti - sconto);
            
            subtotaleDisplay.textContent = subtotale.toFixed(2);
            copertiDisplay.textContent = totaleCoperti.toFixed(2);
            totaleDisplay.textContent = totale.toFixed(2);
            subtotaleDisplay.style.color = '';
            subtotaleDisplay.style.fontWeight = '';
            totaleDisplay.style.color = '';
            totaleDisplay.style.fontWeight = '';
            
            // Riabilita sconto per ordini normali
            if (inputSconto) {
                inputSconto.disabled = false;
            }
        }
    }

    // Piccola utilità per evitare XSS quando inseriamo testi dinamici nelle celle
    function escapeHtml(str) {
        if (typeof str !== 'string') return str;
        return str.replace(/[&<>"'`=\/]/g, function (s) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '/': '&#x2F;',
                '`': '&#x60;',
                '=': '&#x3D;'
            })[s];
        });
    }
});
