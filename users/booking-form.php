<?php 
include '../db/config.php';
session_start(); // Add session_start() at the beginning

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Initialize session guest list if it doesn't exist
if (!isset($_SESSION['temp_guest_list'])) {
    $_SESSION['temp_guest_list'] = [];
}

// Debug information
if (isset($_SESSION['temp_guest_list']) && count($_SESSION['temp_guest_list']) > 0) {
    error_log("Found " . count($_SESSION['temp_guest_list']) . " guests in session");
}

// Get package parameter from URL
$selected_package = isset($_GET['package']) ? $_GET['package'] : null;

// Function to get unavailable dates
function getUnavailableDates() {
    global $conn;
    
    $unavailable_dates = [];
    
    // Check dates with existing bookings - any date with a booking is considered fully booked
    $sql = "SELECT event_date FROM bookings 
            WHERE status != 'cancelled' 
            AND event_date >= CURDATE()";
            
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $date = $row['event_date'];
            // Mark the entire date as unavailable (no time slots, just completely booked)
            $unavailable_dates[] = $date;
        }
    }
    
    return $unavailable_dates;
}

// Get unavailable dates
$unavailable_dates = getUnavailableDates();
$unavailable_dates_json = json_encode($unavailable_dates);

$sql = "SELECT name, price, duration, description, image_path FROM event_packages WHERE status = 'active'";
$result = $conn->query($sql);

$sql2 = "SELECT id, name FROM services"; 
$services_result = $conn->query($sql2);

$sql3 = "SELECT name, image_path, packages FROM themes";
$themes_result = $conn->query($sql3);

// Create a data structure to organize themes by package type
$themes_by_package = [];
if ($themes_result && $themes_result->num_rows > 0) {
    while($theme = $themes_result->fetch_assoc()) {
        $theme_packages = !empty($theme['packages']) ? explode(',', $theme['packages']) : [];
        
        // Add theme to each package it belongs to
        if (!empty($theme_packages)) {
            foreach ($theme_packages as $package_id) {
                if (!isset($themes_by_package[$package_id])) {
                    $themes_by_package[$package_id] = [];
                }
                $themes_by_package[$package_id][] = $theme;
            }
        } else {
            // For themes with no specific package, add to "general" category
            if (!isset($themes_by_package['general'])) {
                $themes_by_package['general'] = [];
            }
            $themes_by_package['general'][] = $theme;
        }
    }
}

// Get package IDs and names for reference
$package_mapping = [];
$sql4 = "SELECT id, name FROM event_packages";
$packages_result = $conn->query($sql4);
if ($packages_result && $packages_result->num_rows > 0) {
    while($package = $packages_result->fetch_assoc()) {
        $package_mapping[$package['id']] = $package['name'];
    }
}

// Get logged in user details
if(isset($_SESSION['user_id'])) { // Check if user is logged in
    $user_id = $_SESSION['user_id'];
    $sql5 = "SELECT name, email, phone FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql5);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user_data = $user_result->fetch_assoc();
} else {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}

