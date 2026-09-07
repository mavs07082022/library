<?php
// index.php - Entry point for HostForge health check
// Redirect to homepage

// If this is a health check request, return success
if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'HostForge') !== false) {
    http_response_code(200);
    echo 'OK';
    exit;
}

// For normal visitors, redirect to homepage
header('Location: homepage.php');
exit;
?>