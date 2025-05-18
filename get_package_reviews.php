<?php
// Include database connection
include 'db/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if package ID is provided
if (!isset($_GET['package_id']) || empty($_GET['package_id'])) {
    echo json_encode(['success' => false, 'message' => 'Package ID is required']);
    exit;
}

$package_id = intval($_GET['package_id']);

// Fetch reviews for this package
$reviews_query = "SELECT r.*, u.name as user_name
                 FROM reviews r 
                 JOIN users u ON r.user_id = u.id 
                 WHERE r.package_id = ?
                 ORDER BY r.created_at DESC
                 LIMIT 10";

$stmt = $conn->prepare($reviews_query);
$stmt->bind_param('i', $package_id);
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = [
            'id' => $row['id'],
            'user_name' => $row['user_name'],
            'rating' => $row['rating'],
            'review_text' => $row['review_text'],
            'created_at' => $row['created_at']
        ];
    }
}

// Return the reviews
echo json_encode([
    'success' => true,
    'reviews' => $reviews
]);

$stmt->close();
$conn->close();
?> 