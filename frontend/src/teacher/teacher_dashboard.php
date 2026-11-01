<?php
// Teacher Dashboard - St. Agnes Academy Library Management System
// Uses the existing Supabase tables and approval workflow already used by the system.
session_start();
date_default_timezone_set('Asia/Manila');

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!isset($_SESSION['user_id']) || !in_array($role, ['teacher', 'admin'], true)) {
    header('Location: ../login.php');
    exit;
}

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', 'https://olzkpwzebcnmbqhbcyyz.supabase.co');
    define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im9semtwd3plYmNubWJxaGJjeXl6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQwMjYxNzcsImV4cCI6MjA5OTYwMjE3N30.GNk7gwaWfi3O-dncbixlkB7M8q6R-UJUe2VMsB5cBTQ');
}
function teacherSupabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $headers = ['apikey: ' . SUPABASE_ANON_KEY, 'Authorization: Bearer ' . SUPABASE_ANON_KEY, 'Content-Type: application/json', 'Prefer: return=representation'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 30]);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    elseif ($method === 'PATCH' || $method === 'DELETE') { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) throw new Exception('Connection error: ' . $error);
    if ($code >= 400) throw new Exception('Database request failed (' . $code . '): ' . $response);
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : [];
}

function supabaseRequest($endpoint, $method = 'GET', $data = null) { return teacherSupabaseRequest($endpoint, $method, $data); }
require_once dirname(__DIR__) . '/loan_policy_notifications.php';
function thEsc($value) { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function thRedirect($section, $message, $ok = true) {
    header('Location: teacher_dashboard.php?section=' . rawurlencode($section) . '&' . ($ok ? 'msg=' : 'error=') . rawurlencode($message));
    exit;
}
$userId = (string)$_SESSION['user_id'];
try { libraryGenerateDueNotices($userId, 'teacher_dashboard.php'); } catch (Throwable $e) { error_log($e->getMessage()); }
$displayId = (string)($_SESSION['user_id_display'] ?? 'TEACHER');
$fullName = (string)($_SESSION['full_name'] ?? 'Teacher');
$section = $_GET['section'] ?? 'dashboard';
$allowedSections = ['dashboard','catalog','requests','borrowings','reservations','history','notifications','profile'];
if (!in_array($section, $allowedSections, true)) $section = 'dashboard';
if (empty($_SESSION['teacher_csrf'])) $_SESSION['teacher_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['teacher_csrf'];

// All state-changing actions are POST-only and scoped to the currently logged-in account.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) thRedirect($section, 'Your session token expired. Please try again.', false);
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'submit_request') {
            $bookId = trim((string)($_POST['book_id'] ?? ''));
            $requestType = $_POST['request_type'] ?? '';
            $purpose = trim((string)($_POST['purpose'] ?? ''));
            if ($bookId === '' || !in_array($requestType, ['borrow','reserve'], true)) thRedirect('catalog', 'Please select a valid book and request type.', false);
            $book = teacherSupabaseRequest('books?select=id,title,available&id=eq.' . rawurlencode($bookId));
            if (!$book) thRedirect('catalog', 'Book not found.', false);
            if ($requestType === 'borrow' && (int)($book[0]['available'] ?? 0) < 1) thRedirect('catalog', 'This book is currently unavailable. You may request a reservation instead.', false);
            $existing = teacherSupabaseRequest('book_requests?select=id&user_id=eq.' . rawurlencode($userId) . '&book_id=eq.' . rawurlencode($bookId) . '&status=eq.Pending');
            if ($existing) thRedirect('requests', 'You already have a pending request for this book.', false);
            $requestData = [
                'user_id' => $userId, 'book_id' => $bookId, 'request_type' => $requestType,
                // Existing book_requests columns are shared with student requests. These values identify a faculty request.
                'student_id' => $displayId, 'full_name' => $fullName,
                'year_level' => 'Faculty', 'section' => 'Teaching Staff',
                'purpose' => $purpose, 'status' => 'Pending'
            ];
            teacherSupabaseRequest('book_requests', 'POST', $requestData);
            try {
                $admins = teacherSupabaseRequest('users?select=id&role=eq.admin');
                foreach ($admins as $admin) teacherSupabaseRequest('notifications', 'POST', [
                    'user_id' => $admin['id'], 'title' => 'New Teacher Book Request',
                    'message' => $fullName . ' submitted a ' . $requestType . ' request for "' . ($book[0]['title'] ?? 'a book') . '".',
                    'type' => 'request', 'icon' => '📚', 'is_read' => false,
                    'action_url' => 'admin_dashboard.php?section=requests', 'action_label' => 'Review Request'
                ]);
            } catch (Exception $ignored) { error_log('Teacher request notification: ' . $ignored->getMessage()); }
            thRedirect('requests', 'Your request was submitted. Please wait for librarian or administrator approval.');
        }
        if ($action === 'complete_approved_borrow' || $action === 'complete_approved_reservation') {
            $bookId = trim((string)($_POST['book_id'] ?? ''));
            $request = teacherSupabaseRequest('book_requests?select=id,book_id,request_type,status&user_id=eq.' . rawurlencode($userId) . '&book_id=eq.' . rawurlencode($bookId) . '&status=eq.Approved&request_type=eq.' . ($action === 'complete_approved_borrow' ? 'borrow' : 'reserve'));
            if (!$request) thRedirect('requests', 'There is no approved request for this action.', false);
            if ($action === 'complete_approved_borrow') {
                $book = teacherSupabaseRequest('books?select=id,title,available&id=eq.' . rawurlencode($bookId));
                if (!$book || (int)($book[0]['available'] ?? 0) < 1) thRedirect('requests', 'The book is no longer available.', false);
                $active = teacherSupabaseRequest('borrowings?select=id&user_id=eq.' . rawurlencode($userId) . '&book_id=eq.' . rawurlencode($bookId) . '&status=neq.Returned');
                if ($active) thRedirect('requests', 'You already have an active borrowing for this book.', false);
                $policy = libraryLoanPolicy('teacher');
                $dueDate = date('Y-m-d', strtotime('+' . $policy['days'] . ' days'));
                teacherSupabaseRequest('borrowings', 'POST', ['book_id' => $bookId, 'user_id' => $userId, 'borrow_date' => date('Y-m-d'), 'due_date' => $dueDate, 'status' => 'Borrowed']);
                libraryCreateBorrowNotice($userId, $book[0]['title'] ?? 'the book', $policy['days'], $dueDate, 'teacher_dashboard.php');
                teacherSupabaseRequest('books?id=eq.' . rawurlencode($bookId), 'PATCH', ['available' => max(0, (int)$book[0]['available'] - 1)]);
                teacherSupabaseRequest('book_requests?id=eq.' . rawurlencode($request[0]['id']), 'PATCH', ['status' => 'Fulfilled']);
                thRedirect('borrowings', 'Borrowing recorded successfully. Your due date is ' . date('F j, Y', strtotime('+' . $policy['days'] . ' days')) . '.');
            } else {
                $existing = teacherSupabaseRequest('reservations?select=id&user_id=eq.' . rawurlencode($userId) . '&book_id=eq.' . rawurlencode($bookId) . '&status=eq.Pending');
                if ($existing) thRedirect('reservations', 'You already have a pending reservation for this book.', false);
                teacherSupabaseRequest('reservations', 'POST', ['book_id' => $bookId, 'user_id' => $userId, 'reservation_date' => date('Y-m-d'), 'expiry_date' => date('Y-m-d', strtotime('+3 days')), 'status' => 'Pending', 'notes' => 'Reserved by teacher']);
                teacherSupabaseRequest('book_requests?id=eq.' . rawurlencode($request[0]['id']), 'PATCH', ['status' => 'Fulfilled']);
                thRedirect('reservations', 'Your reservation has been recorded.');
            }
        }
        if ($action === 'cancel_request') {
            $id = trim((string)($_POST['request_id'] ?? ''));
            $rows = teacherSupabaseRequest('book_requests?select=id,status&id=eq.' . rawurlencode($id) . '&user_id=eq.' . rawurlencode($userId));
            if (!$rows || ($rows[0]['status'] ?? '') !== 'Pending') thRedirect('requests', 'Only your pending requests can be cancelled.', false);
            teacherSupabaseRequest('book_requests?id=eq.' . rawurlencode($id) . '&user_id=eq.' . rawurlencode($userId), 'PATCH', ['status' => 'Cancelled']);
            thRedirect('requests', 'Request cancelled.');
        }
        if ($action === 'cancel_reservation') {
            $id = trim((string)($_POST['reservation_id'] ?? ''));
            $rows = teacherSupabaseRequest('reservations?select=id,status&id=eq.' . rawurlencode($id) . '&user_id=eq.' . rawurlencode($userId));
            if (!$rows || ($rows[0]['status'] ?? '') !== 'Pending') thRedirect('reservations', 'Only your pending reservations can be cancelled.', false);
            teacherSupabaseRequest('reservations?id=eq.' . rawurlencode($id) . '&user_id=eq.' . rawurlencode($userId), 'PATCH', ['status' => 'Cancelled']);
            thRedirect('reservations', 'Reservation cancelled.');
        }
        if ($action === 'mark_notification_read') {
            $id = trim((string)($_POST['notification_id'] ?? ''));
            teacherSupabaseRequest('notifications?id=eq.' . rawurlencode($id) . '&user_id=eq.' . rawurlencode($userId), 'PATCH', ['is_read' => true]);
            thRedirect('notifications', 'Notification marked as read.');
        }
        if ($action === 'update_profile') {
            $newName = trim((string)($_POST['full_name'] ?? ''));
            $newEmail = trim((string)($_POST['email'] ?? ''));
            if ($newName === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) thRedirect('profile', 'Enter a valid name and email address.', false);
            teacherSupabaseRequest('users?id=eq.' . rawurlencode($userId), 'PATCH', ['full_name' => $newName, 'email' => $newEmail]);
            $_SESSION['full_name'] = $newName; $_SESSION['email'] = $newEmail; $fullName = $newName;
            thRedirect('profile', 'Profile updated successfully.');
        }
    } catch (Exception $e) {
        error_log('Teacher dashboard action error: ' . $e->getMessage());
        thRedirect($section, 'Unable to complete the action. ' . $e->getMessage(), false);
    }
}

