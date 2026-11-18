<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);
$user_id = $data['user_id'];

// 1. Check Address & Nominee (scheme_enrollments)
$enroll_sql = "SELECT id, bank_account_number, target_amount, total_amount_paid FROM scheme_enrollments WHERE user_id = ?";
$stmt = $conn->prepare($enroll_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$enroll_result = $stmt->get_result()->fetch_assoc();

// 2. Check KYC Files (users table)
$user_sql = "SELECT aadhar_file, pancard_file, profile_photo FROM users WHERE id = ?";
$stmt2 = $conn->prepare($user_sql);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$user_files = $stmt2->get_result()->fetch_assoc();

$step = "new";

if (!$enroll_result) {
    $step = "new"; // Flow Start: scheme_joining.dart
} else if (empty($user_files['aadhar_file'])) {
    $step = "scheme_done"; // Next: aadhar_upload.dart
} else if (empty($user_files['pancard_file'])) {
    $step = "aadhar_done"; // Next: pancard_upload.dart
} else if (empty($enroll_result['bank_account_number'])) {
    $step = "pan_done"; // Next: bank_details.dart
} else if (empty($user_files['profile_photo'])) {
    $step = "bank_done"; // Next: user_photo.dart
} else if ($enroll_result['target_amount'] <= 0) {
    $step = "photo_done"; // Next: kyc.dart -> joinscheme.dart (Set Target)
} else if ($enroll_result['total_amount_paid'] <= 0) {
    $step = "kyc_done"; // Next: joinscheme.dart (Initial Payment)
} else {
    $step = "enrolled"; // Flow Complete: Only "Pay Now" active
}

echo json_encode(["status" => "success", "step_status" => $step]);
?>