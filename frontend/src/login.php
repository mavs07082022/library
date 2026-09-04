<?php
// login.php - UNIFIED LOGIN WITH MODERN DESIGN
session_start();

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Supabase Configuration
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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        try {
            // Search for user by username or email
            $users = supabaseRequest('users?select=*&or=(username.eq.' . urlencode($username) . ',email.eq.' . urlencode($username) . ')');
            
            if (empty($users)) {
                $error = 'Invalid credentials';
            } else {
                $user = $users[0];
                
                // Verify password
                if ($user['password'] !== $password) {
                    $error = 'Invalid credentials';
                } elseif (!$user['is_active']) {
                    $error = 'Account is deactivated. Please contact administrator.';
                } elseif (!$user['is_verified']) {
                    $error = 'Account is not verified. Please verify your email.';
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['user_id_display'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['login_time'] = time();
                    
                    // Redirect based on role
                    $role = $user['role'];
                    if ($role === 'admin') {
                        header('Location: admin_dashboard.php');
                    } elseif ($role === 'librarian') {
                        header('Location: ./librarian/librarian_dashboard.php');
                    } elseif ($role === 'student') {
                        header('Location: ./students/student_dashboard.php');
                    } else {
                        header('Location: dashboard.php');
                    }
                    exit;
                }
            }
        } catch (Exception $e) {
            $error = 'Connection error. Please try again.';
            error_log('Login error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - St. Agnes Academy-Library Management System</title>
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
            background: linear-gradient(135deg, #1a1a1a 0%, #2a1a1a 50%, #ff0199c9 100%);
            padding: 20px;
        }
        
        .login-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            min-height: 560px;
            background: linear-gradient(135deg, #1a1a1a 0%, #2a1a1a 50%, #ff0199c9 100%);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            border: 0.5px  #e8e0d8;
        }
        
        /* ===== LEFT SIDE - BRANDING ===== */
        .login-brand {
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
        .login-brand::after {
            content: '';
            position: absolute;
            bottom: -30%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(212, 160, 160, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        .login-brand .brand-logo {
            max-width: 130px;
            height: auto;
            display: block;
            margin: 0 auto 20px auto;
            position: relative;
            z-index: 1;
        }
        .login-brand .brand-title {
            font-size: 32px;
            font-weight: 700;
            color: #f0e8e0;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 1;
        }
        .login-brand .brand-subtitle {
            font-size: 15px;
            color: #8a7a6e;
            letter-spacing: 2px;
            font-weight: 300;
            margin-top: 4px;
            position: relative;
            z-index: 1;
        }
        .login-brand .brand-tagline {
            font-size: 14px;
            color: #d4c9c0;
            margin-top: 24px;
            line-height: 1.6;
            position: relative;
            z-index: 1;
            opacity: 0.7;
        }
        .login-brand .brand-divider {
            width: 40px;
            height: 2px;
            background: #d4a0a0;
            margin: 16px auto;
            position: relative;
            z-index: 1;
        }
        
        /* ===== RIGHT SIDE - LOGIN FORM ===== */
        .login-form-side {
            flex: 1;
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
            min-width: 320px;
        }
        
        .login-form-header {
            margin-bottom: 28px;
        }
        .login-form-header h1 {
            font-size: 30px;
            font-family: 'Times New Roman', Times serif;
            color: #1a1a1a;
            margin: 0 0 4px 0;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .login-form-header p {
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
        /* password wrapper - only affects password field container */
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-wrapper input {
            padding-right: 44px; /* space for eye icon */
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
        
        .login-btn {
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
        .login-btn:hover {
            background: #2a2a2a;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .login-btn:active {
            transform: scale(0.98);
        }
        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .login-btn .spinner {
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
        .login-btn.loading .btn-text {
            display: none;
        }
        .login-btn.loading .spinner {
            display: block;
        }
        
        .login-footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f0edea;
            text-align: center;
        }
        .login-footer .forgot-link {
            color: #8a7a6e;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .login-footer .forgot-link:hover {
            color: #1a1a1a;
            text-decoration: underline;
        }
        
        .demo-creds {
            margin-top: 14px;
            display: flex;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            color: #b0a8a0;
            flex-wrap: wrap;
        }
        .demo-creds .cred-group {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .demo-creds .role-label {
            color: #8a7a6e;
            font-weight: 500;
            font-size: 11px;
        }
        .demo-creds span {
            background: #f5f3f0;
            padding: 3px 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 11px;
            color: #4a3a2e;
        }
        .demo-creds .label {
            background: none;
            font-family: inherit;
            color: #c0b4a8;
            padding: 3px 0;
        }
        .demo-creds .separator {
            color: #ddd8d0;
        }
        
        .login-footer .register-link {
            margin-top: 12px;
        }
        .login-footer .register-link a {
            color: #1a1a1a;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .login-footer .register-link a:hover {
            color: #d4a0a0;
            text-decoration: underline;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                min-height: auto;
                border-radius: 16px;
            }
            .login-brand {
                padding: 32px 28px;
                min-width: auto;
            }
            .login-brand .brand-logo {
                max-width: 100px;
            }
            .login-brand .brand-title {
                font-size: 26px;
            }
            .login-form-side {
                padding: 32px 28px;
                min-width: auto;
            }
        }
        @media (max-width: 480px) {
            body {
                padding: 12px;
                background: #ffffff;
            }
            .login-container {
                border-radius: 12px;
                border: 1px solid #e8e0d8;
            }
            .login-brand {
                padding: 24px 20px;
            }
            .login-brand .brand-logo {
                max-width: 80px;
            }
            .login-brand .brand-title {
                font-size: 22px;
            }
            .login-form-side {
                padding: 24px 20px;
            }
            .login-form-header h1 {
                font-size: 20px;
            }
            .demo-creds {
                flex-direction: column;
                align-items: center;
                gap: 4px;
            }
            .demo-creds .cred-group {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- ===== LEFT SIDE - BRANDING ===== -->
        <div class="login-brand">
            <img src="./img/agustinnb.png" alt="Bestlink College of the Philippines" class="brand-logo">
            <div class="brand-title">ST. AGNES ACADEMY</div>
            <div class="brand-subtitle">Caloocan Inc.</div>
            <div class="brand-divider"></div>
            <div class="brand-tagline">
                Library Management System<br>
                <span style="font-size:13px;opacity:0.6;">Knowledge at your fingertips</span>
            </div>
        </div>
        
        <!-- ===== RIGHT SIDE - LOGIN FORM ===== -->
        <div class="login-form-side">
            <div class="login-form-header">
                <h1>Welcome Back</h1>
                <p>Sign in to access your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label>Username or Email <span class="required">*</span></label>
                    <input type="text" name="username" placeholder="Enter username or email" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="passwordField" placeholder="Enter your password" required minlength="4">
                        <button type="button" class="toggle-password" id="togglePasswordBtn" aria-label="Show password">
                            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                    <span class="spinner"></span>
                </button>
            </form>
            
            <div class="login-footer">
                <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                
                <div class="register-link">
                    <p style="text-align:center;margin-top:14px;font-size:14px;color:#8a7a6e;">
                        Don't have an account? <a href="\update-lib\student_register.php">Register here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // ============================================
        // TOGGLE PASSWORD VISIBILITY (eye icon)
        // ============================================
        (function() {
            const passwordInput = document.getElementById('passwordField');
            const toggleBtn = document.getElementById('togglePasswordBtn');
            if (!passwordInput || !toggleBtn) return;

            // Store the initial SVG content (eye open)
            const eyeOpenSVG = `<svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
            const eyeClosedSVG = `<svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;

            let isPasswordVisible = false;

            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                isPasswordVisible = !isPasswordVisible;
                passwordInput.type = isPasswordVisible ? 'text' : 'password';
                toggleBtn.innerHTML = isPasswordVisible ? eyeClosedSVG : eyeOpenSVG;
                // maintain focus
                passwordInput.focus();
            });
        })();

        // ============================================
        // LOGIN FORM HANDLING
        // ============================================
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
        
        // ============================================
        // KEYBOARD SHORTCUT - Enter to submit
        // ============================================
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    const form = this.closest('form');
                    if (form) {
                        form.submit();
                    }
                }
            });
        });
        
        // ============================================
        // FOCUS FIRST INPUT ON LOAD
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[name="username"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
        
        // ============================================
        // REMOVE LOADING STATE IF FORM SUBMISSION FAILS
        // ============================================
        <?php if (!empty($error)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.remove('loading');
            btn.disabled = false;
        });
        <?php endif; ?>
    </script>
</body>
</html>