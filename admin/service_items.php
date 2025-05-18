<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

// Database connection
include '../db/config.php';

// Handle item submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($_POST['action'] === 'add_to_service') {
    $serviceId = $_POST['serviceId'];
    $serviceType = $_POST['serviceType'];
    $itemName = $_POST['itemName'];
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $priceRange = isset($_POST['priceRange']) ? $_POST['priceRange'] : '';
    
    // Add peso sign to price range if not already present
    if (!empty($priceRange) && strpos($priceRange, '₱') !== 0) {
      $priceRange = '₱' . $priceRange;
    }
    
    // Process image if provided
    $imagePath = '';
    if(isset($_FILES["itemImage"]) && $_FILES["itemImage"]["size"] > 0) {
      $targetDir = "../uploads/service_items/";
      
      // Create directory if it doesn't exist
      if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
      }
      
      $fileName = basename($_FILES["itemImage"]["name"]);
      $targetFilePath = $targetDir . $fileName;
      $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
      
      // Check if file is an actual image
      $check = getimagesize($_FILES["itemImage"]["tmp_name"]);
      if($check !== false) {
        // Check if file already exists and rename if needed
        if (file_exists($targetFilePath)) {
          $fileName = time() . '_' . $fileName;
          $targetFilePath = $targetDir . $fileName;
        }
        
        if(move_uploaded_file($_FILES["itemImage"]["tmp_name"], $targetFilePath)){
          $imagePath = $fileName;
        } else {
          echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
          exit;
        }
      } else {
        echo json_encode(['success' => false, 'error' => 'File is not an image']);
        exit;
      }
    }
    
    // Insert into single service_items table
    $sql = "INSERT INTO service_items (name, phone, email, price_range, image_path, service_id, service_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $itemName, $phone, $email, $priceRange, $imagePath, $serviceId, $serviceType);
    
    if($stmt->execute()) {
      echo json_encode(['success' => true, 'message' => 'Item added successfully!']);
      exit;
    } else {
      echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
      exit;
    }
  }
}

// If we get here, it means the request method or action was not recognized
echo json_encode(['success' => false, 'error' => 'Invalid request']);
exit;
?> 