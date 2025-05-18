<?php
// Include the SMS functionality
include_once dirname(__FILE__) . '/../sms/smsback.php';

/**
 * Send SMS notification to customer when their booking is confirmed
 * 
 * @param int $booking_id The ID of the booking
 * @param object $conn Database connection object
 * @return bool Whether the SMS was sent successfully
 */
function sendBookingConfirmationSMS($booking_id, $conn) {
    // Get booking and customer information
    $sql = "SELECT b.booking_reference, b.package_name, b.event_date, b.event_start_time, u.name, u.phone 
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            WHERE b.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("No booking found with ID: $booking_id");
        return false;
    }
    
    $data = $result->fetch_assoc();
    
    // Check if phone number exists
    if (empty($data['phone'])) {
        error_log("Customer phone number is empty for booking ID: $booking_id");
        return false;
    }
    
    // Format the date and time
    $date = date('F j, Y', strtotime($data['event_date']));
    $time = date('h:i A', strtotime($data['event_start_time']));
    
    // Prepare the message
    $message = "Hello {$data['name']}, your booking ({$data['booking_reference']}) for {$data['package_name']} on {$date} at {$time} has been confirmed. Thank you for choosing The Barn & Backyard!";
    
    // Log attempt (for debugging)
    error_log("Attempting to send SMS to {$data['phone']} for booking {$data['booking_reference']}");
    
    // Store the original phone number before sending
    $originalPhone = $data['phone'];
    $formattedPhone = formatPhoneNumber($originalPhone);
    error_log("Original phone: {$originalPhone}, Formatted: {$formattedPhone}");
    
    try {
        // Send the SMS
        $response = sendSMS($originalPhone, $message);
        
        // Log raw response for debugging
        error_log("SMS API Final Response: " . $response);
        
        // Parse response
        $response_data = json_decode($response, true);
        
        // Log success/failure with detailed information
        if (isset($response_data['status']) && $response_data['status'] === 'success') {
            error_log("SMS sent successfully to {$formattedPhone}");
            
            // Try to log the SMS in the database (if admin_logs table has the right structure)
            try {
                // Check if admin_logs table has the right structure
                $check_table_sql = "SHOW COLUMNS FROM admin_logs LIKE 'action'";
                $check_result = $conn->query($check_table_sql);
                
                if ($check_result && $check_result->num_rows > 0) {
                    $log_sql = "INSERT INTO admin_logs (admin_id, action, details, created_at) 
                                VALUES (?, 'send_sms', ?, NOW())";
                    $admin_id = $_SESSION['user_id'];
                    $details = "SMS notification sent to {$data['phone']} for booking {$data['booking_reference']}";
                    
                    $log_stmt = $conn->prepare($log_sql);
                    $log_stmt->bind_param("is", $admin_id, $details);
                    $log_stmt->execute();
                }
            } catch (Exception $e) {
                // Silently fail on logging, as this is not critical
                error_log("Failed to log SMS in database: " . $e->getMessage());
            }
            
            return true;
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown API error';
            error_log("SMS sending failed. Error: {$error_message}");
            error_log("Full API response: " . json_encode($response_data));
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception while sending SMS: " . $e->getMessage());
        return false;
    }
}

/**
 * Send SMS notification to customer when their booking is cancelled
 * 
 * @param int $booking_id The ID of the booking
 * @param object $conn Database connection object
 * @return bool Whether the SMS was sent successfully
 */
function sendBookingCancellationSMS($booking_id, $conn) {
    // Get booking and customer information
    $sql = "SELECT b.booking_reference, b.package_name, b.event_date, b.event_start_time, u.name, u.phone 
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            WHERE b.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("No booking found with ID: $booking_id");
        return false;
    }
    
    $data = $result->fetch_assoc();
    
    // Check if phone number exists
    if (empty($data['phone'])) {
        error_log("Customer phone number is empty for booking ID: $booking_id");
        return false;
    }
    
    // Format the date and time
    $date = date('F j, Y', strtotime($data['event_date']));
    $time = date('h:i A', strtotime($data['event_start_time']));
    
    // Prepare the message
    $message = "Hello {$data['name']}, we regret to inform you that your booking ({$data['booking_reference']}) for {$data['package_name']} on {$date} at {$time} has been cancelled. Please contact us for more information.";
    
    // Log attempt (for debugging)
    error_log("Attempting to send cancellation SMS to {$data['phone']} for booking {$data['booking_reference']}");
    
    // Store the original phone number before sending
    $originalPhone = $data['phone'];
    $formattedPhone = formatPhoneNumber($originalPhone);
    error_log("Original phone: {$originalPhone}, Formatted: {$formattedPhone}");
    
    try {
        // Send the SMS
        $response = sendSMS($originalPhone, $message);
        
        // Log raw response for debugging
        error_log("Cancellation SMS API Response: " . $response);
        
        // Parse response
        $response_data = json_decode($response, true);
        
        // Log success/failure with detailed information
        if (isset($response_data['status']) && $response_data['status'] === 'success') {
            error_log("Cancellation SMS sent successfully to {$formattedPhone}");
            
            // Try to log the SMS in the database (if admin_logs table has the right structure)
            try {
                // Check if admin_logs table has the right structure
                $check_table_sql = "SHOW COLUMNS FROM admin_logs LIKE 'action'";
                $check_result = $conn->query($check_table_sql);
                
                if ($check_result && $check_result->num_rows > 0) {
                    $log_sql = "INSERT INTO admin_logs (admin_id, action, details, created_at) 
                                VALUES (?, 'send_cancellation_sms', ?, NOW())";
                    $admin_id = $_SESSION['user_id'];
                    $details = "Cancellation SMS notification sent to {$data['phone']} for booking {$data['booking_reference']}";
                    
                    $log_stmt = $conn->prepare($log_sql);
                    $log_stmt->bind_param("is", $admin_id, $details);
                    $log_stmt->execute();
                }
            } catch (Exception $e) {
                // Silently fail on logging, as this is not critical
                error_log("Failed to log cancellation SMS in database: " . $e->getMessage());
            }
            
            return true;
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown API error';
            error_log("Cancellation SMS sending failed. Error: {$error_message}");
            error_log("Full API response: " . json_encode($response_data));
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception while sending cancellation SMS: " . $e->getMessage());
        return false;
    }
}
?> 