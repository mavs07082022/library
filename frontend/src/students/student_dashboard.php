<?php
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
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

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("API Error: " . $response);
    }

    return json_decode($response, true);
}

$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$userId = $_SESSION['user_id'];

$books = [];
$borrowings = [];
$fines = [];
$reservations = [];
$notifications = [];
$unreadNotifications = [];
$studentData = [];
$accountStatus = 'Good Standing';
$hasOverdue = false;
$hasUnpaidFines = false;
$totalPendingFines = 0;
$pendingFines = [];
$isRestricted = false;
$notificationCount = 0;
$studentRequests = [];
$pendingRequests = [];
$studentSubjects = [];
$userSearchHistory = [];

try {
    $borrowings = supabaseRequest('borrowings?select=*,books(title,author,id,cover_image)&user_id=eq.' . $userId . '&order=borrow_date.desc');
    $fines = supabaseRequest('fines?select=*&user_id=eq.' . $userId);
    $reservations = supabaseRequest('reservations?select=*,books(title,author,id,cover_image,available)&user_id=eq.' . $userId . '&order=reservation_date.desc');
    
    try {
        $notifications = supabaseRequest('notifications?select=*&user_id=eq.' . $userId . '&order=created_at.desc&limit=50');
        $unreadNotifications = array_filter($notifications, function($n) {
            return !($n['is_read'] ?? false);
        });
        $notificationCount = count($unreadNotifications);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'PGRST205') !== false) {
            $notifications = [];
            $unreadNotifications = [];
            $notificationCount = 0;
        } else {
            throw $e;
        }
    }
    
    $student = supabaseRequest('students?select=*&user_id=eq.' . $userId);
    $studentData = !empty($student) ? $student[0] : [];
    
    try {
        $studentRequests = supabaseRequest('book_requests?select=*,books(title,author)&user_id=eq.' . $userId . '&order=created_at.desc');
        $pendingRequests = array_filter($studentRequests, function($r) {
            return ($r['status'] ?? '') === 'Pending';
        });
    } catch (Exception $e) {
        $studentRequests = [];
        $pendingRequests = [];
    }
    
    try {
        $studentSubjects = supabaseRequest('student_subjects?select=subject_name&user_id=eq.' . $userId);
    } catch (Exception $e) {
        $studentSubjects = [];
    }
    
    try {
        $userSearchHistory = supabaseRequest('user_search_history?select=query&user_id=eq.' . $userId . '&order=created_at.desc&limit=10');
    } catch (Exception $e) {
        $userSearchHistory = [];
    }
    
    $hasOverdue = !empty(array_filter($borrowings, function($b) {
        return ($b['status'] ?? '') === 'Overdue';
    }));
    $pendingFines = array_filter($fines, function($f) {
        return ($f['status'] ?? '') !== 'Paid' && ($f['status'] ?? '') !== 'Waived';
    });
    $hasUnpaidFines = !empty($pendingFines);
    $totalPendingFines = array_sum(array_column($pendingFines, 'amount'));
    
    if ($hasOverdue || $hasUnpaidFines) {
        $accountStatus = 'Restricted';
        $isRestricted = true;
    } elseif (!empty(array_filter($borrowings, function($b) {
        return ($b['status'] ?? '') !== 'Returned';
    }))) {
        $accountStatus = 'Active';
        $isRestricted = false;
    } else {
        $accountStatus = 'Good Standing';
        $isRestricted = false;
    }

} catch (Exception $e) {
    $message = 'Error loading data: ' . $e->getMessage();
}

// ===== REQUEST FORM HANDLING =====
if ($section === 'request_form' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId = $_POST['book_id'] ?? '';
    $requestType = $_POST['request_type'] ?? '';
    // Use session data for credentials (fixed)
    $fullName = $_SESSION['full_name'] ?? '';
    $studentId = $studentData['student_id'] ?? $_SESSION['user_id_display'] ?? '';
    $yearLevel = $studentData['year_level'] ?? '';
    $reqSection = $studentData['section'] ?? '';
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
            if (!empty($existing)) {
                header('Location: student_dashboard.php?section=search&msg=You already have a pending request for this book.');
                exit;
            }
            
            $requestData = [
                'user_id' => $userId,
                'book_id' => $bookId,
                'request_type' => $requestType,
                'student_id' => $studentId,
                'full_name' => $fullName,
                'year_level' => $yearLevel,
                'section' => $reqSection,
                'purpose' => $purpose,
                'status' => 'Pending'
            ];
            
            supabaseRequest('book_requests', 'POST', $requestData);
            
            try {
                $bookData = supabaseRequest('books?select=title&id=eq.' . $bookId);
                $bookTitle = !empty($bookData) ? $bookData[0]['title'] : 'Book';
                
                $admins = supabaseRequest('users?select=id&role=eq.admin');
                foreach ($admins as $admin) {
                    $notifData = [
                        'user_id' => $admin['id'],
                        'title' => 'New Book Request',
                        'message' => $fullName . ' has requested to ' . $requestType . ' "' . $bookTitle . '"',
                        'type' => 'request',
                        'icon' => '📋',
                        'is_read' => false,
                        'action_url' => 'admin_dashboard.php?section=requests',
                        'action_label' => 'View Request'
                    ];
                    supabaseRequest('notifications', 'POST', $notifData);
                }
            } catch (Exception $e) {
                error_log('Failed to create admin notification: ' . $e->getMessage());
            }
            
            header('Location: student_dashboard.php?section=requests&msg=Your request has been submitted. Please wait for verification.');
            exit;
        } catch (Exception $e) {
            header('Location: student_dashboard.php?section=request_form&book_id=' . $bookId . '&type=' . $requestType . '&msg=Error submitting request: ' . $e->getMessage());
            exit;
        }
    } else {
        header('Location: student_dashboard.php?section=request_form&book_id=' . $bookId . '&type=' . $requestType . '&msg=' . urlencode(implode(' ', $errors)));
        exit;
    }
}

// ===== BORROW HANDLING =====
if ($section === 'search' && $action === 'borrow' && isset($_GET['book_id'])) {
    $bookId = $_GET['book_id'];
    
    if ($isRestricted) {
        header('Location: student_dashboard.php?section=search&msg=Your account is restricted. Please settle your fines and return overdue books first.');
        exit;
    }
    
    try {
        $approvedRequests = supabaseRequest('book_requests?select=id&user_id=eq.' . $userId . '&book_id=eq.' . $bookId . '&status=eq.Approved&request_type=eq.borrow');
        if (empty($approvedRequests)) {
            header('Location: student_dashboard.php?section=request_form&book_id=' . $bookId . '&type=borrow&msg=Please submit a request form first.');
            exit;
        }
    } catch (Exception $e) {
        header('Location: student_dashboard.php?section=search&msg=Error checking request status.');
        exit;
    }
    
    try {
        $bookCheck = supabaseRequest('books?select=available,id,title&id=eq.' . $bookId);
        if (empty($bookCheck) || ($bookCheck[0]['available'] ?? 0) <= 0) {
            header('Location: student_dashboard.php?section=search&msg=Book not available');
            exit;
        }

        $existing = supabaseRequest('borrowings?select=id&user_id=eq.' . $userId . '&book_id=eq.' . $bookId . '&status=neq.Returned');
        if (!empty($existing)) {
            header('Location: student_dashboard.php?section=search&msg=You already borrowed this book');
            exit;
        }

        $borrowData = [
            'book_id' => $bookId,
            'user_id' => $userId,
            'borrow_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+14 days')),
            'status' => 'Borrowed'
        ];
        supabaseRequest('borrowings', 'POST', $borrowData);
        supabaseRequest('books?id=eq.' . $bookId, 'PATCH', ['available' => ($bookCheck[0]['available'] - 1)]);
        
        if (!empty($approvedRequests)) {
            supabaseRequest('book_requests?id=eq.' . $approvedRequests[0]['id'], 'PATCH', ['status' => 'Fulfilled']);
        }

        header('Location: student_dashboard.php?section=borrowings&msg=Book borrowed successfully!');
    } catch (Exception $e) {
        header('Location: student_dashboard.php?section=search&msg=Error borrowing book: ' . $e->getMessage());
    }
    exit;
}

