#!/bin/bash
#
# Script di test per validare il campo sconto in api/salva_ordine.php
# Test la validazione del campo sconto
#
# Uso: ./scripts/test_discount_validation.sh [BASE_URL]
# Esempio: ./scripts/test_discount_validation.sh http://localhost/RICEVUTE
#

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Base URL del server (default: localhost)
BASE_URL="${1:-http://localhost/RICEVUTE}"

echo "=========================================="
echo "Test Validazione Campo Sconto"
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
    
    response=$(curl -s -w "\n%{http_code}" -X POST "$url" \
        -H "Content-Type: application/json" \
        -d "$data" 2>&1)
    
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
echo "Test api/salva_ordine.php - Campo Sconto"
echo "=========================================="

# Test 1: Sconto negativo (dovrebbe fallire)
test_endpoint \
    "Sconto negativo" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10}],"totale":5,"sconto":-5}' \
    "400"

# Test 2: Sconto non numerico (dovrebbe fallire)
test_endpoint \
    "Sconto non numerico" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10}],"totale":5,"sconto":"abc"}' \
    "400"

# Test 3: Sconto troppo grande (dovrebbe fallire)
test_endpoint \
    "Sconto troppo grande" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10}],"totale":0,"sconto":9999999}' \
    "400"

# Test 4: Sconto zero (dovrebbe funzionare - se DB disponibile)
test_endpoint \
    "Sconto zero" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"nome_cliente":"Test","id_tavolo":1,"numero_coperti":1,"dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10}],"totale":10,"sconto":0}' \
    "200"

# Test 5: Sconto valido (dovrebbe funzionare - se DB disponibile)
test_endpoint \
    "Sconto valido" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"nome_cliente":"Test","id_tavolo":1,"numero_coperti":1,"dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10}],"totale":8,"sconto":2}' \
    "200"

# Test 6: Ordine staff con sconto (sconto dovrebbe essere forzato a 0)
test_endpoint \
    "Ordine staff con sconto" \
    "POST" \
    "$BASE_URL/api/salva_ordine.php" \
    '{"nome_cliente":"Test","id_tavolo":1,"numero_coperti":1,"staff":true,"dettagli":[{"id_prodotto":1,"quantita":1,"prezzo_unitario":10}],"totale":0,"sconto":5}' \
    "200"

echo "=========================================="
echo "Test completati!"
echo "=========================================="
echo ""
echo "Nota: I test con codice atteso 200 potrebbero fallire se"
echo "il database non contiene dati validi o se il server non è"
echo "configurato correttamente."
echo ""
