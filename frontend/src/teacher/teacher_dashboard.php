<?php
// teacher_dashboard.php - St. Agnes Academy Library
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

$profileTab = $_GET['tab'] ?? 'overview';
if (!in_array($profileTab, ['overview', 'edit', 'security'], true)) $profileTab = 'overview';

if (empty($_SESSION['teacher_csrf'])) $_SESSION['teacher_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['teacher_csrf'];

// ===== HANDLE PROFILE POSTS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section === 'profile') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) thRedirect('profile', 'Session expired.', false);
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_photo') {
            $remove = !empty($_POST['remove_photo']);
            $avatarUrl = '';
            if (!$remove && !empty($_FILES['avatar']['tmp_name'])) {
                $file = $_FILES['avatar'];
                if ($file['size'] > 2*1024*1024) thRedirect('profile', 'Photo must be under 2MB.', false);
                $mime = mime_content_type($file['tmp_name']);
                if (!in_array($mime, ['image/jpeg','image/png','image/webp'])) thRedirect('profile', 'Only JPG, PNG, or WebP allowed.', false);
                $avatarUrl = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));
            }
            teacherSupabaseRequest('users?id=eq.' . rawurlencode($userId), 'PATCH', ['avatar_url' => $avatarUrl]);
            if ($avatarUrl === '') unset($_SESSION['avatar_url']); else $_SESSION['avatar_url'] = $avatarUrl;
            thRedirect('profile&tab=edit', 'Profile photo updated.');
        }
        
        if ($action === 'update_info') {
            $fname = trim($_POST['first_name'] ?? '');
            $mname = trim($_POST['middle_name'] ?? '');
            $lname = trim($_POST['last_name'] ?? '');
            $newName = trim("$fname $mname $lname");
            $phone = trim($_POST['phone'] ?? '');
            $dept = trim($_POST['department'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $birthdate = trim($_POST['birthdate'] ?? '');
            
            teacherSupabaseRequest('users?id=eq.' . rawurlencode($userId), 'PATCH', [
                'first_name' => $fname, 'middle_name' => $mname, 'last_name' => $lname,
                'full_name' => $newName, 'phone' => $phone, 'department' => $dept,
                'address' => $address, 'birthdate' => $birthdate ?: null
            ]);
            $_SESSION['full_name'] = $newName;
            $fullName = $newName;
            thRedirect('profile&tab=edit', 'Profile details updated.');
        }

        if ($action === 'update_security') {
            $newPhone = trim($_POST['new_phone'] ?? '');
            $currentPass = $_POST['current_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';

            $updateData = [];
            if (!empty($newPhone)) $updateData['phone'] = $newPhone;

            if (!empty($newPass)) {
                if ($newPass !== $confirmPass) thRedirect('profile&tab=security', 'New passwords do not match.', false);
                $userRec = teacherSupabaseRequest('users?select=password&id=eq.' . rawurlencode($userId));
                if (empty($userRec) || $userRec[0]['password'] !== $currentPass) thRedirect('profile&tab=security', 'Incorrect current password.', false);
                $updateData['password'] = $newPass;
            }

            if (!empty($updateData)) {
                teacherSupabaseRequest('users?id=eq.' . rawurlencode($userId), 'PATCH', $updateData);
                thRedirect('profile&tab=security', 'Security settings updated.');
            }
            thRedirect('profile&tab=security', 'No changes made.');
        }
    } catch (Exception $e) {
        thRedirect('profile', 'Error: ' . $e->getMessage(), false);
    }
}

// ===== STANDARD ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section !== 'profile') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) thRedirect($section, 'Session expired.', false);
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'submit_request') {
            $bookId = trim((string)($_POST['book_id'] ?? ''));
            $requestType = $_POST['request_type'] ?? '';
            $purpose = trim((string)($_POST['purpose'] ?? ''));
            $book = teacherSupabaseRequest('books?select=id,title,available&id=eq.' . rawurlencode($bookId));
            if (!$book) thRedirect('catalog', 'Book not found.', false);
            $existing = teacherSupabaseRequest('book_requests?select=id&user_id=eq.' . rawurlencode($userId) . '&book_id=eq.' . rawurlencode($bookId) . '&status=eq.Pending');
            if ($existing) thRedirect('requests', 'You already have a pending request.', false);
            
            teacherSupabaseRequest('book_requests', 'POST', [
                'user_id' => $userId, 'book_id' => $bookId, 'request_type' => $requestType,
                'student_id' => $displayId, 'full_name' => $fullName,
                'year_level' => 'Faculty', 'section' => 'Teaching Staff',
                'purpose' => $purpose, 'status' => 'Pending'
            ]);
            thRedirect('requests', 'Request submitted.');
        }
        if ($action === 'cancel_request') {
            teacherSupabaseRequest('book_requests?id=eq.' . rawurlencode($_POST['request_id']) . '&user_id=eq.' . rawurlencode($userId), 'PATCH', ['status' => 'Cancelled']);
            thRedirect('requests', 'Request cancelled.');
        }
        if ($action === 'mark_notification_read') {
            teacherSupabaseRequest('notifications?id=eq.' . rawurlencode($_POST['notification_id']) . '&user_id=eq.' . rawurlencode($userId), 'PATCH', ['is_read' => true]);
            thRedirect('notifications', 'Marked as read.');
        }
    } catch (Exception $e) { thRedirect($section, 'Error: ' . $e->getMessage(), false); }
}