// Load the current user's records only. Admin access to this workspace remains scoped to the admin's own account.
$books = $categories = $requests = $borrowings = $reservations = $notifications = $fines = [];
$dataError = '';
try {
    $books = teacherSupabaseRequest('books?select=*,categories(name)&order=title.asc');
    $categories = teacherSupabaseRequest('categories?select=*&order=name.asc');
    $requests = teacherSupabaseRequest('book_requests?select=*,books(title,author,cover_image)&user_id=eq.' . rawurlencode($userId) . '&order=created_at.desc');
    $borrowings = teacherSupabaseRequest('borrowings?select=*,books(title,author,cover_image)&user_id=eq.' . rawurlencode($userId) . '&order=borrow_date.desc');
    $reservations = teacherSupabaseRequest('reservations?select=*,books(title,author,cover_image,available)&user_id=eq.' . rawurlencode($userId) . '&order=reservation_date.desc');
    try { $notifications = teacherSupabaseRequest('notifications?select=*&user_id=eq.' . rawurlencode($userId) . '&order=created_at.desc&limit=50'); } catch (Exception $e) { $notifications = []; }
    try { $fines = teacherSupabaseRequest('fines?select=*&user_id=eq.' . rawurlencode($userId)); } catch (Exception $e) { $fines = []; }
} catch (Exception $e) { $dataError = $e->getMessage(); }

