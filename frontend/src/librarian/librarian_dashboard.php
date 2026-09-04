<?php
// librarian_dashboard.php - Librarian Dashboard with Modern Design
session_start();

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Check if user is logged in and is a librarian or admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'librarian' && $_SESSION['role'] !== 'admin')) {
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

// Get section
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : '';

$studentId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null;
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// ============================================
// HANDLE BOOK ACTIONS
// ============================================

// Add Book
if ($section === 'books' && $action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $isbn = $_POST['isbn'] ?? '';
    $publisher = $_POST['publisher'] ?? '';
    $year_published = $_POST['year_published'] ?? null;
    $category_id = $_POST['category_id'] ?? null;
    $quantity = intval($_POST['quantity'] ?? 1);
    $available = intval($_POST['available'] ?? 1);
    $location = $_POST['location'] ?? '';
    $description = $_POST['description'] ?? '';
    $cover_image = $_POST['cover_image'] ?? '';
    
    if ($title && $author) {
        try {
            $data = [
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'publisher' => $publisher,
                'year_published' => $year_published ? intval($year_published) : null,
                'category_id' => $category_id ?: null,
                'quantity' => $quantity,
                'available' => $available,
                'location' => $location,
                'description' => $description
            ];
            
            if (!empty($cover_image)) {
                $data['cover_image'] = $cover_image;
            }
            
            supabaseRequest('books', 'POST', $data);
            header('Location: librarian_dashboard.php?section=books&msg=Book added successfully');
            exit;
        } catch (Exception $e) {
            header('Location: librarian_dashboard.php?section=books&msg=Error adding book');
            exit;
        }
    }
    header('Location: librarian_dashboard.php?section=books&msg=Title and author are required');
    exit;
}

// Edit Book
if ($section === 'books' && $action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId = $_POST['book_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $isbn = $_POST['isbn'] ?? '';
    $publisher = $_POST['publisher'] ?? '';
    $year_published = $_POST['year_published'] ?? null;
    $category_id = $_POST['category_id'] ?? null;
    $quantity = intval($_POST['quantity'] ?? 1);
    $available = intval($_POST['available'] ?? 1);
    $location = $_POST['location'] ?? '';
    $description = $_POST['description'] ?? '';
    $cover_image = $_POST['cover_image'] ?? '';
    
    if ($bookId && $title && $author) {
        try {
            $data = [
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'publisher' => $publisher,
                'year_published' => $year_published ? intval($year_published) : null,
                'category_id' => $category_id ?: null,
                'quantity' => $quantity,
                'available' => $available,
                'location' => $location,
                'description' => $description
            ];
            
            if (!empty($cover_image)) {
                $data['cover_image'] = $cover_image;
            }
            
            supabaseRequest('books?id=eq.' . $bookId, 'PATCH', $data);
            header('Location: librarian_dashboard.php?section=books&msg=Book updated successfully');
            exit;
        } catch (Exception $e) {
            header('Location: librarian_dashboard.php?section=books&msg=Error updating book');
            exit;
        }
    }
    header('Location: librarian_dashboard.php?section=books&msg=Title and author are required');
    exit;
}

// Delete Book
if ($section === 'books' && isset($_GET['delete'])) {
    $bookId = $_GET['delete'];
    try {
        supabaseRequest('books?id=eq.' . $bookId, 'DELETE');
        header('Location: librarian_dashboard.php?section=books&msg=Book deleted');
        exit;
    } catch (Exception $e) {
        header('Location: librarian_dashboard.php?section=books&msg=Error deleting book');
        exit;
    }
}

// ============================================
// HANDLE BORROWING ACTIONS
// ============================================

// Return Book
if ($section === 'borrowings' && $action === 'return' && isset($_GET['id'])) {
    $borrowingId = $_GET['id'];
    try {
        $borrowing = supabaseRequest('borrowings?select=book_id,status&id=eq.' . $borrowingId);
        if (empty($borrowing)) {
            header('Location: librarian_dashboard.php?section=borrowings&msg=Borrowing not found');
            exit;
        }
        
        if (($borrowing[0]['status'] ?? '') === 'Returned') {
            header('Location: librarian_dashboard.php?section=borrowings&msg=Book already returned');
            exit;
        }
        
        supabaseRequest('borrowings?id=eq.' . $borrowingId, 'PATCH', [
            'status' => 'Returned',
            'return_date' => date('Y-m-d H:i:s')
        ]);
        
        $book = supabaseRequest('books?select=available&id=eq.' . $borrowing[0]['book_id']);
        if (!empty($book)) {
            supabaseRequest('books?id=eq.' . $borrowing[0]['book_id'], 'PATCH', [
                'available' => ($book[0]['available'] + 1)
            ]);
        }
        
        header('Location: librarian_dashboard.php?section=borrowings&msg=Book returned successfully');
        exit;
    } catch (Exception $e) {
        header('Location: librarian_dashboard.php?section=borrowings&msg=Error returning book');
        exit;
    }
}

// ============================================
// HANDLE FINE ACTIONS
// ============================================

// Pay Fine
if ($section === 'fines' && $action === 'pay' && isset($_GET['id'])) {
    $fineId = $_GET['id'];
    try {
        supabaseRequest('fines?id=eq.' . $fineId, 'PATCH', [
            'status' => 'Paid',
            'paid_date' => date('Y-m-d H:i:s')
        ]);
        header('Location: librarian_dashboard.php?section=fines&msg=Fine paid successfully');
        exit;
    } catch (Exception $e) {
        header('Location: librarian_dashboard.php?section=fines&msg=Error paying fine');
        exit;
    }
}

// Add Fine
if ($section === 'fines' && $action === 'add_fine' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $reason = $_POST['reason'] ?? 'Late Return';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($student_id) || $amount <= 0) {
        header('Location: librarian_dashboard.php?section=fines&msg=Student and amount are required');
        exit;
    }
    
    try {
        $studentRecord = supabaseRequest('students?select=user_id,student_id&id=eq.' . $student_id);
        if (empty($studentRecord)) {
            header('Location: librarian_dashboard.php?section=fines&msg=Student not found');
            exit;
        }
        $user_id = $studentRecord[0]['user_id'];
        $student_display_id = $studentRecord[0]['student_id'] ?? 'N/A';
        
        $fineData = [
            'user_id' => $user_id,
            'amount' => $amount,
            'reason' => $reason,
            'status' => 'Pending'
        ];
        
        if (!empty($notes)) {
            $fineData['notes'] = $notes;
        }
        
        try {
            supabaseRequest('fines', 'POST', $fineData);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'notes') !== false) {
                unset($fineData['notes']);
                supabaseRequest('fines', 'POST', $fineData);
            } else {
                throw $e;
            }
        }
        
        header('Location: librarian_dashboard.php?section=fines&msg=Fine added successfully');
        exit;
    } catch (Exception $e) {
        header('Location: librarian_dashboard.php?section=fines&msg=Error adding fine: ' . $e->getMessage());
        exit;
    }
}

