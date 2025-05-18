<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

include '../db/config.php';
include 'sms_notifications.php'; // Include the SMS notification functions

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

// Check if all required parameters are provided
if (!isset($_POST['booking_id']) || !isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

$booking_id = $_POST['booking_id'];
$status = $_POST['status'];

// Validate status
$allowed_statuses = ['pending', 'confirmed', 'cancelled'];
if (!in_array($status, $allowed_statuses)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid status']);
    exit();
}

try {
    // Update booking status
    $sql = "UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $booking_id);
    $result = $stmt->execute();
    
    if ($result) {
        // Status message based on the update
        $message = '';
        $sms_sent = false;
        $sms_error = '';
        
        switch ($status) {
            case 'confirmed':
                $message = 'Booking has been confirmed successfully.';
                
                // Get customer phone info first
                $phone_sql = "SELECT u.phone FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ?";
                $phone_stmt = $conn->prepare($phone_sql);
                $phone_stmt->bind_param("i", $booking_id);
                $phone_stmt->execute();
                $phone_result = $phone_stmt->get_result();
                $customer_data = $phone_result->fetch_assoc();
                
                if ($customer_data && !empty($customer_data['phone'])) {
                    // Send SMS notification to customer
                    $sms_sent = sendBookingConfirmationSMS($booking_id, $conn);
                    
                    if ($sms_sent) {
                        $message .= ' SMS notification sent to customer.';
                    } else {
                        $sms_error = 'SMS could not be sent. Please verify your PhilSMS account configuration.';
                        $message .= ' However, there was an issue with SMS delivery.';
                    }
                } else {
                    $sms_error = 'Customer phone number not found or empty.';
                    $message .= ' However, SMS notification could not be sent (no phone number).';
                }
                break;
                
            case 'cancelled':
                $message = 'Booking has been cancelled.';
                
                // Get customer phone info first
                $phone_sql = "SELECT u.phone FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ?";
                $phone_stmt = $conn->prepare($phone_sql);
                $phone_stmt->bind_param("i", $booking_id);
                $phone_stmt->execute();
                $phone_result = $phone_stmt->get_result();
                $customer_data = $phone_result->fetch_assoc();
                
                if ($customer_data && !empty($customer_data['phone'])) {
                    // Send SMS notification to customer
                    $sms_sent = sendBookingCancellationSMS($booking_id, $conn);
                    
                    if ($sms_sent) {
                        $message .= ' SMS notification sent to customer.';
                    } else {
                        $sms_error = 'SMS could not be sent. Please verify your PhilSMS account configuration.';
                        $message .= ' However, there was an issue with SMS delivery.';
                    }
                } else {
                    $sms_error = 'Customer phone number not found or empty.';
                    $message .= ' However, SMS notification could not be sent (no phone number).';
                }
                break;
                
            default:
                $message = 'Booking status has been updated.';
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'sms_sent' => $sms_sent,
            'sms_error' => $sms_error
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update booking status.'
        ]);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error updating booking status: ' . $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
?>