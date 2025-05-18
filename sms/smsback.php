<?php
// Include configuration file
require_once dirname(__FILE__) . '/config.php';

/**
 * Send SMS using the configured provider
 * 
 * @param string $recipient The recipient phone number
 * @param string $message The message to send
 * @param string $provider (Optional) The SMS provider to use (defaults to DEFAULT_SMS_PROVIDER)
 * @return string JSON response
 */
function sendSMS($recipient, $message, $provider = null) {
    // If no provider specified, use the default one
    if ($provider === null) {
        $provider = defined('DEFAULT_SMS_PROVIDER') ? DEFAULT_SMS_PROVIDER : 'philsms';
    }
    
    // Format phone number
    $original_phone = $recipient;
    $recipient = formatPhoneNumber($recipient);
    
    // Log phone number for debugging
    error_log("SMS Request - Original: $original_phone | Formatted: $recipient | Provider: $provider");
    
    // Select the appropriate provider
    switch (strtolower($provider)) {
        case 'second_provider':
            return sendSMSViaSecondProvider($recipient, $message);
        case 'philsms':
        default:
            return sendSMSViaPhilSMS($recipient, $message);
    }
}

/**
 * Send SMS via the PhilSMS API
 * 
 * @param string $recipient The recipient phone number (formatted)
 * @param string $message The message to send
 * @return string JSON response
 */
function sendSMSViaPhilSMS($recipient, $message) {
    // Get API token and endpoint from config
    $token = PHILSMS_API_TOKEN;
    $url = PHILSMS_API_ENDPOINT;
    
    // Required parameters based on the API documentation
    $data = [
        'recipient' => $recipient,      // The destination phone number
        'message' => $message,          // The SMS message content
        'sender_id' => PHILSMS_SENDER_ID, // Using the registered and active sender ID from the account
        'type' => 'plain',              // Specifies plain text message type as shown in the docs
        'priority' => 'high'            // Add high priority to ensure faster delivery
    ];
    
    error_log("PhilSMS Request Data: " . json_encode($data));
    
    // Set up cURL request
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set a longer timeout (30 seconds)
    
    // Set the authorization header exactly as shown in the documentation
    // Format: "Authorization: Bearer {api_token}"
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    // Execute the request
    $response = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("PhilSMS API Error: " . $error);
        return json_encode(['status' => 'error', 'message' => 'Connection error: ' . $error]);
    }
    
    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    error_log("PhilSMS API HTTP Code: " . $httpCode);
    error_log("PhilSMS API Response: " . $response);
    
    curl_close($ch);
    
    // Parse the response
    $response_data = json_decode($response, true);
    
    // Check for success based on the API response
    if ($response_data && isset($response_data['data']) && isset($response_data['data']['message_id'])) {
        // This indicates success according to PhilSMS documentation
        return json_encode([
            'status' => 'success',
            'message' => 'SMS sent successfully',
            'message_id' => $response_data['data']['message_id'],
            'provider' => 'philsms'
        ]);
    } elseif ($httpCode >= 200 && $httpCode < 300 && !isset($response_data['error'])) {
        // 2xx status without explicit error
        return json_encode([
            'status' => 'success',
            'message' => 'SMS request accepted',
            'response' => $response_data,
            'provider' => 'philsms'
        ]);
    } else {
        // Error response
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
        return json_encode([
            'status' => 'error',
            'message' => $error_message,
            'response' => $response_data,
            'provider' => 'philsms'
        ]);
    }
}

/**
 * Send SMS via the Second Provider API
 * 
 * @param string $recipient The recipient phone number (formatted)
 * @param string $message The message to send
 * @return string JSON response
 */
function sendSMSViaSecondProvider($recipient, $message) {
    // Get API token and endpoint from config
    $token = SECOND_PROVIDER_API_TOKEN;
    $url = SECOND_PROVIDER_API_ENDPOINT;
    
    // NOTE: Adjust the data structure according to your second provider's API requirements
    // This is just an example and should be modified based on your provider's documentation
    $data = [
        'to' => $recipient,
        'message' => $message,
        'from' => SECOND_PROVIDER_SENDER_ID,
        // Add any other required parameters for your second provider
    ];
    
    error_log("Second Provider Request Data: " . json_encode($data));
    
    // Set up cURL request
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Set the authorization header according to your second provider's requirements
    // Modify this based on your provider's authentication method
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token, // Adjust as needed
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    // Execute the request
    $response = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("Second Provider API Error: " . $error);
        return json_encode(['status' => 'error', 'message' => 'Connection error: ' . $error, 'provider' => 'second_provider']);
    }
    
    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    error_log("Second Provider API HTTP Code: " . $httpCode);
    error_log("Second Provider API Response: " . $response);
    
    curl_close($ch);
    
    // Parse the response
    $response_data = json_decode($response, true);
    
    // NOTE: Adjust the success/error checking logic based on your second provider's API response format
    // This is just an example and should be modified
    if ($httpCode >= 200 && $httpCode < 300) {
        return json_encode([
            'status' => 'success',
            'message' => 'SMS sent successfully via second provider',
            'response' => $response_data,
            'provider' => 'second_provider'
        ]);
    } else {
        // Error response
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
        return json_encode([
            'status' => 'error',
            'message' => $error_message,
            'response' => $response_data,
            'provider' => 'second_provider'
        ]);
    }
}

/**
 * Format phone number to ensure it's in the correct format for SMS APIs
 * 
 * @param string $phone The phone number to format
 * @return string The formatted phone number
 */
function formatPhoneNumber($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If it's empty after cleaning, return empty
    if (empty($phone)) {
        return '';
    }
    
    // If it starts with 0, replace with 63 (Philippines country code)
    if (substr($phone, 0, 1) === '0') {
        return '63' . substr($phone, 1);
    }
    
    // If it starts with 9 and has 10 digits total (standard PH mobile format)
    if (substr($phone, 0, 1) === '9' && strlen($phone) === 10) {
        return '63' . $phone;
    }
    
    // If it already has the country code (63)
    if (substr($phone, 0, 2) === '63' && strlen($phone) >= 12) {
        return $phone;
    }
    
    // If it starts with +63, remove the +
    if (substr($phone, 0, 3) === '+63') {
        return '63' . substr($phone, 3);
    }
    
    // Default - if we're not sure, add 63 prefix if it seems like a 10-digit number
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '9') {
        return '63' . $phone;
    }
    
    // If none of the above patterns match, return as is
    return $phone;
}
