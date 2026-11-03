<?php
// student_dashboard.php
session_start();
date_default_timezone_set('Asia/Manila');
$studentWorkspaceRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isAdminPreview = ($studentWorkspaceRole === 'admin');

if (!isset($_SESSION['user_id']) || !in_array($studentWorkspaceRole, ['student', 'admin'], true)) {
    header('Location: ../homepage.php');
    exit;
}

define('SUPABASE_URL', 'https://olzkpwzebcnmbqhbcyyz.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im9semtwd3plYmNubWJxaGJjeXl6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQwMjYxNzcsImV4cCI6MjA5OTYwMjE3N30.GNk7gwaWfi3O-dncbixlkB7M8q6R-UJUe2VMsB5cBTQ');

function supabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $headers = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    elseif ($method === 'PATCH') { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH'); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    elseif ($method === 'DELETE') { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE'); }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode >= 400) throw new Exception("API Error: " . $response);
    return json_decode($response, true);
}

require_once dirname(__DIR__) . '/loan_policy_notifications.php';

$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$userId = $_SESSION['user_id'];
try { libraryGenerateDueNotices($userId, 'student_dashboard.php'); } catch (Throwable $e) { error_log($e->getMessage()); }

$books = $borrowings = $fines = $reservations = $notifications = $unreadNotifications = $studentData = [];
$accountStatus = 'Good Standing';
$hasOverdue = $hasUnpaidFines = $isRestricted = false;
$totalPendingFines = 0;
$pendingFines = $studentRequests = $pendingRequests = $studentSubjects = $userSearchHistory = [];

// Handle notification actions
if ($section === 'notifications' || $action === 'mark_read' || $action === 'mark_all_read') {
    if ($action === 'mark_read' && isset($_GET['id'])) {
        try {
            supabaseRequest('notifications?id=eq.' . rawurlencode($_GET['id']), 'PATCH', ['is_read' => true]);
        } catch (Exception $e) { error_log($e->getMessage()); }
        header('Location: student_dashboard.php?section=' . ($_GET['return'] ?? 'notifications'));
        exit;
    }
    if ($action === 'mark_all_read') {
        try {
            $unread = supabaseRequest('notifications?select=id&user_id=eq.' . $userId . '&is_read=eq.false');
            foreach ($unread as $n) {
                supabaseRequest('notifications?id=eq.' . $n['id'], 'PATCH', ['is_read' => true]);
            }
        } catch (Exception $e) { error_log($e->getMessage()); }
        header('Location: student_dashboard.php?section=notifications&msg=All notifications marked as read.');
        exit;
    }
}

try {
    $borrowings = supabaseRequest('borrowings?select=*,books(title,author,id,cover_image)&user_id=eq.' . $userId . '&order=borrow_date.desc');
    $fines = supabaseRequest('fines?select=*&user_id=eq.' . $userId);
    $reservations = supabaseRequest('reservations?select=*,books(title,author,id,cover_image,available)&user_id=eq.' . $userId . '&order=reservation_date.desc');
    try {
        $notifications = supabaseRequest('notifications?select=*&user_id=eq.' . $userId . '&order=created_at.desc&limit=50');
        $unreadNotifications = array_filter($notifications, function($n) { return !($n['is_read'] ?? false); });
    } catch (Exception $e) { $notifications = []; $unreadNotifications = []; }
    
    $student = supabaseRequest('students?select=*&user_id=eq.' . $userId);
    $studentData = !empty($student) ? $student[0] : [];

    try {
        $studentRequests = supabaseRequest('book_requests?select=*,books(title,author)&user_id=eq.' . $userId . '&order=created_at.desc');
        $pendingRequests = array_filter($studentRequests, function($r) { return ($r['status'] ?? '') === 'Pending'; });
    } catch (Exception $e) { $studentRequests = []; $pendingRequests = []; }
    try { $studentSubjects = supabaseRequest('student_subjects?select=subject_name&user_id=eq.' . $userId); } catch (Exception $e) { $studentSubjects = []; }
    try { $userSearchHistory = supabaseRequest('user_search_history?select=query&user_id=eq.' . $userId . '&order=created_at.desc&limit=10'); } catch (Exception $e) { $userSearchHistory = []; }

    $hasOverdue = !empty(array_filter($borrowings, function($b) { return ($b['status'] ?? '') === 'Overdue'; }));
    $pendingFines = array_filter($fines, function($f) { return ($f['status'] ?? '') !== 'Paid' && ($f['status'] ?? '') !== 'Waived'; });
    $hasUnpaidFines = !empty($pendingFines);
    $totalPendingFines = array_sum(array_column($pendingFines, 'amount'));

    if ($hasOverdue || $hasUnpaidFines) { $accountStatus = 'Restricted'; $isRestricted = true; }
    elseif (!empty(array_filter($borrowings, function($b) { return ($b['status'] ?? '') !== 'Returned'; }))) { $accountStatus = 'Active'; $isRestricted = false; }
    else { $accountStatus = 'Good Standing'; $isRestricted = false; }
} catch (Exception $e) { $message = 'Error loading data: ' . $e->getMessage(); }

// Profile Updates
if ($section === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_profile_action'])) {
    $profileAction = $_POST['student_profile_action'];
    
    if ($profileAction === 'update_photo') {
        $remove = !empty($_POST['remove_photo']);
        $avatarUrl = '';
        if (!$remove && !empty($_FILES['avatar']['tmp_name'])) {
            $file = $_FILES['avatar'];
            if ($file['size'] > 2*1024*1024) { header('Location: student_dashboard.php?section=profile&msg=' . urlencode('Photo must be under 2MB.')); exit; }
            $mime = mime_content_type($file['tmp_name']);
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($allowed[$mime])) { header('Location: student_dashboard.php?section=profile&msg=' . urlencode('Only JPG, PNG, or WebP images are allowed.')); exit; }
            $avatarUrl = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));
        }
        try {
            supabaseRequest('users?id=eq.' . rawurlencode($userId), 'PATCH', ['avatar_url' => $avatarUrl]);
            if ($avatarUrl === '') { unset($_SESSION['avatar_url']); } else { $_SESSION['avatar_url'] = $avatarUrl; }
            header('Location: student_dashboard.php?section=profile&msg=' . urlencode($remove ? 'Profile photo removed.' : 'Profile photo updated successfully.')); exit;
        } catch (Exception $e) { 
            error_log("Avatar update failed: " . $e->getMessage());
            header('Location: student_dashboard.php?section=profile&msg=' . urlencode('Database Error: Could not save photo.')); exit; 
        }
    }
    if ($profileAction === 'update_info') {
        $newName = trim($_POST['full_name'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $yearLevel = trim($_POST['year_level'] ?? '');
        $reqSection = trim($_POST['section_name'] ?? '');
        
        if ($newName === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) { header('Location: student_dashboard.php?section=profile&msg=' . urlencode('Enter a valid name and email.')); exit; }
        try {
            supabaseRequest('users?id=eq.' . rawurlencode($userId), 'PATCH', ['full_name' => $newName, 'email' => $newEmail, 'phone' => $phone, 'address' => $address]);
            $_SESSION['full_name'] = $newName;
            $_SESSION['email'] = $newEmail;
            if (!empty($studentData['id'])) {
                supabaseRequest('students?id=eq.' . rawurlencode($studentData['id']), 'PATCH', ['year_level' => $yearLevel, 'section' => $reqSection]);
                $studentData['year_level'] = $yearLevel;
                $studentData['section'] = $reqSection;
            }
            header('Location: student_dashboard.php?section=profile&msg=' . urlencode('Profile details updated successfully.')); exit;
        } catch (Exception $e) { header('Location: student_dashboard.php?section=profile&msg=' . urlencode('Could not save profile.')); exit; }
    }
    if ($profileAction === 'update_security') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        if ($newPassword !== '') {
            if (strlen($newPassword) < 6) { header('Location: student_dashboard.php?section=profile&msg=' . urlencode('New password must be at least 6 characters.')); exit; }
            if ($newPassword !== $confirmPassword) { header('Location: student_dashboard.php?section=profile&msg=' . urlencode('New passwords do not match.')); exit; }
            $userRows = supabaseRequest('users?select=password&id=eq.' . rawurlencode($userId));
            if (empty($userRows) || $userRows[0]['password'] !== $currentPassword) { header('Location: student_dashboard.php?section=profile&msg=' . urlencode('Current password is incorrect.')); exit; }
            supabaseRequest('users?id=eq.' . rawurlencode($userId), 'PATCH', ['password' => $newPassword]);
        }
        header('Location: student_dashboard.php?section=profile&msg=' . urlencode('Security settings updated successfully.')); exit;
    }
}

// Request Handling
if ($section === 'request_form' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId = $_POST['book_id'] ?? '';
    $requestType = $_POST['request_type'] ?? '';
    $fullName = $_SESSION['full_name'] ?? '';
    $studentId = $studentData['student_id'] ?? $_SESSION['user_id_display'] ?? '';
    $yearLevel = trim((string)($_POST['year_level'] ?? ($studentData['year_level'] ?? '')));
    $reqSection = trim((string)($_POST['section'] ?? ($studentData['section'] ?? '')));
    $purpose = trim($_POST['purpose'] ?? '');
    $errors = [];
    if (!$bookId) $errors[] = 'Book ID is required.';
    if (!$requestType || !in_array($requestType, ['borrow', 'reserve'])) $errors[] = 'Valid request type is required.';
    if (!$fullName) $errors[] = 'Full name is required.';
    if (!$studentId) $errors[] = 'Student ID is required.';
    if (!$yearLevel) $errors[] = 'Year level is required.';
    if (!$reqSection) $errors[] = 'Section is required.';

    if (empty($errors)) {
        try {
            $existing = supabaseRequest('book_requests?select=id&user_id=eq.' . $userId . '&book_id=eq.' . $bookId . '&status=eq.Pending');
            if (!empty($existing)) { header('Location: student_dashboard.php?section=search&msg=You already have a pending request for this book.'); exit; }
            supabaseRequest('book_requests', 'POST', ['user_id' => $userId, 'book_id' => $bookId, 'request_type' => $requestType, 'student_id' => $studentId, 'full_name' => $fullName, 'year_level' => $yearLevel, 'section' => $reqSection, 'purpose' => $purpose, 'status' => 'Pending']);
            try {
                $bookData = supabaseRequest('books?select=title&id=eq.' . $bookId);
                $bookTitle = !empty($bookData) ? $bookData[0]['title'] : 'Book';
                $admins = supabaseRequest('users?select=id&role=eq.admin');
                foreach ($admins as $admin) {
                    supabaseRequest('notifications', 'POST', ['user_id' => $admin['id'], 'title' => 'New Book Request', 'message' => $fullName . ' requested ' . $requestType . ' "' . $bookTitle . '"', 'type' => 'request', 'icon' => '📋', 'is_read' => false, 'action_url' => 'admin_dashboard.php?section=requests', 'action_label' => 'View Request']);
                }
            } catch (Exception $e) {}
            header('Location: student_dashboard.php?section=requests&msg=Your request has been submitted.'); exit;
        } catch (Exception $e) { header('Location: student_dashboard.php?section=request_form&book_id=' . $bookId . '&type=' . $requestType . '&msg=Error submitting request.'); exit; }
    } else { header('Location: student_dashboard.php?section=request_form&book_id=' . $bookId . '&type=' . $requestType . '&msg=' . urlencode(implode(' ', $errors))); exit; }
}

// Borrow/Reserve Handling
if (($section === 'search' || $section === 'requests') && $action === 'borrow' && isset($_GET['book_id'])) {
    $bookId = $_GET['book_id'];
    if ($isRestricted) { header('Location: student_dashboard.php?section='.$section.'&msg=Your account is restricted.'); exit; }
    try {
        $approvedRequests = supabaseRequest('book_requests?select=id&user_id=eq.' . $userId . '&book_id=eq.' . $bookId . '&status=eq.Approved&request_type=eq.borrow');
        if (empty($approvedRequests)) { header('Location: student_dashboard.php?section=request_form&book_id=' . $bookId . '&type=borrow&msg=Please submit a request form first.'); exit; }
        $bookCheck = supabaseRequest('books?select=available,id,title&id=eq.' . $bookId);
        if (empty($bookCheck) || ($bookCheck[0]['available'] ?? 0) <= 0) { header('Location: student_dashboard.php?section='.$section.'&msg=Book not available'); exit; }
        $existing = supabaseRequest('borrowings?select=id&user_id=eq.' . $userId . '&book_id=eq.' . $bookId . '&status=neq.Returned');
        if (!empty($existing)) { header('Location: student_dashboard.php?section='.$section.'&msg=You already borrowed this book'); exit; }
        $borrowData = ['book_id' => $bookId, 'user_id' => $userId, 'borrow_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+' . libraryLoanPolicy($_SESSION['role'] ?? 'student')['days'] . ' days')), 'status' => 'Borrowed'];
        supabaseRequest('borrowings', 'POST', $borrowData);
        libraryCreateBorrowNotice($userId, $bookCheck[0]['title'] ?? 'the book', libraryLoanPolicy($_SESSION['role'] ?? 'student')['days'], $borrowData['due_date']);
        supabaseRequest('books?id=eq.' . $bookId, 'PATCH', ['available' => ($bookCheck[0]['available'] - 1)]);
        if (!empty($approvedRequests)) { supabaseRequest('book_requests?id=eq.' . $approvedRequests[0]['id'], 'PATCH', ['status' => 'Fulfilled']); }
        header('Location: student_dashboard.php?section=borrowings&msg=Book borrowed successfully!');
    } catch (Exception $e) { header('Location: student_dashboard.php?section='.$section.'&msg=Error borrowing book.'); }
    exit;
}

if ($section === 'search' && $action === 'reserve' && isset($_GET['book_id'])) {
    $bookId = $_GET['book_id'];
    if ($isRestricted) { header('Location: student_dashboard.php?section=search&msg=Your account is restricted.'); exit; }
    try {
        $approvedRequests = supabaseRequest('book_requests?select=id&user_id=eq.' . $userId . '&book_id=eq.' . $bookId . '&status=eq.Approved&request_type=eq.reserve');
        if (empty($approvedRequests)) { header('Location: student_dashboard.php?section=request_form&book_id=' . $bookId . '&type=reserve&msg=Please submit a request form first.'); exit; }
        $bookCheck = supabaseRequest('books?select=available,id,title&id=eq.' . $bookId);
        $existingReservation = supabaseRequest('reservations?select=id&user_id=eq.' . $userId . '&book_id=eq.' . $bookId . '&status=eq.Pending');
        if (!empty($existingReservation)) { header('Location: student_dashboard.php?section=search&msg=You already have a pending reservation.'); exit; }
        supabaseRequest('reservations', 'POST', ['book_id' => $bookId, 'user_id' => $userId, 'reservation_date' => date('Y-m-d'), 'expiry_date' => date('Y-m-d', strtotime('+3 days')), 'status' => 'Pending', 'notes' => 'Reserved by student']);
        if (!empty($approvedRequests)) { supabaseRequest('book_requests?id=eq.' . $approvedRequests[0]['id'], 'PATCH', ['status' => 'Fulfilled']); }
        header('Location: student_dashboard.php?section=reservations&msg=Book reserved successfully!');
    } catch (Exception $e) { header('Location: student_dashboard.php?section=search&msg=Error reserving book.'); }
    exit;
}

