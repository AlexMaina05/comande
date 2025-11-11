#!/bin/bash
# Test manuale per verificare il calcolo server-side del totale
# 
# Questo script invia richieste di test all'API salva_ordine.php
# per verificare che il server ricalcoli correttamente il totale.
#
# Uso: bash test_salva_ordine_manual.sh [URL_BASE]
# Esempio: bash test_salva_ordine_manual.sh http://localhost/RICEVUTE

BASE_URL="${1:-http://localhost/RICEVUTE}"
API_URL="${BASE_URL}/api/salva_ordine.php"

echo "=========================================="
echo "Test Manuale - Calcolo Totale Server-Side"
echo "=========================================="
echo "API: $API_URL"
echo ""

# Colori
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test 1: Totale errato dal client
echo -e "${YELLOW}Test 1: Totale errato dal client (999.99 invece di ~25.00)${NC}"
echo "Inviando ordine con totale errato..."

RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d '{
    "nome_cliente": "Test Cliente 1",
    "id_tavolo": 1,
    "numero_coperti": 2,
    "totale": 999.99,
    "sconto": 0.00,
    "staff": false,
    "dettagli": [
      {
        "id_prodotto": 1,
        "quantita": 2,
        "prezzo_unitario": 10.00,
        "descrizione": "Prodotto Test 1"
      },
      {
        "id_prodotto": 2,
        "quantita": 1,
        "prezzo_unitario": 5.00,
        "descrizione": "Prodotto Test 2"
      }
    ]
  }')

echo "Risposta API:"
echo "$RESPONSE" | jq '.'
echo ""
echo "Totale atteso nel DB: ~25.00 (2*10.00 + 1*5.00 + coperti - 0.00)"
echo "VERIFICA: Controllare nel DB che Totale_Ordine sia ~25.00 e NON 999.99"
echo ""

# Test 2: Ordine con sconto
echo -e "${YELLOW}Test 2: Ordine con sconto${NC}"
echo "Inviando ordine con sconto..."

RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d '{
    "nome_cliente": "Test Cliente 2",
    "id_tavolo": 1,
    "numero_coperti": 1,
    "totale": 100.00,
    "sconto": 5.00,
    "staff": false,
    "dettagli": [
      {
        "id_prodotto": 1,
        "quantita": 1,
        "prezzo_unitario": 20.00,
        "descrizione": "Prodotto Test"
      }
    ]
  }')

echo "Risposta API:"
echo "$RESPONSE" | jq '.'
echo ""
echo "Totale atteso nel DB: ~15.00 (1*20.00 + coperti - 5.00)"
echo "VERIFICA: Controllare nel DB che Totale_Ordine sia ~15.00 e NON 100.00"
echo ""

# Test 3: Ordine staff
echo -e "${YELLOW}Test 3: Ordine staff (totale sempre 0)${NC}"
echo "Inviando ordine staff..."

RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d '{
    "nome_cliente": "Staff Test",
    "id_tavolo": 1,
    "numero_coperti": 1,
    "totale": 50.00,
    "sconto": 0.00,
    "staff": true,
    "dettagli": [
      {
        "id_prodotto": 1,
        "quantita": 3,
        "prezzo_unitario": 15.00,
        "descrizione": "Prodotto Staff"
      }
    ]
  }')

echo "Risposta API:"
echo "$RESPONSE" | jq '.'
echo ""
echo "Totale atteso nel DB: 0.00 (ordine staff)"
echo "VERIFICA: Controllare nel DB che Totale_Ordine sia 0.00 e NON 50.00"
echo ""

echo "=========================================="
echo "Test completati!"
echo "=========================================="
echo ""
echo -e "${BLUE}Per verificare i risultati, eseguire questa query SQL:${NC}"
echo ""
echo "SELECT ID_Ordine, Nome_Cliente, Totale_Ordine, Sconto, Staff, Data_Ora"
echo "FROM ORDINI"
echo "ORDER BY ID_Ordine DESC"
echo "LIMIT 3;"
echo ""
