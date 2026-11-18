<?php
// CORS Headers (must be first)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit();
}

include_once 'db_connect.php';

$rawInput = trim(file_get_contents("php://input"));

if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "No data received"]);
    exit();
}

$data = json_decode($rawInput, true); // Use associative array to avoid notices

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit();
}

// Required fields - safe access
$required = ['user_id', 'door_no', 'street', 'pin_code', 'nominee_name', 'city'];
foreach ($required as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing or empty: $field"]);
        exit();
    }
}

// Validate user_id is integer
$user_id = (int)$data['user_id'];
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid user_id"]);
    exit();
}

// Fetch name and email from users table
$user_stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
if ($user_stmt === false) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error"]);
    exit();
}

$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    $user_stmt->close();
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit();
}

$user_data = $user_result->fetch_assoc();
$name = $user_data['name'] ?? '';
$email_id = $user_data['email'] ?? '';
$user_stmt->close();

// Optional fields with defaults
$area = $data['area'] ?? '';
$state = $data['state'] ?? 'Tamil Nadu';
$nearest_showroom = $data['nearest_showroom'] ?? 'Peelamedu, Coimbatore';
$plan_id = isset($data['plan_id']) ? (int)$data['plan_id'] : 1; // Keep if you still want default

$start_date = date('Y-m-d');
$maturity_date = date('Y-m-d', strtotime('+330 days'));
$status = 'Active';

// Insert into scheme_enrollments
$query = "INSERT INTO scheme_enrollments 
          (user_id, name, email_id, plan_id, door_no, street, area, pin_code, city, state, nominee_name, nearest_showroom, start_date, maturity_date, status) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($query);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database prepare error: " . $conn->error]);
    exit();
}

$stmt->bind_param("ississsssssssss",
    $user_id,
    $name,
    $email_id,
    $plan_id,
    $data['door_no'],
    $data['street'],
    $area,
    $data['pin_code'],
    $data['city'],
    $state,
    $data['nominee_name'],
    $nearest_showroom,
    $start_date,
    $maturity_date,
    $status
);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode(["status" => "success", "message" => "Enrollment successful."]);
} else {
    if ($stmt->errno === 1062) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "You are already enrolled in a scheme."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Enrollment failed: " . $stmt->error]);
    }
}

$stmt->close();
$conn->close();
?>