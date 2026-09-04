<?php
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../homepage.php');
    exit;
}

define('SUPABASE_URL', 'https://olzkpwzebcnmbqhbcyyz.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im9semtwd3plYmNubWJxaGJjeXl6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQwMjYxNzcsImV4cCI6MjA5OTYwMjE3N30.GNk7gwaWfi3O-dncbixlkB7M8q6R-UJUe2VMsB5cBTQ');

define('NLP_SERVICE_SEARCH', 'http://localhost:5000/search');
define('NLP_SERVICE_HEALTH', 'http://localhost:5000/health');
define('NLP_TIMEOUT', 8);
define('PYTHON_PATH', 'python');
define('NLP_SCRIPT_PATH', 'C:\\xampp\\htdocs\\lib\\python\\app.py');
define('NLP_WORKING_DIR', 'C:\\xampp\\htdocs\\lib\\python');

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

function isNLPServiceRunning() {
    $ch = curl_init(NLP_SERVICE_HEALTH);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode === 200 && $response !== false);
}

function startNLPService() {
    if (isNLPServiceRunning()) {
        return true;
    }
    
    if (!file_exists(NLP_SCRIPT_PATH)) {
        error_log("❌ NLP script not found: " . NLP_SCRIPT_PATH);
        return false;
    }
    
    if (!is_dir(NLP_WORKING_DIR)) {
        error_log("❌ NLP working directory not found: " . NLP_WORKING_DIR);
        return false;
    }
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = 'start /MIN cmd /c "cd /d ' . NLP_WORKING_DIR . ' && ' . PYTHON_PATH . ' app.py"';
        shell_exec($command . ' 2>&1');
        error_log("📌 Starting NLP Service on Windows from: " . NLP_WORKING_DIR);
    } else {
        $command = 'nohup ' . PYTHON_PATH . ' "' . NLP_SCRIPT_PATH . '" > /dev/null 2>&1 &';
        shell_exec($command);
        error_log("📌 Starting NLP Service on Linux");
    }
    
    for ($i = 0; $i < 15; $i++) {
        sleep(1);
        if (isNLPServiceRunning()) {
            error_log("✅ NLP Service started successfully!");
            return true;
        }
        if ($i % 3 === 0) {
            error_log("⏳ Waiting for NLP Service to start... (" . ($i + 1) . "s)");
        }
    }
    
    error_log("❌ Failed to start NLP Service");
    return false;
}

function ensureNLPServiceRunning() {
    static $attempted = false;
    
    if (isNLPServiceRunning()) {
        return true;
    }
    
    if (!$attempted) {
        $attempted = true;
        return startNLPService();
    }
    
    return false;
}

function performNLPSearch($query) {
    ensureNLPServiceRunning();
    
    $payload = json_encode([
        'query' => $query, 
        'type' => 'semantic', 
        'limit' => 50,
        'min_relevance' => 15
    ]);
    
    $ch = curl_init(NLP_SERVICE_SEARCH);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, NLP_TIMEOUT);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['results']) && is_array($data['results'])) {
            return $data;
        }
    }
    
    return null;
}

function performBasicSearch($books, $query) {
    $results = [];
    $searchLower = strtolower($query);
    $searchWords = array_filter(explode(' ', $searchLower), function($w) { return strlen($w) > 2; });
    
    foreach ($books as $book) {
        $score = 0;
        $title = strtolower($book['title'] ?? '');
        $author = strtolower($book['author'] ?? '');
        $description = strtolower($book['description'] ?? '');
        $isbn = strtolower($book['isbn'] ?? '');
        $category = strtolower($book['categories']['name'] ?? '');
        $keywords = strtolower($book['keywords'] ?? '');
        
        if (strpos($title, $searchLower) !== false) {
            $score += 50;
            if ($title === $searchLower) $score += 30;
        }
        if (strpos($author, $searchLower) !== false) $score += 30;
        if (strpos($description, $searchLower) !== false) $score += 20;
        if (strpos($keywords, $searchLower) !== false) $score += 15;
        if (strpos($category, $searchLower) !== false) $score += 10;
        if (strpos($isbn, $searchLower) !== false) $score += 25;
        
        foreach ($searchWords as $word) {
            if (strlen($word) > 2) {
                if (strpos($title, $word) !== false) $score += 10;
                if (strpos($author, $word) !== false) $score += 5;
                if (strpos($description, $word) !== false) $score += 3;
                if (strpos($keywords, $word) !== false) $score += 3;
            }
        }
        
        if ($score > 0) {
            $book['relevance_score'] = min($score, 100);
            $book['search_type'] = 'basic';
            $results[] = $book;
        }
    }
    
    usort($results, function($a, $b) {
        return ($b['relevance_score'] ?? 0) - ($a['relevance_score'] ?? 0);
    });
    
    return $results;
}

