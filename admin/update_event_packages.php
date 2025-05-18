<?php
// Include database configuration
include '../db/config.php';

// Check if the column already exists
$result = $conn->query("SHOW COLUMNS FROM event_packages LIKE 'guest_capacity'");
$columnExists = $result->num_rows > 0;

if (!$columnExists) {
    // Add guest_capacity column to event_packages table
    $sql = "ALTER TABLE event_packages ADD COLUMN guest_capacity INT DEFAULT NULL AFTER price";
    
    if ($conn->query($sql) === TRUE) {
        echo "The guest_capacity column has been added successfully!";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "The guest_capacity column already exists.";
}

// Close connection
$conn->close();
?> 