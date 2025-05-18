<?php
// Script to update event status to 'completed' when event time has ended

include_once 'config.php';

function updateCompletedEventStatus() {
    global $conn;
    
    // Set timezone to ensure proper date/time comparison
    date_default_timezone_set('Asia/Manila'); // Change this to your local timezone
    
    // Get current date and time in the correct format
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    // First, get a list of bookings that need to be updated
    // Events that are either confirmed OR have payment_status='paid' and have ended
    $findSql = "SELECT b.id 
                FROM bookings b
                WHERE (b.status = 'confirmed' OR b.payment_status = 'paid')
                AND b.status != 'cancelled'
                AND b.status != 'completed'
                AND (
                    (b.event_date < ?) 
                    OR 
                    (b.event_date = ? AND b.event_end_time <= ?)
                )";
                
    $findStmt = $conn->prepare($findSql);
    $findStmt->bind_param("sss", $currentDate, $currentDate, $currentTime);
    $findStmt->execute();
    $result = $findStmt->get_result();
    
    $updatedCount = 0;
    $bookingIds = [];
    
    while ($row = $result->fetch_assoc()) {
        $bookingIds[] = $row['id'];
    }
    
    if (!empty($bookingIds)) {
        // Update bookings table - change status to 'completed'
        $updateBookings = "UPDATE bookings 
                           SET status = 'completed',
                           updated_at = NOW() 
                           WHERE id IN (" . implode(',', $bookingIds) . ")";
        $conn->query($updateBookings);
        $updatedCount = $conn->affected_rows;
    }
    
    // Return the number of rows updated
    return $updatedCount;
}

// When this file is accessed directly, run the function and output results
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $updatedCount = updateCompletedEventStatus();
    echo "Updated $updatedCount events to 'completed' status.";
} 

// Function to debug current event status checking
function checkEventsStatus() {
    global $conn;
    
    // Set timezone to ensure proper date/time comparison
    date_default_timezone_set('Asia/Manila'); // Change this to your local timezone
    
    // Get current date and time
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    echo "Current Date: $currentDate<br>";
    echo "Current Time: $currentTime<br>";
    
    // Check the status of events
    $sql = "SELECT b.id, b.booking_reference, b.event_date, b.event_start_time, b.event_end_time, 
            b.status as booking_status, b.payment_status,
            CONCAT(b.event_date, ' ', b.event_end_time) as end_datetime,
            CASE 
                WHEN b.status = 'pending' THEN 'Pending'
                WHEN b.status = 'confirmed' AND b.payment_status = 'partially_paid' THEN 'Partially Paid'
                WHEN b.status = 'confirmed' AND b.payment_status != 'paid' THEN 'Confirmed'
                WHEN (b.status = 'confirmed' OR b.status = 'pending') AND b.payment_status = 'paid' THEN 'Paid'
                WHEN b.event_date < '$currentDate' OR (b.event_date = '$currentDate' AND b.event_end_time <= '$currentTime') THEN 'Should be Completed'
                ELSE 'Active event'
            END as display_status,
            CASE 
                WHEN b.status = 'cancelled' THEN 'N/A (cancelled)'
                WHEN b.event_date < '$currentDate' OR (b.event_date = '$currentDate' AND b.event_end_time <= '$currentTime') THEN 'Should be Completed'
                ELSE 'Not yet ended'
            END as status_check
            FROM bookings b
            WHERE b.status IN ('confirmed', 'completed', 'pending', 'cancelled')
            ORDER BY b.event_date DESC, b.event_end_time DESC";
    
    $result = $conn->query($sql);
    
    echo "<h3>Event Status Check</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Reference</th><th>Event Date</th><th>Start Time</th><th>End Time</th><th>Booking Status</th><th>Payment Status</th><th>Displayed Status</th><th>Should Be</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['booking_reference']}</td>";
        echo "<td>{$row['event_date']}</td>";
        echo "<td>{$row['event_start_time']}</td>";
        echo "<td>{$row['event_end_time']}</td>";
        echo "<td>{$row['booking_status']}</td>";
        echo "<td>{$row['payment_status']}</td>";
        echo "<td>{$row['display_status']}</td>";
        echo "<td>{$row['status_check']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Uncomment to check event status with debug info
// checkEventsStatus(); 