$search = trim((string)($_GET['search'] ?? ''));
$categoryFilter = (string)($_GET['category'] ?? '');
$availabilityFilter = (string)($_GET['availability'] ?? '');
$teacherSearchTerms = preg_split('/\\s+/u', mb_strtolower(preg_replace('/[^\\p{L}\\p{N}]+/u', ' ', $search), 'UTF-8'));
$teacherStop = array_fill_keys(preg_split('/\\s+/u', 'ang mga ng na sa si kay kina at o or and the a an of for about tungkol libro aklat book books hanap hanapin naghahanap maghanap gusto ko ako po ba may mayroon meron'), true);
$teacherSearchTerms = array_values(array_filter($teacherSearchTerms, function($term) use ($teacherStop) { return mb_strlen($term, 'UTF-8') > 1 && !isset($teacherStop[$term]); }));
$teacherSynonyms = ['programasyon'=>['programming','program','coding'], 'programming'=>['program','coding'], 'paghahack'=>['hacking','hack'], 'hacking'=>['hack','cybersecurity'], 'hack'=>['hacking','cybersecurity'], 'kasaysayan'=>['history'], 'agham'=>['science'], 'matematika'=>['math','mathematics'], 'panitikan'=>['literature'], 'teknolohiya'=>['technology'], 'kalikasan'=>['nature','environment']];
foreach ($teacherSearchTerms as $term) if (isset($teacherSynonyms[$term])) $teacherSearchTerms = array_merge($teacherSearchTerms, $teacherSynonyms[$term]);
$filteredBooks = array_values(array_filter($books, function($book) use ($search, $teacherSearchTerms, $categoryFilter, $availabilityFilter) {
    $haystack = mb_strtolower(implode(' ', [$book['title'] ?? '', $book['author'] ?? '', $book['isbn'] ?? '', $book['keywords'] ?? '', $book['description'] ?? '', $book['publisher'] ?? '', $book['categories']['name'] ?? '']), 'UTF-8');
    if ($search !== '') { $matched = false; foreach ($teacherSearchTerms as $term) if (mb_strpos($haystack, $term, 0, 'UTF-8') !== false) { $matched = true; break; } if (!$matched) return false; }
    if ($categoryFilter !== '' && (string)($book['category_id'] ?? '') !== $categoryFilter) return false;
    if ($availabilityFilter === 'available' && (int)($book['available'] ?? 0) < 1) return false;
    if ($availabilityFilter === 'unavailable' && (int)($book['available'] ?? 0) > 0) return false;
    return true;
}));
$activeBorrowings = array_values(array_filter($borrowings, fn($b) => ($b['status'] ?? '') !== 'Returned'));
$historyBorrowings = array_values(array_filter($borrowings, fn($b) => ($b['status'] ?? '') === 'Returned'));
$overdueBorrowings = array_values(array_filter($activeBorrowings, fn($b) => !empty($b['due_date']) && strtotime($b['due_date']) < strtotime(date('Y-m-d'))));
$pendingRequests = array_values(array_filter($requests, fn($r) => ($r['status'] ?? '') === 'Pending'));
$approvedRequests = array_values(array_filter($requests, fn($r) => ($r['status'] ?? '') === 'Approved'));
$unreadNotifications = array_values(array_filter($notifications, fn($n) => !($n['is_read'] ?? false)));
$pendingReservations = array_values(array_filter($reservations, fn($r) => ($r['status'] ?? '') === 'Pending'));
$unpaidFines = array_values(array_filter($fines, fn($f) => ($f['status'] ?? '') !== 'Paid'));
$profile = ['full_name' => $_SESSION['full_name'] ?? 'Teacher', 'email' => $_SESSION['email'] ?? '', 'username' => $_SESSION['username'] ?? '', 'user_id' => $displayId];
try { $u = teacherSupabaseRequest('users?select=full_name,email,username,user_id&id=eq.' . rawurlencode($userId)); if ($u) $profile = array_merge($profile, $u[0]); } catch (Exception $e) {}
$msg = $_GET['msg'] ?? ''; $error = $_GET['error'] ?? '';
function teacherBookCover($book) {
    $cover = $book['cover_image'] ?? '';
    if ($cover && (strpos($cover, 'data:image') === 0 || filter_var($cover, FILTER_VALIDATE_URL))) return $cover;
    return '';
}
function teacherStatusClass($status) {
    $s = strtolower((string)$status);
    if (in_array($s, ['approved','returned','fulfilled','paid','available'], true)) return 'success';
    if (in_array($s, ['rejected','cancelled','overdue','lost'], true)) return 'danger';
    return 'pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Dashboard - St. Agnes Academy Library</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--ink:#08080a;--pink:#b40f7d;--pink2:#d41688;--rose:#ff0199;--paper:#f7f1f5;--muted:#8d7d89;--line:#eeddea;--white:#fff;--success:#18794e;--danger:#b42342;--shadow:0 8px 28px rgba(42,12,35,.06)}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Inter,Arial,sans-serif;background:var(--paper);color:#17121a}body{overflow-x:hidden}a{color:inherit;text-decoration:none}button,input,select,textarea{font:inherit}.app{min-height:100vh}.sidebar{position:fixed;inset:0 auto 0 0;width:240px;background:#010107;color:#e8e0e8;z-index:30;display:flex;flex-direction:column;transition:.25s;overflow-y:auto;border-right:1px solid #29212a}.sidebar.collapsed{width:70px}.sidebar.collapsed .brand{padding:18px 8px;text-align:center}.sidebar.collapsed .brand img{width:48px;height:48px;margin:0 auto 8px;object-position:center}.sidebar.collapsed .brand h2,.sidebar.collapsed .brand p,.sidebar.collapsed .brand .who,.sidebar.collapsed .nav .label{display:none}.sidebar.collapsed .nav a{justify-content:center;padding:12px 8px}.sidebar.collapsed .nav .ico{width:auto}.sidebar.collapsed .side-bottom{padding:12px 8px}.sidebar.collapsed .logout{font-size:0;padding:10px 4px}.sidebar.collapsed .logout:after{content:"↪";font-size:16px}.brand{padding:22px 20px 17px;border-bottom:1px solid #29212a;min-width:0}.brand img{width:72px;height:72px;max-width:100%;object-fit:contain;object-position:left center;display:block;margin:0 0 11px}.brand h2{font-size:13px;line-height:1.35;margin:0;color:#f6eaf2;white-space:normal;overflow-wrap:anywhere}.brand p{font-size:11px;line-height:1.4;color:#9c8295;margin:4px 0 0;letter-spacing:.4px}.brand .who{margin-top:14px;padding-top:12px;border-top:1px solid #29212a;font-size:12px;line-height:1.45;overflow-wrap:anywhere}.brand small{display:block;color:#8b7787;font-size:10px;line-height:1.4;margin-top:4px;overflow-wrap:anywhere}.nav{padding:13px 0;flex:1}.nav a{display:flex;align-items:center;gap:13px;padding:12px 22px;border-left:3px solid transparent;color:#a899a8;font-size:13px;transition:.2s}.nav a:hover,.nav a.active{background:rgba(180,15,125,.17);border-left-color:var(--pink);color:#fff}.nav .ico{width:22px;text-align:center;font-size:17px}.nav .label{white-space:normal;overflow-wrap:anywhere;line-height:1.35;min-width:0}.side-bottom{padding:15px 18px 22px;border-top:1px solid #29212a}.logout{display:block;text-align:center;padding:11px;border:1px solid rgba(180,15,125,.35);border-radius:9px;color:#e77ac4;background:rgba(180,15,125,.13);font-size:13px}.main{margin-left:240px;min-height:100vh;transition:.25s}.main.expanded{margin-left:70px}.topbar{height:56px;background:#010107;position:sticky;top:0;z-index:20;display:flex;align-items:center;justify-content:space-between;padding:0 22px;border-bottom:1px solid #29212a}.hamb{width:38px;height:38px;border:1px solid #302530;background:#111015;color:#f5e9f2;border-radius:9px;cursor:pointer;font-size:19px}.toplinks{display:flex;gap:8px;align-items:center}.toplinks a{font-size:12px;color:#c4b1c1;padding:9px 12px;border-radius:8px}.toplinks a:hover{background:rgba(180,15,125,.2);color:white}.content{padding:25px 30px 42px;max-width:1600px;margin:auto}.hero{background:linear-gradient(135deg,#08080a 0%,#0a090a 30%,#d41688b0 60%,#ff0199a8 80%,#ff0199c9 100%);padding:25px 30px;border-radius:16px;display:flex;justify-content:space-between;align-items:center;gap:20px;color:white;margin-bottom:23px;min-height:118px;position:relative;overflow:hidden}.hero:after{content:'';position:absolute;width:260px;height:260px;border-radius:50%;right:8%;top:-150px;background:rgba(255,255,255,.1)}.hero h1{font-size:22px;margin:0 0 7px;position:relative;z-index:1}.hero p{margin:0;color:#f6d9ed;font-size:12px;position:relative;z-index:1}.hero-date{position:relative;z-index:1;text-align:right;padding:10px 16px;border:1px solid rgba(255,255,255,.17);border-radius:10px;background:rgba(255,255,255,.08);font-size:12px}.hero-date strong{display:block;font-size:18px;margin-bottom:3px}.alert{padding:12px 15px;border-radius:10px;margin:0 0 17px;font-size:13px}.alert.ok{background:#e7f5ed;color:#17613f;border:1px solid #c9ead7}.alert.err{background:#fff0f2;color:#9f2440;border:1px solid #f4cbd4}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px;margin-bottom:22px}.stat,.panel,.book-card,.empty{background:white;border:1px solid var(--line);border-radius:13px;box-shadow:var(--shadow)}.stat{padding:18px 16px;text-align:center}.stat strong{font-size:25px;color:#17121a;display:block}.stat span{font-size:10px;color:#806c7d;text-transform:uppercase;letter-spacing:.5px}.stat small{display:block;color:#b16b9c;font-size:10px;margin-top:5px}.panel{padding:20px;margin-bottom:18px}.panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.panel h2,.panel h3{font-size:15px;margin:0;color:#211522}.subtle{font-size:12px;color:#907e8e}.btn{border:0;border-radius:8px;padding:10px 14px;background:var(--pink);color:#fff;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px}.btn:hover{background:#920b65}.btn.light{background:#f8eaf3;color:#94106a;border:1px solid #efd3e5}.btn.dark{background:#17121f}.btn.danger{background:#fff0f2;color:#a52643;border:1px solid #f1ccd5}.btn:disabled{opacity:.45;cursor:not-allowed}.grid-books{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px}.book-card{overflow:hidden;display:flex;flex-direction:column}.cover{height:180px;background:linear-gradient(145deg,#f8e8f2,#ead6e5);display:flex;align-items:center;justify-content:center;color:#a81779;font-size:48px;overflow:hidden}.cover img{width:100%;height:100%;object-fit:contain;background:#fbf8fa}.book-info{padding:13px;display:flex;flex-direction:column;flex:1}.book-info h3{font-size:13px;line-height:1.45;margin:0 0 5px;color:#211522}.book-info p{font-size:11px;color:#897989;margin:0 0 8px}.book-meta{font-size:10px;color:#9c8497;margin:0 0 10px}.book-actions{display:flex;gap:7px;margin-top:auto}.book-actions form{flex:1}.book-actions .btn{width:100%;padding:9px 7px;font-size:10px}.toolbar{display:flex;flex-wrap:wrap;gap:9px;margin-bottom:16px}.toolbar input,.toolbar select,.field input,.field textarea,.field select{border:1px solid #e8d8e5;border-radius:8px;padding:11px 12px;background:#fff;color:#241b25;outline:none;font-size:12px}.toolbar input:focus,.field input:focus,.field textarea:focus,.field select:focus{border-color:#ce58a4;box-shadow:0 0 0 3px rgba(180,15,125,.08)}.toolbar input{flex:1;min-width:190px}.table-wrap{overflow-x:auto}table{border-collapse:collapse;width:100%;min-width:650px}th{text-align:left;background:#fbf6fa;color:#856f81;font-size:10px;text-transform:uppercase;letter-spacing:.5px;padding:12px;border-bottom:1px solid var(--line)}td{padding:13px 12px;border-bottom:1px solid #f2eaf0;font-size:12px;color:#453746}tr:last-child td{border-bottom:0}.status{display:inline-block;border-radius:20px;padding:5px 9px;font-size:10px;font-weight:700;text-transform:capitalize}.status.success{background:#e7f5ed;color:#17613f}.status.pending{background:#fff5df;color:#8b5b08}.status.danger{background:#fff0f2;color:#a52643}.empty{text-align:center;padding:35px 20px;color:#8d7d89}.empty .big{font-size:32px;margin-bottom:8px}.empty h3{font-size:14px;color:#352638;margin:0 0 5px}.empty p{font-size:12px;margin:0}.two-col{display:grid;grid-template-columns:1.3fr 1fr;gap:17px}.notice{padding:13px;border:1px solid #f0e3ed;border-radius:10px;margin-bottom:9px;display:flex;justify-content:space-between;gap:12px}.notice.unread{background:#fff7fc;border-color:#e8c5dd}.notice strong{font-size:12px;display:block;margin-bottom:4px}.notice p{font-size:11px;color:#8c7b8b;margin:0}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{display:flex;flex-direction:column;gap:6px}.field.full{grid-column:1/-1}.field label{font-size:11px;font-weight:700;color:#6e596d}.field input,.field textarea{width:100%}.field textarea{min-height:80px;resize:vertical}.modal{display:none;position:fixed;inset:0;background:rgba(12,4,12,.58);z-index:80;align-items:center;justify-content:center;padding:18px}.modal.open{display:flex}.modal-box{background:white;border-radius:15px;width:min(500px,100%);padding:22px;box-shadow:0 20px 70px rgba(0,0,0,.25)}.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}.modal-head h3{font-size:16px;margin:0}.close{border:0;background:#f7edf4;border-radius:7px;padding:6px 10px;cursor:pointer}.footer-note{text-align:center;font-size:10px;color:#a28e9f;padding:12px}
@media(max-width:1150px){.grid-books{grid-template-columns:repeat(3,minmax(0,1fr))}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:760px){.brand{padding:22px 20px 17px}.brand img{width:68px;height:68px}.brand h2{font-size:13px}.sidebar{transform:translateX(-100%);width:250px}.sidebar.mobile-open{transform:translateX(0)}.sidebar.collapsed{width:260px}.main,.main.expanded{margin-left:0}.content{padding:17px 13px 30px}.hero{padding:21px;align-items:flex-start;flex-direction:column}.hero-date{text-align:left}.grid-books{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.cover{height:145px}.stats{gap:9px}.stat{padding:14px 8px}.stat strong{font-size:21px}.two-col{grid-template-columns:1fr}.toplinks a{padding:8px 7px;font-size:10px}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}}
@media(max-width:420px){.grid-books{grid-template-columns:1fr 1fr}.book-actions{flex-direction:column}.book-actions form{width:100%}.stats{grid-template-columns:repeat(2,1fr)}}

.teacher-predict-wrap{position:relative;flex:1;min-width:220px}.teacher-predict-wrap input{width:100%;box-sizing:border-box}.predictive-results{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ead8e5;border-top:0;border-radius:0 0 12px 12px;max-height:340px;overflow:auto;z-index:1300;display:none;box-shadow:0 12px 28px rgba(0,0,0,.12)}.predictive-results.visible{display:block}.predictive-item{display:block;width:100%;padding:12px 15px;text-align:left;border:0;border-bottom:1px solid #f2eaf0;background:#fff;cursor:pointer}.predictive-item:hover,.predictive-item:focus{background:#fcf3f9;outline:none}.pred-title{font-weight:600;color:#1a1a1a;font-size:14px}.pred-author{color:#766575;font-size:12px;margin-top:3px}.pred-badge{display:inline-block;margin-top:5px;padding:2px 8px;border-radius:10px;background:#f7e8f1;color:#8e1769;font-size:10px}.pred-score{float:right;color:#8e1769;font-size:11px}

</style>
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
    <div class="brand"><img src="../img/agustinnb.png" alt="St. Agnes Academy logo" onerror="this.style.display='none'"><h2>ST. AGNES ACADEMY</h2><p>Caloocan Inc.</p><div class="who"><?=thEsc($fullName)?><small><?=thEsc($role === 'admin' ? 'System Administrator · ' : 'Teacher · ')?>ID: <?=thEsc($displayId)?></small></div></div>
    <nav class="nav">
        <?php $navItems = ['dashboard'=>['▦','Dashboard'],'catalog'=>['▤','Browse Books'],'requests'=>['▧','My Requests'],'borrowings'=>['⇄','My Borrowings'],'reservations'=>['◷','Reservations'],'history'=>['▤','Borrowing History'],'notifications'=>['♧','Notifications'],'profile'=>['◉','My Profile']]; foreach($navItems as $key=>$item): ?>
        <a href="?section=<?=$key?>" class="<?=$section===$key?'active':''?>"><span class="ico"><?=$item[0]?></span><span class="label"><?=$item[1]?></span><?php if($key==='notifications' && count($unreadNotifications)): ?><span class="status pending"><?=count($unreadNotifications)?></span><?php endif; ?></a>
        <?php endforeach; ?>
        <a href="library_workspace.php?tab=purchases" class="<?=($section==='purchase_requests'?'active':'')?>"><span class="ico">₱</span><span class="label">Purchase Requests</span></a>
        <a href="library_workspace.php?tab=class_sets"><span class="ico">▤</span><span class="label">Class Set Requests</span></a>
        <?php if($role === 'admin'): ?><a href="../admin_dashboard.php"><span class="ico">⚙</span><span class="label">Admin Dashboard</span></a><?php endif; ?>
            </nav>
    <div class="side-bottom"><?php if ($role === 'admin'): ?><a href="../admin_dashboard.php" class="logout" style="margin-bottom:9px;background:#fce7f3;color:#9d174d;border-color:#f9a8d4;font-weight:700">← Back to Admin Dashboard</a><?php endif; ?><a class="logout" href="../admin_logout.php">↪ &nbsp;Logout</a></div>
</aside>
<main class="main" id="main">
<header class="topbar"><button class="hamb" id="hamb" aria-label="Toggle navigation">☰</button><div class="toplinks"><?php if($role==='admin'): ?><a href="../admin_dashboard.php">Admin Dashboard</a><?php endif; ?></div></header>
<div class="content">
<div class="hero"><div><h1><?= $section==='dashboard' ? 'Teacher Dashboard' : thEsc($navItems[$section][1]) ?></h1><p>Welcome back, <?=thEsc($fullName)?> · St. Agnes Academy Library Management System</p></div><div class="hero-date"><strong><?=date('h:i A')?></strong><?=date('F j, Y')?></div></div>
<?php if($msg): ?><div class="alert ok">✓ <?=thEsc($msg)?></div><?php endif; ?><?php if($error): ?><div class="alert err">! <?=thEsc($error)?></div><?php endif; ?><?php if($dataError): ?><div class="alert err">Unable to load some library data: <?=thEsc($dataError)?></div><?php endif; ?>

<?php if($section==='dashboard'): ?>
<div class="stats">
 <div class="stat"><strong><?=count($activeBorrowings)?></strong><span>Active Borrowings</span><small><?=count($overdueBorrowings)?> overdue</small></div>
 <div class="stat"><strong><?=count($pendingRequests)?></strong><span>Pending Requests</span><small><?=count($approvedRequests)?> approved</small></div>
 <div class="stat"><strong><?=count($pendingReservations)?></strong><span>Reservations</span><small><?=count($historyBorrowings)?> returned books</small></div>
 <div class="stat"><strong><?=count($unreadNotifications)?></strong><span>Unread Notifications</span><small><?=count($unpaidFines)?> unpaid fine records</small></div>
</div>
<div class="two-col">
 <section class="panel"><div class="panel-head"><h2>Quick Actions</h2><span class="subtle">Library services</span></div><div style="display:flex;flex-wrap:wrap;gap:9px"><a class="btn" href="?section=catalog">⌕ Browse Catalog</a><a class="btn light" href="?section=requests">▧ View Requests</a><a class="btn light" href="?section=borrowings">⇄ My Borrowings</a><a class="btn light" href="?section=reservations">◷ Reservations</a><a class="btn light" href="library_workspace.php?tab=purchases">₱ Purchase Requests</a><a class="btn light" href="library_workspace.php?tab=class_sets">▤ Class Set Requests</a></div></section>
 <section class="panel"><div class="panel-head"><h2>Account Summary</h2><a class="subtle" href="?section=profile">View profile →</a></div><p style="font-size:12px;margin:8px 0;color:#766578">Name: <b><?=thEsc($profile['full_name']??$fullName)?></b></p><p style="font-size:12px;margin:8px 0;color:#766578">Teacher ID: <b><?=thEsc($profile['user_id']??$displayId)?></b></p><p style="font-size:12px;margin:8px 0;color:#766578">Email: <b><?=thEsc($profile['email']??'Not provided')?></b></p><p style="font-size:12px;margin:8px 0;color:#766578">Account role: <b><?=thEsc(ucfirst($role))?></b></p></section>
</div>
<section class="panel"><div class="panel-head"><h2>Recently Added Books</h2><a class="subtle" href="?section=catalog">Browse all books →</a></div>
<div class="grid-books"><?php foreach(array_slice(array_reverse($books),0,4) as $book): $cover=teacherBookCover($book); ?><article class="book-card"><div class="cover"><?php if($cover): ?><img src="<?=thEsc($cover)?>" alt="Book cover"><?php else: ?>▤<?php endif; ?></div><div class="book-info"><h3><?=thEsc($book['title']??'Untitled')?></h3><p><?=thEsc($book['author']??'Unknown author')?></p><div class="book-meta"><?=thEsc($book['categories']['name']??'Uncategorized')?> · <?=max(0,(int)($book['available']??0))?> available</div><div class="book-actions"><a class="btn light" href="?section=catalog&search=<?=rawurlencode($book['title']??'')?>">View in catalog</a></div></div></article><?php endforeach; ?></div>
</section>
<?php elseif($section==='catalog'): ?>
<section class="panel"><div class="panel-head"><h2>Library Catalog</h2><span class="subtle"><?=count($filteredBooks)?> matching books</span></div>
<form class="toolbar" method="get"><input type="hidden" name="section" value="catalog"><div class="teacher-predict-wrap"><input id="teacherBookSearch" autocomplete="off" name="search" value="<?=thEsc($search)?>" placeholder="Search title, author, ISBN, or keyword..." oninput="getTeacherPredictions(this.value)" onkeydown="handleTeacherPredictionKey(event)"><div class="predictive-results" id="teacherPredictiveResults"></div></div><select name="category"><option value="">All categories</option><?php foreach($categories as $cat): ?><option value="<?=thEsc($cat['id'])?>" <?=$categoryFilter==(string)$cat['id']?'selected':''?>><?=thEsc($cat['name']??'Category')?></option><?php endforeach; ?></select><select name="availability"><option value="">All availability</option><option value="available" <?=$availabilityFilter==='available'?'selected':''?>>Available</option><option value="unavailable" <?=$availabilityFilter==='unavailable'?'selected':''?>>Unavailable</option></select><button class="btn">Search</button><a class="btn light" href="?section=catalog">Reset</a></form>
<?php if($filteredBooks): ?><div class="grid-books"><?php foreach($filteredBooks as $book): $cover=teacherBookCover($book); $available=(int)($book['available']??0); ?><article class="book-card"><div class="cover"><?php if($cover): ?><img src="<?=thEsc($cover)?>" alt="Cover of <?=thEsc($book['title']??'book')?>"><?php else: ?>▤<?php endif; ?></div><div class="book-info"><h3><?=thEsc($book['title']??'Untitled')?></h3><p><?=thEsc($book['author']??'Unknown author')?></p><div class="book-meta">ISBN: <?=thEsc($book['isbn']??'N/A')?><br><?=thEsc($book['categories']['name']??'Uncategorized')?> · <?=thEsc($book['year_published']??'')?></div><div style="margin-bottom:10px"><span class="status <?=$available>0?'success':'danger'?>"><?=$available>0?$available.' available':'Unavailable'?></span></div><div class="book-actions"><button class="btn" type="button" onclick="openRequest('<?=thEsc($book['id'])?>','<?=thEsc($book['title']??'Book')?>',<?=$available?>)">Request</button></div></div></article><?php endforeach; ?></div><?php else: ?><div class="empty"><div class="big">⌕</div><h3>No books found</h3><p>Try a different keyword or clear your filters.</p></div><?php endif; ?></section>
<?php elseif($section==='requests'): ?>
<section class="panel"><div class="panel-head"><h2>My Book Requests</h2><a class="btn" href="?section=catalog">+ New Request</a></div><?php if($requests): ?><div class="table-wrap"><table><thead><tr><th>Book</th><th>Request Type</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($requests as $r): ?><tr><td><b><?=thEsc($r['books']['title']??'Book')?></b><br><span class="subtle"><?=thEsc($r['books']['author']??'')?></span></td><td><?=thEsc(ucfirst($r['request_type']??''))?></td><td><?=thEsc($r['created_at']??$r['request_date']??'—')?></td><td><span class="status <?=teacherStatusClass($r['status']??'Pending')?>"><?=thEsc($r['status']??'Pending')?></span></td><td><?php if(($r['status']??'')==='Pending'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>"><input type="hidden" name="action" value="cancel_request"><input type="hidden" name="request_id" value="<?=thEsc($r['id'])?>"><button class="btn danger" onclick="return confirm('Cancel this request?')">Cancel</button></form><?php elseif(($r['status']??'')==='Approved'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>"><input type="hidden" name="action" value="<?=$r['request_type']==='reserve'?'complete_approved_reservation':'complete_approved_borrow'?>"><input type="hidden" name="book_id" value="<?=thEsc($r['book_id'])?>"><button class="btn"><?=$r['request_type']==='reserve'?'Complete Reservation':'Complete Borrowing'?></button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty"><div class="big">▧</div><h3>No requests yet</h3><p>Browse the catalog and submit your first borrowing or reservation request.</p><br><a class="btn" href="?section=catalog">Browse Books</a></div><?php endif; ?></section>
<?php elseif($section==='borrowings' || $section==='history'): $rows=$section==='history'?$historyBorrowings:$activeBorrowings; ?>
<section class="panel"><div class="panel-head"><h2><?=$section==='history'?'Borrowing History':'Current Borrowings'?></h2><span class="subtle"><?=count($rows)?> records</span></div><?php if($rows): ?><div class="table-wrap"><table><thead><tr><th>Book</th><th>Borrowed</th><th>Due Date</th><th>Returned</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $b): $overdue=($b['status']??'')!=='Returned'&&!empty($b['due_date'])&&strtotime($b['due_date'])<strtotime(date('Y-m-d')); $st=$overdue?'Overdue':($b['status']??'Borrowed'); ?><tr><td><b><?=thEsc($b['books']['title']??'Book')?></b><br><span class="subtle"><?=thEsc($b['books']['author']??'')?></span></td><td><?=thEsc($b['borrow_date']??'—')?></td><td><?=thEsc($b['due_date']??'—')?></td><td><?=thEsc($b['return_date']??'—')?></td><td><span class="status <?=teacherStatusClass($st)?>"><?=thEsc($st)?></span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty"><div class="big">⇄</div><h3>No borrowing records</h3><p>Your approved and completed borrowings will appear here.</p></div><?php endif; ?></section>
<?php elseif($section==='reservations'): ?>
<section class="panel"><div class="panel-head"><h2>My Reservations</h2><a class="btn" href="?section=catalog">Find a Book</a></div><?php if($reservations): ?><div class="table-wrap"><table><thead><tr><th>Book</th><th>Reservation Date</th><th>Expiry Date</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($reservations as $r): ?><tr><td><b><?=thEsc($r['books']['title']??'Book')?></b><br><span class="subtle"><?=thEsc($r['books']['author']??'')?></span></td><td><?=thEsc($r['reservation_date']??'—')?></td><td><?=thEsc($r['expiry_date']??'—')?></td><td><span class="status <?=teacherStatusClass($r['status']??'Pending')?>"><?=thEsc($r['status']??'Pending')?></span></td><td><?php if(($r['status']??'')==='Pending'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>"><input type="hidden" name="action" value="cancel_reservation"><input type="hidden" name="reservation_id" value="<?=thEsc($r['id'])?>"><button class="btn danger" onclick="return confirm('Cancel this reservation?')">Cancel</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty"><div class="big">◷</div><h3>No reservations</h3><p>Your reservations will appear here.</p></div><?php endif; ?></section>
<?php elseif($section==='notifications'): ?>
<section class="panel"><div class="panel-head"><h2>Notifications</h2><span class="subtle"><?=count($unreadNotifications)?> unread</span></div><?php if($notifications): foreach($notifications as $n): ?><div class="notice <?=empty($n['is_read'])?'unread':''?>"><div><strong><?=thEsc($n['title']??'Library Notification')?></strong><p><?=thEsc($n['message']??'')?> · <?=thEsc($n['created_at']??'')?></p></div><?php if(empty($n['is_read'])): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>"><input type="hidden" name="action" value="mark_notification_read"><input type="hidden" name="notification_id" value="<?=thEsc($n['id'])?>"><button class="btn light">Mark read</button></form><?php else: ?><span class="status success">Read</span><?php endif; ?></div><?php endforeach; else: ?><div class="empty"><div class="big">♧</div><h3>No notifications</h3><p>Library updates will appear here.</p></div><?php endif; ?></section>
<?php elseif($section==='profile'): ?>
<section class="panel"><div class="panel-head"><h2>My Profile</h2><span class="subtle">Manage your basic account information</span></div><form method="post"><input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>"><input type="hidden" name="action" value="update_profile"><div class="form-grid"><div class="field"><label>Full Name</label><input name="full_name" required value="<?=thEsc($profile['full_name']??'')?>"></div><div class="field"><label>Email Address</label><input type="email" name="email" required value="<?=thEsc($profile['email']??'')?>"></div><div class="field"><label>Username</label><input value="<?=thEsc($profile['username']??'')?>" disabled></div><div class="field"><label>Teacher ID</label><input value="<?=thEsc($profile['user_id']??$displayId)?>" disabled></div><div class="field"><label>Account Role</label><input value="<?=thEsc(ucfirst($role))?>" disabled></div></div><br><button class="btn">Save Profile</button></form></section>
<?php endif; ?>
<div class="footer-note">St. Agnes Academy · Library Management System · Teacher Workspace</div>
</div></main></div>

<div class="modal" id="requestModal"><div class="modal-box"><div class="modal-head"><h3>Submit Book Request</h3><button class="close" type="button" onclick="closeRequest()">✕</button></div><p id="requestBookTitle" class="subtle" style="margin:0 0 15px"></p><form method="post"><input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>"><input type="hidden" name="action" value="submit_request"><input type="hidden" name="book_id" id="requestBookId"><div class="field"><label>Request Type</label><select name="request_type" id="requestType"><option value="borrow">Borrow this book</option><option value="reserve">Reserve this book</option></select></div><br><div class="field"><label>Purpose / Notes (optional)</label><textarea name="purpose" placeholder="e.g. For classroom instruction, lesson preparation, or research"></textarea></div><br><p class="subtle" style="line-height:1.5">Your request will be reviewed by the librarian or administrator. Once approved, return to My Requests to complete the borrowing or reservation.</p><button class="btn" style="width:100%">Submit Request</button></form></div></div>
<script>
const sidebar=document.getElementById('sidebar'),main=document.getElementById('main');document.getElementById('hamb').addEventListener('click',()=>{if(window.innerWidth<=760){sidebar.classList.toggle('mobile-open')}else{sidebar.classList.toggle('collapsed');main.classList.toggle('expanded')}});
function openRequest(id,title,available){document.getElementById('requestBookId').value=id;document.getElementById('requestBookTitle').textContent=title;const type=document.getElementById('requestType');type.value=available>0?'borrow':'reserve';document.getElementById('requestModal').classList.add('open')}
function closeRequest(){document.getElementById('requestModal').classList.remove('open')}document.getElementById('requestModal').addEventListener('click',e=>{if(e.target.id==='requestModal')closeRequest()});

let teacherPredictionTimer=null, teacherPredictionRequest=0;
async function getTeacherPredictions(query){
 const box=document.getElementById('teacherPredictiveResults');if(!box)return;clearTimeout(teacherPredictionTimer);
 if(query.trim().length<2){box.classList.remove('visible');box.innerHTML='';return;}
 teacherPredictionTimer=setTimeout(async()=>{const id=++teacherPredictionRequest;box.innerHTML='<div class="predictive-item">Checking stored library books...</div>';box.classList.add('visible');
  try{const res=await fetch('../../../api/predict_search.php',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({partial_query:query.trim()})});const data=await res.json();if(id!==teacherPredictionRequest)return;if(!res.ok||data.success!==true)throw new Error(data.message||'Search failed');const rows=Array.isArray(data.predictions)?data.predictions:[];box.innerHTML='';if(!rows.length){box.innerHTML='<div class="predictive-item">No matching books found in the system.</div>';return;}
   rows.forEach(book=>{const btn=document.createElement('button');btn.type='button';btn.className='predictive-item';const score=document.createElement('span');score.className='pred-score';const availableCount=Number(book.available_count ?? book.available ?? 0);score.textContent=book.availability_label||(availableCount>0?availableCount+' available':'Unavailable');const title=document.createElement('div');title.className='pred-title';title.textContent=book.title;const author=document.createElement('div');author.className='pred-author';author.textContent='by '+(book.author||'Unknown author');const badge=document.createElement('span');badge.className='pred-badge';badge.textContent=book.category||'Uncategorized';btn.append(score,title,author,badge);btn.addEventListener('click',()=>{const input=document.getElementById('teacherBookSearch');input.value=book.title;box.classList.remove('visible');input.form.submit();});box.appendChild(btn);});
  }catch(err){if(id!==teacherPredictionRequest)return;console.error(err);box.innerHTML='<div class="predictive-item">Suggestions unavailable. You can still search.</div>';}
 },220);
}
function handleTeacherPredictionKey(event){if(event.key==='Escape'){const box=document.getElementById('teacherPredictiveResults');if(box)box.classList.remove('visible');}if(event.key==='Enter'){const box=document.getElementById('teacherPredictiveResults');if(box)box.classList.remove('visible');}}
document.addEventListener('click',event=>{const box=document.getElementById('teacherPredictiveResults'),input=document.getElementById('teacherBookSearch');if(box&&input&&!box.contains(event.target)&&event.target!==input)box.classList.remove('visible');});

</script>
</body></html>
