<?php
session_start();
include 'db/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to submit a review']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if all required fields are provided
if (!isset($_POST['package_id']) || !isset($_POST['rating']) || !isset($_POST['review_text']) || 
    empty($_POST['package_id']) || empty($_POST['rating']) || empty($_POST['review_text'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Get form data
$user_id = $_SESSION['user_id'];
$package_id = intval($_POST['package_id']);
$rating = intval($_POST['rating']);
$review_text = trim($_POST['review_text']);

// Validate rating (1-5)
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit;
}

// Check if user has already reviewed this package
$check_query = "SELECT id FROM reviews WHERE user_id = ? AND package_id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param('ii', $user_id, $package_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    // Update existing review
    $review_id = $check_result->fetch_assoc()['id'];
    $update_query = "UPDATE reviews SET rating = ?, review_text = ?, created_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('isi', $rating, $review_text, $review_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Your review has been updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating review: ' . $conn->error]);
    }
    
    $update_stmt->close();
} else {
    // Insert new review
    $insert_query = "INSERT INTO reviews (user_id, package_id, rating, review_text) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param('iiis', $user_id, $package_id, $rating, $review_text);
    
    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Your review has been submitted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error submitting review: ' . $conn->error]);
    }
    
    $insert_stmt->close();
}

$check_stmt->close();
$conn->close();
?> 