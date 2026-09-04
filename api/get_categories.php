<?php
// api/get_categories.php - Get all categories

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $categories = supabaseRequest('categories?select=*');
    
    jsonResponse([
        'success' => true,
        'categories' => $categories,
        'count' => count($categories)
    ]);
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>