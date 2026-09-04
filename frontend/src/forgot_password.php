<?php
// forgot_password.php - Forgot Password with OTP Verification
session_start();
date_default_timezone_set('Asia/Manila');

// ============================================
// FIXED: Correct paths for includes
// ============================================
$emailConfigPaths = [
    __DIR__ . '/api/email_config.php',
    __DIR__ . '/../api/email_config.php',
    __DIR__ . '/../../api/email_config.php',
    'C:/xampp/htdocs/lib/api/email_config.php',
    'C:/xampp/htdocs/lib/frontend/api/email_config.php',
];

$configFound = false;
foreach ($emailConfigPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $configFound = true;
        $sendEmailPath = dirname($path) . '/send_email.php';
        if (file_exists($sendEmailPath)) {
            require_once $sendEmailPath;
        }
        break;
    }
}

if (!$configFound) {
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_USERNAME', 'st.agnesacademycaloocan@gmail.com');
    define('SMTP_PASSWORD', 'pllvswuhloglskba');
    define('SMTP_FROM_EMAIL', 'st.agnesacademycaloocan@gmail.com');
    define('SMTP_FROM_NAME', 'St. Agnes Academy Caloocan Inc.- Library System');
}

// ============================================
// SUPABASE CONFIGURATION
// ============================================
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
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("CURL Error: " . $error);
        throw new Exception("Connection error: " . $error);
    }

    if ($httpCode >= 400) {
        error_log("API Error (" . $httpCode . "): " . $response);
        throw new Exception("API Error: " . $response);
    }

    return json_decode($response, true);
}

function generateOTP() {
    return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

// ============================================
// SEND OTP EMAIL (FALLBACK)
// ============================================
if (!function_exists('sendOTPEmail')) {
    function sendOTPEmail($to, $otp, $fullName = 'User', $purpose = 'registration') {
        $phpmailerPaths = [
            __DIR__ . '/phpmailer/src/PHPMailer.php',
            __DIR__ . '/../phpmailer/src/PHPMailer.php',
            __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php',
            'C:/xampp/htdocs/lib/vendor/phpmailer/phpmailer/src/PHPMailer.php',
        ];
        
        $loaded = false;
        foreach ($phpmailerPaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $basePath = dirname($path);
                if (file_exists($basePath . '/SMTP.php')) require_once $basePath . '/SMTP.php';
                if (file_exists($basePath . '/Exception.php')) require_once $basePath . '/Exception.php';
                $loaded = true;
                break;
            }
        }
        
        if (!$loaded) {
            error_log("❌ PHPMailer not found");
            return false;
        }
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to, $fullName);
            
            $mail->isHTML(true);
            
            if ($purpose === 'password_reset') {
                $mail->Subject = '🔐 St. Agnes Academy - Password Reset OTP';
                $mail->Body = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2 style='color: #1a1a1a;'>Password Reset Request</h2>
                    <p>Hello <strong>$fullName</strong>,</p>
                    <p>We received a request to reset your password. Please use the OTP below:</p>
                    <div style='background: #f5f3f0; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0;'>
                        <h1 style='font-size: 48px; color: #8a3a2a; letter-spacing: 10px; margin: 0;'>$otp</h1>
                    </div>
                    <p>This OTP will expire in <strong>10 minutes</strong>.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <hr>
                    <p><small>St. Agnes Academy Caloocan Inc. - Library Management System</small></p>
                </body>
                </html>
                ";
                $mail->AltBody = "Your password reset OTP is: $otp\n\nThis code will expire in 10 minutes.";
            } else {
                $mail->Subject = '🔐 St. Agnes Academy - OTP Verification Code';
                $mail->Body = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2 style='color: #1a1a1a;'>OTP Verification</h2>
                    <p>Hello <strong>$fullName</strong>,</p>
                    <p>Your OTP verification code is:</p>
                    <div style='background: #f5f3f0; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0;'>
                        <h1 style='font-size: 48px; color: #122fb1; letter-spacing: 10px; margin: 0;'>$otp</h1>
                    </div>
                    <p>This OTP will expire in <strong>10 minutes</strong>.</p>
                    <hr>
                    <p><small>St. Agnes Academy Caloocan Inc. - Library Management System</small></p>
                </body>
                </html>
                ";
                $mail->AltBody = "Your OTP verification code is: $otp\n\nThis code will expire in 10 minutes.";
            }
            
            $mail->send();
            error_log("✅ OTP email sent to: $to");
            return true;
        } catch (Exception $e) {
            error_log("❌ Email failed to $to: " . $e->getMessage());
            return false;
        }
    }
}

