<?php
// api/search.php - NLP-Powered Search
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$query = isset($_GET['q']) ? $_GET['q'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'semantic';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

if (empty($query)) {
    jsonResponse(['error' => 'Search query is required'], 400);
}

// Check if NLP service is available
if (isNLPServiceRunning()) {
    $payload = json_encode([
        'query' => $query,
        'type' => 'semantic',
        'limit' => $limit
    ]);
    
    $ch = curl_init(NLP_SERVICE_SEARCH);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        echo $response;
        exit;
    }
}

// Fallback: Basic search
try {
    $queryLower = strtolower($query);
    $conditions = [];
    $conditions[] = 'title.ilike.%' . urlencode($query) . '%';
    $conditions[] = 'author.ilike.%' . urlencode($query) . '%';
    $conditions[] = 'description.ilike.%' . urlencode($query) . '%';
    $conditions[] = 'isbn.ilike.%' . urlencode($query) . '%';
    $conditions[] = 'keywords.ilike.%' . urlencode($query) . '%';
    
    $queryString = 'books?select=*,categories(name)&or=(' . implode(',', $conditions) . ')';
    $books = supabaseRequest($queryString);
    
    // Calculate relevance
    foreach ($books as &$book) {
        $score = 0;
        $text = strtolower($book['title'] . ' ' . $book['author'] . ' ' . ($book['description'] ?? ''));
        if (strpos($text, $queryLower) !== false) $score += 50;
        $book['relevance'] = min($score, 100);
        $book['search_type'] = 'basic';
    }
    
    jsonResponse([
        'query' => $query,
        'type' => 'basic',
        'count' => count($books),
        'results' => $books
    ]);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
?>