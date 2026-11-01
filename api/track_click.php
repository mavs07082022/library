<?php
// api/track_click.php - Record student clicks on catalog search results.

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
$userId = (string)$_SESSION['user_id'];
$bookId = trim((string)($input['book_id'] ?? ''));
$sessionId = trim((string)($input['session_id'] ?? ''));
$query = trim((string)($input['query'] ?? ''));

if ($bookId === '' || $sessionId === '' || $query === '') {
    jsonResponse(['success' => false, 'error' => 'Missing book ID, session ID, or query'], 400);
}

try {
    $encodedUserId = rawurlencode($userId);
    $encodedSessionId = rawurlencode($sessionId);
    $encodedQuery = rawurlencode($query);

    $sessions = supabaseRequest(
        'search_sessions?select=id,results_clicked&user_id=eq.' . $encodedUserId .
        '&session_id=eq.' . $encodedSessionId .
        '&query=eq.' . $encodedQuery .
        '&order=created_at.desc&limit=1'
    );

    if (!empty($sessions)) {
        $newClicks = (int)($sessions[0]['results_clicked'] ?? 0) + 1;
        supabaseRequest(
            'search_sessions?id=eq.' . rawurlencode((string)$sessions[0]['id']),
            'PATCH',
            ['results_clicked' => $newClicks, 'abandoned' => false]
        );
    }

    $history = supabaseRequest(
        'user_search_history?select=id,clicked_books&user_id=eq.' . $encodedUserId .
        '&query=eq.' . $encodedQuery .
        '&order=created_at.desc&limit=1'
    );

    if (!empty($history)) {
        $clickedBooks = $history[0]['clicked_books'] ?? [];
        if (!is_array($clickedBooks)) $clickedBooks = [];
        if (!in_array($bookId, array_map('strval', $clickedBooks), true)) {
            $clickedBooks[] = $bookId;
            supabaseRequest(
                'user_search_history?id=eq.' . rawurlencode((string)$history[0]['id']),
                'PATCH',
                ['clicked_books' => $clickedBooks]
            );
        }
    }

    jsonResponse(['success' => true, 'message' => 'Student result click recorded']);
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => 'Click tracking could not be saved.',
    ], 500);
}
?>
