<?php
// Turn off error reporting at the beginning
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();
include '../db/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Validate required fields
    if (!isset($_POST['booking_id']) || !isset($_POST['payment_method']) || !isset($_POST['total_amount'])) {
        throw new Exception('Missing required fields');
    }

    $booking_id = $_POST['booking_id'];
    $payment_method = $_POST['payment_method'];
    $amount = $_POST['total_amount'];
    $payment_type = $_POST['payment_type'] ?? 'full'; // Default to full payment if not specified
    $user_id = $_SESSION['user_id'];

    // Verify booking belongs to user
    $check_sql = "SELECT id FROM bookings WHERE id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Invalid booking');
    }

    // Handle file upload if receipt is provided
    $receipt_path = null;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/receipts/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
        $receipt_path = $upload_dir . uniqid('receipt_') . '.' . $file_extension;
        
        if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $receipt_path)) {
            throw new Exception('Failed to upload receipt');
        }
    }

    // Determine payment status based on payment type
    $payment_status = ($payment_type === 'full') ? 'paid' : 'partially_paid';
    $account_name = $_POST['account_name'] ?? null;
    $phone_number = $_POST['phone_number'] ?? null;

    // Check if payment record already exists
    $check_payment_sql = "SELECT id FROM payment_transactions WHERE booking_id = ?";
    $check_payment_stmt = $conn->prepare($check_payment_sql);
    $check_payment_stmt->bind_param("i", $booking_id);
    $check_payment_stmt->execute();
    $payment_result = $check_payment_stmt->get_result();

    if ($payment_result->num_rows > 0) {
        // Update existing payment record
        $update_payment_sql = "UPDATE payment_transactions SET 
                             payment_method = ?, 
                             status = 'paid',
                             amount = ?,
                             account_name = COALESCE(?, account_name),
                             phone_number = COALESCE(?, phone_number),
                             receipt_path = COALESCE(?, receipt_path),
                             updated_at = CURRENT_TIMESTAMP 
                             WHERE booking_id = ?";
        $update_payment_stmt = $conn->prepare($update_payment_sql);
        $update_payment_stmt->bind_param("sdsssi", $payment_method, $amount, $account_name, $phone_number, $receipt_path, $booking_id);
        $update_payment_stmt->execute();
    } else {
        // Insert new payment record
        $payment_sql = "INSERT INTO payment_transactions (booking_id, amount, payment_method, account_name, phone_number, status, receipt_path) 
                       VALUES (?, ?, ?, ?, ?, 'paid', ?)";
        $payment_stmt = $conn->prepare($payment_sql);
        $payment_stmt->bind_param("idssss", $booking_id, $amount, $payment_method, $account_name, $phone_number, $receipt_path);
        $payment_stmt->execute();
    }

    // Update booking payment status
    $update_booking_sql = "UPDATE bookings SET 
                          payment_status = ?, 
                          status = 'confirmed',
                          updated_at = CURRENT_TIMESTAMP 
                          WHERE id = ?";
    $update_booking_stmt = $conn->prepare($update_booking_sql);
    $update_booking_stmt->bind_param("si", $payment_status, $booking_id);
    $update_booking_stmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>