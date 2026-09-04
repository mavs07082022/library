<?php
// api/track_search.php - Track user search activity

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = getInput();
$userId = $input['user_id'] ?? null;
$sessionId = $input['session_id'] ?? null;
$query = $input['query'] ?? '';
$resultsClicked = intval($input['results_clicked'] ?? 0);
$timeSpent = intval($input['time_spent'] ?? 0);
$abandoned = isset($input['abandoned']) ? (bool)$input['abandoned'] : false;
$clickedBooks = $input['clicked_books'] ?? [];

if (!$userId || !$sessionId || empty($query)) {
    jsonResponse(['error' => 'Missing required fields'], 400);
}

try {
    $sessionData = [
        'user_id' => $userId,
        'session_id' => $sessionId,
        'query' => $query,
        'results_clicked' => $resultsClicked,
        'time_spent_seconds' => $timeSpent,
        'abandoned' => $abandoned
    ];
    
    supabaseRequest('search_sessions', 'POST', $sessionData);
    
    $historyData = [
        'user_id' => $userId,
        'query' => $query,
        'results_count' => $input['results_count'] ?? 0,
        'clicked_books' => $clickedBooks
    ];
    
    supabaseRequest('user_search_history', 'POST', $historyData);
    
    $analysis = analyzeUserSession($userId, $sessionId);
    
    jsonResponse([
        'success' => true,
        'message' => 'Search tracked successfully',
        'analysis' => $analysis
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}