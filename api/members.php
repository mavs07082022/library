<?php
// api/members.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// SIMPLE FILE-BASED DATABASE FOR MEMBERS
// ============================================
$dataFile = __DIR__ . '/members_data.json';

// Initialize data file if it doesn't exist
if (!file_exists($dataFile)) {
    $initialMembers = [
        ['id' => 1, 'library_id' => 'LIB001', 'first_name' => 'Emily', 'last_name' => 'Roberts', 'email' => 'emily@email.com', 'phone' => '555-0101', 'address' => '123 Main St', 'membership_type' => 'Student', 'join_date' => '2025-01-15', 'expiry_date' => '2026-01-15', 'is_verified' => true, 'clearance_status' => 'Clear'],
        ['id' => 2, 'library_id' => 'LIB002', 'first_name' => 'James', 'last_name' => 'Kim', 'email' => 'james@email.com', 'phone' => '555-0102', 'address' => '456 Oak Ave', 'membership_type' => 'Faculty', 'join_date' => '2024-08-20', 'expiry_date' => '2026-08-20', 'is_verified' => true, 'clearance_status' => 'Clear'],
        ['id' => 3, 'library_id' => 'LIB003', 'first_name' => 'Sophia', 'last_name' => 'Lee', 'email' => 'sophia@email.com', 'phone' => '555-0103', 'address' => '789 Pine St', 'membership_type' => 'Student', 'join_date' => '2025-03-01', 'expiry_date' => '2026-03-01', 'is_verified' => false, 'clearance_status' => 'Pending']
    ];
    file_put_contents($dataFile, json_encode($initialMembers, JSON_PRETTY_PRINT));
}

function getMembers() {
    global $dataFile;
    $data = file_get_contents($dataFile);
    return json_decode($data, true) ?: [];
}

function saveMembers($members) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($members, JSON_PRETTY_PRINT));
}

// ============================================
// GET ALL MEMBERS OR SEARCH
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $members = getMembers();
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if ($id) {
        // Get single member
        foreach ($members as $member) {
            if ($member['id'] == $id) {
                echo json_encode($member);
                exit;
            }
        }
        http_response_code(404);
        echo json_encode(['error' => 'Member not found']);
        exit;
    }
    
    if ($search) {
        $results = [];
        $searchLower = strtolower($search);
        foreach ($members as $member) {
            if (stripos($member['first_name'], $searchLower) !== false || 
                stripos($member['last_name'], $searchLower) !== false ||
                stripos($member['email'], $searchLower) !== false ||
                stripos($member['library_id'], $searchLower) !== false) {
                $results[] = $member;
            }
        }
        echo json_encode($results);
    } else {
        echo json_encode($members);
    }
    exit;
}

// ============================================
// ADD NEW MEMBER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['first_name']) || empty($input['last_name']) || empty($input['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'First name, last name, and email are required']);
        exit;
    }
    
    $members = getMembers();
    
    // Generate new ID
    $maxId = 0;
    foreach ($members as $member) {
        if ($member['id'] > $maxId) $maxId = $member['id'];
    }
    $newId = $maxId + 1;
    
    // Generate library ID
    $libId = 'LIB' . str_pad($newId, 3, '0', STR_PAD_LEFT);
    
    $newMember = [
        'id' => $newId,
        'library_id' => $libId,
        'first_name' => $input['first_name'],
        'last_name' => $input['last_name'],
        'email' => $input['email'],
        'phone' => $input['phone'] ?? '',
        'address' => $input['address'] ?? '',
        'membership_type' => $input['membership_type'] ?? 'Student',
        'join_date' => $input['join_date'] ?? date('Y-m-d'),
        'expiry_date' => $input['expiry_date'] ?? date('Y-m-d', strtotime('+1 year')),
        'is_verified' => isset($input['is_verified']) ? (bool)$input['is_verified'] : false,
        'clearance_status' => $input['clearance_status'] ?? 'Pending'
    ];
    
    $members[] = $newMember;
    saveMembers($members);
    
    http_response_code(201);
    echo json_encode(['message' => 'Member added successfully', 'member' => $newMember]);
    exit;
}

// ============================================
// UPDATE MEMBER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Member ID required']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $members = getMembers();
    $found = false;
    
    foreach ($members as &$member) {
        if ($member['id'] == $id) {
            $member['first_name'] = $input['first_name'] ?? $member['first_name'];
            $member['last_name'] = $input['last_name'] ?? $member['last_name'];
            $member['email'] = $input['email'] ?? $member['email'];
            $member['phone'] = $input['phone'] ?? $member['phone'];
            $member['address'] = $input['address'] ?? $member['address'];
            $member['membership_type'] = $input['membership_type'] ?? $member['membership_type'];
            $member['join_date'] = $input['join_date'] ?? $member['join_date'];
            $member['expiry_date'] = $input['expiry_date'] ?? $member['expiry_date'];
            $member['is_verified'] = isset($input['is_verified']) ? (bool)$input['is_verified'] : $member['is_verified'];
            $member['clearance_status'] = $input['clearance_status'] ?? $member['clearance_status'];
            $found = true;
            break;
        }
    }
    
    if ($found) {
        saveMembers($members);
        echo json_encode(['message' => 'Member updated successfully', 'member' => $member]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Member not found']);
    }
    exit;
}

// ============================================
// DELETE MEMBER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Member ID required']);
        exit;
    }
    
    $members = getMembers();
    $found = false;
    
    foreach ($members as $key => $member) {
        if ($member['id'] == $id) {
            unset($members[$key]);
            $found = true;
            break;
        }
    }
    
    if ($found) {
        $members = array_values($members);
        saveMembers($members);
        echo json_encode(['message' => 'Member deleted successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Member not found']);
    }
    exit;
}

// ============================================
// METHOD NOT ALLOWED
// ============================================
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>