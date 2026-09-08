<?php
// index.php - Entry point for HostForge

// Health check - Always returns OK
if ($_SERVER['REQUEST_URI'] === '/' || 
    $_SERVER['REQUEST_URI'] === '/health' || 
    $_SERVER['REQUEST_URI'] === '/index.php') {
    http_response_code(200);
    echo "OK";
    exit;
}

// Serve the homepage directly - NO REDIRECTS!
include __DIR__ . '/frontend/src/homepage.php';
exit;
?>
