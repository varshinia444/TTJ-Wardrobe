<?php
// 1. CORS Headers: Flutter Web/App connection-ku idhu romba mukkiyam
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

header("Content-Type: application/json");
include 'db_connect.php';

// 2. Query: Date-padi latest transactions mela varra maari order panniyachu
$sql = "SELECT id, trans_date, customer_name, type, points, details, status 
        FROM transactions 
        ORDER BY trans_date DESC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $data = array();
    while($row = $result->fetch_assoc()) {
        // Null values vandha error varama irukka handle panniyachu
        $data[] = array(
            "id" => $row['id'],
            "trans_date" => $row['trans_date'],
            "customer_name" => $row['customer_name'] ?? 'N/A',
            "type" => $row['type'] ?? 'Debit',
            "points" => $row['points'] ?? '0',
            "details" => $row['details'] ?? '',
            "status" => $row['status'] ?? 'Pending'
        );
    }
    // 3. Success Response: Browser-la paatha maari JSON format
    echo json_encode(["status" => "success", "data" => $data]);
} else {
    echo json_encode(["status" => "error", "message" => "No data found"]);
}

$conn->close();
?>