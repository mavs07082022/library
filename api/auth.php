<?php
// api/auth.php - Complete Authentication with OTP Email
require_once 'config.php';
require_once 'send_email.php';

$method = getMethod();
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================
// LOGIN
// ============================================
if ($method === 'POST' && $action === 'login') {
    $input = getInput();
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        jsonResponse(['error' => 'Username and password are required'], 400);
    }
    
    try {
        $users = supabaseRequest('users?select=*&or=(username.eq.' . urlencode($username) . ',email.eq.' . urlencode($username) . ')');
        
        if (empty($users)) {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
        
        $user = $users[0];
        
        // Plain text password check
        if ($user['password'] !== $password) {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
        
        if (!$user['is_active']) {
            jsonResponse(['error' => 'Account is deactivated'], 403);
        }
        
        // Update last login
        supabaseRequest('users?id=eq.' . $user['id'], 'PATCH', ['last_login' => date('Y-m-d H:i:s')]);
        
        $tokenData = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'user_id' => $user['user_id']
        ];
        $token = base64_encode(json_encode($tokenData));
        
        jsonResponse([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'email' => $user['email'],
                'user_id' => $user['user_id'],
                'is_verified' => $user['is_verified']
            ]
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Login failed: ' . $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// SEND OTP WITH EMAIL
// ============================================
if ($method === 'POST' && $action === 'send-otp') {
    $input = getInput();
    $email = $input['email'] ?? '';
    $full_name = $input['full_name'] ?? 'Student';
    $purpose = $input['purpose'] ?? 'registration';
    
    error_log("📧 Sending OTP to: $email, Name: $full_name");
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Valid email is required'], 400);
    }
    
    // Check if email already registered
    if ($purpose === 'registration') {
        try {
            $existing = supabaseRequest('users?select=email&email=eq.' . urlencode($email));
            if (!empty($existing)) {
                jsonResponse(['error' => 'Email already registered. Please login instead.'], 400);
            }
        } catch (Exception $e) {
            // Continue if check fails
        }
    }
    
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    try {
        // Delete old OTPs for this email
        try {
            supabaseRequest('otp_verifications?email=eq.' . urlencode($email), 'DELETE');
        } catch (Exception $e) {
            // Ignore delete errors
        }
        
        // Store OTP in database
        $otpResult = supabaseRequest('otp_verifications', 'POST', [
            'email' => $email,
            'otp' => $otp,
            'expires_at' => $expires,
            'is_used' => false,
            'purpose' => $purpose
        ]);
        
        error_log("📝 OTP stored in database: $otp for $email");
        
        // Send OTP via email
        $emailSent = sendOTPEmail($email, $otp, $full_name);
        
        error_log("📧 Email sent result: " . ($emailSent ? 'YES' : 'NO') . " to $email");
        
        jsonResponse([
            'success' => true,
            'message' => 'OTP sent to your email!',
            'otp' => $otp, // Remove in production
            'email_sent' => $emailSent,
            'expires_at' => $expires
        ]);
    } catch (Exception $e) {
        error_log("❌ OTP error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to send OTP: ' . $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// VERIFY OTP
// ============================================
if ($method === 'POST' && $action === 'verify-otp') {
    $input = getInput();
    $email = $input['email'] ?? '';
    $otp = $input['otp'] ?? '';
    $purpose = $input['purpose'] ?? 'registration';
    
    error_log("🔍 Verifying OTP for $email: $otp");
    
    if (empty($email) || empty($otp)) {
        jsonResponse(['error' => 'Email and OTP are required'], 400);
    }
    
    try {
        $otps = supabaseRequest('otp_verifications?select=*&email=eq.' . urlencode($email) . '&otp=eq.' . urlencode($otp) . '&is_used=eq.false');
        
        if (empty($otps)) {
            error_log("❌ Invalid OTP for $email: $otp");
            jsonResponse(['error' => 'Invalid or expired OTP'], 400);
        }
        
        $otpRecord = $otps[0];
        if (strtotime($otpRecord['expires_at']) < time()) {
            error_log("⏰ Expired OTP for $email: $otp");
            jsonResponse(['error' => 'OTP has expired. Please request a new one.'], 400);
        }
        
        // Mark OTP as used
        supabaseRequest('otp_verifications?id=eq.' . $otpRecord['id'], 'PATCH', ['is_used' => true]);
        
        error_log("✅ OTP verified for $email");
        
        jsonResponse([
            'success' => true,
            'message' => 'OTP verified successfully'
        ]);
    } catch (Exception $e) {
        error_log("❌ OTP verification error: " . $e->getMessage());
        jsonResponse(['error' => 'OTP verification failed: ' . $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// REGISTER STUDENT
// ============================================
if ($method === 'POST' && $action === 'register-student') {
    $input = getInput();
    
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $full_name = $input['full_name'] ?? '';
    $student_id = $input['student_id'] ?? '';
    $course = $input['course'] ?? '';
    $year_level = $input['year_level'] ?? '';
    $section = $input['section'] ?? '';
    $birth_date = $input['birth_date'] ?? '';
    $address = $input['address'] ?? '';
    $phone = $input['phone'] ?? '';
    $guardian_name = $input['guardian_name'] ?? '';
    $guardian_phone = $input['guardian_phone'] ?? '';
    $otp_verified = $input['otp_verified'] ?? false;
    
    error_log("📝 Registering student: $full_name, $email, $student_id");
    
    if (empty($email) || empty($password) || empty($full_name) || empty($student_id)) {
        jsonResponse(['error' => 'Email, password, full name, and student ID are required'], 400);
    }
    
    if (!$otp_verified) {
        jsonResponse(['error' => 'OTP verification is required'], 400);
    }
    
    try {
        // Check if user exists
        $existing = supabaseRequest('users?select=*&email=eq.' . urlencode($email));
        if (!empty($existing)) {
            jsonResponse(['error' => 'Email already registered'], 400);
        }
        
        // Check if student ID is unique
        $existingStudent = supabaseRequest('students?select=*&student_id=eq.' . urlencode($student_id));
        if (!empty($existingStudent)) {
            jsonResponse(['error' => 'Student ID already registered'], 400);
        }
        
        // Create user
        $newUser = [
            'username' => $email,
            'email' => $email,
            'password' => $password,
            'full_name' => $full_name,
            'role' => 'student',
            'user_id' => 'STU' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'is_verified' => true,
            'is_active' => true
        ];
        
        $userResult = supabaseRequest('users', 'POST', $newUser);
        $user = $userResult[0];
        
        error_log("✅ User created: " . $user['id']);
        
        // Create student record
        $studentData = [
            'user_id' => $user['id'],
            'student_id' => $student_id,
            'course' => $course,
            'year_level' => $year_level,
            'section' => $section,
            'address' => $address,
            'phone' => $phone,
            'guardian_name' => $guardian_name,
            'guardian_phone' => $guardian_phone
        ];
        
        if (!empty($birth_date)) {
            $studentData['birth_date'] = $birth_date;
        }
        
        supabaseRequest('students', 'POST', $studentData);
        
        error_log("✅ Student record created for: " . $full_name);
        
        jsonResponse([
            'success' => true,
            'message' => 'Registration successful! You can now login.',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'user_id' => $user['user_id']
            ]
        ]);
    } catch (Exception $e) {
        error_log("❌ Registration failed: " . $e->getMessage());
        jsonResponse(['error' => 'Registration failed: ' . $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// CHECK EMAIL AVAILABILITY
// ============================================
if ($method === 'GET' && $action === 'check-email') {
    $email = isset($_GET['email']) ? $_GET['email'] : '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Valid email is required'], 400);
    }
    
    try {
        $users = supabaseRequest('users?select=email&email=eq.' . urlencode($email));
        jsonResponse(['exists' => !empty($users)]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to check email: ' . $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// CHECK STUDENT ID AVAILABILITY
// ============================================
if ($method === 'GET' && $action === 'check-student-id') {
    $studentId = isset($_GET['student_id']) ? $_GET['student_id'] : '';
    
    if (empty($studentId)) {
        jsonResponse(['error' => 'Student ID is required'], 400);
    }
    
    try {
        $students = supabaseRequest('students?select=student_id&student_id=eq.' . urlencode($studentId));
        jsonResponse(['exists' => !empty($students)]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to check student ID: ' . $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// VERIFY TOKEN
// ============================================
if ($method === 'GET' && $action === 'verify') {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
    
    if ($token) {
        $decoded = json_decode(base64_decode($token), true);
        if ($decoded && isset($decoded['id'])) {
            try {
                $users = supabaseRequest('users?select=*&id=eq.' . $decoded['id']);
                if (!empty($users)) {
                    $user = $users[0];
                    jsonResponse([
                        'success' => true,
                        'user' => [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'full_name' => $user['full_name'],
                            'role' => $user['role'],
                            'email' => $user['email'],
                            'user_id' => $user['user_id'],
                            'is_verified' => $user['is_verified']
                        ]
                    ]);
                    exit;
                }
            } catch (Exception $e) {
                jsonResponse(['error' => 'Verification failed'], 401);
            }
        }
    }
    jsonResponse(['error' => 'Invalid token'], 401);
    exit;
}

jsonResponse(['error' => 'Invalid request'], 400);
?>