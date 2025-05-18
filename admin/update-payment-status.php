<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include '../db/config.php';

// Check if required parameters are present
if (!isset($_POST['booking_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$booking_id = $conn->real_escape_string($_POST['booking_id']);
$status = $conn->real_escape_string($_POST['status']);

// Validate status
if ($status !== 'paid') {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Update payment status
    $update_sql = "UPDATE bookings SET payment_status = 'paid', updated_at = NOW() WHERE id = '$booking_id'";
    
    if (!$conn->query($update_sql)) {
        throw new Exception("Failed to update payment status");
    }

    // Insert payment transaction record
    $transaction_sql = "INSERT INTO payment_transactions (booking_id, amount, payment_method, status, created_at) 
                       SELECT id, total_amount, 'cash', 'completed', NOW() 
                       FROM bookings 
                       WHERE id = '$booking_id'";
    
    if (!$conn->query($transaction_sql)) {
        throw new Exception("Failed to create payment transaction");
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Payment status updated successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?> 