if (!isset($_SESSION['nlp_auto_started'])) {
    $_SESSION['nlp_auto_started'] = true;
    
    if (!isNLPServiceRunning()) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $command = 'start /MIN cmd /c "cd /d ' . NLP_WORKING_DIR . ' && ' . PYTHON_PATH . ' app.py"';
            shell_exec($command . ' 2>&1');
        } else {
            $command = 'nohup ' . PYTHON_PATH . ' "' . NLP_SCRIPT_PATH . '" > /dev/null 2>&1 &';
            shell_exec($command);
        }
        error_log("🚀 NLP Service auto-start triggered on student login");
    }
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
$requestStatus = [];

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
    
    // Get student requests
    try {
        $studentRequests = supabaseRequest('book_requests?select=*,books(title,author)&user_id=eq.' . $userId . '&order=created_at.desc');
        $pendingRequests = array_filter($studentRequests, function($r) {
            return ($r['status'] ?? '') === 'Pending';
        });
    } catch (Exception $e) {
        $studentRequests = [];
        $pendingRequests = [];
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
    $fullName = trim($_POST['full_name'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $yearLevel = trim($_POST['year_level'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    
    // Validate
    $errors = [];
    if (!$bookId) $errors[] = 'Book ID is required.';
    if (!$requestType || !in_array($requestType, ['borrow', 'reserve'])) $errors[] = 'Valid request type is required.';
    if (!$fullName) $errors[] = 'Full name is required.';
    if (!$studentId) $errors[] = 'Student ID is required.';
    if (!$yearLevel) $errors[] = 'Year level is required.';
    if (!$section) $errors[] = 'Section is required.';
    
    if (empty($errors)) {
        try {
            // Check if user already has a pending request for this book
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
                'section' => $section,
                'purpose' => $purpose,
                'status' => 'Pending'
            ];
            
            supabaseRequest('book_requests', 'POST', $requestData);
            
            // Create notification for admin
            try {
                // Get book title
                $bookData = supabaseRequest('books?select=title&id=eq.' . $bookId);
                $bookTitle = !empty($bookData) ? $bookData[0]['title'] : 'Book';
                
                // Get admin users
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
                // Log but don't fail
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

// ===== ORIGINAL BORROW/RESERVE HANDLING (modified to check for approved requests) =====
if ($section === 'search' && $action === 'borrow' && isset($_GET['book_id'])) {
    $bookId = $_GET['book_id'];
    
    if ($isRestricted) {
        header('Location: student_dashboard.php?section=search&msg=Your account is restricted. Please settle your fines and return overdue books first.');
        exit;
    }
    
    // Check if student has an approved request for this book
    try {
        $approvedRequests = supabaseRequest('book_requests?select=id&user_id=eq.' . $userId . '&book_id=eq.' . $bookId . '&status=eq.Approved&request_type=eq.borrow');
        if (empty($approvedRequests)) {
            // Redirect to request form
            header('Location: student_dashboard.php?section=request_form&book_id=' . $bookId . '&type=borrow&msg=Please submit a request form first.');
            exit;
        }
    } catch (Exception $e) {
        header('Location: student_dashboard.php?section=search&msg=Error checking request status.');
        exit;
    }
    
    try {
        $books = supabaseRequest('books?select=available,id,title&id=eq.' . $bookId);
        if (empty($books) || ($books[0]['available'] ?? 0) <= 0) {
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
        supabaseRequest('books?id=eq.' . $bookId, 'PATCH', ['available' => ($books[0]['available'] - 1)]);
        
        // Mark request as fulfilled
        if (!empty($approvedRequests)) {
            supabaseRequest('book_requests?id=eq.' . $approvedRequests[0]['id'], 'PATCH', ['status' => 'Fulfilled']);
        }

        header('Location: student_dashboard.php?section=borrowings&msg=Book borrowed successfully!');
    } catch (Exception $e) {
        header('Location: student_dashboard.php?section=search&msg=Error borrowing book: ' . $e->getMessage());
    }
    exit;
}

if ($section === 'search' && $action === 'reserve' && isset($_GET['book_id'])) {
    $bookId = $_GET['book_id'];
    
    if ($isRestricted) {
        header('Location: student_dashboard.php?section=search&msg=Your account is restricted. Please settle your fines and return overdue books first.');
        exit;
    }
    
    // Check if student has an approved request for this book
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
        $books = supabaseRequest('books?select=available,id,title&id=eq.' . $bookId);
        if (empty($books)) {
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
        
        // Mark request as fulfilled
        if (!empty($approvedRequests)) {
            supabaseRequest('book_requests?id=eq.' . $approvedRequests[0]['id'], 'PATCH', ['status' => 'Fulfilled']);
        }

        header('Location: student_dashboard.php?section=reservations&msg=Book reserved successfully! You will be notified when available.');
    } catch (Exception $e) {
        header('Location: student_dashboard.php?section=search&msg=Error reserving book: ' . $e->getMessage());
    }
    exit;
}

// Rest of the original code continues...
// [The rest of the original code remains unchanged]

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
    } catch (Exception $e) {
        // Book not found
    }
}

$allBooks = [];
$searchQuery = isset($_GET['q']) ? $_GET['q'] : '';
$searchType = isset($_GET['type']) ? $_GET['type'] : 'semantic';
$searchResults = [];
$message = isset($_GET['msg']) ? $_GET['msg'] : '';
$nlpAvailable = false;
$searchTime = 0;
$searchTypeUsed = 'all';
$errorMessage = '';

try {
    $allBooks = supabaseRequest('books?select=*,categories(name)');
    $books = $allBooks;
    
    if (!empty($searchQuery)) {
        $startTime = microtime(true);
        
        $nlpResults = performNLPSearch($searchQuery);
        
        if ($nlpResults !== null && !empty($nlpResults['results'])) {
            $searchResults = $nlpResults['results'];
            $searchTypeUsed = $nlpResults['type'] ?? 'semantic';
            $nlpAvailable = true;
            $errorMessage = '';
        } else {
            $searchResults = performBasicSearch($allBooks, $searchQuery);
            $searchTypeUsed = 'basic';
            $nlpAvailable = false;
            $errorMessage = 'NLP service unavailable, using basic search';
        }
        
        $endTime = microtime(true);
        $searchTime = round(($endTime - $startTime) * 1000);
    } else {
        $searchResults = $allBooks;
        $searchTypeUsed = 'all';
    }
} catch (Exception $e) {
    $message = 'Error loading books: ' . $e->getMessage();
    $searchResults = [];
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
    if (empty($coverImage)) {
        return false;
    }
    
    if (strpos($coverImage, 'data:image') === 0) {
        return strlen($coverImage) > 100;
    }
    
    if (filter_var($coverImage, FILTER_VALIDATE_URL)) {
        return true;
    }
    
    return false;
}

function displayCoverImage($coverImage, $title, $id) {
    if (hasValidCoverImage($coverImage)) {
        echo '<img src="' . htmlspecialchars($coverImage) . '" 
                   alt="' . htmlspecialchars($title ?? 'Book') . '" 
                   onerror="this.style.display=\'none\';this.parentElement.querySelector(\'.cover-placeholder\').style.display=\'flex\';">';
        echo '<div class="cover-placeholder" style="display:none;background-color:' . getPlaceholderColor($id) . ';">';
        echo '<span class="initial">' . (isset($title) ? strtoupper(substr($title, 0, 1)) : '▣') . '</span>';
        echo '<span class="placeholder-label">No Image</span>';
        echo '</div>';
    } else {
        echo '<div class="cover-placeholder" style="background-color:' . getPlaceholderColor($id) . ';">';
        echo '<span class="initial">' . (isset($title) ? strtoupper(substr($title, 0, 1)) : '▣') . '</span>';
        echo '<span class="placeholder-label">No Image</span>';
        echo '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - St. Agnes Academy</title>
    <style>
        /* [All original styles remain unchanged] */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; 
            background: #f5f3f0;
            color: #1a1a1a;
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f0edea; }
        ::-webkit-scrollbar-thumb { background: #d4c9c0; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #b8a89c; }

        .student-app { display: flex; min-height: 100vh; }
        .student-sidebar {
            width: 240px;
            background: #000000;
            color: #e8e0d8;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            border-right: 1px solid #2a2a2a;
        }
        .sidebar-header { 
            padding: 28px 24px 20px; 
            border-bottom: 1px solid #2a2a2a;
            text-align: left;
        }
        .sidebar-header .sidebar-logo {
            max-width: 72px;
            height: auto;
            display: block;
            margin-bottom: 12px;
        }
        .sidebar-header h2 { 
            margin: 0; 
            font-size: 16px; 
            color: #f0e8e0;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .sidebar-header .subtitle {
            color: #8a7a6e;
            font-size: 11px;
            letter-spacing: 1px;
            margin-top: 2px;
            font-weight: 300;
        }
        .sidebar-header p { 
            margin: 12px 0 0; 
            font-size: 13px;
            color: #d4c9c0;
            font-weight: 400;
        }
        .sidebar-header small { 
            opacity: 0.5; 
            font-size: 11px; 
            display: block;
            color: #8a7a6e;
        }
        
        .nav-item-with-badge {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .notification-badge {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: #e51d66;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
            animation: pulse-badge 2s infinite;
            box-shadow: 0 0 12px rgba(229, 29, 102, 0.4);
        }
        @keyframes pulse-badge {
            0%, 100% { transform: translateY(-50%) scale(1); }
            50% { transform: translateY(-50%) scale(1.1); }
        }
        .notification-badge.empty {
            display: none;
        }

        .sidebar-nav { flex: 1; padding: 16px 0; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: #8a7a6e;
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        .sidebar-nav a:hover { 
            color: #f0e8e0; 
            background: rgba(255,255,255,0.04);
            border-left-color: #d4a0a0;
        }
        .sidebar-nav a.active { 
            color: #f0e8e0; 
            background: rgba(180, 15, 125, 0.18);
            border-left-color: #d4a0a0;
        }
        .sidebar-nav a .nav-icon {
            font-size: 18px;
            width: 24px;
            text-align: center;
            opacity: 0.7;
        }
        .sidebar-nav a.active .nav-icon {
            opacity: 1;
        }
        .sidebar-nav a .nav-label {
            flex: 1;
        }

        .sidebar-footer { 
            padding: 16px 24px 24px; 
            border-top: 1px solid #2a2a2a;
        }
        .logout-btn {
            width: 100%;
            padding: 10px 16px;
            background: rgba(180, 15, 125, 0.15);
            color: #d460b8;
            border: 1px solid rgba(180, 15, 125, 0.2);
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            text-decoration: none;
            text-align: center;
            display: block;
            font-weight: 500;
        }
        .logout-btn:hover { 
            background: rgba(212, 160, 160, 0.2);
            border-color: rgba(212, 160, 160, 0.4);
        }

        .student-content { 
            margin-left: 240px; 
            flex: 1; 
            padding: 32px 40px; 
            background: #f5f3f0; 
            min-height: 100vh; 
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 2000;
            background: #1a1a1a;
            color: #e8e0d8;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            padding: 10px 12px;
            cursor: pointer;
            min-height: 44px;
            min-width: 44px;
        }
        .hamburger-icon { display: flex; flex-direction: column; gap: 4px; width: 22px; }
        .hamburger-icon span { display: block; height: 2px; width: 100%; background: #e8e0d8; border-radius: 2px; transition: 0.3s; }
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        .dashboard-content { padding: 0; }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
            padding: 24px 32px;
            background: linear-gradient(135deg, #08080a 0%, #0a090a 30%, #e51d66c9 60%, #e51d66c9 80%, #e51d66c9 100%);
            border-radius: 16px;
            position: relative;
            overflow: hidden;
        }
        .dashboard-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(212, 160, 160, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        .dashboard-header .header-left {
            position: relative;
            z-index: 1;
        }
        .dashboard-header h1 { 
            font-size: 22px; 
            color: #f0e8e0; 
            margin: 0;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .header-date { 
            color: #8a7a6e; 
            font-size: 14px; 
            margin: 4px 0 0; 
        }
        .header-time {
            text-align: right;
            background: rgba(255,255,255,0.06);
            padding: 8px 20px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.06);
            position: relative;
            z-index: 1;
        }
        .header-time .time { 
            font-size: 20px; 
            font-weight: 600; 
            color: #0a0a0a; 
            display: block; 
            letter-spacing: 0.5px;
        }
        .header-time .date { 
            font-size: 12px; 
            color: #0a0a0a; 
            letter-spacing: 0.3px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: #ffffff;
            padding: 16px 18px;
            border-radius: 12px;
            border: 1px solid #e8e0d8;
            text-align: center;
            transition: all 0.2s ease;
        }
        .stat-card:hover {
            border-color: #d4c9c0;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        .stat-number { 
            font-size: 24px; 
            font-weight: 700; 
            color: #1a1a1a; 
            letter-spacing: -0.5px;
        }
        .stat-label { 
            font-size: 12px; 
            color: #6a5a4e; 
            margin-top: 2px; 
            font-weight: 400;
        }
        .stat-sub { 
            font-size: 10px; 
            color: #9a8a7e; 
            margin-top: 2px; 
        }

        .status-card {
            background: #ffffff;
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid #e8e0d8;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .status-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .status-dot.good-standing { background: #34a853; }
        .status-dot.active { background: #fbbc04; }
        .status-dot.restricted { background: #ea4335; animation: pulse-dot 1.5s infinite; }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
        .status-info { flex: 1; }
        .status-info .status-title { font-weight: 600; color: #1a1a1a; font-size: 16px; }
        .status-info .status-desc { font-size: 13px; color: #6a5a4e; }
        .status-badge-large {
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-badge-large.good-standing { background: #dde8e0; color: #1a4a3a; }
        .status-badge-large.active { background: #f0edd8; color: #6a5a3a; }
        .status-badge-large.restricted { background: #f0ddd8; color: #8a3a2a; }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 28px;
        }
        .quick-action-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 18px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #e8e0d8;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
        }
        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            border-color: #d4c9c0;
        }
        .action-icon { 
            font-size: 22px; 
            display: block; 
            margin-bottom: 6px; 
            opacity: 0.6;
        }
        .action-label { font-size: 13px; color: #4a3a2e; font-weight: 500; }
        .action-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: #e51d66;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .section-header h1 { 
            font-size: 22px; 
            color: #1a1a1a; 
            margin: 0; 
            font-weight: 600;
            letter-spacing: -0.5px;
        }

        .search-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-bar .search-input-wrapper {
            flex: 1;
            position: relative;
            min-width: 200px;
        }
        .search-bar .search-input-wrapper input {
            width: 100%;
            padding: 14px 16px 14px 50px;
            border: 2px solid #e8e0d8;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: #ffffff;
            color: #1a1a1a;
        }
        .search-bar .search-input-wrapper input:focus {
            border-color: #d4a0a0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(212,160,160,0.12);
        }
        .search-bar .search-input-wrapper .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #9a8a7e;
            font-size: 18px;
        }
        .search-bar .search-input-wrapper .clear-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9a8a7e;
            cursor: pointer;
            font-size: 20px;
            display: none;
            padding: 4px 8px;
        }
        .search-bar .search-input-wrapper .clear-btn.visible { display: block; }
        .search-bar .search-input-wrapper .clear-btn:hover { color: #4a3a2e; }
        
        .search-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .count-badge { 
            color: #6a5a4e; 
            font-size: 14px; 
            white-space: nowrap;
            background: #ffffff;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #e8e0d8;
        }
        .count-badge strong { color: #1a1a1a; }
        .search-type-badge {
            font-size: 12px;
            padding: 4px 14px;
            border-radius: 20px;
            font-weight: 500;
        }
        .search-type-badge.semantic { 
            background: #dde8e0; 
            color: #1a4a3a; 
        }
        .search-type-badge.nlp { 
            background: #e0dde8; 
            color: #3a1a4a; 
        }
        .search-type-badge.basic { 
            background: #e8e0d8; 
            color: #6a5a4e; 
        }
        .search-type-badge.basic_fallback { 
            background: #e8ddd8; 
            color: #6a3a2a; 
        }
        .search-type-badge.all { 
            background: #e8e8e8; 
            color: #4a4a4a; 
        }
        .search-time { font-size: 12px; color: #b0a8a0; }

        .book-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }
        .book-card {
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e8e0d8;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .book-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            border-color: #d4c9c0;
        }
        .book-card .book-cover-wrapper {
            height: 200px;
            background: #f0edea;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        .book-card .book-cover-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .book-card .book-cover-wrapper .cover-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: #ffffff;
            font-size: 48px;
            font-weight: bold;
        }
        .book-card .book-cover-wrapper .cover-placeholder .initial {
            font-size: 64px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .book-card .book-cover-wrapper .cover-placeholder .placeholder-label {
            font-size: 12px;
            opacity: 0.7;
            margin-top: 4px;
        }
        .book-card .book-cover-wrapper .availability-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #f0e8e0;
            background: #3a2a2a;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .book-card .book-cover-wrapper .availability-badge.low {
            background: #8a7a6e;
        }
        .book-card .book-cover-wrapper .availability-badge.none {
            background: #8a3a2a;
        }
        .book-card .book-info {
            padding: 16px 20px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .book-card .book-info .book-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0 0 4px 0;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .book-card .book-info .book-author {
            font-size: 14px;
            color: #6a5a4e;
            margin: 0 0 8px 0;
        }
        .book-card .book-info .book-category {
            display: inline-block;
            background: #f0edea;
            color: #4a3a2e;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            margin-bottom: 8px;
            align-self: flex-start;
        }
        .book-card .book-info .book-description {
            font-size: 14px;
            color: #6a5a4e;
            margin: 8px 0 12px;
            line-height: 1.5;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .book-card .book-info .book-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #f0edea;
            margin-top: auto;
            flex-wrap: wrap;
            gap: 8px;
        }
        .book-card .book-info .book-meta .availability {
            font-size: 14px;
            color: #6a5a4e;
        }
        .book-card .book-info .book-meta .availability strong {
            color: #1a1a1a;
        }
        .book-card .book-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .book-card .relevance-score {
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            background: #f0edea;
            color: #4a3a2e;
            align-self: flex-end;
            margin-top: 4px;
        }
        .book-card .semantic-badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            background: #dde8e0;
            color: #1a4a3a;
            align-self: flex-start;
            margin-bottom: 4px;
        }
        .btn-borrow {
            padding: 8px 20px;
            background: #1a1a1a;
            color: #f0e8e0;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-borrow:hover:not(:disabled) {
            background: #2a2a2a;
            transform: translateY(-1px);
        }
        .btn-borrow:disabled {
            background: #d4c9c0;
            cursor: not-allowed;
            opacity: 0.6;
            color: #8a7a6e;
        }
        .btn-reserve {
            padding: 8px 16px;
            background: #d4a0a0;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-reserve:hover:not(:disabled) {
            background: #c48a8a;
            transform: translateY(-1px);
        }
        .btn-reserve:disabled {
            background: #d4c9c0;
            cursor: not-allowed;
            opacity: 0.6;
            color: #8a7a6e;
        }
        .btn-request {
            padding: 8px 16px;
            background: #b40f7d;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-request:hover:not(:disabled) {
            background: #8a0a5f;
            transform: translateY(-1px);
        }
        .btn-request:disabled {
            background: #d4c9c0;
            cursor: not-allowed;
            opacity: 0.6;
            color: #8a7a6e;
        }

        /* REQUEST FORM STYLES */
        .request-form-container {
            background: #ffffff;
            border-radius: 16px;
            padding: 32px 36px;
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #e8e0d8;
        }
        .request-form-container .form-header {
            text-align: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f0edea;
        }
        .request-form-container .form-header .book-preview {
            background: #faf8f6;
            padding: 12px 16px;
            border-radius: 10px;
            margin-top: 12px;
            text-align: left;
        }
        .request-form-container .form-header .book-preview .preview-title {
            font-weight: 600;
            color: #1a1a1a;
            font-size: 16px;
        }
        .request-form-container .form-header .book-preview .preview-author {
            color: #6a5a4e;
            font-size: 14px;
        }
        .request-form-container .form-group {
            margin-bottom: 16px;
        }
        .request-form-container .form-group label {
            display: block;
            font-weight: 600;
            color: #4a3a2e;
            font-size: 14px;
            margin-bottom: 4px;
        }
        .request-form-container .form-group label .required {
            color: #8a3a2a;
        }
        .request-form-container .form-group input,
        .request-form-container .form-group select,
        .request-form-container .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e8e0d8;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.2s ease;
            background: #ffffff;
            color: #1a1a1a;
            font-family: inherit;
        }
        .request-form-container .form-group input:focus,
        .request-form-container .form-group select:focus,
        .request-form-container .form-group textarea:focus {
            border-color: #d4a0a0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(212,160,160,0.12);
        }
        .request-form-container .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .request-form-container .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #f0edea;
        }
        .request-form-container .form-actions .btn-submit {
            flex: 1;
            padding: 12px 28px;
            background: #1a1a1a;
            color: #f0e8e0;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .request-form-container .form-actions .btn-submit:hover:not(:disabled) {
            background: #2a2a2a;
        }
        .request-form-container .form-actions .btn-cancel {
            padding: 12px 24px;
            background: #f0edea;
            color: #4a3a2e;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .request-form-container .form-actions .btn-cancel:hover {
            background: #e8e0d8;
        }
        .request-form-container .info-note {
            background: #f0edea;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #6a5a4e;
        }
        .request-form-container .info-note strong {
            color: #1a1a1a;
        }

        /* REQUESTS LIST */
        .request-item {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 12px;
            border: 1px solid #e8e0d8;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .request-item .request-info {
            flex: 1;
        }
        .request-item .request-info .request-title {
            font-weight: 600;
            color: #1a1a1a;
            font-size: 15px;
        }
        .request-item .request-info .request-details {
            font-size: 13px;
            color: #6a5a4e;
            margin-top: 2px;
        }
        .request-item .request-status {
            font-weight: 600;
            font-size: 13px;
            padding: 4px 14px;
            border-radius: 20px;
        }
        .request-item .request-status.pending {
            background: #f0edd8;
            color: #6a5a3a;
        }
        .request-item .request-status.approved {
            background: #dde8e0;
            color: #1a4a3a;
        }
        .request-item .request-status.rejected {
            background: #f0ddd8;
            color: #8a3a2a;
        }
        .request-item .request-status.fulfilled {
            background: #dde8e0;
            color: #1a4a3a;
        }
        .request-item .request-date {
            font-size: 12px;
            color: #b0a8a0;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .modal-overlay.active {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-content {
            background: #ffffff;
            border-radius: 20px;
            padding: 32px 36px;
            max-width: 520px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-content .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f0edea;
        }
        .modal-content .modal-header h2 {
            font-size: 20px;
            color: #1a1a1a;
            margin: 0;
        }
        .modal-content .modal-header .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            color: #9a8a7e;
            cursor: pointer;
            padding: 0 8px;
            transition: color 0.2s ease;
        }
        .modal-content .modal-header .close-modal:hover {
            color: #4a3a2e;
        }
        .modal-content .book-info-preview {
            background: #faf8f6;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            border: 1px solid #e8e0d8;
        }
        .modal-content .book-info-preview .preview-title {
            font-weight: 600;
            color: #1a1a1a;
            font-size: 16px;
        }
        .modal-content .book-info-preview .preview-author {
            color: #6a5a4e;
            font-size: 14px;
        }
        .modal-content .book-info-preview .preview-detail {
            font-size: 13px;
            color: #6a5a4e;
            margin-top: 4px;
        }
        .modal-content .form-group {
            margin-bottom: 16px;
        }
        .modal-content .form-group label {
            display: block;
            font-weight: 500;
            color: #4a3a2e;
            font-size: 14px;
            margin-bottom: 4px;
        }
        .modal-content .form-group input,
        .modal-content .form-group select,
        .modal-content .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e8e0d8;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.2s ease;
            background: #ffffff;
            color: #1a1a1a;
        }
        .modal-content .form-group input:focus,
        .modal-content .form-group select:focus,
        .modal-content .form-group textarea:focus {
            border-color: #d4a0a0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(212,160,160,0.12);
        }
        .modal-content .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .modal-content .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #f0edea;
        }
        .modal-content .form-actions .btn-primary {
            padding: 12px 28px;
            background: #1a1a1a;
            color: #f0e8e0;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            flex: 1;
        }
        .modal-content .form-actions .btn-primary:hover:not(:disabled) {
            background: #2a2a2a;
        }
        .modal-content .form-actions .btn-primary:disabled {
            background: #d4c9c0;
            cursor: not-allowed;
        }
        .modal-content .form-actions .btn-secondary {
            padding: 12px 24px;
            background: #f0edea;
            color: #4a3a2e;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .modal-content .form-actions .btn-secondary:hover {
            background: #e8e0d8;
        }
        .modal-content .restricted-warning {
            background: #f0ddd8;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            border-left: 4px solid #ea4335;
        }
        .modal-content .restricted-warning .warning-title {
            font-weight: 600;
            color: #8a3a2a;
            font-size: 15px;
        }
        .modal-content .restricted-warning .warning-text {
            color: #6a3a2a;
            font-size: 14px;
            margin-top: 4px;
        }
        .modal-content .restricted-warning .fine-list {
            margin-top: 8px;
            padding-left: 20px;
            font-size: 13px;
            color: #6a3a2a;
        }
        .modal-content .restricted-warning .fine-list li {
            margin: 2px 0;
        }

        .search-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }
        .search-tag {
            padding: 6px 16px;
            background: #ffffff;
            border: 1px solid #e8e0d8;
            border-radius: 20px;
            font-size: 13px;
            color: #6a5a4e;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .search-tag:hover {
            border-color: #d4a0a0;
            color: #1a1a1a;
            background: #faf8f6;
        }

        .message {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .message.success { 
            background: #e8ddd8; 
            color: #3a2a2a; 
            border-left: 4px solid #d4a0a0;
        }
        .message.error { 
            background: #f0e0d8; 
            color: #8a3a2a; 
            border-left: 4px solid #d48080;
        }
        .message.info { 
            background: #e8e4e0; 
            color: #3a3a3a; 
            border-left: 4px solid #b0a8a0;
        }

        .table-container {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e8e0d8;
            overflow-x: auto;
        }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            background: #f5f3f0;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #4a3a2e;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td { 
            padding: 12px 16px; 
            border-top: 1px solid #f0edea; 
            vertical-align: middle;
            color: #2a2a2a;
            font-size: 14px;
        }
        .data-table tr:hover td {
            background: #faf8f6;
        }

        .status-badge {
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
            font-weight: 500;
        }
        .status-borrowed { background: #e8e4e0; color: #4a3a2e; }
        .status-returned { background: #e8ddd8; color: #3a2a2a; }
        .status-overdue { background: #f0e0d8; color: #8a3a2a; animation: pulse 2s infinite; }
        .status-pending { background: #e8e0d8; color: #6a5a4e; }
        .status-paid { background: #dde8e0; color: #2a4a3a; }
        .status-waived { background: #e8e8d8; color: #4a4a2a; }
        .status-fulfilled { background: #dde8e0; color: #1a4a3a; }
        .status-expired { background: #e8ddd8; color: #6a3a2a; }
        .status-cancelled { background: #e8e4e0; color: #6a5a4e; }
        .status-approved { background: #dde8e0; color: #1a4a3a; }
        .status-rejected { background: #f0ddd8; color: #8a3a2a; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .notification-container {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e8e0d8;
            overflow: hidden;
        }
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-bottom: 1px solid #f0edea;
            background: #faf8f6;
        }
        .notification-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1a1a1a;
            font-weight: 600;
        }
        .notification-header .mark-all-read {
            font-size: 13px;
            color: #d4a0a0;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }
        .notification-header .mark-all-read:hover {
            color: #b88080;
        }
        .notification-item {
            padding: 16px 24px;
            border-bottom: 1px solid #f0edea;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            transition: background 0.2s ease;
            cursor: pointer;
        }
        .notification-item:hover {
            background: #faf8f6;
        }
        .notification-item.unread {
            background: #f8f0ee;
            border-left: 4px solid #e51d66;
        }
        .notification-item .notif-icon {
            font-size: 20px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #f0edea;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .notification-item .notif-icon.fine { background: #f0ddd8; color: #8a3a2a; }
        .notification-item .notif-icon.borrow { background: #dde8e0; color: #1a4a3a; }
        .notification-item .notif-icon.reservation { background: #e0dde8; color: #3a1a4a; }
        .notification-item .notif-icon.system { background: #e8e4e0; color: #4a3a2e; }
        .notification-item .notif-icon.available { background: #dde8d8; color: #1a5a2a; }
        .notification-item .notif-icon.overdue { background: #f0ddd8; color: #8a3a2a; }
        .notification-item .notif-content { flex: 1; }
        .notification-item .notif-content .notif-title {
            font-weight: 600;
            color: #1a1a1a;
            font-size: 14px;
        }
        .notification-item .notif-content .notif-message {
            color: #6a5a4e;
            font-size: 13px;
            margin-top: 2px;
            line-height: 1.4;
        }
        .notification-item .notif-content .notif-time {
            color: #b0a8a0;
            font-size: 11px;
            margin-top: 4px;
            display: block;
        }
        .notification-item .notif-actions {
            margin-top: 6px;
            display: flex;
            gap: 8px;
        }
        .notification-item .notif-actions .btn-sm {
            padding: 4px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        .notification-item .notif-actions .btn-sm.btn-primary {
            background: #1a1a1a;
            color: #f0e8e0;
        }
        .notification-item .notif-actions .btn-sm.btn-primary:hover {
            background: #2a2a2a;
        }
        .notification-item .notif-actions .btn-sm.btn-secondary {
            background: #f0edea;
            color: #4a3a2e;
        }
        .notification-item .notif-actions .btn-sm.btn-secondary:hover {
            background: #e8e0d8;
        }
        .notification-item .notif-status {
            margin-left: auto;
            font-size: 11px;
            color: #b0a8a0;
            white-space: nowrap;
            padding-top: 2px;
        }
        .notification-item .notif-status .unread-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #e51d66;
            margin-right: 6px;
        }
        .no-notifications {
            padding: 40px;
            text-align: center;
            color: #9a8a7e;
        }
        .no-notifications .no-notif-icon {
            font-size: 42px;
            display: block;
            margin-bottom: 12px;
            opacity: 0.3;
        }

        .no-data { 
            text-align: center; 
            padding: 40px !important; 
            color: #9a8a7e; 
        }
        .no-data .no-data-icon { 
            font-size: 42px; 
            display: block; 
            margin-bottom: 12px; 
            opacity: 0.4;
        }
        .no-data .no-data-hint { 
            font-size: 14px; 
            color: #c0b4a8; 
        }

        .profile-container {
            background: #ffffff;
            border-radius: 16px;
            padding: 32px 36px;
            max-width: 600px;
            border: 1px solid #e8e0d8;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f0e8e0;
            font-size: 32px;
            font-weight: 600;
        }
        .profile-field {
            padding: 8px 0;
            border-bottom: 1px solid #f0edea;
        }
        .profile-field:last-child {
            border-bottom: none;
        }
        .profile-field .label {
            font-weight: 600;
            color: #4a3a2e;
            font-size: 13px;
        }
        .profile-field .value {
            color: #1a1a1a;
            font-size: 14px;
        }

        .nlp-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 20px;
            background: #f0edea;
        }
        .nlp-status .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .nlp-status .dot.online {
            background: #34a853;
        }
        .nlp-status .dot.offline {
            background: #ea4335;
        }

        .nlp-starting {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            padding: 4px 14px;
            border-radius: 20px;
            background: #f0e8d8;
            color: #8a7a3a;
        }
        .nlp-starting .spinner {
            width: 14px;
            height: 14px;
            border: 2px solid #d4c9c0;
            border-top-color: #8a7a3a;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .reservation-actions {
            display: flex;
            gap: 8px;
        }
        .btn-cancel {
            padding: 4px 14px;
            background: #f0ddd8;
            color: #8a3a2a;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-cancel:hover {
            background: #e8c8c0;
        }

        .fine-amount {
            font-weight: 600;
        }
        .fine-amount.pending { color: #8a3a2a; }
        .fine-amount.paid { color: #1a4a3a; }

        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .book-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
        }
        @media (max-width: 768px) {
            .student-sidebar { width: 70px; }
            .sidebar-header h2, .sidebar-header p, .sidebar-header small, 
            .sidebar-header .subtitle, .sidebar-nav a .nav-label { display: none; }
            .sidebar-nav a { justify-content: center; padding: 14px; font-size: 20px; }
            .sidebar-nav a .nav-icon { font-size: 22px; }
            .notification-badge { right: 8px; min-width: 16px; height: 16px; font-size: 8px; }
            .student-content { margin-left: 70px; padding: 20px 24px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .quick-actions { grid-template-columns: 1fr 1fr; }
            .book-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
            .search-info { width: 100%; justify-content: flex-start; }
            .dashboard-header { flex-direction: column; align-items: flex-start; }
            .header-time { width: 100%; text-align: left; }
            .profile-container { padding: 24px 20px; }
            .status-card { flex-direction: column; text-align: center; }
            .modal-content { padding: 24px 20px; }
            .request-form-container { padding: 24px 20px; }
        }
        @media (max-width: 480px) {
            .mobile-menu-toggle { display: flex !important; align-items: center; justify-content: center; }
            .student-sidebar {
                position: fixed !important;
                top: 0 !important; left: 0 !important;
                width: 280px !important;
                height: 100vh !important;
                z-index: 1000 !important;
                transform: translateX(-100%) !important;
                transition: transform 0.3s ease !important;
                box-shadow: 2px 0 30px rgba(0,0,0,0.2) !important;
                padding-top: 60px !important;
            }
            .student-sidebar.mobile-open { transform: translateX(0) !important; }
            .mobile-overlay { display: block !important; }
            .student-content { margin-left: 0 !important; padding: 70px 12px 12px !important; }
            .sidebar-header h2, .sidebar-header p, .sidebar-header small, 
            .sidebar-header .subtitle, .sidebar-nav a .nav-label { display: block !important; }
            .sidebar-nav a { justify-content: flex-start; padding: 12px 20px; font-size: 14px; }
            .sidebar-nav a .nav-icon { font-size: 18px; }
            .notification-badge { right: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-number { font-size: 20px; }
            .book-grid { grid-template-columns: 1fr; }
            .book-card .book-cover-wrapper { height: 160px; }
            .search-bar .search-input-wrapper input { font-size: 14px; padding: 12px 14px 12px 45px; }
            .dashboard-header { padding: 20px; }
            .dashboard-header h1 { font-size: 18px; }
            .quick-actions { grid-template-columns: 1fr 1fr; }
            .profile-container { padding: 20px 16px; }
            .book-actions { width: 100%; }
            .book-actions .btn-borrow, .book-actions .btn-reserve { flex: 1; text-align: center; }
            .modal-content .form-actions { flex-direction: column; }
            .modal-content .form-actions .btn-primary,
            .modal-content .form-actions .btn-secondary { width: 100%; text-align: center; }
            .request-form-container .form-actions { flex-direction: column; }
            .request-form-container .form-actions .btn-submit,
            .request-form-container .form-actions .btn-cancel { width: 100%; text-align: center; }
            .request-item { flex-direction: column; align-items: flex-start; }
        }
        .ai-assistant-popup {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: #ffffff;
    border-radius: 16px;
    padding: 24px 28px;
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    border: 1px solid #e8e0d8;
    z-index: 9999;
    display: none;
    animation: slideUp 0.3s ease;
}
.ai-assistant-popup.visible { display: block; }
.ai-assistant-popup .popup-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}
.ai-assistant-popup .popup-header h3 {
    margin: 0;
    font-size: 16px;
    color: #1a1a1a;
    font-weight: 600;
}
.ai-assistant-popup .popup-header .close-popup {
    background: none;
    border: none;
    font-size: 22px;
    color: #9a8a7e;
    cursor: pointer;
    padding: 0 4px;
}
.ai-assistant-popup .popup-body {
    color: #4a3a2e;
    font-size: 14px;
    line-height: 1.6;
}
.ai-assistant-popup .popup-body ul {
    margin: 8px 0 12px 20px;
    color: #6a5a4e;
    font-size: 13px;
}
.ai-assistant-popup .popup-actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
    flex-wrap: wrap;
}
.ai-assistant-popup .popup-actions .btn-help {
    padding: 8px 20px;
    background: #1a1a1a;
    color: #f0e8e0;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
    text-decoration: none;
}
.ai-assistant-popup .popup-actions .btn-help:hover {
    background: #2a2a2a;
    transform: translateY(-1px);
}
.ai-assistant-popup .popup-actions .btn-dismiss {
    padding: 8px 20px;
    background: #f0edea;
    color: #4a3a2e;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
}
.ai-assistant-popup .popup-actions .btn-dismiss:hover {
    background: #e8e0d8;
}

/* Predictive Search Results - matches existing design */
.predictive-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #ffffff;
    border: 2px solid #e8e0d8;
    border-top: none;
    border-radius: 0 0 12px 12px;
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}
.predictive-results.visible { display: block; }
.predictive-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f0edea;
    cursor: pointer;
    transition: background 0.2s ease;
}
.predictive-item:hover {
    background: #faf8f6;
}
.predictive-item .pred-title {
    font-weight: 500;
    color: #1a1a1a;
    font-size: 14px;
}
.predictive-item .pred-author {
    color: #6a5a4e;
    font-size: 13px;
}
.predictive-item .pred-score {
    float: right;
    font-size: 12px;
    color: #9a8a7e;
}
.predictive-item .pred-badge {
    display: inline-block;
    background: #dde8e0;
    color: #1a4a3a;
    font-size: 10px;
    padding: 2px 10px;
    border-radius: 10px;
    margin-left: 8px;
}
.loading-predictions {
    padding: 20px;
    text-align: center;
    color: #9a8a7e;
}
.loading-predictions .spinner {
    width: 24px;
    height: 24px;
    border: 3px solid #f0edea;
    border-top-color: #1a1a1a;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    display: inline-block;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
    </style>
</head>
<body>
    <div class="student-app">
        <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
            <span class="hamburger-icon"><span></span><span></span><span></span></span>
        </button>

        <div class="student-sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="../img/agustinnb.png" alt="BCP Logo" class="sidebar-logo">
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
                        <span class="notification-badge" style="position:relative;right:auto;top:auto;transform:none;margin-left:auto;"><?php echo count($pendingRequests); ?></span>
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

        <div class="student-content">
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'Error') !== false || strpos($message, 'restricted') !== false || strpos($message, 'not available') !== false ? 'error' : 'success'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage && !empty($searchQuery)): ?>
                <div class="message info">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($section === 'dashboard'): ?>
            <!-- ===== DASHBOARD ===== -->
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
                                <?php if ($hasOverdue): ?>⚠️ You have overdue books. Please return them to restore access.<?php endif; ?>
                                <?php if ($hasUnpaidFines): ?>⚠️ You have unpaid fines (₱<?php echo number_format($totalPendingFines, 2); ?>). Please settle them to restore access.<?php endif; ?>
                            <?php elseif ($accountStatus === 'Active'): ?>
                                You have active borrowings. Please return them on time.
                            <?php else: ?>
                                Your account is in good standing. You may borrow books.
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
                        <?php if (count($overdueBorrowings) > 0): ?>
                            <div class="stat-sub" style="color:#8a3a2a;">⚠️ Action Required</div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($reservations); ?></div>
                        <div class="stat-label">Reservations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">₱<?php echo number_format($totalPendingFines, 2); ?></div>
                        <div class="stat-label">Pending Fines</div>
                        <?php if ($totalPendingFines > 0): ?>
                            <div class="stat-sub" style="color:#8a3a2a;">Due: <?php echo number_format($totalPendingFines, 2); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="quick-actions">
                    <a href="student_dashboard.php?section=search" class="quick-action-card">
                        <span class="action-icon">◐</span>
                        <span class="action-label">Search Books</span>
                    </a>
                    <a href="student_dashboard.php?section=borrowings" class="quick-action-card">
                        <span class="action-icon">◈</span>
                        <span class="action-label">My Borrowings</span>
                    </a>
                    <a href="student_dashboard.php?section=reservations" class="quick-action-card">
                        <span class="action-icon">◑</span>
                        <span class="action-label">Reservations</span>
                    </a>
                    <a href="student_dashboard.php?section=requests" class="quick-action-card" style="position:relative;">
                        <span class="action-icon">📋</span>
                        <span class="action-label">My Requests</span>
                        <?php if (!empty($pendingRequests)): ?>
                            <span class="action-badge"><?php echo count($pendingRequests); ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <div style="background:#ffffff;border-radius:16px;padding:20px 24px;border:1px solid #e8e0d8;">
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
                        <p style="color:#9a8a7e;text-align:center;padding:20px;">No borrowings yet. Start exploring books!</p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($notifications)): ?>
                <div style="background:#ffffff;border-radius:16px;padding:20px 24px;border:1px solid #e8e0d8;margin-top:20px;">
                    <h3 style="margin:0 0 16px 0;color:#1a1a1a;font-weight:600;">
                        Recent Notifications
                        <?php if ($notificationCount > 0): ?>
                            <span style="font-size:12px;font-weight:400;color:#6a5a4e;margin-left:8px;">(<?php echo $notificationCount; ?> unread)</span>
                        <?php endif; ?>
                    </h3>
                    <?php foreach (array_slice($notifications, 0, 3) as $n): ?>
                        <div style="padding:12px 16px;border-bottom:1px solid #f0edea;display:flex;align-items:flex-start;gap:12px;<?php echo !($n['is_read'] ?? false) ? 'background:#f8f0ee;border-left:3px solid #e51d66;' : ''; ?>">
                            <span style="font-size:18px;"><?php echo $n['icon'] ?? '📢'; ?></span>
                            <div style="flex:1;">
                                <div style="font-weight:500;color:#1a1a1a;font-size:14px;"><?php echo htmlspecialchars($n['title'] ?? 'Notification'); ?></div>
                                <div style="color:#6a5a4e;font-size:13px;"><?php echo htmlspecialchars($n['message'] ?? ''); ?></div>
                                <div style="color:#b0a8a0;font-size:11px;margin-top:2px;"><?php echo date('M d, Y g:i A', strtotime($n['created_at'] ?? 'now')); ?></div>
                            </div>
                            <?php if (!($n['is_read'] ?? false)): ?>
                                <a href="student_dashboard.php?action=mark_notification_read&notification_id=<?php echo $n['id']; ?>&section=dashboard" style="font-size:12px;color:#d4a0a0;text-decoration:none;">Mark read</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($notifications) > 3): ?>
                        <div style="text-align:center;padding-top:12px;">
                            <a href="student_dashboard.php?section=notifications" style="color:#d4a0a0;text-decoration:none;font-weight:500;">View all notifications →</a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php elseif ($section === 'search'): ?>
            <!-- ===== SEARCH PAGE ===== -->
            <div class="book-management">
                <div class="section-header">
                    <h1>Search Books</h1>
                    <div class="search-info">
                        <?php if (!empty($searchQuery)): ?>
                            <span class="search-type-badge <?php echo $searchTypeUsed; ?>">
                                <?php 
                                    if ($searchTypeUsed === 'semantic' || $searchTypeUsed === 'semantic_nlp' || $searchTypeUsed === 'nlp') {
                                        echo '🧠 Semantic NLP';
                                    } elseif ($searchTypeUsed === 'basic_fallback') {
                                        echo '⚠️ Basic (Fallback)';
                                    } elseif ($searchTypeUsed === 'basic') {
                                        echo '📝 Basic Search';
                                    } else {
                                        echo '📚 All Books';
                                    }
                                ?>
                            </span>
                            <?php if ($searchTime > 0): ?>
                                <span class="search-time">⏱ <?php echo $searchTime; ?>ms</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <span class="count-badge">Books: <strong><?php echo count($searchResults); ?></strong></span>
                        <?php if ($nlpAvailable): ?>
                            <span class="nlp-status">
                                <span class="dot online"></span> NLP Online
                            </span>
                        <?php else: ?>
                            <span class="nlp-status">
                                <span class="dot offline"></span> NLP Offline
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="search-bar">
                    <div class="search-input-wrapper">
                        <span class="search-icon">⌕</span>
                        <input type="text" id="searchInput" 
                               placeholder="Search by title, author, ISBN, or description..." 
                               value="<?php echo htmlspecialchars($searchQuery); ?>"
                               onkeydown="if(event.key==='Enter') performSearch()">
                        <button class="clear-btn <?php echo !empty($searchQuery) ? 'visible' : ''; ?>" 
                                id="clearSearchBtn" onclick="clearSearch()">✕</button>
                    </div>
                    <button onclick="performSearch()" style="padding:14px 24px;background:#1a1a1a;color:#f0e8e0;border:none;border-radius:12px;cursor:pointer;font-size:15px;font-weight:500;transition:all 0.2s ease;">Search</button>
                </div>

                <div class="search-tags">
                    <span class="search-tag" onclick="quickSearch('programming')">Programming</span>
                    <span class="search-tag" onclick="quickSearch('design')">Design</span>
                    <span class="search-tag" onclick="quickSearch('psychology')">Psychology</span>
                    <span class="search-tag" onclick="quickSearch('business')">Business</span>
                    <span class="search-tag" onclick="quickSearch('science')">Science</span>
                    <span class="search-tag" onclick="quickSearch('fiction')">Fiction</span>
                    <span class="search-tag" onclick="quickSearch('self-help')">Self-Help</span>
                    <span class="search-tag" onclick="quickSearch('history')">History</span>
                </div>

                <?php if (!empty($searchResults)): ?>
                    <div class="book-grid" id="bookGrid">
                        <?php foreach ($searchResults as $book): 
                            $categoryName = isset($book['categories']['name']) ? $book['categories']['name'] : (isset($book['category']) ? $book['category'] : 'Uncategorized');
                            $coverImage = $book['cover_image'] ?? '';
                            $hasCover = hasValidCoverImage($coverImage);
                            $available = $book['available'] ?? 0;
                            $availabilityClass = $available <= 0 ? 'none' : ($available <= 2 ? 'low' : '');
                            $availabilityText = $available <= 0 ? 'Not Available' : ($available <= 2 ? 'Low Stock' : 'Available');
                            $relevance = $book['relevance'] ?? $book['relevance_score'] ?? null;
                            $searchType = $book['search_type'] ?? $searchTypeUsed;
                            $isSemantic = ($searchType === 'semantic' || $searchType === 'semantic_nlp' || $searchType === 'nlp' || $searchTypeUsed === 'semantic' || $searchTypeUsed === 'semantic_nlp');
                            $bookId = $book['id'] ?? uniqid();
                            $title = $book['title'] ?? 'Unknown';
                            $author = $book['author'] ?? 'Unknown';
                            $description = $book['description'] ?? 'No description available.';
                            
                            $hasReservation = !empty(array_filter($reservations, function($r) use ($bookId) {
                                return $r['book_id'] == $bookId && ($r['status'] ?? '') === 'Pending';
                            }));
                            
                            // Check if user has a pending request for this book
                            $hasPendingRequest = !empty(array_filter($studentRequests, function($r) use ($bookId) {
                                return $r['book_id'] == $bookId && ($r['status'] ?? '') === 'Pending';
                            }));
                            
                            // Check if user has an approved request for this book
                            $hasApprovedRequest = !empty(array_filter($studentRequests, function($r) use ($bookId) {
                                return $r['book_id'] == $bookId && ($r['status'] ?? '') === 'Approved';
                            }));
                            
                            $canBorrow = !$isRestricted && $available > 0 && $hasApprovedRequest;
                            $canReserve = !$isRestricted && !$hasReservation && $available <= 0 && $hasApprovedRequest;
                            $canRequest = !$isRestricted && !$hasPendingRequest && !$hasApprovedRequest;
                        ?>
                            <div class="book-card">
                                <div class="book-cover-wrapper">
                                    <?php if ($hasCover): ?>
                                        <img src="<?php echo htmlspecialchars($coverImage); ?>" 
                                             alt="<?php echo htmlspecialchars($title); ?>" 
                                             onerror="this.style.display='none';this.parentElement.querySelector('.cover-placeholder').style.display='flex';">
                                        <div class="cover-placeholder" style="display:none;background-color:<?php echo getPlaceholderColor($bookId); ?>;">
                                            <span class="initial"><?php echo strtoupper(substr($title, 0, 1)); ?></span>
                                            <span class="placeholder-label">No Image</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="cover-placeholder" style="background-color:<?php echo getPlaceholderColor($bookId); ?>;">
                                            <span class="initial"><?php echo strtoupper(substr($title, 0, 1)); ?></span>
                                            <span class="placeholder-label">No Image</span>
                                        </div>
                                    <?php endif; ?>
                                    <span class="availability-badge <?php echo $availabilityClass; ?>">
                                        <?php echo $availabilityText; ?>
                                    </span>
                                </div>
                                <div class="book-info">
                                    <h3 class="book-title"><?php echo htmlspecialchars($title); ?></h3>
                                    <p class="book-author">by <?php echo htmlspecialchars($author); ?></p>
                                    <span class="book-category"><?php echo htmlspecialchars($categoryName); ?></span>
                                    <?php if ($isSemantic): ?>
                                        <span class="semantic-badge">🧠 Semantic Match</span>
                                    <?php endif; ?>
                                    <p class="book-description"><?php echo htmlspecialchars(substr($description, 0, 120)); ?></p>
                                    <?php if ($relevance !== null): ?>
                                        <span class="relevance-score">Relevance: <?php echo round($relevance, 1); ?>%</span>
                                    <?php endif; ?>
                                    <div class="book-meta">
                                        <span class="availability"><strong><?php echo $available; ?></strong> / <?php echo $book['quantity'] ?? 0; ?> available</span>
                                        <div class="book-actions">
                                            <?php if ($isRestricted): ?>
                                                <button class="btn-borrow" disabled onclick="showRestrictedModal('<?php echo htmlspecialchars($title); ?>', '<?php echo $bookId; ?>')">
                                                    Restricted
                                                </button>
                                                <button class="btn-reserve" disabled>Restricted</button>
                                            <?php elseif ($canBorrow): ?>
                                                <button class="btn-borrow" onclick="openBorrowModal('<?php echo $bookId; ?>', '<?php echo htmlspecialchars($title); ?>', '<?php echo htmlspecialchars($author); ?>', <?php echo $available; ?>)">
                                                    Borrow
                                                </button>
                                                <?php if ($available <= 2): ?>
                                                    <button class="btn-reserve" onclick="openReserveModal('<?php echo $bookId; ?>', '<?php echo htmlspecialchars($title); ?>', '<?php echo htmlspecialchars($author); ?>')">
                                                        Reserve
                                                    </button>
                                                <?php endif; ?>
                                            <?php elseif ($canReserve): ?>
                                                <button class="btn-reserve" onclick="openReserveModal('<?php echo $bookId; ?>', '<?php echo htmlspecialchars($title); ?>', '<?php echo htmlspecialchars($author); ?>')">
                                                    Reserve
                                                </button>
                                            <?php elseif ($hasPendingRequest): ?>
                                                <span class="btn-request" style="background:#d4c9c0;cursor:not-allowed;opacity:0.6;">Request Pending</span>
                                            <?php elseif ($canRequest): ?>
                                                <button class="btn-request" onclick="openRequestForm('<?php echo $bookId; ?>', '<?php echo htmlspecialchars($title); ?>', '<?php echo htmlspecialchars($author); ?>', 'borrow')">
                                                    Request to Borrow
                                                </button>
                                                <button class="btn-request" onclick="openRequestForm('<?php echo $bookId; ?>', '<?php echo htmlspecialchars($title); ?>', '<?php echo htmlspecialchars($author); ?>', 'reserve')">
                                                    Request to Reserve
                                                </button>
                                            <?php elseif ($hasReservation): ?>
                                                <span class="btn-reserve" style="background:#d4c9c0;cursor:not-allowed;opacity:0.6;">Pending</span>
                                            <?php else: ?>
                                                <span class="btn-borrow" style="background:#d4c9c0;cursor:not-allowed;color:#8a7a6e;">Not Available</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="background:#ffffff;border-radius:16px;padding:60px 40px;text-align:center;border:1px solid #e8e0d8;">
                        <span style="font-size:56px;display:block;margin-bottom:16px;opacity:0.4;">▣</span>
                        <?php if (!empty($searchQuery)): ?>
                            <h3 style="color:#1a1a1a;margin-bottom:8px;">No books found</h3>
                            <p style="color:#9a8a7e;font-size:15px;">No books matching "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"</p>
                            <p style="color:#c0b4a8;font-size:13px;margin-top:8px;">Try searching by title, author, ISBN, or description</p>
                            <div style="margin-top:16px;">
                                <span class="search-tag" onclick="clearSearch()">Clear Search</span>
                            </div>
                        <?php else: ?>
                            <h3 style="color:#1a1a1a;margin-bottom:8px;">Search for books</h3>
                            <p style="color:#9a8a7e;font-size:15px;">Enter a search query to find books in the library</p>
                            <p style="color:#c0b4a8;font-size:13px;margin-top:8px;">You can search by title, author, ISBN, or description</p>
                            <div style="margin-top:16px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                                <span class="search-tag" onclick="quickSearch('programming')">Programming</span>
                                <span class="search-tag" onclick="quickSearch('design')">Design</span>
                                <span class="search-tag" onclick="quickSearch('psychology')">Psychology</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php elseif ($section === 'request_form'): ?>
            <!-- ===== REQUEST FORM PAGE ===== -->
            <div class="request-form-container">
                <div class="form-header">
                    <h1 style="margin:0;font-size:22px;color:#1a1a1a;font-weight:600;">📋 Book Request Form</h1>
                    <p style="color:#6a5a4e;font-size:14px;margin:4px 0 0;">Please fill out this form to request a book.</p>
                    
                    <?php if (!empty($requestBookData)): ?>
                        <div class="book-preview">
                            <div class="preview-title"><?php echo htmlspecialchars($requestBookData['title'] ?? 'Unknown Book'); ?></div>
                            <div class="preview-author">by <?php echo htmlspecialchars($requestBookData['author'] ?? 'Unknown'); ?></div>
                            <div style="font-size:13px;color:#6a5a4e;margin-top:2px;">
                                <?php echo isset($requestBookData['categories']['name']) ? htmlspecialchars($requestBookData['categories']['name']) : 'Uncategorized'; ?> • 
                                <?php echo ($requestBookData['available'] ?? 0); ?> / <?php echo ($requestBookData['quantity'] ?? 0); ?> available
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="book-preview" style="background:#f0edea;text-align:center;">
                            <p style="color:#6a5a4e;margin:0;">Book information not available</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($requestMessage): ?>
                    <div class="message <?php echo strpos($requestMessage, 'Error') !== false ? 'error' : 'info'; ?>">
                        <?php echo htmlspecialchars($requestMessage); ?>
                    </div>
                <?php endif; ?>

                <div class="info-note">
                    <strong>📌 Note:</strong> Your request will be reviewed by the admin. You will be notified once approved.
                </div>

                <form method="POST" action="student_dashboard.php?section=request_form">
                    <input type="hidden" name="book_id" value="<?php echo htmlspecialchars($requestBookId); ?>">
                    <input type="hidden" name="request_type" value="<?php echo htmlspecialchars($requestType); ?>">
                    
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Student ID <span class="required">*</span></label>
                        <input type="text" name="student_id" value="<?php echo htmlspecialchars($studentData['student_id'] ?? $_SESSION['user_id_display'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Year Level <span class="required">*</span></label>
                        <select name="year_level" required>
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
                        <input type="text" name="section" value="<?php echo htmlspecialchars($studentData['section'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Request Type</label>
                        <input type="text" value="<?php echo ucfirst($requestType); ?>" disabled style="background:#f5f3f0;">
                        <input type="hidden" name="request_type" value="<?php echo htmlspecialchars($requestType); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Purpose of Request <span class="required">*</span></label>
                        <textarea name="purpose" placeholder="Please explain why you need to borrow/reserve this book..." rows="3" required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <a href="student_dashboard.php?section=search" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-submit">Submit Request</button>
                    </div>
                </form>
            </div>

            <?php elseif ($section === 'requests'): ?>
            <!-- ===== MY REQUESTS PAGE ===== -->
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
                                    by <?php echo htmlspecialchars($bookAuthor); ?> • 
                                    <?php echo ucfirst($requestType); ?> request • 
                                    <span class="request-date"><?php echo date('M d, Y', strtotime($createdAt)); ?></span>
                                    <?php if (!empty($request['purpose'])): ?>
                                        <br><span style="font-size:12px;color:#8a7a6e;">Purpose: <?php echo htmlspecialchars($request['purpose']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($request['verification_notes'])): ?>
                                        <br><span style="font-size:12px;color:#8a7a6e;">Note: <?php echo htmlspecialchars($request['verification_notes']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <span class="request-status <?php echo strtolower($status); ?>">
                                    <?php echo $status; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="background:#ffffff;border-radius:16px;padding:60px 40px;text-align:center;border:1px solid #e8e0d8;">
                        <span style="font-size:56px;display:block;margin-bottom:16px;opacity:0.4;">📋</span>
                        <h3 style="color:#1a1a1a;margin-bottom:8px;">No Requests Yet</h3>
                        <p style="color:#9a8a7e;font-size:15px;">You haven't submitted any book requests.</p>
                        <div style="margin-top:16px;">
                            <a href="student_dashboard.php?section=search" class="btn-borrow" style="text-decoration:none;">Browse Books</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php elseif ($section === 'borrowings'): ?>
            <!-- ===== MY BORROWINGS ===== -->
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
                                <?php foreach ($borrowings as $b): 
                                    $bookTitle = $b['books']['title'] ?? 'Unknown';
                                    $bookAuthor = $b['books']['author'] ?? 'Unknown';
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($bookTitle); ?></strong></td>
                                        <td><?php echo htmlspecialchars($bookAuthor); ?></td>
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
            <!-- ===== RESERVATIONS ===== -->
            <div class="reservation-management">
                <div class="section-header">
                    <h1>My Reservations</h1>
                    <span class="count-badge"><?php echo count($reservations); ?> reservations</span>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr><th>Book</th><th>Reserved</th><th>Expiry Date</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($reservations)): ?>
                                <?php foreach ($reservations as $r): 
                                    $bookTitle = $r['books']['title'] ?? 'Unknown';
                                    $bookAuthor = $r['books']['author'] ?? 'Unknown';
                                    $bookAvailable = $r['books']['available'] ?? 0;
                                    $isPending = ($r['status'] ?? '') === 'Pending';
                                    $isFulfilled = ($r['status'] ?? '') === 'Fulfilled';
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($bookTitle); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($r['reservation_date'] ?? 'now')); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($r['expiry_date'] ?? 'now')); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($r['status'] ?? 'pending'); ?>">
                                                <?php echo $r['status'] ?? 'Pending'; ?>
                                            </span>
                                            <?php if ($isPending && $bookAvailable > 0): ?>
                                                <span style="font-size:11px;color:#34a853;display:block;margin-top:2px;">✅ Book now available!</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isPending && !$isRestricted): ?>
                                                <div class="reservation-actions">
                                                    <?php if ($bookAvailable > 0): ?>
                                                        <button class="btn-borrow" style="font-size:12px;padding:4px 12px;" 
                                                                onclick="openBorrowModal('<?php echo $r['book_id']; ?>', '<?php echo htmlspecialchars($bookTitle); ?>', '<?php echo htmlspecialchars($bookAuthor); ?>', <?php echo $bookAvailable; ?>)">
                                                            Borrow Now
                                                        </button>
                                                    <?php endif; ?>
                                                    <a href="student_dashboard.php?section=reservations&action=cancel&reservation_id=<?php echo $r['id']; ?>" 
                                                       class="btn-cancel"
                                                       onclick="return confirm('Cancel this reservation?')">
                                                        Cancel
                                                    </a>
                                                </div>
                                            <?php elseif ($isPending && $isRestricted): ?>
                                                <span style="color:#8a3a2a;font-size:12px;">⚠️ Account restricted</span>
                                            <?php elseif ($isFulfilled): ?>
                                                <span style="color:#4a3a2e;font-size:13px;">✓ Borrowed</span>
                                            <?php else: ?>
                                                <span style="color:#9a8a7e;font-size:13px;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="no-data">
                                    <span class="no-data-icon">◑</span>
                                    No reservations found.
                                    <div class="no-data-hint">Reserve books that are currently unavailable.</div>
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($section === 'fines'): ?>
            <!-- ===== MY FINES ===== -->
            <div class="fine-management">
                <div class="section-header">
                    <h1>My Fines</h1>
                    <span class="count-badge">Total: ₱<?php echo number_format($totalFines, 2); ?></span>
                </div>
                <div style="background:#ffffff;border-radius:12px;padding:16px 20px;margin-bottom:20px;border:1px solid #e8e0d8;">
                    <p><strong>Pending Fines:</strong> ₱<?php echo number_format($totalPendingFines, 2); ?> (<?php echo count($pendingFines); ?> pending)</p>
                    <?php if ($totalPendingFines > 0): ?>
                        <p style="color:#8a3a2a;font-size:13px;margin-top:4px;">⚠️ Please settle your pending fines to restore your account access.</p>
                    <?php endif; ?>
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
                                        <td class="fine-amount <?php echo strtolower($f['status'] ?? 'pending'); ?>">₱<?php echo number_format($f['amount'] ?? 0, 2); ?></td>
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
            <!-- ===== NOTIFICATIONS ===== -->
            <div class="notification-section">
                <div class="section-header">
                    <h1>Notifications</h1>
                    <span class="count-badge"><?php echo count($notifications); ?> total</span>
                </div>
                <div class="notification-container">
                    <div class="notification-header">
                        <h3>📬 All Notifications</h3>
                        <?php if ($notificationCount > 0): ?>
                            <a href="student_dashboard.php?action=mark_all_read&section=notifications" class="mark-all-read">Mark all as read</a>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $n): 
                            $isUnread = !($n['is_read'] ?? false);
                            $icon = $n['icon'] ?? '📢';
                            $iconClass = '';
                            if (strpos($n['type'] ?? '', 'fine') !== false) $iconClass = 'fine';
                            elseif (strpos($n['type'] ?? '', 'borrow') !== false) $iconClass = 'borrow';
                            elseif (strpos($n['type'] ?? '', 'reservation') !== false) $iconClass = 'reservation';
                            elseif (strpos($n['type'] ?? '', 'available') !== false) $iconClass = 'available';
                            elseif (strpos($n['type'] ?? '', 'overdue') !== false) $iconClass = 'overdue';
                            else $iconClass = 'system';
                        ?>
                            <div class="notification-item <?php echo $isUnread ? 'unread' : ''; ?>">
                                <div class="notif-icon <?php echo $iconClass; ?>"><?php echo $icon; ?></div>
                                <div class="notif-content">
                                    <div class="notif-title"><?php echo htmlspecialchars($n['title'] ?? 'Notification'); ?></div>
                                    <div class="notif-message"><?php echo htmlspecialchars($n['message'] ?? ''); ?></div>
                                    <span class="notif-time"><?php echo date('M d, Y g:i A', strtotime($n['created_at'] ?? 'now')); ?></span>
                                    <?php if (!empty($n['action_url']) && !empty($n['action_label'])): ?>
                                        <div class="notif-actions">
                                            <a href="<?php echo htmlspecialchars($n['action_url']); ?>" class="btn-sm btn-primary"><?php echo htmlspecialchars($n['action_label']); ?></a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="notif-status">
                                    <?php if ($isUnread): ?>
                                        <span class="unread-dot"></span>
                                        <a href="student_dashboard.php?action=mark_notification_read&notification_id=<?php echo $n['id']; ?>&section=notifications" 
                                           style="font-size:11px;color:#d4a0a0;text-decoration:none;">Mark read</a>
                                    <?php else: ?>
                                        <span style="color:#b0a8a0;">Read</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-notifications">
                            <span class="no-notif-icon">◌</span>
                            <p>No notifications yet.</p>
                            <p style="font-size:13px;color:#c0b4a8;">You'll receive notifications for borrowings, fines, and reservations.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($section === 'profile'): ?>
            <!-- ===== PROFILE ===== -->
            <div class="dashboard-content">
                <h1>My Profile</h1>
                <div class="profile-container">
                    <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'S', 0, 1)); ?>
                        </div>
                        <div>
                            <h2 style="margin:0;color:#1a1a1a;font-size:20px;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?></h2>
                            <p style="margin:0;color:#8a7a6e;"><?php echo htmlspecialchars($_SESSION['user_id_display'] ?? 'N/A'); ?></p>
                            <span class="status-badge-large <?php echo strtolower(str_replace(' ', '-', $accountStatus)); ?>" style="margin-top:4px;display:inline-block;">
                                <?php echo $accountStatus; ?>
                            </span>
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
                        <div class="profile-field">
                            <span class="label">Role</span>
                            <div class="value"><?php echo ucfirst($_SESSION['role'] ?? 'student'); ?></div>
                        </div>
                        <div class="profile-field">
                            <span class="label">Account Status</span>
                            <div class="value">
                                <span class="status-badge status-<?php echo $accountStatus === 'Restricted' ? 'overdue' : ($accountStatus === 'Active' ? 'borrowed' : 'paid'); ?>">
                                    <?php echo $accountStatus; ?>
                                </span>
                                <?php if ($accountStatus === 'Restricted'): ?>
                                    <span style="color:#8a3a2a;font-size:13px;display:block;margin-top:4px;">
                                        ⚠️ Please settle your fines (₱<?php echo number_format($totalPendingFines, 2); ?>) to restore access.
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($studentData)): ?>
                            <div class="profile-field">
                                <span class="label">Student ID</span>
                                <div class="value"><?php echo htmlspecialchars($studentData['student_id'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="profile-field">
                                <span class="label">Course</span>
                                <div class="value"><?php echo htmlspecialchars($studentData['course'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="profile-field">
                                <span class="label">Year Level</span>
                                <div class="value"><?php echo htmlspecialchars($studentData['year_level'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="profile-field">
                                <span class="label">Section</span>
                                <div class="value"><?php echo htmlspecialchars($studentData['section'] ?? 'N/A'); ?></div>
                            </div>
                            <?php if (!empty($studentData['phone'])): ?>
                            <div class="profile-field">
                                <span class="label">Phone</span>
                                <div class="value"><?php echo htmlspecialchars($studentData['phone'] ?? 'N/A'); ?></div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- BORROW MODAL -->
    <!-- ============================================ -->
    <div class="modal-overlay" id="borrowModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📖 Borrow Book</h2>
                <button class="close-modal" onclick="closeModal('borrowModal')">&times;</button>
            </div>
            <div class="book-info-preview">
                <div class="preview-title" id="borrowBookTitle">Book Title</div>
                <div class="preview-author" id="borrowBookAuthor">by Author</div>
                <div class="preview-detail" id="borrowBookAvailability">Available: 0 copies</div>
            </div>
            <form id="borrowForm" method="GET" action="student_dashboard.php">
                <input type="hidden" name="section" value="search">
                <input type="hidden" name="action" value="borrow">
                <input type="hidden" name="book_id" id="borrowBookId">
                <div class="form-group">
                    <label for="borrowDueDate">Due Date</label>
                    <input type="date" id="borrowDueDate" name="due_date" 
                           value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>"
                           min="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"
                           max="<?php echo date('Y-m-d', strtotime('+21 days')); ?>">
                    <small style="color:#9a8a7e;font-size:12px;">Books are typically due in 14 days.</small>
                </div>
                <div class="form-group">
                    <label for="borrowNotes">Notes (Optional)</label>
                    <textarea id="borrowNotes" name="notes" placeholder="Any special notes for this borrowing..." rows="2"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('borrowModal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="borrowSubmitBtn">Confirm Borrow</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- RESERVE MODAL -->
    <!-- ============================================ -->
    <div class="modal-overlay" id="reserveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>◑ Reserve Book</h2>
                <button class="close-modal" onclick="closeModal('reserveModal')">&times;</button>
            </div>
            <div class="book-info-preview">
                <div class="preview-title" id="reserveBookTitle">Book Title</div>
                <div class="preview-author" id="reserveBookAuthor">by Author</div>
                <div class="preview-detail">📌 You will be notified when this book becomes available.</div>
            </div>
            <form id="reserveForm" method="GET" action="student_dashboard.php">
                <input type="hidden" name="section" value="search">
                <input type="hidden" name="action" value="reserve">
                <input type="hidden" name="book_id" id="reserveBookId">
                <div class="form-group">
                    <label for="reserveExpiry">Reservation Expiry</label>
                    <input type="date" id="reserveExpiry" name="expiry_date" 
                           value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>"
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                           max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                    <small style="color:#9a8a7e;font-size:12px;">Your reservation will expire if not fulfilled within this period.</small>
                </div>
                <div class="form-group">
                    <label for="reserveNotes">Notes (Optional)</label>
                    <textarea id="reserveNotes" name="notes" placeholder="Any special notes for this reservation..." rows="2"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('reserveModal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="reserveSubmitBtn">Confirm Reserve</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- RESTRICTED MODAL -->
    <!-- ============================================ -->
    <div class="modal-overlay" id="restrictedModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>⛔ Account Restricted</h2>
                <button class="close-modal" onclick="closeModal('restrictedModal')">&times;</button>
            </div>
            <div class="restricted-warning">
                <div class="warning-title">⚠️ Your account is currently restricted</div>
                <div class="warning-text">You cannot borrow or reserve books until the following issues are resolved:</div>
                <ul class="fine-list">
                    <?php if ($hasOverdue): ?>
                        <li>📚 You have overdue books that need to be returned.</li>
                    <?php endif; ?>
                    <?php if ($hasUnpaidFines): ?>
                        <li>💰 You have unpaid fines totaling ₱<?php echo number_format($totalPendingFines, 2); ?>.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div style="background:#faf8f6;border-radius:12px;padding:16px 20px;margin-bottom:20px;border:1px solid #e8e0d8;">
                <h4 style="margin:0 0 8px 0;color:#1a1a1a;font-size:14px;">How to restore access:</h4>
                <ol style="margin:0;padding-left:20px;color:#6a5a4e;font-size:14px;line-height:1.6;">
                    <?php if ($hasOverdue): ?>
                        <li>Return all overdue books to the library</li>
                    <?php endif; ?>
                    <?php if ($hasUnpaidFines): ?>
                        <li>Settle all pending fines (₱<?php echo number_format($totalPendingFines, 2); ?>)</li>
                    <?php endif; ?>
                    <li>Wait for librarian to update your account status</li>
                </ol>
            </div>
            <div class="form-actions">
                <button class="btn-primary" onclick="closeModal('restrictedModal')" style="flex:1;">I Understand</button>
            </div>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            sidebar.classList.toggle('mobile-open');
            overlay.style.display = sidebar.classList.contains('mobile-open') ? 'block' : 'none';
        }

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
            document.getElementById('borrowBookAvailability').textContent = 'Available: ' + available + ' copies';
            
            const dueDate = new Date();
            dueDate.setDate(dueDate.getDate() + 14);
            document.getElementById('borrowDueDate').value = dueDate.toISOString().split('T')[0];
            
            openModal('borrowModal');
        }

        function openReserveModal(bookId, title, author) {
            document.getElementById('reserveBookId').value = bookId;
            document.getElementById('reserveBookTitle').textContent = title;
            document.getElementById('reserveBookAuthor').textContent = 'by ' + author;
            
            const expiryDate = new Date();
            expiryDate.setDate(expiryDate.getDate() + 3);
            document.getElementById('reserveExpiry').value = expiryDate.toISOString().split('T')[0];
            
            openModal('reserveModal');
        }

        function showRestrictedModal(title, bookId) {
            openModal('restrictedModal');
        }

        function openRequestForm(bookId, title, author, type) {
            window.location.href = 'student_dashboard.php?section=request_form&book_id=' + bookId + '&type=' + type;
        }

        function performSearch() {
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return;
            
            const query = searchInput.value.trim();
            if (query.length > 0) {
                window.location.href = 'student_dashboard.php?section=search&q=' + encodeURIComponent(query) + '&type=semantic';
            } else {
                window.location.href = 'student_dashboard.php?section=search';
            }
        }

        function quickSearch(query) {
            window.location.href = 'student_dashboard.php?section=search&q=' + encodeURIComponent(query) + '&type=semantic';
        }

        function clearSearch() {
            const searchInput = document.getElementById('searchInput');
            const clearBtn = document.getElementById('clearSearchBtn');
            if (searchInput) {
                searchInput.value = '';
            }
            if (clearBtn) {
                clearBtn.classList.remove('visible');
            }
            window.location.href = 'student_dashboard.php?section=search';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const clearBtn = document.getElementById('clearSearchBtn');
            
            if (searchInput && clearBtn) {
                if (searchInput.value.length > 0) {
                    clearBtn.classList.add('visible');
                }
                
                searchInput.addEventListener('input', function() {
                    if (this.value.length > 0) {
                        clearBtn.classList.add('visible');
                    } else {
                        clearBtn.classList.remove('visible');
                    }
                });
            }

            document.querySelectorAll('.book-cover-wrapper img').forEach(function(img) {
                img.addEventListener('error', function() {
                    this.style.display = 'none';
                    const placeholder = this.parentElement.querySelector('.cover-placeholder');
                    if (placeholder) {
                        placeholder.style.display = 'flex';
                    }
                });
            });
        });

        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                hour12: true, 
                timeZone: 'Asia/Manila' 
            });
            const dateString = now.toLocaleDateString('en-US', { 
                month: 'long', 
                day: 'numeric', 
                year: 'numeric', 
                timeZone: 'Asia/Manila' 
            });
            
            const timeEl = document.getElementById('currentTime');
            const dateEl = document.getElementById('currentDateDisplay');
            if (timeEl) timeEl.textContent = timeString;
            if (dateEl) dateEl.textContent = dateString;
        }
        setInterval(updateClock, 1000);
        updateClock();

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const searchInput = document.getElementById('searchInput');
                if (searchInput && document.activeElement === searchInput) {
                    performSearch();
                }
            }
        });

        let lastNotificationCheck = 0;
        const NOTIFICATION_CHECK_INTERVAL = 30000;

        function checkNotifications() {
            const now = Date.now();
            if (now - lastNotificationCheck < NOTIFICATION_CHECK_INTERVAL) return;
            lastNotificationCheck = now;

            const section = '<?php echo $section; ?>';
            if (['dashboard', 'notifications', 'borrowings', 'reservations', 'fines', 'requests'].includes(section)) {
                fetch('api/check_notifications.php?user_id=<?php echo $userId; ?>')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.count > 0) {
                            const badges = document.querySelectorAll('.notification-badge');
                            badges.forEach(badge => {
                                const count = Math.min(data.count, 99);
                                badge.textContent = count;
                                if (count > 0) {
                                    badge.classList.remove('empty');
                                } else {
                                    badge.classList.add('empty');
                                }
                            });
                            
                            const actionBadges = document.querySelectorAll('.action-badge');
                            actionBadges.forEach(badge => {
                                const count = Math.min(data.count, 99);
                                badge.textContent = count;
                                if (count === 0) {
                                    badge.style.display = 'none';
                                } else {
                                    badge.style.display = 'flex';
                                }
                            });
                            
                            if (section === 'notifications' && data.count > 0) {
                                const currentCount = parseInt('<?php echo $notificationCount; ?>');
                                if (data.count > currentCount) {
                                    window.location.reload();
                                }
                            }
                        }
                    })
                    .catch(err => console.error('Error checking notifications:', err));
            }
        }

        setInterval(checkNotifications, NOTIFICATION_CHECK_INTERVAL);
        setTimeout(checkNotifications, 5000);

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                checkNotifications();
            }
        });
        const searchSessionId = '<?php echo session_id(); ?>';
const userId = '<?php echo $userId; ?>';
let currentQuery = '';
let searchStartTime = 0;
let resultsClicked = 0;
let searchAbandoned = false;
let predictionTimeout = null;
let frustrationPopupVisible = false;

// ============================================
// 1. ZERO-QUERY PREDICTOR - Search as you type
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        // Add predictive search container
        const wrapper = searchInput.closest('.search-input-wrapper');
        if (wrapper && !document.getElementById('predictiveResults')) {
            const container = document.createElement('div');
            container.className = 'predictive-results';
            container.id = 'predictiveResults';
            wrapper.appendChild(container);
        }
        
        // Listen for input events
        searchInput.addEventListener('input', function(e) {
            const query = this.value.trim();
            currentQuery = query;
            
            if (query.length >= 2) {
                clearTimeout(predictionTimeout);
                predictionTimeout = setTimeout(() => {
                    getPredictions(query);
                }, 300);
            } else {
                hidePredictions();
            }
        });
        
        // Track search start time
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query) {
                    searchStartTime = Date.now();
                    resultsClicked = 0;
                    searchAbandoned = false;
                    hidePredictions();
                }
            }
        });
    }
});

function getPredictions(query) {
    if (!query || query.length < 2) {
        hidePredictions();
        return;
    }
    
    const container = document.getElementById('predictiveResults');
    if (!container) return;
    
    container.innerHTML = `
        <div class="loading-predictions">
            <div class="spinner"></div>
            <span style="display:block;margin-top:8px;">Predicting...</span>
        </div>
    `;
    container.classList.add('visible');
    
    fetch('api/predict_search.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_id: userId,
            partial_query: query
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.predictions && data.predictions.length > 0) {
            renderPredictions(data.predictions);
        } else {
            container.innerHTML = `
                <div style="padding:16px;text-align:center;color:#9a8a7e;">
                    No predictions found. Continue typing...
                </div>
            `;
        }
    })
    .catch(err => {
        console.error('Prediction error:', err);
        hidePredictions();
    });
}

