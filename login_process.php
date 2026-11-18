<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'db_connect.php'; 

    if ($conn->connect_error) {
        header("Location: index.php?error=server_busy");
        exit;
    }

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $query = "SELECT * FROM admins WHERE username = '$username' AND password = '$password' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) === 1) {
        $_SESSION['admin_logged_in'] = true; 
        $_SESSION['admin_user'] = $username;
        
        // Initialize the activity timer for 2 hours
        $_SESSION['last_activity'] = time(); 
        
        mysqli_close($conn); 
        header("Location: dashboard.php");
        exit;
    } else {
        mysqli_close($conn); 
        header("Location: index.php?error=1");
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>