<?php
session_start();
include '../db/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please login to make a booking']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // Get user ID from session
    $user_id = $_SESSION['user_id'];

    // Generate unique booking reference
    $booking_reference = 'EP' . date('Ymd') . rand(1000, 9999);

    // Get and validate form data
    $event_date = $_POST['event_date'] ?? '';
    $event_start_time = $_POST['event_start_time'] ?? '';
    $event_end_time = $_POST['event_end_time'] ?? '';
    $package_name = $_POST['package_name'] ?? '';
    $theme_name = $_POST['theme_name'] ?? '';
    $total_amount = $_POST['total_amount'] ?? 0;
    $special_requests = $_POST['special_requests'] ?? '';
    $guest_list = json_decode($_POST['guest_list'] ?? '[]', true);
    $selected_services = json_decode($_POST['selected_services'] ?? '[]', true);

    // Validate required fields
    if (empty($event_date) || empty($event_start_time) || empty($event_end_time) || 
        empty($package_name) || empty($theme_name)) {
        throw new Exception('Please fill in all required fields');
    }

    // Start transaction
    $conn->begin_transaction();

    // Insert into bookings table
    $sql = "INSERT INTO bookings (
                user_id, 
                booking_reference, 
                event_date, 
                event_start_time, 
                event_end_time, 
                package_name, 
                theme_name, 
                total_amount, 
                special_requests, 
                status, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssds", 
        $user_id, 
        $booking_reference, 
        $event_date,
        $event_start_time,
        $event_end_time,
        $package_name, 
        $theme_name, 
        $total_amount, 
        $special_requests
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to save booking details');
    }

    $booking_id = $conn->insert_id;

    // Insert guest list
    if (!empty($guest_list)) {
        $guest_sql = "INSERT INTO guests (
            booking_id, 
            name, 
            phone
        ) VALUES (?, ?, ?)";
        
        $guest_stmt = $conn->prepare($guest_sql);
        
        foreach ($guest_list as $guest) {
            $guest_stmt->bind_param("iss", 
                $booking_id, 
                $guest['name'], 
                $guest['phone']
            );
            
            if (!$guest_stmt->execute()) {
                throw new Exception('Failed to save guest details');
            }
        }
        $guest_stmt->close();
    }

    // Insert selected services
    if (!empty($selected_services)) {
        $service_sql = "INSERT INTO booking_services (
            booking_id, 
            service_name, 
            service_price
        ) VALUES (?, ?, ?)";
        
        $service_stmt = $conn->prepare($service_sql);
        
        foreach ($selected_services as $service) {
            $service_stmt->bind_param("isd", 
                $booking_id, 
                $service['name'], 
                $service['price']
            );
            
            if (!$service_stmt->execute()) {
                throw new Exception('Failed to save service details');
            }
        }
        $service_stmt->close();
    }

    // Create initial payment transaction record
    $payment_sql = "INSERT INTO payment_transactions (
        booking_id,
        amount,
        status,
        created_at
    ) VALUES (?, ?, 'unpaid', NOW())";

    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("id", $booking_id, $total_amount);

    if (!$payment_stmt->execute()) {
        throw new Exception('Failed to create payment record');
    }

    // Commit transaction
    $conn->commit();

    // Set success message in session
    $_SESSION['booking_success'] = true;
    $_SESSION['booking_reference'] = $booking_reference;

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully',
        'booking_reference' => $booking_reference,
        'redirect_url' => 'booking-success.php'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->connect_errno) {
        $conn->rollback();
    }

    // Log the error
    error_log("Booking Error: " . $e->getMessage());

    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while saving the booking: ' . $e->getMessage()
    ]);
}

// Close database connection
$stmt->close();
$conn->close();
?>