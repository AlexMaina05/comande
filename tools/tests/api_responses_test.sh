#!/bin/bash

# Test script per verificare il formato uniforme delle risposte API
# Usage: ./api_responses_test.sh [BASE_URL]
# Example: ./api_responses_test.sh http://localhost/RICEVUTE

# Colori per output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Base URL (default: localhost)
BASE_URL="${1:-http://localhost/RICEVUTE}"

echo "======================================"
echo "Test API Response Format"
echo "Base URL: $BASE_URL"
echo "======================================"
echo ""

TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Funzione per testare una risposta
test_response() {
    local test_name="$1"
    local url="$2"
    local method="${3:-GET}"
    local data="${4:-}"
    local expected_http_code="$5"
    local should_succeed="${6:-true}"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    echo -n "Test $TOTAL_TESTS: $test_name... "
    
    # Esegui richiesta
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" "$url")
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" -H "Content-Type: application/json" -d "$data" "$url")
    fi
    
    # Estrai HTTP code e body
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    # Verifica HTTP code
    if [ "$http_code" != "$expected_http_code" ]; then
        echo -e "${RED}FAIL${NC} - HTTP code: $http_code (expected: $expected_http_code)"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
    
    # Verifica formato JSON
    if ! echo "$body" | jq empty 2>/dev/null; then
        echo -e "${RED}FAIL${NC} - Invalid JSON response"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
    
    # Verifica presenza campo success
    success_field=$(echo "$body" | jq -r '.success // empty')
    if [ -z "$success_field" ]; then
        echo -e "${RED}FAIL${NC} - Missing 'success' field"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
    
    # Verifica valore success
    if [ "$should_succeed" = "true" ]; then
        if [ "$success_field" != "true" ]; then
            echo -e "${RED}FAIL${NC} - Expected success=true, got success=$success_field"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            return 1
        fi
        
        # Verifica presenza campo data
        data_field=$(echo "$body" | jq '.data // empty')
        if [ -z "$data_field" ]; then
            echo -e "${RED}FAIL${NC} - Missing 'data' field in success response"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            return 1
        fi
    else
        if [ "$success_field" != "false" ]; then
            echo -e "${RED}FAIL${NC} - Expected success=false, got success=$success_field"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            return 1
        fi
        
        # Verifica struttura error
        error_code=$(echo "$body" | jq -r '.error.code // empty')
        error_message=$(echo "$body" | jq -r '.error.message // empty')
        
        if [ -z "$error_code" ] || [ -z "$error_message" ]; then
            echo -e "${RED}FAIL${NC} - Missing 'error.code' or 'error.message' field"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            return 1
        fi
    fi
    
    echo -e "${GREEN}PASS${NC}"
    PASSED_TESTS=$((PASSED_TESTS + 1))
    return 0
}

echo "--- Test 1: Successo (cerca_prodotto.php) ---"
# Nota: Questo test richiede che esista un prodotto con codice "TEST" nel database
# In alternativa, usare un codice prodotto valido dal database
test_response \
    "Cerca prodotto esistente (success)" \
    "$BASE_URL/api/cerca_prodotto.php?codice=TEST" \
    "GET" \
    "" \
    "200" \
    "true"

echo ""
echo "--- Test 2: Errore validazione (cerca_prodotto.php) ---"
test_response \
    "Cerca prodotto senza codice (error 400)" \
    "$BASE_URL/api/cerca_prodotto.php" \
    "GET" \
    "" \
    "400" \
    "false"

echo ""
echo "--- Test 3: Errore 404 (cerca_prodotto.php) ---"
test_response \
    "Cerca prodotto inesistente (error 404)" \
    "$BASE_URL/api/cerca_prodotto.php?codice=PRODOTTO_INESISTENTE_XYZ123" \
    "GET" \
    "" \
    "404" \
    "false"

echo ""
echo "--- Test 4: Errore validazione (genera_report.php) ---"
test_response \
    "Report senza data (error 400)" \
    "$BASE_URL/api/genera_report.php" \
    "GET" \
    "" \
    "400" \
    "false"

echo ""
echo "--- Test 5: Errore validazione formato (genera_report.php) ---"
test_response \
    "Report con data invalida (error 400)" \
    "$BASE_URL/api/genera_report.php?data=invalid-date" \
    "GET" \
    "" \
    "400" \
    "false"

echo ""
echo "--- Test 6: Successo (genera_report.php) ---"
test_response \
    "Report con data valida (success)" \
    "$BASE_URL/api/genera_report.php?data=2024-01-01" \
    "GET" \
    "" \
    "200" \
    "true"

echo ""
echo "--- Test 7: Errore validazione (salva_ordine.php) ---"
test_response \
    "Salva ordine con JSON vuoto (error 400)" \
    "$BASE_URL/api/salva_ordine.php" \
    "POST" \
    "{}" \
    "400" \
    "false"

echo ""
echo "--- Test 8: Errore validazione (salva_ordine.php) ---"
test_response \
    "Salva ordine senza dettagli (error 400)" \
    "$BASE_URL/api/salva_ordine.php" \
    "POST" \
    '{"nome_cliente":"Test","totale":10}' \
    "400" \
    "false"

echo ""
echo "--- Test 9: Errore validazione (ripeti_comanda.php) ---"
test_response \
    "Ripeti comanda senza ID (error 400)" \
    "$BASE_URL/api/ripeti_comanda.php" \
    "POST" \
    "{}" \
    "400" \
    "false"

echo ""
echo "--- Test 10: Errore 404 (ripeti_comanda.php) ---"
test_response \
    "Ripeti comanda inesistente (error 404)" \
    "$BASE_URL/api/ripeti_comanda.php" \
    "POST" \
    '{"id_comanda":999999999}' \
    "404" \
    "false"

echo ""
echo "======================================"
echo "Test Summary"
echo "======================================"
echo -e "Total:  $TOTAL_TESTS"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$FAILED_TESTS${NC}"
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed!${NC}"
    exit 1
fi
