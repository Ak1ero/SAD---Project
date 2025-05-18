<?php
// Include the SMS functionality
include_once 'smsback.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if required parameters are provided
    if (!isset($_POST['number']) || !isset($_POST['message']) || empty($_POST['number']) || empty($_POST['message'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Phone number and message are required'
        ]);
        exit;
    }
    
    $number = $_POST['number'];
    $message = $_POST['message'];
    
    // Send the SMS
    $response = sendSMS($number, $message);
    
    // Log for debugging
    error_log("SMS API response for " . $number . ": " . $response);
    
    // Parse response
    $response_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // JSON parsing error
        echo json_encode([
            'success' => false,
            'message' => 'Failed to parse API response',
            'raw_response' => $response
        ]);
    } else {
        // Pass through the API response
        echo $response;
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>