// ===== RESERVE HANDLING =====
if ($section === 'search' && $action === 'reserve' && isset($_GET['book_id'])) {
    $bookId = $_GET['book_id'];
    
    if ($isRestricted) {
        header('Location: student_dashboard.php?section=search&msg=Your account is restricted.');
        exit;
    }
    
    try {
        $approvedRequests = supabaseRequest('book_requests?select=id&user_id=eq.' . $userId . '&book_id=eq.' . $bookId . '&status=eq.Approved&request_type=eq.reserve');
        if (empty($approvedRequests)) {
            header('Location: student_dashboard.php?section=request_form&book_id=' . $bookId . '&type=reserve&msg=Please submit a request form first.');
            exit;
        }
    } catch (Exception $e) {
        header('Location: student_dashboard.php?section=search&msg=Error checking request status.');
        exit;
    }
    
    try {
        $bookCheck = supabaseRequest('books?select=available,id,title&id=eq.' . $bookId);
        if (empty($bookCheck)) {
            header('Location: student_dashboard.php?section=search&msg=Book not found');
            exit;
        }

        $existingReservation = supabaseRequest('reservations?select=id&user_id=eq.' . $userId . '&book_id=eq.' . $bookId . '&status=eq.Pending');
        if (!empty($existingReservation)) {
            header('Location: student_dashboard.php?section=search&msg=You already have a pending reservation for this book');
            exit;
        }

        $reservationData = [
            'book_id' => $bookId,
            'user_id' => $userId,
            'reservation_date' => date('Y-m-d'),
            'expiry_date' => date('Y-m-d', strtotime('+3 days')),
            'status' => 'Pending',
            'notes' => 'Reserved by student'
        ];
        supabaseRequest('reservations', 'POST', $reservationData);
        
        if (!empty($approvedRequests)) {
            supabaseRequest('book_requests?id=eq.' . $approvedRequests[0]['id'], 'PATCH', ['status' => 'Fulfilled']);
        }

        header('Location: student_dashboard.php?section=reservations&msg=Book reserved successfully!');
    } catch (Exception $e) {
        header('Location: student_dashboard.php?section=search&msg=Error reserving book: ' . $e->getMessage());
    }
    exit;
}

// ===== VARIABLES FOR REQUEST FORM PAGE =====
$requestBookId = isset($_GET['book_id']) ? $_GET['book_id'] : '';
$requestType = isset($_GET['type']) ? $_GET['type'] : 'borrow';
$requestMessage = isset($_GET['msg']) ? $_GET['msg'] : '';
$requestBookData = [];

if ($section === 'request_form' && $requestBookId) {
    try {
        $bookData = supabaseRequest('books?select=*,categories(name)&id=eq.' . $requestBookId);
        if (!empty($bookData)) {
            $requestBookData = $bookData[0];
        }
    } catch (Exception $e) {}
}

$allBooks = [];
$searchQuery = isset($_GET['q']) ? $_GET['q'] : '';
$message = isset($_GET['msg']) ? $_GET['msg'] : '';

try {
    $allBooks = supabaseRequest('books?select=*,categories(name)');
    $books = $allBooks;
} catch (Exception $e) {
    $message = 'Error loading books: ' . $e->getMessage();
}

$activeBorrowings = array_filter($borrowings, function($b) {
    return ($b['status'] ?? '') !== 'Returned';
});
$overdueBorrowings = array_filter($borrowings, function($b) {
    return ($b['status'] ?? '') === 'Overdue';
});
$totalFines = array_sum(array_column($fines, 'amount'));

function getPlaceholderColor($id) {
    $colors = ['#2a2a2a', '#4a4a4a', '#6a6a6a', '#8a8a8a', '#aaaaaa', '#cacaca', '#eaeaea', '#fafafa'];
    $hash = crc32($id);
    if ($hash < 0) $hash = -$hash;
    return $colors[$hash % count($colors)];
}

