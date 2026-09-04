<?php
// api/test_render.php - Test connection to Render

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Test health check
$ch = curl_init(NLP_HEALTH);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode([
    'render_url' => NLP_SERVICE_URL,
    'health_check' => [
        'status' => $httpCode === 200 ? '✅ Connected' : '❌ Failed',
        'http_code' => $httpCode,
        'response' => $response ? json_decode($response, true) : null
    ],
    'timestamp' => date('Y-m-d H:i:s')
]);
?>