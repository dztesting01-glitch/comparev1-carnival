<?php
/**
 * Crypto Price Proxy Server
 * 
 * This script fetches live cryptocurrency prices from CryptoCompare API
 * and serves them as JSON for your local trading site.
 * 
 * @author Your Name
 * @version 1.0.0
 * 
 * USAGE:
 * 1. Upload this file to your PHP hosting (GitHub Pages, Netlify, etc.)
 * 2. Get the raw URL: https://raw.githubusercontent.com/username/repo/main/prices.php
 * 3. Add the URL in your admin panel: http://localhost/admin/currency/data/provider
 * 4. Prices will update automatically every few seconds
 * 
 * API ENDPOINTS:
 * - Get all prices:     ?symbols=BTC,ETH,USDT
 * - Get single price:   ?symbol=BTC
 * - Get top coins:      ?top=10
 * - Health check:       ?health=1
 */

// Configuration
define('API_BASE_URL', 'https://min-api.cryptocompare.com/data');
define('DEFAULT_SYMBOLS', 'BTC,ETH,USDT,BNB,XRP,ADA,DOGE,SOL,TRX,AVAX,dot,MATIC,LINK,UNI,ATOM');
define('CACHE_DURATION', 2); // seconds - keep data fresh

// Response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Simple caching to reduce API calls
$cacheFile = sys_get_temp_dir() . '/crypto_prices_cache.json';
$cacheTime = file_exists($cacheFile) ? filemtime($cacheFile) : 0;

// Health check endpoint
if (isset($_GET['health'])) {
    echo json_encode([
        'status' => 'ok',
        'timestamp' => time(),
        'api_url' => API_BASE_URL,
        'version' => '1.0.0'
    ]);
    exit;
}

// Get requested symbols
$symbols = isset($_GET['symbols']) ? $_GET['symbols'] : 
           (isset($_GET['symbol']) ? $_GET['symbol'] : DEFAULT_SYMBOLS);

// Top coins endpoint
if (isset($_GET['top'])) {
    $limit = intval($_GET['top']);
    $url = API_BASE_URL . '/topcoins?limit=' . $limit . '&tsym=USD';
} else {
    // Price multi endpoint
    $url = API_BASE_URL . '/pricemulti?fsyms=' . urlencode($symbols) . '&tsyms=USD';
}

// Check cache (only 2 seconds cache)
if (file_exists($cacheFile) && (time() - $cacheTime) < CACHE_DURATION) {
    $cachedData = json_decode(file_get_contents($cacheFile), true);
    if ($cachedData) {
        $cachedData['cached'] = true;
        echo json_encode($cachedData);
        exit;
    }
}

// Make API request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'User-Agent: CryptoPriceProxy/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Build response
if ($httpCode == 200 && $response) {
    $data = json_decode($response, true);
    
    if ($data) {
        // Format response
        $result = [
            'success' => true,
            'timestamp' => time(),
            'cached' => false,
            'count' => count($data),
            'data' => $data
        ];
        
        // Cache the response
        file_put_contents($cacheFile, json_encode($result));
        
        echo json_encode($result);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON response from API',
            'timestamp' => time()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => $error ?: 'HTTP Error: ' . $httpCode,
        'http_code' => $httpCode,
        'timestamp' => time()
    ]);
}