<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
  header("Location: ../../login.php");
  exit();
}

// Database connection
include '../../db/config.php';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get admin ID who's creating this reservation
    $adminId = $_SESSION['user_id'];
    
    // Get form data
    // Customer information
    $customerName = $_POST['customerName'];
    $customerEmail = $_POST['customerEmail'];
    $customerPhone = $_POST['customerPhone'];
    $customerAddress = isset($_POST['customerAddress']) ? $_POST['customerAddress'] : '';
    
    // Event details
    $eventDate = $_POST['eventDate'];
    $eventTime = $_POST['eventTime'];
    $eventEndTime = isset($_POST['eventEndTime']) ? $_POST['eventEndTime'] : null;
    $packageName = $_POST['packageName'];
    $themeName = isset($_POST['themeName']) && !empty($_POST['themeName']) ? $_POST['themeName'] : '';
    $guestCount = $_POST['guestCount'];
    
    // Additional services
    $services = isset($_POST['services']) ? $_POST['services'] : [];
    
    // Payment information
    $paymentMethod = $_POST['paymentMethod'];
    $amountPaid = $_POST['amountPaid'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // 1. Insert user or get existing user
        $sql = "INSERT INTO users (name, email, phone, birthday, password) 
                VALUES (?, ?, ?, CURDATE(), ?) 
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                phone = VALUES(phone)";
        
        // Generate a random password hash for manually created users
        $defaultPassword = password_hash(uniqid(), PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $customerName, $customerEmail, $customerPhone, $defaultPassword);
        $stmt->execute();
        
        // Get user ID (either new or existing)
        if ($stmt->affected_rows > 0) {
            $userId = $stmt->insert_id;
        } else {
            // If no rows affected, user already exists, get their ID
            $sql = "SELECT id FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $customerEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $userId = $user['id'];
        }
        
        // Generate booking reference
        $bookingReference = 'BK' . date('YmdHis') . rand(100, 999);
        
        // Calculate service costs if any
        $serviceCosts = 0;
        $servicePrices = []; // Store prices for each service
        
        if (!empty($services)) {
            foreach ($services as $serviceName) {
                $servicePrice = 0; // Default price
                
                // Try to extract price from the service name if it contains a price
                if (preg_match('/\d+,\d+|\d+/', $serviceName, $matches)) {
                    // If price is in format "10,000" or similar, remove commas
                    $priceString = str_replace(',', '', $matches[0]);
                    $servicePrice = (float)$priceString;
                } else {
                    // If no price in name, try to find a service item with this service name
                    $priceSql = "SELECT si.price_range FROM service_items si 
                                JOIN services s ON si.service_id = s.id 
                                WHERE s.name = ? LIMIT 1";
                    $priceStmt = $conn->prepare($priceSql);
                    $priceStmt->bind_param("s", $serviceName);
                    $priceStmt->execute();
                    $priceResult = $priceStmt->get_result();
                    
                    if ($priceResult && $priceResult->num_rows > 0) {
                        $priceData = $priceResult->fetch_assoc();
                        // Convert price_range like "15,000" to a number
                        $priceRange = $priceData['price_range'];
                        $priceRange = str_replace(',', '', $priceRange);
                        if (is_numeric($priceRange)) {
                            $servicePrice = (float)$priceRange;
                        }
                    }
                }
                
                // Store the price for this service
                $servicePrices[$serviceName] = $servicePrice;
                $serviceCosts += $servicePrice;
            }
        }
        
        // Set total amount to include the package price plus all service costs
        // The amountPaid from the form already includes service costs since we updated it with JavaScript
        $totalAmount = $amountPaid;
        
        // Status will be 'confirmed' since it's a manual booking
        $status = 'confirmed';
        
        // Payment status will always be 'paid' for manual reservations
        $paymentStatus = 'paid';
        
        // 2. Insert booking
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
                    payment_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issssssiss", 
            $userId, 
            $bookingReference,
            $eventDate, 
            $eventTime, 
            $eventEndTime,
            $packageName, 
            $themeName, 
            $totalAmount,
            $status, 
            $paymentStatus
        );
        $stmt->execute();
        $bookingId = $stmt->insert_id;
        
        // 3. Insert selected services
        if (!empty($services)) {
            $sql = "INSERT INTO booking_services (booking_id, service_name, service_price) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            foreach ($services as $serviceName) {
                $stmt->bind_param("isi", $bookingId, $serviceName, $servicePrices[$serviceName]);
                $stmt->execute();
            }
        }
        
        // 4. Insert selected service items if any
        if (isset($_POST['service_items']) && !empty($_POST['service_items'])) {
            $sql = "INSERT INTO booking_service_items (booking_id, service_item_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            
            foreach ($_POST['service_items'] as $serviceItemId) {
                $stmt->bind_param("ii", $bookingId, $serviceItemId);
                $stmt->execute();
            }
        }
        
        // 5. Add payment transaction if amount paid > 0
        if ($amountPaid > 0) {
            $sql = "INSERT INTO payment_transactions (booking_id, amount, payment_method, status) 
                    VALUES (?, ?, ?, 'paid')";
            $stmt = $conn->prepare($sql);
            // For manual cash reservations, set the payment amount to the full total amount
            $stmt->bind_param("ids", $bookingId, $totalAmount, $paymentMethod);
            $stmt->execute();
        }
        
        // Commit the transaction
        $conn->commit();
        
        // Redirect to the same page with success message
        header("Location: index.php?success=1&booking_reference=" . urlencode($bookingReference));
        exit();
        
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        
        // Redirect back with error
        header("Location: index.php?error=" . urlencode("Error creating reservation: " . $e->getMessage()));
        exit();
    }
} else {
    // If not POST request, redirect to the form
    header("Location: index.php");
    exit();
}

/**
 * Generate a unique reservation code
 * 
 * @param int $reservationId The reservation ID
 * @return string The generated reservation code
 */
function generateReservationCode($reservationId) {
    $prefix = 'MR'; // MR for Manual Reservation
    $date = date('ymd');
    $padded = str_pad($reservationId, 4, '0', STR_PAD_LEFT);
    return $prefix . $date . $padded;
}
?> 