<?php
include('db_connect.php');

// Security check: ensure an ID is provided
if(isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Update the transaction to set it as acknowledged by admin
    $query = "UPDATE transactions SET admin_acknowledged = 1 WHERE id = $id";
    
    if(mysqli_query($conn, $query)) {
        // Redirect back to the admin portal with a success message
        header("Location: goldsavings.php?msg=Transaction acknowledged");
    } else {
        header("Location: goldsavings.php?error=Failed to update status");
    }
} else {
    header("Location: goldsavings.php");
}
exit();
?>