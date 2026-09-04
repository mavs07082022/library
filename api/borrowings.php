<?php
// api/borrowings.php - Updated with Supabase integration
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
$id = isset($_GET['id']) ? $_GET['id'] : null;
$userId = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================
// GET BORROWINGS - FIXED
// ============================================
if ($method === 'GET') {
    try {
        // Fetch borrowings with book info only
        $query = 'borrowings?select=*,books(title,author,id)&order=borrow_date.desc';
        if ($userId) {
            $query .= '&student_id=eq.' . urlencode($userId);
        }
        $borrowings = supabaseRequest($query);
        
        // Fetch students with user info separately
        $studentsData = supabaseRequest('students?select=id,user_id,users(full_name)');
        $studentMap = [];
        foreach ($studentsData as $s) {
            $studentMap[$s['id']] = [
                'full_name' => $s['users']['full_name'] ?? 'Unknown',
                'user_id' => $s['user_id'] ?? ''
            ];
        }
        
        // Map student info to borrowings
        foreach ($borrowings as &$b) {
            $b['student'] = [
                'full_name' => $studentMap[$b['student_id']]['full_name'] ?? 'Unknown',
                'user_id' => $studentMap[$b['student_id']]['user_id'] ?? ''
            ];
        }
        
        jsonResponse($borrowings);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// CREATE BORROWING
// ============================================
if ($method === 'POST') {
    $input = getInput();
    
    if (empty($input['book_id']) || empty($input['student_id'])) {
        jsonResponse(['error' => 'Book ID and Student ID are required'], 400);
    }
    
    try {
        // Check if book is available
        $books = supabaseRequest('books?select=available&id=eq.' . $input['book_id']);
        if (empty($books) || ($books[0]['available'] ?? 0) <= 0) {
            jsonResponse(['error' => 'Book is not available'], 400);
        }
        
        // Check if student already borrowed this book
        $existing = supabaseRequest('borrowings?select=id&student_id=eq.' . $input['student_id'] . '&book_id=eq.' . $input['book_id'] . '&status=neq.Returned');
        if (!empty($existing)) {
            jsonResponse(['error' => 'Student already borrowed this book'], 400);
        }
        
        // Create borrowing
        $borrowData = [
            'book_id' => $input['book_id'],
            'student_id' => $input['student_id'],
            'borrow_date' => $input['borrow_date'] ?? date('Y-m-d H:i:s'),
            'due_date' => $input['due_date'] ?? date('Y-m-d H:i:s', strtotime('+14 days')),
            'status' => 'Borrowed'
        ];
        
        $result = supabaseRequest('borrowings', 'POST', $borrowData);
        
        // Update book availability
        supabaseRequest('books?id=eq.' . $input['book_id'], 'PATCH', [
            'available' => ($books[0]['available'] - 1)
        ]);
        
        jsonResponse(['success' => true, 'message' => 'Book borrowed successfully', 'borrowing' => $result[0] ?? null], 201);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// RETURN BORROWING
// ============================================
if ($method === 'PUT' && $action === 'return' && $id) {
    try {
        $borrowing = supabaseRequest('borrowings?select=book_id,status&id=eq.' . $id);
        if (empty($borrowing)) {
            jsonResponse(['error' => 'Borrowing not found'], 404);
        }
        
        if (($borrowing[0]['status'] ?? '') === 'Returned') {
            jsonResponse(['error' => 'Book already returned'], 400);
        }
        
        // Update borrowing
        supabaseRequest('borrowings?id=eq.' . $id, 'PATCH', [
            'status' => 'Returned',
            'return_date' => date('Y-m-d H:i:s')
        ]);
        
        // Increase book availability
        $book = supabaseRequest('books?select=available&id=eq.' . $borrowing[0]['book_id']);
        if (!empty($book)) {
            supabaseRequest('books?id=eq.' . $borrowing[0]['book_id'], 'PATCH', [
                'available' => ($book[0]['available'] + 1)
            ]);
        }
        
        jsonResponse(['success' => true, 'message' => 'Book returned successfully']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// DELETE BORROWING
// ============================================
if ($method === 'DELETE') {
    if (!$id) {
        jsonResponse(['error' => 'Borrowing ID required'], 400);
    }
    
    try {
        supabaseRequest('borrowings?id=eq.' . $id, 'DELETE');
        jsonResponse(['success' => true, 'message' => 'Borrowing deleted successfully']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

jsonResponse(['error' => 'Method not allowed'], 405);
?>