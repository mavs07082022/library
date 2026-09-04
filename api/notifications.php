<?php
// api/notifications.php - Complete Notification API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$userId = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

// ============================================
// GET NOTIFICATIONS
// ============================================
if ($method === 'GET' && empty($action)) {
    if (empty($userId)) {
        jsonResponse(['error' => 'User ID required'], 400);
    }
    
    try {
        $query = 'notifications?select=*&user_id=eq.' . urlencode($userId) . '&order=created_at.desc';
        
        if (isset($_GET['unread']) && $_GET['unread'] === 'true') {
            $query .= '&is_read=eq.false';
        }
        
        $query .= '&limit=' . $limit;
        
        $notifications = supabaseRequest($query);
        
        // Get unread count
        $unreadResult = supabaseRequest('notifications?select=count&user_id=eq.' . urlencode($userId) . '&is_read=eq.false');
        $unreadCount = isset($unreadResult[0]['count']) ? intval($unreadResult[0]['count']) : 0;
        
        jsonResponse([
            'notifications' => $notifications ?: [],
            'unread_count' => $unreadCount,
            'total' => count($notifications ?: [])
        ]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'PGRST205') !== false) {
            jsonResponse([
                'notifications' => [],
                'unread_count' => 0,
                'total' => 0,
                'warning' => 'Notifications table not set up'
            ]);
        } else {
            error_log("Notifications fetch error: " . $e->getMessage());
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    exit;
}

// ============================================
// GET UNREAD COUNT
// ============================================
if ($method === 'GET' && $action === 'count') {
    if (empty($userId)) {
        jsonResponse(['error' => 'User ID required'], 400);
    }
    
    try {
        $result = supabaseRequest('notifications?select=count&user_id=eq.' . urlencode($userId) . '&is_read=eq.false');
        $count = isset($result[0]['count']) ? intval($result[0]['count']) : 0;
        jsonResponse(['unread_count' => $count]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'PGRST205') !== false) {
            jsonResponse(['unread_count' => 0]);
        } else {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    exit;
}

// ============================================
// MARK NOTIFICATION AS READ
// ============================================
if ($method === 'PUT' && $action === 'mark-read') {
    if (empty($id)) {
        jsonResponse(['error' => 'Notification ID required'], 400);
    }
    
    try {
        supabaseRequest('notifications?id=eq.' . $id, 'PATCH', ['is_read' => true]);
        jsonResponse(['success' => true, 'message' => 'Notification marked as read']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// MARK ALL NOTIFICATIONS AS READ
// ============================================
if ($method === 'PUT' && $action === 'mark-all-read') {
    if (empty($userId)) {
        jsonResponse(['error' => 'User ID required'], 400);
    }
    
    try {
        supabaseRequest('notifications?user_id=eq.' . urlencode($userId) . '&is_read=eq.false', 'PATCH', ['is_read' => true]);
        jsonResponse(['success' => true, 'message' => 'All notifications marked as read']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// DELETE NOTIFICATION
// ============================================
if ($method === 'DELETE') {
    if (empty($id)) {
        jsonResponse(['error' => 'Notification ID required'], 400);
    }
    
    try {
        supabaseRequest('notifications?id=eq.' . $id, 'DELETE');
        jsonResponse(['success' => true, 'message' => 'Notification deleted']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// CREATE NOTIFICATION (Manual)
// ============================================
if ($method === 'POST') {
    $input = getInput();
    
    if (empty($input['user_id']) || empty($input['title']) || empty($input['message'])) {
        jsonResponse(['error' => 'User ID, title, and message are required'], 400);
    }
    
    try {
        $data = [
            'user_id' => $input['user_id'],
            'title' => $input['title'],
            'message' => $input['message'],
            'type' => $input['type'] ?? 'system',
            'icon' => $input['icon'] ?? '📢',
            'is_read' => false
        ];
        
        if (!empty($input['action_url'])) {
            $data['action_url'] = $input['action_url'];
        }
        if (!empty($input['action_label'])) {
            $data['action_label'] = $input['action_label'];
        }
        
        $result = supabaseRequest('notifications', 'POST', $data);
        jsonResponse(['success' => true, 'notification' => $result[0] ?? null], 201);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

jsonResponse(['error' => 'Method not allowed'], 405);