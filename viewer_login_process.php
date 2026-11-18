<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the submitted credentials
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Hardcoded Viewer Credentials
    $viewer_user = 'test@ttj.com';
    $viewer_pass = 'Admin$2026';

    // Check if they match exactly
    if ($username === $viewer_user && $password === $viewer_pass) {
        
        // --- THIS IS THE CRITICAL PART ---
        // Set the session role to 'viewer' so they cannot edit anything
        $_SESSION['viewer_logged_in'] = true;
        $_SESSION['role'] = 'viewer'; 
        $_SESSION['admin_username'] = $username;
        
        // Redirect to the CMS dashboard
        header("Location: dashboard.php");
        exit();
        
    } else {
        // Wrong username or password
        header("Location: viewer_login.php?error=1");
        exit();
    }
} else {
    // If someone tries to access this file directly without submitting the form
    header("Location: viewer_login.php");
    exit();
}
?>