// ===== LOAD DATA =====
$books = $categories = $requests = $borrowings = $reservations = $notifications = $fines = [];
try {
    $books = teacherSupabaseRequest('books?select=*,categories(name)&order=title.asc');
    $categories = teacherSupabaseRequest('categories?select=*&order=name.asc');
    $requests = teacherSupabaseRequest('book_requests?select=*,books(title,author,cover_image)&user_id=eq.' . rawurlencode($userId) . '&order=created_at.desc');
    $borrowings = teacherSupabaseRequest('borrowings?select=*,books(title,author,cover_image)&user_id=eq.' . rawurlencode($userId) . '&order=borrow_date.desc');
    $reservations = teacherSupabaseRequest('reservations?select=*,books(title,author,cover_image,available)&user_id=eq.' . rawurlencode($userId) . '&order=reservation_date.desc');
    $notifications = teacherSupabaseRequest('notifications?select=*&user_id=eq.' . rawurlencode($userId) . '&order=created_at.desc&limit=50');
    $fines = teacherSupabaseRequest('fines?select=*&user_id=eq.' . rawurlencode($userId));
} catch (Exception $e) { $dataError = $e->getMessage(); }

$search = trim((string)($_GET['search'] ?? ''));
$categoryFilter = (string)($_GET['category'] ?? '');
$availabilityFilter = (string)($_GET['availability'] ?? '');
$teacherSearchTerms = preg_split('/\\s+/u', mb_strtolower(preg_replace('/[^\\p{L}\\p{N}]+/u', ' ', $search), 'UTF-8'));
$teacherStop = array_fill_keys(preg_split('/\\s+/u', 'ang mga ng na sa si kay kina at o or and the a an of for about tungkol libro aklat book books hanap hanapin naghahanap maghanap gusto ko ako po ba may mayroon meron'), true);
$teacherSearchTerms = array_values(array_filter($teacherSearchTerms, function($term) use ($teacherStop) { return mb_strlen($term, 'UTF-8') > 1 && !isset($teacherStop[$term]); }));
$filteredBooks = array_values(array_filter($books, function($book) use ($search, $teacherSearchTerms, $categoryFilter, $availabilityFilter) {
    $haystack = mb_strtolower(implode(' ', [$book['title'] ?? '', $book['author'] ?? '', $book['isbn'] ?? '', $book['keywords'] ?? '', $book['description'] ?? '']), 'UTF-8');
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
try { 
    $u = teacherSupabaseRequest('users?select=*&id=eq.' . rawurlencode($userId)); 
    if ($u) { $profile = array_merge($profile, $u[0]); if (!empty($u[0]['avatar_url'])) $_SESSION['avatar_url'] = $u[0]['avatar_url']; } 
} catch (Exception $e) {}

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
/* [VARIABLES & RESET] */
:root{--ink:#08080a;--pink:#b40f7d;--pink2:#d41688;--rose:#ff0199;--paper:#f7f1f5;--muted:#8d7d89;--line:#eeddea;--white:#fff;--success:#18794e;--danger:#b42342;--shadow:0 8px 28px rgba(42,12,35,.06)}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Inter,Arial,sans-serif;background:var(--paper);color:#17121a}body{overflow-x:hidden;transition:background-color .35s ease,color .35s ease}a{color:inherit;text-decoration:none}button,input,select,textarea{font:inherit}.app{min-height:100vh}

/* [SIDEBAR & NAV] */
.sidebar{position:fixed;inset:0 auto 0 0;width:240px;background:#010107;color:#e8e0e8;z-index:30;display:flex;flex-direction:column;transition:.25s;overflow-y:auto;border-right:1px solid #29212a}
.sidebar.collapsed{width:70px}.sidebar.collapsed .brand{padding:18px 8px;text-align:center}.sidebar.collapsed .brand img{width:48px;height:48px;margin:0 auto 8px}.sidebar.collapsed .brand h2,.sidebar.collapsed .brand p,.sidebar.collapsed .brand .who,.sidebar.collapsed .nav .label{display:none}.sidebar.collapsed .nav a{justify-content:center;padding:12px 8px}.sidebar.collapsed .nav .ico{width:auto}
.brand{padding:22px 20px 17px;border-bottom:1px solid #29212a}.brand img{width:72px;height:72px;object-fit:contain;display:block;margin:0 0 11px}.brand h2{font-size:13px;margin:0;color:#f6eaf2}.brand p{font-size:11px;color:#9c8295;margin:4px 0 0}.brand .who{margin-top:14px;padding-top:12px;border-top:1px solid #29212a;font-size:12px}.brand small{display:block;color:#8b7787;font-size:10px;margin-top:4px}
.nav{padding:13px 0;flex:1}.nav a{display:flex;align-items:center;gap:13px;padding:12px 22px;border-left:3px solid transparent;color:#a899a8;font-size:13px;transition:.2s}.nav a:hover,.nav a.active{background:rgba(180,15,125,.17);border-left-color:var(--pink);color:#fff}.nav .ico{width:22px;text-align:center;font-size:17px}

/* Sidebar Notification Badge */
.notification-badge { background: #e51d66; color: #ffffff !important; font-size: 10px; font-weight: 700; min-width: 20px; height: 20px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; padding: 0 6px; margin-left: auto; }

/* [MAIN & TOPBAR] */
.main{margin-left:240px;min-height:100vh;transition:.25s}.main.expanded{margin-left:70px}
.topbar{height:56px;background:#010107;position:sticky;top:0;z-index:20;display:flex;align-items:center;justify-content:space-between;padding:0 22px;border-bottom:1px solid #29212a}
.hamb{width:38px;height:38px;border:1px solid #302530;background:#111015;color:#f5e9f2;border-radius:9px;cursor:pointer;font-size:19px}
.toplinks{display:flex;gap:8px;align-items:center;margin-left:auto}
.header-action{position:relative;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:36px;padding:0 12px;border:1px solid rgba(255,255,255,.13);border-radius:10px;background:rgba(255,255,255,.055);color:#f8eaf4!important;font-size:12px;font-weight:600;cursor:pointer;transition:.2s}
.header-action:hover{background:rgba(212,22,136,.22);border-color:rgba(255,1,153,.45)}
.header-action.logout-action{background:#b40f7d;border-color:#b40f7d}
.header-badge{position:absolute;top:-4px;right:-4px;background:#ff0199;color:#fff;font-size:9px;font-weight:800;min-width:17px;height:17px;border-radius:20px;display:flex;align-items:center;justify-content:center;border:2px solid #08080a}

/* [HERO & PANELS] */
.content{padding:25px 30px 42px;max-width:1600px;margin:auto}
.hero{background:linear-gradient(135deg,#08080a 0%,#0a090a 30%,#d41688b0 60%,#ff0199a8 80%,#ff0199c9 100%);padding:25px 30px;border-radius:16px;display:flex;justify-content:space-between;align-items:center;gap:20px;color:white;margin-bottom:23px;min-height:118px;position:relative;overflow:hidden}
.hero:after{content:'';position:absolute;width:260px;height:260px;border-radius:50%;right:8%;top:-150px;background:rgba(255,255,255,.1)}
.hero h1{font-size:22px;margin:0 0 7px}.hero p{margin:0;color:#f6d9ed;font-size:12px}.hero-date{position:relative;z-index:1;text-align:right;padding:10px 16px;border:1px solid rgba(255,255,255,.17);border-radius:10px;background:rgba(255,255,255,.08);font-size:12px;min-width:138px}.hero-date strong{display:block;font-size:18px;margin-bottom:3px}
.alert{padding:12px 15px;border-radius:10px;margin:0 0 17px;font-size:13px}.alert.ok{background:#e7f5ed;color:#17613f;border:1px solid #c9ead7}.alert.err{background:#fff0f2;color:#9f2440;border:1px solid #f4cbd4}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px;margin-bottom:22px}
.stat,.panel,.book-card,.empty{background:white;border:1px solid var(--line);border-radius:13px;box-shadow:var(--shadow)}
.stat{padding:18px 16px;text-align:center}.stat strong{font-size:25px;display:block}.stat span{font-size:10px;color:#806c7d;text-transform:uppercase}.stat small{display:block;color:#b16b9c;font-size:10px;margin-top:5px}
.panel{padding:20px;margin-bottom:18px}.panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.panel h2{font-size:15px;margin:0}
.btn{border:0;border-radius:8px;padding:10px 14px;background:var(--pink);color:#fff;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}.btn:hover{background:#920b65}.btn.light{background:#f8eaf3;color:#94106a}.btn.danger{background:#fff0f2;color:#a52643}

/* [PROFILE TABS & FORMS - ENHANCED] */
.profile-hero-v2 { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; padding: 26px 30px; margin-bottom: 24px; border-radius: 18px; background: radial-gradient(circle at 88% 0%, rgba(255,1,153,.42), transparent 32%), linear-gradient(115deg, #08080a 0%, #291126 48%, #d41688 100%); color: #fff !important; box-shadow: 0 12px 28px rgba(85,12,66,.12); position: relative; overflow: hidden; }
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

.profile-tabs-container { background: #fff; border-radius: 16px; border: 1px solid var(--line); box-shadow: var(--shadow); overflow: hidden; width: 100%; }
.profile-tabs-nav { display: flex; border-bottom: 1px solid var(--line); background: #faf8fa; padding: 0 20px; gap: 24px; overflow-x: auto; }
.profile-tab-btn { border: 0; background: transparent; padding: 18px 0; font-size: 14px; font-weight: 600; color: var(--muted); cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; }
.profile-tab-btn.active { color: var(--pink); border-bottom-color: var(--pink); }
.profile-tab-btn:hover:not(.active) { color: #211522; }
.profile-tab-pane { display: none; padding: 28px; width: 100%; }
.profile-tab-pane.active { display: block; animation: fadeIn .3s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }

.overview-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.overview-item { padding: 16px; border-radius: 10px; border: 1px solid var(--line); background: #fbfafa; }
.overview-item span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); font-weight: 700; margin-bottom: 6px; }
.overview-item strong { display: block; font-size: 14px; color: #211522; }

.profile-photo-upload{display:flex;align-items:center;gap:20px;flex-wrap:wrap;padding:20px;border-radius:12px;border:1px dashed var(--line);background:rgba(180,15,125,.02);margin-bottom:24px}
.profile-photo-preview{width:84px;height:84px;border-radius:50%;overflow:hidden;display:grid;place-items:center;background:var(--pink);color:#fff;font-size:32px;font-weight:800;box-shadow:0 4px 14px rgba(0,0,0,.1)}
.profile-photo-preview img{width:100%;height:100%;object-fit:cover}
.profile-photo-actions{display:flex;flex-direction:column;gap:10px;align-items:flex-start}
.profile-photo-actions label{display:inline-flex;align-items:center;gap:8px;cursor:pointer;padding:10px 18px;border-radius:8px;border:1px solid var(--line);background:#fff;color:#211522 !important;font-size:13px;font-weight:600;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.profile-photo-actions label:hover{background:#fbf6fa}
.profile-photo-actions input[type="file"]{display:none}
.profile-photo-remove{background:transparent;border:0;color:var(--danger) !important;font-size:12px;font-weight:600;cursor:pointer;padding:0;text-decoration:underline}
.profile-photo-actions small{color:var(--muted);font-size:11px}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-grid .full{grid-column:1/-1}
.field{display:flex;flex-direction:column;gap:6px}
.field label{font-size:11px;font-weight:700;color:#6e596d;text-transform:uppercase;letter-spacing:.5px}
.field input,.field select,.field textarea{padding:12px;border:1px solid var(--line);border-radius:8px;font-size:14px;background:#fff;color:#1a1a1a}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--pink);outline:none;box-shadow:0 0 0 3px rgba(180,15,125,.1)}
.field input:disabled,.field select:disabled{background:#f5f3f0;color:var(--muted);cursor:not-allowed}
.field .hint{font-size:11px;color:var(--muted);margin-top:2px}
.profile-actions{display:flex;justify-content:flex-end;margin-top:24px;padding-top:20px;border-top:1px solid var(--line)}
.btn-save{background:var(--pink);color:#fff;border:0;border-radius:8px;padding:12px 28px;font-size:14px;font-weight:600;cursor:pointer;transition:background 0.2s}
.btn-save:hover{background:#8a0a5f}

/* [TABLES & BOOK CARDS] */
.grid-books{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px}
.book-card{overflow:hidden;display:flex;flex-direction:column}.cover{height:180px;background:linear-gradient(145deg,#f8e8f2,#ead6e5);display:flex;align-items:center;justify-content:center;color:#a81779;font-size:48px;overflow:hidden}.cover img{width:100%;height:100%;object-fit:contain;background:#fbf8fa}
.book-info{padding:13px;display:flex;flex-direction:column;flex:1}.book-info h3{font-size:13px;margin:0 0 5px}.book-info p{font-size:11px;color:#897989;margin:0 0 8px}.book-meta{font-size:10px;color:#9c8497;margin:0 0 10px}.book-actions{display:flex;gap:7px;margin-top:auto}.book-actions .btn{width:100%;padding:9px 7px;font-size:10px}
.table-wrap{overflow-x:auto}table{border-collapse:collapse;width:100%;min-width:650px}th{text-align:left;background:#fbf6fa;color:#856f81;font-size:10px;text-transform:uppercase;padding:12px;border-bottom:1px solid var(--line)}td{padding:13px 12px;border-bottom:1px solid #f2eaf0;font-size:12px}tr:last-child td{border-bottom:0}
.status{display:inline-block;border-radius:20px;padding:5px 9px;font-size:10px;font-weight:700;text-transform:capitalize}.status.success{background:#e7f5ed;color:#17613f}.status.pending{background:#fff5df;color:#8b5b08}.status.danger{background:#fff0f2;color:#a52643}

/* [UNIVERSAL DARK MODE] */
:root{--dm-bg:#0f0e13;--dm-surface:#1a1720;--dm-surface-2:#221d2a;--dm-border:#3a2e3d;--dm-text:#f3edf5;--dm-text-muted:#c2b3c6}
body[data-theme="dark"]{background:var(--dm-bg)!important;color:var(--dm-text)!important}
body[data-theme="dark"] .main,body[data-theme="dark"] .content{background:var(--dm-bg)!important}
body[data-theme="dark"] h1,body[data-theme="dark"] h2,body[data-theme="dark"] h3,body[data-theme="dark"] h4,body[data-theme="dark"] p,body[data-theme="dark"] span,body[data-theme="dark"] label,body[data-theme="dark"] td,body[data-theme="dark"] th,body[data-theme="dark"] strong,body[data-theme="dark"] .value{color:var(--dm-text)!important}
body[data-theme="dark"] .panel,body[data-theme="dark"] .stat,body[data-theme="dark"] .book-card,body[data-theme="dark"] .empty,body[data-theme="dark"] .profile-tabs-container{background:var(--dm-surface)!important;border-color:var(--dm-border)!important}
body[data-theme="dark"] .profile-tabs-nav{background:var(--dm-surface-2)!important;border-color:var(--dm-border)!important}
body[data-theme="dark"] .profile-tab-btn{color:var(--dm-text-muted)!important}
body[data-theme="dark"] .profile-tab-btn.active{color:#ff4dbb!important;border-bottom-color:#ff4dbb!important}
body[data-theme="dark"] .overview-item,body[data-theme="dark"] .profile-photo-upload{background:var(--dm-surface-2)!important;border-color:var(--dm-border)!important}
body[data-theme="dark"] .overview-item strong{color:var(--dm-text)!important}
body[data-theme="dark"] th{background:var(--dm-surface-2)!important;color:var(--dm-text)!important;border-color:var(--dm-border)!important}
body[data-theme="dark"] td{border-color:var(--dm-border)!important}
body[data-theme="dark"] input,body[data-theme="dark"] select,body[data-theme="dark"] textarea{background:var(--dm-surface-2)!important;color:#fff!important;border-color:var(--dm-border)!important}
body[data-theme="dark"] .field input:disabled{background:#15111a!important;color:#6a5d69!important}
body[data-theme="dark"] .field label,body[data-theme="dark"] .hint{color:var(--dm-text-muted)!important}
body[data-theme="dark"] .profile-actions{border-top-color:var(--dm-border)!important}
body[data-theme="dark"] .profile-photo-actions label{background:var(--dm-surface-2)!important;color:var(--dm-text)!important;border-color:var(--dm-border)!important}
body[data-theme="dark"] .profile-photo-actions label:hover{background:#2d2432!important}

/* [RESPONSIVE] */
@media(max-width:1150px){.grid-books{grid-template-columns:repeat(3,1fr)}.stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:760px){
  .sidebar{transform:translateX(-100%);width:250px}.sidebar.mobile-open{transform:translateX(0)}.main{margin-left:0}
  .hero{flex-direction:column;align-items:flex-start;padding:21px}.hero-date{text-align:left}
  .grid-books{grid-template-columns:repeat(2,1fr)}.stats{gap:9px}.stat{padding:14px 8px}
  .form-grid{grid-template-columns:1fr}.form-grid .full{grid-column:auto}
  .overview-grid{grid-template-columns:1fr}
  .profile-photo-upload{flex-direction:column;align-items:flex-start}
  .profile-tabs-nav{gap:16px}.profile-tab-btn{font-size:13px;padding:14px 0}
}
@media(max-width:420px){.grid-books{grid-template-columns:1fr}.header-action .action-label{display:none}.header-action{padding:0;width:34px;height:34px}}
</style>
</head>
<body>

<div class="app">
<aside class="sidebar" id="sidebar">
    <div class="brand"><img src="../img/agustinnb.png" alt="Logo" onerror="this.style.display='none'"><h2>ST. AGNES ACADEMY</h2><p>Caloocan Inc.</p><div class="who"><?=thEsc($fullName)?><small>ID: <?=thEsc($displayId)?></small></div></div>
    <nav class="nav">
        <?php $navItems = ['dashboard'=>['▦','Dashboard'],'catalog'=>['▤','Browse Books'],'requests'=>['▧','My Requests'],'borrowings'=>['⇄','My Borrowings'],'reservations'=>['◷','Reservations'],'history'=>['▤','Borrowing History'],'notifications'=>['♧','Notifications'],'profile'=>['◉','My Profile']]; foreach($navItems as $key=>$item): ?>
        <a href="?section=<?=$key?>" class="<?=$section===$key?'active':''?>"><span class="ico"><?=$item[0]?></span><span class="label"><?=$item[1]?></span><?php if($key==='notifications' && count($unreadNotifications)): ?><span class="notification-badge"><?=count($unreadNotifications)?></span><?php endif; ?></a>
        <?php endforeach; ?>
        <a href="library_workspace.php?tab=purchases"><span class="ico">₱</span><span class="label">Purchase Requests</span></a>
        <a href="library_workspace.php?tab=class_sets"><span class="ico">▤</span><span class="label">Class Set Requests</span></a>
    </nav>
</aside>
<main class="main" id="main">
<header class="topbar">
<button class="hamb" id="hamb" aria-label="Toggle navigation">☰</button>
<div class="toplinks">
<a class="header-action" href="?section=notifications" title="Notifications"><span class="action-icon">♧</span><span class="action-label">Notifications</span><?php if(count($unreadNotifications)): ?><span class="header-badge"><?=count($unreadNotifications)?></span><?php endif; ?></a>
<a class="header-action" href="?section=profile" title="Profile"><span class="action-icon">◉</span><span class="action-label">Profile</span></a>
<button class="header-action" type="button" data-theme-toggle title="Toggle theme"><span class="action-icon">☾</span><span class="action-label">Dark mode</span></button>
<a class="header-action logout-action" href="../admin_logout.php" title="Logout"><span class="action-icon">↪</span><span class="action-label">Logout</span></a>
</div>
</header>

<div class="content">
<!-- WE ONLY SHOW THE MAIN HERO IF WE ARE NOT ON THE PROFILE PAGE -->
<?php if($section !== 'profile'): ?>
<div class="hero">
    <div><h1><?= $section==='dashboard' ? 'Teacher Dashboard' : thEsc($navItems[$section][1]) ?></h1><p>Welcome back, <?=thEsc($fullName)?> · St. Agnes Academy Library Management System</p></div>
    <div class="hero-date"><strong><?=date('h:i A')?></strong><?=date('F j, Y')?></div>
</div>
<?php endif; ?>

<?php if($msg): ?><div class="alert ok">✓ <?=thEsc($msg)?></div><?php endif; ?>
<?php if($error): ?><div class="alert err">! <?=thEsc($error)?></div><?php endif; ?>

<?php if($section==='dashboard'): ?>
<!-- DASHBOARD CONTENT -->
<div class="stats">
 <div class="stat"><strong><?=count($activeBorrowings)?></strong><span>Active Borrowings</span><small><?=count($overdueBorrowings)?> overdue</small></div>
 <div class="stat"><strong><?=count($pendingRequests)?></strong><span>Pending Requests</span><small><?=count($approvedRequests)?> approved</small></div>
 <div class="stat"><strong><?=count($pendingReservations)?></strong><span>Reservations</span><small><?=count($historyBorrowings)?> returned books</small></div>
 <div class="stat"><strong><?=count($unreadNotifications)?></strong><span>Unread Notifications</span><small><?=count($unpaidFines)?> unpaid fine records</small></div>
</div>
<section class="panel"><div class="panel-head"><h2>Recently Added Books</h2><a class="subtle" href="?section=catalog">Browse all books →</a></div>
<div class="grid-books"><?php foreach(array_slice(array_reverse($books),0,4) as $book): $cover=teacherBookCover($book); ?><article class="book-card"><div class="cover"><?php if($cover): ?><img src="<?=thEsc($cover)?>" alt="Book cover"><?php else: ?>▤<?php endif; ?></div><div class="book-info"><h3><?=thEsc($book['title']??'Untitled')?></h3><p><?=thEsc($book['author']??'Unknown')?></p><div class="book-actions"><a class="btn light" href="?section=catalog&search=<?=rawurlencode($book['title']??'')?>">View</a></div></div></article><?php endforeach; ?></div>
</section>

<?php elseif($section==='profile'): ?>
<!-- ========================================== -->
<!-- PROFILE PAGE (MATCHING STUDENT DASHBOARD)  -->
<!-- ========================================== -->
<div class="profile-hero-v2">
    <div class="profile-hero-avatar">
        <?php if(!empty($_SESSION['avatar_url'])): ?>
            <img src="<?=thEsc($_SESSION['avatar_url'])?>" alt="Profile picture">
        <?php else: ?>
            <?=thEsc(strtoupper(substr($fullName,0,1)))?>
        <?php endif; ?>
    </div>
    <div class="profile-hero-copy">
        <span class="profile-hero-eyebrow">ST. AGNES ACADEMY · TEACHER WORKSPACE</span>
        <h2>My Profile</h2>
        <p>Manage your account details and view your library membership.</p>
    </div>
    <div class="profile-hero-status"><span class="profile-status-dot"></span><?=thEsc(ucfirst($role))?></div>
</div>

<div class="profile-tabs-container">
    <div class="profile-tabs-nav">
        <button type="button" class="profile-tab-btn <?=$profileTab==='overview'?'active':''?>" onclick="window.location.href='?section=profile&tab=overview'">Overview</button>
        <button type="button" class="profile-tab-btn <?=$profileTab==='edit'?'active':''?>" onclick="window.location.href='?section=profile&tab=edit'">Edit profile</button>
        <button type="button" class="profile-tab-btn <?=$profileTab==='security'?'active':''?>" onclick="window.location.href='?section=profile&tab=security'">Security</button>
    </div>

    <div class="profile-tab-pane <?=$profileTab==='overview'?'active':''?>" id="tab-overview">
        <h3 class="profile-section-title">Account Overview</h3>
        <div class="overview-grid">
            <div class="overview-item"><span>Full Name</span><strong><?=thEsc($fullName)?></strong></div>
            <div class="overview-item"><span>Teacher ID</span><strong><?=thEsc($displayId)?></strong></div>
            <div class="overview-item"><span>Email</span><strong><?=thEsc($profile['email']??'—')?></strong></div>
            <div class="overview-item"><span>Phone</span><strong><?=thEsc($profile['phone']??'Not set')?></strong></div>
            <div class="overview-item"><span>Department</span><strong><?=thEsc($profile['department']??'Not set')?></strong></div>
            <div class="overview-item"><span>Birthdate</span><strong><?=!empty($profile['birthdate'])?date('F j, Y', strtotime($profile['birthdate'])):'Not set'?></strong></div>
            <div class="overview-item"><span>Home Address</span><strong><?=thEsc($profile['address']??'Not set')?></strong></div>
            <div class="overview-item"><span>Username</span><strong><?=thEsc($profile['username']??$displayId)?></strong></div>
        </div>
    </div>

    <div class="profile-tab-pane <?=$profileTab==='edit'?'active':''?>" id="tab-edit">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>">
            <input type="hidden" name="action" value="update_photo">
            <h3 class="profile-section-title">Profile photo</h3>
            <div class="profile-photo-upload">
                <div class="profile-photo-preview" id="teacherPhotoPreview">
                    <?php if(!empty($_SESSION['avatar_url'])): ?><img src="<?=thEsc($_SESSION['avatar_url'])?>" alt="Avatar"><?php else: ?><?=thEsc(strtoupper(substr($fullName,0,1)))?><?php endif; ?>
                </div>
                <div class="profile-photo-actions">
                    <label for="teacherPhotoInput">Upload new photo</label>
                    <input type="file" id="teacherPhotoInput" name="avatar" accept="image/*" onchange="previewTeacherPhoto(event)">
                    <small>JPG or PNG, square works best. Without a photo your initials are shown.</small>
                    <?php if(!empty($_SESSION['avatar_url'])): ?><button type="button" class="profile-photo-remove" onclick="removeTeacherPhoto()">Remove</button><?php endif; ?>
                </div>
                <button class="btn-save" type="submit" style="margin-left:auto;align-self:center;padding:10px 18px;">Save photo</button>
            </div>
        </form>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>">
            <input type="hidden" name="action" value="update_info">
            
            <h3 class="profile-section-title">Personal information</h3>
            <div class="form-grid">
                <div class="field full"><label>First name</label><input name="first_name" value="<?=thEsc($profile['first_name']??'')?>"></div>
                <div class="field full"><label>Middle name</label><input name="middle_name" value="<?=thEsc($profile['middle_name']??'')?>"></div>
                <div class="field full"><label>Last name</label><input name="last_name" value="<?=thEsc($profile['last_name']??'')?>"></div>
                <div class="field">
                    <label>Birthdate</label>
                    <input type="date" name="birthdate" value="<?=thEsc($profile['birthdate']??'')?>">
                </div>
                <div class="field"><label>Department</label><input name="department" value="<?=thEsc($profile['department']??'')?>"></div>
                <div class="field">
                    <label>Teacher ID</label>
                    <input value="<?=thEsc($displayId)?>" disabled>
                    <div class="hint">Locked</div>
                </div>
            </div>
            
            <h3 class="profile-section-title" style="margin-top:32px;">Contact details</h3>
            <div class="form-grid">
                <div class="field">
                    <label>School email</label>
                    <input value="<?=thEsc($profile['email']??'')?>" disabled>
                    <div class="hint">Locked, used to sign in</div>
                </div>
                <div class="field">
                    <label>Mobile number</label>
                    <input name="phone" value="<?=thEsc($profile['phone']??'')?>">
                </div>
                <div class="field full">
                    <label>Home address</label>
                    <input name="address" value="<?=thEsc($profile['address']??'')?>" placeholder="Enter full address">
                </div>
            </div>
            <div class="profile-actions">
                <button class="btn-save" type="submit">Save changes</button>
            </div>
        </form>
    </div>

    <div class="profile-tab-pane <?=$profileTab==='security'?'active':''?>" id="tab-security">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>">
            <input type="hidden" name="action" value="update_security">
            
            <h3 class="profile-section-title">Change Phone Number</h3>
            <p style="font-size:13px; color:var(--muted); margin:-10px 0 20px;">Update your contact number.</p>
            <div class="form-grid" style="max-width:500px;">
                <div class="field full">
                    <label>New Mobile number</label>
                    <input name="new_phone" value="<?=thEsc($profile['phone']??'')?>">
                </div>
            </div>

            <h3 class="profile-section-title" style="margin-top:32px;">Change Password</h3>
            <p style="font-size:13px; color:var(--muted); margin:-10px 0 20px;">Leave blank if you do not want to change your password.</p>
            <div class="form-grid" style="max-width:500px;">
                <div class="field full">
                    <label>Current Password</label>
                    <input type="password" name="current_password">
                </div>
                <div class="field full">
                    <label>New Password</label>
                    <input type="password" name="new_password">
                </div>
                <div class="field full">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password">
                </div>
            </div>

            <div class="profile-actions" style="justify-content:flex-start;">
                <button class="btn-save" type="submit">Update Security</button>
            </div>
        </form>
    </div>
</div>


<?php elseif($section==='catalog'): ?>
<!-- CATALOG -->
<section class="panel"><div class="panel-head"><h2>Library Catalog</h2><span class="subtle"><?=count($filteredBooks)?> matching books</span></div>
<form class="toolbar" method="get"><input type="hidden" name="section" value="catalog"><div class="teacher-predict-wrap"><input autocomplete="off" name="search" value="<?=thEsc($search)?>" placeholder="Search title, author, ISBN..."></div><button class="btn">Search</button><a class="btn light" href="?section=catalog">Reset</a></form>
<?php if($filteredBooks): ?><div class="grid-books"><?php foreach($filteredBooks as $book): $cover=teacherBookCover($book); $available=(int)($book['available']??0); ?><article class="book-card"><div class="cover"><?php if($cover): ?><img src="<?=thEsc($cover)?>" alt="Cover"><?php else: ?>▤<?php endif; ?></div><div class="book-info"><h3><?=thEsc($book['title']??'Untitled')?></h3><p><?=thEsc($book['author']??'Unknown')?></p><div style="margin-bottom:10px"><span class="status <?=$available>0?'success':'danger'?>"><?=$available>0?$available.' available':'Unavailable'?></span></div><div class="book-actions"><button class="btn" type="button" onclick="openRequest('<?=thEsc($book['id'])?>','<?=thEsc($book['title']??'Book')?>',<?=$available?>)">Request</button></div></div></article><?php endforeach; ?></div><?php else: ?><div class="empty"><h3>No books found</h3></div><?php endif; ?></section>

<?php elseif($section==='requests'): ?>
<!-- REQUESTS -->
<section class="panel"><div class="panel-head"><h2>My Book Requests</h2></div><?php if($requests): ?><div class="table-wrap"><table><thead><tr><th>Book</th><th>Type</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($requests as $r): ?><tr><td><b><?=thEsc($r['books']['title']??'Book')?></b></td><td><?=thEsc(ucfirst($r['request_type']??''))?></td><td><?=thEsc(date('M d, Y', strtotime($r['created_at']??'')))?></td><td><span class="status <?=teacherStatusClass($r['status']??'Pending')?>"><?=thEsc($r['status']??'Pending')?></span></td><td><?php if(($r['status']??'')==='Pending'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>"><input type="hidden" name="action" value="cancel_request"><input type="hidden" name="request_id" value="<?=thEsc($r['id'])?>"><button class="btn danger" onclick="return confirm('Cancel?')">Cancel</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty"><h3>No requests yet</h3></div><?php endif; ?></section>

<?php elseif($section==='borrowings' || $section==='history'): $rows=$section==='history'?$historyBorrowings:$activeBorrowings; ?>
<!-- BORROWINGS -->
<section class="panel"><div class="panel-head"><h2><?=$section==='history'?'Borrowing History':'Current Borrowings'?></h2></div><?php if($rows): ?><div class="table-wrap"><table><thead><tr><th>Book</th><th>Borrowed</th><th>Due Date</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $b): $st=($b['status']??'')!=='Returned'&&!empty($b['due_date'])&&strtotime($b['due_date'])<strtotime(date('Y-m-d'))?'Overdue':($b['status']??'Borrowed'); ?><tr><td><b><?=thEsc($b['books']['title']??'Book')?></b></td><td><?=thEsc($b['borrow_date']??'—')?></td><td><?=thEsc($b['due_date']??'—')?></td><td><span class="status <?=teacherStatusClass($st)?>"><?=thEsc($st)?></span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty"><h3>No records</h3></div><?php endif; ?></section>

<?php elseif($section==='reservations'): ?>
<!-- RESERVATIONS -->
<section class="panel"><div class="panel-head"><h2>My Reservations</h2></div><?php if($reservations): ?><div class="table-wrap"><table><thead><tr><th>Book</th><th>Reserved</th><th>Expiry</th><th>Status</th></tr></thead><tbody><?php foreach($reservations as $r): ?><tr><td><b><?=thEsc($r['books']['title']??'Book')?></b></td><td><?=thEsc($r['reservation_date']??'—')?></td><td><?=thEsc($r['expiry_date']??'—')?></td><td><span class="status <?=teacherStatusClass($r['status']??'Pending')?>"><?=thEsc($r['status']??'Pending')?></span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty"><h3>No reservations</h3></div><?php endif; ?></section>

<?php elseif($section==='notifications'): ?>
<!-- NOTIFICATIONS -->
<section class="panel"><div class="panel-head"><h2>Notifications</h2></div><?php if($notifications): foreach($notifications as $n): ?><div class="notice <?=empty($n['is_read'])?'unread':''?>" style="padding:13px;border:1px solid #f0e3ed;border-radius:10px;margin-bottom:9px;display:flex;justify-content:space-between;gap:12px;"><div><strong><?=thEsc($n['title']??'Notice')?></strong><p style="font-size:11px;color:#8c7b8b;margin:4px 0 0;"><?=thEsc($n['message']??'')?></p></div><?php if(empty($n['is_read'])): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>"><input type="hidden" name="action" value="mark_notification_read"><input type="hidden" name="notification_id" value="<?=thEsc($n['id'])?>"><button class="btn light">Mark read</button></form><?php else: ?><span class="status success">Read</span><?php endif; ?></div><?php endforeach; else: ?><div class="empty"><h3>No notifications</h3></div><?php endif; ?></section>

<?php endif; ?>
<div class="footer-note" style="text-align:center;font-size:10px;color:#a28e9f;padding:12px;">St. Agnes Academy · Library Management System · Teacher Workspace</div>
</div></main></div>

<div class="modal" id="requestModal" style="display:none;position:fixed;inset:0;background:rgba(12,4,12,.58);z-index:80;align-items:center;justify-content:center;padding:18px;">
<div class="modal-box" style="background:white;border-radius:15px;width:min(500px,100%);padding:22px;box-shadow:0 20px 70px rgba(0,0,0,.25);">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;"><h3 style="font-size:16px;margin:0;">Submit Book Request</h3><button class="close" type="button" onclick="closeRequest()" style="border:0;background:#f7edf4;border-radius:7px;padding:6px 10px;cursor:pointer;">✕</button></div>
<p id="requestBookTitle" style="margin:0 0 15px;font-size:12px;color:#907e8e;"></p>
<form method="post"><input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>"><input type="hidden" name="action" value="submit_request"><input type="hidden" name="book_id" id="requestBookId">
<div class="field"><label>Request Type</label><select name="request_type" id="requestType"><option value="borrow">Borrow this book</option><option value="reserve">Reserve this book</option></select></div><br>
<div class="field"><label>Purpose / Notes</label><textarea name="purpose" placeholder="e.g. For classroom instruction..."></textarea></div><br>
<button class="btn" style="width:100%">Submit Request</button></form></div></div>

<script>
const sidebar=document.getElementById('sidebar'),main=document.getElementById('main');
document.getElementById('hamb').addEventListener('click',()=>{if(window.innerWidth<=760){sidebar.classList.toggle('mobile-open')}else{sidebar.classList.toggle('collapsed');main.classList.toggle('expanded')}});
function openRequest(id,title,available){document.getElementById('requestBookId').value=id;document.getElementById('requestBookTitle').textContent=title;document.getElementById('requestType').value=available>0?'borrow':'reserve';document.getElementById('requestModal').style.display='flex'}
function closeRequest(){document.getElementById('requestModal').style.display='none'}
document.getElementById('requestModal').addEventListener('click',e=>{if(e.target.id==='requestModal')closeRequest()});

function previewTeacherPhoto(event){const file=event.target.files[0];if(!file)return;if(file.size>2*1024*1024){alert('Photo must be under 2MB.');event.target.value='';return;}const reader=new FileReader();reader.onload=e=>{document.getElementById('teacherPhotoPreview').innerHTML='<img src="'+e.target.result+'" alt="Preview">';};reader.readAsDataURL(file);}
function removeTeacherPhoto(){if(!confirm('Remove your profile photo?'))return;const f=document.createElement('form');f.method='post';f.innerHTML='<input type="hidden" name="csrf_token" value="<?=thEsc($csrf)?>"><input type="hidden" name="action" value="update_photo"><input type="hidden" name="remove_photo" value="1">';document.body.appendChild(f);f.submit();}

(function(){const body=document.body,toggle=document.querySelector('[data-theme-toggle]'),icon=toggle?.querySelector('.action-icon'),label=toggle?.querySelector('.action-label');let saved='light';try{saved=localStorage.getItem('library-dashboard-theme')||'light';}catch(e){}function applyTheme(theme){body.setAttribute('data-theme',theme);if(icon)icon.textContent=theme==='dark'?'☀':'☾';if(label)label.textContent=theme==='dark'?'Light mode':'Dark mode';try{localStorage.setItem('library-dashboard-theme',theme);}catch(e){}}applyTheme(saved);toggle?.addEventListener('click',()=>applyTheme(body.getAttribute('data-theme')==='dark'?'light':'dark'));})();
</script>
</body></html>