<?php
// deploy_check.php - Check if latest changes are deployed

echo "<h1>🔍 Deployment Check</h1>";

// Check student_dashboard.php - CORRECT PATH
$dashboard = @file_get_contents('frontend/src/students/student_dashboard.php');
if ($dashboard && strpos($dashboard, 'model_loaded') !== false) {
    echo "<p style='color:green'>✅ frontend/src/students/student_dashboard.php HAS the model_loaded fix</p>";
} else {
    echo "<p style='color:red'>❌ frontend/src/students/student_dashboard.php does NOT have the model_loaded fix</p>";
    if ($dashboard) {
        echo "<p>File exists but model_loaded not found</p>";
    } else {
        echo "<p>File not found at frontend/src/students/student_dashboard.php</p>";
    }
}

// Check config.php
$config = @file_get_contents('api/config.php');
if ($config && strpos($config, 'model_loaded') !== false) {
    echo "<p style='color:green'>✅ api/config.php HAS the model_loaded fix</p>";
} else {
    echo "<p style='color:red'>❌ api/config.php does NOT have the model_loaded fix</p>";
}

// Check file modification times
echo "<h2>📁 File Timestamps</h2>";
$files = ['frontend/src/students/student_dashboard.php', 'api/config.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p>$file: " . date('Y-m-d H:i:s', filemtime($file)) . "</p>";
    } else {
        echo "<p style='color:red'>$file: NOT FOUND</p>";
    }
}

// Test NLP connection
echo "<h2>🔗 NLP Service Test</h2>";
$ch = curl_init('https://lib-nlp-service-2.onrender.com/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    echo "<p style='color:green'>✅ NLP Service reachable (HTTP $httpCode)</p>";
    if (isset($data['model_loaded']) && $data['model_loaded'] === true) {
        echo "<p style='color:green;font-weight:bold;font-size:18px;'>✅ MODEL IS LOADED!</p>";
    } else {
        echo "<p style='color:orange;font-weight:bold;'>⚠️ Model not loaded</p>";
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }
} else {
    echo "<p style='color:red'>❌ Cannot reach NLP Service (HTTP $httpCode)</p>";
    if ($curlError) {
        echo "<p style='color:red'>CURL Error: $curlError</p>";
    }
}

echo "<h2>📝 What this means:</h2>";
echo "<ul>";
echo "<li>If student_dashboard.php shows ❌, the file hasn't been updated correctly</li>";
echo "<li>If student_dashboard.php shows ✅ but the dashboard still shows the error, it could be browser cache or opcache</li>";
echo "<li>If NLP Service shows ❌, there's a connection issue between HostForge and Render</li>";
echo "</ul>";

echo "<h2>🔧 Next Steps:</h2>";
echo "<ol>";
echo "<li>Edit frontend/src/students/student_dashboard.php on GitHub</li>";
echo "<li>Make sure the isNLPServiceRunning() function has the model_loaded check</li>";
echo "<li>Commit and push the changes</li>";
echo "<li>Trigger a manual deploy on HostForge</li>";
echo "<li>Clear browser cache and test again</li>";
echo "</ol>";
?>