function renderPredictions(predictions) {
    const container = document.getElementById('predictiveResults');
    if (!container) return;
    
    let html = '';
    predictions.forEach(book => {
        const title = book.title || 'Unknown';
        const author = book.author || 'Unknown';
        const score = book.prediction_score || book.relevance || 0;
        const category = book.category || 'General';
        
        html += `
            <div class="predictive-item" onclick="selectPrediction('${book.id}', '${title.replace(/'/g, "\\'")}')">
                <span class="pred-score">${Math.round(score)}% match</span>
                <div class="pred-title">${escapeHtml(title)}</div>
                <div class="pred-author">by ${escapeHtml(author)}</div>
                <span class="pred-badge">🎯 ${escapeHtml(category)}</span>
            </div>
        `;
    });
    
    container.innerHTML = html;
    container.classList.add('visible');
}

function selectPrediction(bookId, title) {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.value = title;
        hidePredictions();
        performSearch();
    }
}

function hidePredictions() {
    const container = document.getElementById('predictiveResults');
    if (container) {
        container.classList.remove('visible');
    }
}

// Click outside to hide predictions
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
// 2. TRACK SEARCH ACTIVITY
// ============================================

function trackSearchActivity(query, resultsCount, clickedBooks) {
    const timeSpent = Math.round((Date.now() - searchStartTime) / 1000);
    
    fetch('api/track_search.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_id: userId,
            session_id: searchSessionId,
            query: query,
            results_count: resultsCount,
            results_clicked: resultsClicked,
            time_spent: timeSpent,
            abandoned: searchAbandoned,
            clicked_books: clickedBooks || []
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.analysis && data.analysis.frustration_detected) {
            showFrustrationPopup(data.analysis);
        }
    })
    .catch(err => console.error('Track error:', err));
}

