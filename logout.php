<?php
session_start();

// 0. Remember who is logging out BEFORE destroying the session
$was_viewer = (isset($_SESSION['viewer_logged_in']) && $_SESSION['viewer_logged_in'] === true);

// 1. Unset all session variables
$_SESSION = array();

// 2. Destroy the session cookie (optional but recommended for complete security)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session
session_destroy();

// 4. Redirect to the correct login page
if ($was_viewer) {
    header("Location: viewer_login.php");
} else {
    header("Location: index.php");
}
exit;
?>