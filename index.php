<?php
// index.php - Entry point with routing

// Get the request URI
$uri = $_SERVER['REQUEST_URI'];

// Health check
if ($uri === '/' || $uri === '/health' || $uri === '/health.php' || $uri === '/index.php') {
    http_response_code(200);
    echo "OK";
    exit;
}

// Remove query string
if (strpos($uri, '?') !== false) {
    $uri = substr($uri, 0, strpos($uri, '?'));
}

// Remove leading slash
$path = ltrim($uri, '/');

// If empty, show homepage
if (empty($path)) {
    include 'homepage.php';
    exit;
}

// If file exists with .php extension
if (file_exists($path . '.php')) {
    include $path . '.php';
    exit;
}

// If file exists
if (file_exists($path)) {
    // Serve static files
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $mime_types = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
    ];
    if (isset($mime_types[$ext])) {
        header('Content-Type: ' . $mime_types[$ext]);
    }
    readfile($path);
    exit;
}

// 404
http_response_code(404);
echo '404 Not Found';
?>