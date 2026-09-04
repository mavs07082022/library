<?php
// api/track_click.php - Track when users click on book results

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = getInput();
$userId = $input['user_id'] ?? null;
$bookId = $input['book_id'] ?? null;
$sessionId = $input['session_id'] ?? null;
$query = $input['query'] ?? '';

if (!$userId || !$bookId) {
    jsonResponse(['error' => 'Missing required fields'], 400);
}

try {
    if ($sessionId && $query) {
        $sessions = supabaseRequest(
            'search_sessions?select=id&user_id=eq.' . $userId . 
            '&session_id=eq.' . $sessionId . 
            '&query=eq.' . urlencode($query) . 
            '&order=created_at.desc&limit=1'
        );
        
        if (!empty($sessions)) {
            $currentClicks = supabaseRequest(
                'search_sessions?select=results_clicked&id=eq.' . $sessions[0]['id']
            );
            $newClicks = isset($currentClicks[0]) ? ($currentClicks[0]['results_clicked'] + 1) : 1;
            
            supabaseRequest(
                'search_sessions?id=eq.' . $sessions[0]['id'],
                'PATCH',
                ['results_clicked' => $newClicks]
            );
        }
    }
    
    $history = supabaseRequest(
        'user_search_history?select=id,clicked_books&user_id=eq.' . $userId . 
        '&query=eq.' . urlencode($query) . 
        '&order=created_at.desc&limit=1'
    );
    
    if (!empty($history)) {
        $clickedBooks = $history[0]['clicked_books'] ?? [];
        if (!in_array($bookId, $clickedBooks)) {
            $clickedBooks[] = $bookId;
            supabaseRequest(
                'user_search_history?id=eq.' . $history[0]['id'],
                'PATCH',
                ['clicked_books' => $clickedBooks]
            );
        }
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Click tracked'
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}