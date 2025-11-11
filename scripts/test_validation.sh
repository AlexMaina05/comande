#!/bin/bash
#
# Script di test manuale per validazione input API
# Riferimento: Issue #5
# https://github.com/AlexMaina05/RICEVUTE/issues/5
#
# Esegue una serie di richieste curl con payload non validi contro gli endpoint API
# per verificare che la validazione funzioni correttamente.
#
# Uso: ./scripts/test_validation.sh [BASE_URL]
# Esempio: ./scripts/test_validation.sh http://localhost/RICEVUTE
#

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Base URL del server (default: localhost)
BASE_URL="${1:-http://localhost/RICEVUTE}"

echo "=========================================="
echo "Test Validazione Input API - Issue #5"
echo "Base URL: $BASE_URL"
echo "=========================================="
echo ""

# Funzione per testare un endpoint
test_endpoint() {
    local test_name="$1"
    local method="$2"
    local url="$3"
    local data="$4"
    local expected_code="$5"
    
    echo -e "${YELLOW}Test: $test_name${NC}"
    echo "  URL: $url"
    echo "  Method: $method"
    if [ -n "$data" ]; then
        echo "  Data: $data"
    fi
    
    if [ "$method" = "POST" ]; then
        response=$(curl -s -w "\n%{http_code}" -X POST "$url" \
            -H "Content-Type: application/json" \
            -d "$data" 2>&1)
    else
        response=$(curl -s -w "\n%{http_code}" "$url" 2>&1)
    fi
    
    http_code=$(echo "$response" | tail -n 1)
    body=$(echo "$response" | head -n -1)
    
    echo "  Response Code: $http_code"
    echo "  Response Body: $body"
    
    if [ "$http_code" = "$expected_code" ]; then
        echo -e "  ${GREEN}✓ PASS${NC}"
    else
        echo -e "  ${RED}✗ FAIL (expected $expected_code)${NC}"
    fi
    echo ""
}

echo "=========================================="
echo "1. Test api/gestisci_dati.php"
echo "=========================================="

# Test 1.1: Parametro 'data' mancante
test_endpoint \
    "Data mancante" \
    "GET" \
    "$BASE_URL/api/gestisci_dati.php" \
    "" \
    "400"

# Test 1.2: Formato data non valido
test_endpoint \
    "Formato data non valido" \
    "GET" \
    "$BASE_URL/api/gestisci_dati.php?data=invalid-date" \
    "" \
    "400"

# Test 1.3: Data troppo lunga
test_endpoint \
    "Data troppo lunga" \
    "GET" \
    "$BASE_URL/api/gestisci_dati.php?data=2024-01-01-extra-long-string" \
    "" \
    "400"

# Test 1.4: Data valida (dovrebbe funzionare)
test_endpoint \
    "Data valida" \
    "GET" \
    "$BASE_URL/api/gestisci_dati.php?data=2024-01-01" \
    "" \
    "200"

echo "=========================================="
echo "2. Test api/cerca_prodotto.php"
echo "=========================================="

# Test 2.1: Parametro 'codice' mancante
test_endpoint \
    "Codice mancante" \
    "GET" \
    "$BASE_URL/api/cerca_prodotto.php" \
    "" \
    "400"

# Test 2.2: Codice vuoto
test_endpoint \
    "Codice vuoto" \
    "GET" \
    "$BASE_URL/api/cerca_prodotto.php?codice=" \
    "" \
    "400"

# Test 2.3: Codice troppo lungo (>64 caratteri)
test_endpoint \
    "Codice troppo lungo" \
    "GET" \
    "$BASE_URL/api/cerca_prodotto.php?codice=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA" \
    "" \
    "400"

echo "=========================================="
echo "3. Test api/salva_ordine.php"
echo "=========================================="

# Test 3.1: JSON vuoto
test_endpoint \
    "JSON vuoto" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    "" \
    "400"

# Test 3.2: JSON non valido
test_endpoint \
    "JSON non valido" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    "invalid json" \
    "400"

# Test 3.3: Campo 'dettagli' mancante
test_endpoint \
    "Campo dettagli mancante" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"nome_cliente":"Test","totale":10}' \
    "400"

# Test 3.4: Campo 'dettagli' vuoto
test_endpoint \
    "Campo dettagli vuoto" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"nome_cliente":"Test","dettagli":[],"totale":10}' \
    "400"

# Test 3.5: ID tavolo non numerico
test_endpoint \
    "ID tavolo non numerico" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"id_tavolo":"abc","dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10}],"totale":10}' \
    "400"

# Test 3.6: Numero coperti non numerico
test_endpoint \
    "Numero coperti non numerico" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"numero_coperti":"abc","dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10}],"totale":10}' \
    "400"

# Test 3.7: Numero coperti negativo
test_endpoint \
    "Numero coperti negativo" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"numero_coperti":-5,"dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10}],"totale":10}' \
    "400"

# Test 3.8: Totale negativo
test_endpoint \
    "Totale negativo" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10}],"totale":-10}' \
    "400"

# Test 3.9: Totale troppo grande
test_endpoint \
    "Totale troppo grande" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10}],"totale":9999999}' \
    "400"

# Test 3.10: Dettaglio con id_prodotto mancante
test_endpoint \
    "Dettaglio senza id_prodotto" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"dettagli":[{"quantita":1,"prezzo_unitario":10}],"totale":10}' \
    "400"

# Test 3.11: Dettaglio con quantita negativa
test_endpoint \
    "Dettaglio con quantita negativa" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"dettagli":[{"id_prodotto":1,"quantita":-1,"prezzo_unitario":10}],"totale":10}' \
    "400"

# Test 3.12: Dettaglio con prezzo negativo
test_endpoint \
    "Dettaglio con prezzo negativo" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":-10}],"totale":10}' \
    "400"

# Test 3.13: Nome cliente troppo lungo
test_endpoint \
    "Nome cliente troppo lungo" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"nome_cliente":"AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA","dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10}],"totale":10}' \
    "400"

echo "=========================================="
echo "4. Test api/ripeti_comanda.php"
echo "=========================================="

# Test 4.1: JSON vuoto
test_endpoint \
    "JSON vuoto" \
    "POST" \
    "$BASE_URL/api/ripeti_comanda.php" \
    "" \
    "400"

# Test 4.2: Campo 'id_comanda' mancante
test_endpoint \
    "Campo id_comanda mancante" \
    "POST" \
    "$BASE_URL/api/ripeti_comanda.php" \
    '{"other_field":"value"}' \
    "400"

# Test 4.3: ID comanda non numerico
test_endpoint \
    "ID comanda non numerico" \
    "POST" \
    "$BASE_URL/api/ripeti_comanda.php" \
    '{"id_comanda":"abc"}' \
    "400"

# Test 4.4: ID comanda zero
test_endpoint \
    "ID comanda zero" \
    "POST" \
    "$BASE_URL/api/ripeti_comanda.php" \
    '{"id_comanda":0}' \
    "400"

# Test 4.5: ID comanda negativo
test_endpoint \
    "ID comanda negativo" \
    "POST" \
    "$BASE_URL/api/ripeti_comanda.php" \
    '{"id_comanda":-5}' \
    "400"

echo "=========================================="
echo "Test completati!"
echo "=========================================="
echo ""
echo "Nota: I test con codice atteso 200 potrebbero fallire se"
echo "il database non contiene dati validi o se il server non è"
echo "configurato correttamente."
echo ""