$requestBookId = isset($_GET['book_id']) ? $_GET['book_id'] : '';
$requestType = isset($_GET['type']) ? $_GET['type'] : 'borrow';
$requestMessage = isset($_GET['msg']) ? $_GET['msg'] : '';
$requestBookData = [];
if ($section === 'request_form' && $requestBookId) {
    try { $bookData = supabaseRequest('books?select=*,categories(name)&id=eq.' . $requestBookId); if (!empty($bookData)) { $requestBookData = $bookData[0]; } } catch (Exception $e) {}
}

$allBooks = [];
$searchQuery = isset($_GET['q']) ? $_GET['q'] : '';
$message = isset($_GET['msg']) ? $_GET['msg'] : '';
try { $allBooks = supabaseRequest('books?select=*,categories(name)'); $books = $allBooks; } catch (Exception $e) { $message = 'Error loading books: ' . $e->getMessage(); }

$activeBorrowings = array_filter($borrowings, function($b) { return ($b['status'] ?? '') !== 'Returned'; });
$overdueBorrowings = array_filter($borrowings, function($b) { return ($b['status'] ?? '') === 'Overdue'; });
$totalFines = array_sum(array_column($fines, 'amount'));

function getPlaceholderColor($id) {
    $colors = ['#2a2a2a', '#4a4a4a', '#6a6a6a', '#8a8a8a', '#aaaaaa', '#cacaca', '#eaeaea', '#fafafa'];
    $hash = crc32($id); if ($hash < 0) $hash = -$hash;
    return $colors[$hash % count($colors)];
}
function hasValidCoverImage($coverImage) {
    if (empty($coverImage)) return false;
    if (strpos($coverImage, 'data:image') === 0) return strlen($coverImage) > 100;
    if (filter_var($coverImage, FILTER_VALIDATE_URL)) return true;
    return false;
}

$basePath = dirname(dirname(dirname(dirname($_SERVER['PHP_SELF']))));
if ($basePath === '/' || $basePath === '\\' || $basePath === '.') $basePath = '';

// Load user info for profile
$userRecord = [];
try { 
    $u = supabaseRequest('users?select=*&id=eq.' . $userId); 
    if ($u) { 
        $userRecord = $u[0]; 
        if (!empty($userRecord['avatar_url'])) $_SESSION['avatar_url'] = $userRecord['avatar_url'];
        $_SESSION['full_name'] = $userRecord['full_name'] ?? $_SESSION['full_name'];
        $_SESSION['email'] = $userRecord['email'] ?? $_SESSION['email'];
    }
} catch (Exception $e) {}

$activeTab = $_GET['tab'] ?? 'overview';

