<?php
// admin_logout.php - Universal logout
session_start();
session_destroy();

// Clear all session data
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to appropriate login page based on referrer
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($referer, 'librarian') !== false) {
    header('Location: homepage.php');
} elseif (strpos($referer, 'student') !== false) {
    header('Location: homepage.php');
} else {
    header('Location: homepage.php');
}
exit;
?>