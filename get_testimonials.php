<?php
// Include database connection
include 'db/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get 3 random reviews with high ratings (4-5 stars)
$query = "SELECT r.*, u.name as user_name, p.name as package_name 
          FROM reviews r 
          JOIN users u ON r.user_id = u.id 
          JOIN event_packages p ON r.package_id = p.id 
          WHERE r.rating >= 4 
          ORDER BY RAND() 
          LIMIT 3";

$result = $conn->query($query);
$testimonials = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Get first name or first initial of user for display
        $name_parts = explode(' ', $row['user_name']);
        $first_name = $name_parts[0];
        $last_initial = !empty($name_parts[1]) ? substr($name_parts[1], 0, 1) . '.' : '';
        
        // Format date
        $date = date('F Y', strtotime($row['created_at']));
        
        // Get event type from package name
        $event_type = '';
        if (stripos($row['package_name'], 'wedding') !== false) {
            $event_type = 'Wedding';
        } elseif (stripos($row['package_name'], 'corporate') !== false) {
            $event_type = 'Corporate Retreat';
        } elseif (stripos($row['package_name'], 'birthday') !== false) {
            $event_type = 'Birthday Party';
        } else {
            $event_type = 'Event';
        }
        
        $testimonials[] = [
            'id' => $row['id'],
            'user_name' => $first_name . ' ' . $last_initial,
            'rating' => $row['rating'],
            'review_text' => $row['review_text'],
            'created_at' => $date,
            'event_type' => $event_type,
            'first_initial' => substr($first_name, 0, 1)
        ];
    }
}

// Return testimonials
echo json_encode([
    'success' => true,
    'testimonials' => $testimonials
]);

$conn->close();
?> 