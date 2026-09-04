<?php
// api/send_notification.php - Helper to send notifications
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

/**
 * Send a notification to a user
 * 
 * @param string $userId The user ID
 * @param string $title The notification title
 * @param string $message The notification message
 * @param string $type The notification type (fine, borrow, overdue, available, reservation, system)
 * @param string $icon The emoji icon
 * @param string|null $actionUrl Optional action URL
 * @param string|null $actionLabel Optional action label
 * @return array Result
 */
function sendNotification($userId, $title, $message, $type = 'system', $icon = '📢', $actionUrl = null, $actionLabel = null) {
    try {
        $data = [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'icon' => $icon,
            'is_read' => false
        ];
        
        if ($actionUrl) {
            $data['action_url'] = $actionUrl;
        }
        if ($actionLabel) {
            $data['action_label'] = $actionLabel;
        }
        
        $result = supabaseRequest('notifications', 'POST', $data);
        return [
            'success' => true,
            'notification' => $result[0] ?? null
        ];
    } catch (Exception $e) {
        error_log("Send notification error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// If called directly with parameters
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getInput();
    
    if (empty($input['user_id']) || empty($input['title']) || empty($input['message'])) {
        echo json_encode(['error' => 'User ID, title, and message are required']);
        exit;
    }
    
    $result = sendNotification(
        $input['user_id'],
        $input['title'],
        $input['message'],
        $input['type'] ?? 'system',
        $input['icon'] ?? '📢',
        $input['action_url'] ?? null,
        $input['action_label'] ?? null
    );
    
    echo json_encode($result);
    exit;
}

// If called via GET for testing
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test'])) {
    $userId = isset($_GET['user_id']) ? $_GET['user_id'] : '';
    if (empty($userId)) {
        echo json_encode(['error' => 'User ID required for test']);
        exit;
    }
    
    $result = sendNotification(
        $userId,
        '🔔 Test Notification',
        'This is a test notification sent via the API.',
        'system',
        '🔔'
    );
    
    echo json_encode($result);
    exit;
}