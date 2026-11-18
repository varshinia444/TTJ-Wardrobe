<?php
include('db_connect.php');
header("Content-Type: application/json");

$user_id = isset($_GET['user_id']) ? mysqli_real_escape_string($conn, $_GET['user_id']) : null;

if ($user_id) {
    // Fetches every ledger entry including Scheme Payments and Gold Wallet buys
    $query = "SELECT id, created_at, transaction_type, order_amount, description 
              FROM loyalty_ledger 
              WHERE user_id = '$user_id' 
              ORDER BY created_at DESC";
    
    $result = mysqli_query($conn, $query);
    $history = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $history[] = [
            "date" => date("M d, Y h:i A", strtotime($row['created_at'])),
            "type" => ($row['transaction_type'] == 'EARN') ? 'Credit' : 'Debit',
            "amount" => "₹" . number_format($row['order_amount'], 2),
            "reason" => $row['description']
        ];
    }

    echo json_encode(["status" => "success", "data" => $history]);
} else {
    echo json_encode(["status" => "error", "message" => "User ID required"]);
}
?>