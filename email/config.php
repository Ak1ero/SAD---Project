<?php
/**
 * Email Configuration File
 * 
 * This file contains configuration for email sending.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Create log directory if it doesn't exist
$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Set error log to a file in the logs directory
ini_set('error_log', $log_dir . '/mail_errors_' . date('Y-m-d') . '.log');

// Get environment variables or use defaults
// For production, these should be set in your server environment
function getEnvVar($name, $default = '') {
    return getenv($name) ?: $default;
}

// Default email settings
define('EMAIL_FROM', getEnvVar('EMAIL_FROM', 'noreply@thebarnbackyard.com'));
define('EMAIL_FROM_NAME', getEnvVar('EMAIL_FROM_NAME', 'The Barn & Backyard'));
define('EMAIL_REPLY_TO', getEnvVar('EMAIL_REPLY_TO', 'info@thebarnbackyard.com'));

// SMTP Configuration
define('SMTP_ENABLED', getEnvVar('SMTP_ENABLED', true)); // Set to true to enable SMTP
define('SMTP_HOST', getEnvVar('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', getEnvVar('SMTP_PORT', 587));
define('SMTP_AUTH', getEnvVar('SMTP_AUTH', true));
define('SMTP_USERNAME', getEnvVar('SMTP_USERNAME', 'andresanavares@gmail.com'));
define('SMTP_PASSWORD', getEnvVar('SMTP_PASSWORD', 'bxjl zetg jttp swud')); // Leave empty in code, set in environment
define('SMTP_SECURE', getEnvVar('SMTP_SECURE', 'tls')); // tls or ssl

/**
 * Configure PHPMailer with the settings above
 * 
 * @param PHPMailer\PHPMailer\PHPMailer $mailer The PHPMailer instance to configure
 * @return void
 */
function configurePHPMailer($mailer) {
    if (SMTP_ENABLED) {
        $mailer->isSMTP();
        $mailer->Host = SMTP_HOST;
        $mailer->Port = SMTP_PORT;
        
        if (SMTP_AUTH) {
            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USERNAME;
            $mailer->Password = SMTP_PASSWORD;
        }
        
        $mailer->SMTPSecure = SMTP_SECURE;
    }
    
    // Common settings
    $mailer->isHTML(true);
    $mailer->CharSet = 'UTF-8';
    
    return $mailer;
}

/**
 * Simple logging function
 * 
 * @param string $message The message to log
 * @param string $type The type of message (INFO, ERROR, etc.)
 * @return void
 */
function log_message($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] [$type] $message");
}

// Additional settings as array for future use
return [
    // SMTP server settings
    'smtp_host' => SMTP_HOST,
    'smtp_port' => SMTP_PORT,
    'smtp_username' => SMTP_USERNAME,
    'smtp_password' => SMTP_PASSWORD,
    'smtp_encryption' => SMTP_SECURE,
    
    // Sender information
    'from_email' => EMAIL_FROM,
    'from_name' => EMAIL_FROM_NAME,
    
    // Additional settings
    'reply_to' => EMAIL_REPLY_TO,
    'max_retries' => getEnvVar('MAX_RETRIES', 3),
    
    // Debug settings - set to 2 for verbose output during testing
    'debug_mode' => getEnvVar('EMAIL_DEBUG', 0),
    
    // Testing mode - set to true during testing
    'test_mode' => getEnvVar('EMAIL_TEST_MODE', false),
    'test_email' => getEnvVar('EMAIL_TEST_ADDRESS', ''),
    
    // Email template default path
    'template_dir' => __DIR__ . '/templates'
]; 