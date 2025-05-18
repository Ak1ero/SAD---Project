<?php
// Include database connection
include '../db/config.php';

// Check if ID is provided
if(isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Fetch package details
    $sql = "SELECT * FROM event_packages WHERE id = $id";
    $result = $conn->query($sql);
    
    if($result && $result->num_rows > 0) {
        $package = $result->fetch_assoc();
        
        // Return as JSON
        header('Content-Type: application/json');
        echo json_encode($package);
        exit();
    }
}

// Return error if package not found
header('Content-Type: application/json');
echo json_encode(['error' => 'Package not found']);
?>