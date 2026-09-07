<?php
// index.php - Main entry point for HostForge

// ============================================
// HEALTH CHECK - For HostForge
// ============================================
if ($_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/health') {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'server' => 'PHP Built-in',
        'version' => '1.0.0'
    ]);
    exit;
}

// ============================================
// ROUTE TO PHP FILES
// ============================================
$uri = ltrim($_SERVER['REQUEST_URI'], '/');

// If no URI, go to homepage
if (empty($uri)) {
    include 'homepage.php';
    exit;
}

// Remove query string if present
if (strpos($uri, '?') !== false) {
    $uri = substr($uri, 0, strpos($uri, '?'));
}

// Check if it's a PHP file
if (file_exists($uri) && pathinfo($uri, PATHINFO_EXTENSION) === 'php') {
    include $uri;
    exit;
}

// Try adding .php extension (for clean URLs like /student_dashboard)
if (file_exists($uri . '.php')) {
    include $uri . '.php';
    exit;
}

// ============================================
// SERVE STATIC FILES (CSS, JS, Images)
// ============================================
if (file_exists($uri) && !is_dir($uri)) {
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    $mime_types = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'json' => 'application/json',
        'txt' => 'text/plain',
        'html' => 'text/html'
    ];
    
    if (isset($mime_types[$ext])) {
        header('Content-Type: ' . $mime_types[$ext]);
    }
    readfile($uri);
    exit;
}

// ============================================
// 404 - Not Found
// ============================================
http_response_code(404);
echo '<h1>404 - Page Not Found</h1>';
echo '<p>The requested page could not be found.</p>';
echo '<p><a href="/">Return to Homepage</a></p>';
?>