function hasValidCoverImage($coverImage) {
    if (empty($coverImage)) return false;
    if (strpos($coverImage, 'data:image') === 0) return strlen($coverImage) > 100;
    if (filter_var($coverImage, FILTER_VALIDATE_URL)) return true;
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Student Dashboard - St. Agnes Academy</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { -webkit-text-size-adjust: 100%; }
        html, body { overflow-x: hidden; width: 100%; max-width: 100vw; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #f5f3f0; color: #1a1a1a; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f0edea; }
        ::-webkit-scrollbar-thumb { background: #d4c9c0; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #b8a89c; }

        /* ===== TOP HEADER NAVIGATION (SYMBOLS ONLY) ===== */
        .top-header {
            position: fixed;
            top: 0;
            left: 240px;
            right: 0;
            height: 56px;
            background: #010107;
            border-bottom: 1px solid #2a2a2a;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1100;
            transition: left 0.25s ease;
        }
        .top-header.collapsed { left: 70px; }
        .header-left-group { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .hamburger-btn {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .hamburger-btn:hover { background: rgba(229, 29, 102, 0.18); border-color: rgba(229, 29, 102, 0.3); }
        .hamburger-lines { display: flex; flex-direction: column; gap: 4px; width: 18px; }
        .hamburger-lines span { display: block; height: 2px; width: 100%; background: #e8e0d8; border-radius: 2px; transition: 0.3s; }
        .header-title-symbol { color: #f0e8e0; font-size: 17px; opacity: 0.85; }
        .header-nav-symbols { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
        .header-nav-symbols a {
            display: flex; align-items: center; justify-content: center;
            width: 38px; height: 38px;
            border-radius: 8px;
            color: #8a7a6e;
            text-decoration: none;
            font-size: 17px;
            transition: all 0.2s ease;
            position: relative;
        }
        .header-nav-symbols a:hover { color: #f0e8e0; background: rgba(255,255,255,0.05); }
        .header-nav-symbols a.active { color: #f0e8e0; background: rgba(229, 29, 102, 0.18); }
        .header-nav-symbols a .header-badge {
            position: absolute; top: 3px; right: 3px;
            background: #e51d66; color: #fff;
            font-size: 9px; font-weight: 700;
            min-width: 15px; height: 15px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            padding: 0 4px;
        }

        .student-app { display: flex; min-height: 100vh; }
        .student-sidebar {
            width: 240px;
            background: #010107;
            color: #e8e0d8;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1050;
            border-right: 1px solid #2a2a2a;
            transition: width 0.25s ease, transform 0.3s ease;
        }
        .student-sidebar.collapsed { width: 70px; }
        .student-sidebar.collapsed .sidebar-header h2,
        .student-sidebar.collapsed .sidebar-header p,
        .student-sidebar.collapsed .sidebar-header small,
        .student-sidebar.collapsed .sidebar-header .subtitle,
        .student-sidebar.collapsed .sidebar-nav a .nav-label { display: none; }
        .student-sidebar.collapsed .sidebar-nav a { justify-content: center; padding: 14px; font-size: 20px; }
        .student-sidebar.collapsed .sidebar-nav a .nav-icon { font-size: 22px; }
        .student-sidebar.collapsed .sidebar-header { padding: 16px 8px; text-align: center; }
        .student-sidebar.collapsed .sidebar-footer { padding: 16px 12px 24px; }
        .student-sidebar.collapsed .logout-btn { padding: 10px 0; font-size: 16px; }
        .student-sidebar.collapsed .logout-btn .logout-text { display: none; }
        .student-sidebar.collapsed .notification-badge { position: absolute; top: 6px; right: 6px; margin: 0; }
        .student-sidebar.collapsed .sidebar-logo-wrapper { justify-content: center; }
        .student-sidebar.collapsed .sidebar-header .sidebar-logo {
            max-width: 40px; width: 40px; margin: 0 auto;
        }

        .sidebar-header { padding: 28px 24px 20px; border-bottom: 1px solid #2a2a2a; text-align: left; transition: padding 0.25s ease; }
        .sidebar-logo-wrapper {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            transition: justify-content 0.25s ease;
        }
        .sidebar-header .sidebar-logo {
            max-width: 72px;
            width: 72px;
            height: auto;
            display: block;
            margin-bottom: 12px;
            transition: max-width 0.3s ease, width 0.3s ease, margin 0.3s ease;
        }
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
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; padding: 24px 32px; background: linear-gradient(135deg, #08080a 0%, #0a090a 30%, #e51d66c9 60%, #e51d66c9 80%, #e51d66c9 100%); border-radius: 16px; position: relative; overflow: hidden; }
        .dashboard-header::after { content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(212, 160, 160, 0.15) 0%, transparent 70%); border-radius: 50%; }
        .dashboard-header .header-left { position: relative; z-index: 1; min-width: 0; }
        .dashboard-header h1 { font-size: 22px; color: #f0e8e0; margin: 0; font-weight: 600; letter-spacing: -0.5px; word-break: break-word; }
        .header-date { color: #8a7a6e; font-size: 14px; margin: 4px 0 0; word-break: break-word; }
        .header-time { text-align: right; background: rgba(255,255,255,0.06); padding: 8px 20px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.06); position: relative; z-index: 1; flex-shrink: 0; }
        .header-time .time { font-size: 20px; font-weight: 600; color: #0a0a0a; display: block; letter-spacing: 0.5px; }
        .header-time .date { font-size: 12px; color: #0a0a0a; letter-spacing: 0.3px; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 28px; }
        .stat-card { background: #ffffff; padding: 16px 18px; border-radius: 12px; border: 1px solid #e8e0d8; text-align: center; transition: all 0.2s ease; min-width: 0; overflow: hidden; }
        .stat-card:hover { border-color: #d4c9c0; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .stat-number { font-size: 24px; font-weight: 700; color: #1a1a1a; letter-spacing: -0.5px; word-break: break-word; }
        .stat-label { font-size: 12px; color: #6a5a4e; margin-top: 2px; font-weight: 400; word-break: break-word; }
        .stat-sub { font-size: 10px; color: #9a8a7e; margin-top: 2px; }

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
        .action-label { font-size: 13px; color: #4a3a2e; font-weight: 500; word-break: break-word; }
        .action-badge { position: absolute; top: 4px; right: 4px; background: #e51d66; color: #fff; font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; border-radius: 9px; display: flex; align-items: center; justify-content: center; padding: 0 5px; }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .section-header h1 { font-size: 22px; color: #1a1a1a; margin: 0; font-weight: 600; letter-spacing: -0.5px; word-break: break-word; }

        .search-bar { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .search-bar .search-input-wrapper { flex: 1; position: relative; min-width: 200px; }
        .search-bar .search-input-wrapper input { width: 100%; padding: 14px 16px 14px 50px; border: 2px solid #e8e0d8; border-radius: 12px; font-size: 15px; transition: all 0.2s ease; background: #ffffff; color: #1a1a1a; }
        .search-bar .search-input-wrapper input:focus { border-color: #d4a0a0; outline: none; box-shadow: 0 0 0 3px rgba(212,160,160,0.12); }
        .search-bar .search-input-wrapper .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #9a8a7e; font-size: 18px; }
        .search-bar .search-input-wrapper .clear-btn { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9a8a7e; cursor: pointer; font-size: 20px; display: none; padding: 4px 8px; }
        .search-bar .search-input-wrapper .clear-btn.visible { display: block; }
        .search-bar .search-input-wrapper .clear-btn:hover { color: #4a3a2e; }

        .search-info { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .count-badge { color: #6a5a4e; font-size: 14px; white-space: nowrap; background: #ffffff; padding: 8px 16px; border-radius: 8px; border: 1px solid #e8e0d8; }
        .count-badge strong { color: #1a1a1a; }
        .search-type-badge { font-size: 12px; padding: 4px 14px; border-radius: 20px; font-weight: 500; background: #dde8e0; color: #1a4a3a; }
        .search-time { font-size: 12px; color: #b0a8a0; }

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
        .book-card .book-info .book-category { display: inline-block; background: #f0edea; color: #4a3a2e; padding: 2px 12px; border-radius: 12px; font-size: 12px; margin-bottom: 8px; align-self: flex-start; }
        .book-card .book-info .book-meta { display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid #f0edea; margin-top: auto; flex-wrap: wrap; gap: 8px; }
        .book-card .book-info .book-meta .availability { font-size: 14px; color: #6a5a4e; }
        .book-card .book-info .book-meta .availability strong { color: #1a1a1a; }
        .book-card .book-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .book-card .relevance-score { padding: 2px 12px; border-radius: 12px; font-size: 11px; background: #f0edea; color: #4a3a2e; align-self: flex-end; margin-top: 4px; }

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

        .profile-container { background: #ffffff; border-radius: 16px; padding: 32px 36px; max-width: 600px; border: 1px solid #e8e0d8; }
        .profile-avatar { width: 80px; height: 80px; border-radius: 50%; background: #1a1a1a; display: flex; align-items: center; justify-content: center; color: #f0e8e0; font-size: 32px; font-weight: 600; flex-shrink: 0; }
        .profile-field { padding: 8px 0; border-bottom: 1px solid #f0edea; }
        .profile-field:last-child { border-bottom: none; }
        .profile-field .label { font-weight: 600; color: #4a3a2e; font-size: 13px; }
        .profile-field .value { color: #1a1a1a; font-size: 14px; word-break: break-word; }

        .ai-assistant-popup { position: fixed; bottom: 30px; right: 30px; background: #ffffff; border-radius: 16px; padding: 24px 28px; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); border: 1px solid #e8e0d8; z-index: 9999; display: none; }
        .ai-assistant-popup.visible { display: block; }
        .ai-assistant-popup .popup-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .ai-assistant-popup .popup-header h3 { margin: 0; font-size: 16px; color: #1a1a1a; font-weight: 600; }
        .ai-assistant-popup .popup-header .close-popup { background: none; border: none; font-size: 22px; color: #9a8a7e; cursor: pointer; }
        .ai-assistant-popup .popup-body { color: #4a3a2e; font-size: 14px; line-height: 1.6; }
        .ai-assistant-popup .popup-body ul { margin: 8px 0 12px 20px; color: #6a5a4e; font-size: 13px; }
        .ai-assistant-popup .popup-actions { display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap; }
        .ai-assistant-popup .popup-actions .btn-help { padding: 8px 20px; background: #1a1a1a; color: #f0e8e0; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; text-decoration: none; }
        .ai-assistant-popup .popup-actions .btn-dismiss { padding: 8px 20px; background: #f0edea; color: #4a3a2e; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; }

        .predictive-results { position: absolute; top: 100%; left: 0; right: 0; background: #ffffff; border: 2px solid #e8e0d8; border-top: none; border-radius: 0 0 12px 12px; max-height: 400px; overflow-y: auto; z-index: 1000; display: none; box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
        .predictive-results.visible { display: block; }
        .predictive-item { padding: 12px 16px; border-bottom: 1px solid #f0edea; cursor: pointer; transition: background 0.2s ease; }
        .predictive-item:hover { background: #faf8f6; }
        .predictive-item .pred-title { font-weight: 500; color: #1a1a1a; font-size: 14px; }
        .predictive-item .pred-author { color: #6a5a4e; font-size: 13px; }
        .predictive-item .pred-score { float: right; font-size: 12px; color: #9a8a7e; }
        .predictive-item .pred-badge { display: inline-block; background: #dde8e0; color: #1a4a3a; font-size: 10px; padding: 2px 10px; border-radius: 10px; margin-left: 8px; }
        .loading-predictions { padding: 20px; text-align: center; color: #9a8a7e; }
        .loading-predictions .spinner { width: 24px; height: 24px; border: 3px solid #f0edea; border-top-color: #1a1a1a; border-radius: 50%; animation: spin 0.8s linear infinite; display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .restricted-warning { background: #f0ddd8; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; border-left: 4px solid #ea4335; }
        .restricted-warning .warning-title { font-weight: 600; color: #8a3a2a; font-size: 15px; }
        .restricted-warning .warning-text { color: #6a3a2a; font-size: 14px; margin-top: 4px; }
        .restricted-warning .fine-list { margin-top: 8px; padding-left: 20px; font-size: 13px; color: #6a3a2a; }

        /* ============================================
           RESPONSIVE BREAKPOINTS
           ============================================ */

        /* --- Large tablet --- */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }

        /* --- Tablet --- */
        @media (max-width: 992px) {
            .student-content { padding: 80px 24px 24px; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .book-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
            .quick-actions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        /* =========================================
           MOBILE / TABLET: Sidebar slides in below header
           ========================================= */
        @media (max-width: 900px) {
            .top-header { left: 0 !important; right: 0 !important; padding: 0 12px; height: 56px; z-index: 1100; }
            .top-header.collapsed { left: 0 !important; }
            .header-title-symbol { display: none; }

            .student-sidebar {
                top: 56px !important;
                left: 0;
                width: 280px !important;
                height: calc(100vh - 56px) !important;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                box-shadow: 6px 0 24px rgba(0,0,0,0.35);
                z-index: 1050;
                border-right: 1px solid #2a2a2a;
            }
            .student-sidebar.mobile-open { transform: translateX(0); }
            .student-sidebar.collapsed { width: 280px !important; }
            .student-sidebar.collapsed .sidebar-header h2,
            .student-sidebar.collapsed .sidebar-header p,
            .student-sidebar.collapsed .sidebar-header small,
            .student-sidebar.collapsed .sidebar-header .subtitle,
            .student-sidebar.collapsed .sidebar-nav a .nav-label { display: block !important; }
            .student-sidebar.collapsed .sidebar-nav a { justify-content: flex-start; padding: 12px 24px; font-size: 14px; }
            .student-sidebar.collapsed .sidebar-nav a .nav-icon { font-size: 18px; }
            .student-sidebar.collapsed .sidebar-header { padding: 20px 24px 16px; text-align: left; }
            .student-sidebar.collapsed .sidebar-logo-wrapper { justify-content: flex-start; }
            .student-sidebar.collapsed .sidebar-header .sidebar-logo { max-width: 56px; width: 56px; margin: 0 0 10px; }
            .student-sidebar.collapsed .sidebar-footer { padding: 16px 24px 24px; }
            .student-sidebar.collapsed .logout-btn .logout-text { display: inline; }
            .student-sidebar.collapsed .notification-badge { position: static; margin-left: auto; }
            .student-sidebar.collapsed .sidebar-nav a { position: relative; }

            .student-sidebar .sidebar-header { padding: 20px 24px 16px; }
            .student-sidebar .sidebar-header .sidebar-logo { max-width: 56px; width: 56px; margin-bottom: 10px; }

            .student-content { margin-left: 0 !important; padding: 74px 16px 24px; }
            .student-content.collapsed { margin-left: 0 !important; }

            .mobile-overlay { z-index: 1040; }

            .dashboard-header { flex-direction: column; align-items: flex-start; padding: 20px; gap: 12px; }
            .header-time { text-align: left; width: 100%; }

            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .quick-actions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        /* --- Small tablet / large phone --- */
        @media (max-width: 768px) {
            .top-header { padding: 0 10px; height: 54px; }
            .header-nav-symbols a { width: 34px; height: 34px; font-size: 15px; }
            .hamburger-btn { width: 36px; height: 36px; }

            .student-sidebar { top: 54px !important; height: calc(100vh - 54px) !important; }
            .student-content { padding: 70px 14px 24px; }

            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .stat-number { font-size: 20px; }
            .stat-label { font-size: 11px; }

            .dashboard-header h1 { font-size: 18px; }
            .header-time .time { font-size: 17px; }

            .quick-actions { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .section-header { flex-direction: column; align-items: flex-start; }
            .section-header h1 { font-size: 18px; }

            .status-card { flex-direction: column; align-items: flex-start; gap: 10px; }

            .request-form-container { padding: 24px 20px; }
            .profile-container { padding: 24px 20px; }
            .profile-avatar { width: 64px; height: 64px; font-size: 26px; }

            .modal-content { padding: 22px 18px; border-radius: 14px; }
            .modal-content .modal-header h2 { font-size: 17px; }

            .data-table th, .data-table td { padding: 10px 12px; font-size: 13px; }

            .book-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
            .book-card .book-cover-wrapper { height: 170px; }
        }

        /* --- Phone --- */
        @media (max-width: 480px) {
            .top-header { padding: 0 8px; height: 52px; }
            .header-left-group { gap: 8px; }
            .hamburger-btn { width: 34px; height: 34px; border-radius: 7px; }
            .hamburger-lines { width: 16px; gap: 3px; }
            .header-nav-symbols { gap: 1px; }
            .header-nav-symbols a { width: 32px; height: 32px; font-size: 14px; border-radius: 7px; }
            .header-nav-symbols a .header-badge { font-size: 8px; min-width: 13px; height: 13px; top: 2px; right: 2px; padding: 0 3px; }

            .student-sidebar { top: 52px !important; height: calc(100vh - 52px) !important; width: 270px !important; }
            .student-content { padding: 66px 10px 20px; }

            /* Force 2 columns with equal width */
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 8px; }
            .stat-card { padding: 12px 8px; border-radius: 10px; }
            .stat-number { font-size: 18px; line-height: 1.1; }
            .stat-label { font-size: 10px; margin-top: 4px; line-height: 1.2; }

            .dashboard-header { padding: 16px; border-radius: 12px; }
            .dashboard-header h1 { font-size: 16px; }
            .header-date { font-size: 12px; }
            .header-time { padding: 8px 14px; }
            .header-time .time { font-size: 16px; }
            .header-time .date { font-size: 11px; }

            .quick-actions { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 8px; }
            .quick-action-card { padding: 14px 8px; border-radius: 10px; }
            .action-icon { font-size: 20px; margin-bottom: 4px; }
            .action-label { font-size: 11px; line-height: 1.2; }

            .section-header h1 { font-size: 16px; }

            .message { padding: 11px 14px; font-size: 12px; border-radius: 8px; }

            .status-card { padding: 14px 16px; border-radius: 10px; }
            .status-info .status-title { font-size: 14px; }
            .status-info .status-desc { font-size: 12px; }
            .status-badge-large { padding: 3px 12px; font-size: 11px; }

            .book-grid { grid-template-columns: 1fr !important; gap: 14px; }
            .book-card .book-cover-wrapper { height: 220px; }
            .book-card .book-info { padding: 14px 16px 18px; }

            .search-bar { flex-direction: column; align-items: stretch; gap: 10px; }
            .search-bar .search-input-wrapper { min-width: 0; width: 100%; }
            .search-bar button { width: 100%; }

            .request-form-container { padding: 20px 16px; border-radius: 12px; }
            .request-form-container .form-actions { flex-direction: column-reverse; }
            .request-form-container .form-actions .btn-submit,
            .request-form-container .form-actions .btn-cancel { width: 100%; text-align: center; }

            .request-item { padding: 14px 16px; }
            .request-item .request-status { align-self: flex-start; }

            .modal-content { padding: 18px 14px; border-radius: 12px; }
            .modal-content .form-actions { flex-direction: column-reverse; }
            .modal-content .form-actions .btn-primary,
            .modal-content .form-actions .btn-secondary { width: 100%; }

            .data-table th, .data-table td { padding: 9px 10px; font-size: 12px; }
            .data-table { min-width: 480px; }

            .profile-container { padding: 20px 16px; border-radius: 12px; }

            .ai-assistant-popup { bottom: 12px; right: 12px; left: 12px; max-width: none; padding: 18px 20px; }
        }

        /* --- Very small phone --- */
        @media (max-width: 360px) {
            .header-nav-symbols a { width: 30px; height: 30px; font-size: 13px; }
            .hamburger-btn { width: 32px; height: 32px; }
            .stat-number { font-size: 16px; }
            .action-label { font-size: 10px; }
        }

        /* --- Touch devices: disable hover transforms --- */
        @media (hover: none) {
            .stat-card:hover, .quick-action-card:hover, .book-card:hover { transform: none; }
        }
    </style>
    
    <!-- Firebase AI Logic -->
    <script src="/update-libV2_V2/firebase_config.js"></script>
    <script src="/update-libV2_V2/ai_functions.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', async function() {
        try {
            await window.firebaseServices.initialize();
            console.log('✅ Firebase AI ready for student dashboard');
        } catch (err) {
            console.error('❌ Firebase init failed:', err);
        }
    });
    </script>
</head>
<body>
    <!-- ===== TOP HEADER NAVIGATION (SYMBOLS ONLY) ===== -->
    <header class="top-header" id="topHeader">
        <div class="header-left-group">
            <button class="hamburger-btn" onclick="toggleSidebar()" title="Toggle Sidebar" aria-label="Toggle Sidebar">
                <span class="hamburger-lines"><span></span><span></span><span></span></span>
            </button>
            
        </div>
        <nav class="header-nav-symbols">
            <a href="student_dashboard.php?section=dashboard" class="<?php echo $section === 'dashboard' ? 'active' : ''; ?>" title="Dashboard">
                <span>🗠</span>
            </a>
            <a href="student_dashboard.php?section=search" class="<?php echo $section === 'search' ? 'active' : ''; ?>" title="Search Books">
                <span>🕮</span>
            </a>
            <a href="student_dashboard.php?section=borrowings" class="<?php echo $section === 'borrowings' ? 'active' : ''; ?>" title="Borrowings">
                <span>⎘</span>
            </a>
            <a href="student_dashboard.php?section=reservations" class="<?php echo $section === 'reservations' ? 'active' : ''; ?>" title="Reservations">
                <span>⏱</span>
            </a>
            <a href="student_dashboard.php?section=fines" class="<?php echo $section === 'fines' ? 'active' : ''; ?>" title="Fines">
                <span>⚠</span>
            </a>
            <a href="student_dashboard.php?section=requests" class="<?php echo $section === 'requests' ? 'active' : ''; ?>" title="Requests">
                <span>🖺</span>
                <?php if (!empty($pendingRequests)): ?>
                    <span class="header-badge"><?php echo count($pendingRequests); ?></span>
                <?php endif; ?>
            </a>
            <a href="student_dashboard.php?section=profile" class="<?php echo $section === 'profile' ? 'active' : ''; ?>" title="Profile">
                <span>⚙</span>
            </a>
        </nav>
    </header>

    <div class="student-app">
        <div class="student-sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo-wrapper">
                    <img src="../img/agustinnb.png" alt="BCP Logo" class="sidebar-logo">
                </div>
                <h2>ST. AGNES ACADEMY</h2>
                <div class="subtitle">Caloocan Inc.</div>
                <p><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?></p>
                <small>ID: <?php echo htmlspecialchars($_SESSION['user_id_display'] ?? 'N/A'); ?></small>
            </div>
            <nav class="sidebar-nav">
                <a href="student_dashboard.php?section=dashboard" class="<?php echo $section === 'dashboard' ? 'active' : ''; ?>">
                    <span class="nav-icon">🗠</span>
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="student_dashboard.php?section=search" class="<?php echo $section === 'search' ? 'active' : ''; ?>">
                    <span class="nav-icon">🕮</span>
                    <span class="nav-label">Search Books</span>
                </a>
                <a href="student_dashboard.php?section=borrowings" class="<?php echo $section === 'borrowings' ? 'active' : ''; ?>">
                    <span class="nav-icon">⎘</span>
                    <span class="nav-label">My Borrowings</span>
                </a>
                <a href="student_dashboard.php?section=reservations" class="<?php echo $section === 'reservations' ? 'active' : ''; ?>">
                    <span class="nav-icon">⏱</span>
                    <span class="nav-label">Reservations</span>
                </a>
                <a href="student_dashboard.php?section=fines" class="<?php echo $section === 'fines' ? 'active' : ''; ?>">
                    <span class="nav-icon">⚠</span>
                    <span class="nav-label">My Fines</span>
                </a>
                <a href="student_dashboard.php?section=requests" class="<?php echo $section === 'requests' ? 'active' : ''; ?>">
                    <span class="nav-icon">🖺</span>
                    <span class="nav-label">My Requests</span>
                    <?php if (!empty($pendingRequests)): ?>
                        <span class="notification-badge"><?php echo count($pendingRequests); ?></span>
                    <?php endif; ?>
                </a>
                <a href="student_dashboard.php?section=profile" class="<?php echo $section === 'profile' ? 'active' : ''; ?>">
                    <span class="nav-icon">⚙</span>
                    <span class="nav-label">Profile</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="../admin_logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

        <div class="student-content" id="studentContent">
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
                        <p class="header-date">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>!</p>
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
                            <?php elseif ($accountStatus === 'Active'): ?>
                                You have active borrowings. Please return them on time.
                            <?php else: ?>
                                Your account is in good standing.
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="status-badge-large <?php echo strtolower(str_replace(' ', '-', $accountStatus)); ?>">
                        <?php echo $accountStatus; ?>
                    </span>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($activeBorrowings); ?></div>
                        <div class="stat-label">Active Borrowings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($overdueBorrowings); ?></div>
                        <div class="stat-label">Overdue Books</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($reservations); ?></div>
                        <div class="stat-label">Reservations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">₱<?php echo number_format($totalPendingFines, 2); ?></div>
                        <div class="stat-label">Pending Fines</div>
                    </div>
                </div>

                <div class="quick-actions">
                    <a href="student_dashboard.php?section=search" class="quick-action-card">
                        <span class="action-icon">🕮</span>
                        <span class="action-label">Search Books</span>
                    </a>
                    <a href="student_dashboard.php?section=borrowings" class="quick-action-card">
                        <span class="action-icon">⎘</span>
                        <span class="action-label">My Borrowings</span>
                    </a>
                    <a href="student_dashboard.php?section=reservations" class="quick-action-card">
                        <span class="action-icon">⏱</span>
                        <span class="action-label">Reservations</span>
                    </a>
                    <a href="student_dashboard.php?section=requests" class="quick-action-card" style="position:relative;">
                        <span class="action-icon">🖺</span>
                        <span class="action-label">My Requests</span>
                        <?php if (!empty($pendingRequests)): ?>
                            <span class="action-badge"><?php echo count($pendingRequests); ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <div style="background:#ffffff;border-radius:16px;padding:20px 24px;border:1px solid #e8e0d8;overflow-x:auto;">
                    <h3 style="margin:0 0 16px 0;color:#1a1a1a;font-weight:600;">Recent Borrowings</h3>
                    <?php if (!empty($borrowings)): ?>
                        <table class="data-table">
                            <thead>
                                <tr><th>Book</th><th>Borrowed</th><th>Due Date</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($borrowings, 0, 5) as $b): 
                                    $bookTitle = $b['books']['title'] ?? 'Unknown';
                                ?>
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
                        <span id="aiStatus" style="font-size:12px;color:#8a7a6e;">🤖 Initializing AI...</span>
                    </div>
                </div>

                <div class="search-bar">
                    <div class="search-input-wrapper">
                        <span class="search-icon">⌕</span>
                        <input type="text" id="searchInput" 
                               placeholder="Search by title, author, or describe what you need..."
                               value="<?php echo htmlspecialchars($searchQuery); ?>"
                               onkeydown="if(event.key==='Enter'){event.preventDefault(); performSearch();}">
                        <button class="clear-btn" id="clearSearchBtn" onclick="clearSearch()">✕</button>
                        <div class="predictive-results" id="predictiveResults"></div>
                    </div>
                    <button onclick="performSearch()" style="padding:14px 24px;background:#1a1a1a;color:#f0e8e0;border:none;border-radius:12px;cursor:pointer;font-size:15px;font-weight:500;">Search</button>
                </div>

                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
                    <span style="padding:6px 16px;background:#ffffff;border:1px solid #e8e0d8;border-radius:20px;font-size:13px;color:#6a5a4e;cursor:pointer;" onclick="quickSearch('programming')">Programming</span>
                    <span style="padding:6px 16px;background:#ffffff;border:1px solid #e8e0d8;border-radius:20px;font-size:13px;color:#6a5a4e;cursor:pointer;" onclick="quickSearch('history')">History</span>
                    <span style="padding:6px 16px;background:#ffffff;border:1px solid #e8e0d8;border-radius:20px;font-size:13px;color:#6a5a4e;cursor:pointer;" onclick="quickSearch('science')">Science</span>
                    <span style="padding:6px 16px;background:#ffffff;border:1px solid #e8e0d8;border-radius:20px;font-size:13px;color:#6a5a4e;cursor:pointer;" onclick="quickSearch('psychology')">Psychology</span>
                    <span style="padding:6px 16px;background:#ffffff;border:1px solid #e8e0d8;border-radius:20px;font-size:13px;color:#6a5a4e;cursor:pointer;" onclick="quickSearch('fiction')">Fiction</span>
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
                        
                        $hasPendingRequest = !empty(array_filter($studentRequests, function($r) use ($bookId) {
                            return $r['book_id'] == $bookId && ($r['status'] ?? '') === 'Pending';
                        }));
                        $hasApprovedRequest = !empty(array_filter($studentRequests, function($r) use ($bookId) {
                            return $r['book_id'] == $bookId && ($r['status'] ?? '') === 'Approved';
                        }));
                        $canBorrow = !$isRestricted && $available > 0 && $hasApprovedRequest;
                        $canRequest = !$isRestricted && !$hasPendingRequest && !$hasApprovedRequest;
                    ?>
                        <div class="book-card" data-book-id="<?php echo $bookId; ?>">
                            <div class="book-cover-wrapper">
                                <?php if ($hasCover): ?>
                                    <img src="<?php echo htmlspecialchars($coverImage); ?>" alt="<?php echo htmlspecialchars($title); ?>">
                                <?php else: ?>
                                    <div class="cover-placeholder" style="background-color:<?php echo getPlaceholderColor($bookId); ?>;">
                                        <span class="initial"><?php echo strtoupper(substr($title, 0, 1)); ?></span>
                                    </div>
                                <?php endif; ?>
                                <span class="availability-badge <?php echo $available <= 0 ? 'none' : ($available <= 2 ? 'low' : ''); ?>">
                                    <?php echo $available <= 0 ? 'Not Available' : ($available <= 2 ? 'Low Stock' : 'Available'); ?>
                                </span>
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
                                            <button class="btn-borrow" onclick="openBorrowModal('<?php echo $bookId; ?>', '<?php echo htmlspecialchars($title); ?>', '<?php echo htmlspecialchars($author); ?>', <?php echo $available; ?>)">Borrow</button>
                                        <?php elseif ($hasPendingRequest): ?>
                                            <span class="btn-request" style="background:#d4c9c0;cursor:not-allowed;opacity:0.6;">Request Pending</span>
                                        <?php elseif ($canRequest): ?>
                                            <button class="btn-request" onclick="openRequestForm('<?php echo $bookId; ?>', '<?php echo htmlspecialchars($title); ?>', '<?php echo htmlspecialchars($author); ?>', 'borrow')">Request</button>
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
                    <div class="message <?php echo strpos($requestMessage, 'Error') !== false ? 'error' : 'info'; ?>">
                        <?php echo htmlspecialchars($requestMessage); ?>
                    </div>
                <?php endif; ?>

                <div class="info-note">
                    <strong>📌 Note:</strong> Your request will be reviewed by the admin.
                </div>

                <form method="POST" action="student_dashboard.php?section=request_form">
                    <input type="hidden" name="book_id" value="<?php echo htmlspecialchars($requestBookId); ?>">
                    <input type="hidden" name="request_type" value="<?php echo htmlspecialchars($requestType); ?>">
                    
                    <!-- FIXED CREDENTIALS: Using session and student data from account creation -->
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>" disabled required>
                    </div>
                    
                    <div class="form-group">
                        <label>Student ID <span class="required">*</span></label>
                        <input type="text" name="student_id" value="<?php echo htmlspecialchars($studentData['student_id'] ?? $_SESSION['user_id_display'] ?? ''); ?>" disabled required>
                    </div>
                    
                    <div class="form-group">
                        <label>Year Level <span class="required">*</span></label>
                        <select name="year_level" disabled required>
                            <option value="">Select Year Level</option>
                            <option value="Grade 7" <?php echo ($studentData['year_level'] ?? '') === 'Grade 7' ? 'selected' : ''; ?>>Grade 7</option>
                            <option value="Grade 8" <?php echo ($studentData['year_level'] ?? '') === 'Grade 8' ? 'selected' : ''; ?>>Grade 8</option>
                            <option value="Grade 9" <?php echo ($studentData['year_level'] ?? '') === 'Grade 9' ? 'selected' : ''; ?>>Grade 9</option>
                            <option value="Grade 10" <?php echo ($studentData['year_level'] ?? '') === 'Grade 10' ? 'selected' : ''; ?>>Grade 10</option>
                            <option value="Grade 11" <?php echo ($studentData['year_level'] ?? '') === 'Grade 11' ? 'selected' : ''; ?>>Grade 11</option>
                            <option value="Grade 12" <?php echo ($studentData['year_level'] ?? '') === 'Grade 12' ? 'selected' : ''; ?>>Grade 12</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Section <span class="required">*</span></label>
                        <input type="text" name="section" value="<?php echo htmlspecialchars($studentData['section'] ?? ''); ?>" disabled required>
                    </div>
                    
                    <div class="form-group">
                        <label>Request Type</label>
                        <input type="text" value="<?php echo ucfirst($requestType); ?>" disabled style="background:#f5f3f0;">
                    </div>
                    
                    <div class="form-group">
                        <label>Purpose of Request <span class="required">*</span></label>
                        <textarea name="purpose" placeholder="Please explain why you need this book..." rows="3" required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <a href="student_dashboard.php?section=search" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-submit">Submit Request</button>
                    </div>
                </form>
            </div>

            <?php elseif ($section === 'requests'): ?>
            <div class="requests-management">
                <div class="section-header">
                    <h1>My Book Requests</h1>
                    <span class="count-badge"><?php echo count($studentRequests); ?> requests</span>
                </div>
                
                <?php if (!empty($studentRequests)): ?>
                    <?php foreach ($studentRequests as $request): 
                        $bookTitle = $request['books']['title'] ?? 'Unknown Book';
                        $bookAuthor = $request['books']['author'] ?? 'Unknown Author';
                        $status = $request['status'] ?? 'Pending';
                        $requestType = $request['request_type'] ?? 'borrow';
                        $createdAt = $request['created_at'] ?? 'now';
                    ?>
                        <div class="request-item">
                            <div class="request-info">
                                <div class="request-title"><?php echo htmlspecialchars($bookTitle); ?></div>
                                <div class="request-details">
                                    by <?php echo htmlspecialchars($bookAuthor); ?> • <?php echo ucfirst($requestType); ?> request • <?php echo date('M d, Y', strtotime($createdAt)); ?>
                                </div>
                            </div>
                            <div>
                                <span class="request-status <?php echo strtolower($status); ?>"><?php echo $status; ?></span>
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
                <div class="section-header">
                    <h1>My Borrowings</h1>
                    <span class="count-badge"><?php echo count($borrowings); ?> borrowings</span>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr><th>Book</th><th>Author</th><th>Borrowed</th><th>Due Date</th><th>Status</th></tr>
                        </thead>
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
                <div class="section-header">
                    <h1>My Reservations</h1>
                    <span class="count-badge"><?php echo count($reservations); ?> reservations</span>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr><th>Book</th><th>Reserved</th><th>Expiry Date</th><th>Status</th></tr>
                        </thead>
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
                <div class="section-header">
                    <h1>My Fines</h1>
                    <span class="count-badge">Total: ₱<?php echo number_format($totalFines, 2); ?></span>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr><th>Reason</th><th>Amount</th><th>Date</th><th>Status</th></tr>
                        </thead>
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

            <?php elseif ($section === 'profile'): ?>
            <div class="dashboard-content">
                <h1 style="margin-bottom:20px;">My Profile</h1>
                <div class="profile-container">
                    <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;flex-wrap:wrap;">
                        <div class="profile-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'S', 0, 1)); ?></div>
                        <div style="min-width:0;">
                            <h2 style="margin:0;color:#1a1a1a;font-size:20px;word-break:break-word;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?></h2>
                            <p style="margin:0;color:#8a7a6e;"><?php echo htmlspecialchars($_SESSION['user_id_display'] ?? 'N/A'); ?></p>
                            <span class="status-badge-large <?php echo strtolower(str_replace(' ', '-', $accountStatus)); ?>" style="margin-top:4px;display:inline-block;"><?php echo $accountStatus; ?></span>
                        </div>
                    </div>
                    <div style="border-top:1px solid #f0edea;padding-top:20px;">
                        <div class="profile-field">
                            <span class="label">Username</span>
                            <div class="value"><?php echo htmlspecialchars($_SESSION['username'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="profile-field">
                            <span class="label">Email</span>
                            <div class="value"><?php echo htmlspecialchars($_SESSION['email'] ?? 'N/A'); ?></div>
                        </div>
                        <?php if (!empty($studentData)): ?>
                            <div class="profile-field">
                                <span class="label">Student ID</span>
                                <div class="value"><?php echo htmlspecialchars($studentData['student_id'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="profile-field">
                                <span class="label">Year Level</span>
                                <div class="value"><?php echo htmlspecialchars($studentData['year_level'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="profile-field">
                                <span class="label">Section</span>
                                <div class="value"><?php echo htmlspecialchars($studentData['section'] ?? 'N/A'); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- BORROW MODAL -->
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
            <form id="borrowForm" method="GET" action="student_dashboard.php">
                <input type="hidden" name="section" value="search">
                <input type="hidden" name="action" value="borrow">
                <input type="hidden" name="book_id" id="borrowBookId">
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('borrowModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Confirm Borrow</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const allBooks = <?php echo json_encode($allBooks); ?>;
        const userId = '<?php echo $userId; ?>';
        const userGradeLevel = '<?php echo $studentData['year_level'] ?? 'Grade 10'; ?>';
        const userSubjects = <?php echo json_encode(array_column($studentSubjects, 'subject_name')); ?>;
        const userHistory = <?php echo json_encode(array_column($userSearchHistory, 'query')); ?>;

        /* ===== SIDEBAR TOGGLE (HAMBURGER) ===== */
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('studentContent');
            const topHeader = document.getElementById('topHeader');
            const overlay = document.getElementById('mobileOverlay');
            
            if (window.innerWidth <= 900) {
                sidebar.classList.toggle('mobile-open');
                if (sidebar.classList.contains('mobile-open')) {
                    overlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
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

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.modal-overlay').forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        function openBorrowModal(bookId, title, author, available) {
            document.getElementById('borrowBookId').value = bookId;
            document.getElementById('borrowBookTitle').textContent = title;
            document.getElementById('borrowBookAuthor').textContent = 'by ' + author;
            openModal('borrowModal');
        }

        function openRequestForm(bookId, title, author, type) {
            window.location.href = 'student_dashboard.php?section=request_form&book_id=' + bookId + '&type=' + type;
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

        // ============================================
        // AI SEARCH FUNCTIONS
        // ============================================
        let predictionTimeout = null;
        let searchSession = { queries: [], clicks: [], abandoned: [] };

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getPlaceholderColor(id) {
            const colors = ['#2a2a2a', '#4a4a4a', '#6a6a6a', '#8a8a8a', '#aaaaaa', '#cacaca'];
            let hash = 0;
            for (let i = 0; i < String(id).length; i++) {
                hash = ((hash << 5) - hash) + String(id).charCodeAt(i);
                hash |= 0;
            }
            return colors[Math.abs(hash) % colors.length];
        }

        async function performSearch() {
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return;
            
            const query = searchInput.value.trim();
            if (!query) {
                window.location.href = 'student_dashboard.php?section=search';
                return;
            }

            const grid = document.getElementById('bookGrid');
            const badge = document.getElementById('searchTypeBadge');
            const aiStatus = document.getElementById('aiStatus');

            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;"><div style="width:40px;height:40px;border:3px solid #f0edea;border-top-color:#1a1a1a;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div><p style="color:#9a8a7e;">AI is searching...</p></div>';
            
            badge.style.display = 'inline-block';
            badge.textContent = '🧠 Searching...';
            aiStatus.textContent = '🤖 AI analyzing...';

            try {
                let results;
                if (window.firebaseServices && window.firebaseServices.isReady) {
                    results = await window.aiFunctions.semanticSearch(query, allBooks, 20);
                    aiStatus.innerHTML = '<span style="color:#34a853;">●</span> AI Online';
                    badge.textContent = '🧠 AI Search';
                } else {
                    results = basicSearchFallback(query, allBooks);
                    aiStatus.textContent = '📝 Basic Search';
                    badge.textContent = '📝 Basic';
                }
                
                renderSearchResults(results);
                document.getElementById('resultCount').textContent = results.length;

                searchSession.queries.push(query);
                searchSession.clicks.push(0);
                
                if (searchSession.queries.length >= 3 && searchSession.queries.length % 3 === 0 && window.firebaseServices && window.firebaseServices.isReady) {
                    const analysis = await window.aiFunctions.analyzeSession(
                        searchSession.queries,
                        searchSession.clicks,
                        searchSession.abandoned
                    );
                    if (analysis.frustration_detected && analysis.frustration_score > 50) {
                        showFrustrationPopup(analysis);
                    }
                }
            } catch (error) {
                console.error('Search error:', error);
                const results = basicSearchFallback(query, allBooks);
                renderSearchResults(results);
                document.getElementById('resultCount').textContent = results.length;
                badge.textContent = '📝 Basic';
            }
        }

        function basicSearchFallback(query, books) {
            const q = query.toLowerCase();
            const words = q.split(' ').filter(w => w.length > 2);

            return books.map(book => {
                let score = 0;
                const title = (book.title || '').toLowerCase();
                const author = (book.author || '').toLowerCase();
                const desc = (book.description || '').toLowerCase();

                if (title.includes(q)) score += 50;
                if (author.includes(q)) score += 30;
                if (desc.includes(q)) score += 20;
                
                words.forEach(w => {
                    if (title.includes(w)) score += 10;
                    if (author.includes(w)) score += 5;
                    if (desc.includes(w)) score += 3;
                });

                return { ...book, relevance: Math.min(score, 100) };
            })
            .filter(b => b.relevance > 0)
            .sort((a, b) => b.relevance - a.relevance);
        }

        function renderSearchResults(books) {
            const grid = document.getElementById('bookGrid');
            if (!grid) return;

            if (books.length === 0) {
                grid.innerHTML = '<div style="grid-column:1/-1;background:#fff;border-radius:16px;padding:60px 40px;text-align:center;border:1px solid #e8e0d8;"><h3>No books found</h3><p style="color:#9a8a7e;margin-top:8px;">Try different keywords</p></div>';
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
                            <span class="availability-badge ${available <= 0 ? 'none' : (available <= 2 ? 'low' : '')}">
                                ${available <= 0 ? 'Not Available' : (available <= 2 ? 'Low Stock' : 'Available')}
                            </span>
                        </div>
                        <div class="book-info">
                            <h3 class="book-title">${escapeHtml(title)}</h3>
                            <p class="book-author">by ${escapeHtml(author)}</p>
                            <span class="book-category">${escapeHtml(category)}</span>
                            ${relevance > 0 ? `<span class="relevance-score">AI Match: ${relevance}%</span>` : ''}
                            <div class="book-meta">
                                <span class="availability"><strong>${available}</strong> / ${book.quantity || 0} available</span>
                                <div class="book-actions">
                                    ${available > 0 
                                        ? `<button class="btn-request" onclick="openRequestForm('${bookId}', '${escapeHtml(title).replace(/'/g, "\\'")}', '${escapeHtml(author).replace(/'/g, "\\'")}', 'borrow')">Request</button>`
                                        : `<button class="btn-reserve" onclick="openRequestForm('${bookId}', '${escapeHtml(title).replace(/'/g, "\\'")}', '${escapeHtml(author).replace(/'/g, "\\'")}', 'reserve')">Reserve</button>`
                                    }
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

        // ============================================
        // PREDICTIVE SEARCH
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const clearBtn = document.getElementById('clearSearchBtn');

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.trim();
                    if (query.length > 0) {
                        clearBtn.classList.add('visible');
                    } else {
                        clearBtn.classList.remove('visible');
                        hidePredictions();
                    }

                    if (query.length >= 2) {
                        clearTimeout(predictionTimeout);
                        predictionTimeout = setTimeout(() => getPredictions(query), 400);
                    } else {
                        hidePredictions();
                    }
                });
            }
        });

        async function getPredictions(query) {
            const container = document.getElementById('predictiveResults');
            if (!container) return;

            if (!window.firebaseServices || !window.firebaseServices.isReady) return;

            container.innerHTML = '<div class="loading-predictions"><div class="spinner"></div> Predicting...</div>';
            container.classList.add('visible');

            try {
                const predictions = await window.aiFunctions.predictSearch(
                    query,
                    userGradeLevel,
                    userSubjects,
                    userHistory
                );

                if (predictions && predictions.length > 0) {
                    container.innerHTML = predictions.map(p => `
                        <div class="predictive-item" onclick="selectPrediction('${escapeHtml(p.title).replace(/'/g, "\\'")}')">
                            <span class="pred-score">${Math.round(p.prediction_score || 0)}%</span>
                            <div class="pred-title">${escapeHtml(p.title)}</div>
                            <div class="pred-author">by ${escapeHtml(p.author || 'Unknown')}</div>
                            <span class="pred-badge">🎯 ${escapeHtml(p.category || 'General')}</span>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<div style="padding:16px;text-align:center;color:#9a8a7e;">Keep typing...</div>';
                }
            } catch (error) {
                console.error('Prediction error:', error);
                container.innerHTML = '<div style="padding:16px;text-align:center;color:#9a8a7e;">Prediction unavailable</div>';
            }
        }

        function selectPrediction(title) {
            document.getElementById('searchInput').value = title;
            hidePredictions();
            performSearch();
        }

        function hidePredictions() {
            const container = document.getElementById('predictiveResults');
            if (container) container.classList.remove('visible');
        }

        document.addEventListener('click', function(e) {
            const container = document.getElementById('predictiveResults');
            if (container && !container.contains(e.target)) {
                const searchInput = document.getElementById('searchInput');
                if (searchInput && !searchInput.contains(e.target)) {
                    hidePredictions();
                }
            }
        });

        // ============================================
        // FRUSTRATION POPUP
        // ============================================
        function showFrustrationPopup(analysis) {
            let popup = document.getElementById('aiAssistantPopup');
            if (!popup) {
                popup = document.createElement('div');
                popup.id = 'aiAssistantPopup';
                popup.className = 'ai-assistant-popup';
                document.body.appendChild(popup);
            }

            popup.innerHTML = `
                <div class="popup-header">
                    <h3>🤖 Research Assistant</h3>
                    <button class="close-popup" onclick="closeFrustrationPopup()">×</button>
                </div>
                <div class="popup-body">
                    <p><strong>It looks like you're having some difficulty with your search.</strong></p>
                    <ul>${(analysis.reasons || []).map(r => `<li>${r}</li>`).join('')}</ul>
                    <p>${(analysis.suggestions || [])[0] || 'Try using simpler keywords.'}</p>
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

        document.addEventListener('click', function(e) {
            const bookCard = e.target.closest('.book-card');
            if (bookCard && searchSession.clicks.length > 0) {
                searchSession.clicks[searchSession.clicks.length - 1]++;
            }
        });
    </script>
</body>
</html>
