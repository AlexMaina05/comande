# Implementazione Campo Sconto - Riepilogo

## Panoramica
Questo documento descrive l'implementazione del campo sconto nella pagina di creazione ordini come richiesto nell'issue.

## Modifiche Apportate

### 1. Database Schema
**File**: `sql/schema.sql` e `sql/migrations/2025-10-23_add_discount_field.sql`

- Aggiunto campo `Sconto DECIMAL(10,2) NOT NULL DEFAULT 0.00` alla tabella `ORDINI`
- Aggiunto indice `idx_ordini_sconto` per query di reporting
- Il campo `Totale_Ordine` continua a memorizzare il prezzo finale dopo lo sconto

**Migrazione SQL**:
```sql
ALTER TABLE ORDINI 
ADD COLUMN Sconto DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER Totale_Ordine;

ALTER TABLE ORDINI 
ADD INDEX idx_ordini_sconto (Sconto);
```

### 2. Interfaccia Utente (cassa.php)
**File**: `cassa.php`

Modificato il contenitore del totale per mostrare:
- Subtotale (somma prodotti)
- Campo input per sconto
- Totale finale (subtotale - sconto)

**Struttura HTML**:
```html
<div id="totale-container">
    <div class="totale-row">
        <span>Subtotale:</span>
        <span id="subtotale-display">0.00</span> €
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
```

### 3. Logica JavaScript (cassa.js)
**File**: `cassa.js`

**Modifiche principali**:
1. Aggiunto riferimento all'elemento `input-sconto`
2. Aggiunto evento `input` per ricalcolare il totale quando cambia lo sconto
3. Modificata funzione `aggiornaRiepilogo()` per:
   - Mostrare subtotale e totale separati
   - Applicare lo sconto al calcolo del totale
   - Disabilitare lo sconto per ordini staff
4. Modificata funzione per salvare l'ordine per inviare lo sconto al backend
5. Aggiunto reset del campo sconto in `resetOrderState()`

**Logica chiave**:
```javascript
const subtotale = calcolaTotale();
const sconto = inputSconto ? (parseFloat(inputSconto.value) || 0) : 0;
const totale = Math.max(0, subtotale - sconto);
```

### 4. Backend API (api/salva_ordine.php)
**File**: `api/salva_ordine.php`

**Modifiche**:
1. Aggiunta estrazione parametro `sconto` dall'input JSON
2. Per ordini staff, sconto forzato a 0
3. Aggiunta validazione sconto:
   - Deve essere un numero (float o int)
   - Range: 0 - 999999.99
4. Aggiornato SQL INSERT per includere campo `Sconto`
5. Modificata stampa ricevuta per mostrare:
   - Subtotale
   - Sconto (se > 0)
   - Totale finale

**Validazione**:
```php
// Validazione sconto
if (!InputValidator::validate_type($sconto, 'float') && !InputValidator::validate_type($sconto, 'int')) {
    ApiResponse::sendError('Sconto deve essere un numero', 1031, 400);
}
if (!InputValidator::validate_range($sconto, 0, 999999.99)) {
    ApiResponse::sendError('Sconto deve essere tra 0 e 999999.99', 1032, 400);
}
```

### 5. Stili CSS (style.css)
**File**: `style.css`

Aggiunto stile per il contenitore totale con layout flessibile:
- `.totale-row`: Layout flex per allineamento
- `.sconto-row`: Stile specifico per la riga sconto
- `.totale-finale`: Evidenziazione del totale finale

### 6. Test Script
**File**: `scripts/test_discount_validation.sh`

Creato script di test per validare:
- Sconto negativo (deve fallire)
- Sconto non numerico (deve fallire)
- Sconto troppo grande (deve fallire)
- Sconto zero (deve funzionare)
- Sconto valido (deve funzionare)
- Ordine staff con sconto (sconto deve essere forzato a 0)

## Comportamento

### Caso 1: Ordine Normale con Sconto
1. L'utente aggiunge prodotti alla comanda
2. Il subtotale viene calcolato automaticamente
3. L'utente inserisce uno sconto (es. 5.00€)
4. Il totale viene aggiornato: Totale = Subtotale - Sconto
5. Al salvataggio, il database memorizza:
   - `Totale_Ordine`: prezzo finale (es. 45.00€)
   - `Sconto`: importo sconto (es. 5.00€)
6. La ricevuta stampata mostra:
   ```
   SUBTOTALE:     50.00 EUR
   SCONTO:         5.00 EUR
   ---------------------------
   TOTALE:        45.00 EUR
   ```

### Caso 2: Ordine Normale senza Sconto
1. L'utente aggiunge prodotti alla comanda
2. Il campo sconto rimane a 0.00
3. Il totale è uguale al subtotale
4. La ricevuta non mostra la riga sconto:
   ```
   TOTALE:        50.00 EUR
   ```

### Caso 3: Ordine Staff
1. L'utente seleziona "Ordine Staff"
2. Il campo sconto viene disabilitato e forzato a 0
3. Il totale viene forzato a 0.00 (comportamento esistente)
4. Al salvataggio, sia `Totale_Ordine` che `Sconto` sono 0

## Compatibilità

### Database
- È necessario eseguire la migrazione SQL prima di utilizzare la nuova funzionalità
- Gli ordini esistenti avranno `Sconto = 0.00` (valore di default)

### Report
- I report esistenti continuano a funzionare correttamente
- `SUM(o.Totale_Ordine)` già include il totale finale dopo lo sconto
- Se necessario, in futuro è possibile aggiungere colonne per visualizzare gli sconti nei report

## Note Tecniche

### Validazione
- Sconto deve essere >= 0 e <= 999999.99
- Il totale finale non può essere negativo (viene forzato a 0 se sconto > subtotale)
- Per ordini staff, sconto è sempre 0

### Sicurezza
- Input sanitizzati tramite `InputValidator`
- Protezione SQL injection tramite prepared statements
- Validazione type-safe per tutti i parametri numerici

### Performance
- Indice aggiunto su campo `Sconto` per query di reporting efficienti
- Nessun impatto sulle query esistenti

## Testing

### Test Manuali
1. Verificare che il campo sconto appaia nella pagina cassa
2. Aggiungere prodotti e inserire uno sconto
3. Verificare che il totale venga ricalcolato correttamente
4. Salvare l'ordine e verificare che venga salvato in database
5. Verificare la stampa della ricevuta

### Test Automatici
Eseguire lo script di test:
```bash
./scripts/test_discount_validation.sh http://your-server-url/RICEVUTE
```

## File Modificati
1. `sql/schema.sql` - Schema database aggiornato
2. `sql/migrations/2025-10-23_add_discount_field.sql` - Script di migrazione
3. `cassa.php` - UI aggiornata
4. `cassa.js` - Logica frontend aggiornata
5. `style.css` - Stili aggiornati
6. `api/salva_ordine.php` - Backend aggiornato
7. `scripts/test_discount_validation.sh` - Nuovo script di test

## Checklist Implementazione
- [x] Schema database aggiornato
- [x] Script di migrazione creato
- [x] UI aggiornata con campo sconto
- [x] JavaScript aggiornato per gestire sconto
- [x] Backend aggiornato per validare e salvare sconto
- [x] Stili CSS aggiunti
- [x] Stampa ricevuta aggiornata
- [x] Test script creato
- [x] Code review completata
- [x] Security check completata
- [x] Documentazione creata
