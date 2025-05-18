<?php
session_start();
include '../db/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please log in to upload guests']);
    exit();
}

// Set header for JSON response
header('Content-Type: application/json');

// Handle the uploaded file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get the guest data from POST
        if (!isset($_POST['guests']) || empty($_POST['guests'])) {
            throw new Exception('No guest data provided');
        }

        $guests = json_decode($_POST['guests'], true);
        if (!is_array($guests)) {
            throw new Exception('Invalid guest data format');
        }

        // Get temporary booking ID from session if available
        $temp_booking_id = isset($_SESSION['temp_booking_id']) ? $_SESSION['temp_booking_id'] : null;
        
        // If no temp booking ID exists, create a temporary booking record
        if (!$temp_booking_id) {
            // Generate a temporary booking reference
            $temp_booking_reference = 'TMP' . date('YmdHis') . rand(100, 999);
            
            // Create a temporary booking record
            $sql = "INSERT INTO bookings (user_id, booking_reference, event_date, status, created_at) 
                   VALUES (?, ?, CURDATE(), 'pending', NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $_SESSION['user_id'], $temp_booking_reference);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create temporary booking');
            }
            
            $temp_booking_id = $conn->insert_id;
            $_SESSION['temp_booking_id'] = $temp_booking_id;
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        // First, clear any existing guests for this temp booking
        $clear_sql = "DELETE FROM guests WHERE booking_id = ?";
        $clear_stmt = $conn->prepare($clear_sql);
        $clear_stmt->bind_param("i", $temp_booking_id);
        $clear_stmt->execute();
        
        // Now insert guests into the database
        $insert_sql = "INSERT INTO guests (booking_id, name, email, phone, unique_code, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        
        $success_count = 0;
        $generated_unique_codes = [];
        
        foreach ($guests as $guest) {
            if (!isset($guest['name']) || empty($guest['name'])) {
                continue; // Skip guests without names
            }
            
            $email = isset($guest['email']) ? $guest['email'] : null;
            $phone = isset($guest['phone']) ? $guest['phone'] : null;
            
            // Generate a unique random code for each guest
            // Format: 2 letters + 6 numbers + 2 letters
            $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $prefix = $letters[rand(0, 25)] . $letters[rand(0, 25)];
            $numbers = sprintf('%06d', rand(0, 999999));
            $suffix = $letters[rand(0, 25)] . $letters[rand(0, 25)];
            
            $unique_code = $prefix . $numbers . $suffix;
            
            // Store the unique code
            $unique_code_data = json_encode([
                'booking_id' => $temp_booking_id,
                'name' => $guest['name'],
                'email' => $email,
                'phone' => $phone,
                'unique_code' => $unique_code,
                'timestamp' => time()
            ]);
            
            $insert_stmt->bind_param("issss", 
                $temp_booking_id,
                $guest['name'],
                $email,
                $phone,
                $unique_code
            );
            
            if ($insert_stmt->execute()) {
                $success_count++;
                $generated_unique_codes[] = [
                    'name' => $guest['name'],
                    'unique_code' => $unique_code
                ];
            }
        }
        
        // Also save to session for backup
        $_SESSION['temp_guest_list'] = $guests;
        $_SESSION['generated_unique_codes'] = $generated_unique_codes;
        
        // Commit the transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Guest Uploaded Successfully',
            'temp_booking_id' => $temp_booking_id,
            'count' => $success_count,
            'unique_codes' => $generated_unique_codes
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        if ($conn && $conn->connect_errno) {
            $conn->rollback();
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
} 