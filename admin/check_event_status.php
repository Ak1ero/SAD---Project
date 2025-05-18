<?php
session_start();
include '../db/config.php';

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit();
}

// Check if booking_id is provided
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid booking ID', 'is_started' => false, 'is_ended' => false]);
  exit();
}

$bookingId = intval($_GET['booking_id']);

// Get the event details
$sql = "SELECT event_date, event_start_time, event_end_time, status 
        FROM bookings 
        WHERE id = ? 
        AND status != 'cancelled'
        AND (payment_status = 'paid' OR payment_status = 'partially_paid')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bookingId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  http_response_code(404);
  echo json_encode(['success' => false, 'message' => 'Booking not found', 'is_started' => false, 'is_ended' => false]);
  exit();
}

$event = $result->fetch_assoc();

// Get current date and time
$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');

// Convert event date and times for comparison
$eventDate = $event['event_date'];
$eventStartTime = $event['event_start_time'];
$eventEndTime = $event['event_end_time'];

// Format for display
$formattedDate = date('F j, Y', strtotime($eventDate));
$formattedStartTime = date('g:i A', strtotime($eventStartTime));
$formattedEndTime = date('g:i A', strtotime($eventEndTime));

// Check if event has started
$hasStarted = false;

if ($eventDate < $currentDate) {
  // Event date is in the past
  $hasStarted = true;
} elseif ($eventDate == $currentDate && $eventStartTime <= $currentTime) {
  // Event is today and start time has passed
  $hasStarted = true;
}

// Allow a 15-minute grace period before the event starts
$graceStartTime = date('H:i:s', strtotime($eventStartTime . ' - 15 minutes'));
if ($eventDate == $currentDate && $currentTime >= $graceStartTime && $currentTime <= $eventEndTime) {
  $hasStarted = true;
}

// Check if event has ended
$hasEnded = false;

if ($eventDate < $currentDate) {
  // Event date is in the past
  $hasEnded = true;
} elseif ($eventDate == $currentDate && $eventEndTime < $currentTime) {
  // Event is today and end time has passed
  $hasEnded = true;
}

// If status is 'completed', also consider the event as ended
if ($event['status'] === 'completed') {
  $hasEnded = true;
}

// Return the result
http_response_code(200);
echo json_encode([
  'success' => true,
  'is_started' => $hasStarted,
  'is_ended' => $hasEnded,
  'event_date' => $formattedDate,
  'start_time' => $formattedStartTime,
  'end_time' => $formattedEndTime,
  'current_date' => $currentDate,
  'current_time' => $currentTime
]);
?> 