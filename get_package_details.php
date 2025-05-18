<?php
include 'db/config.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Prepare the SQL statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM event_packages WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Format the data
        $response = array(
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => $row['price'],
            'duration' => $row['duration'],
            'inclusions' => $row['inclusions'],
            'exclusions' => $row['exclusions'],
            'terms' => nl2br(htmlspecialchars($row['terms'])),
            'image_path' => $row['image_path'],
            'guest_capacity' => $row['guest_capacity']
        );
        
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        http_response_code(404);
        echo json_encode(array('error' => 'Package not found'));
    }
    
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(array('error' => 'No ID provided'));
}

$conn->close();
?> 