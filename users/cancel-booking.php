<?php
session_start();
include '../db/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    $booking_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    // Verify the booking belongs to the user
    $sql = "UPDATE bookings SET status = 'cancelled' 
            WHERE id = ? AND user_id = ? AND status = 'pending'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $booking_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel booking']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>