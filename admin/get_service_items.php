<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Authentication required']);
  exit();
}

// Database connection
include '../db/config.php';

// Check if service_id is provided
if (!isset($_GET['service_id']) || empty($_GET['service_id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Service ID is required']);
  exit();
}

$serviceId = (int)$_GET['service_id'];

// Query to get all service items for the given service
$sql = "SELECT * FROM service_items WHERE service_id = ? ORDER BY name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $serviceId);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $priceRange = $row['price_range'];
    // Add peso sign if not already present
    if (!empty($priceRange) && strpos($priceRange, '₱') !== 0) {
      $priceRange = '₱' . $priceRange;
    }
    
    $items[] = [
      'id' => $row['id'],
      'name' => htmlspecialchars($row['name']),
      'phone' => htmlspecialchars($row['phone']),
      'email' => htmlspecialchars($row['email']),
      'price_range' => htmlspecialchars($priceRange),
      'image_path' => htmlspecialchars($row['image_path']),
      'service_type' => htmlspecialchars($row['service_type'])
    ];
  }
}

// Get the service name
$serviceName = '';
$serviceQuery = "SELECT name FROM services WHERE id = ?";
$stmtService = $conn->prepare($serviceQuery);
$stmtService->bind_param("i", $serviceId);
$stmtService->execute();
$serviceResult = $stmtService->get_result();
if ($serviceResult->num_rows > 0) {
  $serviceRow = $serviceResult->fetch_assoc();
  $serviceName = htmlspecialchars($serviceRow['name']);
}

header('Content-Type: application/json');
echo json_encode([
  'success' => true,
  'service_id' => $serviceId,
  'service_name' => $serviceName,
  'items' => $items,
  'count' => count($items)
]);
?> 