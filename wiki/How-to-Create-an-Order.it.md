# Come creare un ordine

Questa guida spiega come creare ordini nel sistema RICEVUTE per tutti gli scenari: ordini normali, ordini per staff e ordini con sconto.

## Riferimento rapido

| Tipo di ordine | Checkbox Staff | Campo Sconto | Totale | Appare nei report |
|---------------|----------------|-------------:|-------:|------------------:|
| **Ordine normale** | ❌ Non selezionato | Facoltativo (€0,00+) | Calcolato | ✅ Sì |
| **Ordine Staff** | ✅ **SELEZIONATO** | Disabilitato | **€0,00** | ❌ No |
| **Ordine con sconto** | ❌ Non selezionato | ✅ Inserire importo | Subtotale - Sconto | ✅ Sì |

## Indice

1. Panoramica
2. Accesso alla pagina Cassa
3. Creare un ordine normale
4. Creare un ordine per staff
5. Creare un ordine con sconto
6. Comprendere la ricevuta
7. Consigli e best practice

---

## Panoramica

Il sistema RICEVUTE permette di creare tre tipi di ordini:

- **Ordini normali**: ordini cliente inclusi nei report giornalieri e nei ricavi
- **Ordini staff**: ordini per personale con costo 0, esclusi dai report
- **Ordini con sconto**: ordini cliente con sconto applicato

Tutti gli ordini si creano attraverso la pagina **Cassa**.

### Albero decisionale: quale tipo di ordine?

Start -> È per lo staff? — SÌ → ORDINE STAFF (spuntare la checkbox "Ordine Staff")
                         — NO → Ha uno sconto? — SÌ → ORDINE CON SCONTO
                                                — NO → ORDINE NORMALE

---

## Accesso alla pagina Cassa

1. Accedi al sistema RICEVUTE
2. Dalla home clicca su **"Cassa"** o vai a `/public/cassa.php`
3. Vedrai l'interfaccia per la creazione dell'ordine

![Interfaccia Cassa](../docs/screenshots/cassa.png)

---

## Creare un ordine normale

Un ordine normale contribuisce ai ricavi giornalieri.

### Passo 1: Inserire i dati principali

1. **Nome Cliente**: es. "Mario Rossi" (default: "Cliente" se vuoto)
2. **Tavolo / Asporto**: seleziona un tavolo o "Asporto / Ritiro"
   - Se scegli "ASPORTO", i coperti vengono impostati automaticamente a 0
3. **Numero Coperti**: minimo 0, massimo 999, default 1

### Passo 2: Aggiungere prodotti

1. **Codice Prodotto**: inserisci codice (es. "PIZZA001") e premi Invio o clicca "Aggiungi Prodotto"
2. **Quantità**: default 1
3. Ripeti per tutti i prodotti

### Passo 3: Controllare l'ordine

Il pannello di riepilogo mostra:
- Lista prodotti
- Subtotale
- Coperti
- Sconto
- TOTALE

Rimuovi prodotti con l'icona ❌ se serve.

### Passo 4: Salva e Stampa

1. Clicca **"Salva e Stampa Comande"**
2. Il sistema salva l'ordine, genera le comande per reparto e la ricevuta cliente, e mostra un messaggio di successo.

### Esempio

Cliente: Mario Rossi
Tavolo: Tavolo 5
Coperti: 4

Prodotti:
- 2x Pizza Margherita @ €8,50 = €17,00
- 4x Acqua Naturale @ €3,00 = €12,00

Subtotale: €29,00
Coperti: €8,00 (4 x €2,00)
Sconto: €0,00
TOTALE: €37,00

---

## Creare un ordine per staff

Gli ordini staff hanno totale fissato a €0,00 e non compaiono nei report di vendita.

### Passi principali

1. Inserisci il nome (es. "Giuseppe - Staff")
2. Seleziona tavolo o asporto
3. Imposta numero coperti
4. **Importante**: spunta la checkbox **"Ordine Staff"** PRIMA di salvare

Cosa succede quando la checkbox è attiva:
- Il campo sconto viene disabilitato
- Il totale sarà €0,00
- L'ordine sarà salvato con `Staff = true` nel DB e verrà comunque stampato per i reparti

### Esempio

Cliente: Giuseppe - Staff
Tavolo: Tavolo 12
Coperti: 1
✅ Ordine Staff: CHECKED

Prodotti:
- 1x Pizza Margherita @ €8,50
- 1x Coca Cola @ €3,00

Subtotale: (lista prodotti)
Sconto: DISABILITATO
TOTALE: €0,00

---

## Creare un ordine con sconto

Puoi applicare uno sconto a qualsiasi ordine normale. Lo sconto riduce il totale e viene mostrato sulla ricevuta.

### Passo 1: Crea l'ordine normalmente

### Passo 2: Applica lo sconto

1. Nel pannello di riepilogo trova il campo **"Sconto"**
2. Inserisci l'importo in euro (es. `5.00`)
3. Il totale viene aggiornato automaticamente: `TOTALE = Subtotale + Coperti - Sconto`

### Passo 3: Salva e Stampa

La ricevuta mostrerà Subtotale, Sconto e Totale finale.

### Regole sconto

- Sconto ammesso da €0,00 a €999.999,99
- Se lo sconto supera il subtotale, il totale è impostato a €0,00 (minimo)
- Gli sconti sono salvati separatamente nel DB e compaiono nelle ricevute
- Gli ordini staff non possono avere sconto (campo disabilitato)

---

## Comprendere la ricevuta

Il sistema genera più ricevute quando salvi un ordine:

1. Ricevuta cliente: mostra dettagli, subtotale, coperti, sconto e totale
2. Comande reparto: estratti per cucina, bar, ecc.

Le ricevute con sconto mostrano lo sconto applicato.

---

## Consigli e best practice

- Per ordini normali: ricontrolla il tavolo e i prodotti prima del salvataggio
- Per ordini staff: spunta **Ordine Staff** prima di aggiungere prodotti
- Per sconti: inserisci l'importo prima di salvare l'ordine
- Verifica le stampanti e il corretto funzionamento prima dei periodi di punta

### Scorciatoie da tastiera

- Tab: spostamento campi
- Enter: aggiunge il prodotto (quando focalizzato sul campo codice)
- ESC: cancella il campo corrente

---

## Risoluzione problemi comuni

- Non riesco ad aggiungere prodotti: controlla il codice prodotto
- Totale non aggiornato: ricarica la pagina e verifica che JS sia abilitato
- Ordine staff mostra il prezzo: assicurati di aver spuntato la checkbox
- Sconto non applicato: inseriscilo prima del salvataggio
- Ricevuta non stampa: controlla le stampanti e la configurazione admin

---

## Documentazione correlata

- [Documentazione API](../docs/API.md)
- [Feature Staff Flag](../docs/STAFF_FLAG_FEATURE.md)
- [Implementazione campo Sconto](../docs/DISCOUNT_FIELD_IMPLEMENTATION.md)

---

*Ultimo aggiornamento: Ottobre 2024*
