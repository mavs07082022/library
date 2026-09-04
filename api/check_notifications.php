<?php
// api/check_notifications.php - Check for new notifications
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'config.php';

$userId = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$lastCheck = isset($_GET['last_check']) ? intval($_GET['last_check']) : 0;

if (empty($userId)) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    // Get total unread count
    $unreadResult = supabaseRequest('notifications?select=count&user_id=eq.' . urlencode($userId) . '&is_read=eq.false');
    $unreadCount = isset($unreadResult[0]['count']) ? intval($unreadResult[0]['count']) : 0;
    
    // Get new notifications since last check
    if ($lastCheck > 0) {
        $newResult = supabaseRequest('notifications?select=count&user_id=eq.' . urlencode($userId) . '&created_at=gt.' . date('Y-m-d H:i:s', $lastCheck));
        $newCount = isset($newResult[0]['count']) ? intval($newResult[0]['count']) : 0;
    } else {
        $newCount = $unreadCount;
    }
    
    // Get latest notifications for preview
    $latestResult = supabaseRequest('notifications?select=id,title,message,type,icon,created_at,is_read&user_id=eq.' . urlencode($userId) . '&order=created_at.desc&limit=5');
    
    echo json_encode([
        'success' => true,
        'count' => $unreadCount,
        'new_count' => $newCount,
        'latest' => $latestResult ?: [],
        'timestamp' => time()
    ]);
} catch (Exception $e) {
    // If table doesn't exist, return empty
    if (strpos($e->getMessage(), 'PGRST205') !== false) {
        echo json_encode([
            'success' => true,
            'count' => 0,
            'new_count' => 0,
            'timestamp' => time(),
            'warning' => 'Notifications table not set up yet'
        ]);
    } else {
        error_log("Notification check error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}