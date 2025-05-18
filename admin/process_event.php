<?php
// Include database connection
include '../db/config.php';
// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $event_name = mysqli_real_escape_string($conn, $_POST['event_name']);
    $price = floatval($_POST['price']);
    $venue_rental = intval($_POST['venue_rental']);
    $capacity = intval($_POST['capacity']);
    $package_details = mysqli_real_escape_string($conn, $_POST['package_details']);
    $inclusions = mysqli_real_escape_string($conn, $_POST['inclusions']);
    $exclusions = mysqli_real_escape_string($conn, $_POST['exclusions']);
    $terms = mysqli_real_escape_string($conn, $_POST['terms']);
    
    // Image upload handling
    $image_path = '';
    if(isset($_FILES['event_image']) && $_FILES['event_image']['error'] == 0) {
        $upload_dir = '../uploads/events/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['event_image']['name']);
        $target_file = $upload_dir . $file_name;
        
        // Check if image file is an actual image
        $check = getimagesize($_FILES['event_image']['tmp_name']);
        if($check !== false) {
            // Try to upload file
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
                $image_path = 'uploads/events/' . $file_name;
            }
        }
    }
    
    // Insert data into database
    $sql = "INSERT INTO event_packages (name, price, duration, capacity, description, inclusions, exclusions, terms, image_path) 
            VALUES ('$event_name', $price, $venue_rental, $capacity, '$package_details', '$inclusions', '$exclusions', '$terms', '$image_path')";
    
    if ($conn->query($sql) === TRUE) {
        // Redirect back to events page with success message
        header("Location: events.php?success=1");
        exit();
    } else {
        // Redirect back to events page with error message
        header("Location: events.php?error=1");
        exit();
    }
}

// If not POST request, redirect back to events page
header("Location: events.php");
exit();
?> 