// ============================================
// FETCH DATA
// ============================================
$books = [];
$categories = [];
$borrowings = [];
$fines = [];
$users = [];
$students = [];
$message = isset($_GET['msg']) ? $_GET['msg'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

try {
    $books = supabaseRequest('books?select=*');
    $categories = supabaseRequest('categories?select=*');
    $borrowings = supabaseRequest('borrowings?select=*,books(title,author)&order=borrow_date.desc');
    $users = supabaseRequest('users?select=*');
    $fines = supabaseRequest('fines?select=*');
    $students = supabaseRequest('students?select=*,users(full_name,user_id,email)');
    
    $catMap = [];
    foreach ($categories as $cat) {
        $catMap[$cat['id']] = $cat['name'];
    }
    
    foreach ($books as &$book) {
        $book['category_name'] = isset($book['category_id']) && isset($catMap[$book['category_id']]) 
            ? $catMap[$book['category_id']] 
            : 'Uncategorized';
    }
    
    foreach ($borrowings as &$b) {
        if (isset($b['user_id'])) {
            $studentUser = supabaseRequest('users?select=full_name,user_id&id=eq.' . $b['user_id']);
            if (!empty($studentUser)) {
                $b['student_name'] = $studentUser[0]['full_name'] ?? 'Unknown';
                $b['student_id'] = $studentUser[0]['user_id'] ?? 'N/A';
            } else {
                $b['student_name'] = 'Unknown';
                $b['student_id'] = 'N/A';
            }
        } else {
            $b['student_name'] = 'Unknown';
            $b['student_id'] = 'N/A';
        }
    }

} catch (Exception $e) {
    $message = 'Error loading data: ' . $e->getMessage();
}

// Filter books
$filteredBooks = $books;
if (!empty($searchTerm)) {
    $searchLower = strtolower($searchTerm);
    $filteredBooks = array_filter($books, function($book) use ($searchLower) {
        return strpos(strtolower($book['title'] ?? ''), $searchLower) !== false ||
               strpos(strtolower($book['author'] ?? ''), $searchLower) !== false ||
               strpos(strtolower($book['isbn'] ?? ''), $searchLower) !== false;
    });
}

// Statistics
$stats = [
    'totalBooks' => count($books),
    'totalBorrowings' => count($borrowings),
    'activeBorrowings' => count(array_filter($borrowings, function($b) {
        return ($b['status'] ?? '') !== 'Returned';
    })),
    'overdueBorrowings' => count(array_filter($borrowings, function($b) {
        return ($b['status'] ?? '') === 'Overdue';
    })),
    'totalFines' => array_sum(array_column($fines, 'amount')),
    'pendingFines' => count(array_filter($fines, function($f) {
        return ($f['status'] ?? '') !== 'Paid';
    })),
    'paidFines' => count(array_filter($fines, function($f) {
        return ($f['status'] ?? '') === 'Paid';
    }))
];

function getPlaceholderColor($id) {
    $colors = ['#2a2a2a', '#4a4a4a', '#6a6a6a', '#8a8a8a', '#aaaaaa', '#cacaca', '#eaeaea', '#fafafa'];
    $hash = crc32($id);
    if ($hash < 0) $hash = -$hash;
    return $colors[$hash % count($colors)];
}

function hasValidCoverImage($coverImage) {
    return !empty($coverImage) && strlen($coverImage) > 100 && strpos($coverImage, 'data:image') === 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarian Dashboard - Bestlink College</title>
    <style>
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

        .librarian-app { display: flex; min-height: 100vh; }
        .librarian-sidebar {
            width: 240px;
            background: #010107;
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

        .librarian-content { 
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
            color: #0e0d0c; 
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
            color: #0c0c0b; 
            display: block; 
            letter-spacing: 0.5px;
        }
        .header-time .date { 
            font-size: 12px; 
            color: #0f0f0e; 
            letter-spacing: 0.3px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
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
        .btn-add, .btn-save, .btn-export {
            padding: 10px 20px;
            background: #1a1a1a;
            color: #f0e8e0;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .btn-add:hover, .btn-save:hover, .btn-export:hover { 
            background: #2a2a2a;
            transform: translateY(-1px);
        }
        .btn-return {
            padding: 4px 14px;
            background: #e8ddd8;
            color: #3a2a2a;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .btn-return:hover { background: #d4c9c0; }
        .btn-pay {
            padding: 4px 14px;
            background: #e8ddd8;
            color: #3a2a2a;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .btn-pay:hover { background: #d4c9c0; }
        .btn-delete {
            padding: 4px 14px;
            background: #f0e0d8;
            color: #8a3a2a;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .btn-delete:hover { background: #e8d0c8; }
        .btn-edit {
            padding: 4px 14px;
            background: #f0edea;
            color: #4a3a2e;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 4px;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .btn-edit:hover { background: #e0d8d0; }

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
            padding: 12px 16px 12px 44px;
            border: 2px solid #e8e0d8;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: #ffffff;
            color: #1a1a1a;
        }
        .search-bar .search-input-wrapper input:focus {
            border-color: #d4a0a0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(212, 160, 160, 0.12);
        }
        .search-bar .search-input-wrapper .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9a8a7e;
            font-size: 16px;
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

        .cover-cell { width: 60px; min-width: 60px; padding: 4px !important; text-align: center; }
        .book-cover-small {
            width: 50px; height: 65px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: block;
            margin: 0 auto;
            background: #f0edea;
        }
        .cover-placeholder-small {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px; height: 65px;
            border-radius: 4px;
            color: #f0e8e0;
            font-size: 22px;
            font-weight: 600;
            margin: 0 auto;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
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
        .status-active { background: #e8ddd8; color: #3a2a2a; }
        .status-inactive { background: #f0e0d8; color: #8a3a2a; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .no-data { text-align: center; padding: 30px !important; color: #9a8a7e; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: #ffffff;
            padding: 32px 36px;
            border-radius: 16px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .modal h3 { 
            margin-top: 0; 
            color: #1a1a1a; 
            margin-bottom: 24px; 
            font-weight: 600;
            font-size: 20px;
        }
        .modal .form-group { margin-bottom: 16px; }
        .modal .form-group label { 
            display: block; 
            font-weight: 600; 
            font-size: 14px; 
            color: #1a1a1a; 
            margin-bottom: 4px; 
        }
        .modal .form-group input, 
        .modal .form-group select, 
        .modal .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e8e0d8;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            font-family: inherit;
            background: #faf8f6;
            transition: all 0.2s ease;
        }
        .modal .form-group input:focus, 
        .modal .form-group select:focus, 
        .modal .form-group textarea:focus { 
            border-color: #d4a0a0; 
            outline: none; 
            box-shadow: 0 0 0 3px rgba(212, 160, 160, 0.12);
        }
        .modal .form-group textarea { resize: vertical; min-height: 60px; }
        .modal .modal-actions { 
            display: flex; 
            gap: 10px; 
            margin-top: 24px; 
            justify-content: flex-end; 
        }
        .btn-cancel { 
            padding: 10px 24px; 
            background: #f0edea; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-cancel:hover { background: #e0d8d0; }
        .btn-confirm { 
            padding: 10px 24px; 
            background: #1a1a1a; 
            color: #f0e8e0; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-confirm:hover { background: #2a2a2a; }

        .cover-upload-container {
            border: 2px dashed #e0d8d0;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            min-height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: all 0.3s ease;
            background: #faf8f6;
        }
        .cover-upload-container:hover { border-color: #d4a0a0; }
        .cover-input {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .cover-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            color: #8a7a6e;
        }
        .cover-icon { font-size: 36px; opacity: 0.4; }
        .cover-hint { font-size: 12px; color: #b0a8a0; }
        .cover-preview-container { position: relative; display: inline-block; }
        .cover-preview {
            max-width: 150px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .btn-remove-cover {
            position: absolute;
            top: -8px; right: -8px;
            width: 26px; height: 26px;
            border-radius: 50%;
            background: #f0e0d8;
            color: #8a3a2a;
            border: 2px solid #ffffff;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        .btn-remove-cover:hover { background: #e8d0c8; }

        .count-badge { 
            color: #6a5a4e; 
            font-size: 14px; 
            white-space: nowrap; 
            font-weight: 400;
        }

        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) {
            .librarian-sidebar { width: 70px; }
            .sidebar-header h2, .sidebar-header p, .sidebar-header small, 
            .sidebar-header .subtitle, .sidebar-nav a .nav-label { display: none; }
            .sidebar-nav a { justify-content: center; padding: 14px; font-size: 20px; }
            .sidebar-nav a .nav-icon { font-size: 22px; }
            .librarian-content { margin-left: 70px; padding: 20px 24px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .quick-actions { grid-template-columns: 1fr 1fr; }
            .cover-cell { width: 45px; min-width: 45px; }
            .book-cover-small { width: 40px; height: 52px; }
            .cover-placeholder-small { width: 40px; height: 52px; font-size: 18px; }
            .dashboard-header { flex-direction: column; align-items: flex-start; }
            .header-time { width: 100%; text-align: left; }
        }
        @media (max-width: 480px) {
            .mobile-menu-toggle { display: flex !important; align-items: center; justify-content: center; }
            .librarian-sidebar {
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
            .librarian-sidebar.mobile-open { transform: translateX(0) !important; }
            .mobile-overlay { display: block !important; }
            .librarian-content { margin-left: 0 !important; padding: 70px 12px 12px !important; }
            .sidebar-header h2, .sidebar-header p, .sidebar-header small, 
            .sidebar-header .subtitle, .sidebar-nav a .nav-label { display: block !important; }
            .sidebar-nav a { justify-content: flex-start; padding: 12px 20px; font-size: 14px; }
            .sidebar-nav a .nav-icon { font-size: 18px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-number { font-size: 20px; }
            .cover-cell { width: 35px; min-width: 35px; }
            .book-cover-small { width: 28px; height: 36px; }
            .cover-placeholder-small { width: 28px; height: 36px; font-size: 12px; }
            .section-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .dashboard-header { padding: 20px; }
            .dashboard-header h1 { font-size: 18px; }
            .quick-actions { grid-template-columns: 1fr 1fr; }
            .modal { padding: 24px 20px; }
        }
    </style>
</head>
<body>
    <div class="librarian-app">
        <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
            <span class="hamburger-icon"><span></span><span></span><span></span></span>
        </button>

        <div class="librarian-sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="../img/agustinnb.png" alt="BCP Logo" class="sidebar-logo" onerror="this.style.display='none'">
                <h2>ST. AGNES ACADEMY</h2>
                <div class="subtitle">Caloocan Inc.</div>
                <p><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Librarian'); ?></p>
                <small>ID: <?php echo htmlspecialchars($_SESSION['user_id_display'] ?? 'N/A'); ?></small>
            </div>
            <nav class="sidebar-nav">
                <a href="librarian_dashboard.php?section=dashboard" class="<?php echo $section === 'dashboard' ? 'active' : ''; ?>">
                    <span class="nav-icon">◆</span>
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="librarian_dashboard.php?section=books" class="<?php echo $section === 'books' ? 'active' : ''; ?>">
                    <span class="nav-icon">▣</span>
                    <span class="nav-label">Books</span>
                </a>
                <a href="librarian_dashboard.php?section=borrowings" class="<?php echo $section === 'borrowings' ? 'active' : ''; ?>">
                    <span class="nav-icon">◈</span>
                    <span class="nav-label">Borrowings</span>
                </a>
                <a href="librarian_dashboard.php?section=fines" class="<?php echo $section === 'fines' ? 'active' : ''; ?>">
                    <span class="nav-icon">◉</span>
                    <span class="nav-label">Fines</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="../admin_logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

        <div class="librarian-content">
            <?php if ($message): ?>
                <div class="message info"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($section === 'dashboard'): ?>
            <!-- ===== DASHBOARD ===== -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <div class="header-left">
                        <h1>Librarian Dashboard</h1>
                        <p class="header-date">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Librarian'); ?>! — <?php echo date('F j, Y'); ?></p>
                    </div>
                    <div class="header-time">
                        <span class="time" id="currentTime"><?php echo date('g:i A'); ?></span>
                        <span class="date" id="currentDateDisplay"><?php echo date('F j, Y'); ?></span>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['totalBooks']; ?></div>
                        <div class="stat-label">Total Books</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['totalBorrowings']; ?></div>
                        <div class="stat-label">Total Borrowings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['activeBorrowings']; ?></div>
                        <div class="stat-label">Active Borrowings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['overdueBorrowings']; ?></div>
                        <div class="stat-label">Overdue</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">₱<?php echo number_format($stats['totalFines'], 2); ?></div>
                        <div class="stat-label">Total Fines</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['pendingFines']; ?></div>
                        <div class="stat-label">Pending Fines</div>
                    </div>
                </div>

                <div class="quick-actions">
                    <a href="librarian_dashboard.php?section=books" class="quick-action-card">
                        <span class="action-icon">▣</span>
                        <span class="action-label">Manage Books</span>
                    </a>
                    <a href="librarian_dashboard.php?section=borrowings" class="quick-action-card">
                        <span class="action-icon">◈</span>
                        <span class="action-label">Manage Borrowings</span>
                    </a>
                    <a href="librarian_dashboard.php?section=fines" class="quick-action-card">
                        <span class="action-icon">◉</span>
                        <span class="action-label">Manage Fines</span>
                    </a>
                    <button class="quick-action-card" onclick="openAddBookModal()">
                        <span class="action-icon">+</span>
                        <span class="action-label">Add New Book</span>
                    </button>
                </div>

                <div style="background:#ffffff;border-radius:16px;padding:20px 24px;border:1px solid #e8e0d8;">
                    <h3 style="margin:0 0 16px 0;color:#1a1a1a;font-weight:600;">Recent Borrowings</h3>
                    <?php if (!empty($borrowings)): ?>
                        <table class="data-table">
                            <thead>
                                <tr><th>Book</th><th>Student</th><th>Borrowed</th><th>Due Date</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($borrowings, 0, 10) as $b): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($b['books']['title'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($b['student_name'] ?? 'Unknown'); ?></td>
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

            <?php elseif ($section === 'books'): ?>
            <!-- ===== BOOKS MANAGEMENT ===== -->
            <div class="book-management">
                <div class="section-header">
                    <h1>Books Management (<?php echo count($books); ?> books)</h1>
                    <div>
                        <button onclick="openAddBookModal()" class="btn-add">+ Add Book</button>
                    </div>
                </div>

                <div class="search-bar">
                    <div class="search-input-wrapper">
                        <span class="search-icon">⌕</span>
                        <input type="text" id="bookSearch" placeholder="Search books by title, author, or ISBN..." 
                               value="<?php echo htmlspecialchars($searchTerm); ?>"
                               onkeyup="searchBooks(this.value)">
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cover</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Available</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($filteredBooks)): ?>
                                <?php foreach ($filteredBooks as $b): 
                                    $hasValidImage = hasValidCoverImage($b['cover_image'] ?? '');
                                    $categoryName = isset($b['category_id']) && isset($catMap[$b['category_id']]) ? $catMap[$b['category_id']] : 'Uncategorized';
                                ?>
                                    <tr>
                                        <td class="cover-cell">
                                            <?php if ($hasValidImage): ?>
                                                <img src="<?php echo htmlspecialchars($b['cover_image']); ?>" alt="<?php echo htmlspecialchars($b['title'] ?? ''); ?>" class="book-cover-small" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                                <div class="cover-placeholder-small" style="display:none;background-color:<?php echo getPlaceholderColor($b['id']); ?>;"><?php echo isset($b['title']) ? strtoupper(substr($b['title'], 0, 1)) : '▣'; ?></div>
                                            <?php else: ?>
                                                <div class="cover-placeholder-small" style="background-color:<?php echo getPlaceholderColor($b['id']); ?>;"><?php echo isset($b['title']) ? strtoupper(substr($b['title'], 0, 1)) : '▣'; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($b['title'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($b['author'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($categoryName); ?></td>
                                        <td><?php echo ($b['available'] ?? 0); ?> / <?php echo ($b['quantity'] ?? 0); ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="openEditBookModal('<?php echo $b['id']; ?>', '<?php echo addslashes($b['title'] ?? ''); ?>', '<?php echo addslashes($b['author'] ?? ''); ?>', '<?php echo addslashes($b['isbn'] ?? ''); ?>', '<?php echo addslashes($b['publisher'] ?? ''); ?>', '<?php echo $b['year_published'] ?? ''; ?>', '<?php echo $b['category_id'] ?? ''; ?>', '<?php echo $b['quantity'] ?? 1; ?>', '<?php echo $b['available'] ?? 1; ?>', '<?php echo addslashes($b['location'] ?? ''); ?>', '<?php echo addslashes($b['description'] ?? ''); ?>', '<?php echo addslashes($b['cover_image'] ?? ''); ?>')">Edit</button>
                                            <a href="librarian_dashboard.php?section=books&delete=<?php echo $b['id']; ?>" class="btn-delete" onclick="return confirm('Delete this book?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="no-data">No books found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Book Modal -->
            <div class="modal-overlay" id="addBookModal">
                <div class="modal">
                    <h3>Add New Book</h3>
                    <form method="POST" action="librarian_dashboard.php?section=books&action=add" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="form-group">
                            <label>Author *</label>
                            <input type="text" name="author" required>
                        </div>
                        <div class="form-group">
                            <label>ISBN</label>
                            <input type="text" name="isbn">
                        </div>
                        <div class="form-group">
                            <label>Publisher</label>
                            <input type="text" name="publisher">
                        </div>
                        <div class="form-group">
                            <label>Year Published</label>
                            <input type="number" name="year_published" min="1000" max="<?php echo date('Y'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" min="1" value="1" required>
                        </div>
                        <div class="form-group">
                            <label>Available</label>
                            <input type="number" name="available" min="0" value="1">
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" placeholder="Shelf A-1">
                        </div>
                        <div class="form-group">
                            <label>Cover Image</label>
                            <div class="cover-upload-container">
                                <input type="file" id="coverImageInput" accept="image/*" onchange="handleCoverImageUpload(event)" class="cover-input">
                                <div id="coverPlaceholder" class="cover-placeholder">
                                    <span class="cover-icon">▣</span>
                                    <span>No cover image selected</span>
                                    <span class="cover-hint">Click to upload (JPEG, PNG, GIF, WEBP)</span>
                                </div>
                                <div id="coverPreviewContainer" class="cover-preview-container" style="display:none;">
                                    <img id="coverPreview" class="cover-preview" alt="Cover Preview">
                                    <button type="button" onclick="removeCoverImage()" class="btn-remove-cover">✕</button>
                                </div>
                                <input type="hidden" name="cover_image" id="coverImageData" value="">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3"></textarea>
                        </div>
                        <div class="modal-actions">
                            <button type="button" onclick="closeModal('addBookModal')" class="btn-cancel">Cancel</button>
                            <button type="submit" class="btn-confirm">Add Book</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Book Modal -->
            <div class="modal-overlay" id="editBookModal">
                <div class="modal">
                    <h3>Edit Book</h3>
                    <form method="POST" action="librarian_dashboard.php?section=books&action=edit" enctype="multipart/form-data">
                        <input type="hidden" name="book_id" id="edit_book_id" value="">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" id="edit_title" required>
                        </div>
                        <div class="form-group">
                            <label>Author *</label>
                            <input type="text" name="author" id="edit_author" required>
                        </div>
                        <div class="form-group">
                            <label>ISBN</label>
                            <input type="text" name="isbn" id="edit_isbn">
                        </div>
                        <div class="form-group">
                            <label>Publisher</label>
                            <input type="text" name="publisher" id="edit_publisher">
                        </div>
                        <div class="form-group">
                            <label>Year Published</label>
                            <input type="number" name="year_published" id="edit_year" min="1000" max="<?php echo date('Y'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" id="edit_category">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" id="edit_quantity" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>Available</label>
                            <input type="number" name="available" id="edit_available" min="0">
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" id="edit_location" placeholder="Shelf A-1">
                        </div>
                        <div class="form-group">
                            <label>Cover Image</label>
                            <div class="cover-upload-container">
                                <input type="file" id="editCoverImageInput" accept="image/*" onchange="handleEditCoverImageUpload(event)" class="cover-input">
                                <div id="editCoverPlaceholder" class="cover-placeholder">
                                    <span class="cover-icon">▣</span>
                                    <span>No cover image selected</span>
                                    <span class="cover-hint">Click to upload (JPEG, PNG, GIF, WEBP)</span>
                                </div>
                                <div id="editCoverPreviewContainer" class="cover-preview-container" style="display:none;">
                                    <img id="editCoverPreview" class="cover-preview" alt="Cover Preview">
                                    <button type="button" onclick="removeEditCoverImage()" class="btn-remove-cover">✕</button>
                                </div>
                                <input type="hidden" name="cover_image" id="editCoverImageData" value="">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="modal-actions">
                            <button type="button" onclick="closeModal('editBookModal')" class="btn-cancel">Cancel</button>
                            <button type="submit" class="btn-confirm">Update Book</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php elseif ($section === 'borrowings'): ?>
            <!-- ===== BORROWINGS MANAGEMENT ===== -->
            <div class="borrowing-management">
                <div class="section-header">
                    <h1>Borrowings Management</h1>
                    <span class="count-badge">Total: <?php echo count($borrowings); ?></span>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Borrowed</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($borrowings)): ?>
                                <?php foreach ($borrowings as $b): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($b['books']['title'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($b['student_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($b['student_id'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($b['borrow_date'] ?? 'now')); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($b['due_date'] ?? 'now')); ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($b['status'] ?? 'borrowed'); ?>"><?php echo $b['status'] ?? 'Borrowed'; ?></span></td>
                                        <td>
                                            <?php if (($b['status'] ?? '') !== 'Returned'): ?>
                                                <a href="librarian_dashboard.php?section=borrowings&action=return&id=<?php echo $b['id']; ?>" class="btn-return" onclick="return confirm('Return this book?')">Return</a>
                                            <?php else: ?>
                                                <span style="color:#b0a8a0;font-size:12px;">Returned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="no-data">No borrowings found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($section === 'fines'): ?>
            <!-- ===== FINES MANAGEMENT ===== -->
            <div class="fine-management">
                <div class="section-header">
                    <h1>Fines Management</h1>
                    <div>
                        <span class="count-badge">Total: ₱<?php echo number_format($stats['totalFines'], 2); ?></span>
                        <button onclick="openModal('addFineModal')" class="btn-add" style="margin-left:10px;">+ Add Fine</button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Amount</th>
                                <th>Reason</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($fines)): ?>
                                <?php foreach ($fines as $f): 
                                    $studentName = 'Unknown';
                                    $studentDisplayId = 'N/A';
                                    if (isset($f['user_id'])) {
                                        $userRecord = supabaseRequest('users?select=full_name&id=eq.' . $f['user_id']);
                                        if (!empty($userRecord)) {
                                            $studentName = $userRecord[0]['full_name'] ?? 'Unknown';
                                        }
                                        $studentRecord = supabaseRequest('students?select=student_id&user_id=eq.' . $f['user_id']);
                                        if (!empty($studentRecord)) {
                                            $studentDisplayId = $studentRecord[0]['student_id'] ?? 'N/A';
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($studentName); ?></td>
                                        <td><?php echo htmlspecialchars($studentDisplayId); ?></td>
                                        <td>₱<?php echo number_format($f['amount'] ?? 0, 2); ?></td>
                                        <td><?php echo htmlspecialchars($f['reason'] ?? 'Late Return'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($f['created_at'] ?? 'now')); ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($f['status'] ?? 'pending'); ?>"><?php echo $f['status'] ?? 'Pending'; ?></span></td>
                                        <td>
                                            <?php if (($f['status'] ?? '') !== 'Paid'): ?>
                                                <a href="librarian_dashboard.php?section=fines&action=pay&id=<?php echo $f['id']; ?>" class="btn-pay" onclick="return confirm('Mark this fine as paid?')">Pay</a>
                                            <?php else: ?>
                                                <span style="color:#b0a8a0;font-size:12px;">Paid</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="no-data">No fines found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Fine Modal -->
    <div class="modal-overlay" id="addFineModal">
        <div class="modal">
            <h3>Add Fine to Student</h3>
            <form method="POST" action="librarian_dashboard.php?section=fines&action=add_fine">
                <div class="form-group">
                    <label>Student <span style="color:#8a3a2a;">*</span></label>
                    <select name="student_id" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $s): 
                            $studentName = $s['users']['full_name'] ?? 'Unknown';
                            $studentId = $s['student_id'] ?? 'N/A';
                        ?>
                            <option value="<?php echo $s['id']; ?>">
                                <?php echo htmlspecialchars($studentName . ' (' . $studentId . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (₱) <span style="color:#8a3a2a;">*</span></label>
                    <input type="number" name="amount" min="1" step="0.50" placeholder="Enter fine amount" required>
                </div>
                <div class="form-group">
                    <label>Reason <span style="color:#8a3a2a;">*</span></label>
                    <select name="reason" required>
                        <option value="Late Return">Late Return</option>
                        <option value="Lost Book">Lost Book</option>
                        <option value="Damaged Book">Damaged Book</option>
                        <option value="Overdue Fine">Overdue Fine</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea name="notes" rows="2" placeholder="Additional notes about this fine"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('addFineModal')" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-confirm">Add Fine</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ===== MOBILE MENU =====
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            sidebar.classList.toggle('mobile-open');
            overlay.style.display = sidebar.classList.contains('mobile-open') ? 'block' : 'none';
        }

        // ===== SEARCH =====
        let searchTimeout;

        function searchBooks(query) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                const url = new URL(window.location.href);
                if (query.length > 0) {
                    url.searchParams.set('search', query);
                } else {
                    url.searchParams.delete('search');
                }
                window.location.href = url.toString();
            }, 400);
        }

        // ===== MODALS =====
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function openAddBookModal() {
            openModal('addBookModal');
            removeCoverImage();
        }

        // ============================================
        // COVER IMAGE UPLOAD FOR ADD BOOK
        // ============================================
        let currentCoverImageData = '';

        function handleCoverImageUpload(event) {
            const file = event.target.files[0];
            if (file) {
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
                if (!validTypes.includes(file.type)) {
                    alert('Please upload a valid image file (JPEG, PNG, GIF, WEBP)');
                    event.target.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image size should be less than 5MB');
                    event.target.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onloadend = function() {
                    const imageData = reader.result;
                    currentCoverImageData = imageData;
                    document.getElementById('coverImageData').value = imageData;
                    document.getElementById('coverPlaceholder').style.display = 'none';
                    document.getElementById('coverPreviewContainer').style.display = 'inline-block';
                    document.getElementById('coverPreview').src = imageData;
                };
                reader.readAsDataURL(file);
            }
        }

        function removeCoverImage() {
            currentCoverImageData = '';
            document.getElementById('coverImageData').value = '';
            document.getElementById('coverPlaceholder').style.display = 'flex';
            document.getElementById('coverPreviewContainer').style.display = 'none';
            document.getElementById('coverPreview').src = '';
            document.getElementById('coverImageInput').value = '';
        }

        // ============================================
        // COVER IMAGE UPLOAD FOR EDIT BOOK
        // ============================================
        let editCoverImageData = '';

        function handleEditCoverImageUpload(event) {
            const file = event.target.files[0];
            if (file) {
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
                if (!validTypes.includes(file.type)) {
                    alert('Please upload a valid image file (JPEG, PNG, GIF, WEBP)');
                    event.target.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image size should be less than 5MB');
                    event.target.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onloadend = function() {
                    const imageData = reader.result;
                    editCoverImageData = imageData;
                    document.getElementById('editCoverImageData').value = imageData;
                    document.getElementById('editCoverPlaceholder').style.display = 'none';
                    document.getElementById('editCoverPreviewContainer').style.display = 'inline-block';
                    document.getElementById('editCoverPreview').src = imageData;
                };
                reader.readAsDataURL(file);
            }
        }

        function removeEditCoverImage() {
            editCoverImageData = '';
            document.getElementById('editCoverImageData').value = '';
            document.getElementById('editCoverPlaceholder').style.display = 'flex';
            document.getElementById('editCoverPreviewContainer').style.display = 'none';
            document.getElementById('editCoverPreview').src = '';
            document.getElementById('editCoverImageInput').value = '';
        }

        // ============================================
        // OPEN EDIT BOOK MODAL
        // ============================================
        function openEditBookModal(id, title, author, isbn, publisher, year, category, quantity, available, location, description, coverImage) {
            document.getElementById('edit_book_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_author').value = author;
            document.getElementById('edit_isbn').value = isbn;
            document.getElementById('edit_publisher').value = publisher;
            document.getElementById('edit_year').value = year;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_quantity').value = quantity;
            document.getElementById('edit_available').value = available;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_description').value = description;
            
            if (coverImage && coverImage.length > 100) {
                editCoverImageData = coverImage;
                document.getElementById('editCoverImageData').value = coverImage;
                document.getElementById('editCoverPlaceholder').style.display = 'none';
                document.getElementById('editCoverPreviewContainer').style.display = 'inline-block';
                document.getElementById('editCoverPreview').src = coverImage;
            } else {
                removeEditCoverImage();
            }
            
            openModal('editBookModal');
        }

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // ===== CLOCK =====
        function updateClock() {
            const now = new Date();
            const options = { timeZone: 'Asia/Manila', hour12: true };
            const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila' });
            const dateString = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'Asia/Manila' });
            
            const timeEl = document.getElementById('currentTime');
            const dateEl = document.getElementById('currentDateDisplay');
            if (timeEl) timeEl.textContent = timeString;
            if (dateEl) dateEl.textContent = dateString;
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>