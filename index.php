<?php
// index.php - Root entry point for HostForge

// ============================================
// HEALTH CHECK - For HostForge
// ============================================
if ($_SERVER['REQUEST_URI'] === '/' || 
    $_SERVER['REQUEST_URI'] === '/health' || 
    $_SERVER['REQUEST_URI'] === '/index.php') {
    http_response_code(200);
    echo "OK";
    exit;
}

// ============================================
// ROUTE TO FILES IN frontend/src/
// ============================================
$uri = ltrim($_SERVER['REQUEST_URI'], '/');

// Remove query string
if (strpos($uri, '?') !== false) {
    $uri = substr($uri, 0, strpos($uri, '?'));
}

// If empty -> go to homepage
if (empty($uri)) {
    include __DIR__ . '/frontend/src/homepage.php';
    exit;
}

// Check if file exists in frontend/src/
if (file_exists(__DIR__ . '/frontend/src/' . $uri)) {
    // Serve PHP files
    if (pathinfo($uri, PATHINFO_EXTENSION) === 'php') {
        include __DIR__ . '/frontend/src/' . $uri;
        exit;
    }
    // Serve static files (images, css, js)
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    $mime_types = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'css' => 'text/css',
        'js' => 'application/javascript'
    ];
    if (isset($mime_types[$ext])) {
        header('Content-Type: ' . $mime_types[$ext]);
    }
    readfile(__DIR__ . '/frontend/src/' . $uri);
    exit;
}

// Try with .php extension
if (file_exists(__DIR__ . '/frontend/src/' . $uri . '.php')) {
    include __DIR__ . '/frontend/src/' . $uri . '.php';
    exit;
}

// Check if it's in root (for admin_logout.php)
if (file_exists(__DIR__ . '/' . $uri . '.php')) {
    include __DIR__ . '/' . $uri . '.php';
    exit;
}

// 404
http_response_code(404);
echo "404 - Page Not Found";
?>