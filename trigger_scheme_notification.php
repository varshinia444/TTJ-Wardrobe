<?php
include 'cors_header.php';
// MUST BE THE VERY FIRST LINES
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['amount']) || !isset($input['grams'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$user_id = mysqli_real_escape_string($conn, $input['user_id']);
$amount = mysqli_real_escape_string($conn, $input['amount']);
$target_grams = mysqli_real_escape_string($conn, $input['grams']);

// Fetch User Name
$user_res = $conn->query("SELECT name FROM users WHERE id = '$user_id'");
$user_name = ($user_res->num_rows > 0) ? $user_res->fetch_assoc()['name'] : 'Unknown User';

$action_type = "New Scheme Enrollment";

// Insert with is_read = 0 to trigger the red dot in CMS
$sql = "INSERT INTO admin_alerts (user_id, user_name, action_type, amount, target_weight, pending_grams, is_read, created_at) 
        VALUES ('$user_id', '$user_name', '$action_type', '$amount', '$target_grams', '$target_grams', 0, NOW())";

if($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Scheme alert created"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
}
?>