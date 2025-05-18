<?php
// Suppress warnings and notices that might interfere with JSON output
error_reporting(0);
session_start();

include '../db/config.php';

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Booking ID is required']);
    exit();
}

$booking_id = $_GET['id'];

// Prepare the SQL query to get booking details
$sql = "SELECT 
        b.*,
        u.name as customer_name,
        u.email as customer_email,
        u.phone as customer_phone,
        (SELECT COUNT(*) FROM guests bg WHERE bg.booking_id = b.id) as guest_count,
        COALESCE((SELECT SUM(service_price) FROM booking_services bs WHERE bs.booking_id = b.id), 0) as services_total,
        COALESCE((SELECT SUM(amount) FROM payment_transactions pt WHERE pt.booking_id = b.id AND pt.status = 'paid'), 0) as total_paid_amount,
        (SELECT payment_method FROM payment_transactions pt WHERE pt.booking_id = b.id ORDER BY pt.created_at DESC LIMIT 1) as latest_payment_method,
        (SELECT receipt_path FROM payment_transactions pt WHERE pt.booking_id = b.id AND pt.receipt_path IS NOT NULL ORDER BY pt.created_at DESC LIMIT 1) as receipt_path
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.id = ?";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Booking not found']);
        exit();
    }
    
    $booking = $result->fetch_assoc();
    
    // Get services for this booking
    $services_sql = "SELECT service_name, service_price FROM booking_services WHERE booking_id = ?";
    $services_stmt = $conn->prepare($services_sql);
    $services_stmt->bind_param("i", $booking_id);
    $services_stmt->execute();
    $services_result = $services_stmt->get_result();
    
    $services = [];
    while ($service = $services_result->fetch_assoc()) {
        $price = $service['service_price'];
        $service_name = $service['service_name'];
        
        // If the price is 0, try to get the actual price from service_items
        if ($price == 0) {
            $price_sql = "SELECT price_range FROM service_items 
                         WHERE service_id IN (SELECT id FROM services WHERE name = ?) 
                         LIMIT 1";
            $price_stmt = $conn->prepare($price_sql);
            $price_stmt->bind_param("s", $service_name);
            $price_stmt->execute();
            $price_result = $price_stmt->get_result();
            
            if ($price_result && $price_result->num_rows > 0) {
                $price_data = $price_result->fetch_assoc();
                if (!empty($price_data['price_range'])) {
                    // Convert price_range like "₱10,000" to a number
                    $price_range = $price_data['price_range'];
                    $price_range = str_replace(['₱', ','], '', $price_range);
                    if (is_numeric($price_range)) {
                        $price = (float)$price_range;
                        
                        // Update the price in the booking_services table for future reference
                        $update_sql = "UPDATE booking_services SET service_price = ? 
                                      WHERE booking_id = ? AND service_name = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("dis", $price, $booking_id, $service_name);
                        $update_stmt->execute();
                    }
                }
            }
        }
        
        $services[] = [
            'service_name' => ucwords(strtolower($service_name)),
            'price' => $price
        ];
    }
    
    // Calculate total amount and remaining balance
    $total_amount = $booking['total_amount'] + $booking['services_total'];
    $paid_amount = $booking['total_paid_amount'];
    $remaining_balance = max(0, $total_amount - $paid_amount);
    
    // Determine payment status based on paid amount
    $payment_status = 'pending';
    if ($paid_amount >= $total_amount) {
        $payment_status = 'paid';
        $remaining_balance = 0;
    } elseif ($paid_amount > 0) {
        $payment_status = 'partially_paid';
    }
    
    // For cash payments, always set payment status to paid and remaining balance to 0
    $payment_method = $booking['latest_payment_method'] ?? '';
    if (strtolower($payment_method) === 'cash') {
        $payment_status = 'paid';
        $paid_amount = $total_amount; // Set paid amount to total amount
        $remaining_balance = 0;
    }
    
    // Check if this is a custom theme
    $theme_info = ucwords(strtolower($booking['theme_name']));
    $custom_theme_description = null;
    $is_custom_theme = false;
    
    if (strpos($booking['theme_name'], 'custom_') === 0) {
        // Extract the custom theme ID
        $custom_theme_id = substr($booking['theme_name'], 7);
        $is_custom_theme = true;
        
        // Get custom theme details
        $custom_theme_sql = "SELECT name, description FROM custom_themes WHERE id = ?";
        $custom_theme_stmt = $conn->prepare($custom_theme_sql);
        $custom_theme_stmt->bind_param("i", $custom_theme_id);
        $custom_theme_stmt->execute();
        $custom_theme_result = $custom_theme_stmt->get_result();
        
        if ($custom_theme_result->num_rows > 0) {
            $custom_theme = $custom_theme_result->fetch_assoc();
            $theme_info = "Custom: " . ucwords(strtolower($custom_theme['name']));
            $custom_theme_description = $custom_theme['description'];
        }
    }
    
    // Prepare the response
    $response = [
        'booking_reference' => $booking['booking_reference'],
        'package_name' => ucwords(strtolower($booking['package_name'])),
        'event_date' => $booking['event_date'],
        'event_start_time' => $booking['event_start_time'],
        'event_end_time' => $booking['event_end_time'],
        'theme_name' => $theme_info,
        'is_custom_theme' => $is_custom_theme,
        'custom_theme_description' => $custom_theme_description,
        'guest_count' => $booking['guest_count'],
        'customer_name' => $booking['customer_name'],
        'customer_email' => $booking['customer_email'],
        'customer_phone' => $booking['customer_phone'],
        'base_amount' => $booking['total_amount'],
        'services_total' => $booking['services_total'],
        'total_amount' => $total_amount,
        'paid_amount' => $paid_amount,
        'remaining_balance' => $remaining_balance,
        'payment_method' => $booking['latest_payment_method'] ? ucwords(strtolower($booking['latest_payment_method'])) : 'Pending',
        'payment_status' => $payment_status,
        'receipt_path' => $booking['receipt_path'],
        'services' => $services
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error fetching booking details: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?> 