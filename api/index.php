<?php
// api/index.php - Main Router
require_once 'config.php';

$method = getMethod();
$path = getPath();

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // If path is empty or '/', show API info
    if (empty($path) || $path === '/') {
        jsonResponse([
            'message' => 'Library Admin API v1.0',
            'status' => 'running',
            'endpoints' => [
                'POST /auth' => 'Login',
                'GET /admin' => 'Admin Dashboard',
                'GET /books' => 'Books Management',
                'GET /members' => 'Members Management',
                'GET /borrowings' => 'Borrowings Management'
            ],
            'frontend' => 'http://localhost:3000'
        ]);
        exit;
    }

    switch ($path) {
        case 'auth':
            require_once 'auth.php';
            break;
        case 'admin':
            require_once 'admin.php';
            break;
        case 'books':
            require_once 'books.php';
            break;
        case 'members':
            require_once 'members.php';
            break;
        case 'borrowings':
            require_once 'borrowings.php';
            break;
        default:
            jsonResponse(['error' => 'Endpoint not found: ' . $path], 404);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
?>