// Track when results are clicked
document.addEventListener('click', function(e) {
    const bookCard = e.target.closest('.book-card');
    if (bookCard) {
        resultsClicked++;
        const bookId = bookCard.dataset.bookId || '';
        if (bookId) {
            fetch('api/track_click.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: userId,
                    book_id: bookId,
                    session_id: searchSessionId,
                    query: currentQuery
                })
            }).catch(err => console.error('Click track error:', err));
        }
    }
});

// Track when search is abandoned
window.addEventListener('beforeunload', function() {
    if (currentQuery && searchStartTime > 0) {
        searchAbandoned = true;
        navigator.sendBeacon('api/track_search.php', JSON.stringify({
            user_id: userId,
            session_id: searchSessionId,
            query: currentQuery,
            results_clicked: resultsClicked,
            time_spent: Math.round((Date.now() - searchStartTime) / 1000),
            abandoned: true
        }));
    }
});

// ============================================
// 3. FRUSTRATION DETECTION POPUP
// ============================================

function showFrustrationPopup(analysis) {
    if (frustrationPopupVisible) return;
    
    let popup = document.getElementById('aiAssistantPopup');
    if (!popup) {
        popup = document.createElement('div');
        popup.id = 'aiAssistantPopup';
        popup.className = 'ai-assistant-popup';
        document.body.appendChild(popup);
    }
    
    const reasons = analysis.reasons || ['Difficulty finding relevant resources'];
    const suggestions = analysis.suggestions || [
        '📚 Would you like to schedule a consultation with the librarian?',
        '🔍 Try using simpler keywords in your search.'
    ];
    
    popup.innerHTML = `
        <div class="popup-header">
            <h3>🤖 Research Assistant</h3>
            <button class="close-popup" onclick="closeFrustrationPopup()">×</button>
        </div>
        <div class="popup-body">
            <p><strong>It looks like you're having some difficulty with your search.</strong></p>
            <ul>
                ${reasons.map(r => `<li>${r}</li>`).join('')}
            </ul>
            <p>${suggestions[0]}</p>
            <div class="popup-actions">
                <a href="#" class="btn-help" onclick="scheduleConsultation()">📅 Schedule Consultation</a>
                <a href="#" class="btn-help" onclick="showBeginnerGuide()">📚 View Beginner's Guide</a>
                <button class="btn-dismiss" onclick="closeFrustrationPopup()">Dismiss</button>
            </div>
        </div>
    `;
    
    popup.classList.add('visible');
    frustrationPopupVisible = true;
}

