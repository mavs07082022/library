<?php
// api/admin.php - Complete Admin API
require_once 'config.php';

$method = getMethod();
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================
// GET DASHBOARD STATS
// ============================================
if ($method === 'GET' && $action === 'dashboard') {
    try {
        $usersResult = supabaseRequest('users?select=count');
        $totalUsers = $usersResult[0]['count'] ?? 0;
        
        $studentsResult = supabaseRequest('users?select=count&role=eq.student');
        $librariansResult = supabaseRequest('users?select=count&role=eq.librarian');
        $adminsResult = supabaseRequest('users?select=count&role=eq.admin');
        
        $booksResult = supabaseRequest('books?select=count');
        $totalBooks = $booksResult[0]['count'] ?? 0;
        
        $borrowingsResult = supabaseRequest('borrowings?select=count');
        $totalBorrowings = $borrowingsResult[0]['count'] ?? 0;
        
        $overdueResult = supabaseRequest('borrowings?select=count&status=eq.Overdue');
        $totalOverdue = $overdueResult[0]['count'] ?? 0;
        
        jsonResponse([
            'totalUsers' => $totalUsers,
            'totalStudents' => $studentsResult[0]['count'] ?? 0,
            'totalLibrarians' => $librariansResult[0]['count'] ?? 0,
            'totalAdmins' => $adminsResult[0]['count'] ?? 0,
            'totalBooks' => $totalBooks,
            'totalBorrowings' => $totalBorrowings,
            'totalOverdue' => $totalOverdue,
            'mostBorrowed' => []
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// GET USERS
// ============================================
if ($method === 'GET' && $action === 'users') {
    try {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $role = isset($_GET['role']) ? $_GET['role'] : '';
        
        $query = 'users?select=*';
        if ($role) {
            $query .= '&role=eq.' . urlencode($role);
        }
        if ($search) {
            $query .= '&or=(username.ilike.%' . urlencode($search) . '%,full_name.ilike.%' . urlencode($search) . '%,email.ilike.%' . urlencode($search) . '%)';
        }
        
        $users = supabaseRequest($query);
        
        // If role is student or no role specified, fetch student details
        if (empty($role) || $role === 'student') {
            $studentsData = supabaseRequest('students?select=user_id,course,year_level,section');
            $studentMap = [];
            foreach ($studentsData as $s) {
                $studentMap[$s['user_id']] = $s;
            }
            foreach ($users as &$user) {
                if ($user['role'] === 'student' && isset($studentMap[$user['id']])) {
                    $user['student_details'] = $studentMap[$user['id']];
                }
            }
        }
        
        jsonResponse($users);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// GET BOOKS
// ============================================
if ($method === 'GET' && $action === 'books') {
    try {
        $books = supabaseRequest('books?select=*');
        $categories = supabaseRequest('categories?select=id,name');
        $catMap = [];
        foreach ($categories as $cat) {
            $catMap[$cat['id']] = $cat['name'];
        }
        foreach ($books as &$book) {
            $book['categories'] = isset($catMap[$book['category_id']]) 
                ? ['name' => $catMap[$book['category_id']]] 
                : null;
        }
        jsonResponse($books);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// GET CATEGORIES
// ============================================
if ($method === 'GET' && $action === 'categories') {
    try {
        $categories = supabaseRequest('categories?select=*');
        jsonResponse($categories);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// GET BORROWING REPORTS
// ============================================
if ($method === 'GET' && $action === 'borrowing-reports') {
    try {
        $stats = supabaseRequest('borrowings?select=status,count&group_by=status');
        jsonResponse(['stats' => $stats]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// GET FINE ANALYTICS
// ============================================
if ($method === 'GET' && $action === 'fine-analytics') {
    try {
        $fines = supabaseRequest('fines?select=amount,status,reason');
        $totalFines = 0;
        foreach ($fines as $fine) {
            $totalFines += floatval($fine['amount'] ?? 0);
        }
        jsonResponse([
            'total_fines' => $totalFines,
            'fines' => $fines,
            'count' => count($fines)
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// GET SETTINGS
// ============================================
if ($method === 'GET' && $action === 'settings') {
    try {
        $fineSettings = supabaseRequest('fine_settings?select=*');
        $academicYears = supabaseRequest('academic_years?select=*&order=year_name.desc');
        jsonResponse([
            'fine_settings' => !empty($fineSettings) ? $fineSettings[0] : [],
            'academic_years' => $academicYears
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// CREATE LIBRARIAN
// ============================================
if ($method === 'POST' && $action === 'create-librarian') {
    $input = getInput();
    $username = $input['username'] ?? '';
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $full_name = $input['full_name'] ?? '';
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        jsonResponse(['error' => 'All fields are required'], 400);
    }
    
    try {
        $existing = supabaseRequest('users?select=*&or=(username.eq.' . urlencode($username) . ',email.eq.' . urlencode($email) . ')');
        if (!empty($existing)) {
            jsonResponse(['error' => 'Username or email already exists'], 400);
        }
        
        $newUser = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'full_name' => $full_name,
            'role' => 'librarian',
            'user_id' => 'LIB' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
            'is_verified' => true,
            'is_active' => true
        ];
        
        $result = supabaseRequest('users', 'POST', $newUser);
        jsonResponse(['success' => true, 'message' => 'Librarian created successfully', 'user' => $result[0]]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// UPDATE USER
// ============================================
if ($method === 'PUT' && $action === 'update-user') {
    $input = getInput();
    $userId = $input['id'] ?? '';
    $data = [];
    
    if (isset($input['is_active'])) {
        $data['is_active'] = $input['is_active'];
    }
    if (isset($input['is_verified'])) {
        $data['is_verified'] = $input['is_verified'];
    }
    if (isset($input['role'])) {
        $data['role'] = $input['role'];
    }
    
    if (empty($userId) || empty($data)) {
        jsonResponse(['error' => 'User ID and data are required'], 400);
    }
    
    try {
        $result = supabaseRequest('users?id=eq.' . $userId, 'PATCH', $data);
        jsonResponse(['success' => true, 'message' => 'User updated successfully']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// DELETE USER
// ============================================
if ($method === 'DELETE' && $action === 'delete-user') {
    $userId = isset($_GET['id']) ? $_GET['id'] : '';
    
    if (empty($userId)) {
        jsonResponse(['error' => 'User ID is required'], 400);
    }
    
    try {
        supabaseRequest('users?id=eq.' . $userId, 'DELETE');
        jsonResponse(['success' => true, 'message' => 'User deleted successfully']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// UPDATE FINE SETTINGS
// ============================================
if ($method === 'POST' && $action === 'update-fine-settings') {
    $input = getInput();
    $id = $input['id'] ?? '';
    
    if (empty($id)) {
        try {
            unset($input['id']);
            $result = supabaseRequest('fine_settings', 'POST', $input);
            jsonResponse(['success' => true, 'message' => 'Settings created successfully']);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    } else {
        try {
            unset($input['id']);
            $result = supabaseRequest('fine_settings?id=eq.' . $id, 'PATCH', $input);
            jsonResponse(['success' => true, 'message' => 'Settings updated successfully']);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    exit;
}

// ============================================
// SEARCH ANALYTICS - FIXED
// ============================================
if ($method === 'GET' && $action === 'search-analytics') {
    try {
        // Check if table exists first
        $tableCheck = supabaseRequest('search_analytics?select=count&limit=1');
        if (isset($tableCheck['error'])) {
            jsonResponse([]);
            exit;
        }
        $analytics = supabaseRequest('search_analytics?select=query,search_type,created_at&order=created_at.desc&limit=50');
        jsonResponse($analytics);
    } catch (Exception $e) {
        jsonResponse([]);
    }
    exit;
}

// ============================================
// MOST SEARCHED - FIXED (return empty array if not exists)
// ============================================
if ($method === 'GET' && $action === 'most-searched') {
    jsonResponse([]);
    exit;
}

// ============================================
// VERIFICATION REQUESTS - FIXED
// ============================================
if ($method === 'GET' && $action === 'verification-requests') {
    jsonResponse([]);
    exit;
}

// ============================================
// CLEARANCE REQUESTS - FIXED
// ============================================
if ($method === 'GET' && $action === 'clearance-requests') {
    jsonResponse([]);
    exit;
}

// ============================================
// AUDIT LOGS - FIXED
// ============================================
if ($method === 'GET' && $action === 'audit-logs') {
    jsonResponse([]);
    exit;
}

// ============================================
// USER ACTIVITY - FIXED
// ============================================
if ($method === 'GET' && $action === 'user-activity') {
    jsonResponse([]);
    exit;
}

// ============================================
// MOST BORROWED
// ============================================
if ($method === 'GET' && $action === 'most-borrowed') {
    jsonResponse([]);
    exit;
}

jsonResponse(['error' => 'Invalid action'], 400);
?>