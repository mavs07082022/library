<?php
// index.php - Entry point for HostForge

// Health check - Always returns OK for /index.php
if ($_SERVER['REQUEST_URI'] === '/index.php' || 
    $_SERVER['REQUEST_URI'] === '/health') {
    http_response_code(200);
    echo "OK";
    exit;
}

// For the root URL (/), show the homepage
if ($_SERVER['REQUEST_URI'] === '/') {
    include __DIR__ . '/frontend/src/homepage.php';
    exit;
}

// For any other request, try to serve the file
$uri = ltrim($_SERVER['REQUEST_URI'], '/');

// Remove query string
if (strpos($uri, '?') !== false) {
    $uri = substr($uri, 0, strpos($uri, '?'));
}

// Try frontend/src first
$file = __DIR__ . '/frontend/src/' . $uri;
if (file_exists($file) && !is_dir($file)) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        include $file;
        exit;
    }
    // Serve static files
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mime_types = [
        'png' => 'image/png', 'jpg' => 'image/jpeg',
        'css' => 'text/css', 'js' => 'application/javascript'
    ];
    if (isset($mime_types[$ext])) {
        header('Content-Type: ' . $mime_types[$ext]);
    }
    readfile($file);
    exit;
}

// Try with .php extension
if (file_exists(__DIR__ . '/frontend/src/' . $uri . '.php')) {
    include __DIR__ . '/frontend/src/' . $uri . '.php';
    exit;
}

// Fallback: show homepage
include __DIR__ . '/frontend/src/homepage.php';
exit;
?>
