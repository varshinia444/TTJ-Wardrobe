<?php
// CORS Headers - MUST be before any output
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start output buffering to catch accidental whitespace/warnings
ob_start();

include_once 'db_connect.php';

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput);

if (!isset($data->user_id) || empty($data->user_id)) {
    // Clear buffer before sending error JSON
    ob_clean();
    echo json_encode(["enrolled" => false, "status" => "error", "message" => "User ID required"]);
    exit();
}

$user_id = (int)$data->user_id;

// Check for existing record and its status
$query = "SELECT status, payment_status FROM scheme_enrollments WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Prepare response data
$response = [];

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // CASE 1: Completed & Active (Show already enrolled message)
    if ($row['status'] == 'Active' && $row['payment_status'] == 'Completed') {
        $response = [
            "enrolled" => true, 
            "status" => "already_enrolled"
        ];
    } 
    // CASE 2: Incomplete (Redirect to Payment/Join Scheme)
    else {
        $response = [
            "enrolled" => false, 
            "status" => "incomplete"
        ];
    }
} else {
    // CASE 3: No record found (Redirect to Start)
    $response = [
        "enrolled" => false, 
        "status" => "new_user"
    ];
}

$stmt->close();
$conn->close();

// FINAL OUTPUT: Clear any accidental characters (like position 20 error) and send clean JSON
ob_clean();
echo json_encode($response);
exit();
?>