<?php
header('Content-Type: application/json');
include('db_connect.php');

if(isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Fetching enrollment details along with user's mobile from users table
    $query = "SELECT e.*, u.mobile as user_mobile 
              FROM scheme_enrollments e 
              JOIN users u ON e.user_id = u.id 
              WHERE e.id = '$id'";
              
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        
        // Sending data in JSON format as expected by your schemes.php fetch()
        echo json_encode([
            'success' => true,
            'details' => $row
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No record found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>