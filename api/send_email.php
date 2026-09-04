<?php
// api/send_email.php - Complete Email Sending with PHPMailer

// ============================================
// LOAD EMAIL CONFIGURATION
// ============================================
if (file_exists(__DIR__ . '/email_config.php')) {
    require_once __DIR__ . '/email_config.php';
} else {
    die('❌ email_config.php not found! Please create it with your Gmail credentials.');
}

// ============================================
// LOAD PHPMailer - Check multiple locations
// ============================================
$phpmailerPaths = [
    __DIR__ . '/phpmailer/src/PHPMailer.php',
    __DIR__ . '/../phpmailer/src/PHPMailer.php',
    __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
    __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php',
    'C:/xampp/htdocs/lib/vendor/phpmailer/phpmailer/src/PHPMailer.php',
];

$loaded = false;
foreach ($phpmailerPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $basePath = dirname($path);
        require_once $basePath . '/SMTP.php';
        require_once $basePath . '/Exception.php';
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    die('❌ PHPMailer not found. Install: composer require phpmailer/phpmailer');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ============================================
// SEND OTP EMAIL FUNCTION (UPDATED WITH PURPOSE)
// ============================================
function sendOTPEmail($to, $otp, $fullName = 'Student', $purpose = 'registration') {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $fullName);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // ============================================
        // ATTACH LOGO IMAGE WITH CID
        // ============================================
        $imagePath = __DIR__ . '/../frontend/src/img/agustinnb.png';
        $imageCID = 'bcp_logo';
        
        if (file_exists($imagePath)) {
            $mail->AddEmbeddedImage($imagePath, $imageCID, 'bcpd.png', 'base64', 'image/png');
            $logoHtml = '<img src="cid:' . $imageCID . '" alt="Bestlink College of the Philippines" class="logo-image" style="max-width:80px;height:auto;display:block;margin:0 auto 10px auto;">';
        } else {
            // Fallback if image not found
            $logoHtml = '<span style="font-size:48px;display:block;margin-bottom:10px;">📚</span>';
        }
        
        // Choose template based on purpose
        if ($purpose === 'password_reset') {
            $mail->Subject = '🔐 St. Agnes Academy - Password Reset OTP';
            $mail->Body    = getPasswordResetOTPEmailHTML($otp, $fullName, $logoHtml);
            $mail->AltBody = "Your password reset OTP is: $otp\n\nThis code will expire in 10 minutes.\n\nSt. Agnes Academy Library System";
        } else {
            $mail->Subject = '🔐 St. Agnes Academy - OTP Verification Code';
            $mail->Body    = getOTPEmailHTML($otp, $fullName, $logoHtml);
            $mail->AltBody = "Your OTP verification code is: $otp\n\nThis code will expire in 10 minutes.\n\nSt. Agnes Academy Library System";
        }
        
        $mail->send();
        error_log("✅ OTP email sent to: $to (purpose: $purpose)");
        return true;
        
    } catch (Exception $e) {
        error_log("❌ Email failed to $to: " . $e->getMessage());
        return false;
    }
}

// ============================================
// SEND PASSWORD RESET OTP EMAIL (FOR BACKWARD COMPATIBILITY)
// ============================================
function sendPasswordResetOTP($to, $otp, $fullName = 'User') {
    return sendOTPEmail($to, $otp, $fullName, 'password_reset');
}

