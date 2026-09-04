<?php
// student_register.php - Complete Student Registration with Modern Design
session_start();

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') {
        header('Location: student_dashboard.php');
    } else {
        header('Location: admin_dashboard.php');
    }
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    if ($action === 'check_email') {
        $email = $_POST['email'] ?? '';
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'exists' => false]);
            exit;
        }
        
        try {
            $existing = supabaseRequest('users?select=email&email=eq.' . urlencode($email));
            echo json_encode(['success' => true, 'exists' => !empty($existing)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'exists' => false]);
        }
        exit;
    }
    
    if ($action === 'check_student_id') {
        $student_id = $_POST['student_id'] ?? '';
        
        if (empty($student_id)) {
            echo json_encode(['success' => false, 'exists' => false]);
            exit;
        }
        
        try {
            $existing = supabaseRequest('students?select=student_id&student_id=eq.' . urlencode($student_id));
            echo json_encode(['success' => true, 'exists' => !empty($existing)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'exists' => false]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - St. Agnes Academy</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; 
            background: linear-gradient(135deg, #1a1a1a 0%, #2a1a1a 50%, #ff0199c9 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .register-container {
            background: #ffffff;
            border-radius: 20px;
            padding: 40px 44px;
            max-width: 540px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.06);
            border: 1px solid #e8e0d8;
        }
        .logo {
            text-align: center;
            margin-bottom: 24px;
        }
        .logo img {
            max-width: 72px;
            height: auto;
            display: block;
            margin: 0 auto 10px auto;
        }
        .logo h1 {
            font-size: 22px;
            color: #cf1fa9;
            margin: 0 0 2px 0;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .logo .subtitle {
            color: #8a7a6e;
            font-size: 13px;
            letter-spacing: 1px;
        }
        .logo .tagline {
            color: #b0a8a0;
            font-size: 12px;
            margin-top: 4px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 24px;
        }
        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e0d8d0;
            transition: all 0.3s ease;
        }
        .step-dot.active {
            background: #1a1a1a;
            width: 30px;
            border-radius: 5px;
        }
        .step-dot.done {
            background: #d4a0a0;
        }
        .step-label {
            text-align: center;
            font-size: 13px;
            color: #8a7a6e;
            margin-bottom: 20px;
        }
        .step-label strong {
            color: #1a1a1a;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        .form-group label .required {
            color: #8a3a2a;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #e8e0d8;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s ease;
            font-family: inherit;
            background: #faf8f6;
            color: #1a1a1a;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #d4a0a0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(212,160,160,0.12);
            background: #ffffff;
        }
        .form-group .input-hint {
            font-size: 12px;
            color: #b0a8a0;
            margin-top: 4px;
            min-height: 20px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-wrapper input {
            width: 100%;
            padding: 11px 46px 11px 14px;
        }
        .toggle-password {
            position: absolute;
            right: 14px;
            background: transparent;
            border: none;
            padding: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b0a8a0;
            transition: color 0.2s ease;
            width: 24px;
            height: 24px;
        }
        .toggle-password:hover {
            color: #1a1a1a;
        }
        .toggle-password:focus {
            outline: none;
        }
        .toggle-password svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .otp-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .otp-container input {
            flex: 1;
        }
        .btn-otp {
            padding: 11px 20px;
            background: #1a1a1a;
            color: #f0e8e0;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            white-space: nowrap;
            transition: all 0.2s ease;
            min-height: 46px;
            font-size: 14px;
        }
        .btn-otp:hover:not(:disabled) {
            background: #2a2a2a;
        }
        .btn-otp:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-otp.loading {
            opacity: 0.7;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #1a1a1a;
            color: #f0e8e0;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
        }
        .btn-submit:hover:not(:disabled) {
            background: #2a2a2a;
        }
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-submit.loading {
            opacity: 0.7;
        }
        .btn-back {
            flex: 1;
            padding: 14px;
            background: #f0edea;
            color: #4a3a2e;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-back:hover {
            background: #e0d8d0;
        }
        .message {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            display: none;
            font-size: 14px;
        }
        .message.error {
            display: block;
            background: #f0e0d8;
            color: #8a3a2a;
            border-left: 4px solid #d48080;
        }
        .message.success {
            display: block;
            background: #e8ddd8;
            color: #3a2a2a;
            border-left: 4px solid #d4a0a0;
        }
        .message.info {
            display: block;
            background: #e8e4e0;
            color: #3a3a3a;
            border-left: 4px solid #b0a8a0;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #8a7a6e;
        }
        .login-link a {
            color: #1a1a1a;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .login-link a:hover {
            color: #d4a0a0;
            text-decoration: underline;
        }
        .form-step {
            display: none;
        }
        .form-step.active {
            display: block;
        }
        .otp-timer {
            font-size: 13px;
            color: #8a7a6e;
            text-align: center;
            margin-top: 8px;
            min-height: 24px;
        }
        .otp-timer .resend-btn {
            color: #1a1a1a;
            cursor: pointer;
            font-weight: 600;
            text-decoration: underline;
            transition: all 0.2s ease;
        }
        .otp-timer .resend-btn:hover {
            color: #d4a0a0;
        }
        .password-strength {
            height: 4px;
            background: #e8e0d8;
            border-radius: 2px;
            margin-top: 6px;
            overflow: hidden;
        }
        .password-strength .bar {
            height: 100%;
            width: 0%;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        .password-strength-text {
            font-size: 12px;
            color: #b0a8a0;
            margin-top: 2px;
            min-height: 20px;
        }
        .password-match-status {
            font-size: 12px;
            margin-top: 2px;
            min-height: 20px;
            transition: all 0.3s ease;
        }
        .password-match-status.match {
            color: #3a2a2a;
        }
        .password-match-status.no-match {
            color: #8a3a2a;
        }
        .btn-row {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }
        .otp-status {
            font-size: 13px;
            margin-top: 6px;
            padding: 8px 12px;
            border-radius: 6px;
            display: none;
        }
        .otp-status.verified {
            display: block;
            background: #e8ddd8;
            color: #3a2a2a;
        }
        .otp-status.error {
            display: block;
            background: #f0e0d8;
            color: #8a3a2a;
        }
        @media (max-width: 480px) {
            .register-container {
                padding: 24px 20px;
                border-radius: 16px;
                border: none;
                box-shadow: none;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .otp-container {
                flex-direction: column;
            }
            .btn-otp {
                width: 100%;
            }
            .btn-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <img src="frontend/src/img/agustinnb.png" alt="BCP Logo">
            <h1>ST. AGNES ACADEMY</h1>
            <div class="subtitle">Caloocan Inc.</div>
            <div class="tagline">Student Registration</div>
        </div>

        <div id="message" class="message"></div>

        <div class="step-indicator">
            <span class="step-dot active" id="step1Dot"></span>
            <span class="step-dot" id="step2Dot"></span>
            <span class="step-dot" id="step3Dot"></span>
        </div>

        <!-- Step 1: Personal Information -->
        <div class="form-step active" id="step1">
            <div class="step-label">Step 1 of 3: <strong>Personal Information</strong></div>
            <form id="step1Form" onsubmit="event.preventDefault(); goToStep2();">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" id="full_name" placeholder="Enter your full name" required>
                </div>
                <div class="form-group">
                    <label>Student ID <span class="required">*</span></label>
                    <input type="text" id="student_id" placeholder="Enter your student ID" required>
                    <div class="input-hint" id="studentIdHint"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Year Level</label>
                        <select id="year_level">
                            <option value="">Select Year</option>
                            <option value="Grade 7">Grade 7</option>
                            <option value="Grade 8">Grade 8</option>
                            <option value="Grade 9">Grade 9</option>
                            <option value="Grade 10">Grade 10</option>
                         <option value="Grade 11">Grade 11</option>
                         <option value="Grade 12">Grade 12</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" id="section" placeholder="Enter your section">
                    </div>
                </div>
                <div class="form-group">
                    <label>Birth Date</label>
                    <input type="date" id="birth_date">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" id="address" placeholder="Your complete address">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="phone" placeholder="e.g. 09123456789">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Guardian Name</label>
                        <input type="text" id="guardian_name" placeholder="Guardian's full name">
                    </div>
                    <div class="form-group">
                        <label>Guardian Phone</label>
                        <input type="tel" id="guardian_phone" placeholder="Guardian's phone">
                    </div>
                </div>
                <button type="submit" class="btn-submit">Next →</button>
            </form>
        </div>

        <!-- Step 2: Account & OTP -->
        <div class="form-step" id="step2">
            <div class="step-label">Step 2 of 3: <strong>Account Setup & Verification</strong></div>
            <form id="step2Form" onsubmit="event.preventDefault(); registerStudent();">
                <div class="form-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" id="email" placeholder="Enter your email address" required>
                    <div class="input-hint" id="emailHint"></div>
                </div>
                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="password" placeholder="Minimum 6 characters" required minlength="6">
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                    <div class="password-strength"><div class="bar" id="passwordStrengthBar"></div></div>
                    <div class="password-strength-text" id="passwordStrengthText"></div>
                </div>
                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" placeholder="Confirm your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)">
                            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                    <div class="password-match-status" id="passwordMatchStatus"></div>
                </div>
                <div class="form-group">
                    <label>OTP Verification <span class="required">*</span></label>
                    <div class="otp-container">
                        <input type="text" id="otp" placeholder="Enter 6-digit OTP" maxlength="6" pattern="[0-9]{6}">
                        <button type="button" class="btn-otp" id="sendOtpBtn" onclick="sendOTP()">Send OTP</button>
                    </div>
                    <div id="otpStatus" class="otp-status"></div>
                    <div class="otp-timer" id="otpTimer"></div>
                </div>
                <div class="btn-row">
                    <button type="button" class="btn-back" onclick="goToStep1()">← Back</button>
                    <button type="submit" class="btn-submit" id="registerBtn">Register</button>
                </div>
            </form>
        </div>

        <!-- Step 3: Success -->
        <div class="form-step" id="step3">
            <div style="text-align:center;padding:20px 0;">
                <div style="font-size:56px;margin-bottom:16px;color:#d4a0a0;">◆</div>
                <h2 style="color:#3a2a2a;">Registration Successful!</h2>
                <p style="color:#8a7a6e;margin:12px 0 24px;">Your account has been created. You can now login to the library system.</p>
                <a href="frontend/src/login.php" class="btn-submit" style="display:inline-block;text-decoration:none;text-align:center;">Login Now</a>
            </div>
        </div>

        <div class="login-link">
            Already have an account? <a href="frontend/src/login.php">Login here</a>
        </div>
    </div>

    <script>
        let otpTimerInterval = null;
        let otpExpirySeconds = 0;
        let isOtpVerified = false;
        let currentStep = 1;

        // ============================================
        // TOGGLE PASSWORD VISIBILITY
        // ============================================
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            
            if (isPassword) {
                btn.innerHTML = `<svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
            } else {
                btn.innerHTML = `<svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
            }
        }

        // ============================================
        // STEP NAVIGATION
        // ============================================
        function goToStep(step) {
            currentStep = step;
            document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
            document.getElementById('step' + step).classList.add('active');
            
            document.querySelectorAll('.step-dot').forEach((dot, index) => {
                dot.classList.remove('active', 'done');
                if (index + 1 === step) dot.classList.add('active');
                else if (index + 1 < step) dot.classList.add('done');
            });
        }

        function goToStep1() {
            goToStep(1);
        }

        function goToStep2() {
            const fullName = document.getElementById('full_name').value.trim();
            const studentId = document.getElementById('student_id').value.trim();
            
            if (!fullName) {
                showMessage('Please enter your full name.', 'error');
                return;
            }
            if (!studentId) {
                showMessage('Please enter your student ID.', 'error');
                return;
            }
            
            checkStudentId(studentId, function(exists) {
                if (exists) {
                    showMessage('This student ID is already registered. Please check your ID.', 'error');
                } else {
                    showMessage('', '');
                    goToStep(2);
                }
            });
        }

        // ============================================
        // CHECK STUDENT ID
        // ============================================
        function checkStudentId(studentId, callback) {
            const hint = document.getElementById('studentIdHint');
            hint.textContent = 'Checking...';
            hint.style.color = '#8a7a6e';
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=check_student_id&student_id=' + encodeURIComponent(studentId)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.exists) {
                        hint.textContent = 'This student ID is already registered.';
                        hint.style.color = '#8a3a2a';
                        callback(true);
                    } else {
                        hint.textContent = 'Student ID is available.';
                        hint.style.color = '#3a2a2a';
                        callback(false);
                    }
                } else {
                    hint.textContent = 'Unable to check student ID.';
                    hint.style.color = '#8a5a3a';
                    callback(false);
                }
            })
            .catch(() => {
                hint.textContent = 'Unable to check student ID. Please continue.';
                hint.style.color = '#8a5a3a';
                callback(false);
            });
        }

        // ============================================
        // CHECK EMAIL
        // ============================================
        function checkEmail(email, callback) {
            const hint = document.getElementById('emailHint');
            hint.textContent = 'Checking...';
            hint.style.color = '#8a7a6e';
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=check_email&email=' + encodeURIComponent(email)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.exists) {
                        hint.textContent = 'This email is already registered. Please login instead.';
                        hint.style.color = '#8a3a2a';
                        callback(true);
                    } else {
                        hint.textContent = 'Email is available.';
                        hint.style.color = '#3a2a2a';
                        callback(false);
                    }
                } else {
                    hint.textContent = 'Unable to check email.';
                    hint.style.color = '#8a5a3a';
                    callback(false);
                }
            })
            .catch(() => {
                hint.textContent = 'Unable to check email. Please continue.';
                hint.style.color = '#8a5a3a';
                callback(false);
            });
        }

        // ============================================
        // PASSWORD STRENGTH & MATCHING
        // ============================================
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const bar = document.getElementById('passwordStrengthBar');
            const text = document.getElementById('passwordStrengthText');
            
            let strength = 0;
            let label = 'Weak';
            let color = '#8a7a6e';
            
            if (password.length >= 6) strength += 20;
            if (password.length >= 10) strength += 20;
            if (/[a-z]/.test(password)) strength += 20;
            if (/[A-Z]/.test(password)) strength += 20;
            if (/[0-9]/.test(password)) strength += 20;
            
            if (strength >= 80) { label = 'Strong'; color = '#3a2a2a'; }
            else if (strength >= 60) { label = 'Good'; color = '#8a7a6e'; }
            
            bar.style.width = strength + '%';
            bar.style.background = color;
            text.textContent = password.length > 0 ? 'Strength: ' + label : '';
            
            // Check password match
            checkPasswordMatch();
        });

        document.getElementById('confirm_password').addEventListener('input', function() {
            checkPasswordMatch();
        });

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const statusEl = document.getElementById('passwordMatchStatus');
            
            if (password.length === 0 && confirmPassword.length === 0) {
                statusEl.textContent = '';
                statusEl.className = 'password-match-status';
                return;
            }
            
            if (password.length > 0 && confirmPassword.length === 0) {
                statusEl.textContent = 'Please confirm your password.';
                statusEl.className = 'password-match-status no-match';
                return;
            }
            
            if (password === confirmPassword) {
                statusEl.textContent = '✓ Passwords match';
                statusEl.className = 'password-match-status match';
            } else {
                statusEl.textContent = '✗ Passwords do not match';
                statusEl.className = 'password-match-status no-match';
            }
        }

        // ============================================
        // EMAIL CHECK ON INPUT
        // ============================================
        let emailCheckTimeout = null;
        document.getElementById('email').addEventListener('input', function() {
            const email = this.value.trim();
            const hint = document.getElementById('emailHint');
            
            if (emailCheckTimeout) {
                clearTimeout(emailCheckTimeout);
            }
            
            if (!email || !email.includes('@')) {
                hint.textContent = '';
                return;
            }
            
            emailCheckTimeout = setTimeout(function() {
                checkEmail(email, function(exists) {});
            }, 500);
        });

        // ============================================
        // STUDENT ID CHECK ON INPUT
        // ============================================
        let studentIdCheckTimeout = null;
        document.getElementById('student_id').addEventListener('input', function() {
            const studentId = this.value.trim();
            const hint = document.getElementById('studentIdHint');
            
            if (studentIdCheckTimeout) {
                clearTimeout(studentIdCheckTimeout);
            }
            
            if (!studentId || studentId.length < 3) {
                hint.textContent = '';
                return;
            }
            
            studentIdCheckTimeout = setTimeout(function() {
                checkStudentId(studentId, function(exists) {});
            }, 500);
        });

        // ============================================
        // SEND OTP
        // ============================================
        function sendOTP() {
            const email = document.getElementById('email').value.trim();
            const fullName = document.getElementById('full_name').value.trim();
            const btn = document.getElementById('sendOtpBtn');
            const otpStatus = document.getElementById('otpStatus');
            
            if (!email || !email.includes('@')) {
                showMessage('Please enter a valid email address.', 'error');
                return;
            }
            
            otpStatus.className = 'otp-status';
            otpStatus.textContent = '';
            isOtpVerified = false;
            
            btn.disabled = true;
            btn.textContent = 'Sending...';
            btn.classList.add('loading');
            
            fetch('api/auth.php?action=send-otp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    email: email, 
                    full_name: fullName || 'Student',
                    purpose: 'registration' 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('OTP sent to ' + email + '! Please check your email.', 'success');
                    
                    if (data.otp) {
                        console.log('Your OTP is:', data.otp);
                    }
                    
                    document.getElementById('otp').focus();
                    startOtpTimer(600);
                    
                    btn.textContent = 'Resend OTP';
                    btn.classList.remove('loading');
                    btn.disabled = false;
                } else {
                    showMessage(data.message || 'Failed to send OTP. Please try again.', 'error');
                    btn.textContent = 'Send OTP';
                    btn.classList.remove('loading');
                    btn.disabled = false;
                }
            })
            .catch((error) => {
                console.error('Fetch error:', error);
                showMessage('Failed to send OTP. Please try again.', 'error');
                btn.textContent = 'Send OTP';
                btn.classList.remove('loading');
                btn.disabled = false;
            });
        }

        // ============================================
        // OTP TIMER
        // ============================================
        function startOtpTimer(seconds) {
            otpExpirySeconds = seconds;
            const timerEl = document.getElementById('otpTimer');
            
            if (otpTimerInterval) {
                clearInterval(otpTimerInterval);
            }
            
            otpTimerInterval = setInterval(function() {
                otpExpirySeconds--;
                if (otpExpirySeconds <= 0) {
                    clearInterval(otpTimerInterval);
                    timerEl.innerHTML = 'OTP expired. <span class="resend-btn" onclick="sendOTP()">Resend OTP</span>';
                    document.getElementById('sendOtpBtn').textContent = 'Resend OTP';
                    document.getElementById('sendOtpBtn').disabled = false;
                    document.getElementById('sendOtpBtn').classList.remove('loading');
                } else {
                    const mins = Math.floor(otpExpirySeconds / 60);
                    const secs = otpExpirySeconds % 60;
                    timerEl.textContent = 'OTP expires in ' + mins + ':' + (secs < 10 ? '0' : '') + secs;
                }
            }, 1000);
        }

        // ============================================
        // VERIFY OTP ON INPUT
        // ============================================
        document.getElementById('otp').addEventListener('input', function() {
            const otp = this.value.trim();
            const otpStatus = document.getElementById('otpStatus');
            
            if (otp.length < 6) {
                otpStatus.className = 'otp-status';
                otpStatus.textContent = '';
                isOtpVerified = false;
                return;
            }
            
            if (otp.length === 6) {
                verifyOTP(otp);
            }
        });

        function verifyOTP(otp) {
            const email = document.getElementById('email').value.trim();
            const otpStatus = document.getElementById('otpStatus');
            
            if (!email) {
                showMessage('Please enter your email first.', 'error');
                return;
            }
            
            otpStatus.className = 'otp-status';
            otpStatus.textContent = 'Verifying...';
            
            fetch('api/auth.php?action=verify-otp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    email: email, 
                    otp: otp, 
                    purpose: 'registration' 
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    isOtpVerified = true;
                    otpStatus.className = 'otp-status verified';
                    otpStatus.textContent = 'OTP verified successfully! You can now register.';
                    
                    if (otpTimerInterval) {
                        clearInterval(otpTimerInterval);
                    }
                    document.getElementById('otpTimer').textContent = 'OTP Verified';
                } else {
                    isOtpVerified = false;
                    otpStatus.className = 'otp-status error';
                    otpStatus.textContent = data.message || 'Invalid OTP.';
                }
            })
            .catch(() => {
                otpStatus.className = 'otp-status error';
                otpStatus.textContent = 'Error verifying OTP. Please try again.';
            });
        }

        // ============================================
        // REGISTER STUDENT
        // ============================================
        function registerStudent() {
            const fullName = document.getElementById('full_name').value.trim();
            const studentId = document.getElementById('student_id').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const yearLevel = document.getElementById('year_level').value;
            const section = document.getElementById('section').value.trim();
            const birthDate = document.getElementById('birth_date').value;
            const address = document.getElementById('address').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const guardianName = document.getElementById('guardian_name').value.trim();
            const guardianPhone = document.getElementById('guardian_phone').value.trim();
            
            if (!fullName) { showMessage('Please enter your full name.', 'error'); return; }
            if (!studentId) { showMessage('Please enter your student ID.', 'error'); return; }
            if (!email) { showMessage('Please enter your email.', 'error'); return; }
            if (!password) { showMessage('Please enter a password.', 'error'); return; }
            if (password.length < 6) { showMessage('Password must be at least 6 characters.', 'error'); return; }
            if (password !== confirmPassword) { showMessage('Passwords do not match.', 'error'); return; }
            
            if (!isOtpVerified) {
                showMessage('Please verify your OTP first.', 'error');
                return;
            }
            
            const btn = document.getElementById('registerBtn');
            btn.disabled = true;
            btn.textContent = 'Registering...';
            btn.classList.add('loading');
            
            const data = {
                action: 'register-student',
                full_name: fullName,
                student_id: studentId,
                email: email,
                password: password,
                year_level: yearLevel,
                section: section,
                birth_date: birthDate,
                address: address,
                phone: phone,
                guardian_name: guardianName,
                guardian_phone: guardianPhone,
                otp_verified: true
            };
            
            fetch('api/auth.php?action=register-student', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage('Registration successful!', 'success');
                    goToStep(3);
                } else {
                    showMessage(data.message || 'Registration failed.', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Register';
                    btn.classList.remove('loading');
                }
            })
            .catch(() => {
                showMessage('Registration failed. Please try again.', 'error');
                btn.disabled = false;
                btn.textContent = 'Register';
                btn.classList.remove('loading');
            });
        }

        // ============================================
        // MESSAGE HELPER
        // ============================================
        function showMessage(text, type) {
            const el = document.getElementById('message');
            if (!text) {
                el.style.display = 'none';
                el.textContent = '';
                el.className = 'message';
                return;
            }
            el.textContent = text;
            el.className = 'message ' + type;
            el.style.display = 'block';
        }

        // ============================================
        // ENTER KEY SUPPORT
        // ============================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const activeStep = document.querySelector('.form-step.active');
                if (activeStep) {
                    if (activeStep.id === 'step1') {
                        goToStep2();
                    } else if (activeStep.id === 'step2') {
                        if (document.activeElement.id === 'otp') {
                            const otp = document.getElementById('otp').value.trim();
                            if (otp.length === 6) {
                                verifyOTP(otp);
                            }
                        } else {
                            registerStudent();
                        }
                    }
                }
            }
        });

        // ============================================
        // INITIALIZE
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            // Focus first input
            const firstInput = document.querySelector('#step1 input');
            if (firstInput) firstInput.focus();
        });
    </script>
</body>
</html>