// ============================================
// PROCESS FORM SUBMISSIONS
// ============================================
$step = isset($_GET['step']) ? $_GET['step'] : 'request';
$error = '';
$success = '';
$email = '';

// ============================================
// STEP 1: REQUEST OTP
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_otp') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            // Check if user exists
            $users = supabaseRequest('users?select=id,email,full_name,is_active,is_verified&email=eq.' . urlencode($email));
            
            if (empty($users)) {
                $error = 'No account found with this email address';
            } elseif (!$users[0]['is_active']) {
                $error = 'Account is deactivated. Please contact administrator.';
            } elseif (!$users[0]['is_verified']) {
                $error = 'Account is not verified. Please verify your email first.';
            } else {
                // Generate OTP
                $otpCode = generateOTP();
                $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                $fullName = $users[0]['full_name'] ?? 'User';
                
                // Store OTP in database (without user_id column)
                $otpData = [
                    'email' => $email,
                    'otp' => $otpCode,
                    'expires_at' => $expiresAt,
                    'is_used' => false,
                    'purpose' => 'password_reset'
                ];
                
                // Delete old OTPs for this email
                try {
                    supabaseRequest('otp_verifications?email=eq.' . urlencode($email) . '&purpose=eq.password_reset', 'DELETE');
                } catch (Exception $e) {
                    // Ignore delete errors
                }
                
                // Insert new OTP
                supabaseRequest('otp_verifications', 'POST', $otpData);
                
                // Send OTP email
                $emailSent = sendOTPEmail($email, $otpCode, $fullName, 'password_reset');
                
                if ($emailSent) {
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_otp'] = $otpCode;
                    $_SESSION['reset_expires'] = strtotime('+10 minutes');
                    
                    $success = 'OTP has been sent to your email. Please check your inbox.';
                    $step = 'verify';
                } else {
                    $error = 'Failed to send OTP email. Please try again later.';
                }
            }
        } catch (Exception $e) {
            $error = 'Connection error: ' . $e->getMessage();
            error_log('Forgot password error: ' . $e->getMessage());
        }
    }
}

// ============================================
// STEP 2: VERIFY OTP
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $otp = trim($_POST['otp'] ?? '');
    $email = $_SESSION['reset_email'] ?? '';
    
    if (empty($otp)) {
        $error = 'Please enter the OTP code';
    } elseif (empty($email)) {
        $error = 'Session expired. Please start over.';
        $step = 'request';
    } else {
        try {
            // Verify OTP
            $otpRecords = supabaseRequest('otp_verifications?select=*&email=eq.' . urlencode($email) . '&otp=eq.' . urlencode($otp) . '&purpose=eq.password_reset&is_used=eq.false');
            
            if (empty($otpRecords)) {
                $error = 'Invalid OTP code';
            } else {
                $record = $otpRecords[0];
                $expiresAt = strtotime($record['expires_at']);
                
                if (time() > $expiresAt) {
                    $error = 'OTP has expired. Please request a new one.';
                    $step = 'request';
                } else {
                    // Mark OTP as used
                    supabaseRequest('otp_verifications?id=eq.' . $record['id'], 'PATCH', ['is_used' => true]);
                    
                    $_SESSION['reset_verified'] = true;
                    $_SESSION['reset_email'] = $email;
                    $success = 'OTP verified successfully. Please set your new password.';
                    $step = 'reset';
                }
            }
        } catch (Exception $e) {
            $error = 'Connection error: ' . $e->getMessage();
            error_log('OTP verification error: ' . $e->getMessage());
        }
    }
}