// Add query to fetch service items
$service_items = [];
$sql_items = "SELECT * FROM service_items ORDER BY name ASC";
$items_result = $conn->query($sql_items);
if ($items_result && $items_result->num_rows > 0) {
    while($item = $items_result->fetch_assoc()) {
        if (!isset($service_items[$item['service_id']])) {
            $service_items[$item['service_id']] = [];
        }
        $service_items[$item['service_id']][] = $item;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add header for JSON response
    header('Content-Type: application/json');
    
    // Generate booking reference
    $booking_reference = 'BK' . date('YmdHis') . rand(100,999);
    
    // Get values from POST data
    $event_date = $_POST['eventDate'];
    $event_start_time = $_POST['eventStartTime'];
    $event_end_time = $_POST['eventEndTime'];
    $package_name = $_POST['package'];
    $theme_name = $_POST['theme'];
    $total_amount = $_POST['totalAmount'];
    $special_requests = $_POST['specialRequests'] ?? '';
    
    // Custom theme handling
    $custom_theme_name = null;
    $custom_theme_description = null;
    $custom_theme_colors = null;
    $custom_theme_image = null;
    
    if ($theme_name === 'custom' && isset($_POST['customThemeName'])) {
        $custom_theme_name = $_POST['customThemeName'];
        $custom_theme_description = $_POST['customThemeDescription'] ?? '';
        $custom_theme_colors = $_POST['customThemeColors'] ?? '';
        
        // Handle image upload
        if (isset($_FILES['customThemeImage']) && $_FILES['customThemeImage']['error'] == 0) {
            $upload_dir = '../uploads/custom_themes/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = time() . '_' . $_FILES['customThemeImage']['name'];
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['customThemeImage']['tmp_name'], $upload_path)) {
                $custom_theme_image = $file_name;
            }
        }
        
        // Save custom theme to database
        $sql_theme = "INSERT INTO custom_themes (user_id, name, description, colors, image_path) 
                    VALUES (?, ?, ?, ?, ?)";
        
        try {
            $stmt_theme = $conn->prepare($sql_theme);
            $stmt_theme->bind_param("issss", 
                $user_id,
                $custom_theme_name,
                $custom_theme_description,
                $custom_theme_colors,
                $custom_theme_image
            );
            
            $stmt_theme->execute();
            $custom_theme_id = $conn->insert_id;
            
            // Update theme name to include ID for reference
            $theme_name = 'custom_' . $custom_theme_id;
        } catch (Exception $e) {
            // If custom theme couldn't be saved, continue with booking but log error
            error_log('Error saving custom theme: ' . $e->getMessage());
        }
    }
    
    // Insert into bookings table
    $sql = "INSERT INTO bookings (user_id, booking_reference, event_date, event_start_time, event_end_time, package_name, theme_name, total_amount, special_requests, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    try {
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
        
        if ($stmt->execute()) {
            $booking_id = $conn->insert_id;
            
            // Check if we already have guests in the guests table with this temp booking ID
            $temp_booking_id = isset($_POST['temp_booking_id']) ? $_POST['temp_booking_id'] : null;
            
            if ($temp_booking_id) {
                // Update the booking_id for existing guests in the guests table
                $update_sql = "UPDATE guests SET booking_id = ? WHERE booking_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $booking_id, $temp_booking_id);
                
                if ($update_stmt->execute()) {
                    // Log success
                    error_log("Successfully updated guest records from temporary booking ID: $temp_booking_id to new booking ID: $booking_id");
                } else {
                    // Log error but continue with the booking process
                    error_log("Error updating guest records: " . $conn->error);
                }
            }
            
            // Insert guests from POST data if there are any (fallback approach)
            if (isset($_POST['guests']) && !empty($_POST['guests'])) {
                $guests = json_decode($_POST['guests'], true);
                
                if (!$guests && isset($_SESSION['temp_guest_list']) && !empty($_SESSION['temp_guest_list'])) {
                    // Use temporarily stored guests from session if JSON parsing failed
                    $guests = $_SESSION['temp_guest_list'];
                    error_log("Using guest list from session for booking ID: $booking_id (" . count($guests) . " guests)");
                }
                
                if ($guests && is_array($guests)) {
                    $guest_count = 0;
                    
                    // First, check if we already inserted these guests via the temp_booking_id approach
                    $check_sql = "SELECT COUNT(*) as guest_count FROM guests WHERE booking_id = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param("i", $booking_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $existing_count = $check_result->fetch_assoc()['guest_count'];
                    
                    // Only insert guests if we don't already have them
                    if ($existing_count == 0) {
                        $conn->begin_transaction();
                        
                        try {
                            $sql_guests = "INSERT INTO guests (booking_id, name, email, phone, created_at) 
                                          VALUES (?, ?, ?, ?, NOW())";
                            $stmt_guests = $conn->prepare($sql_guests);
                            
                            foreach($guests as $guest) {
                                if (!isset($guest['name']) || empty($guest['name'])) {
                                    continue;
                                }
                                
                                $stmt_guests->bind_param("isss",
                                    $booking_id,
                                    $guest['name'],
                                    isset($guest['email']) ? $guest['email'] : '',
                                    isset($guest['phone']) ? $guest['phone'] : ''
                                );
                                
                                if ($stmt_guests->execute()) {
                                    $guest_count++;
                                }
                            }
                            
                            $conn->commit();
                            error_log("Successfully inserted $guest_count guests for booking ID: $booking_id");
                            
                        } catch (Exception $e) {
                            $conn->rollback();
                            error_log("Error inserting guests from JSON: " . $e->getMessage());
                        }
                    } else {
                        error_log("Skipped inserting guests from JSON, $existing_count guests already exist for booking ID: $booking_id");
                    }
                }
                
                // Clear temporary guest list regardless of the outcome
                unset($_SESSION['temp_guest_list']);
                
                // Also clear temp_booking_id from session
                if (isset($_SESSION['temp_booking_id'])) {
                    unset($_SESSION['temp_booking_id']);
                }
            }
            
            // Insert services if services data exists
            if (isset($_POST['services']) && !empty($_POST['services'])) {
                $sql_services = "INSERT INTO booking_services (booking_id, service_name, service_price) VALUES (?, ?, ?)";
                $stmt_services = $conn->prepare($sql_services);
                
                foreach($_POST['services'] as $service) {
                    $service_name = $service;
                    // Get price from service_items
                    $service_price = 0; // Default price
                    
                    // First try to get price by looking up the actual service name
                    $price_query = "SELECT si.price_range 
                                    FROM service_items si 
                                    JOIN services s ON si.service_id = s.id 
                                    WHERE LOWER(s.name) = LOWER(?) 
                                    LIMIT 1";
                    $stmt_price = $conn->prepare($price_query);
                    $stmt_price->bind_param("s", $service_name);
                    $stmt_price->execute();
                    $price_result = $stmt_price->get_result();
                    
                    if ($price_result && $price_result->num_rows > 0) {
                        $price_data = $price_result->fetch_assoc();
                        // Convert price_range like "₱15,000" to a number
                        $price_range = $price_data['price_range'];
                        $price_range = str_replace(['₱', ','], '', $price_range);
                        if (is_numeric($price_range)) {
                            $service_price = (float)$price_range;
                        }
                    } else {
                        // If not found, try to get it by generic service_id lookup (backward compatibility)
                        $fallback_query = "SELECT price_range FROM service_items WHERE service_id IN (SELECT id FROM services WHERE name = ?) LIMIT 1";
                        $stmt_fallback = $conn->prepare($fallback_query);
                        $stmt_fallback->bind_param("s", $service_name);
                        $stmt_fallback->execute();
                        $fallback_result = $stmt_fallback->get_result();
                        
                        if ($fallback_result && $fallback_result->num_rows > 0) {
                            $price_data = $fallback_result->fetch_assoc();
                            // Convert price_range like "₱15,000" to a number
                            $price_range = $price_data['price_range'];
                            $price_range = str_replace(['₱', ','], '', $price_range);
                            if (is_numeric($price_range)) {
                                $service_price = (float)$price_range;
                            }
                        }
                    }
                    
                    $stmt_services->bind_param("isd",
                        $booking_id,
                        $service_name,
                        $service_price
                    );
                    $stmt_services->execute();
                }
            }
            
            // Insert service items if selected
            if (isset($_POST['service_items']) && !empty($_POST['service_items'])) {
                $sql_items = "INSERT INTO booking_service_items (booking_id, service_item_id) VALUES (?, ?)";
                $stmt_items = $conn->prepare($sql_items);
                
                foreach($_POST['service_items'] as $item_id) {
                    $stmt_items->bind_param("ii", $booking_id, $item_id);
                    $stmt_items->execute();
                }
            }
            
            // Set success message in session
            $_SESSION['booking_success'] = true;
            
            // Clean up temporary booking record if it exists
            if (isset($_POST['temp_booking_id']) && !empty($_POST['temp_booking_id'])) {
                // We don't need to delete guests as they've been moved to the new booking
                // Just delete the temporary booking record
                $cleanup_sql = "DELETE FROM bookings WHERE id = ? AND booking_reference LIKE 'TMP%'";
                $cleanup_stmt = $conn->prepare($cleanup_sql);
                $temp_id = $_POST['temp_booking_id'];
                $cleanup_stmt->bind_param("i", $temp_id);
                $cleanup_stmt->execute();
                error_log("Cleaned up temporary booking ID: $temp_id");
            }
            
            echo json_encode(['success' => true, 'message' => 'Your booking has been booked! Booking reference: ' . $booking_reference]);
            exit;
        } else {
            throw new Exception("Failed to save booking");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error saving booking: ' . $e->getMessage()]);
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Form | The Barn & Backyard</title>
    <link rel="icon" href="img/barn-backyard.svg" type="image/svg+xml"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 40px 20px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            padding: 50px 40px;
            text-align: center;
            color: #ffffff;
            position: relative;
            overflow: hidden;
        }

        .form-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            pointer-events: none;
        }

        .form-header h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 15px;
            letter-spacing: -0.5px;
        }

        .form-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            padding: 30px 40px;
            background: #f8fafc;
            border-bottom: 1px solid #e9ecef;
            position: relative;
        }

        .step-indicator::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 40px;
            right: 40px;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
            z-index: 0;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .step-number {
            width: 50px;
            height: 50px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            color: #64748b;
            font-weight: 600;
            transition: all 0.4s ease;
            border: 2px solid #e2e8f0;
            position: relative;
            z-index: 2;
        }

        .step.active .step-number {
            background: #4f46e5;
            color: white;
            box-shadow: 0 0 0 5px rgba(79, 70, 229, 0.2);
            border-color: #4f46e5;
        }

        .step.completed .step-number {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }

        .step-title {
            font-size: 0.95rem;
            color: #64748b;
            font-weight: 500;
        }

        .step.active .step-title {
            color: #4f46e5;
            font-weight: 600;
        }

        .form-section {
            display: none;
            padding: 40px;
        }

        .form-section.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-section h2 {
            font-size: 1.8rem;
            color: #1e293b;
            margin-bottom: 30px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-section h2 i {
            color: #4f46e5;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #334155;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f8fafc;
            color: #1e293b;
        }

        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
            outline: none;
            background: #ffffff;
        }

        .form-control.invalid {
            border-color: #ef4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15);
        }

        .package-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .package-card {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            position: relative;
            background: #ffffff;
            min-height: 280px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        
        .package-image {
            margin: -30px -30px 20px -30px;
            height: 180px;
            overflow: hidden;
            border-radius: 16px 16px 0 0;
            position: relative;
        }
        
        .package-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .package-card:hover .package-image img {
            transform: scale(1.05);
        }

        .package-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #4f46e5, #8b5cf6);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .package-card:hover {
            border-color: #4f46e5;
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(79, 70, 229, 0.15);
        }

        .package-card:hover::before {
            opacity: 1;
        }

        .package-card.selected {
            border-color: #4f46e5;
            background: rgba(79, 70, 229, 0.05);
            box-shadow: 0 15px 30px rgba(79, 70, 229, 0.2);
        }

        .package-card.selected::before {
            opacity: 1;
        }
        
        .package-card.selected .package-image::after {
            content: '✓ Selected';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #4f46e5;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .package-card h3 {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 600;
        }

        .package-card p {
            color: #64748b;
            margin-bottom: 15px;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .package-card .package-price {
            position: absolute;
            bottom: 25px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 1.6rem;
            color: #1e293b;
            font-weight: 700;
            padding: 15px;
            border-top: 1px solid #e2e8f0;
            background: rgba(255,255,255,0.9);
        }

        .additional-services {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
            margin: 24px 0 32px;
        }

        .service-card {
            display: flex;
            flex-direction: column;
            padding: 20px;
            border-radius: 12px;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .service-card:hover {
            background: #ffffff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-color: #4f46e5;
            transform: translateY(-5px);
        }

        .service-card h4 {
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: #1e293b;
        }

        .service-card .service-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #4f46e5;
        }

        .service-items-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-y: auto;
        }

        .service-items-modal.show {
            display: flex;
        }

        .service-items-content {
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        .service-items-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .service-items-header h3 {
            font-size: 1.4rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .service-items-header .close-service-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .service-items-header .close-service-modal:hover {
            color: #ef4444;
        }

        .service-items-body {
            padding: 20px;
            overflow-y: auto;
            max-height: 60vh;
        }

        .service-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .service-item-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
            position: relative;
        }

        .service-item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            border-color: #4f46e5;
        }

        .service-item-card .item-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-bottom: 1px solid #e2e8f0;
        }

        .service-item-card .item-image-placeholder {
            width: 100%;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: #94a3b8;
            font-size: 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .service-item-card .item-details {
            padding: 15px;
        }

        .service-item-card h4 {
            font-size: 1rem;
            margin: 0 0 5px 0;
            color: #1e293b;
        }

        .service-item-card .item-price {
            font-size: 0.9rem;
            color: #4f46e5;
            font-weight: 600;
        }

        .service-item-select {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .service-item-select.selected {
            background: #4f46e5;
            border-color: #4f46e5;
            color: white;
        }

        .service-item-card .item-select-input {
            display: none;
        }

        .service-items-footer {
            padding: 15px 20px;
            display: flex;
            justify-content: flex-end;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .service-items-footer button {
            padding: 10px 20px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .service-items-footer button:hover {
            background: #4338ca;
        }

        .service-card.selected {
            border-color: #4f46e5;
            background: rgba(79, 70, 229, 0.05);
        }

        .service-card.selected::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #8b5cf6);
            opacity: 1;
        }

        .no-service-items {
            text-align: center;
            padding: 30px;
            color: #64748b;
        }

        .no-service-items i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #94a3b8;
        }

        .theme-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 24px;
            margin: 24px 0 32px;
        }
        
        .theme-option {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #ffffff;
            position: relative;
            overflow: hidden;
        }
        
        .theme-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #8b5cf6);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .theme-option:hover {
            border-color: #4f46e5;
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(79, 70, 229, 0.15);
        }
        
        .theme-option:hover::before {
            opacity: 1;
        }

        .theme-option.selected {
            border-color: #4f46e5;
            background: rgba(79, 70, 229, 0.05);
        }
        
        .theme-option.selected::before {
            opacity: 1;
        }
        
        .theme-preview {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .theme-option:hover .theme-preview {
            transform: scale(1.05);
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }

        .prev-btn, .next-btn, .submit-btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .prev-btn {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .prev-btn:hover {
            background: #e2e8f0;
            color: #334155;
        }

        .next-btn, .submit-btn {
            background: #4f46e5;
            color: white;
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
        }

        .next-btn:hover, .submit-btn:hover {
            background: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
        }

        .prev-btn i, .next-btn i, .submit-btn i {
            margin-right: 8px;
        }

        .next-btn i, .submit-btn i {
            margin-left: 8px;
            margin-right: 0;
        }

        .guest-list-section {
            margin: 24px 0 32px;
            background: #f8fafc;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
        }

        .guest-list-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .guest-list-table th {
            background: #4f46e5;
            color: white;
            padding: 16px;
            text-align: left;
            font-weight: 600;
        }

        .guest-list-table th.text-center {
            text-align: center;
        }

        .guest-list-table td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .guest-list-table tr:last-child td {
            border-bottom: none;
        }

        .guest-list-table tr:hover {
            background-color: #f8fafc;
        }

        .guest-name {
            font-weight: 500;
            color: #1e293b;
        }

        .guest-email, .guest-phone {
            color: #64748b;
        }

        .actions-cell {
            text-align: center;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
        }

        .action-btn i {
            font-size: 1rem;
        }

        .view-qr-btn {
            padding: 8px 16px;
            background-color: #6366f1;
            color: white;
            font-size: 1rem;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 4px 6px rgba(99, 102, 241, 0.3);
        }
        
        .view-qr-btn:hover {
            background-color: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(99, 102, 241, 0.4);
        }

        .edit-btn {
            background-color: #10b981;
        }

        .edit-btn:hover {
            background-color: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .delete-btn {
            background-color: #ef4444;
        }

        .delete-btn:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }

        .empty-guest-message {
            text-align: center;
            padding: 30px !important;
            color: #64748b;
            font-size: 1rem;
            background-color: #f8fafc;
        }

        .empty-guest-message i {
            color: #4f46e5;
            margin-right: 8px;
            font-size: 1.1rem;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .btn {
            padding: 12px 20px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn:hover {
            background: #4338ca;
        }

        .form-text {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 8px;
        }

        .booking-summary {
            background: #f8fafc;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-item strong {
            color: #334155;
            font-weight: 600;
        }

        .total-amount {
            font-size: 1.6rem;
            font-weight: 700;
            color: #10b981;
            margin-top: 25px;
            text-align: right;
            padding-top: 20px;
            border-top: 2px dashed #e2e8f0;
        }

        @media (max-width: 768px) {
            .container {
                border-radius: 16px;
            }

            .form-header {
                padding: 30px 20px;
            }

            .form-header h1 {
                font-size: 2rem;
            }

            .step-indicator {
                padding: 20px;
            }

            .step-number {
                width: 40px;
                height: 40px;
                font-size: 0.9rem;
            }

            .step-title {
                font-size: 0.8rem;
            }

            .form-section {
                padding: 25px 20px;
            }

            .package-selector, .theme-selector {
                grid-template-columns: 1fr;
            }
        }

        /* Custom radio and checkbox styles */
        input[type="radio"], input[type="checkbox"] {
            accent-color: #4f46e5;
        }

        /* File upload button styling */
        input[type="file"] {
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            width: 100%;
            cursor: pointer;
        }

        /* Textarea styling */
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 12px 24px;
            background: #1e293b;
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .back-button:hover {
            background: #334155;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        /* Date and Time selection styling */
        .date-picker-group {
            margin-bottom: 35px;
        }

        .date-input-container {
            position: relative;
            margin-bottom: 10px;
        }

        .date-status-indicator {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: none;
        }

        .date-input {
            padding-right: 45px !important;
            cursor: pointer;
            font-weight: 500;
            text-align: left;
        }
        
        /* Flatpickr customizations */
        .flatpickr-calendar {
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            width: 100%;
            max-width: 320px;
        }
        
        .flatpickr-day {
            border-radius: 8px;
            margin: 2px;
            height: 38px;
            line-height: 38px;
        }
        
        .flatpickr-day.selected {
            background: #4f46e5;
            border-color: #4f46e5;
        }
        
        .flatpickr-day.selected:hover {
            background: #4338ca;
            border-color: #4338ca;
        }
        
        .flatpickr-day:hover {
            background: #f1f5f9;
        }
        
        .flatpickr-day.today {
            border-color: #4f46e5;
        }
        
        .flatpickr-day.flatpickr-disabled {
            color: #dc2626 !important;
            text-decoration: line-through;
            background-color: #fee2e2 !important;
            cursor: not-allowed;
        }
        
        .flatpickr-months {
            background-color: #4f46e5;
            color: white;
            padding-top: 8px;
        }
        
        .flatpickr-current-month {
            color: white;
            padding: 5px 0;
        }
        
        .flatpickr-monthDropdown-months {
            color: white;
            background-color: #4f46e5;
        }
        
        .flatpickr-monthDropdown-month {
            color: #1e293b;
        }
        
        .flatpickr-prev-month, .flatpickr-next-month {
            fill: white !important;
            color: white !important;
        }
        
        .date-legend {
            display: flex;
            gap: 15px;
            margin-top: 8px;
            font-size: 0.85rem;
        }
        
        .date-legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .date-legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
        
        .date-legend-available {
            background-color: #4f46e5;
        }
        
        .date-legend-booked {
            background-color: #dc2626;
        }

        .time-selection-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }

        .time-select {
            cursor: pointer;
            font-weight: 500;
            padding: 12px 15px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%231e293b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
            appearance: none;
        }

        .time-availability-message {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 12px;
            font-size: 0.95rem;
            color: #475569;
            margin-bottom: 30px;
            border-left: 4px solid #64748b;
            display: flex;
            align-items: center;
        }

        .time-availability-message i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .time-availability-message.available {
            background: #ecfdf5;
            border-left-color: #10b981;
            color: #047857;
        }

        .time-availability-message.unavailable {
            background: #fef2f2;
            border-left-color: #ef4444;
            color: #b91c1c;
        }

        .time-availability-message.warning {
            background: #fffbeb;
            border-left-color: #f59e0b;
            color: #b45309;
        }

        /* Disabled Input Styling */
        .form-control:disabled {
            background-color: #f1f5f9;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Next Button Highlight Effect */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(79, 70, 229, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(79, 70, 229, 0);
            }
        }

        .next-btn-highlight {
            animation: pulse 1.5s infinite;
        }

        /* Add to your existing CSS */
        .theme-selector {
            min-height: 280px; /* Set minimum height to prevent layout shifts */
        }
        
        #noThemesMessage {
            padding: 20px;
            background-color: #f1f5f9;
            border-radius: 12px;
            text-align: center;
            margin: 20px 0;
            color: #475569;
            border-left: 4px solid #4f46e5;
        }
        
        /* Custom Theme Styles */
        .custom-theme-option {
            display: block !important;
        }

        .custom-theme-container {
            margin: 24px 0 32px;
            display: flex;
            justify-content: center;
        }

        .custom-theme-card {
            width: 100%;
            max-width: 400px;
            border: 2px solid #e2e8f0;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px;
            border-radius: 16px;
        }

        .custom-theme-card p {
            color: #64748b;
            text-align: center;
            margin: 12px 0;
            font-size: 0.95rem;
        }

        .theme-separator {
            text-align: center;
            font-size: 1.1rem;
            font-weight: 500;
            color: #334155;
            position: relative;
            margin: 36px 0;
        }

        .theme-separator:before {
            content: "";
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e2e8f0;
            z-index: 0;
        }

        .theme-separator span {
            background: #fff;
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }

        .custom-theme-btn {
            margin: 12px auto 0;
            background: #4f46e5;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .custom-theme-btn:hover {
            background: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
        }

        /* Modal Styles */
        .custom-theme-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .custom-theme-modal-content {
            background-color: #ffffff;
            margin: 5% auto;
            padding: 36px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            width: 80%;
            max-width: 600px;
            position: relative;
            animation: slideIn 0.4s ease;
        }

        @keyframes slideIn {
            from {opacity: 0; transform: translateY(-30px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 25px;
            color: #94a3b8;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .close-modal:hover {
            color: #1e293b;
        }

        .custom-theme-details {
            margin-top: 16px;
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            border-left: 4px solid #4f46e5;
        }

        .custom-theme-preview {
            width: 100%;
            max-height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-top: 12px;
            display: none;
        }

        /* QR Code Modal */
        .qr-modal {
            display: none;
            position: fixed;
            z-index: 1500;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
        }

        .qr-modal-content {
            background-color: #ffffff;
            margin: 10% auto;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 400px;
            text-align: center;
            position: relative;
            animation: slideInFromTop 0.3s ease-out;
        }

        @keyframes slideInFromTop {
            0% {
                transform: translateY(-50px);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .qr-close {
            position: absolute;
            top: 15px;
            right: 20px;
            color: #94a3b8;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .qr-close:hover {
            color: #1e293b;
        }

        .qr-code-container {
            padding: 20px;
            border-radius: 12px;
            background-color: #f1f5f9;
            margin: 20px auto;
            width: 90%;
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
            border: 2px dashed #6366f1;
        }

        .qr-guest-info {
            margin-bottom: 15px;
        }

        .qr-guest-info h3 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
            text-align: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }

        .qr-guest-info p {
            color: #64748b;
            margin-bottom: 5px;
        }
        
        .btn-view-qr {
            padding: 6px 12px;
            margin: 0 5px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            background: #6366f1;
            color: white;
        }

        .btn-view-qr:hover {
            background: #4f46e5;
        }

        .btn-download-qr {
            padding: 12px 24px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            min-width: 180px;
            box-shadow: 0 6px 12px rgba(79, 70, 229, 0.3);
        }

        .btn-download-qr:hover {
            background: #4338ca;
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(79, 70, 229, 0.4);
        }

        /* Upload Container Styles */
        .upload-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px;
            background-color: #f8fafc;
            border-radius: 16px;
            border: 2px dashed #cbd5e1;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }
        
        .upload-container:hover {
            border-color: #4f46e5;
            background-color: #f1f5f9;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.1);
        }
        
        .upload-label {
            font-size: 1.2rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .upload-label i {
            color: #4f46e5;
            font-size: 1.4rem;
        }
        
        .upload-container small {
            margin: 15px 0;
            color: #64748b;
            font-size: 0.95rem;
            text-align: center;
            width: 100%;
        }
        
        .upload-btn {
            margin-top: 15px;
            background: #4f46e5;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .upload-btn:hover {
            background: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(79, 70, 229, 0.3);
        }
        
        .upload-success {
            margin-top: 20px;
            background-color: #ecfdf5;
            color: #065f46;
            padding: 15px 25px;
            border-radius: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeInUp 0.5s ease;
            border-left: 4px solid #10b981;
        }
        
        .upload-success i {
            font-size: 1.5rem;
            color: #10b981;
        }
        
        .upload-error {
            margin-top: 20px;
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 15px 25px;
            border-radius: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeInUp 0.5s ease;
            border-left: 4px solid #ef4444;
        }
        
        .upload-error i {
            font-size: 1.5rem;
            color: #ef4444;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .empty-guest-message {
            text-align: center;
            padding: 30px !important;
            color: #64748b;
            font-size: 1rem;
            background-color: #f8fafc;
        }
        
        .empty-guest-message i {
            color: #4f46e5;
            margin-right: 8px;
            font-size: 1.1rem;
        }
        
        .add-guest-btn {
            margin: 20px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background-color: #4f46e5;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .add-guest-btn:hover {
            background-color: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(79, 70, 229, 0.3);
        }

        /* Guest Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.2s ease;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: #ffffff;
            width: 90%;
            max-width: 500px;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: modalSlideIn 0.3s ease-out;
            border: 1px solid rgba(226, 232, 240, 0.6);
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }
        
        .modal-header h3 {
            color: #1e293b;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-header h3 i {
            color: #4f46e5;
        }
        
        .modal-header .modal-close {
            font-size: 1.8rem;
            font-weight: 300;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.2s ease;
            line-height: 1;
        }
        
        .modal-header .modal-close:hover {
            color: #1e293b;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 16px 24px 24px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .btn-cancel {
            padding: 12px 20px;
            background-color: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-cancel:hover {
            background-color: #e2e8f0;
            color: #334155;
        }
        
        .btn-save {
            padding: 12px 20px;
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        
        .btn-save:hover {
            background-color: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
        }
        
        .btn-delete-confirm {
            padding: 12px 20px;
            background-color: #ef4444;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }
        
        .btn-delete-confirm:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3);
        }
        
        .delete-message {
            font-size: 1.1rem;
            color: #334155;
            margin-bottom: 16px;
            text-align: center;
        }
        
        .delete-warning {
            font-size: 0.95rem;
            color: #ef4444;
            display: flex;
            align-items: center;
            gap: 8px;
            background-color: #fef2f2;
            padding: 12px 16px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .delete-warning i {
            color: #ef4444;
            font-size: 1.1rem;
        }
        
        #deleteGuestName {
            font-weight: 600;
            color: #1e293b;
        }

        /* Modal visibility classes */
        .modal-overlay.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        
        /* Guest Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }

        .selected-package-info {
            margin-bottom: 20px;
            color: #6b7280;
            font-style: italic;
        }

        .theme-description {
            margin-bottom: 20px;
            color: #6b7280;
            font-style: italic;
        }

        /* Booking Confirmation Modal Styles */
        #bookingConfirmationModal.modal-overlay {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        #bookingConfirmationModal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
        }
        
        #bookingConfirmationModal .modal-content {
            transform: translateY(30px);
            transition: transform 0.4s ease-out;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border-radius: 12px;
            overflow: hidden;
        }
        
        #bookingConfirmationModal.show .modal-content {
            transform: translateY(0);
        }
        
        #bookingConfirmationModal .modal-header {
            position: relative;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #bookingConfirmationModal .modal-close {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.7);
            transition: color 0.3s ease;
        }
        
        #bookingConfirmationModal .modal-close:hover {
            color: white;
        }
        
        #bookingConfirmationModal .fas.fa-calendar-check {
            display: block;
            margin: 0 auto 15px;
            animation: checkmark-scale 0.5s ease-in-out;
        }
        
        @keyframes checkmark-scale {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        #confirmationOkBtn {
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        
        #confirmationOkBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
        }

        /* Guest List Table */
        .guest-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .guest-table th {
            background-color: #f8fafc;
            padding: 12px 16px;
            text-align: left;
            color: #64748b;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .guest-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }
        
        .guest-name {
            font-weight: 600;
            color: #1e293b;
        }
        
        .actions-cell {
            width: 220px;
            padding-right: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            align-items: center;
        }
        
        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .view-qr-btn {
            padding: 8px 16px;
            background-color: #6366f1;
            color: white;
            font-size: 1rem;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 4px 6px rgba(99, 102, 241, 0.3);
        }
        
        .view-qr-btn:hover {
            background-color: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(99, 102, 241, 0.4);
        }
        
        .edit-btn {
            background-color: #f1f5f9;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .delete-btn {
            background-color: #f1f5f9;
            color: #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .delete-btn:hover {
            background-color: #fee2e2;
            color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.2);
        }
    </style>
