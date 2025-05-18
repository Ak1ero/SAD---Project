<?php
/**
 * System Settings Management File
 * 
 * This file contains functions to manage system settings
 * including maintenance mode
 */

// Create settings table if it doesn't exist
function ensure_settings_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_name VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $conn->query($sql);
    
    // Initialize maintenance_mode setting if it doesn't exist
    $sql = "INSERT IGNORE INTO system_settings (setting_name, setting_value) VALUES ('maintenance_mode', '0')";
    $conn->query($sql);
}

// Get a system setting value
function get_setting($conn, $setting_name, $default = null) {
    $sql = "SELECT setting_value FROM system_settings WHERE setting_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $setting_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    
    return $default;
}

// Update a system setting
function update_setting($conn, $setting_name, $setting_value) {
    $sql = "INSERT INTO system_settings (setting_name, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $setting_name, $setting_value);
    return $stmt->execute();
}

// Check if maintenance mode is active
function is_maintenance_mode($conn) {
    return get_setting($conn, 'maintenance_mode', '0') === '1';
}

// Redirect to maintenance page if in maintenance mode
// Admin users can bypass maintenance mode
function maintenance_check($conn) {
    // Don't check on maintenance page itself or login page
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page === 'maintenance.php' || $current_page === 'login.php') {
        return;
    }
    
    // Don't check on admin pages
    $admin_path = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
    if ($admin_path) {
        return;
    }
    
    // Check if maintenance mode is active
    if (is_maintenance_mode($conn)) {
        // Allow admins to bypass maintenance mode
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
            // Admins can access the site
            return;
        }
        
        // Get the relative path to maintenance.php
        $maintenance_path = 'maintenance.php';
        
        // If in a subdirectory, adjust the path
        $depth = substr_count($_SERVER['PHP_SELF'], '/');
        if ($depth > 1) {
            $maintenance_path = str_repeat('../', $depth - 1) . 'maintenance.php';
        }
        
        // Redirect to maintenance page
        header("Location: $maintenance_path");
        exit;
    }
}

// Ensure the settings table exists
ensure_settings_table($conn);
?> 