<?php
// -------------------- CORS HEADERS --------------------
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db_connect.php';

if (isset($_GET['type']) && $_GET['type'] === 'api') {

    $today = date('Y-m-d');

    // Fetch ACTIVE promotions within date range
    $sql = "
        SELECT campaign_name, image_path
        FROM promotions
        WHERE status = 'Active'
        AND CURDATE() BETWEEN start_date AND end_date
        ORDER BY start_date DESC
    ";

    $result = mysqli_query($conn, $sql);

    $data = [];

    while ($row = mysqli_fetch_assoc($result)) {

        // 🔹 CLEAN image path from DB (IMPORTANT)
        $imagePath = trim($row['image_path']);
        $imagePath = str_replace(["\r", "\n"], '', $imagePath);
        $imagePath = ltrim($imagePath, '/');

        // 🔹 Build FULL PUBLIC URL (images are NOT inside ttj_api)
        $row['full_image_url'] = "https://ttjnextgen.divasprik.in/" . $imagePath;

        $data[] = $row;
    }

    echo json_encode($data);
    exit();
}
?>