// Notification type helpers
function getNotificationIcon($type, $icon) {
    if (!empty($icon)) return $icon;
    $icons = [
        'request' => '📋',
        'borrow' => '📚',
        'return' => '✅',
        'fine' => '💰',
        'reservation' => '📅',
        'due' => '⏰',
        'overdue' => '⚠️',
        'system' => '♧',
        'success' => '✅',
        'warning' => '⚠️',
        'info' => 'ℹ️'
    ];
    return $icons[strtolower($type ?? 'system')] ?? '♧';
}
function getNotificationColorClass($type) {
    $classes = [
        'request' => 'notif-request',
        'borrow' => 'notif-borrow',
        'return' => 'notif-success',
        'fine' => 'notif-fine',
        'reservation' => 'notif-reservation',
        'due' => 'notif-warning',
        'overdue' => 'notif-danger',
        'system' => 'notif-system',
        'success' => 'notif-success',
        'warning' => 'notif-warning',
        'info' => 'notif-info'
    ];
    return $classes[strtolower($type ?? 'system')] ?? 'notif-system';
}
function formatNotificationTime($datetime) {
    if (empty($datetime)) return 'Just now';
    $ts = strtotime($datetime);
    if ($ts === false) return $datetime;
    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Student Dashboard - St. Agnes Academy</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="96x96" href="/img/favicon-96x96.png">
    <meta name="theme-color" content="#e51d66">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { -webkit-text-size-adjust: 100%; }
        html, body { overflow-x: hidden; width: 100%; max-width: 100vw; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #f5f3f0; color: #1a1a1a; transition: background-color .35s ease, color .35s ease; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f0edea; }
        ::-webkit-scrollbar-thumb { background: #d4c9c0; border-radius: 3px; }

        .top-header { position: fixed; top: 0; left: 240px; right: 0; height: 56px; background: #010107; border-bottom: 1px solid #2a2a2a; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 1100; transition: left 0.25s ease; box-sizing:border-box; }
        .top-header.collapsed { left: 70px; }
        .header-left-group { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .hamburger-btn { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; flex-shrink: 0; }
        .hamburger-btn:hover { background: rgba(229, 29, 102, 0.18); border-color: rgba(229, 29, 102, 0.3); }
        .hamburger-lines { display: flex; flex-direction: column; gap: 4px; width: 18px; }
        .hamburger-lines span { display: block; height: 2px; width: 100%; background: #e8e0d8; border-radius: 2px; transition: 0.3s; }
        .header-nav-symbols { display: flex; align-items: center; gap: 8px; flex-shrink: 1; min-width: 0; margin-left:auto; justify-content:flex-end; }
        .header-nav-symbols .header-action { position:relative; display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:36px; padding:0 12px; border:1px solid rgba(255,255,255,.13); border-radius:10px; background:rgba(255,255,255,.055); color:#f8eaf4 !important; text-decoration:none; font-size:12px; font-weight:600; white-space:nowrap; cursor:pointer; font-family:inherit; transition:background .2s,border-color .2s,transform .2s; }
        .header-nav-symbols .header-action:hover { background:rgba(212,22,136,.22); border-color:rgba(255,1,153,.45); transform:translateY(-1px); }
        .header-action .action-icon { font-size: 15px; line-height: 1; }
        .header-action .action-label { font-size: 11px; color: white;} 
        .header-action.logout-action { background:#b40f7d; border-color:#b40f7d; color:#fff !important; font-weight:700; }
        .header-action.logout-action:hover { background:#d41688; }
        .header-nav-symbols .header-badge { position: absolute; top: -4px; right: -4px; background: #ff0199; color: #fff !important; font-size: 9px; font-weight: 800; min-width: 17px; height: 17px; border-radius: 20px; display: flex; align-items: center; justify-content: center; padding: 0 4px; border: 2px solid #08080a; }
        .theme-action { min-width: 105px; }

        .student-app { display: flex; min-height: 100vh; }
        .student-sidebar { width: 240px; background: #010107; color: #e8e0d8; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; overflow-x: hidden; z-index: 1050; border-right: 1px solid #2a2a2a; transition: width 0.25s ease, transform 0.3s ease; }
        .student-sidebar.collapsed { width: 70px; }
        .student-sidebar.collapsed .sidebar-header h2, .student-sidebar.collapsed .sidebar-header p, .student-sidebar.collapsed .sidebar-header small, .student-sidebar.collapsed .sidebar-header .subtitle, .student-sidebar.collapsed .sidebar-nav a .nav-label { display: none; }
        .student-sidebar.collapsed .sidebar-nav a { justify-content: center; padding: 14px; font-size: 20px; }
        .student-sidebar.collapsed .sidebar-nav a .nav-icon { font-size: 22px; }
        .student-sidebar.collapsed .sidebar-header { padding: 16px 8px; text-align: center; }
        .student-sidebar.collapsed .sidebar-footer { padding: 16px 12px 24px; }
        .student-sidebar.collapsed .logout-btn { padding: 10px 0; font-size: 16px; }
        .student-sidebar.collapsed .logout-btn .logout-text { display: none; }
        .student-sidebar.collapsed .notification-badge { position: absolute; top: 6px; right: 6px; margin: 0; }
        .student-sidebar.collapsed .sidebar-logo-wrapper { justify-content: center; }
        .student-sidebar.collapsed .sidebar-header .sidebar-logo { max-width: 40px; width: 40px; margin: 0 auto; }

        .sidebar-header { padding: 28px 24px 20px; border-bottom: 1px solid #2a2a2a; text-align: left; transition: padding 0.25s ease; }
        .sidebar-logo-wrapper { width: 100%; display: flex; align-items: center; justify-content: flex-start; transition: justify-content 0.25s ease; }
        .sidebar-header .sidebar-logo { max-width: 72px; width: 72px; height: auto; display: block; margin-bottom: 12px; transition: max-width 0.3s ease, width 0.3s ease, margin 0.3s ease; }
        .sidebar-header h2 { margin: 0; font-size: 16px; color: #f0e8e0; font-weight: 600; letter-spacing: 0.5px; }
        .sidebar-header .subtitle { color: #8a7a6e; font-size: 11px; letter-spacing: 1px; margin-top: 2px; font-weight: 300; }
        .sidebar-header p { margin: 12px 0 0; font-size: 13px; color: #d4c9c0; font-weight: 400; }
        .sidebar-header small { opacity: 0.5; font-size: 11px; display: block; color: #8a7a6e; }

        .notification-badge { background: #e51d66; color: #ffffff; font-size: 10px; font-weight: 700; min-width: 20px; height: 20px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; padding: 0 6px; margin-left: auto; }

        .sidebar-nav { flex: 1; padding: 16px 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 12px 24px; color: #8a7a6e; text-decoration: none; font-size: 14px; font-weight: 400; transition: all 0.2s ease; border-left: 3px solid transparent; position: relative; }
        .sidebar-nav a:hover { color: #f0e8e0; background: rgba(255,255,255,0.04); border-left-color: #d4a0a0; }
        .sidebar-nav a.active { color: #f0e8e0; background: rgba(180, 15, 125, 0.18); border-left-color: #d4a0a0; }
        .sidebar-nav a .nav-icon { font-size: 18px; width: 24px; text-align: center; opacity: 0.7; }
        .sidebar-nav a.active .nav-icon { opacity: 1; }
        .sidebar-nav a .nav-label { flex: 1; }

        .sidebar-footer { padding: 16px 24px 24px; border-top: 1px solid #2a2a2a; }
        .logout-btn { width: 100%; padding: 10px 16px; background: rgba(180, 15, 125, 0.15); color: #d460b8; border: 1px solid rgba(180, 15, 125, 0.2); border-radius: 8px; cursor: pointer; font-size: 14px; transition: all 0.2s ease; text-decoration: none; text-align: center; display: block; font-weight: 500; }
        .logout-btn:hover { background: rgba(212, 160, 160, 0.2); border-color: rgba(212, 160, 160, 0.4); }

        .student-content { margin-left: 240px; flex: 1; padding: 80px 40px 32px; background: #f5f3f0; min-height: 100vh; transition: margin-left 0.25s ease; min-width: 0; }
        .student-content.collapsed { margin-left: 70px; }

        .mobile-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1040; }
        .mobile-overlay.active { display: block; }

        .dashboard-content { padding: 0; min-width: 0; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; padding: 24px 32px; background: linear-gradient(135deg, #08080a 0%, #0a090a 30%, #d41688b0 60%, #ff0199a8 80%, #ff0199c9 100%) !important; border-radius: 16px; position: relative; overflow: hidden; min-height:118px; color:#fff !important; }
        .dashboard-header::after { content: ''; position: absolute; width: 260px; height: 260px; border-radius: 50%; right: 8%; top: -150px; background: rgba(255,255,255,.1); pointer-events: none; }
        .dashboard-header .header-left { position: relative; z-index: 1; min-width: 0; }
        .dashboard-header h1 { font-size: 22px; color: #fff !important; margin: 0 0 7px; font-weight: 600; letter-spacing: -0.3px; line-height: 1.25; word-break: break-word; }
        .header-date { color: #f6d9ed !important; font-size: 12px; margin: 0; word-break: break-word; }
        .header-time { text-align: right; background: rgba(255,255,255,.08) !important; padding: 10px 16px; border-radius: 10px; border: 1px solid rgba(255,255,255,.17) !important; position: relative; z-index: 1; flex-shrink: 0; min-width:138px; }
        .header-time .time { font-size: 18px; font-weight: 700; color: #fff !important; display: block; margin-bottom:3px; }
        .header-time .date { font-size: 12px; color: #fff !important; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 28px; }
        .stat-card { background: #ffffff; padding: 16px 18px; border-radius: 12px; border: 1px solid #e8e0d8; text-align: center; transition: all 0.2s ease; min-width: 0; overflow: hidden; }
        .stat-card:hover { border-color: #d4c9c0; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .stat-number { font-size: 24px; font-weight: 700; color: #6b6565; letter-spacing: -0.5px; word-break: break-word; }
        .stat-label { font-size: 12px; color: #6a5a4e; margin-top: 2px; font-weight: 400; word-break: break-word; }

        .status-card { background: #ffffff; padding: 16px 20px; border-radius: 12px; border: 1px solid #e8e0d8; display: flex; align-items: center; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
        .status-dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }
        .status-dot.good-standing { background: #34a853; }
        .status-dot.active { background: #fbbc04; }
        .status-dot.restricted { background: #ea4335; animation: pulse-dot 1.5s infinite; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.8); } }
        .status-info { flex: 1; min-width: 0; }
        .status-info .status-title { font-weight: 600; color: #1a1a1a; font-size: 16px; word-break: break-word; }
        .status-info .status-desc { font-size: 13px; color: #6a5a4e; word-break: break-word; }
        .status-badge-large { padding: 4px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; flex-shrink: 0; }
        .status-badge-large.good-standing { background: #dde8e0; color: #1a4a3a; }
        .status-badge-large.active { background: #f0edd8; color: #6a5a3a; }
        .status-badge-large.restricted { background: #f0ddd8; color: #8a3a2a; }

        .quick-actions { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 28px; }
        .quick-action-card { background: #ffffff; border-radius: 12px; padding: 18px 16px; text-align: center; cursor: pointer; transition: all 0.2s ease; border: 1px solid #e8e0d8; text-decoration: none; color: inherit; display: block; position: relative; min-width: 0; }
        .quick-action-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.06); border-color: #d4c9c0; }
        .action-icon { font-size: 22px; display: block; margin-bottom: 6px; opacity: 0.6; }
        .action-label { font-size: 13px; color: #000000; font-weight: 500; word-break: break-word; }
        .action-badge { position: absolute; top: 4px; right: 4px; background: #e51d66; color: #fff; font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; border-radius: 9px; display: flex; align-items: center; justify-content: center; padding: 0 5px; }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .section-header h1 { font-size: 22px; color: #1a1a1a; margin: 0; font-weight: 600; letter-spacing: -0.5px; word-break: break-word; }

        /* ============================================================
           ENHANCED NOTIFICATIONS
           ============================================================ */
        .notifications-page { max-width: 900px; margin: 0 auto; }
        .notifications-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; padding: 16px 20px; background: #fff; border-radius: 14px; border: 1px solid #e8e0d8; }
        .notifications-filter { display: flex; gap: 6px; flex-wrap: wrap; }
        .notif-filter-btn { border: 1px solid #e8e0d8; background: #faf8f6; color: #6a5a4e; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; font-family: inherit; }
        .notif-filter-btn:hover { background: #f0edea; }
        .notif-filter-btn.active { background: #1a1a1a; color: #fff; border-color: #1a1a1a; }
        .notif-filter-btn .filter-count { background: rgba(255,255,255,0.2); padding: 1px 7px; border-radius: 10px; font-size: 11px; margin-left: 4px; }
        .notif-filter-btn.active .filter-count { background: rgba(255,255,255,0.25); }
        .notif-mark-all { border: 0; background: #b40f7d; color: #fff; padding: 9px 16px; border-radius: 9px; font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.2s; font-family: inherit; display: inline-flex; align-items: center; gap: 6px; }
        .notif-mark-all:hover { background: #8a0a5f; }
        .notif-mark-all:disabled { background: #d4c9c0; cursor: not-allowed; opacity: 0.7; }

        .notification-list { display: flex; flex-direction: column; gap: 10px; }
        .notification-card { background: #fff; border: 1px solid #e8e0d8; border-radius: 14px; padding: 18px 20px; display: flex; gap: 16px; align-items: flex-start; transition: all 0.2s ease; position: relative; overflow: hidden; }
        .notification-card:hover { border-color: #d4c9c0; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .notification-card.unread { background: linear-gradient(135deg, #fff 0%, #fdf5fb 100%); border-left: 4px solid #b40f7d; }
        .notification-card.unread::after { content: ''; position: absolute; top: 16px; right: 16px; width: 9px; height: 9px; border-radius: 50%; background: #ff0199; box-shadow: 0 0 0 3px rgba(255,1,153,0.15); }
        .notif-icon-wrap { width: 46px; height: 46px; flex: 0 0 46px; border-radius: 12px; display: grid; place-items: center; font-size: 22px; background: #f0edea; }
        .notif-icon-wrap.notif-request { background: #e8f0ee; }
        .notif-icon-wrap.notif-borrow { background: #e8e4f0; }
        .notif-icon-wrap.notif-success { background: #dde8e0; }
        .notif-icon-wrap.notif-fine { background: #f0edd8; }
        .notif-icon-wrap.notif-reservation { background: #e8f0f0; }
        .notif-icon-wrap.notif-warning { background: #f0e8d8; }
        .notif-icon-wrap.notif-danger { background: #f0ddd8; }
        .notif-icon-wrap.notif-info { background: #e8e4f0; }
        .notif-icon-wrap.notif-system { background: #f0edea; }
        .notif-body { flex: 1; min-width: 0; }
        .notif-title { font-weight: 600; color: #1a1a1a; font-size: 15px; margin-bottom: 4px; line-height: 1.35; word-break: break-word; padding-right: 20px; }
        .notif-message { color: #6a5a4e; font-size: 13.5px; line-height: 1.5; margin-bottom: 8px; word-break: break-word; }
        .notif-meta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 12px; color: #9a8a7e; }
        .notif-meta .notif-dot { width: 3px; height: 3px; border-radius: 50%; background: #d4c9c0; }
        .notif-actions-row { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .notif-action-btn { padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s; border: 1px solid transparent; cursor: pointer; font-family: inherit; }
        .notif-action-btn.primary { background: #b40f7d; color: #fff; border-color: #b40f7d; }
        .notif-action-btn.primary:hover { background: #8a0a5f; }
        .notif-action-btn.secondary { background: #faf8f6; color: #6a5a4e; border-color: #e8e0d8; }
        .notif-action-btn.secondary:hover { background: #f0edea; }
        .notif-action-btn.ghost { background: transparent; color: #b40f7d; }
        .notif-action-btn.ghost:hover { background: #fdf2f8; }

        .notif-empty { text-align: center; padding: 70px 30px; background: #fff; border-radius: 16px; border: 1px dashed #e8e0d8; }
        .notif-empty .empty-icon { font-size: 56px; display: block; margin-bottom: 16px; opacity: 0.35; }
        .notif-empty h3 { color: #1a1a1a; margin-bottom: 8px; font-size: 18px; }
        .notif-empty p { color: #9a8a7e; font-size: 14px; }

        /* Enhanced Notification Modal */
        .profile-modal-backdrop { display:none; position:fixed; inset:0; z-index:5000; background:rgba(8,3,10,.68); backdrop-filter:blur(6px); align-items:center; justify-content:center; padding:18px; }
        .profile-modal-backdrop.open { display:flex; animation:modalFade .2s ease-out; }
        @keyframes modalFade{from{opacity:0}to{opacity:1}}
        .notification-modal-panel { width: min(560px, 100%); max-height: 86vh; display: flex; flex-direction: column; background: #fff; border-radius: 20px; box-shadow: 0 30px 90px rgba(0,0,0,.35); overflow: hidden; animation: modalSlideUp .28s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes modalSlideUp { from { opacity: 0; transform: translateY(24px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .notif-modal-header { padding: 22px 26px 18px; background: linear-gradient(135deg, #08080a 0%, #291126 48%, #d41688 100%); color: #fff; position: relative; overflow: hidden; flex-shrink: 0; }
        .notif-modal-header::after { content: ''; position: absolute; width: 180px; height: 180px; border-radius: 50%; right: -50px; top: -100px; background: rgba(255,255,255,.1); pointer-events: none; }
        .notif-modal-header > * { position: relative; z-index: 1; }
        .notif-modal-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .notif-modal-title { font-size: 19px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 10px; }
        .notif-modal-subtitle { color: rgba(255,255,255,.75); font-size: 12.5px; margin: 4px 0 0; }
        .notif-modal-close { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); color: #fff; width: 32px; height: 32px; border-radius: 9px; cursor: pointer; font-size: 18px; display: grid; place-items: center; flex-shrink: 0; transition: background 0.2s; line-height: 1; }
        .notif-modal-close:hover { background: rgba(255,255,255,.25); }
        .notif-modal-actions-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; flex-wrap: wrap; }
        .notif-modal-mark-all { background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.24); color: #fff; padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; transition: background 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .notif-modal-mark-all:hover { background: rgba(255,255,255,.26); }
        .notif-modal-body { flex: 1; overflow-y: auto; padding: 8px; background: #faf8fa; }
        .notif-modal-item { background: #fff; border: 1px solid #ece2ea; border-radius: 12px; padding: 14px 16px; margin: 6px; display: flex; gap: 12px; align-items: flex-start; transition: all 0.2s; position: relative; }
        .notif-modal-item:hover { border-color: #e0cfe0; box-shadow: 0 3px 12px rgba(0,0,0,.04); }
        .notif-modal-item.unread { border-left: 3px solid #b40f7d; background: #fffaff; }
        .notif-modal-item .notif-icon-wrap { width: 38px; height: 38px; flex: 0 0 38px; font-size: 18px; border-radius: 10px; }
        .notif-modal-item .notif-title { font-size: 13.5px; padding-right: 8px; margin-bottom: 3px; }
        .notif-modal-item .notif-message { font-size: 12.5px; line-height: 1.45; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .notif-modal-item .notif-meta { font-size: 11px; }
        .notif-modal-item .notif-actions-row { margin-top: 7px; }
        .notif-modal-item .notif-action-btn { padding: 5px 11px; font-size: 11px; }
        .notif-modal-footer { padding: 14px 20px; border-top: 1px solid #ece2ea; background: #fff; flex-shrink: 0; text-align: center; }
        .notif-modal-view-all { display: inline-flex; align-items: center; gap: 7px; background: #1a1a1a; color: #fff; padding: 10px 22px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; transition: background 0.2s; }
        .notif-modal-view-all:hover { background: #333; }
        .notif-modal-empty { text-align: center; padding: 50px 24px; color: #9a8a7e; }
        .notif-modal-empty .empty-icon { font-size: 44px; display: block; margin-bottom: 12px; opacity: 0.35; }
        .notif-modal-empty h4 { color: #1a1a1a; margin-bottom: 6px; font-size: 15px; }
        .notif-modal-empty p { font-size: 13px; }

        /* Search bar */
        .search-bar { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .search-bar .search-input-wrapper { flex: 1; position: relative; min-width: 200px; }
        .search-bar .search-input-wrapper input { width: 100%; padding: 12px 16px 12px 44px; border: 2px solid #f0e0ee; border-radius: 10px; font-size: 14px; background: #ffffff; color: #1a1a2e; }
        .search-bar .search-input-wrapper input:focus { border-color: #b40f7d; outline: none; box-shadow: 0 0 0 3px rgba(180, 15, 125, 0.12); }
        .search-bar .search-input-wrapper .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #b8a8b8; font-size: 16px; }
        .search-bar .search-input-wrapper .clear-btn { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #b8a8b8; cursor: pointer; font-size: 18px; display: none; padding: 4px 8px; }
        .search-bar .search-input-wrapper .clear-btn.visible { display: block; }
        .search-info { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .search-type-badge { font-size: 12px; padding: 4px 14px; border-radius: 20px; font-weight: 500; background: #f0e8ee; color: #4a3a4a; }
        .count-badge { color: #8a7a8a; font-size: 14px; white-space: nowrap; font-weight: 400; }
        .count-badge.overdue-warning { color: #8a2a5a; font-weight: 600; }

        .predictive-results { position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #ffffff; border: 1px solid #e8d8e5; border-radius: 12px; max-height: 350px; overflow-y: auto; z-index: 1000; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .predictive-results.visible { display: block; }
        .predictive-item { display: flex; flex-direction: column; gap: 4px; padding: 12px 16px; border-bottom: 1px solid #f5eef5; cursor: pointer; transition: background 0.2s ease; text-align: left; width: 100%; border: none; background: transparent; }
        .predictive-item:last-child { border-bottom: none; }
        .predictive-item:hover { background: #faf5fa; }
        .pred-title { font-weight: 600; color: #1a1a2e; font-size: 14px; }
        .pred-author { color: #6a5a4e; font-size: 12px; }
        .pred-meta { display: flex; gap: 10px; align-items: center; font-size: 11px; color: #8a7a8a; margin-top: 4px; }
        .pred-availability { padding: 2px 8px; border-radius: 10px; background: #e8f0ee; color: #2a4a3a; font-weight: 600; }
        .pred-availability.unavailable { background: #f0e0e8; color: #8a2a5a; }
        .loading-predictions { padding: 20px; text-align: center; color: #8a7a8a; font-size: 13px; }
        .loading-predictions .spinner { width: 20px; height: 20px; border: 3px solid #f0e8ee; border-top-color: #b40f7d; border-radius: 50%; animation: spin 0.8s linear infinite; display: inline-block; vertical-align: middle; margin-right: 8px; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .book-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; }
        .book-card { background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #e8e0d8; transition: all 0.3s ease; display: flex; flex-direction: column; min-width: 0; }
        .book-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.08); border-color: #d4c9c0; }
        .book-card .book-cover-wrapper { height: 200px; background: #f0edea; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; flex-shrink: 0; }
        .book-card .book-cover-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        .book-card .book-cover-wrapper .cover-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%; color: #ffffff; font-size: 48px; font-weight: bold; }
        .book-card .book-cover-wrapper .cover-placeholder .initial { font-size: 64px; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .book-card .book-cover-wrapper .availability-badge { position: absolute; top: 12px; right: 12px; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; color: #f0e8e0; background: #3a2a2a; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .book-card .book-cover-wrapper .availability-badge.low { background: #8a7a6e; }
        .book-card .book-cover-wrapper .availability-badge.none { background: #8a3a2a; }
        .book-card .book-info { padding: 16px 20px 20px; flex: 1; display: flex; flex-direction: column; }
        .book-card .book-info .book-title { font-size: 16px; font-weight: 600; color: #1a1a1a; margin: 0 0 4px 0; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .book-card .book-info .book-author { font-size: 14px; color: #6a5a4e; margin: 0 0 8px 0; }
        .book-card .book-info .book-category { display: inline-block; background: #6f6767; color: #fff; padding: 2px 12px; border-radius: 12px; font-size: 12px; margin-bottom: 8px; align-self: flex-start; }
        .book-card .book-info .book-meta { display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid #f0edea; margin-top: auto; flex-wrap: wrap; gap: 8px; }
        .book-card .book-info .book-meta .availability { font-size: 14px; color: #6a5a4e; }
        .book-card .book-info .book-meta .availability strong { color: #1a1a1a; }
        .book-card .book-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .book-card .relevance-score { padding: 2px 12px; border-radius: 12px; font-size: 11px; background: #000000; color: #ffffff; align-self: flex-end; margin-top: 4px; }

        .btn-borrow { padding: 8px 20px; background: #1a1a1a; color: #f0e8e0; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.2s ease; text-decoration: none; display: inline-block; }
        .btn-borrow:hover:not(:disabled) { background: #2a2a2a; transform: translateY(-1px); }
        .btn-borrow:disabled { background: #d4c9c0; cursor: not-allowed; opacity: 0.6; color: #8a7a6e; }
        .btn-reserve { padding: 8px 16px; background: #d4a0a0; color: #ffffff; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s ease; text-decoration: none; display: inline-block; }
        .btn-reserve:hover:not(:disabled) { background: #c48a8a; transform: translateY(-1px); }
        .btn-request { padding: 8px 16px; background: #b40f7d; color: #ffffff; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s ease; text-decoration: none; display: inline-block; }
        .btn-request:hover:not(:disabled) { background: #8a0a5f; transform: translateY(-1px); }

        .request-form-container { background: #ffffff; border-radius: 16px; padding: 32px 36px; max-width: 600px; margin: 0 auto; border: 1px solid #e8e0d8; }
        .request-form-container .form-header { text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #f0edea; }
        .request-form-container .form-header .book-preview { background: #faf8f6; padding: 12px 16px; border-radius: 10px; margin-top: 12px; text-align: left; }
        .request-form-container .form-header .book-preview .preview-title { font-weight: 600; color: #1a1a1a; font-size: 16px; }
        .request-form-container .form-header .book-preview .preview-author { color: #6a5a4e; font-size: 14px; }
        .request-form-container .form-group { margin-bottom: 16px; }
        .request-form-container .form-group label { display: block; font-weight: 600; color: #4a3a2e; font-size: 14px; margin-bottom: 4px; }
        .request-form-container .form-group label .required { color: #8a3a2a; }
        .request-form-container .form-group input, .request-form-container .form-group select, .request-form-container .form-group textarea { width: 100%; padding: 10px 14px; border: 2px solid #e8e0d8; border-radius: 10px; font-size: 14px; transition: border-color 0.2s ease; background: #ffffff; color: #1a1a1a; font-family: inherit; }
        .request-form-container .form-group input:focus, .request-form-container .form-group select:focus, .request-form-container .form-group textarea:focus { border-color: #d4a0a0; outline: none; box-shadow: 0 0 0 3px rgba(212,160,160,0.12); }
        .request-form-container .form-group textarea { resize: vertical; min-height: 80px; }
        .request-form-container .form-group input[disabled], .request-form-container .form-group select[disabled] { background: #f5f3f0; color: #6a5a4e; cursor: not-allowed; }
        .request-form-container .form-actions { display: flex; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #f0edea; }
        .request-form-container .form-actions .btn-submit { flex: 1; padding: 12px 28px; background: #1a1a1a; color: #f0e8e0; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .request-form-container .form-actions .btn-cancel { padding: 12px 24px; background: #f0edea; color: #4a3a2e; border: none; border-radius: 10px; font-size: 15px; font-weight: 500; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .request-form-container .info-note { background: #f0edea; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #6a5a4e; }

        .request-item { background: #ffffff; border-radius: 12px; padding: 16px 20px; margin-bottom: 12px; border: 1px solid #e8e0d8; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .request-item .request-info { flex: 1; min-width: 0; }
        .request-item .request-info .request-title { font-weight: 600; color: #1a1a1a; font-size: 15px; word-break: break-word; }
        .request-item .request-info .request-details { font-size: 13px; color: #6a5a4e; margin-top: 2px; word-break: break-word; }
        .request-item .request-status { font-weight: 600; font-size: 13px; padding: 4px 14px; border-radius: 20px; flex-shrink: 0; }
        .request-item .request-status.pending { background: #f0edd8; color: #6a5a3a; }
        .request-item .request-status.approved { background: #dde8e0; color: #1a4a3a; }
        .request-item .request-status.rejected { background: #f0ddd8; color: #8a3a2a; }
        .request-item .request-status.fulfilled { background: #dde8e0; color: #1a4a3a; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; padding: 12px; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: #ffffff; border-radius: 20px; padding: 32px 36px; max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
        .modal-content .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #f0edea; }
        .modal-content .modal-header h2 { font-size: 20px; color: #1a1a1a; margin: 0; }
        .modal-content .modal-header .close-modal { background: none; border: none; font-size: 28px; color: #9a8a7e; cursor: pointer; padding: 0 8px; }
        .modal-content .book-info-preview { background: #faf8f6; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; border: 1px solid #e8e0d8; }
        .modal-content .form-group { margin-bottom: 16px; }
        .modal-content .form-group label { display: block; font-weight: 500; color: #4a3a2e; font-size: 14px; margin-bottom: 4px; }
        .modal-content .form-group input, .modal-content .form-group textarea { width: 100%; padding: 10px 14px; border: 2px solid #e8e0d8; border-radius: 10px; font-size: 14px; background: #ffffff; }
        .modal-content .form-actions { display: flex; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #f0edea; }
        .modal-content .form-actions .btn-primary { padding: 12px 28px; background: #1a1a1a; color: #f0e8e0; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; flex: 1; }
        .modal-content .form-actions .btn-secondary { padding: 12px 24px; background: #f0edea; color: #4a3a2e; border: none; border-radius: 10px; font-size: 15px; font-weight: 500; cursor: pointer; }

        .message { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .message.success { background: #e8ddd8; color: #3a2a2a; border-left: 4px solid #d4a0a0; }
        .message.error { background: #f0e0d8; color: #8a3a2a; border-left: 4px solid #d48080; }
        .message.info { background: #e8e4e0; color: #3a3a3a; border-left: 4px solid #b0a8a0; }

        .table-container { background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e8e0d8; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 560px; }
        .data-table th { background: #f5f3f0; padding: 12px 16px; text-align: left; font-weight: 600; color: #4a3a2e; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        .data-table td { padding: 12px 16px; border-top: 1px solid #f0edea; vertical-align: middle; color: #2a2a2a; font-size: 14px; }
        .data-table tr:hover td { background: #faf8f6; }

        .status-badge { padding: 2px 12px; border-radius: 12px; font-size: 12px; display: inline-block; font-weight: 500; white-space: nowrap; }
        .status-borrowed { background: #e8e4e0; color: #4a3a2e; }
        .status-returned { background: #e8ddd8; color: #3a2a2a; }
        .status-overdue { background: #f0e0d8; color: #8a3a2a; }
        .status-pending { background: #e8e0d8; color: #6a5a4e; }
        .status-paid { background: #dde8e0; color: #2a4a3a; }
        .status-fulfilled { background: #dde8e0; color: #1a4a3a; }
        .status-expired { background: #e8ddd8; color: #6a3a2a; }
        .status-cancelled { background: #e8e4e0; color: #6a5a4e; }
        .status-approved { background: #dde8e0; color: #1a4a3a; }
        .status-rejected { background: #f0ddd8; color: #8a3a2a; }

        .no-data { text-align: center; padding: 40px !important; color: #9a8a7e; }

        /* Profile */
        .profile-page-enhanced{ display: block; max-width:1000px; margin:0 auto; }
        .profile-hero-v2 { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; padding: 26px 30px; margin-bottom: 20px; border-radius: 18px; background: radial-gradient(circle at 88% 0%, rgba(255,1,153,.42), transparent 32%), linear-gradient(115deg, #08080a 0%, #291126 48%, #d41688 100%); color: #fff !important; box-shadow: 0 12px 28px rgba(85,12,66,.12); position: relative; overflow: hidden; }
        .profile-hero-v2::after { content: ''; position: absolute; width: 220px; height: 220px; border-radius: 50%; right: 8%; top: -120px; background: rgba(255,255,255,.12); pointer-events: none; }
        .profile-hero-v2 > * { position: relative; z-index: 1; }
        .profile-hero-avatar { width: 82px; height: 82px; flex: 0 0 82px; border-radius: 50%; display: grid; place-items: center; background: rgba(255,255,255,.13); border: 2px solid rgba(255,255,255,.6); font-size: 30px; font-weight: 800; overflow: hidden; color: #fff; }
        .profile-hero-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .profile-hero-copy { flex: 1; min-width: 180px; }
        .profile-hero-copy h2 { font-size: clamp(22px,3vw,30px); margin: 5px 0; color: #fff !important; }
        .profile-hero-copy p { margin: 0; color: rgba(255,255,255,.82) !important; font-size: 13px; line-height: 1.5; }
        .profile-hero-eyebrow { font-size: 10px; letter-spacing: 1.5px; color: #ffc8e8 !important; font-weight: 800; }
        .profile-hero-status { display: flex; align-items: center; gap: 8px; padding: 9px 13px; border: 1px solid rgba(255,255,255,.25); border-radius: 999px; background: rgba(255,255,255,.1); font-size: 12px; font-weight: 700; color: #fff !important; }
        .profile-hero-status .profile-status-dot { width: 8px; height: 8px; border-radius: 50%; background: #6ee7a5; box-shadow: 0 0 0 4px rgba(110,231,165,.15); }
        .profile-tabs-container { background: #fff; border-radius: 16px; border: 1px solid #e8d8e5; box-shadow: 0 4px 20px rgba(0,0,0,0.03); overflow: hidden; }
        .profile-tabs-nav { display: flex; border-bottom: 1px solid #e8d8e5; background: #faf8fa; padding: 0 20px; gap: 24px; overflow-x: auto; }
        .profile-tab-btn { border: 0; background: transparent; padding: 18px 0; font-size: 14px; font-weight: 600; color: #8d7d89; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; }
        .profile-tab-btn.active { color: #b40f7d; border-bottom-color: #b40f7d; }
        .profile-tab-btn:hover:not(.active) { color: #211522; }
        .profile-tab-pane { display: none; padding: 28px; }
        .profile-tab-pane.active { display: block; animation: fadeIn .3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        .profile-section-title { font-size: 16px; font-weight: 700; margin: 0 0 16px; color: #211522; }
        .profile-photo-upload { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; padding: 20px; border-radius: 12px; border: 1px dashed #e8d8e5; background: rgba(180,15,125,.02); margin-bottom: 24px; }
        .profile-photo-preview { width: 84px; height: 84px; border-radius: 50%; overflow: hidden; flex: 0 0 84px; display: grid; place-items: center; background: #b40f7d; color: #fff; font-size: 32px; font-weight: 800; box-shadow: 0 4px 14px rgba(0,0,0,.1); }
        .profile-photo-preview img { width: 100%; height: 100%; object-fit: cover; }
        .profile-photo-actions { display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
        .profile-photo-actions label { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 18px; border-radius: 8px; border: 1px solid #e8d8e5; background: #fff; color: #211522 !important; font-size: 13px; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
        .profile-photo-actions label:hover { background: #fbf6fa; }
        .profile-photo-actions input[type="file"] { display: none; }
        .profile-photo-remove { background: transparent; border: 0; color: #b42342 !important; font-size: 12px; font-weight: 600; cursor: pointer; padding: 0; text-decoration: underline; }
        .profile-photo-actions small { color: #8d7d89; font-size: 11px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-grid .full { grid-column: 1 / -1; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field label { font-size: 11px; font-weight: 700; color: #6e596d; text-transform: uppercase; letter-spacing: .5px; }
        .field input, .field select, .field textarea { padding: 12px; border: 1px solid #e8d8e5; border-radius: 8px; font-size: 14px; background: #fff; color: #1a1a1a; }
        .field input:focus, .field select:focus, .field textarea:focus { border-color: #b40f7d; outline: none; box-shadow: 0 0 0 3px rgba(180,15,125,.1); }
        .field input:disabled, .field select:disabled { background: #f5f3f0; color: #8d7d89; cursor: not-allowed; }
        .field-hint { font-size: 11px; color: #8d7d89; margin-top: 2px; }
        .profile-actions { display: flex; justify-content: flex-end; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e8d8e5; }
        .btn-save { background: #b40f7d; color: #fff; border: 0; border-radius: 8px; padding: 12px 28px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-save:hover { background: #8a0a5f; }
        .overview-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .overview-item { padding: 16px; border-radius: 10px; border: 1px solid #e8d8e5; background: #fbfafa; }
        .overview-item span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #8d7d89; font-weight: 700; margin-bottom: 6px; }
        .overview-item strong { display: block; font-size: 14px; color: #211522; }

        /* Dark Mode Overrides */
        :root{--dm-bg:#0f0e13;--dm-surface:#1a1720;--dm-surface-2:#221d2a;--dm-border:#3a2e3d;--dm-text:#f3edf5;--dm-text-muted:#c2b3c6}
        body[data-theme="dark"]{background:var(--dm-bg) !important;color:var(--dm-text) !important;color-scheme:dark}
        body[data-theme="dark"] .student-content,body[data-theme="dark"] .dashboard-content{background:var(--dm-bg) !important;color:var(--dm-text) !important}
        body[data-theme="dark"] h1,body[data-theme="dark"] h2,body[data-theme="dark"] h3,body[data-theme="dark"] h4,body[data-theme="dark"] h5,body[data-theme="dark"] h6,body[data-theme="dark"] p,body[data-theme="dark"] span,body[data-theme="dark"] label,body[data-theme="dark"] small,body[data-theme="dark"] td,body[data-theme="dark"] th,body[data-theme="dark"] li,body[data-theme="dark"] strong,body[data-theme="dark"] b,body[data-theme="dark"] .book-title,body[data-theme="dark"] .stat-label,body[data-theme="dark"] .status-title,body[data-theme="dark"] .status-desc,body[data-theme="dark"] .request-title,body[data-theme="dark"] .request-details,body[data-theme="dark"] .book-author,body[data-theme="dark"] .notif-title{color:var(--dm-text) !important}
        body[data-theme="dark"] .stat-sub,body[data-theme="dark"] .muted,body[data-theme="dark"] .subtle,body[data-theme="dark"] .book-meta,body[data-theme="dark"] .no-data,body[data-theme="dark"] .header-date,body[data-theme="dark"] .overview-item span,body[data-theme="dark"] .notif-message,body[data-theme="dark"] .notif-meta{color:var(--dm-text-muted) !important}
        body[data-theme="dark"] .stat-card,body[data-theme="dark"] .panel,body[data-theme="dark"] .card,body[data-theme="dark"] .book-card,body[data-theme="dark"] .table-container,body[data-theme="dark"] .request-item,body[data-theme="dark"] .quick-action-card,body[data-theme="dark"] .modal-content,body[data-theme="dark"] .request-form-container,body[data-theme="dark"] .status-card,body[data-theme="dark"] .profile-container,body[data-theme="dark"] .profile-tabs-container,body[data-theme="dark"] .notification-card,body[data-theme="dark"] .notifications-toolbar,body[data-theme="dark"] .notif-modal-panel,body[data-theme="dark"] .notif-modal-item{background:var(--dm-surface) !important;color:var(--dm-text) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] .notification-card.unread{background:linear-gradient(135deg,var(--dm-surface) 0%,#2a1f2e 100%) !important;border-left-color:#ff4dbb !important}
        body[data-theme="dark"] .notif-modal-body{background:var(--dm-surface-2) !important}
        body[data-theme="dark"] .notif-modal-item.unread{background:var(--dm-surface-2) !important;border-left-color:#ff4dbb !important}
        body[data-theme="dark"] .notif-modal-footer{background:var(--dm-surface) !important;border-top-color:var(--dm-border) !important}
        body[data-theme="dark"] .notif-icon-wrap{background:var(--dm-surface-2) !important}
        body[data-theme="dark"] .notif-action-btn.secondary{background:var(--dm-surface-2) !important;color:var(--dm-text) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] .notif-action-btn.ghost{color:#ff4dbb !important}
        body[data-theme="dark"] .notif-filter-btn{background:var(--dm-surface-2) !important;color:var(--dm-text) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] .notif-filter-btn.active{background:#b40f7d !important;color:#fff !important;border-color:#b40f7d !important}
        body[data-theme="dark"] .notif-empty{background:var(--dm-surface) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] .notif-modal-view-all{background:#b40f7d !important}
        body[data-theme="dark"] .profile-tabs-nav{background:var(--dm-surface-2) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] .profile-tab-btn{color:var(--dm-text-muted) !important}
        body[data-theme="dark"] .profile-tab-btn.active{color:#ff4dbb !important;border-bottom-color:#ff4dbb !important}
        body[data-theme="dark"] .overview-item,body[data-theme="dark"] .profile-photo-upload{background:var(--dm-surface-2) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] .overview-item strong{color:var(--dm-text) !important}
        body[data-theme="dark"] table,body[data-theme="dark"] thead,body[data-theme="dark"] tbody,body[data-theme="dark"] tr{background:transparent !important;color:var(--dm-text) !important}
        body[data-theme="dark"] th{background:var(--dm-surface-2) !important;color:var(--dm-text) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] td{color:var(--dm-text) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] tr:hover td{background:rgba(255,255,255,.04) !important}
        body[data-theme="dark"] input,body[data-theme="dark"] select,body[data-theme="dark"] textarea{background:var(--dm-surface-2) !important;color:#fff !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] input:disabled,body[data-theme="dark"] select:disabled{background:#15121a !important;color:#7a6a7a !important}
        body[data-theme="dark"] input::placeholder,body[data-theme="dark"] textarea::placeholder{color:#8f8296 !important}
        body[data-theme="dark"] [style*="background:#fff"],body[data-theme="dark"] [style*="background: #fff"],body[data-theme="dark"] [style*="background:white"],body[data-theme="dark"] [style*="background: white"],body[data-theme="dark"] [style*="background:#ffffff"],body[data-theme="dark"] [style*="background: #ffffff"],body[data-theme="dark"] [style*="background:#faf5fa"],body[data-theme="dark"] [style*="background:#faf8f6"],body[data-theme="dark"] [style*="background:#f5f3f0"],body[data-theme="dark"] [style*="background:#f8f0f5"],body[data-theme="dark"] [style*="background:#f5eef5"]{background:var(--dm-surface) !important;color:var(--dm-text) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] [style*="color:#1a1a"],body[data-theme="dark"] [style*="color: #1a1a"],body[data-theme="dark"] [style*="color:#333"],body[data-theme="dark"] [style*="color: #333"],body[data-theme="dark"] [style*="color:#2a2a"],body[data-theme="dark"] [style*="color:#4a3a"],body[data-theme="dark"] [style*="color:#6a5a"],body[data-theme="dark"] [style*="color:#766"],body[data-theme="dark"] [style*="color:#8a7a"],body[data-theme="dark"] [style*="color:#9a8a"],body[data-theme="dark"] [style*="color:#211"],body[data-theme="dark"] [style*="color:#171"]{color:var(--dm-text) !important}
        body[data-theme="dark"] .status-badge,body[data-theme="dark"] .count-badge,body[data-theme="dark"] .search-type-badge,body[data-theme="dark"] .book-category{color:white  !important}
        body[data-theme="dark"] .book-card .book-cover-wrapper{background:var(--dm-surface-2) !important}
        body[data-theme="dark"] .book-card .book-info .book-meta{border-top-color:var(--dm-border) !important}
        body[data-theme="dark"] .dashboard-header,body[data-theme="dark"] .dashboard-header *{color:#fff !important}
        body[data-theme="dark"] .btn-cancel,body[data-theme="dark"] .btn-secondary,body[data-theme="dark"] .modal-content .form-actions .btn-secondary{background:var(--dm-surface-2) !important;color:var(--dm-text) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] .request-form-container .info-note{background:var(--dm-surface-2) !important;color:var(--dm-text) !important}
        body[data-theme="dark"] .request-form-container .form-header{border-bottom-color:var(--dm-border) !important}
        body[data-theme="dark"] .request-form-container .form-header .book-preview{background:var(--dm-surface-2) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] .modal-content .book-info-preview{background:var(--dm-surface-2) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] .message,body[data-theme="dark"] .message.success,body[data-theme="dark"] .message.error,body[data-theme="dark"] .message.info{background:var(--dm-surface-2) !important;color:var(--dm-text) !important;border-left-color:#ff0199 !important}
        body[data-theme="dark"] .status-badge-large{color:black !important}
        body[data-theme="dark"] .request-item .request-status{color:black !important}
        body[data-theme="dark"] .predictive-results{background:var(--dm-surface) !important;border-color:var(--dm-border) !important;box-shadow: 0 10px 30px rgba(0,0,0,0.5);}
        body[data-theme="dark"] .predictive-item{background:var(--dm-surface) !important;color:var(--dm-text) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] .predictive-item:hover{background:var(--dm-surface-2) !important}
        body[data-theme="dark"] .pred-title{color:var(--dm-text) !important}
        body[data-theme="dark"] .pred-author{color:var(--dm-text-muted) !important}
        body[data-theme="dark"] .pred-availability{background:rgba(110,231,165,.1) !important;color:#6ee7a5 !important;}
        body[data-theme="dark"] .pred-availability.unavailable{background:rgba(255,100,100,.1) !important;color:#ff8a8a !important;}
        body[data-theme="dark"] .profile-photo-actions label{background:var(--dm-surface-2) !important;color:var(--dm-text) !important;border-color:var(--dm-border) !important}
        body[data-theme="dark"] .profile-photo-actions label:hover{background:#2d2432 !important}

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .full { grid-column: auto; }
            .overview-grid { grid-template-columns: 1fr; }
            .profile-tabs-nav { gap: 16px; }
            .profile-tab-btn { font-size: 13px; padding: 14px 0; }
            .predictive-results { max-height: 250px; }
            .notification-card { flex-direction: column; gap: 12px; }
            .notif-icon-wrap { width: 40px; height: 40px; flex: 0 0 40px; font-size: 19px; }
            .notifications-toolbar { flex-direction: column; align-items: stretch; }
            .notif-mark-all { justify-content: center; }
            .notif-modal-item { flex-direction: column; gap: 10px; }
        }
    </style>
    <script src="<?php echo $basePath; ?>/firebase_config.js"></script>
    <script src="<?php echo $basePath; ?>/ai_functions.js"></script>
</head>
<body>
    <header class="top-header" id="topHeader">
        <div class="header-left-group">
            <button class="hamburger-btn" onclick="toggleSidebar()" title="Toggle Sidebar" aria-label="Toggle Sidebar">
                <span class="hamburger-lines"><span></span><span></span><span></span></span>
            </button>
        </div>
        <nav class="header-nav-symbols" aria-label="Account actions">
            <button class="header-action" type="button" data-notifications-open title="Notifications" aria-label="Notifications"><span class="action-icon">♧</span><span class="action-label">Notifications</span><?php if (!empty($unreadNotifications)): ?><span class="header-badge"><?php echo count($unreadNotifications); ?></span><?php endif; ?></button>
            <a class="header-action" href="student_dashboard.php?section=profile" title="Profile"><span class="action-icon">◉</span><span class="action-label">Profile</span></a>
            <button class="header-action theme-action" type="button" data-theme-toggle aria-pressed="false" title="Toggle theme"><span class="action-icon">☾</span><span class="action-label">Dark mode</span></button>
            <a class="header-action logout-action" href="../admin_logout.php" title="Logout"><span class="action-icon">↪</span><span class="action-label">Logout</span></a>
        </nav>
    </header>

    <div class="student-app">
        <div class="student-sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo-wrapper">
                    <img src="../img/agustinnb.png" alt="SAAC Logo" class="sidebar-logo" onerror="this.style.display='none'">
                </div>
                <h2>ST. AGNES ACADEMY</h2>
                <div class="subtitle">Caloocan Inc.</div>
                <p><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?></p>
                <small>ID: <?php echo htmlspecialchars($_SESSION['user_id_display'] ?? 'N/A'); ?></small>
            </div>
            <nav class="sidebar-nav">
                <a href="student_dashboard.php?section=dashboard" class="<?php echo $section === 'dashboard' ? 'active' : ''; ?>"><span class="nav-icon">🗠</span><span class="nav-label">Dashboard</span></a>
                <a href="student_dashboard.php?section=search" class="<?php echo $section === 'search' ? 'active' : ''; ?>"><span class="nav-icon">🕮</span><span class="nav-label">Search Books</span></a>
                <a href="student_dashboard.php?section=borrowings" class="<?php echo $section === 'borrowings' ? 'active' : ''; ?>"><span class="nav-icon">⎘</span><span class="nav-label">My Borrowings</span></a>
                <a href="student_dashboard.php?section=reservations" class="<?php echo $section === 'reservations' ? 'active' : ''; ?>"><span class="nav-icon">⏱</span><span class="nav-label">Reservations</span></a>
                <a href="student_dashboard.php?section=fines" class="<?php echo $section === 'fines' ? 'active' : ''; ?>"><span class="nav-icon">⚠</span><span class="nav-label">My Fines</span></a>
                <a href="student_dashboard.php?section=requests" class="<?php echo $section === 'requests' ? 'active' : ''; ?>"><span class="nav-icon">🖺</span><span class="nav-label">My Requests</span><?php if (!empty($pendingRequests)): ?><span class="notification-badge"><?php echo count($pendingRequests); ?></span><?php endif; ?></a>
                <a href="student_dashboard.php?section=notifications" class="<?php echo $section === 'notifications' ? 'active' : ''; ?>"><span class="nav-icon">♧</span><span class="nav-label">Notifications</span><?php if (!empty($unreadNotifications)): ?><span class="notification-badge"><?php echo count($unreadNotifications); ?></span><?php endif; ?></a>
                <a href="student_dashboard.php?section=profile" class="<?php echo $section === 'profile' ? 'active' : ''; ?>"><span class="nav-icon">⚙</span><span class="nav-label">Profile</span></a>
            </nav>
            <?php if ($isAdminPreview): ?>
            <div style="padding:12px 18px 0;">
                <a href="../admin_dashboard.php" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 12px;border-radius:9px;background:#fce7f3;color:#9d174d;border:1px solid #f9a8d4;font-size:12px;font-weight:700;text-decoration:none;">← Back to Admin Dashboard</a>
            </div>
            <?php endif; ?>
        </div>

        <div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

        <div class="student-content" id="studentContent">
            <?php if ($isAdminPreview): ?>
                <div style="margin:14px 18px 0;padding:11px 14px;border:1px solid #f9a8d4;border-radius:10px;background:#fdf2f8;color:#9d174d;font-size:12px;">
                    <strong>Administrator Preview:</strong> You are viewing the complete Student Workspace using your administrator account.
                </div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'Error') !== false || strpos($message, 'restricted') !== false || strpos($message, 'not available') !== false ? 'error' : 'success'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($section === 'dashboard'): ?>
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <div class="header-left">
                        <h1>Student Dashboard</h1>
                        <p class="header-date">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?> · St. Agnes Academy Library Management System</p>
                    </div>
                    <div class="header-time">
                        <span class="time" id="currentTime"><?php echo date('g:i A'); ?></span>
                        <span class="date" id="currentDateDisplay"><?php echo date('F j, Y'); ?></span>
                    </div>
                </div>
                <div class="status-card">
                    <div class="status-dot <?php echo strtolower(str_replace(' ', '-', $accountStatus)); ?>"></div>
                    <div class="status-info">
                        <div class="status-title">Account Status: <?php echo $accountStatus; ?></div>
                        <div class="status-desc">
                            <?php if ($accountStatus === 'Restricted'): ?>
                                <?php if ($hasOverdue): ?>⚠️ You have overdue books.<?php endif; ?>
                                <?php if ($hasUnpaidFines): ?>⚠️ You have unpaid fines (₱<?php echo number_format($totalPendingFines, 2); ?>).<?php endif; ?>
                            <?php elseif ($accountStatus === 'Active'): ?>You have active borrowings. Please return them on time.
                            <?php else: ?>Your account is in good standing.<?php endif; ?>
                        </div>
                    </div>
                    <span class="status-badge-large <?php echo strtolower(str_replace(' ', '-', $accountStatus)); ?>"><?php echo $accountStatus; ?></span>
                </div>
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-number"><?php echo count($activeBorrowings); ?></div><div class="stat-label">Active Borrowings</div></div>
                    <div class="stat-card"><div class="stat-number"><?php echo count($overdueBorrowings); ?></div><div class="stat-label">Overdue Books</div></div>
                    <div class="stat-card"><div class="stat-number"><?php echo count($reservations); ?></div><div class="stat-label">Reservations</div></div>
                    <div class="stat-card"><div class="stat-number">₱<?php echo number_format($totalPendingFines, 2); ?></div><div class="stat-label">Pending Fines</div></div>
                </div>
                <div class="quick-actions">
                    <a href="student_dashboard.php?section=search" class="quick-action-card"><span class="action-icon">🕮</span><span class="action-label">Search Books</span></a>
                    <a href="student_dashboard.php?section=borrowings" class="quick-action-card"><span class="action-icon">⎘</span><span class="action-label">My Borrowings</span></a>
                    <a href="student_dashboard.php?section=reservations" class="quick-action-card"><span class="action-icon">⏱</span><span class="action-label">Reservations</span></a>
                    <a href="student_dashboard.php?section=requests" class="quick-action-card" style="position:relative;"><span class="action-icon">🖺</span><span class="action-label">My Requests</span><?php if (!empty($pendingRequests)): ?><span class="action-badge"><?php echo count($pendingRequests); ?></span><?php endif; ?></a>
                </div>
                <div style="background:#ffffff;border-radius:16px;padding:20px 24px;border:1px solid #e8e0d8;overflow-x:auto;">
                    <h3 style="margin:0 0 16px 0;color:#1a1a1a;font-weight:600;">Recent Borrowings</h3>
                    <?php if (!empty($borrowings)): ?>
                        <table class="data-table">
                            <thead><tr><th>Book</th><th>Borrowed</th><th>Due Date</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($borrowings, 0, 5) as $b): $bookTitle = $b['books']['title'] ?? 'Unknown'; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bookTitle); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($b['borrow_date'] ?? 'now')); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($b['due_date'] ?? 'now')); ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($b['status'] ?? 'borrowed'); ?>"><?php echo $b['status'] ?? 'Borrowed'; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color:#9a8a7e;text-align:center;padding:20px;">No borrowings yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($section === 'search'): ?>
            <div class="book-management">
                <div class="section-header">
                    <h1>Search Books</h1>
                    <div class="search-info">
                        <span class="search-type-badge" id="searchTypeBadge" style="display:none;">🧠 AI Search</span>
                        <span class="count-badge">Books: <strong id="resultCount"><?php echo count($allBooks); ?></strong></span>
                        <span id="aiStatus" style="font-size:12px;">🤖 Initializing AI...</span>
                    </div>
                </div>
                <div class="search-bar">
                    <div class="search-input-wrapper">
                        <span class="search-icon">⌕</span>
                        <input type="text" id="searchInput" placeholder="Search by title, author, or describe what you need..." value="<?php echo htmlspecialchars($searchQuery); ?>" onkeydown="if(event.key==='Enter'){event.preventDefault(); performSearch();}">
                        <button class="clear-btn" id="clearSearchBtn" onclick="clearSearch()">✕</button>
                        <div class="predictive-results" id="predictiveResults"></div>
                    </div>
                    <button onclick="performSearch()" style="padding:12px 24px;background:#1a1a2e;color:#f0e8e8;border:none;border-radius:10px;cursor:pointer;font-size:14px;font-weight:500;">Search</button>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
                    <span style="padding:6px 16px;border:1px solid #f0e0ee;border-radius:20px;font-size:13px;cursor:pointer;" onclick="quickSearch('programming')">Programming</span>
                    <span style="padding:6px 16px;border:1px solid #f0e0ee;border-radius:20px;font-size:13px;cursor:pointer;" onclick="quickSearch('history')">History</span>
                    <span style="padding:6px 16px;border:1px solid #f0e0ee;border-radius:20px;font-size:13px;cursor:pointer;" onclick="quickSearch('science')">Science</span>
                    <span style="padding:6px 16px;border:1px solid #f0e0ee;border-radius:20px;font-size:13px;cursor:pointer;" onclick="quickSearch('psychology')">Psychology</span>
                    <span style="padding:6px 16px;border:1px solid #f0e0ee;border-radius:20px;font-size:13px;cursor:pointer;" onclick="quickSearch('fiction')">Fiction</span>
                </div>
                <div class="book-grid" id="bookGrid">
                    <?php foreach ($allBooks as $book): 
                        $categoryName = isset($book['categories']['name']) ? $book['categories']['name'] : 'Uncategorized';
                        $coverImage = $book['cover_image'] ?? '';
                        $hasCover = hasValidCoverImage($coverImage);
                        $available = $book['available'] ?? 0;
                        $bookId = $book['id'] ?? uniqid();
                        $title = $book['title'] ?? 'Unknown';
                        $author = $book['author'] ?? 'Unknown';
                        $hasPendingRequest = !empty(array_filter($studentRequests, function($r) use ($bookId) { return $r['book_id'] == $bookId && ($r['status'] ?? '') === 'Pending'; }));
                        $hasApprovedRequest = !empty(array_filter($studentRequests, function($r) use ($bookId) { return $r['book_id'] == $bookId && ($r['status'] ?? '') === 'Approved'; }));
                        $canBorrow = !$isRestricted && $available > 0 && $hasApprovedRequest;
                        $canRequest = !$isRestricted && !$hasPendingRequest && !$hasApprovedRequest;
                    ?>
                        <div class="book-card" data-book-id="<?php echo $bookId; ?>">
                            <div class="book-cover-wrapper">
                                <?php if ($hasCover): ?>
                                    <img src="<?php echo htmlspecialchars($coverImage); ?>" alt="<?php echo htmlspecialchars($title); ?>">
                                <?php else: ?>
                                    <div class="cover-placeholder" style="background-color:<?php echo getPlaceholderColor($bookId); ?>;"><span class="initial"><?php echo strtoupper(substr($title, 0, 1)); ?></span></div>
                                <?php endif; ?>
                                <span class="availability-badge <?php echo $available <= 0 ? 'none' : ($available <= 2 ? 'low' : ''); ?>"><?php echo $available <= 0 ? 'Not Available' : ($available <= 2 ? 'Low Stock' : 'Available'); ?></span>
                            </div>
                            <div class="book-info">
                                <h3 class="book-title"><?php echo htmlspecialchars($title); ?></h3>
                                <p class="book-author">by <?php echo htmlspecialchars($author); ?></p>
                                <span class="book-category"><?php echo htmlspecialchars($categoryName); ?></span>
                                <div class="book-meta">
                                    <span class="availability"><strong><?php echo $available; ?></strong> / <?php echo $book['quantity'] ?? 0; ?> available</span>
                                    <div class="book-actions">
                                        <?php if ($isRestricted): ?>
                                            <button class="btn-borrow" disabled>Restricted</button>
                                        <?php elseif ($canBorrow): ?>
                                            <button class="btn-borrow" onclick="openBorrowModal('<?php echo $bookId; ?>', '<?php echo htmlspecialchars($title, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($author, ENT_QUOTES); ?>', <?php echo $available; ?>)">Borrow</button>
                                        <?php elseif ($hasPendingRequest): ?>
                                            <span class="btn-request" style="background:#d4c9c0;cursor:not-allowed;opacity:0.6;">Request Pending</span>
                                        <?php elseif ($canRequest): ?>
                                            <button class="btn-request" onclick="openRequestForm('<?php echo $bookId; ?>', '<?php echo htmlspecialchars($title, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($author, ENT_QUOTES); ?>', 'borrow')">Request</button>
                                        <?php else: ?>
                                            <span class="btn-borrow" style="background:#d4c9c0;cursor:not-allowed;color:#8a7a6e;">Unavailable</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php elseif ($section === 'request_form'): ?>
            <div class="request-form-container">
                <div class="form-header">
                    <h1 style="margin:0;font-size:22px;color:#1a1a1a;font-weight:600;">🖺 Book Request Form</h1>
                    <p style="color:#6a5a4e;font-size:14px;margin:4px 0 0;">Please fill out this form to request a book.</p>
                    <?php if (!empty($requestBookData)): ?>
                        <div class="book-preview">
                            <div class="preview-title"><?php echo htmlspecialchars($requestBookData['title'] ?? 'Unknown Book'); ?></div>
                            <div class="preview-author">by <?php echo htmlspecialchars($requestBookData['author'] ?? 'Unknown'); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($requestMessage): ?>
                    <div class="message <?php echo strpos($requestMessage, 'Error') !== false ? 'error' : 'info'; ?>"><?php echo htmlspecialchars($requestMessage); ?></div>
                <?php endif; ?>
                <div class="info-note"><strong>📌 Note:</strong> Your request will be reviewed by the admin.</div>
                <form method="POST" action="student_dashboard.php?section=request_form">
                    <input type="hidden" name="book_id" value="<?php echo htmlspecialchars($requestBookId); ?>">
                    <input type="hidden" name="request_type" value="<?php echo htmlspecialchars($requestType); ?>">
                    <div class="form-group"><label>Full Name <span class="required">*</span></label><input type="text" name="full_name" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>" readonly required></div>
                    <div class="form-group"><label>Student ID <span class="required">*</span></label><input type="text" name="student_id" value="<?php echo htmlspecialchars($studentData['student_id'] ?? $_SESSION['user_id_display'] ?? ''); ?>" readonly required></div>
                    <div class="form-group">
                        <label>Year Level <span class="required">*</span></label>
                        <select name="year_level" required>
                            <option value="">Select Year Level</option>
                            <?php foreach (['Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12'] as $yl): ?>
                                <option value="<?php echo $yl; ?>" <?php echo ($_POST['year_level'] ?? ($studentData['year_level'] ?? '')) === $yl ? 'selected' : ''; ?>><?php echo $yl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Section <span class="required">*</span></label><input type="text" name="section" value="<?php echo htmlspecialchars($_POST['section'] ?? ($studentData['section'] ?? '')); ?>" placeholder="Enter your section (e.g. STEM-A)" required></div>
                    <div class="form-group"><label>Request Type</label><input type="text" value="<?php echo ucfirst($requestType); ?>" disabled style="background:#f5f3f0;"></div>
                    <div class="form-group"><label>Purpose of Request <span class="required">*</span></label><textarea name="purpose" placeholder="Please explain why you need this book..." rows="3" required></textarea></div>
                    <div class="form-actions">
                        <a href="student_dashboard.php?section=search" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-submit">Submit Request</button>
                    </div>
                </form>
            </div>

            <?php elseif ($section === 'requests'): ?>
            <div class="requests-management">
                <div class="section-header"><h1>My Book Requests</h1><span class="count-badge"><?php echo count($studentRequests); ?> requests</span></div>
                <?php if (!empty($studentRequests)): ?>
                    <?php foreach ($studentRequests as $request): 
                        $bookTitle = $request['books']['title'] ?? 'Unknown Book';
                        $bookAuthor = $request['books']['author'] ?? 'Unknown Author';
                        $bookId = $request['book_id'] ?? '';
                        $status = $request['status'] ?? 'Pending';
                        $requestTypeRow = $request['request_type'] ?? 'borrow';
                        $createdAt = $request['created_at'] ?? 'now';
                        $bookAvailable = 0;
                        foreach ($allBooks as $bk) { if ($bk['id'] == $bookId) { $bookAvailable = $bk['available'] ?? 0; break; } }
                        $canBorrowNow = ($status === 'Approved') && ($requestTypeRow === 'borrow') && ($bookAvailable > 0) && !$isRestricted;
                    ?>
                        <div class="request-item">
                            <div class="request-info">
                                <div class="request-title"><?php echo htmlspecialchars($bookTitle); ?></div>
                                <div class="request-details">by <?php echo htmlspecialchars($bookAuthor); ?> • <?php echo ucfirst($requestTypeRow); ?> request • <?php echo date('M d, Y', strtotime($createdAt)); ?></div>
                            </div>
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <span class="request-status <?php echo strtolower($status); ?>"><?php echo $status; ?></span>
                                <?php if ($canBorrowNow): ?>
                                    <button class="btn-borrow" onclick="openBorrowModal('<?php echo $bookId; ?>', '<?php echo htmlspecialchars($bookTitle, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($bookAuthor, ENT_QUOTES); ?>', <?php echo $bookAvailable; ?>)">Borrow Now</button>
                                <?php elseif ($status === 'Approved' && $bookAvailable <= 0): ?>
                                    <span style="font-size:12px;color:#8a3a2a;">Book currently unavailable</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="background:#ffffff;border-radius:16px;padding:60px 40px;text-align:center;border:1px solid #e8e0d8;">
                        <h3 style="color:#1a1a1a;margin-bottom:8px;">No Requests Yet</h3>
                        <p style="color:#9a8a7e;">You haven't submitted any book requests.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php elseif ($section === 'borrowings'): ?>
            <div class="borrowing-management">
                <div class="section-header"><h1>My Borrowings</h1><span class="count-badge"><?php echo count($borrowings); ?> borrowings</span></div>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>Book</th><th>Author</th><th>Borrowed</th><th>Due Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if (!empty($borrowings)): ?>
                                <?php foreach ($borrowings as $b): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($b['books']['title'] ?? 'Unknown'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($b['books']['author'] ?? 'Unknown'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($b['borrow_date'] ?? 'now')); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($b['due_date'] ?? 'now')); ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($b['status'] ?? 'borrowed'); ?>"><?php echo $b['status'] ?? 'Borrowed'; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="no-data">No borrowings found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($section === 'reservations'): ?>
            <div class="reservation-management">
                <div class="section-header"><h1>My Reservations</h1><span class="count-badge"><?php echo count($reservations); ?> reservations</span></div>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>Book</th><th>Reserved</th><th>Expiry Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if (!empty($reservations)): ?>
                                <?php foreach ($reservations as $r): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($r['books']['title'] ?? 'Unknown'); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($r['reservation_date'] ?? 'now')); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($r['expiry_date'] ?? 'now')); ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($r['status'] ?? 'pending'); ?>"><?php echo $r['status'] ?? 'Pending'; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="no-data">No reservations found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($section === 'fines'): ?>
            <div class="fine-management">
                <div class="section-header"><h1>My Fines</h1><span class="count-badge">Total: ₱<?php echo number_format($totalFines, 2); ?></span></div>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>Reason</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if (!empty($fines)): ?>
                                <?php foreach ($fines as $f): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($f['reason'] ?? 'Late Return'); ?></td>
                                        <td>₱<?php echo number_format($f['amount'] ?? 0, 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($f['created_at'] ?? 'now')); ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($f['status'] ?? 'pending'); ?>"><?php echo $f['status'] ?? 'Pending'; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="no-data">No fines found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($section === 'notifications'): ?>
            <div class="notifications-page">
                <div class="section-header">
                    <h1>♧ Notifications</h1>
                    <span class="count-badge"><?php echo count($notifications); ?> total · <?php echo count($unreadNotifications); ?> unread</span>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="message success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <div class="notifications-toolbar">
                    <div class="notifications-filter" id="notifFilters">
                        <button class="notif-filter-btn active" data-filter="all" onclick="filterNotifications('all')">All <span class="filter-count"><?php echo count($notifications); ?></span></button>
                        <button class="notif-filter-btn" data-filter="unread" onclick="filterNotifications('unread')">Unread <span class="filter-count"><?php echo count($unreadNotifications); ?></span></button>
                        <button class="notif-filter-btn" data-filter="read" onclick="filterNotifications('read')">Read <span class="filter-count"><?php echo count($notifications) - count($unreadNotifications); ?></span></button>
                    </div>
                    <?php if (!empty($unreadNotifications)): ?>
                        <a href="student_dashboard.php?section=notifications&action=mark_all_read" class="notif-mark-all" onclick="return confirm('Mark all notifications as read?')">✓ Mark all as read</a>
                    <?php else: ?>
                        <button class="notif-mark-all" disabled>✓ All read</button>
                    <?php endif; ?>
                </div>

                <div class="notification-list" id="notificationList">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notice): 
                            $isUnread = empty($notice['is_read']);
                            $noticeType = $notice['type'] ?? 'system';
                            $icon = getNotificationIcon($noticeType, $notice['icon'] ?? '');
                            $colorClass = getNotificationColorClass($noticeType);
                            $timeAgo = formatNotificationTime($notice['created_at'] ?? '');
                        ?>
                            <div class="notification-card <?php echo $isUnread ? 'unread' : ''; ?>" data-status="<?php echo $isUnread ? 'unread' : 'read'; ?>">
                                <div class="notif-icon-wrap <?php echo $colorClass; ?>"><?php echo $icon; ?></div>
                                <div class="notif-body">
                                    <div class="notif-title"><?php echo htmlspecialchars($notice['title'] ?? 'Library Notification'); ?></div>
                                    <div class="notif-message"><?php echo htmlspecialchars($notice['message'] ?? ''); ?></div>
                                    <div class="notif-meta">
                                        <span><?php echo $timeAgo; ?></span>
                                        <?php if (!empty($notice['created_at'])): ?><span class="notif-dot"></span><span><?php echo date('M j, Y · g:i A', strtotime($notice['created_at'])); ?></span><?php endif; ?>
                                        <?php if ($isUnread): ?><span class="notif-dot"></span><span style="color:#b40f7d;font-weight:600;">● Unread</span><?php endif; ?>
                                    </div>
                                    <div class="notif-actions-row">
                                        <?php if (!empty($notice['action_url'])): ?>
                                            <a href="<?php echo htmlspecialchars($notice['action_url']); ?>" class="notif-action-btn primary"><?php echo htmlspecialchars($notice['action_label'] ?? 'View'); ?> →</a>
                                        <?php endif; ?>
                                        <?php if ($isUnread): ?>
                                            <a href="student_dashboard.php?section=notifications&action=mark_read&id=<?php echo urlencode($notice['id']); ?>&return=notifications" class="notif-action-btn ghost">✓ Mark as read</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="notif-empty">
                            <span class="empty-icon">♧</span>
                            <h3>No notifications yet</h3>
                            <p>When you get updates about your books, fines, or requests, they'll appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($section === 'profile'): ?>
            <div class="dashboard-content profile-page-enhanced">
                <div class="profile-hero-v2">
                    <div class="profile-hero-avatar">
                        <?php if (!empty($_SESSION['avatar_url'])): ?>
                            <img src="<?php echo htmlspecialchars($_SESSION['avatar_url']); ?>" alt="Profile picture">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'S', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-hero-copy">
                        <span class="profile-hero-eyebrow">ST. AGNES ACADEMY · STUDENT WORKSPACE</span>
                        <h2>My Profile</h2>
                        <p>Manage your account details and view your library membership.</p>
                    </div>
                    <div class="profile-hero-status"><span class="profile-status-dot"></span><?php echo htmlspecialchars($accountStatus); ?></div>
                </div>

                <div class="profile-tabs-container">
                    <div class="profile-tabs-nav">
                        <button type="button" class="profile-tab-btn <?= $activeTab === 'overview' ? 'active' : '' ?>" onclick="switchProfileTab('overview')">Overview</button>
                        <button type="button" class="profile-tab-btn <?= $activeTab === 'edit' ? 'active' : '' ?>" onclick="switchProfileTab('edit')">Edit profile</button>
                        <button type="button" class="profile-tab-btn <?= $activeTab === 'security' ? 'active' : '' ?>" onclick="switchProfileTab('security')">Security</button>
                    </div>

                    <div class="profile-tab-pane <?= $activeTab === 'overview' ? 'active' : '' ?>" id="tab-overview">
                        <h3 class="profile-section-title">Account Overview</h3>
                        <div class="overview-grid">
                            <div class="overview-item"><span>Full Name</span><strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?></strong></div>
                            <div class="overview-item"><span>Student ID</span><strong><?php echo htmlspecialchars($studentData['student_id'] ?? $_SESSION['user_id_display'] ?? 'N/A'); ?></strong></div>
                            <div class="overview-item"><span>Email</span><strong><?php echo htmlspecialchars($_SESSION['email'] ?? 'Not provided'); ?></strong></div>
                            <div class="overview-item"><span>Phone</span><strong><?php echo htmlspecialchars($userRecord['phone'] ?? 'Not provided'); ?></strong></div>
                            <div class="overview-item"><span>Year Level</span><strong><?php echo htmlspecialchars($studentData['year_level'] ?? 'N/A'); ?></strong></div>
                            <div class="overview-item"><span>Section</span><strong><?php echo htmlspecialchars($studentData['section'] ?? 'N/A'); ?></strong></div>
                            <div class="overview-item"><span>Home Address</span><strong><?php echo htmlspecialchars($userRecord['address'] ?? 'Not provided'); ?></strong></div>
                            <div class="overview-item"><span>Username</span><strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'N/A'); ?></strong></div>
                        </div>
                    </div>

                    <div class="profile-tab-pane <?= $activeTab === 'edit' ? 'active' : '' ?>" id="tab-edit">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="student_profile_action" value="update_photo">
                            <h3 class="profile-section-title">Profile photo</h3>
                            <div class="profile-photo-upload">
                                <div class="profile-photo-preview" id="studentPhotoPreview">
                                    <?php if (!empty($_SESSION['avatar_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($_SESSION['avatar_url']); ?>" alt="Profile picture">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'S', 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="profile-photo-actions">
                                    <label for="studentPhotoInput">Upload new photo</label>
                                    <input type="file" id="studentPhotoInput" name="avatar" accept="image/*" onchange="previewStudentPhoto(event)">
                                    <small>JPG or PNG, square works best. Without a photo your initials are shown.</small>
                                    <?php if (!empty($_SESSION['avatar_url'])): ?>
                                        <button type="button" class="profile-photo-remove" onclick="removeStudentPhoto()">Remove</button>
                                    <?php endif; ?>
                                </div>
                                <button class="btn-save" type="submit" style="margin-left:auto;align-self:center;padding:10px 18px;">Save photo</button>
                            </div>
                        </form>

                        <form method="post">
                            <input type="hidden" name="student_profile_action" value="update_info">
                            <h3 class="profile-section-title">Personal information</h3>
                            <div class="form-grid">
                                <div class="field full"><label>Full Name</label><input name="full_name" required value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>"></div>
                                <div class="field"><label>Student ID</label><input value="<?php echo htmlspecialchars($studentData['student_id'] ?? $_SESSION['user_id_display'] ?? 'N/A'); ?>" disabled><div class="field-hint">Locked</div></div>
                                <div class="field"><label>Year Level</label>
                                    <select name="year_level">
                                        <?php foreach (['Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12'] as $yl): ?>
                                            <option value="<?php echo $yl; ?>" <?php echo ($studentData['year_level'] ?? '') === $yl ? 'selected' : ''; ?>><?php echo $yl; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field"><label>Section</label><input name="section_name" value="<?php echo htmlspecialchars($studentData['section'] ?? ''); ?>"></div>
                            </div>
                            
                            <h3 class="profile-section-title" style="margin-top:32px;">Contact details</h3>
                            <div class="form-grid">
                                <div class="field"><label>Email address</label><input type="email" name="email" required value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>"></div>
                                <div class="field"><label>Mobile number</label><input name="phone" value="<?php echo htmlspecialchars($userRecord['phone'] ?? ''); ?>"></div>
                                <div class="field full"><label>Home address</label><textarea name="address" rows="2"><?php echo htmlspecialchars($userRecord['address'] ?? ''); ?></textarea></div>
                            </div>
                            <div class="profile-actions">
                                <button class="btn-save" type="submit">Save changes</button>
                            </div>
                        </form>
                    </div>

                    <div class="profile-tab-pane <?= $activeTab === 'security' ? 'active' : '' ?>" id="tab-security">
                        <form method="post">
                            <input type="hidden" name="student_profile_action" value="update_security">
                            <h3 class="profile-section-title">Change Password</h3>
                            <p class="subtle" style="margin:-10px 0 20px;">Ensure your account is using a long, random password to stay secure.</p>
                            <div class="form-grid" style="max-width:500px;">
                                <div class="field full"><label>Current Password</label><input type="password" name="current_password" required placeholder="Enter current password"></div>
                                <div class="field full"><label>New Password</label><input type="password" name="new_password" placeholder="Leave blank to keep current password"></div>
                                <div class="field full"><label>Confirm New Password</label><input type="password" name="confirm_password" placeholder="Confirm new password"></div>
                            </div>
                            <div class="profile-actions" style="justify-content:flex-start;">
                                <button class="btn-save" type="submit">Update Password</button>
                            </div>
                        </form>
                        
                       
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <div class="modal-overlay" id="borrowModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📖 Borrow Book</h2>
                <button class="close-modal" onclick="closeModal('borrowModal')">&times;</button>
            </div>
            <div class="book-info-preview">
                <div id="borrowBookTitle" style="font-weight:600;">Book Title</div>
                <div id="borrowBookAuthor" style="color:#6a5a4e;font-size:14px;">by Author</div>
            </div>
            <p style="color:#6a5a4e;font-size:13px;margin-bottom:16px;">You are about to borrow this book. The due date will be 14 days from today.</p>
            <form id="borrowForm" method="GET" action="student_dashboard.php">
                <input type="hidden" name="section" id="borrowFormSection" value="search">
                <input type="hidden" name="action" value="borrow">
                <input type="hidden" name="book_id" id="borrowBookId">
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('borrowModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Confirm Borrow</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Enhanced Notification Modal -->
    <div class="profile-modal-backdrop" id="studentNotificationsModal" role="dialog" aria-modal="true" aria-labelledby="studentNotificationsTitle">
        <section class="notification-modal-panel">
            <div class="notif-modal-header">
                <div class="notif-modal-top">
                    <div>
                        <h2 id="studentNotificationsTitle" class="notif-modal-title">♧ Notifications</h2>
                        <p class="notif-modal-subtitle"><?php echo count($unreadNotifications); ?> unread · <?php echo count($notifications); ?> total</p>
                    </div>
                    <button type="button" class="notif-modal-close" data-notifications-close aria-label="Close notifications">&times;</button>
                </div>
                <div class="notif-modal-actions-bar">
                    <span style="font-size:12px;color:rgba(255,255,255,.75);">Latest updates from the library</span>
                    <?php if (!empty($unreadNotifications)): ?>
                        <a href="student_dashboard.php?section=notifications&action=mark_all_read" class="notif-modal-mark-all" onclick="return confirm('Mark all notifications as read?')">✓ Mark all read</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="notif-modal-body">
                <?php if (!empty($notifications)): ?>
                    <?php foreach (array_slice($notifications, 0, 10) as $notice): 
                        $isUnread = empty($notice['is_read']);
                        $noticeType = $notice['type'] ?? 'system';
                        $icon = getNotificationIcon($noticeType, $notice['icon'] ?? '');
                        $colorClass = getNotificationColorClass($noticeType);
                        $timeAgo = formatNotificationTime($notice['created_at'] ?? '');
                    ?>
                        <div class="notif-modal-item <?php echo $isUnread ? 'unread' : ''; ?>">
                            <div class="notif-icon-wrap <?php echo $colorClass; ?>"><?php echo $icon; ?></div>
                            <div class="notif-body" style="flex:1;min-width:0;">
                                <div class="notif-title"><?php echo htmlspecialchars($notice['title'] ?? 'Library Notification'); ?></div>
                                <div class="notif-message"><?php echo htmlspecialchars($notice['message'] ?? ''); ?></div>
                                <div class="notif-meta">
                                    <span><?php echo $timeAgo; ?></span>
                                    <?php if ($isUnread): ?><span class="notif-dot"></span><span style="color:#b40f7d;font-weight:600;">Unread</span><?php endif; ?>
                                </div>
                                <div class="notif-actions-row">
                                    <?php if (!empty($notice['action_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($notice['action_url']); ?>" class="notif-action-btn primary"><?php echo htmlspecialchars($notice['action_label'] ?? 'View'); ?></a>
                                    <?php endif; ?>
                                    <?php if ($isUnread): ?>
                                        <a href="student_dashboard.php?section=notifications&action=mark_read&id=<?php echo urlencode($notice['id']); ?>&return=dashboard" class="notif-action-btn ghost">✓ Mark read</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="notif-modal-empty">
                        <span class="empty-icon">♧</span>
                        <h4>No notifications yet</h4>
                        <p>You're all caught up!</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="notif-modal-footer">
                <a href="student_dashboard.php?section=notifications" class="notif-modal-view-all">View all notifications →</a>
            </div>
        </section>
    </div>

    <script>
        const allBooks = <?php echo json_encode($allBooks); ?>;
        const userId = '<?php echo $userId; ?>';
        const userGradeLevel = '<?php echo $studentData['year_level'] ?? 'Grade 10'; ?>';
        const userSubjects = <?php echo json_encode(array_column($studentSubjects, 'subject_name')); ?>;
        const userHistory = <?php echo json_encode(array_column($userSearchHistory, 'query')); ?>;

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('studentContent');
            const topHeader = document.getElementById('topHeader');
            const overlay = document.getElementById('mobileOverlay');
            if (window.innerWidth <= 900) {
                sidebar.classList.toggle('mobile-open');
                if (sidebar.classList.contains('mobile-open')) { overlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
                else { overlay.classList.remove('active'); document.body.style.overflow = ''; }
            } else {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('collapsed');
                topHeader.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
            }
        }
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        document.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth > 900 && localStorage.getItem('sidebarCollapsed') === '1') {
                document.getElementById('sidebar').classList.add('collapsed');
                document.getElementById('studentContent').classList.add('collapsed');
                document.getElementById('topHeader').classList.add('collapsed');
            }
        });
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('mobileOverlay');
                if (window.innerWidth > 900) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                } else {
                    sidebar.classList.remove('collapsed');
                    document.getElementById('studentContent').classList.remove('collapsed');
                    document.getElementById('topHeader').classList.remove('collapsed');
                }
            }, 150);
        });

        function openModal(modalId) { document.getElementById(modalId).classList.add('active'); document.body.style.overflow = 'hidden'; }
        function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); document.body.style.overflow = ''; }
        document.querySelectorAll('.modal-overlay').forEach(function(modal) {
            modal.addEventListener('click', function(e) { if (e.target === this) { this.classList.remove('active'); document.body.style.overflow = ''; } });
        });
        function openBorrowModal(bookId, title, author, available) {
            document.getElementById('borrowBookId').value = bookId;
            document.getElementById('borrowBookTitle').textContent = title;
            document.getElementById('borrowBookAuthor').textContent = 'by ' + author;
            const currentSection = '<?php echo $section; ?>';
            document.getElementById('borrowFormSection').value = currentSection === 'requests' ? 'requests' : 'search';
            openModal('borrowModal');
        }
        function openRequestForm(bookId, title, author, type) {
            window.location.href = 'student_dashboard.php?section=request_form&book_id=' + bookId + '&type=' + type;
        }

        function switchProfileTab(tabId) {
            document.querySelectorAll('.profile-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.profile-tab-pane').forEach(pane => pane.classList.remove('active'));
            document.querySelector(`.profile-tab-btn[onclick="switchProfileTab('${tabId}')"]`).classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
        }

        function previewStudentPhoto(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (file.size > 2*1024*1024) { alert('Photo must be under 2MB.'); event.target.value=''; return; }
            const reader = new FileReader();
            reader.onload = e => { document.getElementById('studentPhotoPreview').innerHTML = '<img src="'+e.target.result+'" alt="Preview">'; };
            reader.readAsDataURL(file);
        }
        function removeStudentPhoto() {
            if (!confirm('Remove your profile photo?')) return;
            const f = document.createElement('form'); f.method = 'post';
            f.innerHTML = '<input type="hidden" name="student_profile_action" value="update_photo"><input type="hidden" name="remove_photo" value="1">';
            document.body.appendChild(f); f.submit();
        }

        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila' });
            const dateString = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'Asia/Manila' });
            const timeEl = document.getElementById('currentTime');
            const dateEl = document.getElementById('currentDateDisplay');
            if (timeEl) timeEl.textContent = timeString;
            if (dateEl) dateEl.textContent = dateString;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Notification filtering
        function filterNotifications(filter) {
            document.querySelectorAll('.notif-filter-btn').forEach(btn => btn.classList.remove('active'));
            const targetBtn = document.querySelector(`.notif-filter-btn[data-filter="${filter}"]`);
            if (targetBtn) targetBtn.classList.add('active');
            
            document.querySelectorAll('#notificationList .notification-card').forEach(card => {
                const status = card.getAttribute('data-status');
                if (filter === 'all' || filter === status) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // ============================================================
        // SEARCH (FIXED)
        // ============================================================
        let predictionTimeout = null;
        let searchSession = { queries: [], clicks: [], abandoned: [] };

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }
        function getPlaceholderColor(id) {
            const colors = ['#2a2a2a', '#4a4a4a', '#6a6a6a', '#8a8a8a', '#aaaaaa', '#cacaca'];
            let hash = 0;
            const s = String(id);
            for (let i = 0; i < s.length; i++) { hash = ((hash << 5) - hash) + s.charCodeAt(i); hash |= 0; }
            return colors[Math.abs(hash) % colors.length];
        }

        // Local, dependency-free search (mirrors ai_functions basicSearch but with bilingual hint awareness)
        function basicLocalSearch(query, books) {
            if (!query || !Array.isArray(books)) return [];
            let q = query.toLowerCase().trim();
            // If aiFunctions is available and bilingual translation exists, expand to English too
            let expanded = q;
            try {
                if (window.aiFunctions && typeof window.aiFunctions.tagalogToEnglish === 'function') {
                    const t = window.aiFunctions.tagalogToEnglish(query);
                    if (t && t.isTagalog) expanded = (q + ' ' + t.translated).toLowerCase();
                }
            } catch (_) {}
            const tokens = expanded.split(/\s+/).filter(w => w.length > 1);
            return books.map(b => {
                const title = (b.title || '').toLowerCase();
                const author = (b.author || '').toLowerCase();
                const desc = (b.description || '').toLowerCase();
                const kw = (b.keywords || '').toLowerCase();
                const cat = ((b.categories && b.categories.name) || b.category || '').toLowerCase();
                let score = 0;
                if (title.includes(q)) score += 60;
                if (author.includes(q)) score += 40;
                if (cat.includes(q)) score += 25;
                if (kw.includes(q)) score += 20;
                if (desc.includes(q)) score += 10;
                tokens.forEach(t => {
                    if (title.includes(t)) score += 12;
                    if (author.includes(t)) score += 6;
                    if (cat.includes(t)) score += 5;
                    if (kw.includes(t)) score += 4;
                    if (desc.includes(t)) score += 2;
                });
                return { ...b, relevance: Math.min(100, score) };
            }).filter(b => b.relevance > 0).sort((a, b) => b.relevance - a.relevance);
        }

        async function performSearch() {
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return;
            const query = searchInput.value.trim();
            if (!query) { window.location.href = 'student_dashboard.php?section=search'; return; }

            const grid = document.getElementById('bookGrid');
            const badge = document.getElementById('searchTypeBadge');
            const aiStatus = document.getElementById('aiStatus');

            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;"><div style="width:40px;height:40px;border:3px solid #f0edea;border-top-color:#1a1a1a;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div><p>Searching...</p></div>';
            badge.style.display = 'inline-block';
            badge.textContent = '🧠 Searching...';
            if (aiStatus) aiStatus.textContent = '🤖 Connecting to AI...';

            try {
                // 1) Try to bring AI online (awaits up to 8s)
                let ready = false;
                if (window.aiFunctions && typeof window.aiFunctions.waitForFirebase === 'function') {
                    ready = await window.aiFunctions.waitForFirebase(8000);
                } else if (window.firebaseServices) {
                    try {
                        await window.firebaseServices.initialize();
                        ready = !!window.firebaseServices.isReady;
                    } catch (e) { ready = false; }
                }

                let results = [];
                let usedAI = false;

                // 2) If AI ready, run semantic search
                if (ready && window.aiFunctions && window.aiFunctions.semanticSearch) {
                    results = await window.aiFunctions.semanticSearch(query, allBooks, 20) || [];
                    usedAI = true;
                    if (aiStatus) aiStatus.innerHTML = '<span style="color:#34a853;">●</span> AI Online';
                }

                // 3) Fallback to bilingual local search if AI returned nothing
                if (!results.length) {
                    if (window.aiFunctions && typeof window.aiFunctions.basicSearch === 'function') {
                        results = window.aiFunctions.basicSearch(query, allBooks) || [];
                    } else {
                        results = basicLocalSearch(query, allBooks);
                    }
                    if (usedAI) { usedAI = false; if (aiStatus) aiStatus.textContent = '📝 Basic Search'; }
                    else if (aiStatus && !ready) aiStatus.textContent = '📝 Basic Search (AI offline)';
                }

                badge.textContent = usedAI ? '🧠 AI Search' : '📝 Basic';
                renderSearchResults(results);
                document.getElementById('resultCount').textContent = results.length;

                searchSession.queries.push(query);
                searchSession.clicks.push(0);

                // 4) Frustration analysis (optional, non-blocking)
                if (searchSession.queries.length >= 3 && searchSession.queries.length % 3 === 0 && ready &&
                    window.aiFunctions && window.aiFunctions.analyzeSession) {
                    try {
                        const analysis = await window.aiFunctions.analyzeSession(
                            searchSession.queries, searchSession.clicks, searchSession.abandoned
                        );
                        if (analysis && analysis.frustration_detected && analysis.frustration_score > 50) {
                            showFrustrationPopup(analysis);
                        }
                    } catch (e) { console.warn(e); }
                }
            } catch (error) {
                console.error('Search error:', error);
                const results = basicLocalSearch(query, allBooks);
                renderSearchResults(results);
                document.getElementById('resultCount').textContent = results.length;
                badge.textContent = '📝 Basic';
                if (aiStatus) aiStatus.textContent = '📝 Basic Search';
            }
        }

        function renderSearchResults(books) {
            const grid = document.getElementById('bookGrid');
            if (!grid) return;
            if (!books.length) {
                grid.innerHTML = '<div style="grid-column:1/-1;background:#fff;border-radius:16px;padding:60px 40px;text-align:center;border:1px solid #e8e0d8;"><h3>No books found</h3><p style="margin-top:8px;">Try different keywords</p></div>';
                return;
            }
            grid.innerHTML = books.map(book => {
                const title = book.title || 'Unknown';
                const author = book.author || 'Unknown';
                const category = (book.categories && book.categories.name) || book.category || 'Uncategorized';
                const available = book.available || 0;
                const relevance = Math.round(book.relevance || 0);
                const bookId = book.id;
                const coverImage = book.cover_image || '';
                const hasCover = coverImage && coverImage.length > 100;
                return `
                    <div class="book-card" data-book-id="${bookId}">
                        <div class="book-cover-wrapper">
                            ${hasCover ? `<img src="${coverImage}" alt="${escapeHtml(title)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">` : ''}
                            <div class="cover-placeholder" style="${hasCover ? 'display:none;' : ''}background-color:${getPlaceholderColor(bookId)};">
                                <span class="initial">${title.charAt(0).toUpperCase()}</span>
                            </div>
                            <span class="availability-badge ${available <= 0 ? 'none' : (available <= 2 ? 'low' : '')}">${available <= 0 ? 'Not Available' : (available <= 2 ? 'Low Stock' : 'Available')}</span>
                        </div>
                        <div class="book-info">
                            <h3 class="book-title">${escapeHtml(title)}</h3>
                            <p class="book-author">by ${escapeHtml(author)}</p>
                            <span class="book-category">${escapeHtml(category)}</span>
                            ${relevance > 0 ? `<span class="relevance-score">AI Match: ${relevance}%</span>` : ''}
                            <div class="book-meta">
                                <span class="availability"><strong>${available}</strong> / ${book.quantity || 0} available</span>
                                <div class="book-actions">
                                    ${available > 0 ? `<button class="btn-request" onclick="openRequestForm('${bookId}', '${escapeHtml(title).replace(/'/g, "\\'")}', '${escapeHtml(author).replace(/'/g, "\\'")}', 'borrow')">Request</button>` : `<button class="btn-reserve" onclick="openRequestForm('${bookId}', '${escapeHtml(title).replace(/'/g, "\\'")}', '${escapeHtml(author).replace(/'/g, "\\'")}', 'reserve')">Reserve</button>`}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function quickSearch(query) {
            document.getElementById('searchInput').value = query;
            performSearch();
        }
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            document.getElementById('clearSearchBtn').classList.remove('visible');
            window.location.href = 'student_dashboard.php?section=search';
        }

        // ============================================================
        // PREDICTIVE SUGGESTIONS (local — no missing /api endpoint)
        // ============================================================
        let predictionRequestId = 0;
        async function getPredictions(query) {
            const container = document.getElementById('predictiveResults');
            if (!container || query.trim().length < 2) return;
            const requestId = ++predictionRequestId;
            container.innerHTML = '<div class="loading-predictions"><div class="spinner"></div> Checking library catalog...</div>';
            container.classList.add('visible');

            const q = query.toLowerCase().trim();
            // Expand with bilingual translation when available
            let expanded = q;
            try {
                if (window.aiFunctions && typeof window.aiFunctions.tagalogToEnglish === 'function') {
                    const t = window.aiFunctions.tagalogToEnglish(query);
                    if (t && t.isTagalog) expanded = (q + ' ' + t.translated).toLowerCase();
                }
            } catch (_) {}

            const tokens = expanded.split(/\s+/).filter(w => w.length >= 2);

            const scored = allBooks.map(b => {
                const title = (b.title || '').toLowerCase();
                const author = (b.author || '').toLowerCase();
                const kw = (b.keywords || '').toLowerCase();
                const cat = ((b.categories && b.categories.name) || '').toLowerCase();
                let score = 0;
                if (title.includes(q)) score += 60;
                if (author.includes(q)) score += 30;
                if (kw.includes(q)) score += 20;
                if (cat.includes(q)) score += 15;
                tokens.forEach(t => {
                    if (title.includes(t)) score += 10;
                    if (author.includes(t)) score += 6;
                    if (kw.includes(t)) score += 4;
                    if (cat.includes(t)) score += 3;
                });
                return { book: b, score };
            }).filter(x => x.score > 0)
              .sort((a, b) => b.score - a.score)
              .slice(0, 8)
              .map(x => x.book);

            if (requestId !== predictionRequestId) return;
            container.replaceChildren();

            if (!scored.length) {
                container.innerHTML = '<div style="padding:16px;text-align:center;">No matching books in the library catalog.</div>';
                return;
            }

            scored.forEach(book => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'predictive-item';
                const available = Number(book.available || 0);
                const availabilityHtml = available > 0
                    ? `<span class="pred-availability">${available} available</span>`
                    : `<span class="pred-availability unavailable">Unavailable</span>`;
                item.innerHTML = `
                    <div class="pred-title">${escapeHtml(book.title || 'Untitled')}</div>
                    <div class="pred-author">by ${escapeHtml(book.author || 'Unknown author')}</div>
                    <div class="pred-meta">${availabilityHtml}${book.isbn ? `<span>ISBN ${escapeHtml(book.isbn)}</span>` : ''}</div>
                `;
                item.addEventListener('click', () => selectPrediction(book.title || ''));
                container.appendChild(item);
            });
        }

        function selectPrediction(title) {
            const input = document.getElementById('searchInput');
            if (input) input.value = title;
            hidePredictions();
            performSearch();
        }
        function hidePredictions() {
            predictionRequestId++;
            const container = document.getElementById('predictiveResults');
            if (container) { container.classList.remove('visible'); container.innerHTML = ''; }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const clearBtn = document.getElementById('clearSearchBtn');
            if (!searchInput) return;

            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                if (query.length > 0) clearBtn.classList.add('visible');
                else { clearBtn.classList.remove('visible'); hidePredictions(); }

                if (query.length >= 2) {
                    clearTimeout(predictionTimeout);
                    predictionTimeout = setTimeout(() => getPredictions(query), 300);
                } else {
                    hidePredictions();
                }
            });
        });

        document.addEventListener('click', function(e) {
            const container = document.getElementById('predictiveResults');
            if (container && !container.contains(e.target)) {
                const searchInput = document.getElementById('searchInput');
                if (searchInput && !searchInput.contains(e.target)) hidePredictions();
            }
            const bookCard = e.target.closest('.book-card');
            if (bookCard && searchSession.clicks.length > 0) {
                searchSession.clicks[searchSession.clicks.length - 1]++;
            }
        });

        function showFrustrationPopup(analysis) {
            let popup = document.getElementById('aiAssistantPopup');
            if (!popup) {
                popup = document.createElement('div');
                popup.id = 'aiAssistantPopup';
                popup.className = 'ai-assistant-popup';
                document.body.appendChild(popup);
            }
            popup.innerHTML = `
                <div class="popup-header"><h3>🤖 Research Assistant</h3><button class="close-popup" onclick="closeFrustrationPopup()">×</button></div>
                <div class="popup-body">
                    <p><strong>It looks like you're having some difficulty with your search.</strong></p>
                    <ul>${(analysis.reasons || []).map(r => `<li>${escapeHtml(r)}</li>`).join('')}</ul>
                    <p>${escapeHtml((analysis.suggestions || [])[0] || 'Try using simpler keywords.')}</p>
                    <div class="popup-actions">
                        <button class="btn-help" onclick="closeFrustrationPopup()">📅 Schedule Consultation</button>
                        <button class="btn-dismiss" onclick="closeFrustrationPopup()">Dismiss</button>
                    </div>
                </div>
            `;
            popup.classList.add('visible');
        }
        function closeFrustrationPopup() {
            const popup = document.getElementById('aiAssistantPopup');
            if (popup) popup.classList.remove('visible');
        }

        // ============================================================
        // AI WARM-UP (kick off Firebase so first search is fast)
        // ============================================================
        (function warmUpAI() {
            const aiStatus = document.getElementById('aiStatus');
            if (!window.firebaseServices) {
                if (aiStatus) aiStatus.textContent = '📝 Basic Search (AI not loaded)';
                return;
            }
            const init = (window.aiFunctions && typeof window.aiFunctions.waitForFirebase === 'function')
                ? window.aiFunctions.waitForFirebase(8000)
                : window.firebaseServices.initialize().then(() => !!window.firebaseServices.isReady).catch(() => false);

            init.then(ready => {
                if (aiStatus) {
                    if (ready) aiStatus.innerHTML = '<span style="color:#34a853;">●</span> AI Ready';
                    else aiStatus.textContent = '📝 Basic Search (AI offline)';
                }
            }).catch(err => {
                console.warn('AI warm-up failed:', err);
                if (aiStatus) aiStatus.textContent = '📝 Basic Search';
            });
        })();
    </script>

<script>
(function(){
 const noticeModal=document.getElementById('studentNotificationsModal');
 const openBtns = document.querySelectorAll('[data-notifications-open]');
 openBtns.forEach(btn => btn.addEventListener('click', () => noticeModal?.classList.add('open')));
 document.querySelectorAll('[data-notifications-close]').forEach(btn => {
     btn.addEventListener('click', () => noticeModal?.classList.remove('open'));
 });
 noticeModal?.addEventListener('click',e=>{if(e.target===noticeModal)noticeModal.classList.remove('open')});
 document.addEventListener('keydown', e => { if (e.key === 'Escape' && noticeModal?.classList.contains('open')) { noticeModal.classList.remove('open'); } });

 const body = document.body;
 const toggle = document.querySelector('[data-theme-toggle]');
 const icon = toggle?.querySelector('.action-icon');
 const label = toggle?.querySelector('.action-label');
 let saved = 'light'; try { saved = localStorage.getItem('library-dashboard-theme') || 'light'; } catch(e){}
 function applyTheme(theme, animate) {
   if (animate) {
     const r = toggle?.getBoundingClientRect();
     if (r) { body.style.setProperty('--theme-x', ((r.left + r.width/2)/innerWidth*100)+'%'); body.style.setProperty('--theme-y', ((r.top + r.height/2)/innerHeight*100)+'%'); }
     body.classList.remove('theme-transition'); void body.offsetWidth; body.classList.add('theme-transition');
     setTimeout(()=>body.classList.remove('theme-transition'), 650);
   }
   body.setAttribute('data-theme', theme);
   body.classList.toggle('dark-mode', theme === 'dark');
   body.classList.toggle('light-mode', theme === 'light');
   if (toggle) { toggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false'); toggle.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'); }
   if (icon) icon.textContent = theme === 'dark' ? '☀' : '☾';
   if (label) label.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
   try { localStorage.setItem('library-dashboard-theme', theme); } catch(e){}
 }
 applyTheme(saved, false);
 toggle?.addEventListener('click', () => applyTheme(body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark', true));
})();
</script>
</body>
</html>