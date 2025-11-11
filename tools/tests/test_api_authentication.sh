#!/bin/bash
# Test di integrazione per autenticazione API
# Verifica che le API protette richiedano autenticazione e restituiscano 401

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Controlla se è stato fornito l'URL base
if [ -z "$1" ]; then
    echo "Uso: $0 <base_url>"
    echo "Esempio: $0 http://localhost/RICEVUTE"
    exit 1
fi

BASE_URL="$1"

echo "=========================================="
echo "Test Autenticazione API"
echo "=========================================="
echo "Base URL: $BASE_URL"
echo ""

# Controlla se curl è installato
if ! command -v curl &> /dev/null; then
    echo -e "${RED}✗ curl non trovato. Installare curl per eseguire questo test.${NC}"
    exit 1
fi

# Controlla se jq è installato (opzionale ma consigliato)
JQ_AVAILABLE=false
if command -v jq &> /dev/null; then
    JQ_AVAILABLE=true
fi

# Contatori per statistiche
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Funzione per testare un endpoint protetto
test_protected_endpoint() {
    local endpoint="$1"
    local method="${2:-GET}"
    local data="${3:-}"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    echo "----------------------------------------"
    echo "Test: $endpoint (metodo $method)"
    echo "Expected: HTTP 401 con error.code=4001"
    
    # Costruisci il comando curl
    local url="${BASE_URL}/api/${endpoint}"
    local curl_cmd="curl -s -w '\n%{http_code}' -X $method"
    
    if [ ! -z "$data" ]; then
        curl_cmd="$curl_cmd -H 'Content-Type: application/json' -d '$data'"
    fi
    
    curl_cmd="$curl_cmd '$url'"
    
    # Esegui la richiesta
    response=$(eval $curl_cmd)
    
    # Separa body e status code
    http_code=$(echo "$response" | tail -n 1)
    body=$(echo "$response" | head -n -1)
    
    # Verifica status code
    if [ "$http_code" = "401" ]; then
        echo -e "${GREEN}✓ HTTP Status Code: 401 (corretto)${NC}"
    else
        echo -e "${RED}✗ HTTP Status Code: $http_code (atteso 401)${NC}"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo "Response body: $body"
        return
    fi
    
    # Verifica formato JSON
    if [ "$JQ_AVAILABLE" = true ]; then
        success=$(echo "$body" | jq -r '.success' 2>/dev/null)
        error_code=$(echo "$body" | jq -r '.error.code' 2>/dev/null)
        error_message=$(echo "$body" | jq -r '.error.message' 2>/dev/null)
        
        if [ "$success" = "false" ] && [ "$error_code" = "4001" ]; then
            echo -e "${GREEN}✓ Formato JSON corretto: success=false, error.code=4001${NC}"
            echo "  Message: $error_message"
            PASSED_TESTS=$((PASSED_TESTS + 1))
        else
            echo -e "${RED}✗ Formato JSON non corretto${NC}"
            echo "  success: $success (atteso: false)"
            echo "  error.code: $error_code (atteso: 4001)"
            echo "  Response: $body"
            FAILED_TESTS=$((FAILED_TESTS + 1))
        fi
    else
        # Senza jq, verifica solo che contenga i campi principali
        if echo "$body" | grep -q '"success"' && echo "$body" | grep -q '"error"' && echo "$body" | grep -q '4001'; then
            echo -e "${GREEN}✓ Formato JSON sembra corretto (verifica limitata senza jq)${NC}"
            PASSED_TESTS=$((PASSED_TESTS + 1))
        else
            echo -e "${RED}✗ Formato JSON non corretto${NC}"
            echo "  Response: $body"
            FAILED_TESTS=$((FAILED_TESTS + 1))
        fi
    fi
}

# Funzione per testare un endpoint operazionale (dovrebbe funzionare senza autenticazione)
test_operational_endpoint() {
    local endpoint="$1"
    local query_params="${2:-}"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    echo "----------------------------------------"
    echo "Test: $endpoint (API operazionale)"
    echo "Expected: NON deve restituire 401"
    
    local url="${BASE_URL}/api/${endpoint}"
    if [ ! -z "$query_params" ]; then
        url="${url}?${query_params}"
    fi
    
    http_code=$(curl -s -o /dev/null -w '%{http_code}' "$url")
    
    # Un'API operazionale dovrebbe restituire 200, 400, 404, ma NON 401
    if [ "$http_code" != "401" ]; then
        echo -e "${GREEN}✓ HTTP Status Code: $http_code (non richiede autenticazione)${NC}"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}✗ HTTP Status Code: 401 (l'API operazionale non dovrebbe richiedere autenticazione)${NC}"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

echo ""
echo "=========================================="
echo "Test API Protette (devono richiedere autenticazione)"
echo "=========================================="
echo ""

# Test API protette
test_protected_endpoint "gestisci_impostazioni.php"
test_protected_endpoint "ripeti_comanda.php" "POST" '{"id_comanda": 1}'
test_protected_endpoint "genera_report.php?data=2024-01-01"

echo ""
echo "=========================================="
echo "Test API Operazionali (non devono richiedere autenticazione)"
echo "=========================================="
echo ""

# Test API operazionali
test_operational_endpoint "cerca_prodotto.php" "codice=TEST001"
test_operational_endpoint "salva_ordine.php" # Questo probabilmente darà 400 (dati mancanti) ma non 401

echo ""
echo "=========================================="
echo "Riepilogo Test"
echo "=========================================="
echo "Test totali: $TOTAL_TESTS"
echo -e "${GREEN}Test superati: $PASSED_TESTS${NC}"
if [ $FAILED_TESTS -gt 0 ]; then
    echo -e "${RED}Test falliti: $FAILED_TESTS${NC}"
else
    echo -e "${GREEN}Test falliti: 0${NC}"
fi
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}✓ Tutti i test sono stati superati!${NC}"
    exit 0
else
    echo -e "${RED}✗ Alcuni test sono falliti.${NC}"
    exit 1
fi
