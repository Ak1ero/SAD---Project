<?php
// This script checks for confirmed bookings that haven't been paid within 1 hour and cancels them

// Script execution start time
$start_time = microtime(true);

// Log function for debugging
function logAction($message) {
    $logFile = 'auto_cancel_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    
    // Only echo if running in browser
    if (php_sapi_name() !== 'cli') {
        echo "[$timestamp] $message<br>\n";
    }
}

logAction("==== AUTO-CANCEL EXECUTION STARTED ====");
logAction("Script running as: " . php_sapi_name());

try {
    // Include database configuration
    require_once __DIR__ . '/db/config.php';
    
    // Set timezone
    date_default_timezone_set('Asia/Manila');
    logAction("Timezone set to Asia/Manila");
    
    logAction("Current time: " . date('Y-m-d H:i:s'));
    
    // Test database connection
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn ? $conn->connect_error : "No connection"));
    }
    
    logAction("Database connection successful");
    
    // Start transaction
    $conn->begin_transaction();
    
    // Find confirmed bookings with payment_status not 'paid' and updated more than 1 hour ago
    $cutoff_time = date('Y-m-d H:i:s', strtotime('-1 hour'));
    logAction("Looking for bookings confirmed before: $cutoff_time");
    
    $sql = "SELECT id, booking_reference, user_id, updated_at FROM bookings 
            WHERE status = 'confirmed' 
            AND payment_status NOT IN ('paid') 
            AND updated_at < ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cutoff_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cancelled_count = 0;
    $found_count = $result->num_rows;
    
    logAction("Found $found_count bookings that meet cancellation criteria");
    
    // Process each booking to cancel
    while ($booking = $result->fetch_assoc()) {
        $booking_id = $booking['id'];
        $booking_reference = $booking['booking_reference'];
        $user_id = $booking['user_id'];
        $updated_at = $booking['updated_at'];
        
        $time_diff = strtotime('now') - strtotime($updated_at);
        $minutes = floor($time_diff / 60);
        
        logAction("Booking #$booking_id (Ref: $booking_reference) was last updated $minutes minutes ago");
        
        // Update the booking status to 'cancelled'
        $update_sql = "UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $booking_id);
        $update_stmt->execute();
        
        // Verify the update worked
        if ($update_stmt->affected_rows > 0) {
            // Log cancellation
            logAction("SUCCESS: Cancelled booking #$booking_id (Ref: $booking_reference) for user #$user_id due to unpaid after 1 hour");
            $cancelled_count++;
        } else {
            logAction("WARNING: Failed to cancel booking #$booking_id - no rows affected by update query");
        }
    }
    
    // If no bookings were found, check if there are any potential candidates
    if ($found_count == 0) {
        $potential_sql = "SELECT id, booking_reference, updated_at, payment_status, status, 
                          TIMESTAMPDIFF(MINUTE, updated_at, NOW()) as minutes_since_update
                          FROM bookings 
                          WHERE status = 'confirmed' 
                          AND payment_status NOT IN ('paid')
                          ORDER BY updated_at DESC
                          LIMIT 5";
        
        $potential_result = $conn->query($potential_sql);
        
        if ($potential_result && $potential_result->num_rows > 0) {
            logAction("Found some potential bookings that might be cancelled soon:");
            
            while ($row = $potential_result->fetch_assoc()) {
                $minutes_left = 60 - $row['minutes_since_update'];
                
                if ($minutes_left > 0) {
                    logAction("Booking #{$row['id']} (Ref: {$row['booking_reference']}) with status '{$row['status']}', payment_status '{$row['payment_status']}', updated {$row['minutes_since_update']} minutes ago, will be eligible for cancellation in approximately $minutes_left minutes");
                } else {
                    logAction("WARNING: Booking #{$row['id']} (Ref: {$row['booking_reference']}) with status '{$row['status']}', payment_status '{$row['payment_status']}', updated {$row['minutes_since_update']} minutes ago, SHOULD have been cancelled already but wasn't");
                }
            }
        } else {
            logAction("No potential bookings found that might be cancelled soon");
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    logAction("Completed auto-cancel job: $cancelled_count bookings cancelled");
    
} catch (Exception $e) {
    logAction("ERROR: " . $e->getMessage());
    logAction("Error stack trace: " . $e->getTraceAsString());
    
    // Rollback on error if connection is available
    if (isset($conn) && is_object($conn) && $conn->connect_errno === 0) {
        try {
            $conn->rollback();
            logAction("Transaction rolled back due to error");
        } catch (Exception $rollbackError) {
            logAction("Failed to rollback transaction: " . $rollbackError->getMessage());
        }
    }
}

// Close connection if it exists
if (isset($conn) && is_object($conn)) {
    try {
        $conn->close();
        logAction("Database connection closed");
    } catch (Exception $closeError) {
        logAction("Error closing database connection: " . $closeError->getMessage());
    }
}

// Calculate execution time
$execution_time = microtime(true) - $start_time;
logAction("Script execution completed in " . number_format($execution_time, 4) . " seconds");
logAction("==== AUTO-CANCEL EXECUTION FINISHED ====\n");

// If called from web, display a link to return
if (php_sapi_name() !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
    echo '<p><a href="index.php">Return to Homepage</a></p>';
} 