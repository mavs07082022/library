<?php
// api/fines.php - Fine Management API
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

// ============================================
// GET FINES - FIXED
// ============================================
if ($method === 'GET') {
    try {
        // Fetch fines without join
        $query = 'fines?select=*';
        if ($userId) {
            $query .= '&student_id=eq.' . urlencode($userId);
        }
        $fines = supabaseRequest($query);
        
        // Fetch users for student names
        $usersData = supabaseRequest('users?select=id,full_name');
        $userMap = [];
        foreach ($usersData as $u) {
            $userMap[$u['id']] = $u['full_name'] ?? 'Unknown';
        }
        
        // Get student-to-user mapping
        $studentsData = supabaseRequest('students?select=id,user_id');
        $studentUserMap = [];
        foreach ($studentsData as $s) {
            $studentUserMap[$s['id']] = $s['user_id'];
        }
        
        // Map student names to fines
        foreach ($fines as &$f) {
            $studentUserId = $studentUserMap[$f['student_id']] ?? null;
            $f['student_name'] = $studentUserId ? ($userMap[$studentUserId] ?? 'Unknown') : 'Unknown';
        }
        
        jsonResponse($fines);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// ADD FINE
// ============================================
if ($method === 'POST') {
    $input = getInput();
    
    if (empty($input['student_id']) || empty($input['amount']) || $input['amount'] <= 0) {
        jsonResponse(['error' => 'Student ID and amount are required'], 400);
    }
    
    try {
        $data = [
            'student_id' => $input['student_id'],
            'amount' => floatval($input['amount']),
            'reason' => $input['reason'] ?? 'Late Return',
            'status' => 'Pending'
        ];
        
        $result = supabaseRequest('fines', 'POST', $data);
        jsonResponse(['success' => true, 'message' => 'Fine added successfully', 'fine' => $result[0] ?? null], 201);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// UPDATE FINE (PAY)
// ============================================
if ($method === 'PUT') {
    if (!$id) {
        jsonResponse(['error' => 'Fine ID required'], 400);
    }
    
    $input = getInput();
    
    try {
        $data = [];
        if (isset($input['status'])) {
            $data['status'] = $input['status'];
            if ($input['status'] === 'Paid') {
                $data['paid_date'] = date('Y-m-d H:i:s');
            }
        }
        if (isset($input['amount'])) {
            $data['amount'] = floatval($input['amount']);
        }
        
        if (empty($data)) {
            jsonResponse(['error' => 'No data to update'], 400);
        }
        
        supabaseRequest('fines?id=eq.' . $id, 'PATCH', $data);
        jsonResponse(['success' => true, 'message' => 'Fine updated successfully']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// DELETE FINE
// ============================================
if ($method === 'DELETE') {
    if (!$id) {
        jsonResponse(['error' => 'Fine ID required'], 400);
    }
    
    try {
        supabaseRequest('fines?id=eq.' . $id, 'DELETE');
        jsonResponse(['success' => true, 'message' => 'Fine deleted successfully']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

jsonResponse(['error' => 'Method not allowed'], 405);
?>