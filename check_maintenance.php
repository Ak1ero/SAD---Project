<?php
/**
 * Maintenance Mode Status Check
 * 
 * This file is used by the maintenance page to check if maintenance mode has been turned off.
 * It returns a JSON response with the current maintenance mode status.
 */

// Start session
session_start();

// Include database configuration and system settings
include 'db/config.php';
include 'db/system_settings.php';

// Get the current maintenance mode status
$maintenance_mode = is_maintenance_mode($conn);

// Set appropriate headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Return the maintenance mode status as JSON
echo json_encode([
    'maintenance_mode' => $maintenance_mode,
    'timestamp' => time()
]);
?> 