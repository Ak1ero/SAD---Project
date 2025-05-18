<?php
/**
 * Send Invitations Script
 * 
 * This script processes guest data and sends email invitations
 * with QR codes.
 */

// Start session
session_start();

// Include configuration
require_once 'config.php';

// Use PHPMailer if available
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Create a custom error handler that captures errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log the error for debugging
    log_message("Error ($errno): $errstr in $errfile on line $errline", "ERROR");
    return false; // Let PHP handle the error as well
});

try {
    // Include PHPMailer autoloader if available
    if (file_exists('../vendor/autoload.php')) {
        require '../vendor/autoload.php';
    } else {
        log_message('PHPMailer autoloader not found. Will use native mail() function.', 'WARNING');
    }
    
    // Database connection
    include '../db/config.php';
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../users/login.php");
        exit();
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json'); // Set header type early
        
        // Get booking ID
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        // Validate booking ID
        if ($booking_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
            exit();
        }
        
        // Check if booking belongs to the logged-in user
        $user_id = $_SESSION['user_id'];
        $check_sql = "SELECT b.*, u.name as host_name, u.email as host_email 
                     FROM bookings b 
                     JOIN users u ON b.user_id = u.id 
                     WHERE b.id = ? AND b.user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $booking_id, $user_id);
        $check_stmt->execute();
        $booking_result = $check_stmt->get_result();
        
        if ($booking_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking or unauthorized access']);
            exit();
        }
        
        // Get booking details
        $booking = $booking_result->fetch_assoc();
        
        // Get event details for the email
        $event_date = date('l, F j, Y', strtotime($booking['event_date']));
        $event_start_time = date('g:i A', strtotime($booking['event_start_time']));
        $event_end_time = date('g:i A', strtotime($booking['event_end_time']));
        $event_location = "The Barn & Backyard";
        $package_name = ucwords($booking['package_name']);
        $theme_name = ucwords($booking['theme_name']);
        $booking_reference = $booking['booking_reference'];
        $host_name = $booking['host_name'];
        $host_email = $booking['host_email'];
        
        // Get all guests for this booking from the guests table
        $guests_sql = "SELECT id, name, email, phone, unique_code FROM guests WHERE booking_id = ?";
        $guests_stmt = $conn->prepare($guests_sql);
        $guests_stmt->bind_param("i", $booking_id);
        $guests_stmt->execute();
        $guests_result = $guests_stmt->get_result();
        
        if ($guests_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No guests found for this booking']);
            exit();
        }
        
        // Prepare to track results
        $total_guests = $guests_result->num_rows;
        $success_count = 0;
        $failure_count = 0;
        $failed_guests = [];
        
        log_message("Starting to send invitations for booking ID: $booking_id with $total_guests guests");
        
        // Get server information for proper URL construction
        $server_protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $server_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $base_url = $server_protocol . '://' . $server_host;
        
        // Process each guest
        while ($guest = $guests_result->fetch_assoc()) {
            // Skip if no email address
            if (empty($guest['email'])) {
                $failure_count++;
                $failed_guests[] = $guest['name'] . " (No email address)";
                log_message("Skipping guest {$guest['name']} - no email address provided", "WARNING");
                continue;
            }
            
            $guest_name = $guest['name'];
            $guest_email = $guest['email'];
            
            log_message("Processing invitation for: $guest_name <$guest_email>");
            
            // Create the email content using the invitation.html template
            $subject = "You're Invited! Event at The Barn & Backyard";
            
            // Load invitation template
            $template_path = __DIR__ . '/templates/invitation.html';
            if (!file_exists($template_path)) {
                log_message("Invitation template not found at: $template_path", "ERROR");
                $failure_count++;
                $failed_guests[] = $guest_name . " (Template file not found)";
                continue;
            }
            
            $message = file_get_contents($template_path);
            
            // Replace placeholders in template
            $message = str_replace('{GUEST_NAME}', $guest_name, $message);
            $message = str_replace('{EVENT_DATE}', $event_date, $message);
            $message = str_replace('{EVENT_TIME}', $event_start_time . ' - ' . $event_end_time, $message);
            $message = str_replace('{EVENT_LOCATION}', $event_location, $message);
            $message = str_replace('{EVENT_PACKAGE}', $package_name, $message);
            $message = str_replace('{EVENT_THEME}', $theme_name, $message);
            $message = str_replace('{BOOKING_REFERENCE}', $booking_reference, $message);
            $message = str_replace('{GUEST_ID}', $guest['id'], $message);
            $message = str_replace('{BASE_URL}', $base_url, $message);
            $message = str_replace('{SERVER_HOST}', $_SERVER['HTTP_HOST'], $message);
            
            // Get unique code from guest record
            $unique_code = !empty($guest['unique_code']) ? $guest['unique_code'] : '';
            
            // If the unique code is empty or has an old format, generate a new one
            if (strpos($unique_code, '/') !== false || empty($unique_code)) {
                // Generate a unique code format: 2 letters + 6 numbers + 2 letters
                $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $prefix = $letters[rand(0, 25)] . $letters[rand(0, 25)];
                $numbers = sprintf('%06d', rand(0, 999999));
                $suffix = $letters[rand(0, 25)] . $letters[rand(0, 25)];
                
                $unique_code = $prefix . $numbers . $suffix;
                
                // Update the guest record with the unique code
                $update_code_sql = "UPDATE guests SET unique_code = ? WHERE id = ?";
                $update_code_stmt = $conn->prepare($update_code_sql);
                $update_code_stmt->bind_param("si", $unique_code, $guest['id']);
                $update_code_stmt->execute();
            }
            
            // Replace the unique code placeholder in the template
            $message = str_replace('{UNIQUE_CODE}', $unique_code, $message);
            
            // Set up email headers
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: The Barn & Backyard <" . EMAIL_FROM . ">" . "\r\n";
            $headers .= "Reply-To: " . $host_email . "\r\n";
            
            // Try to send the email
            $mail_sent = false;
            
            try {
                // PHPMailer is preferred for attachments, so try it first
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    $mail = new PHPMailer(true);
                    
                    try {
                        // Server settings
                        if (SMTP_ENABLED) {
                            $mail->isSMTP();
                            $mail->Host       = SMTP_HOST;
                            $mail->SMTPAuth   = SMTP_AUTH;
                            $mail->Username   = SMTP_USERNAME;
                            $mail->Password   = SMTP_PASSWORD;
                            $mail->SMTPSecure = SMTP_SECURE;
                            $mail->Port       = SMTP_PORT;
                        }
                        
                        // Recipients
                        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
                        $mail->addReplyTo($host_email, $host_name);
                        $mail->addAddress($guest_email, $guest_name);
                        
                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body    = $message;
                        
                        // Send the email
                        $mail_sent = $mail->send();
                        
                        if ($mail_sent) {
                            log_message("Successfully sent invitation to {$guest_email} using PHPMailer", "INFO");
                        } else {
                            log_message("PHPMailer could not send: " . $mail->ErrorInfo, "ERROR");
                        }
                    } catch (Exception $e) {
                        log_message("PHPMailer exception: " . $e->getMessage(), "ERROR");
                    }
                } else {
                    // Fallback to PHP mail() function, but it doesn't support attachments well
                    // For production, you should use PHPMailer for sending emails with attachments
                    log_message("PHPMailer not available, falling back to mail() (attachments may not work)", "WARNING");
                    $mail_sent = @mail($guest_email, $subject, $message, $headers);
                }
            } catch (Exception $e) {
                log_message("Exception while sending to {$guest_email}: " . $e->getMessage(), "ERROR");
                $mail_sent = false;
            }
            
            if ($mail_sent) {
                $success_count++;
                
                // Update guest record to mark invitation as sent
                $update_sql = "UPDATE guests SET invitation_sent = 1, invitation_sent_at = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $guest['id']);
                $update_stmt->execute();
            } else {
                $failure_count++;
                $failed_guests[] = $guest_name . " (" . $guest_email . ")";
            }
        }
        
        // Prepare and send response
        if ($success_count > 0) {
            echo json_encode([
                'success' => true,
                'message' => ($success_count == $total_guests) 
                    ? 'All invitations sent successfully!' 
                    : 'Some invitations were sent successfully.',
                'total' => $total_guests,
                'success_count' => $success_count,
                'failure_count' => $failure_count,
                'failed_guests' => $failed_guests
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send any invitations. Please try again.',
                'total' => $total_guests,
                'success_count' => $success_count,
                'failure_count' => $failure_count,
                'failed_guests' => $failed_guests
            ]);
        }
        
        // Close statements
        $check_stmt->close();
        $guests_stmt->close();
        exit();
    }
    
    // If not a POST request, redirect to bookings page
    header("Location: ../users/my-bookings.php");
    exit();

} catch (Exception $e) {
    // Log the error
    log_message("Exception: " . $e->getMessage(), "ERROR");
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "An error occurred: " . $e->getMessage()
    ]);
    exit();
}

/**
 * Create a simple placeholder QR code image for testing
 * when QR library is not available
 */
function createPlaceholderQR($file_path) {
    // Create a 200x200 image
    $image = imagecreatetruecolor(200, 200);
    
    // Colors
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    
    // Fill background with white
    imagefill($image, 0, 0, $white);
    
    // Draw a black border
    imagerectangle($image, 0, 0, 199, 199, $black);
    
    // Draw text
    imagestring($image, 5, 40, 90, "QR Code Placeholder", $black);
    
    // Save the image
    imagepng($image, $file_path);
    imagedestroy($image);
    
    return $file_path;
} 