// ============================================
// STEP 3: RESET PASSWORD
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $email = $_SESSION['reset_email'] ?? '';
    $verified = $_SESSION['reset_verified'] ?? false;
    
    if (!$verified || empty($email)) {
        $error = 'Session expired. Please start over.';
        $step = 'request';
    } elseif (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please enter and confirm your new password';
    } elseif (strlen($newPassword) < 4) {
        $error = 'Password must be at least 4 characters long';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        try {
            // Update password
            $users = supabaseRequest('users?select=id&email=eq.' . urlencode($email));
            
            if (empty($users)) {
                $error = 'User not found';
            } else {
                $userId = $users[0]['id'];
                supabaseRequest('users?id=eq.' . $userId, 'PATCH', ['password' => $newPassword]);
                
                // Clear session
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_otp']);
                unset($_SESSION['reset_expires']);
                unset($_SESSION['reset_verified']);
                
                $success = 'Password reset successfully! You can now login with your new password.';
                $step = 'complete';
            }
        } catch (Exception $e) {
            $error = 'Connection error: ' . $e->getMessage();
            error_log('Password reset error: ' . $e->getMessage());
        }
    }
}

// ============================================
// RESEND OTP
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    $email = $_SESSION['reset_email'] ?? '';
    
    if (empty($email)) {
        $error = 'Session expired. Please start over.';
        $step = 'request';
    } else {
        try {
            $otpCode = generateOTP();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Get user info
            $users = supabaseRequest('users?select=full_name&email=eq.' . urlencode($email));
            $fullName = !empty($users) ? ($users[0]['full_name'] ?? 'User') : 'User';
            
            // Delete old OTPs
            try {
                supabaseRequest('otp_verifications?email=eq.' . urlencode($email) . '&purpose=eq.password_reset', 'DELETE');
            } catch (Exception $e) {
                // Ignore
            }
            
            $otpData = [
                'email' => $email,
                'otp' => $otpCode,
                'expires_at' => $expiresAt,
                'is_used' => false,
                'purpose' => 'password_reset'
            ];
            
            supabaseRequest('otp_verifications', 'POST', $otpData);
            
            $emailSent = sendOTPEmail($email, $otpCode, $fullName, 'password_reset');
            
            if ($emailSent) {
                $_SESSION['reset_otp'] = $otpCode;
                $_SESSION['reset_expires'] = strtotime('+10 minutes');
                $success = 'New OTP has been sent to your email.';
            } else {
                $error = 'Failed to send OTP. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Connection error: ' . $e->getMessage();
            error_log('Resend OTP error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - St. Agnes Academy</title>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f5f3f0;
            padding: 20px;
        }
        
        .forgot-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            min-height: 560px;
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            border: 1px solid #e8e0d8;
        }
        
        /* ===== LEFT SIDE - BRANDING ===== */
        .forgot-brand {
            flex: 1;
            background: linear-gradient(135deg, #1a1a1a 0%, #2a1a1a 50%, #ff0199c9 100%);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
            min-width: 280px;
            text-align: center;
        }
        .forgot-brand::after {
            content: '';
            position: absolute;
            bottom: -30%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(212, 160, 160, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        .forgot-brand .brand-logo {
            max-width: 130px;
            height: auto;
            display: block;
            margin: 0 auto 20px auto;
            position: relative;
            z-index: 1;
        }
        .forgot-brand .brand-title {
            font-size: 32px;
            font-weight: 700;
            color: #f0e8e0;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 1;
        }
        .forgot-brand .brand-subtitle {
            font-size: 15px;
            color: #8a7a6e;
            letter-spacing: 2px;
            font-weight: 300;
            margin-top: 4px;
            position: relative;
            z-index: 1;
        }
        .forgot-brand .brand-tagline {
            font-size: 14px;
            color: #d4c9c0;
            margin-top: 24px;
            line-height: 1.6;
            position: relative;
            z-index: 1;
            opacity: 0.7;
        }
        .forgot-brand .brand-divider {
            width: 40px;
            height: 2px;
            background: #d4a0a0;
            margin: 16px auto;
            position: relative;
            z-index: 1;
        }
        
        /* ===== RIGHT SIDE - FORM ===== */
        .forgot-form-side {
            flex: 1;
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
            min-width: 320px;
        }
        
        .forgot-form-header {
            margin-bottom: 28px;
        }
        .forgot-form-header h1 {
            font-size: 24px;
            color: #1a1a1a;
            margin: 0 0 4px 0;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .forgot-form-header p {
            color: #8a7a6e;
            font-size: 14px;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            font-size: 13px;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        .form-group label .required {
            color: #8a3a2a;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e8e0d8;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s ease;
            box-sizing: border-box;
            background: #faf8f6;
            color: #1a1a1a;
        }
        .form-group input:focus {
            border-color: #d4a0a0;
            outline: none;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(212, 160, 160, 0.12);
        }
        .form-group input::placeholder {
            color: #b0a8a0;
        }
        
        .form-group .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .form-group .input-wrapper input {
            width: 100%;
            padding: 12px 46px 12px 16px;
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
        
        .otp-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .otp-input-group input {
            flex: 1;
        }
        .btn-resend {
            padding: 12px 20px;
            background: #f0edea;
            color: #4a3a2e;
            border: 2px solid #e8e0d8;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .btn-resend:hover {
            background: #e8e0d8;
            border-color: #d4c9c0;
        }
        .btn-resend:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .error-message {
            background: #f0e0d8;
            color: #8a3a2a;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 16px;
            border-left: 4px solid #d48080;
        }
        .success-message {
            background: #e8ddd8;
            color: #3a2a2a;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 16px;
            border-left: 4px solid #d4a0a0;
        }
        
        .forgot-btn {
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
            margin-top: 4px;
        }
        .forgot-btn:hover {
            background: #2a2a2a;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .forgot-btn:active {
            transform: scale(0.98);
        }
        .forgot-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .forgot-btn .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top: 3px solid #f0e8e0;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .forgot-btn.loading .btn-text {
            display: none;
        }
        .forgot-btn.loading .spinner {
            display: block;
        }
        
        .forgot-footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f0edea;
            text-align: center;
        }
        .forgot-footer .back-link {
            color: #8a7a6e;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .forgot-footer .back-link:hover {
            color: #1a1a1a;
            text-decoration: underline;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e8e0d8;
            transition: all 0.3s ease;
        }
        .step-dot.active {
            background: #d4a0a0;
            transform: scale(1.2);
        }
        .step-dot.completed {
            background: #34a853;
        }
        .step-label {
            font-size: 11px;
            color: #b0a8a0;
            text-align: center;
            margin-top: 4px;
        }
        .step-labels {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-top: 4px;
        }
        .step-labels span {
            font-size: 11px;
            color: #b0a8a0;
        }
        .step-labels span.active {
            color: #1a1a1a;
            font-weight: 500;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #9a8a7e;
            margin-top: 4px;
        }
        
        .complete-icon {
            font-size: 64px;
            display: block;
            text-align: center;
            margin-bottom: 16px;
        }
        .complete-text {
            text-align: center;
            font-size: 18px;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        .complete-sub {
            text-align: center;
            color: #8a7a6e;
            font-size: 14px;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .forgot-container {
                flex-direction: column;
                min-height: auto;
                border-radius: 16px;
            }
            .forgot-brand {
                padding: 32px 28px;
                min-width: auto;
            }
            .forgot-brand .brand-logo {
                max-width: 100px;
            }
            .forgot-brand .brand-title {
                font-size: 26px;
            }
            .forgot-form-side {
                padding: 32px 28px;
                min-width: auto;
            }
            .otp-input-group {
                flex-direction: column;
            }
            .otp-input-group .btn-resend {
                width: 100%;
            }
        }
        @media (max-width: 480px) {
            body {
                padding: 12px;
                background: #ffffff;
            }
            .forgot-container {
                border-radius: 12px;
                border: 1px solid #e8e0d8;
            }
            .forgot-brand {
                padding: 24px 20px;
            }
            .forgot-brand .brand-logo {
                max-width: 80px;
            }
            .forgot-brand .brand-title {
                font-size: 22px;
            }
            .forgot-form-side {
                padding: 24px 20px;
            }
            .forgot-form-header h1 {
                font-size: 20px;
            }
            .step-labels {
                gap: 12px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <!-- ===== LEFT SIDE - BRANDING ===== -->
        <div class="forgot-brand">
            <img src="./img/agustinnb.png" alt="St. Agnes Academy" class="brand-logo">
            <div class="brand-title">ST. AGNES ACADEMY</div>
            <div class="brand-subtitle">Caloocan Inc.</div>
            <div class="brand-divider"></div>
            <div class="brand-tagline">
                Library Management System<br>
                <span style="font-size:13px;opacity:0.6;">Secure password recovery</span>
            </div>
        </div>
        
        <!-- ===== RIGHT SIDE - FORM ===== -->
        <div class="forgot-form-side">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step-dot <?php echo $step === 'request' ? 'active' : ($step === 'verify' || $step === 'reset' || $step === 'complete' ? 'completed' : ''); ?>"></div>
                <div class="step-dot <?php echo $step === 'verify' ? 'active' : ($step === 'reset' || $step === 'complete' ? 'completed' : ''); ?>"></div>
                <div class="step-dot <?php echo $step === 'reset' ? 'active' : ($step === 'complete' ? 'completed' : ''); ?>"></div>
                <div class="step-dot <?php echo $step === 'complete' ? 'active' : ''; ?>"></div>
            </div>
            <div class="step-labels">
                <span class="<?php echo $step === 'request' ? 'active' : ($step === 'verify' || $step === 'reset' || $step === 'complete' ? 'completed' : ''); ?>">Request</span>
                <span class="<?php echo $step === 'verify' ? 'active' : ($step === 'reset' || $step === 'complete' ? 'completed' : ''); ?>">Verify</span>
                <span class="<?php echo $step === 'reset' ? 'active' : ($step === 'complete' ? 'completed' : ''); ?>">Reset</span>
                <span class="<?php echo $step === 'complete' ? 'active' : ''; ?>">Done</span>
            </div>
            
            <div class="forgot-form-header">
                <?php if ($step === 'request'): ?>
                    <h1>Forgot Password</h1>
                    <p>Enter your email to receive a verification code</p>
                <?php elseif ($step === 'verify'): ?>
                    <h1>Verify OTP</h1>
                    <p>Enter the 6-digit code sent to your email</p>
                <?php elseif ($step === 'reset'): ?>
                    <h1>Reset Password</h1>
                    <p>Create a new password for your account</p>
                <?php elseif ($step === 'complete'): ?>
                    <h1>Password Reset Complete</h1>
                    <p>Your password has been successfully changed</p>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($step === 'request'): ?>
            <!-- ===== STEP 1: REQUEST OTP ===== -->
            <form method="POST" action="" id="requestForm">
                <input type="hidden" name="action" value="request_otp">
                <div class="form-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" name="email" placeholder="Enter your registered email" required value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <button type="submit" class="forgot-btn" id="requestBtn">
                    <span class="btn-text">Send OTP</span>
                    <span class="spinner"></span>
                </button>
            </form>
            
            <?php elseif ($step === 'verify'): ?>
            <!-- ===== STEP 2: VERIFY OTP ===== -->
            <form method="POST" action="" id="verifyForm">
                <input type="hidden" name="action" value="verify_otp">
                <div class="form-group">
                    <label>OTP Code <span class="required">*</span></label>
                    <div class="otp-input-group">
                        <input type="text" name="otp" placeholder="Enter 6-digit OTP" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code">
                        <button type="button" class="btn-resend" id="resendBtn" onclick="resendOTP()">Resend</button>
                    </div>
                    <div style="font-size:12px;color:#9a8a7e;margin-top:4px;">
                        <span id="timerDisplay">OTP expires in 10:00</span>
                    </div>
                </div>
                <button type="submit" class="forgot-btn" id="verifyBtn">
                    <span class="btn-text">Verify OTP</span>
                    <span class="spinner"></span>
                </button>
            </form>
            
            <?php elseif ($step === 'reset'): ?>
            <!-- ===== STEP 3: RESET PASSWORD ===== -->
            <form method="POST" action="" id="resetForm">
                <input type="hidden" name="action" value="reset_password">
                <div class="form-group">
                    <label>New Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" name="new_password" id="newPassword" placeholder="Enter new password" required minlength="4">
                        <button type="button" class="toggle-password" onclick="togglePassword('newPassword', this)">
                            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                    <div class="password-requirements">Password must be at least 4 characters</div>
                </div>
                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm new password" required minlength="4">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword', this)">
                            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="forgot-btn" id="resetBtn">
                    <span class="btn-text">Reset Password</span>
                    <span class="spinner"></span>
                </button>
            </form>
            
            <?php elseif ($step === 'complete'): ?>
            <!-- ===== STEP 4: COMPLETE ===== -->
            <div style="text-align:center;padding:20px 0;">
                <div class="complete-icon">✅</div>
                <div class="complete-text">Password Reset Successful!</div>
                <div class="complete-sub">Your password has been changed. You can now login with your new password.</div>
                <a href="login.php" style="display:inline-block;margin-top:24px;padding:12px 32px;background:#1a1a1a;color:#f0e8e0;text-decoration:none;border-radius:10px;font-weight:600;transition:all 0.2s ease;">Go to Login</a>
            </div>
            <?php endif; ?>
            
            <div class="forgot-footer">
                <a href="login.php" class="back-link">← Back to Login</a>
            </div>
        </div>
    </div>
    
    <script>
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
        
        document.getElementById('requestForm')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('requestBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
        
        document.getElementById('verifyForm')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('verifyBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
        
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('resetBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
        
        function resendOTP() {
            const btn = document.getElementById('resendBtn');
            btn.disabled = true;
            btn.textContent = 'Sending...';
            window.location.href = 'forgot_password.php?action=resend';
        }
        
        <?php if ($step === 'verify' && isset($_SESSION['reset_expires'])): ?>
        (function() {
            const timerDisplay = document.getElementById('timerDisplay');
            const resendBtn = document.getElementById('resendBtn');
            const expiresAt = <?php echo $_SESSION['reset_expires']; ?> * 1000;
            
            function updateTimer() {
                const now = Date.now();
                const remaining = Math.max(0, Math.floor((expiresAt - now) / 1000));
                
                if (remaining <= 0) {
                    timerDisplay.textContent = '⏰ OTP has expired';
                    timerDisplay.style.color = '#8a3a2a';
                    if (resendBtn) {
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend';
                    }
                    return;
                }
                
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                timerDisplay.textContent = `OTP expires in ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                timerDisplay.style.color = remaining < 60 ? '#8a3a2a' : '#9a8a7e';
            }
            
            updateTimer();
            const interval = setInterval(updateTimer, 1000);
            
            window.addEventListener('beforeunload', function() {
                clearInterval(interval);
            });
        })();
        <?php endif; ?>
        
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input:not([type="hidden"])');
            if (firstInput) {
                firstInput.focus();
            }
            
            const otpInput = document.querySelector('input[name="otp"]');
            if (otpInput) {
                otpInput.addEventListener('input', function() {
                    if (this.value.length === 6 && /^[0-9]{6}$/.test(this.value)) {
                        document.getElementById('verifyForm')?.submit();
                    }
                });
            }
        });
        
        <?php if (!empty($error)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const btns = document.querySelectorAll('.forgot-btn');
            btns.forEach(btn => {
                btn.classList.remove('loading');
                btn.disabled = false;
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>