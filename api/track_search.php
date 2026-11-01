<?php
// api/track_search.php - Record student catalog searches for better predictions.

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    jsonResponse(['success' => false, 'error' => 'Student login required'], 401);
}

$input = getInput();
$userId = (string)$_SESSION['user_id']; // Never trust a client-supplied user ID.
$sessionId = trim((string)($input['session_id'] ?? ''));
$query = trim((string)($input['query'] ?? ''));
$resultsCount = max(0, (int)($input['results_count'] ?? 0));

if ($sessionId === '' || $query === '') {
    jsonResponse(['success' => false, 'error' => 'Missing session ID or query'], 400);
}

try {
    $sessionData = [
        'user_id' => $userId,
        'session_id' => $sessionId,
        'query' => mb_substr($query, 0, 500, 'UTF-8'),
        'results_clicked' => 0,
        'time_spent_seconds' => 0,
        'abandoned' => ($resultsCount === 0),
    ];

    supabaseRequest('search_sessions', 'POST', $sessionData);

    supabaseRequest('user_search_history', 'POST', [
        'user_id' => $userId,
        'query' => mb_substr($query, 0, 500, 'UTF-8'),
        'results_count' => $resultsCount,
        'clicked_books' => [],
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Student search recorded',
    ]);
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => 'Search tracking could not be saved.',
    ], 500);
}
?>