// ============================================
// OTP EMAIL HTML TEMPLATE WITH CENTERED LOGO
// ============================================
function getOTPEmailHTML($otp, $fullName = '', $logoHtml = '') {
    $nameHtml = !empty($fullName) 
        ? "Hello <strong>" . htmlspecialchars($fullName) . "</strong>,<br><br>" 
        : "Hello,<br><br>";
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>OTP Verification</title>
        <style>
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; 
                background: #f0f4f8; 
                padding: 20px; 
                margin: 0;
            }
            .container { 
                max-width: 520px; 
                margin: 0 auto; 
                background: #ffffff; 
                border-radius: 16px; 
                padding: 40px 30px; 
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                border: 1px solid #e8edf4;
            }
            .header { 
                text-align: center; 
                margin-bottom: 24px;
                border-bottom: 2px solid #f0f4f8;
                padding-bottom: 20px;
            }
            .header .logo-wrapper {
                text-align: center;
                margin-bottom: 6px;
            }
            .header .logo-image {
                max-width: 80px;
                height: auto;
                display: block;
                margin: 0 auto 10px auto;
            }
            .header h1 { 
                color: #ab09b9; 
                margin: 0; 
                font-size: 28px; 
                font-weight: 700;
                letter-spacing: 2px;
                text-align: center;
            }
            .header .sub { 
                color: #666; 
                font-size: 14px; 
                margin-top: 2px;
                text-align: center;
            }
            .header .divider {
                width: 60px;
                height: 3px;
                background: #122fb1;
                margin: 10px auto 0;
                border-radius: 2px;
            }
            .content { 
                padding: 10px 0; 
            }
            .content p { 
                color: #333; 
                font-size: 15px; 
                line-height: 1.7; 
                margin: 0 0 12px 0;
            }
            .otp-box { 
                background: #f0f4ff; 
                padding: 25px 20px; 
                text-align: center; 
                border-radius: 12px; 
                margin: 24px 0;
                border: 2px dashed #122fb1;
            }
            .otp-box .label { 
                color: #666; 
                font-size: 13px; 
                display: block; 
                margin-bottom: 10px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .otp-box .code { 
                font-size: 40px; 
                font-weight: 700; 
                color: #122fb1; 
                letter-spacing: 12px;
                font-family: "Courier New", monospace;
                background: white;
                padding: 10px 20px;
                border-radius: 8px;
                display: inline-block;
                box-shadow: 0 2px 8px rgba(18,47,177,0.1);
            }
            .info-text { 
                text-align: center; 
                color: #666; 
                font-size: 14px; 
                margin: 16px 0 8px;
            }
            .info-text strong {
                color: #122fb1;
            }
            .footer { 
                margin-top: 30px; 
                text-align: center; 
                color: #999; 
                font-size: 12px; 
                border-top: 1px solid #f0f4f8; 
                padding-top: 20px;
            }
            .footer p { 
                margin: 4px 0; 
            }
            .footer a { 
                color: #122fb1; 
                text-decoration: none; 
            }
            .badge {
                display: inline-block;
                background: #34a853;
                color: white;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
            }
            @media (max-width: 480px) {
                .container { padding: 24px 16px; }
                .otp-box .code { font-size: 28px; letter-spacing: 8px; padding: 8px 12px; }
                .header h1 { font-size: 22px; }
                .header .logo-image { max-width: 60px; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo-wrapper">
                    ' . $logoHtml . '
                </div>
                <h1>ST. AGNES ACADEMY</h1>
                <div class="sub">Caloocan Inc.</div>
                <div class="divider"></div>
            </div>
            
            <div class="content">
                <p>' . $nameHtml . '</p>
                <p>Thank you for registering with our <strong>Library Management System</strong>. Please use the verification code below to complete your registration.</p>
                
                <div class="otp-box">
                    <span class="label">🔐 Verification Code</span>
                    <div class="code">' . $otp . '</div>
                </div>
                
                <p class="info-text">⏱️ This code will expire in <strong>10 minutes</strong></p>
                <p class="info-text" style="font-size:13px;color:#999;">If you didn\'t request this, please ignore this email.</p>
            </div>
            
            <div class="footer">
                <p><strong>St. Agnes Academy Caloocan Inc.</strong></p>
                <p>Library Management System</p>
                <p style="margin-top:8px;">
                    <span class="badge">🔒 Secure</span>
                </p>
                <p style="margin-top:12px;font-size:11px;color:#bbb;">
                    This is an automated message, please do not reply.
                </p>
            </div>
        </div>
    </body>
    </html>
    ';
}

// ============================================
// PASSWORD RESET OTP EMAIL HTML TEMPLATE
// ============================================
function getPasswordResetOTPEmailHTML($otp, $fullName = '', $logoHtml = '') {
    $nameHtml = !empty($fullName) 
        ? "Hello <strong>" . htmlspecialchars($fullName) . "</strong>,<br><br>" 
        : "Hello,<br><br>";
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Reset OTP</title>
        <style>
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; 
                background: #f0f4f8; 
                padding: 20px; 
                margin: 0;
            }
            .container { 
                max-width: 520px; 
                margin: 0 auto; 
                background: #ffffff; 
                border-radius: 16px; 
                padding: 40px 30px; 
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                border: 1px solid #e8edf4;
            }
            .header { 
                text-align: center; 
                margin-bottom: 24px;
                border-bottom: 2px solid #f0f4f8;
                padding-bottom: 20px;
            }
            .header .logo-wrapper {
                text-align: center;
                margin-bottom: 6px;
            }
            .header .logo-image {
                max-width: 80px;
                height: auto;
                display: block;
                margin: 0 auto 10px auto;
            }
            .header h1 { 
                color: #ab09b9; 
                margin: 0; 
                font-size: 28px; 
                font-weight: 700;
                letter-spacing: 2px;
                text-align: center;
            }
            .header .sub { 
                color: #666; 
                font-size: 14px; 
                margin-top: 2px;
                text-align: center;
            }
            .header .divider {
                width: 60px;
                height: 3px;
                background: #122fb1;
                margin: 10px auto 0;
                border-radius: 2px;
            }
            .content { 
                padding: 10px 0; 
            }
            .content p { 
                color: #333; 
                font-size: 15px; 
                line-height: 1.7; 
                margin: 0 0 12px 0;
            }
            .otp-box { 
                background: #fff5f0; 
                padding: 25px 20px; 
                text-align: center; 
                border-radius: 12px; 
                margin: 24px 0;
                border: 2px dashed #d4a0a0;
            }
            .otp-box .label { 
                color: #666; 
                font-size: 13px; 
                display: block; 
                margin-bottom: 10px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .otp-box .code { 
                font-size: 40px; 
                font-weight: 700; 
                color: #8a3a2a; 
                letter-spacing: 12px;
                font-family: "Courier New", monospace;
                background: white;
                padding: 10px 20px;
                border-radius: 8px;
                display: inline-block;
                box-shadow: 0 2px 8px rgba(138,58,42,0.1);
            }
            .info-text { 
                text-align: center; 
                color: #666; 
                font-size: 14px; 
                margin: 16px 0 8px;
            }
            .info-text strong {
                color: #8a3a2a;
            }
            .footer { 
                margin-top: 30px; 
                text-align: center; 
                color: #999; 
                font-size: 12px; 
                border-top: 1px solid #f0f4f8; 
                padding-top: 20px;
            }
            .footer p { 
                margin: 4px 0; 
            }
            .footer a { 
                color: #122fb1; 
                text-decoration: none; 
            }
            .badge {
                display: inline-block;
                background: #34a853;
                color: white;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
            }
            .warning-badge {
                display: inline-block;
                background: #ea4335;
                color: white;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
            }
            @media (max-width: 480px) {
                .container { padding: 24px 16px; }
                .otp-box .code { font-size: 28px; letter-spacing: 8px; padding: 8px 12px; }
                .header h1 { font-size: 22px; }
                .header .logo-image { max-width: 60px; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo-wrapper">
                    ' . $logoHtml . '
                </div>
                <h1>ST. AGNES ACADEMY</h1>
                <div class="sub">Caloocan Inc.</div>
                <div class="divider"></div>
            </div>
            
            <div class="content">
                <p>' . $nameHtml . '</p>
                <p>We received a request to reset your password for the <strong>Library Management System</strong>. Please use the verification code below to proceed.</p>
                
                <div class="otp-box">
                    <span class="label">🔐 Password Reset Code</span>
                    <div class="code">' . $otp . '</div>
                </div>
                
                <p class="info-text">⏱️ This code will expire in <strong>10 minutes</strong></p>
                <p class="info-text" style="font-size:13px;color:#999;">If you didn\'t request a password reset, please ignore this email or contact support.</p>
            </div>
            
            <div class="footer">
                <p><strong>St. Agnes Academy Caloocan Inc.</strong></p>
                <p>Library Management System</p>
                <p style="margin-top:8px;">
                    <span class="warning-badge">🔒 Password Reset</span>
                    <span class="badge">Secure</span>
                </p>
                <p style="margin-top:12px;font-size:11px;color:#bbb;">
                    This is an automated message, please do not reply.
                </p>
            </div>
        </div>
    </body>
    </html>
    ';
}

// ============================================
// TEST FUNCTION - Access directly for testing
// ============================================
if (basename($_SERVER['PHP_SELF']) === 'send_email.php' && isset($_GET['test'])) {
    header('Content-Type: text/html');
    $testEmail = $_GET['email'] ?? '';
    $purpose = $_GET['purpose'] ?? 'registration';
    
    if (empty($testEmail)) {
        echo '<h2>📧 Test Email Sender</h2>';
        echo '<form method="GET" style="margin:20px 0;">';
        echo '<input type="hidden" name="test" value="1">';
        echo '<label style="display:block;margin-bottom:8px;">Email Address:</label>';
        echo '<input type="email" name="email" placeholder="Enter email to test" required style="padding:10px;width:300px;border:2px solid #ddd;border-radius:8px;">';
        echo '<br><br>';
        echo '<label style="display:block;margin-bottom:8px;">Purpose:</label>';
        echo '<select name="purpose" style="padding:10px;width:300px;border:2px solid #ddd;border-radius:8px;">';
        echo '<option value="registration">Registration OTP</option>';
        echo '<option value="password_reset">Password Reset OTP</option>';
        echo '</select>';
        echo '<br><br>';
        echo '<button type="submit" style="padding:10px 20px;background:#122fb1;color:white;border:none;border-radius:8px;cursor:pointer;">Send Test OTP</button>';
        echo '</form>';
        echo '<p style="color:#999;font-size:14px;">This will send a test OTP (123456) to the email address you enter.</p>';
        exit;
    }
    
    echo "<h2>📧 Testing Email Sending</h2>";
    echo "<p>Sending test OTP to: <strong>$testEmail</strong></p>";
    echo "<p>Purpose: <strong>$purpose</strong></p>";
    echo "<p>Using SMTP: <strong>" . SMTP_USERNAME . "</strong></p>";
    echo "<hr>";
    
    $testOtp = '123456';
    $result = sendOTPEmail($testEmail, $testOtp, 'Test User', $purpose);
    
    if ($result) {
        echo '<p style="color:green;font-size:18px;">✅ Email sent successfully!</p>';
        echo '<p>Check your inbox at: <strong>' . htmlspecialchars($testEmail) . '</strong></p>';
        echo '<p>💡 Check <strong>Spam/Junk</strong> folder if not in inbox.</p>';
        echo '<p>Test OTP: <strong style="font-size:24px;color:#122fb1;">' . $testOtp . '</strong></p>';
        
        // Debug image info
        $imagePath = __DIR__ . '/../frontend/src/img/agustinnb.png';
        if (file_exists($imagePath)) {
            echo '<p style="color:green;">✅ Image found at: ' . $imagePath . '</p>';
            echo '<p>Image size: ' . filesize($imagePath) . ' bytes</p>';
        } else {
            echo '<p style="color:red;">❌ Image NOT found at: ' . $imagePath . '</p>';
            echo '<p>Please check if the image exists at: <strong>C:\xampp\htdocs\lib\frontend\src\img\agustinnb.png</strong></p>';
        }
    } else {
        echo '<p style="color:red;font-size:18px;">❌ Failed to send email!</p>';
        echo '<p>Possible issues:</p>';
        echo '<ul>';
        echo '<li>Wrong App Password in <strong>email_config.php</strong></li>';
        echo '<li>2-Step Verification not enabled on Gmail</li>';
        echo '<li>IMAP not enabled in Gmail settings</li>';
        echo '<li>Server blocks port 587</li>';
        echo '</ul>';
        echo '<p><strong>Debug:</strong> Check error logs for more details.</p>';
    }
    exit;
}
?>