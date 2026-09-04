<?php
// api/trigger_notifications.php - Trigger notifications for testing
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

$userId = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'test';

if (empty($userId)) {
    echo json_encode(['error' => 'User ID required']);
    exit;
}

// Verify user exists
try {
    $userCheck = supabaseRequest('users?select=id&id=eq.' . urlencode($userId));
    if (empty($userCheck)) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error verifying user: ' . $e->getMessage()]);
    exit;
}

$messages = [
    'test' => [
        'title' => '🔔 Test Notification', 
        'message' => 'This is a test notification from the system. Your notifications are working!', 
        'icon' => '🔔',
        'type' => 'system'
    ],
    'fine' => [
        'title' => '💰 New Fine Added', 
        'message' => 'A fine of ₱50.00 has been added to your account for late return of "The Great Gatsby".', 
        'icon' => '💰',
        'type' => 'fine',
        'action_url' => '/library-system/student/student_dashboard.php?section=fines',
        'action_label' => 'View Fines'
    ],
    'borrow' => [
        'title' => '📖 Book Borrowed', 
        'message' => 'You have successfully borrowed "The Great Gatsby". Due date: ' . date('Y-m-d', strtotime('+14 days')), 
        'icon' => '📖',
        'type' => 'borrow',
        'action_url' => '/library-system/student/student_dashboard.php?section=borrowings',
        'action_label' => 'View Borrowings'
    ],
    'overdue' => [
        'title' => '⚠️ Overdue Book', 
        'message' => 'Your book "1984" is overdue. Please return it immediately to avoid additional fines.', 
        'icon' => '⚠️',
        'type' => 'overdue',
        'action_url' => '/library-system/student/student_dashboard.php?section=borrowings',
        'action_label' => 'View Borrowings'
    ],
    'available' => [
        'title' => '📚 Book Available!', 
        'message' => 'The book "To Kill a Mockingbird" you reserved is now available for borrowing.', 
        'icon' => '📚',
        'type' => 'available',
        'action_url' => '/library-system/student/student_dashboard.php?section=search&q=To%20Kill%20a%20Mockingbird',
        'action_label' => 'Borrow Now'
    ],
    'reservation' => [
        'title' => '◑ Reservation Confirmed', 
        'message' => 'Your reservation for "Pride and Prejudice" has been confirmed. You will be notified when available.', 
        'icon' => '◑',
        'type' => 'reservation',
        'action_url' => '/library-system/student/student_dashboard.php?section=reservations',
        'action_label' => 'View Reservations'
    ],
    'return' => [
        'title' => '📚 Book Returned', 
        'message' => 'You have successfully returned "The Hobbit". Thank you for returning on time!', 
        'icon' => '📚',
        'type' => 'return'
    ],
    'fine_paid' => [
        'title' => '✅ Fine Paid', 
        'message' => 'Your fine of ₱75.00 has been paid. Thank you for settling your account.', 
        'icon' => '✅',
        'type' => 'fine_paid'
    ]
];

$msg = $messages[$type] ?? $messages['test'];

try {
    $data = [
        'user_id' => $userId,
        'title' => $msg['title'],
        'message' => $msg['message'],
        'type' => $msg['type'],
        'icon' => $msg['icon'],
        'is_read' => false
    ];
    
    if (!empty($msg['action_url'])) {
        $data['action_url'] = $msg['action_url'];
    }
    if (!empty($msg['action_label'])) {
        $data['action_label'] = $msg['action_label'];
    }
    
    $result = supabaseRequest('notifications', 'POST', $data);
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification created successfully',
        'type' => $type,
        'notification' => $result[0] ?? null
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}