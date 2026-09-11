<?php
// api/predict_search.php - Fallback predictions only
// AI predictions now run in the browser via Firebase AI Logic (see ai_functions.js)

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = getInput();
$partialQuery = $input['partial_query'] ?? '';

if (empty($partialQuery) || strlen($partialQuery) < 2) {
    jsonResponse(['predictions' => [], 'message' => 'Invalid query'], 400);
}

try {
    $books = supabaseRequest('books?select=*');
    $query = strtolower($partialQuery);
    $predictions = [];

    foreach ($books as $book) {
        $title = strtolower($book['title'] ?? '');
        $author = strtolower($book['author'] ?? '');
        $keywords = strtolower($book['keywords'] ?? '');
        
        $score = 0;
        if (strpos($title, $query) === 0) $score += 50;
        elseif (strpos($title, $query) !== false) $score += 30;
        if (strpos($author, $query) !== false) $score += 20;
        if (strpos($keywords, $query) !== false) $score += 15;
        
        if ($score > 0) {
            $book['relevance'] = min($score, 100);
            $book['prediction_score'] = $book['relevance'];
            $book['is_prediction'] = true;
            $predictions[] = $book;
        }
    }

    usort($predictions, function($a, $b) {
        return ($b['relevance'] ?? 0) - ($a['relevance'] ?? 0);
    });

    jsonResponse([
        'success' => true,
        'predictions' => array_slice($predictions, 0, 5),
        'count' => min(count($predictions), 5),
        'source' => 'basic',
        'message' => 'Fallback predictions (AI runs in browser)'
    ]);

} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'predictions' => [],
        'error' => $e->getMessage()
    ], 500);
}
?>
