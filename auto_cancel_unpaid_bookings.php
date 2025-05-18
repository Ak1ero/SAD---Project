<?php
// This script checks for confirmed bookings that haven't been paid within 1 hour and cancels them

// Include database configuration
include 'db/config.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Log function for debugging
function logAction($message) {
    $logFile = 'auto_cancel_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

logAction("Starting auto-cancel unpaid bookings script");

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Find confirmed bookings with payment_status not 'paid' and updated more than 1 hour ago
    $cutoff_time = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    $sql = "SELECT id, booking_reference, user_id FROM bookings 
            WHERE status = 'confirmed' 
            AND payment_status != 'paid' 
            AND updated_at < ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cutoff_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cancelled_count = 0;
    
    // Process each booking to cancel
    while ($booking = $result->fetch_assoc()) {
        $booking_id = $booking['id'];
        $booking_reference = $booking['booking_reference'];
        $user_id = $booking['user_id'];
        
        // Update the booking status to 'cancelled'
        $update_sql = "UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $booking_id);
        $update_stmt->execute();
        
        // Log cancellation
        logAction("Cancelled booking #$booking_id (Ref: $booking_reference) for user #$user_id due to unpaid after 1 hour");
        
        // Optional: Send notification to user about cancellation
        // This would require integrating with your notification system
        
        $cancelled_count++;
    }
    
    // Commit transaction
    $conn->commit();
    
    logAction("Completed auto-cancel job: $cancelled_count bookings cancelled");
    
} catch (Exception $e) {
    // Rollback on error
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    logAction("Error in auto-cancel job: " . $e->getMessage());
}

// Close connection
$conn->close(); 