</head>
<body class="bg-gray-50">
    <a href="../index.php" class="back-button">
        <i class="fas fa-arrow-left"></i> Back to Homepage
    </a>
    <div class="container">
        <div class="form-header">
            <h1>Reserve Your Event Now</h1>
            <p>Design your perfect experience with us</p>
        </div>

        <div class="step-indicator">
            <div class="step active">
                <div class="step-number">1</div>
                <div class="step-title">Event Details</div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-title">Package & Theme</div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-title">Guest List</div>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <div class="step-title">Personal Details</div>
            </div>
            <div class="step">
                <div class="step-number">5</div>
                <div class="step-title">Review & Confirm</div>
            </div>
        </div>

        <form id="eventBookingForm" method="POST">
            <!-- Step 1: Event Selection -->
            <div class="form-section active" id="step1">
                <h2><i class="fas fa-calendar-alt"></i> Event Details</h2>
                
                <div class="form-group date-picker-group">
                    <label for="eventDate">Select Your Event Date</label>
                    <div class="date-input-container">
                        <input type="text" id="eventDate" name="eventDate" class="form-control date-input" placeholder="Select a date" required>
                        <div class="date-status-indicator" id="dateStatusIndicator"></div>
                    </div>
                    <div class="date-legend">
                        <div class="date-legend-item">
                            <div class="date-legend-color date-legend-available"></div>
                            <span>Available</span>
                        </div>
                        <div class="date-legend-item">
                            <div class="date-legend-color date-legend-booked"></div>
                            <span>Booked</span>
                        </div>
                    </div>
                    <small class="form-text" id="dateAvailabilityMessage">Please select a date for your event.</small>
                </div>
                
                <div class="time-selection-container">
                    <div class="form-group">
                        <label for="eventStartTime">Start Time</label>
                        <select id="eventStartTime" name="eventStartTime" class="form-control time-select" required>
                            <option value="">Select start time</option>
                            <option value="08:00">8:00 AM</option>
                            <option value="09:00">9:00 AM</option>
                            <option value="10:00">10:00 AM</option>
                            <option value="11:00">11:00 AM</option>
                            <option value="12:00">12:00 PM</option>
                            <option value="13:00">1:00 PM</option>
                            <option value="14:00">2:00 PM</option>
                            <option value="15:00">3:00 PM</option>
                            <option value="16:00">4:00 PM</option>
                            <option value="17:00">5:00 PM</option>
                            <option value="18:00">6:00 PM</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="eventEndTime">End Time</label>
                        <select id="eventEndTime" name="eventEndTime" class="form-control time-select" required>
                            <option value="">Select end time</option>
                            <option value="12:00">12:00 PM</option>
                            <option value="13:00">1:00 PM</option>
                            <option value="14:00">2:00 PM</option>
                            <option value="15:00">3:00 PM</option>
                            <option value="16:00">4:00 PM</option>
                            <option value="17:00">5:00 PM</option>
                            <option value="18:00">6:00 PM</option>
                            <option value="19:00">7:00 PM</option>
                            <option value="20:00">8:00 PM</option>
                            <option value="21:00">9:00 PM</option>
                            <option value="22:00">10:00 PM</option>
                        </select>
                    </div>
                </div>
                
                <div class="time-availability-message" id="timeAvailabilityMessage">
                    <i class="fas fa-info-circle"></i> Select a date and time for your event
                </div>
                
                <div class="navigation-buttons">
                    <div></div> <!-- Empty div for flex spacing -->
                    <button type="button" class="next-btn" id="stepOneNextBtn">Continue <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- Step 2: Package & Theme Selection -->
            <div class="form-section" id="step2">
                <h2><i class="fas fa-box-open"></i> Selected Packages</h2>
                <p class="selected-package-info" id="selectedPackageInfo">View your selected package details below.</p>
                <div class="package-selector">
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            echo "<div class='package-card' data-package='" . htmlspecialchars(strtolower($row["name"])) . "' data-price='" . htmlspecialchars($row["price"]) . "'>";
                            // Add image at the top of the card
                            $imagePath = !empty($row["image_path"]) ? "../" . $row["image_path"] : "../img/bg1.png";
                            echo "<div class='package-image'><img src='" . $imagePath . "' alt='" . htmlspecialchars($row["name"]) . "' /></div>";
                            echo "<h3>" . htmlspecialchars($row["name"]) . "</h3>";
                            echo "<p>" . htmlspecialchars($row["description"]) . "</p>";
                            echo "<div class='package-price'>₱" . number_format(htmlspecialchars($row["price"])) . "</div>";
                            echo "</div>";
                        }
                    }
                    ?>
                </div>
                <h3><i class="fas fa-plus-circle"></i> Additional Services</h3>
                <div class="additional-services">
                    <?php
                    if ($services_result && $services_result->num_rows > 0) {
                        while($service = $services_result->fetch_assoc()) {
                            $service_id = $service['id'];
                            $service_icon = '';
                            
                            // Set appropriate icon based on service name
                            if (stripos($service['name'], 'band') !== false || stripos($service['name'], 'music') !== false) {
                                $service_icon = 'fa-music';
                            } elseif (stripos($service['name'], 'photo') !== false || stripos($service['name'], 'camera') !== false || stripos($service['name'], 'photography') !== false) {
                                $service_icon = 'fa-camera';
                            } elseif (stripos($service['name'], 'video') !== false || stripos($service['name'], 'film') !== false || stripos($service['name'], 'videography') !== false) {
                                $service_icon = 'fa-video';
                            } elseif (stripos($service['name'], 'catering') !== false || stripos($service['name'], 'food') !== false || stripos($service['name'], 'cake') !== false) {
                                $service_icon = 'fa-utensils';
                            } elseif (stripos($service['name'], 'flower') !== false || stripos($service['name'], 'decor') !== false || stripos($service['name'], 'decoration') !== false) {
                                $service_icon = 'fa-leaf';
                            } elseif (stripos($service['name'], 'host') !== false || stripos($service['name'], 'mc') !== false || stripos($service['name'], 'emcee') !== false) {
                                $service_icon = 'fa-microphone';
                            } elseif (stripos($service['name'], 'entertainment') !== false || stripos($service['name'], 'act') !== false || stripos($service['name'], 'show') !== false) {
                                $service_icon = 'fa-theater-masks';
                            } elseif (stripos($service['name'], 'booth') !== false || stripos($service['name'], 'photo booth') !== false) {
                                $service_icon = 'fa-camera-retro';
                            } elseif (stripos($service['name'], 'light') !== false || stripos($service['name'], 'sound') !== false) {
                                $service_icon = 'fa-lightbulb';
                            } elseif (stripos($service['name'], 'makeup') !== false || stripos($service['name'], 'hair') !== false || stripos($service['name'], 'stylist') !== false) {
                                $service_icon = 'fa-paint-brush';
                            } elseif (stripos($service['name'], 'transport') !== false || stripos($service['name'], 'car') !== false || stripos($service['name'], 'limo') !== false) {
                                $service_icon = 'fa-car';
                            } else {
                                $service_icon = 'fa-concierge-bell';
                            }
                            
                            echo "<div class='service-card' data-service-id='" . $service_id . "' onclick='openServiceModal(" . $service_id . ", \"" . htmlspecialchars($service["name"]) . "\")'>";
                            echo "<i class='fas " . $service_icon . " service-icon'></i>";
                            echo "<h4>" . htmlspecialchars($service["name"]) . "</h4>";
                            echo "<input type='hidden' class='service-input' name='services[]' value='" . htmlspecialchars(strtolower($service["name"])) . "' data-price='0' disabled>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p>No additional services available</p>";
                    }
                    ?>
                </div>
                <h3><i class="fas fa-paint-brush"></i> Event Theme</h3>
                <p class="theme-description">Choose a theme that complements your selected package or create a custom theme.</p>
                <!-- Custom Theme Option - Separated from regular themes -->
                <div class="custom-theme-container">
                    <div class="theme-option" data-theme="custom" data-packages="general">
                        <img src="../img/bg1.png" alt="Custom Theme" class="theme-preview">
                        <label>
                            <input type="radio" name="theme" value="custom"> Custom Theme
                        </label>
                        <p>Create your own unique theme by describing your vision</p>
                        <div style="display: flex; justify-content: center; width: 100%;">
                            <button type="button" class="btn custom-theme-btn" id="customThemeBtn">
                                <i class="fas fa-edit"></i> Customize
                            </button>
                        </div>
                    </div>
                </div>
                
                <h4 class="theme-separator"><span>Or Choose From Our Themes</span></h4>
                
                <div class="theme-selector" id="themeSelector">
                    <?php
                    // Initially show all themes
                    if ($themes_result && $themes_result->num_rows > 0) {
                        $themes_result->data_seek(0); // Reset result pointer
                        while($theme = $themes_result->fetch_assoc()) {
                            // Add data attributes for package IDs
                            $package_attr = !empty($theme['packages']) ? 'data-packages="' . htmlspecialchars($theme['packages']) . '"' : 'data-packages="general"';
                            
                            echo "<div class='theme-option' data-theme='" . strtolower($theme["name"]) . "' $package_attr>";
                            echo "<img src='../uploads/themes/" . $theme["image_path"] . "' alt='" . $theme["name"] . " Theme' class='theme-preview'>";
                            echo "<label>";
                            echo "<input type='radio' name='theme' value='" . strtolower($theme["name"]) . "'> " . $theme["name"];
                            echo "</label>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p>No themes available</p>";
                    }
                    ?>
                </div>
                <!-- Custom Theme Modal -->
                <div class="custom-theme-modal" id="customThemeModal">
                    <div class="custom-theme-modal-content">
                        <span class="close-modal">&times;</span>
                        <h3><i class="fas fa-magic"></i> Customize Your Theme</h3>
                        <div class="form-group">
                            <label for="customThemeName">Theme Name</label>
                            <input type="text" id="customThemeName" class="form-control" placeholder="Give your theme a name">
                        </div>
                        <div class="form-group">
                            <label for="customThemeDescription">Theme Description</label>
                            <textarea id="customThemeDescription" class="form-control" rows="4" placeholder="Describe your desired theme"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="customThemeImage">Reference Image (Optional)</label>
                            <input type="file" id="customThemeImage" class="form-control" accept="image/*">
                            <small class="form-text">Upload a reference image to help us understand your vision</small>
                        </div>
                        <div class="form-group">
                            <label for="customThemeColors">Color Preferences (Optional)</label>
                            <input type="text" id="customThemeColors" class="form-control" placeholder="e.g., blue and gold, pastel colors, etc.">
                        </div>
                        <button type="button" class="btn" id="saveCustomTheme">Save Custom Theme</button>
                    </div>
                </div>
                <div class="navigation-buttons">
                    <button type="button" class="prev-btn" onclick="prevStep(2)"><i class="fas fa-arrow-left"></i> Previous</button>
                    <button type="button" class="next-btn" onclick="nextStep(2)">Continue <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- Step 3: Guest List Management -->
            <div class="form-section" id="step3">
                <h2><i class="fas fa-users"></i> Guest List Management</h2>
                <div class="guest-list-section">
                    <!-- Add file upload section with centered message -->
                    <div class="form-group">
                        <div class="upload-container">
                            <label class="upload-label"><i class="fas fa-file-upload"></i> Upload Guest List</label>
                            <input type="file" id="guestListFile" class="form-control" accept=".csv,.xlsx,.xls">
                            <small class="form-text text-center">Upload your guest list in CSV or Excel format</small>
                            <div class="flex space-x-2 mt-4">
                                <button type="button" class="btn upload-btn" onclick="uploadGuestList()"><i class="fas fa-upload"></i> Upload List</button>
                                <button type="button" class="btn download-template-btn" style="background-color: #10b981;" onclick="downloadGuestTemplate()"><i class="fas fa-download"></i> Download Template</button>
                            </div>
                            <div id="uploadSuccessMessage" class="upload-success" style="display: none">
                                <i class="fas fa-check-circle"></i>
                                <span>Guest list uploaded successfully!</span>
                            </div>
                            <div id="uploadErrorMessage" class="upload-error" style="display: none">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Error uploading guest list</span>
                            </div>
                        </div>
                    </div>
                    <table class="guest-list-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="guestListBody">
                            <!-- Guest entries will be added here -->
                        </tbody>
                    </table>
                </div>
                <div class="navigation-buttons">
                    <button type="button" class="prev-btn" onclick="prevStep(3)"><i class="fas fa-arrow-left"></i> Previous</button>
                    <button type="button" class="next-btn" id="step3NextBtn" onclick="nextStep(3)">Continue <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- Step 4: Customer Details -->
            <div class="form-section" id="step4">
                <h2><i class="fas fa-user-circle"></i> Personal Details</h2>
                <div class="form-group">
                    <label for="fullName">Full Name</label>
                    <input type="text" id="fullName" name="fullName" class="form-control" value="<?php echo $user_data['name']; ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo $user_data['email']; ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="phone">Contact Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo $user_data['phone']; ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="specialRequests"><i class="fas fa-comment-alt"></i> Special Requests (Optional)</label>
                    <textarea id="specialRequests" name="specialRequests" class="form-control" rows="4" placeholder="Any dietary restrictions, accessibility needs, or special arrangements?"></textarea>
                </div>
                <div class="navigation-buttons">
                    <button type="button" class="prev-btn" onclick="prevStep(4)"><i class="fas fa-arrow-left"></i> Previous</button>
                    <button type="button" class="next-btn" onclick="nextStep(4)">Review Reservation <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- Step 5: Review & Confirmation -->
            <div class="form-section" id="step5">
                <h2><i class="fas fa-clipboard-check"></i> Review Your Reservation</h2>
                <div class="booking-summary">
                    <div class="summary-item">
                        <strong>Event Date:</strong>
                        <span id="summaryDate"></span>
                    </div>
                    <div class="summary-item">
                        <strong>Event Time:</strong>
                        <span id="summaryStartTime"></span> to <span id="summaryEndTime"></span>
                    </div>
                    <div class="summary-item">
                        <strong>Selected Package:</strong>
                        <span id="summaryPackage"></span>
                    </div>
                    <div class="summary-item">
                        <strong>Additional Services:</strong>
                        <span id="summaryServices"></span>
                    </div>
                    <div class="summary-item">
                        <strong>Theme:</strong>
                        <span id="summaryTheme"></span>
                    </div>
                    <div class="summary-item">
                        <strong>Number of Guests:</strong>
                        <span id="summaryGuests"></span>
                    </div>
                    <div class="summary-item">
                        <strong>Special Requests:</strong>
                        <span id="summaryRequests"></span>
                    </div>
                    <div class="total-amount">
                        Total Amount: <span id="summaryTotal">₱0.00</span>
                    </div>
                </div>
                <input type="hidden" name="package" id="selectedPackage">
                <input type="hidden" name="totalAmount" id="totalAmount">
                <input type="hidden" name="guests" id="guestListData">
                <input type="hidden" name="temp_booking_id" id="tempBookingId">
                <div class="navigation-buttons">
                    <button type="button" class="prev-btn" onclick="prevStep(5)">Previous</button>
                    <button type="submit" id="submitButton" class="submit-btn">Confirm Booking</button>
                </div>
            </div>
        </form>
    </div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="qr-modal">
        <div class="qr-modal-content">
            <span class="qr-close">&times;</span>
            <div class="qr-guest-info">
                <h3 id="qrGuestName"></h3>
            </div>
            <div class="qr-code-container">
                <div id="qrCodeDisplay" style="font-size: 28px; font-weight: bold; text-align: center; padding: 20px; letter-spacing: 2px; color: #4f46e5;"></div>
            </div>
            <p class="text-sm text-gray-500 mt-2">This is your unique guest code</p>
            <button class="btn-download-qr mt-4" id="downloadQrBtn">
                <i class="fas fa-copy"></i> Copy Code
            </button>
        </div>
    </div>

    <!-- Edit Guest Modal -->
    <div id="editGuestModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit Guest</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="editGuestName">Guest Name</label>
                    <input type="text" id="editGuestName" class="form-control" placeholder="Enter guest name">
                </div>
                <div class="form-group">
                    <label for="editGuestEmail">Email Address</label>
                    <input type="email" id="editGuestEmail" class="form-control" placeholder="Enter email address">
                </div>
                <div class="form-group">
                    <label for="editGuestPhone">Phone Number</label>
                    <input type="tel" id="editGuestPhone" class="form-control" placeholder="Enter phone number">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="cancelEditBtn">Cancel</button>
                <button type="button" class="btn-save" id="saveGuestBtn">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteGuestModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <p class="delete-message">Are you sure you want to remove <span id="deleteGuestName"></span> from your guest list?</p>
                <p class="delete-warning"><i class="fas fa-info-circle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="cancelDeleteBtn">Cancel</button>
                <button type="button" class="btn-delete-confirm" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <!-- Service Items Modal -->
    <div class="service-items-modal" id="serviceItemsModal">
        <div class="service-items-content">
            <div class="service-items-header">
                <h3 id="serviceItemsTitle">Select Service Items</h3>
                <button class="close-service-modal">&times;</button>
            </div>
            <div class="service-items-body">
                <div id="serviceItemsGrid" class="service-items-grid">
                    <!-- Service items will be loaded dynamically -->
                </div>
            </div>
            <div class="service-items-footer">
                <button id="confirmServiceItems">Confirm Selection</button>
            </div>
        </div>
    </div>

    <!-- Booking Confirmation Alert Modal -->
    <div id="bookingConfirmationModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header" style="background-color: #4f46e5; color: white;">
                <h3><i class="fas fa-check-circle"></i> Booking Confirmed</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body" style="text-align: center; padding: 30px 20px;">
                <div style="margin-bottom: 20px;">
                    <i class="fas fa-calendar-check" style="font-size: 4rem; color: #4f46e5; margin-bottom: 15px;"></i>
                    <h2 style="font-size: 1.8rem; color: #1e293b; margin-bottom: 10px;">Thank You!</h2>
                    <p id="bookingConfirmationMessage" style="font-size: 1.1rem; color: #4b5563; margin-bottom: 15px;"></p>
                    <div style="padding: 15px; background-color: #f3f4f6; border-radius: 8px; font-weight: 600; font-size: 1.2rem; color: #1e293b; margin-bottom: 20px;">
                        Booking Reference: <span id="bookingReferenceDisplay" style="color: #4f46e5;"></span>
                    </div>
                    <p style="font-size: 0.95rem; color: #6b7280;">We've sent a confirmation email with all the details to your registered email address.</p>
                </div>
            </div>
            <div class="modal-footer" style="justify-content: center; padding-bottom: 30px;">
                <button type="button" class="btn-save" id="confirmationOkBtn" style="padding: 12px 30px; width: 80%;">
                    <i class="fas fa-check"></i> Continue
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <!-- Flatpickr for better date picking -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Parse unavailable dates from PHP - these are dates that are already booked
        const unavailableDates = <?php echo json_encode($unavailable_dates); ?>;
        
        // Get selected package from PHP
        const preSelectedPackage = <?php echo $selected_package ? json_encode($selected_package) : 'null'; ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a selected package from PHP or URL parameters
            if (preSelectedPackage) {
                // Hide all packages except the selected one in step 2
                const packageCards = document.querySelectorAll('.package-card');
                packageCards.forEach(card => {
                    const packageName = card.dataset.package.toLowerCase();
                    if (packageName !== preSelectedPackage.toLowerCase()) {
                        card.style.display = 'none';
                    } else {
                        // Automatically select the package
                        card.classList.add('selected');
                        
                        // Update selected package info text
                        document.getElementById('selectedPackageInfo').textContent = `You have selected the ${packageName.charAt(0).toUpperCase() + packageName.slice(1)} package.`;
                        
                        // Filter themes based on the selected package
                        filterThemesByPackage(packageName);
                    }
                });
            } else {
                // If no package pre-selected, check URL parameters
                const urlParams = new URLSearchParams(window.location.search);
                const selectedPackageParam = urlParams.get('package');
                
                if (selectedPackageParam) {
                    // Hide all packages except the selected one in step 2
                    const packageCards = document.querySelectorAll('.package-card');
                    packageCards.forEach(card => {
                        const packageName = card.dataset.package.toLowerCase();
                        if (packageName !== selectedPackageParam.toLowerCase()) {
                            card.style.display = 'none';
                        } else {
                            // Automatically select the package
                            card.classList.add('selected');
                            
                            // Update selected package info text
                            document.getElementById('selectedPackageInfo').textContent = `You have selected the ${packageName.charAt(0).toUpperCase() + packageName.slice(1)} package.`;
                            
                            // Filter themes based on the selected package
                            filterThemesByPackage(packageName);
                        }
                    });
                }
            }
            
            const dateInput = document.getElementById('eventDate');
            const startTimeSelect = document.getElementById('eventStartTime');
            const endTimeSelect = document.getElementById('eventEndTime');
            const dateMessage = document.getElementById('dateAvailabilityMessage');
            const timeMessage = document.getElementById('timeAvailabilityMessage');
            const dateIndicator = document.getElementById('dateStatusIndicator');
            const stepOneNextBtn = document.getElementById('stepOneNextBtn');
            
            // Initialize Flatpickr date picker with unavailable dates disabled
            const datePicker = flatpickr(dateInput, {
                minDate: "today",
                dateFormat: "Y-m-d",
                disable: unavailableDates,
                onChange: function(selectedDates, dateStr, instance) {
                    handleDateSelection(dateStr);
                },
                onDayCreate: function(dObj, dStr, fp, dayElem) {
                    // Add special class to booked dates for custom styling
                    const dateStr = dayElem.dateObj.toISOString().split('T')[0];
                    if (unavailableDates.includes(dateStr)) {
                        dayElem.classList.add('booked-date');
                    }
                }
            });
            
            // Initially disable time selections and next button
            startTimeSelect.disabled = true;
            endTimeSelect.disabled = true;
            
            // Function to handle date selection
            function handleDateSelection(selectedDate) {
                if (selectedDate) {
                    if (isDateBooked(selectedDate)) {
                        // Date is fully booked - show unavailable indicator
                        dateIndicator.style.display = 'block';
                        dateIndicator.style.backgroundColor = '#e74c3c';
                        dateMessage.textContent = "⚠️ This date is already booked. Please select another date.";
                        dateMessage.style.color = "#e74c3c";
                        
                        // Disable time selections
                        startTimeSelect.disabled = true;
                        endTimeSelect.disabled = true;
                        startTimeSelect.value = "";
                        endTimeSelect.value = "";
                        
                        // Update time message
                        timeMessage.className = "time-availability-message unavailable";
                        timeMessage.innerHTML = '<i class="fas fa-times-circle"></i> This date is not available';
                    } else {
                        // Date is available - show available indicator
                        dateIndicator.style.display = 'block';
                        dateIndicator.style.backgroundColor = '#2ecc71';
                        dateMessage.textContent = "✅ This date is available!";
                        dateMessage.style.color = "#2ecc71";
                        
                        // Enable time selections
                        startTimeSelect.disabled = false;
                        endTimeSelect.disabled = false;
                        
                        // Update time message
                        timeMessage.className = "time-availability-message";
                        timeMessage.innerHTML = '<i class="fas fa-clock"></i> Please select a start and end time';
                    }
                } else {
                    // No date selected
                    dateIndicator.style.display = 'none';
                    dateMessage.textContent = "Please select a date for your event.";
                    dateMessage.style.color = "#6c757d";
                    
                    // Disable time selections
                    startTimeSelect.disabled = true;
                    endTimeSelect.disabled = true;
                }
                
                // Check if we can enable the next button
                validateStep1Fields();
            }
            
            // Handle time selections
            startTimeSelect.addEventListener('change', validateTimeSelection);
            endTimeSelect.addEventListener('change', validateTimeSelection);
            
            function validateTimeSelection() {
                const selectedDate = dateInput.value;
                const startTime = startTimeSelect.value;
                const endTime = endTimeSelect.value;
                
                if (!selectedDate || isDateBooked(selectedDate)) {
                    // If no date is selected or date is already booked, don't proceed
                    return;
                }
                
                if (startTime && endTime) {
                    if (endTime <= startTime) {
                        // End time must be after start time
                        timeMessage.className = "time-availability-message unavailable";
                        timeMessage.innerHTML = '<i class="fas fa-times-circle"></i> End time must be after start time';
                        endTimeSelect.classList.add('invalid');
                    } else {
                        // Valid time selection
                        timeMessage.className = "time-availability-message available";
                        timeMessage.innerHTML = '<i class="fas fa-check-circle"></i> Your selected time is available!';
                        startTimeSelect.classList.remove('invalid');
                        endTimeSelect.classList.remove('invalid');
                        
                        // Highlight the next button to draw attention
                        stepOneNextBtn.classList.add('next-btn-highlight');
                    }
                } else {
                    timeMessage.className = "time-availability-message";
                    timeMessage.innerHTML = '<i class="fas fa-clock"></i> Please select both start and end times';
                }
                
                // Check if we can enable the next button
                validateStep1Fields();
            }
            
            // Check if a date is already booked
            function isDateBooked(date) {
                return unavailableDates.includes(date);
            }
            
            // Validate step 1 fields to control next button state
            function validateStep1Fields() {
                const selectedDate = dateInput.value;
                const startTime = startTimeSelect.value;
                const endTime = endTimeSelect.value;
                
                // Enable next button only if:
                // 1. Date is selected and not booked
                // 2. Start and end times are selected
                // 3. End time is after start time
                
                if (selectedDate && !isDateBooked(selectedDate) && 
                    startTime && endTime && endTime > startTime) {
                    stepOneNextBtn.disabled = false;
                    return true;
                } else {
                    stepOneNextBtn.disabled = true;
                    stepOneNextBtn.classList.remove('next-btn-highlight');
                    return false;
                }
            }
            
            // Connect the next button to the nextStep function
            stepOneNextBtn.addEventListener('click', function() {
                if (validateStep1Fields()) {
                    nextStep(1);
                }
            });
            
            // Theme option selection
            const themeOptions = document.querySelectorAll('.theme-option');
            themeOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Find the radio button within this option
                    const radioBtn = this.querySelector('input[type="radio"]');
                    if (radioBtn) {
                        radioBtn.checked = true;
                        
                        // Remove selected class from all options
                        themeOptions.forEach(opt => opt.classList.remove('selected'));
                        
                        // Add selected class to clicked option
                        this.classList.add('selected');
                    }
                });
            });
        });

        let currentStep = 1;
        const totalSteps = 5;
        let guestList = [];

        function nextStep(step) {
            if (validateStep(step)) {
                if (step === 1) {
                    // Check if a package was pre-selected (from URL or elsewhere)
                    const selectedPackage = document.querySelector('.package-card.selected');
                    
                    if (selectedPackage) {
                        // Hide all other packages, showing only the selected one
                        const packageCards = document.querySelectorAll('.package-card');
                        packageCards.forEach(card => {
                            if (card !== selectedPackage) {
                                card.style.display = 'none';
                            }
                        });
                        
                        // Filter themes based on the selected package
                        filterThemesByPackage(selectedPackage.dataset.package);
                    }
                }
                
                if (step === 4) {
                    updateBookingSummary();
                }
                document.getElementById(`step${step}`).classList.remove('active');
                document.getElementById(`step${step + 1}`).classList.add('active');
                document.querySelector(`.step:nth-child(${step})`).classList.add('completed');
                document.querySelector(`.step:nth-child(${step + 1})`).classList.add('active');
                currentStep = step + 1;
                
                // Scroll to top of form
                document.querySelector('.container').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }

        function prevStep(step) {
            document.getElementById(`step${step}`).classList.remove('active');
            document.getElementById(`step${step - 1}`).classList.add('active');
            document.querySelector(`.step:nth-child(${step})`).classList.remove('active');
            document.querySelector(`.step:nth-child(${step - 1})`).classList.add('active');
            currentStep = step - 1;
            
            // Scroll to top of form
            document.querySelector('.container').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        function validateStep(step) {
            const currentSection = document.getElementById(`step${step}`);
            let valid = true;

            switch(step) {
                case 1:
                    // Validate Event Selection
                    const dateInput = document.getElementById('eventDate');
                    const startTimeSelect = document.getElementById('eventStartTime');
                    const endTimeSelect = document.getElementById('eventEndTime');
                    
                    const selectedDate = dateInput.value;
                    const startTime = startTimeSelect.value;
                    const endTime = endTimeSelect.value;

                    if (!selectedDate || !startTime || !endTime) {
                        valid = false;
                        if (!selectedDate) {
                            alert('Please select a date for your event');
                            // Since Flatpickr is being used, we can open the calendar
                            datePicker.open();
                        }
                        if (!startTime) highlightInvalid('eventStartTime');
                        if (!endTime) highlightInvalid('eventEndTime');
                    } else if (isDateBooked(selectedDate)) {
                        valid = false;
                        alert('This date is already booked. Please select another date.');
                        datePicker.open();
                    } else if (endTime <= startTime) {
                        valid = false;
                        alert('End time must be after start time.');
                        highlightInvalid('eventEndTime');
                    }
                    break;

                case 2:
                    // Validate Package & Theme Selection
                    const selectedPackage = document.querySelector('.package-card.selected');
                    const selectedTheme = document.querySelector('input[name="theme"]:checked');

                    if (!selectedPackage || !selectedTheme) {
                        valid = false;
                        if (!selectedPackage) alert('Please select a package');
                        if (!selectedTheme) alert('Please select a theme');
                    }
                    break;

                case 3:
                    // Validate Guest List
                    if (guestList.length === 0) {
                        valid = false;
                        alert('Please add at least one guest to continue');
                    }
                    break;

                case 4:
                    // Validate Special Requests (optional)
                    // Since special requests are optional, we don't need validation here
                    valid = true;
                    break;
            }

            if (!valid) {
                alert('Please fill in all required fields before proceeding');
            }

            return valid;
        }

        // Check if a date is already booked
        function isDateBooked(date) {
            return unavailableDates.includes(date);
        }

        // Add this helper function to highlight invalid fields
        function highlightInvalid(elementId) {
            const element = document.getElementById(elementId);
            element.classList.add('invalid');
            element.style.borderColor = '#dc3545';
            
            // Remove highlight when user starts typing/selecting
            element.addEventListener('input', function() {
                if (this.value) {
                    this.classList.remove('invalid');
                    this.style.borderColor = '';
                }
            });
        }

        function updateBookingSummary() {
            document.getElementById('summaryDate').textContent = document.getElementById('eventDate').value;
            document.getElementById('summaryStartTime').textContent = document.getElementById('eventStartTime').value;
            document.getElementById('summaryEndTime').textContent = document.getElementById('eventEndTime').value;
            
            const selectedPackage = document.querySelector('.package-card.selected');
            if (selectedPackage) {
                document.getElementById('summaryPackage').textContent = selectedPackage.querySelector('h3').textContent;
                document.getElementById('selectedPackage').value = selectedPackage.dataset.package;
            }

            // Update service summary with selected service cards
            const selectedServices = Array.from(document.querySelectorAll('.service-input:not([disabled])'))
                .map(input => input.parentElement.querySelector('h4').textContent.trim());
            document.getElementById('summaryServices').textContent = selectedServices.length ? selectedServices.join(', ') : 'None';

            const selectedTheme = document.querySelector('input[name="theme"]:checked');
            if (selectedTheme) {
                if (selectedTheme.value === 'custom' && customThemeData) {
                    document.getElementById('summaryTheme').textContent = `Custom: ${customThemeData.name}`;
                } else {
                    document.getElementById('summaryTheme').textContent = selectedTheme.value;
                }
            } else {
                document.getElementById('summaryTheme').textContent = 'None';
            }

            document.getElementById('summaryGuests').textContent = guestList.length;
            document.getElementById('summaryRequests').textContent = document.getElementById('specialRequests').value || 'None';

            // Calculate total amount
            let total = 0;
            if (selectedPackage) {
                total += parseFloat(selectedPackage.dataset.price);
            }
            
            // Use the enabled service inputs for total calculation
            document.querySelectorAll('.service-input:not([disabled])').forEach(service => {
                total += parseFloat(service.getAttribute('data-price')) || 0;
            });
            
            document.getElementById('summaryTotal').textContent = `₱${total.toFixed(2)}`;
            document.getElementById('totalAmount').value = total;
            
            // Store guest list data
            document.getElementById('guestListData').value = JSON.stringify(guestList);
        }

        function uploadGuestList() {
            const fileInput = document.getElementById('guestListFile');
            const file = fileInput.files[0];
            const successMessage = document.getElementById('uploadSuccessMessage');
            const errorMessage = document.getElementById('uploadErrorMessage');
            
            // Hide any existing messages
            successMessage.style.display = 'none';
            errorMessage.style.display = 'none';
            
            if (!file) {
                errorMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Please select a file first</span>';
                errorMessage.style.display = 'flex';
                setTimeout(() => { errorMessage.style.display = 'none'; }, 5000);
                return;
            }
            
            // Validate file extension
            const fileExtension = file.name.split('.').pop().toLowerCase();
            const validExtensions = ['csv', 'xlsx', 'xls'];
            
            if (!validExtensions.includes(fileExtension)) {
                errorMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Invalid file format. Please upload CSV or Excel file</span>';
                errorMessage.style.display = 'flex';
                setTimeout(() => { errorMessage.style.display = 'none'; }, 5000);
                return;
            }
            
            // Show processing message
            successMessage.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Processing file...</span>';
            successMessage.style.display = 'flex';
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });

                    // Validate if file has data
                    if (!jsonData || jsonData.length <= 1) {
                        throw new Error('The file appears to be empty or contains only headers');
                    }
                    
                    // Keep track of previously added guests to avoid duplicates
                    const existingNames = new Set(guestList.map(g => g.name.toLowerCase()));
                    let addedCount = 0;
                    let skippedCount = 0;
                    const newGuests = [];
                    
                    // Skip header row and process data
                    for (let i = 1; i < jsonData.length; i++) {
                        const row = jsonData[i];
                        if (row && row.length > 0 && row[0]) { // Ensure name exists
                            const name = String(row[0]).trim();
                            
                            // Skip if name is empty or already exists
                            if (!name || existingNames.has(name.toLowerCase())) {
                                skippedCount++;
                                continue;
                            }
                            
                            const guest = {
                                name: name,
                                email: row[1] ? String(row[1]).trim() : '',
                                phone: row[2] ? String(row[2]).trim() : ''
                            };
                            
                            guestList.push(guest);
                            newGuests.push(guest);
                            existingNames.add(name.toLowerCase());
                            addedCount++;
                        } else {
                            skippedCount++;
                        }
                    }

                    // Update the table UI
                    updateGuestTable();
                    fileInput.value = '';
                    
                    // If we added guests, save them to session for persistence
                    if (addedCount > 0) {
                        // Store in session via AJAX to ensure persistence
                        saveGuestsToSession(newGuests);
                        
                        successMessage.innerHTML = `<i class="fas fa-check-circle"></i><span>${addedCount} guest${addedCount !== 1 ? 's' : ''} added successfully${skippedCount > 0 ? ` (${skippedCount} skipped)` : ''}!</span>`;
                    } else {
                        successMessage.innerHTML = `<i class="fas fa-info-circle"></i><span>No new guests were added. ${skippedCount} entries were skipped (empty or duplicate names).</span>`;
                    }
                    
                    // Hide the success message after 5 seconds
                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 5000);
                    
                } catch (error) {
                    console.error('Error processing file:', error);
                    errorMessage.innerHTML = `<i class="fas fa-exclamation-circle"></i><span>${error.message || 'Error processing file. Please make sure it\'s a valid Excel or CSV file.'}</span>`;
                    successMessage.style.display = 'none';
                    errorMessage.style.display = 'flex';
                    setTimeout(() => { errorMessage.style.display = 'none'; }, 5000);
                }
            };
            
            reader.onerror = function() {
                errorMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Error reading file. Please try again.</span>';
                successMessage.style.display = 'none';
                errorMessage.style.display = 'flex';
                setTimeout(() => { errorMessage.style.display = 'none'; }, 5000);
            };
            
            reader.readAsArrayBuffer(file);
        }
        
        // Function to save guests to session storage
        function saveGuestsToSession(guests) {
            const formData = new FormData();
            formData.append('guests', JSON.stringify(guests));
            
            // Show saving indicator
            const successMessage = document.getElementById('uploadSuccessMessage');
            successMessage.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Saving guests...</span>';
            successMessage.style.display = 'flex';
            
            // Use our new endpoint that saves to the database
            fetch('save_guests_to_db.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Guests saved to database:', data);
                
                if (data.success) {
                    // Store the temporary booking ID if provided
                    if (data.temp_booking_id) {
                        document.getElementById('tempBookingId').value = data.temp_booking_id;
                    }
                    
                    // Update the guestList with unique codes
                    if (data.unique_codes && data.unique_codes.length > 0) {
                        data.unique_codes.forEach(codeData => {
                            const guestIndex = guestList.findIndex(g => g.name === codeData.name);
                            if (guestIndex !== -1) {
                                guestList[guestIndex].unique_code = codeData.unique_code;
                            }
                        });
                        
                        // Update the table to show unique codes are available
                        updateGuestTable();
                    }
                    
                    // Update success message
                    successMessage.innerHTML = `<i class="fas fa-check-circle"></i><span>${data.message}</span>`;
                } else {
                    // Show error message
                    const errorMessage = document.getElementById('uploadErrorMessage');
                    errorMessage.innerHTML = `<i class="fas fa-exclamation-circle"></i><span>${data.message}</span>`;
                    errorMessage.style.display = 'flex';
                    successMessage.style.display = 'none';
                    
                    // Hide error after 5 seconds
                    setTimeout(() => {
                        errorMessage.style.display = 'none';
                    }, 5000);
                }
            })
            .catch(error => {
                console.error('Error saving guests to database:', error);
                
                // Show error message
                const errorMessage = document.getElementById('uploadErrorMessage');
                errorMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Error saving guests to database</span>';
                errorMessage.style.display = 'flex';
                successMessage.style.display = 'none';
                
                // Hide error after 5 seconds
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 5000);
            });
        }

        function updateGuestTable() {
            const tbody = document.getElementById('guestListBody');
            tbody.innerHTML = '';
            
            if (guestList.length === 0) {
                // Empty state
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="empty-state">
                            <div class="empty-icon"><i class="fas fa-users-slash"></i></div>
                            <p>No guests added yet</p>
                            <p class="empty-hint">Upload a CSV file or add guests manually</p>
                        </td>
                    </tr>
                `;
                
                // Hide the Continue button if no guests
                document.getElementById('step3NextBtn').style.display = 'none';
                
            } else {
                guestList.forEach((guest, index) => {
                    const tr = document.createElement('tr');
                    
                    tr.innerHTML = `
                        <td class="guest-name">${guest.name}</td>
                        <td>${guest.email || '-'}</td>
                        <td>${guest.phone || '-'}</td>
                        <td class="actions-cell">
                            <div class="action-buttons">
                                <button type="button" onclick="editGuest(${index})" class="action-btn edit-btn" title="Edit Guest">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" onclick="deleteGuest(${index})" class="action-btn delete-btn" title="Delete Guest">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button type="button" onclick="viewUniqueCode(${index})" class="action-btn view-qr-btn" title="View Guest Code">
                                    <i class="fas fa-eye"></i> View Code
                                </button>
                            </div>
                        </td>
                    `;
                    
                    tbody.appendChild(tr);
                });
                
                // Show the Continue button if there are guests
                document.getElementById('step3NextBtn').style.display = 'block';
            }
            
            // Update the hidden input with the guest list data
            document.getElementById('guestListData').value = JSON.stringify(guestList);
        }

        function editGuest(index) {
            const guest = guestList[index];
            const modal = document.getElementById('editGuestModal');
            
            // Fill the form with guest data
            document.getElementById('editGuestName').value = guest.name;
            document.getElementById('editGuestEmail').value = guest.email;
            document.getElementById('editGuestPhone').value = guest.phone;
            
            // Store the current index for use in save function
            modal.dataset.guestIndex = index;
            
            // Show the modal
            modal.classList.add('show');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function deleteGuest(index) {
            const guest = guestList[index];
            const modal = document.getElementById('deleteGuestModal');
            
            // Show guest name in the confirmation message
            document.getElementById('deleteGuestName').textContent = guest.name;
            
            // Store the current index for use in confirm function
            modal.dataset.guestIndex = index;
            
            // Show the modal
            modal.classList.add('show');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
        
        function addGuestManually() {
            // Prompt for guest details
            const guestName = prompt('Enter guest name:');
            if (!guestName) return; // Cancel if no name is provided
            
            const guestEmail = prompt('Enter guest email (optional):');
            const guestPhone = prompt('Enter guest phone (optional):');
            
            // Add new guest to the list
            guestList.push({
                name: guestName,
                email: guestEmail || '',
                phone: guestPhone || ''
            });
            
            // Update the table
            updateGuestTable();
            
            // Show success message
            const successMessage = document.getElementById('uploadSuccessMessage');
            successMessage.innerHTML = '<i class="fas fa-check-circle"></i><span>Guest added successfully!</span>';
            successMessage.style.display = 'flex';
            
            // Hide the success message after 5 seconds
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 5000);
        }

        // Package selection with theme filtering
        const packageCards = document.querySelectorAll('.package-card');
        const themeOptions = document.querySelectorAll('.theme-option');
        
        packageCards.forEach(card => {
            card.addEventListener('click', () => {
                // Update package selection
                packageCards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                
                // Get selected package ID/name
                const selectedPackage = card.dataset.package;
                
                // Update selected package info text
                document.getElementById('selectedPackageInfo').textContent = `You have selected the ${selectedPackage.charAt(0).toUpperCase() + selectedPackage.slice(1)} package.`;
                
                // Filter themes based on the selected package
                filterThemesByPackage(selectedPackage);
            });
        });
        
        function filterThemesByPackage(packageName) {
            // Convert package name to lowercase for comparison
            packageName = packageName.toLowerCase();
            
            // First hide all themes in the theme selector
            // Exclude the custom theme option which is outside the theme selector
            const regularThemeOptions = Array.from(document.querySelectorAll('#themeSelector .theme-option'));
            regularThemeOptions.forEach(theme => {
                theme.style.display = 'none';
                
                // Uncheck any selected radio buttons in hidden themes
                const radio = theme.querySelector('input[type="radio"]');
                if (radio && radio.checked) {
                    radio.checked = false;
                }
            });
            
            // Find package ID from name
            let packageId = null;
            
            // This assumes your package cards have a data-id attribute
            packageCards.forEach(card => {
                if (card.dataset.package.toLowerCase() === packageName) {
                    packageId = card.dataset.id;
                }
            });
            
            // Show themes that match the selected package
            regularThemeOptions.forEach(theme => {
                const themePackages = theme.dataset.packages ? theme.dataset.packages.split(',') : [];
                
                // Show theme if it matches the package ID or is a general theme
                if (packageId && themePackages.includes(packageId) || 
                    themePackages.includes('general') || 
                    themePackages.length === 0) {
                    theme.style.display = 'block';
                }
            });
            
            // Update section heading to reflect selected package
            const themeHeading = document.querySelector('#step2 h3:nth-of-type(2)');
            if (themeHeading) {
                themeHeading.innerHTML = `<i class="fas fa-paint-brush"></i> ${packageName.charAt(0).toUpperCase() + packageName.slice(1)} Themes`;
            }
            
            // Add a message if no themes are available for this package
            const visibleThemes = regularThemeOptions.filter(theme => 
                theme.style.display !== 'none'
            );
            
            const themeSelector = document.getElementById('themeSelector');
            const noThemesMessage = document.getElementById('noThemesMessage');
            
            // Remove existing message if it exists
            if (noThemesMessage) {
                noThemesMessage.remove();
            }
            
            // Show message if no themes available
            if (visibleThemes.length === 0) {
                const message = document.createElement('p');
                message.id = 'noThemesMessage';
                message.className = 'text-center p-4 bg-gray-100 rounded-lg';
                message.innerHTML = `<i class="fas fa-info-circle text-blue-500"></i> No specific themes available for ${packageName}. Please contact us for custom theme options.`;
                themeSelector.appendChild(message);
            }
        }
        
        // Update the package cards to include package IDs
        document.addEventListener('DOMContentLoaded', function() {
            // This mapping should be generated from PHP
            const packageMapping = <?php 
                $mapping = [];
                foreach ($package_mapping as $id => $name) {
                    $mapping[strtolower($name)] = $id;
                }
                echo json_encode($mapping); 
            ?>;
            
            // Add package IDs to the cards
            packageCards.forEach(card => {
                const packageName = card.dataset.package.toLowerCase();
                if (packageMapping[packageName]) {
                    card.dataset.id = packageMapping[packageName];
                }
            });
            
            // Add event listener for step3NextBtn
            const step3NextBtn = document.getElementById('step3NextBtn');
            if (step3NextBtn) {
                step3NextBtn.addEventListener('click', function() {
                    nextStep(3);
                });
            }
        });

        // Form submission
        document.getElementById('eventBookingForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (validateStep(currentStep)) {
                try {
                    const formData = new FormData(e.target);
                    
                    // Add guest list data - ensure it's properly formatted
                    if (guestList && guestList.length > 0) {
                        console.log("Adding guest list to form data:", guestList);
                        formData.set('guests', JSON.stringify(guestList));
                    } else {
                        console.log("No guests to add");
                        formData.set('guests', JSON.stringify([]));
                    }
                    
                    // Add selected services using the new approach
                    const selectedServices = Array.from(document.querySelectorAll('.service-input:not([disabled])'))
                        .map(input => input.value);
                    formData.delete('services[]'); // Remove old services data
                    selectedServices.forEach(service => {
                        formData.append('services[]', service);
                    });
                    
                    // Add selected service items if any
                    const selectedServiceItems = Array.from(document.querySelectorAll('.item-select-input:checked'))
                        .map(input => input.value);
                    if (selectedServiceItems.length > 0) {
                        selectedServiceItems.forEach(itemId => {
                            formData.append('service_items[]', itemId);
                        });
                    }

                    // Handle custom theme data
                    if (formData.get('theme') === 'custom' && customThemeData) {
                        formData.append('customThemeName', customThemeData.name);
                        formData.append('customThemeDescription', customThemeData.description);
                        formData.append('customThemeColors', customThemeData.colors);
                        
                        // Append the image file if it exists
                        if (customThemeData.imageFile) {
                            formData.append('customThemeImage', customThemeData.imageFile);
                        }
                    }

                    // Show loading indicator 
                    document.getElementById('submitButton').disabled = true;
                    document.getElementById('submitButton').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

                    const response = await fetch('booking-form.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    let data;
                    try {
                        data = await response.json();
                    } catch (parseError) {
                        console.error('Error parsing response as JSON:', parseError);
                        throw new Error('Server returned an invalid response. Please try again.');
                    }
                    
                    if (data.success) {
                        // Get the booking reference from the response
                        const bookingRef = data.message.match(/Booking reference: ([A-Z0-9]+)/)?.[1] || 'Unknown';
                        
                        // Show custom confirmation modal instead of alert
                        document.getElementById('bookingConfirmationMessage').textContent = 'Your booking has been confirmed!';
                        document.getElementById('bookingReferenceDisplay').textContent = bookingRef;
                        
                        // Show the confirmation modal
                        const confirmationModal = document.getElementById('bookingConfirmationModal');
                        confirmationModal.classList.add('show');
                        document.body.style.overflow = 'hidden'; // Prevent background scrolling
                        
                        // Set up OK button
                        document.getElementById('confirmationOkBtn').onclick = function() {
                            window.location.href = 'booking-success.php';
                        };
                        
                        // Also set up close button
                        confirmationModal.querySelector('.modal-close').onclick = function() {
                            window.location.href = 'booking-success.php';
                        };
                    } else {
                        throw new Error(data.message || 'An error occurred while saving the booking');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert(error.message || 'An error occurred while saving the booking');
                } finally {
                    // Reset the submit button
                    document.getElementById('submitButton').disabled = false;
                    document.getElementById('submitButton').innerHTML = 'Submit Booking';
                }
            }
        });

        // Custom Theme Modal Functionality
        let customThemeData = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('customThemeModal');
            const customBtn = document.getElementById('customThemeBtn');
            const closeBtn = document.querySelector('.close-modal');
            const saveBtn = document.getElementById('saveCustomTheme');
            const customThemeOption = document.querySelector('.custom-theme-container .theme-option');
            const customImageInput = document.getElementById('customThemeImage');
            
            // Open modal when customize button is clicked
            customBtn.addEventListener('click', function() {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
            });
            
            // Close modal functions
            closeBtn.addEventListener('click', closeModal);
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
            
            function closeModal() {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto'; // Restore scrolling
            }
            
            // Image preview functionality
            customImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const preview = document.createElement('img');
                        preview.id = 'imagePreview';
                        preview.src = e.target.result;
                        preview.classList.add('custom-theme-preview');
                        
                        // Remove any existing preview
                        const existingPreview = document.getElementById('imagePreview');
                        if (existingPreview) {
                            existingPreview.remove();
                        }
                        
                        // Add the new preview
                        customImageInput.parentNode.appendChild(preview);
                        preview.style.display = 'block';
                    };
                    
                    reader.readAsDataURL(file);
                }
            });
            
            // Save custom theme data
            saveBtn.addEventListener('click', function() {
                const themeName = document.getElementById('customThemeName').value;
                const themeDescription = document.getElementById('customThemeDescription').value;
                const themeColors = document.getElementById('customThemeColors').value;
                const imageFile = customImageInput.files[0];
                
                if (!themeName || !themeDescription) {
                    alert('Please enter at least a name and description for your custom theme.');
                    return;
                }
                
                // Store custom theme data
                customThemeData = {
                    name: themeName,
                    description: themeDescription,
                    colors: themeColors,
                    imageFile: imageFile
                };
                
                // Update the custom theme option to show it's been customized
                customThemeOption.classList.add('selected');
                const customRadio = customThemeOption.querySelector('input[type="radio"]');
                customRadio.checked = true;
                
                // Add a visual indicator to the custom theme option
                const existingDetails = customThemeOption.querySelector('.custom-theme-details');
                if (existingDetails) {
                    existingDetails.remove();
                }
                
                const detailsEl = document.createElement('div');
                detailsEl.classList.add('custom-theme-details');
                detailsEl.innerHTML = `
                    <strong>${themeName}</strong>
                    <p>${themeDescription.substring(0, 50)}${themeDescription.length > 50 ? '...' : ''}</p>
                `;
                
                customThemeOption.appendChild(detailsEl);
                
                // Close the modal
                closeModal();
                
                // Remove selected class from other themes
                document.querySelectorAll('.theme-option').forEach(theme => {
                    if (theme !== customThemeOption) {
                        theme.classList.remove('selected');
                    }
                });
            });
        });

        // Unique Code functionality
        function viewUniqueCode(index) {
            const guest = guestList[index];
            const modal = document.getElementById('qrModal');
            const codeDisplay = document.getElementById('qrCodeDisplay');
            const guestNameEl = document.getElementById('qrGuestName');
            const downloadBtn = document.getElementById('downloadQrBtn');
            
            // Set guest information - only name is displayed
            guestNameEl.textContent = guest.name;
            
            // Clear previous code display
            codeDisplay.innerHTML = '';
            
            // Check if guest already has a saved unique code
            if (guest.unique_code) {
                // Display the unique code
                codeDisplay.textContent = guest.unique_code;
                
                // Set up copy button
                downloadBtn.onclick = function() {
                    // Copy code to clipboard
                    navigator.clipboard.writeText(guest.unique_code).then(function() {
                        // Temporarily change button text to show success
                        const originalText = downloadBtn.innerHTML;
                        downloadBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                        setTimeout(() => {
                            downloadBtn.innerHTML = originalText;
                        }, 2000);
                    }).catch(function(err) {
                        console.error('Could not copy text: ', err);
                        alert('Failed to copy code to clipboard');
                    });
                };
            } else {
                codeDisplay.textContent = 'No code available';
                downloadBtn.style.display = 'none';
            }
            
            // Show the modal
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
        
        // QR Modal close functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('qrModal');
            const closeBtn = document.querySelector('.qr-close');
            
            closeBtn.addEventListener('click', function() {
                closeQRModal();
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeQRModal();
                }
            });
            
            function closeQRModal() {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto'; // Restore scrolling
            }
        });
        
        // Initialize modal functionality for edit and delete guest modals
        document.addEventListener('DOMContentLoaded', function() {
            // Edit Modal functionality
            const editModal = document.getElementById('editGuestModal');
            const editCloseBtns = editModal.querySelectorAll('.modal-close, #cancelEditBtn');
            const saveGuestBtn = document.getElementById('saveGuestBtn');
            
            // Delete Modal functionality
            const deleteModal = document.getElementById('deleteGuestModal');
            const deleteCloseBtns = deleteModal.querySelectorAll('.modal-close, #cancelDeleteBtn');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            
            // Close Edit Modal
            editCloseBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    editModal.classList.remove('show');
                    document.body.style.overflow = 'auto';
                });
            });
            
            // Close Delete Modal
            deleteCloseBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    deleteModal.classList.remove('show');
                    document.body.style.overflow = 'auto';
                });
            });
            
            // Close modals when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === editModal) {
                    editModal.classList.remove('show');
                    document.body.style.overflow = 'auto';
                }
                if (event.target === deleteModal) {
                    deleteModal.classList.remove('show');
                    document.body.style.overflow = 'auto';
                }
            });
            
            // Save guest changes
            saveGuestBtn.addEventListener('click', function() {
                const index = parseInt(editModal.dataset.guestIndex);
                const newName = document.getElementById('editGuestName').value;
                const newEmail = document.getElementById('editGuestEmail').value;
                const newPhone = document.getElementById('editGuestPhone').value;
                
                if (newName) {
                    guestList[index] = {
                        name: newName,
                        email: newEmail || '',
                        phone: newPhone || ''
                    };
                    updateGuestTable();
                    editModal.classList.remove('show');
                    document.body.style.overflow = 'auto';
                    
                    // Show success message
                    const successMessage = document.getElementById('uploadSuccessMessage');
                    successMessage.innerHTML = '<i class="fas fa-check-circle"></i><span>Guest updated successfully!</span>';
                    successMessage.style.display = 'flex';
                    
                    // Hide the success message after 3 seconds
                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 3000);
                } else {
                    alert('Guest name is required');
                }
            });
            
            // Confirm guest deletion
            confirmDeleteBtn.addEventListener('click', function() {
                const index = parseInt(deleteModal.dataset.guestIndex);
                guestList.splice(index, 1);
                updateGuestTable();
                deleteModal.classList.remove('show');
                document.body.style.overflow = 'auto';
                
                // Show success message
                const successMessage = document.getElementById('uploadSuccessMessage');
                successMessage.innerHTML = '<i class="fas fa-check-circle"></i><span>Guest removed successfully!</span>';
                successMessage.style.display = 'flex';
                
                // Hide the success message after 3 seconds
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 3000);
            });
        });

        // Add the following JavaScript to handle the service items modal
        document.addEventListener('DOMContentLoaded', function() {
            // Service Items Modal functionality
            const serviceItemsModal = document.getElementById('serviceItemsModal');
            const serviceItemsTitle = document.getElementById('serviceItemsTitle');
            const serviceItemsGrid = document.getElementById('serviceItemsGrid');
            const confirmServiceItemsBtn = document.getElementById('confirmServiceItems');
            const closeServiceModalBtn = document.querySelector('.close-service-modal');
            let currentServiceId = null;
            let currentServiceCard = null;
            
            // Service items data
            const serviceItemsData = <?php echo json_encode($service_items); ?>;
            
            // Function to open service modal
            window.openServiceModal = function(serviceId, serviceName) {
                currentServiceId = serviceId;
                currentServiceCard = document.querySelector(`.service-card[data-service-id="${serviceId}"]`);
                
                // Set modal title
                serviceItemsTitle.textContent = `Select ${serviceName} Items`;
                
                // Load service items into the grid
                if (serviceItemsData[serviceId] && serviceItemsData[serviceId].length > 0) {
                    let itemsHtml = '';
                    serviceItemsData[serviceId].forEach(item => {
                        itemsHtml += `
                        <div class="service-item-card" data-item-id="${item.id}">
                            ${item.image_path ? 
                            `<img src="../uploads/service_items/${item.image_path}" alt="${item.name}" class="item-image">` : 
                            `<div class="item-image-placeholder"><i class="fas fa-image"></i></div>`}
                            <div class="service-item-select"><i class="fas fa-check"></i></div>
                            <div class="item-details">
                                <h4>${item.name}</h4>
                                <div class="item-price">${item.price_range || 'Price on request'}</div>
                            </div>
                            <input type="checkbox" class="item-select-input" name="service_items[]" value="${item.id}" data-service-id="${serviceId}">
                        </div>`;
                    });
                    serviceItemsGrid.innerHTML = itemsHtml;
                    
                    // Add click event to service item cards
                    document.querySelectorAll('.service-item-card').forEach(card => {
                        card.addEventListener('click', function() {
                            const checkbox = this.querySelector('.item-select-input');
                            const selectIndicator = this.querySelector('.service-item-select');
                            
                            checkbox.checked = !checkbox.checked;
                            if (checkbox.checked) {
                                selectIndicator.classList.add('selected');
                            } else {
                                selectIndicator.classList.remove('selected');
                            }
                        });
                    });
                } else {
                    serviceItemsGrid.innerHTML = `
                    <div class="no-service-items">
                        <i class="fas fa-info-circle"></i>
                        <p>No items available for this service.</p>
                    </div>`;
                }
                
                // Show modal
                serviceItemsModal.classList.add('show');
            };
            
            // Close modal when clicking the close button
            closeServiceModalBtn.addEventListener('click', function() {
                serviceItemsModal.classList.remove('show');
            });
            
            // Close modal when clicking outside the content
            serviceItemsModal.addEventListener('click', function(e) {
                if (e.target === serviceItemsModal) {
                    serviceItemsModal.classList.remove('show');
                }
            });
            
            // Confirm selection button
            confirmServiceItemsBtn.addEventListener('click', function() {
                const selectedItems = document.querySelectorAll('.item-select-input:checked');
                
                // If any items are selected, mark the service as selected
                if (selectedItems.length > 0) {
                    if (currentServiceCard) {
                        currentServiceCard.classList.add('selected');
                        const serviceInput = currentServiceCard.querySelector('.service-input');
                        serviceInput.disabled = false;
                    }
                } else {
                    if (currentServiceCard) {
                        currentServiceCard.classList.remove('selected');
                        const serviceInput = currentServiceCard.querySelector('.service-input');
                        serviceInput.disabled = true;
                    }
                }
                
                // Update the summary
                updateSummary();
                
                // Close the modal
                serviceItemsModal.classList.remove('show');
            });

            // Function to update the summary display
            function updateSummary() {
                // Get selected services for summary
                const selectedServices = Array.from(document.querySelectorAll('.service-input:not([disabled])'))
                    .map(input => input.parentElement.querySelector('h4').textContent);
                
                document.getElementById('summaryServices').textContent = selectedServices.length ? selectedServices.join(', ') : 'None';
                
                // Update total calculation
                calculateTotal();
            }
        });

        // Replace the service-related part of calculateTotal function
        function calculateTotal() {
            // Calculate total amount
            let total = 0;
            
            // Add package price
            const selectedPackage = document.querySelector('.package-card.selected');
            if (selectedPackage) {
                total += parseFloat(selectedPackage.dataset.price) || 0;
            }
            
            // For each selected service, use the price from service items
            const selectedServices = document.querySelectorAll('.service-input:not([disabled])');
            selectedServices.forEach(service => {
                // Get service ID from parent card
                const serviceCard = service.closest('.service-card');
                if (serviceCard) {
                    const serviceId = serviceCard.dataset.serviceId;
                    
                    // Check if there are selected service items with prices
                    const selectedItems = document.querySelectorAll(`.service-item-checkbox:checked[data-service-id="${serviceId}"]`);
                    let serviceTotal = 0;
                    
                    if (selectedItems && selectedItems.length > 0) {
                        // Add up prices of selected items
                        selectedItems.forEach(item => {
                            const itemPriceStr = item.getAttribute('data-price') || '0';
                            const itemPrice = parseFloat(itemPriceStr.replace(/,/g, '')) || 0;
                            serviceTotal += itemPrice;
                        });
                    } else {
                        // If no items selected, use a default price or the data-price attribute
                        serviceTotal = parseFloat(service.getAttribute('data-price')) || 0;
                    }
                    
                    total += serviceTotal;
                }
            });
            
            // Update summary and form field
            document.getElementById('summaryTotal').textContent = `₱${total.toFixed(2)}`;
            document.getElementById('totalAmount').value = total;
        }

        function downloadGuestTemplate() {
            // Create CSV content with headers
            const csvContent = "Name,Email,Phone\nJohn Doe,john@example.com,1234567890";
            
            // Create a blob and download link
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'guest_list_template.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        document.addEventListener("DOMContentLoaded", function() {
            // ... existing code ...
            
            // Add event listener for step3NextBtn
            document.getElementById('step3NextBtn').addEventListener('click', function() {
                nextStep(3);
            });
            
            // ... existing code ...
        });
    </script>
    <script src="custom-theme.js"></script>
</body>
</html>
