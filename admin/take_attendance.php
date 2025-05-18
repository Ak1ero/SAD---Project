<?php
session_start();
// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');
include '../db/config.php';

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit();
}

// Ensure the request is POST and contains unique_code
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['unique_code']) || empty($_POST['unique_code'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid request. Unique code is required.']);
  exit();
}

$uniqueCode = $_POST['unique_code'];
$adminId = $_SESSION['user_id'];
$bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

// Validate booking ID if provided
if ($bookingId > 0) {
  $bookingCheckSql = "SELECT id, event_date, event_start_time, event_end_time FROM bookings WHERE id = ? 
                      AND status != 'cancelled'";
  $bookingStmt = $conn->prepare($bookingCheckSql);
  $bookingStmt->bind_param("i", $bookingId);
  $bookingStmt->execute();
  $bookingResult = $bookingStmt->get_result();
  
  if ($bookingResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
    exit();
  }
  
  // Check if event has started
  $bookingData = $bookingResult->fetch_assoc();
  $eventDate = $bookingData['event_date'];
  $eventStartTime = $bookingData['event_start_time'];
  $eventEndTime = $bookingData['event_end_time'];
  
  // Get current date and time
  $currentDate = date('Y-m-d');
  $currentTime = date('H:i:s');
  
  // Check if event has started
  $hasStarted = false;
  
  // Convert to timestamps for more reliable comparison
  $eventDateTimestamp = strtotime($eventDate);
  $currentDateTimestamp = strtotime($currentDate);
  $eventStartTimestamp = strtotime($eventDate . ' ' . $eventStartTime);
  $currentTimestamp = strtotime($currentDate . ' ' . $currentTime);
  
  if ($eventDateTimestamp < $currentDateTimestamp) {
    // Event date is in the past
    $hasStarted = true;
  } elseif ($eventDateTimestamp == $currentDateTimestamp && $eventStartTimestamp <= $currentTimestamp) {
    // Event is today and start time has passed or equals current time
    $hasStarted = true;
  }
  
  // Allow a 15-minute grace period before the event starts
  $graceStartTimestamp = strtotime($eventDate . ' ' . $eventStartTime . ' - 15 minutes');
  if ($eventDateTimestamp == $currentDateTimestamp && $currentTimestamp >= $graceStartTimestamp) {
    $hasStarted = true;
  }
  
  if (!$hasStarted) {
    http_response_code(403);
    echo json_encode([
      'success' => false, 
      'message' => 'Attendance cannot be taken yet. This event has not started.'
    ]);
    exit();
  }
  
  // Check if event has ended
  $hasEnded = false;
  $eventEndTimestamp = strtotime($eventDate . ' ' . $eventEndTime);
  
  if ($eventDateTimestamp < $currentDateTimestamp) {
    // Event date is in the past
    $hasEnded = true;
  } elseif ($eventDateTimestamp == $currentDateTimestamp && $eventEndTimestamp < $currentTimestamp) {
    // Event is today and end time has passed
    $hasEnded = true;
  }
  
  if ($hasEnded) {
    http_response_code(403);
    echo json_encode([
      'success' => false, 
      'message' => 'Attendance cannot be taken. This event has already ended.'
    ]);
    exit();
  }
}

// Find the guest with this unique code
$sql = "SELECT g.id, g.name, g.booking_id, b.package_name, b.event_date 
        FROM guests g 
        JOIN bookings b ON g.booking_id = b.id
        WHERE g.unique_code = ? 
        AND b.status != 'cancelled'";

if ($bookingId > 0) {
  $sql .= " AND g.booking_id = ?";
}

$stmt = $conn->prepare($sql);

if ($bookingId > 0) {
  $stmt->bind_param("si", $uniqueCode, $bookingId);
} else {
  $stmt->bind_param("s", $uniqueCode);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  // No guest found with this unique code
  http_response_code(404);
  echo json_encode(['success' => false, 'message' => 'Guest not found. Please check the unique code and try again.']);
  exit();
}

$guest = $result->fetch_assoc();

// Check if guest is already checked in
$checkSql = "SELECT id FROM guest_attendance WHERE guest_id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("i", $guest['id']);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
  // Guest is already checked in
  http_response_code(200);
  echo json_encode([
    'success' => true, 
    'message' => 'Guest already checked in',
    'guest' => [
      'id' => $guest['id'],
      'name' => $guest['name'],
      'booking_id' => $guest['booking_id'],
      'event_name' => $guest['package_name'],
      'event_date' => $guest['event_date'],
      'already_checked_in' => true
    ]
  ]);
  exit();
}

// Add guest attendance record
$insertSql = "INSERT INTO guest_attendance (guest_id, booking_id, checked_in_by) VALUES (?, ?, ?)";
$insertStmt = $conn->prepare($insertSql);
$insertStmt->bind_param("iii", $guest['id'], $guest['booking_id'], $adminId);
$success = $insertStmt->execute();

if ($success) {
  http_response_code(200);
  echo json_encode([
    'success' => true, 
    'message' => 'Guest check-in successful',
    'guest' => [
      'id' => $guest['id'],
      'name' => $guest['name'],
      'booking_id' => $guest['booking_id'],
      'event_name' => $guest['package_name'],
      'event_date' => $guest['event_date'],
      'already_checked_in' => false
    ]
  ]);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to update guest attendance. Please try again.']);
}
?> 