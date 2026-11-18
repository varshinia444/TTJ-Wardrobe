<?php
// Include the database connection
include('db_connect.php');

if (isset($_POST['save_plan'])) {
    // 1. Capture and sanitize form inputs to prevent SQL injection
    $plan_name = mysqli_real_escape_string($conn, $_POST['plan_name']);
    $duration = mysqli_real_escape_string($conn, $_POST['duration']);
    $benefit = mysqli_real_escape_string($conn, $_POST['benefit']);
    $penalty = mysqli_real_escape_string($conn, $_POST['penalty']);
    $border_color = mysqli_real_escape_string($conn, $_POST['border_color']);
    
    // Default status for new plans is 'Active'
    $status = 'Active';

    // 2. Prepare the SQL INSERT statement
    $sql = "INSERT INTO scheme_plans (plan_name, duration, benefit, penalty, status, border_color) 
            VALUES ('$plan_name', '$duration', '$benefit', '$penalty', '$status', '$border_color')";

    // 3. Execute the query and redirect with a status message
    if (mysqli_query($conn, $sql)) {
        // Redirect back to the schemes management page with a success message
        header("Location: schemes.php?msg=New plan created successfully!");
        exit();
    } else {
        // Handle database errors
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }
} else {
    // If the file is accessed directly without a form submission, redirect back
    header("Location: schemes.php");
    exit();
}
?>