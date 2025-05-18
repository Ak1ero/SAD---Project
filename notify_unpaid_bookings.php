<?php
// This script sends warning notifications to users with unpaid bookings approaching the 1-hour deadline

// Include database configuration
include 'db/config.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Log function for debugging
function logAction($message) {
    $logFile = 'notification_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

logAction("Starting notification for unpaid bookings");

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Find confirmed bookings with payment_status not 'paid' that are between 30-45 minutes old
    // This gives users 15-30 minutes notice before the auto-cancellation occurs
    $early_cutoff = date('Y-m-d H:i:s', strtotime('-30 minutes'));
    $late_cutoff = date('Y-m-d H:i:s', strtotime('-45 minutes'));
    
    $sql = "SELECT b.id, b.booking_reference, b.updated_at, b.event_date, b.package_name, 
                  u.name as user_name, u.email as user_email
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            WHERE b.status = 'confirmed' 
            AND b.payment_status != 'paid' 
            AND b.updated_at BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $late_cutoff, $early_cutoff);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notification_count = 0;
    
    // Process each booking to notify
    while ($booking = $result->fetch_assoc()) {
        $booking_id = $booking['id'];
        $booking_reference = $booking['booking_reference'];
        $user_name = $booking['user_name'];
        $user_email = $booking['user_email'];
        $event_date = date('F d, Y', strtotime($booking['event_date']));
        $package_name = $booking['package_name'];
        $updated_at = $booking['updated_at'];
        
        // Calculate time remaining before cancellation (in minutes)
        $confirmation_time = strtotime($updated_at);
        $deadline_time = $confirmation_time + (60 * 60); // 1 hour in seconds
        $current_time = time();
        $minutes_remaining = round(($deadline_time - $current_time) / 60);
        
        // Send email notification
        $to = $user_email;
        $subject = "URGENT: Your Booking #{$booking_reference} Requires Payment";
        
        $message = "
        <html>
        <head>
            <title>Payment Reminder</title>
        </head>
        <body>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;'>
                <div style='background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin-bottom: 20px;'>
                    <strong>URGENT:</strong> Your booking will be automatically cancelled in approximately {$minutes_remaining} minutes if payment is not completed.
                </div>
                
                <p>Dear {$user_name},</p>
                
                <p>We noticed that you haven't completed the payment for your booking:</p>
                
                <div style='background-color: #f5f5f5; padding: 15px; margin: 15px 0;'>
                    <p><strong>Booking Reference:</strong> {$booking_reference}</p>
                    <p><strong>Package:</strong> {$package_name}</p>
                    <p><strong>Event Date:</strong> {$event_date}</p>
                </div>
                
                <p>To prevent automatic cancellation, please complete your payment by visiting:</p>
                
                <p style='text-align: center;'>
                    <a href='http://yourdomain.com/users/payment.php?booking_id={$booking_id}' 
                       style='display: inline-block; background-color: #28a745; color: white; padding: 10px 20px; 
                              text-decoration: none; border-radius: 5px; font-weight: bold;'>
                        Complete Payment Now
                    </a>
                </p>
                
                <p>If you no longer wish to proceed with this booking, no action is required, and the booking will be cancelled automatically.</p>
                
                <p>Thank you for choosing our services.</p>
                
                <p>Best regards,<br>Event Planning Team</p>
            </div>
        </body>
        </html>
        ";
        
        // To send HTML mail, the Content-type header must be set
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: Event Planning <noreply@yourdomain.com>' . "\r\n";
        
        // Send the email
        $mail_sent = mail($to, $subject, $message, $headers);
        
        if ($mail_sent) {
            logAction("Payment reminder sent to {$user_email} for booking #{$booking_reference} ({$minutes_remaining} minutes remaining)");
            $notification_count++;
        } else {
            logAction("Failed to send payment reminder to {$user_email} for booking #{$booking_reference}");
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    logAction("Completed notification job: {$notification_count} reminders sent");
    
} catch (Exception $e) {
    // Rollback on error
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    logAction("Error in notification job: " . $e->getMessage());
}

// Close connection
$conn->close(); 