function closeFrustrationPopup() {
    const popup = document.getElementById('aiAssistantPopup');
    if (popup) {
        popup.classList.remove('visible');
        frustrationPopupVisible = false;
    }
}

function scheduleConsultation() {
    closeFrustrationPopup();
    alert('📅 A librarian consultation request has been sent. You will be contacted shortly.');
}

function showBeginnerGuide() {
    closeFrustrationPopup();
    window.location.href = 'student_dashboard.php?section=search&q=beginner%20guide';
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// MODIFY EXISTING FUNCTIONS
// ============================================

// Override performSearch to track activity
const originalPerformSearch = window.performSearch || function() {};
window.performSearch = function() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;
    
    const query = searchInput.value.trim();
    if (!query) {
        window.location.href = 'student_dashboard.php?section=search';
        return;
    }
    
    searchStartTime = Date.now();
    currentQuery = query;
    resultsClicked = 0;
    searchAbandoned = false;
    hidePredictions();
    trackSearchActivity(query, 0, []);
    
    window.location.href = 'student_dashboard.php?section=search&q=' + encodeURIComponent(query) + '&type=semantic';
};

// Override quickSearch to track
const originalQuickSearch = window.quickSearch || function() {};
window.quickSearch = function(query) {
    searchStartTime = Date.now();
    currentQuery = query;
    resultsClicked = 0;
    searchAbandoned = false;
    trackSearchActivity(query, 0, []);
    window.location.href = 'student_dashboard.php?section=search&q=' + encodeURIComponent(query) + '&type=semantic';
};

console.log('🤖 AI Features Enabled:');
console.log('  ✅ Zero-Query Prediction');
console.log('  ✅ Session Tracking');
console.log('  ✅ Frustration Detection');
    </script>
</body>
</html>