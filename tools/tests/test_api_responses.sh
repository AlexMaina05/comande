#!/bin/bash

# Script di test manuale per verificare lo schema di risposta uniforme delle API
# Usage: ./test_api_responses.sh [BASE_URL]
# Example: ./test_api_responses.sh http://localhost/RICEVUTE

# Colori per output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Base URL (default: localhost)
BASE_URL="${1:-http://localhost/RICEVUTE}"

echo ""
echo "=============================================="
echo "  Test Schema Risposta Uniforme API"
echo "  Issue #6 - Uniform API responses"
echo "=============================================="
echo ""
echo "Base URL: ${BLUE}$BASE_URL${NC}"
echo ""

# Funzione helper per visualizzare le risposte
print_test_header() {
    echo ""
    echo "----------------------------------------------"
    echo -e "${YELLOW}Test: $1${NC}"
    echo "----------------------------------------------"
}

print_result() {
    local status=$1
    local http_code=$2
    local response=$3
    
    echo -e "${BLUE}HTTP Status:${NC} $http_code"
    echo -e "${BLUE}Response:${NC}"
    if command -v jq &> /dev/null; then
        echo "$response" | jq '.'
    else
        echo "$response"
    fi
    
    # Verifica schema
    if echo "$response" | grep -q '"success"'; then
        echo -e "${GREEN}✓ Campo 'success' presente${NC}"
    else
        echo -e "${RED}✗ Campo 'success' mancante${NC}"
    fi
    
    if [ "$status" = "success" ]; then
        if echo "$response" | grep -q '"data"'; then
            echo -e "${GREEN}✓ Campo 'data' presente${NC}"
        else
            echo -e "${RED}✗ Campo 'data' mancante${NC}"
        fi
    else
        if echo "$response" | grep -q '"error"'; then
            echo -e "${GREEN}✓ Campo 'error' presente${NC}"
            if echo "$response" | grep -q '"code"' && echo "$response" | grep -q '"message"'; then
                echo -e "${GREEN}✓ Campi 'error.code' e 'error.message' presenti${NC}"
            else
                echo -e "${RED}✗ Campi 'error.code' o 'error.message' mancanti${NC}"
            fi
        else
            echo -e "${RED}✗ Campo 'error' mancante${NC}"
        fi
    fi
}

# Test 1: GET cerca_prodotto.php - Successo (richiede un prodotto esistente)
print_test_header "GET /api/cerca_prodotto.php?codice=TEST (Successo)"
response=$(curl -s -w "\n%{http_code}" "${BASE_URL}/api/cerca_prodotto.php?codice=TEST")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')
print_result "success" "$http_code" "$body"

# Test 2: GET cerca_prodotto.php - Errore validazione (400)
print_test_header "GET /api/cerca_prodotto.php (Errore validazione - codice mancante)"
response=$(curl -s -w "\n%{http_code}" "${BASE_URL}/api/cerca_prodotto.php")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')
print_result "error" "$http_code" "$body"

# Test 3: GET cerca_prodotto.php - Prodotto non trovato (404)
print_test_header "GET /api/cerca_prodotto.php?codice=NONEXISTENT (Errore 404)"
response=$(curl -s -w "\n%{http_code}" "${BASE_URL}/api/cerca_prodotto.php?codice=PRODOTTO_INESISTENTE_XYZ999")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')
print_result "error" "$http_code" "$body"

# Test 4: GET gestisci_dati.php - Successo
print_test_header "GET /api/gestisci_dati.php?data=2024-01-01 (Successo)"
response=$(curl -s -w "\n%{http_code}" "${BASE_URL}/api/gestisci_dati.php?data=2024-01-01")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')
print_result "success" "$http_code" "$body"

# Test 5: GET gestisci_dati.php - Errore validazione (400)
print_test_header "GET /api/gestisci_dati.php (Errore validazione - data mancante)"
response=$(curl -s -w "\n%{http_code}" "${BASE_URL}/api/gestisci_dati.php")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')
print_result "error" "$http_code" "$body"

# Test 6: GET genera_report.php - Formato data invalido (400)
print_test_header "GET /api/genera_report.php?data=invalid (Errore validazione - formato invalido)"
response=$(curl -s -w "\n%{http_code}" "${BASE_URL}/api/genera_report.php?data=invalid-date-format")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')
print_result "error" "$http_code" "$body"

# Test 7: POST salva_ordine.php - JSON vuoto (400)
print_test_header "POST /api/salva_ordine.php (Errore validazione - JSON vuoto)"
response=$(curl -s -w "\n%{http_code}" -X POST -H "Content-Type: application/json" -d '{}' "${BASE_URL}/api/salva_ordine.php")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')
print_result "error" "$http_code" "$body"

# Test 8: POST salva_ordine.php - Dettagli mancanti (400)
print_test_header "POST /api/salva_ordine.php (Errore validazione - dettagli mancanti)"
response=$(curl -s -w "\n%{http_code}" -X POST -H "Content-Type: application/json" -d '{"nome_cliente":"Test Cliente","totale":50.00}' "${BASE_URL}/api/salva_ordine.php")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')
print_result "error" "$http_code" "$body"

# Test 9: POST ripeti_comanda.php - JSON vuoto (400)
print_test_header "POST /api/ripeti_comanda.php (Errore validazione - id_comanda mancante)"
response=$(curl -s -w "\n%{http_code}" -X POST -H "Content-Type: application/json" -d '{}' "${BASE_URL}/api/ripeti_comanda.php")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')
print_result "error" "$http_code" "$body"

# Test 10: POST ripeti_comanda.php - Comanda non trovata (404)
print_test_header "POST /api/ripeti_comanda.php (Errore 404 - comanda inesistente)"
response=$(curl -s -w "\n%{http_code}" -X POST -H "Content-Type: application/json" -d '{"id_comanda":999999999}' "${BASE_URL}/api/ripeti_comanda.php")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')
print_result "error" "$http_code" "$body"

echo ""
echo "=============================================="
echo -e "  ${GREEN}Test completati${NC}"
echo "=============================================="
echo ""
echo "Note:"
echo "- Verifica che tutte le risposte abbiano il campo 'success'"
echo "- Le risposte di successo devono avere il campo 'data'"
echo "- Le risposte di errore devono avere 'error' con 'code' e 'message'"
echo "- I codici HTTP devono essere appropriati (200, 400, 404, 500)"
echo ""
