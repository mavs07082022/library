<?php
// api/start_nlp.php - Start NLP Service via API
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

function startNLPService() {
    if (isNLPServiceRunning()) {
        return true;
    }
    
    if (!file_exists(NLP_SCRIPT_PATH)) {
        return false;
    }
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = 'start /MIN cmd /c "cd /d ' . NLP_WORKING_DIR . ' && ' . PYTHON_PATH . ' app.py"';
        shell_exec($command . ' 2>&1');
    } else {
        $command = 'nohup ' . PYTHON_PATH . ' "' . NLP_SCRIPT_PATH . '" > /dev/null 2>&1 &';
        shell_exec($command);
    }
    
    // Wait and check
    for ($i = 0; $i < 10; $i++) {
        sleep(1);
        if (isNLPServiceRunning()) {
            return true;
        }
    }
    
    return false;
}

// Check if already running
if (isNLPServiceRunning()) {
    echo json_encode([
        'success' => true,
        'message' => 'NLP Service is already running',
        'status' => 'running'
    ]);
    exit;
}

// Start the service
$started = startNLPService();

if ($started) {
    echo json_encode([
        'success' => true,
        'message' => 'NLP Service started successfully',
        'status' => 'started'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to start NLP Service',
        'status' => 'failed'
    ]);
}