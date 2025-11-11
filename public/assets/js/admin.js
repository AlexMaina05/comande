// Aspetta che tutta la pagina HTML sia caricata prima di eseguire il codice
document.addEventListener("DOMContentLoaded", () => {

    // --- 1. DEFINIZIONE ELEMENTI GLOBALI ---
    const tabs = document.querySelectorAll(".tab-link");
    const contents = document.querySelectorAll(".tab-content");
    const modal = document.getElementById("edit-modal");
    const modalTitle = document.getElementById("modal-title");
    const modalForm = document.getElementById("modal-form");
    const modalBody = document.getElementById("modal-body");
    const closeModalButton = document.querySelector(".close-button");

    // --- robustness checks ---
    function isNodeListNonEmpty(nl) { return nl && nl.length && nl.length > 0; }
    if (!isNodeListNonEmpty(tabs) || !isNodeListNonEmpty(contents)) {
        console.warn("admin.js: tabs o contents mancanti, alcuni comportamenti potrebbero non funzionare.");
    }

    // helper per evitare XSS quando inseriamo testo
    function createTextCell(text) {
        const td = document.createElement('td');
        td.textContent = (text === null || text === undefined) ? '' : String(text);
        return td;
    }

    function createButtonsCell(id) {
        const td = document.createElement('td');
        const edit = document.createElement('button');
        edit.className = 'btn-edit';
        edit.dataset.id = String(id);
        edit.type = 'button';
        edit.textContent = 'Modifica';
        const del = document.createElement('button');
        del.className = 'btn-delete';
        del.dataset.id = String(id);
        del.type = 'button';
        del.textContent = 'Elimina';
        td.appendChild(edit);
        td.appendChild(del);
        return td;
    }

    // --- STATO DELL'APPLICAZIONE ---
    let appData = {
        prodotti: [],
        tavoli: [],
        categorie: [],
        reparti: []
    };

    // --- 2. GESTIONE TAB DI NAVIGAZIONE ---
    if (isNodeListNonEmpty(tabs)) {
        tabs.forEach(tab => {
            tab.addEventListener("click", () => {
                tabs.forEach(item => item.classList.remove("active"));
                contents.forEach(item => item.classList.remove("active"));

                const targetContent = document.getElementById(tab.dataset.tab);
                if (!targetContent) return;
                tab.classList.add("active");
                targetContent.classList.add("active");

                currentSection = tab.dataset.tab;
                renderData();
            });
        });
    }

    // --- 3. GESTIONE MODALE (POPUP) ---
    function openModal() { if (modal) modal.style.display = "block"; }
    function closeModal() { if (modal) modal.style.display = "none"; }

    if (closeModalButton) {
        closeModalButton.addEventListener("click", closeModal);
    }
    if (modal) {
        window.addEventListener("click", (event) => {
            if (event.target === modal) closeModal();
        });
        // chiudi con ESC
        window.addEventListener("keydown", (ev) => {
            if (ev.key === "Escape") closeModal();
        });
    }

    if (modalForm) {
        modalForm.addEventListener("submit", (e) => {
            e.preventDefault();
            alert("Funzionalità non ancora completa nel backend!\nI dati sono stati inviati, ma lo script PHP deve essere aggiornato per salvarli.");
            closeModal();
        });
    }

    // --- 4. FUNZIONI DI RENDERING (sicure, senza innerHTML) ---
    function renderData() {
        switch (currentSection) {
            case 'prodotti': renderProdotti(); break;
            case 'tavoli': renderTavoli(); break;
            case 'categorie': renderCategorie(); break;
            case 'reparti': renderReparti(); break;
            case 'tab-impostazioni': loadImpostazioni(); break;
        }
    }

    function renderProdotti() {
        const tbody = document.getElementById("table-body-prodotti");
        if (!tbody) return;
        tbody.innerHTML = "";
        appData.prodotti.forEach(item => {
            const tr = document.createElement("tr");
            tr.appendChild(createTextCell(item.ID_Prodotto));
            tr.appendChild(createTextCell(item.Codice_Prodotto));
            tr.appendChild(createTextCell(item.Descrizione));
            tr.appendChild(createTextCell((parseFloat(item.Prezzo) || 0).toFixed(2) + " €"));
            tr.appendChild(createButtonsCell(item.ID_Prodotto));
            tbody.appendChild(tr);
        });
    }

    function renderTavoli() {
        const tbody = document.getElementById("table-body-tavoli");
        if (!tbody) return;
        tbody.innerHTML = "";
        appData.tavoli.forEach(item => {
            const tr = document.createElement("tr");
            tr.appendChild(createTextCell(item.ID_Tavolo));
            tr.appendChild(createTextCell(item.Nome_Tavolo));
            const badgeTd = document.createElement("td");
            const span = document.createElement("span");
            span.className = "badge " + (item.Tipo_Servizio ? String(item.Tipo_Servizio).toLowerCase() : "");
            span.textContent = item.Tipo_Servizio || "";
            badgeTd.appendChild(span);
            tr.appendChild(badgeTd);
            tr.appendChild(createButtonsCell(item.ID_Tavolo));
            tbody.appendChild(tr);
        });
    }

    function renderCategorie() {
        const tbody = document.getElementById("table-body-categorie");
        if (!tbody) return;
        tbody.innerHTML = "";
        appData.categorie.forEach(item => {
            const tr = document.createElement("tr");
            tr.appendChild(createTextCell(item.ID_Categoria));
            tr.appendChild(createTextCell(item.Nome_Categoria));
            tr.appendChild(createButtonsCell(item.ID_Categoria));
            tbody.appendChild(tr);
        });
    }

    function renderReparti() {
        const tbody = document.getElementById("table-body-reparti");
        if (!tbody) return;
        tbody.innerHTML = "";
        appData.reparti.forEach(item => {
            const tr = document.createElement("tr");
            tr.appendChild(createTextCell(item.ID_Reparto));
            tr.appendChild(createTextCell(item.Nome_Reparto));
            tr.appendChild(createTextCell(item.Nome_Stampante_LAN));
            tr.appendChild(createButtonsCell(item.ID_Reparto));
            tbody.appendChild(tr);
        });
    }

    // --- 5. GESTIONE CLICK SUI PULSANTI ---
    document.body.addEventListener("click", (e) => {
        const target = e.target;
        if (target.classList.contains("btn-edit") || target.classList.contains("btn-add")) {
            alert("Funzionalità non ancora completa nel backend!\nIl form apparirà quando lo script PHP sarà aggiornato per gestire 'create' e 'update'.");
            return;
        }

        if (target.classList.contains("btn-delete")) {
            const idRaw = target.dataset.id;
            const id = parseInt(idRaw, 10);
            if (Number.isNaN(id)) {
                console.warn("ID non valido per delete:", idRaw);
                return;
            }
            if (confirm(`Sei sicuro di voler eliminare l'elemento #${id}?\n(Questa azione non funzionerà finché il backend non sarà completo).`)) {
                // futura chiamata al backend
            }
        }
    });

    // --- 6. FUNZIONE PER CARICARE I DATI DAL SERVER (con controllo response.ok) ---
    async function loadInitialData() {
        try {
            const urls = [
                'api/gestisci_dati.php?section=prodotti',
                'api/gestisci_dati.php?section=tavoli',
                'api/gestisci_dati.php?section=categorie',
                'api/gestisci_dati.php?section=reparti'
            ];
            const responses = await Promise.all(urls.map(u => fetch(u)));

            for (let i = 0; i < responses.length; i++) {
                const res = responses[i];
                if (!res.ok) {
                    const txt = await res.text().catch(() => res.statusText || 'Errore sconosciuto');
                    throw new Error(`Errore caricamento ${urls[i]}: ${txt}`);
                }
            }

            // Parse responses with new standardized format
            const jsonResults = await Promise.all(responses.map(r => r.json()));
            
            // Extract data from standardized response format (check for success field)
            appData.prodotti = jsonResults[0].success ? jsonResults[0].data : jsonResults[0];
            appData.tavoli = jsonResults[1].success ? jsonResults[1].data : jsonResults[1];
            appData.categorie = jsonResults[2].success ? jsonResults[2].data : jsonResults[2];
            appData.reparti = jsonResults[3].success ? jsonResults[3].data : jsonResults[3];

            renderData();
        } catch (error) {
            console.error("Errore durante il caricamento dei dati:", error);
            alert("Impossibile caricare i dati dal server. Controlla la connessione al database e lo script PHP.");
        }
    }

    // --- 7. GESTIONE IMPOSTAZIONI ---
    async function loadImpostazioni() {
        try {
            const response = await fetch('api/gestisci_impostazioni.php?chiave=costo_coperto');
            
            if (!response.ok) {
                throw new Error(`Errore HTTP: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error?.message || 'Errore sconosciuto');
            }
            
            const impostazione = result.data;
            const inputCostoCoperto = document.getElementById('input-costo-coperto');
            
            if (inputCostoCoperto && impostazione) {
                inputCostoCoperto.value = parseFloat(impostazione.Valore || 0).toFixed(2);
            }
        } catch (error) {
            console.error('Errore caricamento impostazioni:', error);
            const statusEl = document.getElementById('status-costo-coperto');
            if (statusEl) {
                statusEl.textContent = '⚠️ Errore caricamento impostazioni';
                statusEl.style.color = '#d9534f';
            }
        }
    }
    
    // Gestione form costo coperto
    const formCostoCoperto = document.getElementById('form-costo-coperto');
    if (formCostoCoperto) {
        formCostoCoperto.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const inputCostoCoperto = document.getElementById('input-costo-coperto');
            const statusEl = document.getElementById('status-costo-coperto');
            const value = inputCostoCoperto.value;
            
            if (!value || parseFloat(value) < 0) {
                if (statusEl) {
                    statusEl.textContent = '⚠️ Inserisci un valore valido';
                    statusEl.style.color = '#d9534f';
                }
                return;
            }
            
            try {
                const response = await fetch('api/gestisci_impostazioni.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        chiave: 'costo_coperto',
                        valore: value
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`Errore HTTP: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error?.message || 'Errore durante il salvataggio');
                }
                
                if (statusEl) {
                    statusEl.textContent = '✓ Salvato con successo';
                    statusEl.style.color = '#5cb85c';
                    
                    // Rimuovi il messaggio dopo 3 secondi
                    setTimeout(() => {
                        statusEl.textContent = '';
                    }, 3000);
                }
            } catch (error) {
                console.error('Errore salvataggio impostazione:', error);
                if (statusEl) {
                    statusEl.textContent = `⚠️ ${error.message}`;
                    statusEl.style.color = '#d9534f';
                }
            }
        });
    }

    // --- 8. GESTIONE EXPORT / BACKUP ---
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('export-btn')) {
            const table = e.target.dataset.table;
            const format = e.target.dataset.format;
            
            // Conferma prima dell'export
            const formatName = format === 'csv' ? 'CSV' : 'SQL';
            const tableName = table.charAt(0).toUpperCase() + table.slice(1);
            
            if (confirm(`Vuoi esportare la tabella ${tableName} in formato ${formatName}?\n\nIl file verrà scaricato direttamente nel tuo browser.`)) {
                // Genera URL per l'export
                const exportUrl = `api/gestisci_dati.php?action=export&table=${encodeURIComponent(table)}&format=${encodeURIComponent(format)}`;
                
                // Crea un link nascosto e attiva il download
                const link = document.createElement('a');
                link.href = exportUrl;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Feedback all'utente
                console.log(`Export avviato: ${table} (${format})`);
            }
        }
    });

    // --- INIZIALIZZAZIONE ---
    loadInitialData();

});

