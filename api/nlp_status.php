<?php
// api/nlp_status.php - Check NLP Service Status
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

function isNLPServiceRunning() {
    $ch = curl_init(NLP_SERVICE_HEALTH);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode === 200 && $response !== false);
}

$running = isNLPServiceRunning();

echo json_encode([
    'running' => $running,
    'status' => $running ? 'online' : 'offline',
    'timestamp' => date('Y-m-d H:i:s'),
    'service_url' => NLP_SERVICE_URL,
    'health_endpoint' => NLP_SERVICE_HEALTH,
    'script_path' => NLP_SCRIPT_PATH,
    'working_dir' => NLP_WORKING_DIR
]);