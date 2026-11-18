<?php
// admin_alerts_insert.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$u_id = $input['user_id'];
$amt = $input['amount'];
$grams = $input['grams'] ?? 0;
$type = $input['type']; // 'gold' or 'scheme'

// 1. Fetch User Name
$u_query = $conn->query("SELECT name FROM users WHERE id = '$u_id'");
$u_name = ($u_query->num_rows > 0) ? $u_query->fetch_assoc()['name'] : 'Unknown';

// 2. Map Action Type to match your CMS UI logic
if ($type == "gold") {
    $action = "Gold Purchased via App";
} else {
    $action = "New Scheme Enrollment";
}

// 3. Insert into admin_alerts table
$sql = "INSERT INTO admin_alerts (user_id, user_name, action_type, amount, target_weight, is_read, created_at) 
        VALUES (?, ?, ?, ?, ?, 0, NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("issdd", $u_id, $u_name, $action, $amt, $grams);

if($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error"]);
}
?>