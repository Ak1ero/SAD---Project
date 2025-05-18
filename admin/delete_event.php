<?php
// Include database connection
include '../db/config.php';

// Check if ID is provided
if(isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Get image path before deleting
    $sql = "SELECT image_path FROM event_packages WHERE id = $id";
    $result = $conn->query($sql);
    
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $image_path = $row['image_path'];
        
        // Delete from database
        $delete_sql = "DELETE FROM event_packages WHERE id = $id";
        
        if ($conn->query($delete_sql) === TRUE) {
            // Delete image file if it exists
            if(!empty($image_path) && file_exists('../' . $image_path)) {
                unlink('../' . $image_path);
            }
            
            // Redirect with success message
            header("Location: events.php?delete_success=1");
            exit();
        } else {
            // Redirect with error message
            header("Location: events.php?delete_error=1");
            exit();
        }
    } else {
        // Event not found
        header("Location: events.php?not_found=1");
        exit();
    }
} else {
    // Invalid ID
    header("Location: events.php?invalid_id=1");
    exit();
}
?>