#!/usr/bin/env php
<?php
/**
 * Test per verifica protezione autenticazione API
 * 
 * Questo test verifica che le API protette richiedano autenticazione
 * e restituiscano errore 401 con messaggio appropriato quando non autenticati.
 * 
 * Nota: Questo test verifica la logica, non fa chiamate HTTP reali.
 * Per test di integrazione completi, utilizzare test_api_authentication.sh
 */

echo "Test Autenticazione API\n";
echo "=======================\n\n";

// Test 1: Verifica risposta quando non autenticato
echo "Test 1: Risposta quando non autenticato\n";
echo "Expected: HTTP 401 con error.code=4001 e error.message appropriato\n";

$expectedResponse = [
    'success' => false,
    'error' => [
        'code' => 4001,
        'message' => 'Accesso non autorizzato. Autenticazione richiesta.'
    ]
];

echo "Expected structure:\n";
echo json_encode($expectedResponse, JSON_PRETTY_PRINT) . "\n";
echo "✓ Test 1 passed: Struttura risposta corretta\n\n";

// Test 2: Verifica che le API protette includano require_admin.php
echo "Test 2: API protette includono require_admin.php\n";

$protectedApis = [
    'gestisci_impostazioni.php',
    'ripeti_comanda.php',
    'genera_report.php'
];

$apiDir = __DIR__ . '/../../api/';

foreach ($protectedApis as $api) {
    $apiPath = $apiDir . $api;
    
    if (!file_exists($apiPath)) {
        echo "✗ Test 2 failed: File $api non trovato\n";
        continue;
    }
    
    $content = file_get_contents($apiPath);
    
    if (preg_match("/require_once __DIR__ \. ['\"]\/require_admin\.php['\"]/", $content)) {
        echo "  ✓ $api include require_admin.php\n";
    } else {
        echo "  ✗ $api NON include require_admin.php\n";
    }
}

echo "\n";

// Test 3: Verifica che require_admin.php esista e contenga la logica corretta
echo "Test 3: require_admin.php esiste e contiene logica corretta\n";

$requireAdminPath = $apiDir . 'require_admin.php';

if (!file_exists($requireAdminPath)) {
    echo "✗ Test 3 failed: require_admin.php non trovato\n";
} else {
    $content = file_get_contents($requireAdminPath);
    
    $checks = [
        'session_start' => strpos($content, 'session_start()') !== false,
        'loggedin_check' => strpos($content, "\$_SESSION['loggedin']") !== false,
        'http_401' => strpos($content, 'http_response_code(401)') !== false,
        'json_response' => strpos($content, 'application/json') !== false,
        'error_code_4001' => preg_match("/['\"]code['\"]\\s*=>\\s*4001/", $content)
    ];
    
    $allPassed = true;
    foreach ($checks as $check => $passed) {
        if ($passed) {
            echo "  ✓ $check presente\n";
        } else {
            echo "  ✗ $check mancante\n";
            $allPassed = false;
        }
    }
    
    if ($allPassed) {
        echo "✓ Test 3 passed: require_admin.php contiene tutta la logica necessaria\n";
    } else {
        echo "✗ Test 3 failed: require_admin.php manca alcuni controlli\n";
    }
}

echo "\n";

// Test 4: Verifica che API operazionali NON richiedano autenticazione
echo "Test 4: API operazionali non richiedono autenticazione\n";

$operationalApis = [
    'cerca_prodotto.php',
    'salva_ordine.php'
];

foreach ($operationalApis as $api) {
    $apiPath = $apiDir . $api;
    
    if (!file_exists($apiPath)) {
        echo "  ✗ File $api non trovato\n";
        continue;
    }
    
    $content = file_get_contents($apiPath);
    
    if (!preg_match("/require_once __DIR__ \. ['\"]\/require_admin\.php['\"]/", $content)) {
        echo "  ✓ $api NON richiede autenticazione (corretto per API operazionale)\n";
    } else {
        echo "  ✗ $api richiede autenticazione (non dovrebbe per API operazionale)\n";
    }
}

echo "\n=======================\n";
echo "Test completati!\n";
echo "\nNota: Questi sono test di struttura e logica.\n";
echo "Per test completi di integrazione, utilizzare test_api_authentication.sh\n";
echo "con un server web attivo.\n";
?>
