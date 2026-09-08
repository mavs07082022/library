<?php
// index.php - Entry point for HostForge

// ============================================
// 1. HEALTH CHECK - Respond immediately
// ============================================
if ($_SERVER['REQUEST_URI'] === '/' || 
    $_SERVER['REQUEST_URI'] === '/health' || 
    $_SERVER['REQUEST_URI'] === '/index.php') {
    http_response_code(200);
    echo "OK";
    exit;
}

// ============================================
// 2. ROUTING - Send all other requests to frontend/src/
// ============================================
$requestUri = ltrim($_SERVER['REQUEST_URI'], '/');

// Remove query string if present
if (strpos($requestUri, '?') !== false) {
    $requestUri = substr($requestUri, 0, strpos($requestUri, '?'));
}

// Map root requests to frontend/src/
// e.g., /login -> /frontend/src/login.php
$basePath = __DIR__ . '/frontend/src/';
$targetFile = $basePath . $requestUri;

// If the request is for a specific file (e.g., login.php, student_dashboard.php)
if (file_exists($targetFile) && is_file($targetFile)) {
    // Serve PHP files directly
    if (pathinfo($targetFile, PATHINFO_EXTENSION) === 'php') {
        include $targetFile;
        exit;
    }
    // Serve static files (images, css, etc.)
    $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
    $mime_types = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
        'css' => 'text/css', 'js' => 'application/javascript'
    ];
    if (isset($mime_types[$ext])) {
        header('Content-Type: ' . $mime_types[$ext]);
    }
    readfile($targetFile);
    exit;
}

// If the request is for a PHP file without extension (e.g., /login)
if (file_exists($basePath . $requestUri . '.php')) {
    include $basePath . $requestUri . '.php';
    exit;
}

// If the request is for the homepage (e.g., /homepage or just /)
if (empty($requestUri) || $requestUri === 'homepage') {
    include $basePath . 'homepage.php';
    exit;
}

// If nothing matches, serve the homepage as a fallback
include $basePath . 'homepage.php';
exit;
?>