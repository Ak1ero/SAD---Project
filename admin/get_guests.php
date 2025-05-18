<?php
session_start();
include '../db/config.php';

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit();
}

// Check if booking_id is provided
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid booking ID']);
  exit();
}

$bookingId = intval($_GET['booking_id']);

// Verify the booking exists and is not cancelled
$bookingCheckSql = "SELECT id FROM bookings WHERE id = ? AND status != 'cancelled' 
                    AND (payment_status = 'paid' OR payment_status = 'partially_paid')";
$checkStmt = $conn->prepare($bookingCheckSql);
$checkStmt->bind_param("i", $bookingId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
  http_response_code(404);
  echo json_encode(['error' => 'Reservation not found or cancelled']);
  exit();
}

// Fetch guests for the specified booking with their attendance status
$guests = [];
$sql = "SELECT g.id, g.booking_id, g.name, g.email, g.phone, g.unique_code,
        CASE WHEN ga.id IS NOT NULL THEN 1 ELSE 0 END as is_checked_in,
        ga.check_in_time
        FROM guests g 
        LEFT JOIN guest_attendance ga ON g.id = ga.guest_id
        WHERE g.booking_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bookingId);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
  while ($row = $result->fetch_assoc()) {
    $guests[] = $row;
  }
}

// Return guest data as JSON
header('Content-Type: application/json');
echo json_encode($guests);
?>