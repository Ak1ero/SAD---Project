<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

// Database connection
include '../db/config.php';

// Check if notification has been read
$notificationsRead = isset($_SESSION['notifications_read']) ? $_SESSION['notifications_read'] : false;

// Handle marking notifications as read via AJAX
if (isset($_POST['mark_read']) && $_POST['mark_read'] == 1) {
    $_SESSION['notifications_read'] = true;
    echo json_encode(['success' => true]);
    exit;
}

// Fetch the admin's username from the session
$username = $_SESSION['user_name'];

// Handle theme submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($_POST['action'] === 'add_theme') {
    $themeName = $_POST['themeName'];
    $themeDescription = $_POST['themeDescription'];
    $themePackages = isset($_POST['themePackages']) ? implode(',', $_POST['themePackages']) : '';
    
    // Handle file upload
    $targetDir = "../uploads/themes/";
    
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
      mkdir($targetDir, 0777, true);
    }
    
    $fileName = basename($_FILES["themeImage"]["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    
    // Check if file is an actual image
    $check = getimagesize($_FILES["themeImage"]["tmp_name"]);
    if($check !== false) {
      // Check if file already exists and rename if needed
      if (file_exists($targetFilePath)) {
        $fileName = time() . '_' . $fileName;
        $targetFilePath = $targetDir . $fileName;
      }
      
      if(move_uploaded_file($_FILES["themeImage"]["tmp_name"], $targetFilePath)){
        // Insert into database
        $sql = "INSERT INTO themes (name, description, packages, image_path) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $themeName, $themeDescription, $themePackages, $fileName);
        
        if($stmt->execute()) {
          echo json_encode(['success' => true, 'message' => 'Theme added successfully!']);
          exit;
        } else {
          echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
          exit;
        }
      } else {
        echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
        exit;
      }
    } else {
      echo json_encode(['success' => false, 'error' => 'File is not an image']);
      exit;
    }
  }
  
  if ($_POST['action'] === 'add_service') {
    $serviceName = $_POST['serviceName'];
    
    // Insert into database
    $sql = "INSERT INTO services (name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $serviceName);
    
    if($stmt->execute()) {
      echo json_encode(['success' => true, 'message' => 'Service added successfully!']);
      exit;
    }
    echo json_encode(['success' => false]);
    exit;
  }
  
  if ($_POST['action'] === 'delete_theme') {
    $themeId = $_POST['themeId'];
    
    // Delete from database
    $sql = "DELETE FROM themes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $themeId);
    
    if($stmt->execute()) {
      echo json_encode(['success' => true, 'message' => 'Theme deleted successfully!']);
      exit;
    }
    echo json_encode(['success' => false, 'error' => 'Failed to delete theme']);
    exit;
  }
  
  if ($_POST['action'] === 'delete_service') {
    $serviceId = $_POST['serviceId'];
    
    // Delete from database
    $sql = "DELETE FROM services WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $serviceId);
    
    if($stmt->execute()) {
      echo json_encode(['success' => true, 'message' => 'Service deleted successfully!']);
      exit;
    }
    echo json_encode(['success' => false, 'error' => 'Failed to delete service']);
    exit;
  }
  
  if ($_POST['action'] === 'edit_theme') {
    $themeId = $_POST['themeId'];
    $themeName = $_POST['themeName'];
    $themeDescription = $_POST['themeDescription'];
    $themePackages = isset($_POST['themePackages']) ? implode(',', $_POST['themePackages']) : '';
    
    // Check if a new image was uploaded
    if(isset($_FILES["themeImage"]) && $_FILES["themeImage"]["size"] > 0) {
      $targetDir = "../uploads/themes/";
      
      $fileName = basename($_FILES["themeImage"]["name"]);
      $targetFilePath = $targetDir . $fileName;
      
      // Check if file is an actual image
      $check = getimagesize($_FILES["themeImage"]["tmp_name"]);
      if($check !== false) {
        // Check if file already exists and rename if needed
        if (file_exists($targetFilePath)) {
          $fileName = time() . '_' . $fileName;
          $targetFilePath = $targetDir . $fileName;
        }
        
        if(move_uploaded_file($_FILES["themeImage"]["tmp_name"], $targetFilePath)){
          // Update database with new image
          $sql = "UPDATE themes SET name = ?, description = ?, packages = ?, image_path = ? WHERE id = ?";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("ssssi", $themeName, $themeDescription, $themePackages, $fileName, $themeId);
        } else {
          echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
          exit;
        }
      } else {
        echo json_encode(['success' => false, 'error' => 'File is not an image']);
        exit;
      }
    } else {
      // Update without changing the image
      $sql = "UPDATE themes SET name = ?, description = ?, packages = ? WHERE id = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("sssi", $themeName, $themeDescription, $themePackages, $themeId);
    }
    
    if($stmt->execute()) {
      echo json_encode(['success' => true, 'message' => 'Theme updated successfully!']);
      exit;
    } else {
      echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
      exit;
    }
  }
  
  if ($_POST['action'] === 'edit_service') {
    $serviceId = $_POST['serviceId'];
    $serviceName = $_POST['serviceName'];
    
    // Update database
    $sql = "UPDATE services SET name = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $serviceName, $serviceId);
    
    if($stmt->execute()) {
      echo json_encode(['success' => true, 'message' => 'Service updated successfully!']);
      exit;
    } else {
      echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
      exit;
    }
  }
}

// Fetch existing themes
$themes = [];
$sql = "SELECT * FROM themes";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $themes[] = $row;
  }
}

// Fetch existing services  
$services = [];
$sql = "SELECT * FROM services";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $services[] = $row;
  }
}

// Fetch available packages
$packages = [];
$sql = "SELECT * FROM event_packages";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $packages[] = $row;
  }
}

// After you fetch themes, organize them by package
$packageThemes = [];

// First, initialize arrays for each package
foreach ($packages as $package) {
  $packageThemes[$package['id']] = [
    'package_name' => $package['name'],
    'themes' => []
  ];
}

// Assign themes to their respective packages
foreach ($themes as $theme) {
  if (!empty($theme['packages'])) {
    $themePackages = explode(',', $theme['packages']);
    foreach ($themePackages as $packageId) {
      if (isset($packageThemes[$packageId])) {
        $packageThemes[$packageId]['themes'][] = $theme;
      }
    }
  } else {
    // Create a "General" category for themes with no specific package
    if (!isset($packageThemes['general'])) {
      $packageThemes['general'] = [
        'package_name' => 'General Themes',
        'themes' => []
      ];
    }
    $packageThemes['general']['themes'][] = $theme;
  }
}

// Remove empty packages
foreach ($packageThemes as $key => $package) {
  if (empty($package['themes'])) {
    unset($packageThemes[$key]);
  }
}
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>The Barn & Backyard | Theme Management</title>
  <link rel="icon" href="../img/barn-backyard.svg" type="image/svg+xml"/>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
          },
          colors: {
            primary: {
              50: '#f0f9ff',
              100: '#e0f2fe',
              200: '#bae6fd',
              300: '#7dd3fc',
              400: '#38bdf8',
              500: '#0ea5e9',
              600: '#0284c7',
              700: '#0369a1',
              800: '#075985',
              900: '#0c4a6e',
            },
            slate: {
              700: '#21262d',
              800: '#161b22',
              900: '#0d1117',
            }
          },
          animation: {},
          keyframes: {},
          backgroundColor: {
            'github-dark': '#0d1117',
            'github-dark-secondary': '#161b22',
            'github-dark-tertiary': '#21262d',
          },
          borderColor: {
            'github-border': '#30363d',
          },
          textColor: {
            'github-text': '#c9d1d9',
            'github-text-secondary': '#8b949e',
            'github-text-white': '#f0f6fc',
          },
        },
      },
    }
  </script>
  
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    /* Custom Scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }
    
    ::-webkit-scrollbar-track {
      background: transparent;
    }
    
    ::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 4px;
    }
    
    .dark ::-webkit-scrollbar-thumb {
      background: #30363d;
    }
    
    /* Card Hover Effects */
    .stat-card {
      /* Removing transition for smoother dashboard */
      /* transition: all 0.3s ease; */
    }
    
    /* Removing hover effects */
    /* .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    .dark .stat-card:hover {
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
    } */
    
    /* Smooth transitions */
    .transition-smooth {
      transition: all 0.2s ease-in-out;
    }
    
    /* Dark Mode Styles */
    .dark {
      color-scheme: dark;
    }
    
    .dark body {
      background-color: #0d1117;
      color: #c9d1d9;
    }

    /* Dark mode background colors for different elements */
    .dark .bg-slate-800 {
      background-color: #161b22 !important;
    }
    
    .dark .bg-slate-900 {
      background-color: #0d1117 !important;
    }
    
    .dark .bg-slate-700 {
      background-color: #21262d !important;
    }

    /* Adjust dark mode border colors */
    .dark .border-slate-700 {
      border-color: #30363d;
    }

    /* Dark mode text colors */
    .dark .text-gray-300,
    .dark .text-gray-400 {
      color: #8b949e;
    }
    
    .dark .text-white {
      color: #f0f6fc;
    }
    
    /* Hover states for dark mode */
    .dark .hover\:bg-slate-700\/50:hover {
      background-color: rgba(33, 38, 45, 0.5);
    }
    
    .dark .hover\:bg-slate-700:hover {
      background-color: #21262d;
    }
    
    .dark .hover\:bg-slate-700\/25:hover {
      background-color: rgba(33, 38, 45, 0.25);
    }
    
    /* Dark mode for form elements */
    .dark .bg-slate-700 {
      background-color: #21262d;
    }

    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      justify-content: center;
      align-items: flex-start;
      padding-top: 5vh;
      padding-bottom: 5vh;
      overflow-y: auto;
    }

    .modal.show {
      display: flex;
    }

    .modal-content {
      max-height: 90vh;
      overflow-y: auto;
      width: 100%;
      max-width: 500px;
      margin: 0 auto;
    }

    .image-preview {
      width: 200px;
      height: 200px;
      border: 2px dashed #ccc;
      border-radius: 8px;
      display: flex;
      justify-content: center;
      align-items: center;
      margin-bottom: 1rem;
      overflow: hidden;
    }

    .image-preview img {
      max-width: 100%;
      max-height: 100%;
      object-fit: cover;
    }
    
    .action-buttons {
      display: flex;
      justify-content: flex-end;
      gap: 0.5rem;
      margin-top: 0.5rem;
    }

    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 20px;
      border-radius: 4px;
      color: white;
      font-weight: 500;
      z-index: 1100;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      transform: translateY(-100px);
      opacity: 0;
      transition: all 0.3s ease;
    }

    .notification.success {
      background-color: #10B981;
    }

    .notification.error {
      background-color: #EF4444;
    }

    .notification.show {
      transform: translateY(0);
      opacity: 1;
    }

    /* Sidebar Collapse */
    .transition-smooth { transition: all 0.2s ease-in-out; }
    .transition-opacity { transition: opacity 0.3s ease-in-out; }
    .transition-all { transition: all 0.3s ease-in-out; }
  </style>
</head>
<body class="bg-gray-50 dark:bg-slate-900 font-sans">
  <!-- Dashboard Container -->
  <div class="flex min-h-screen">
    <!-- Modern Sidebar -->
    <aside class="w-64 bg-white dark:bg-slate-800 fixed h-screen border-r border-gray-200 
dark:border-slate-700 z-30" id="sidebar">
        <!-- Sidebar Header -->
        <div class="h-16 flex items-center justify-between px-6 border-b border-gray-200 
dark:border-slate-700">
            <span class="text-lg font-semibold text-gray-800 dark:text-white">B&B Admin</span>
            <button id="sidebarCollapseBtn" class="p-2 rounded-lg hover:bg-gray-100 
dark:hover:bg-slate-700 transition-smooth">
                <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
            </button>
        </div>

        <!-- Navigation Menu -->
        <nav class="p-4">
            <div class="space-y-2">
                <!-- Dashboard -->
                <a href="admindash.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg 
text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth">
                    <i class="fas fa-chart-line w-5 h-5"></i>
                    <span class="font-medium">Dashboard</span>
                </a>

                <!-- Theme -->
                <a href="theme.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg 
bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 transition-smooth">
                    <i class="fas fa-palette w-5 h-5"></i>
                    <span class="font-medium">Manage Services</span>
                </a>

                <!-- Reservations -->
                <a href="reservations.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg 
text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth">
                    <i class="far fa-calendar-check w-5 h-5"></i>
                    <span class="font-medium">Manage Reservations</span>
                </a>

                <!-- Events -->
                <a href="events.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg 
text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth">
                    <i class="fas fa-glass-cheers w-5 h-5"></i>
                    <span class="font-medium">Manage Events</span>
                </a>

                <!-- Guests -->
                <a href="guest.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg 
text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth">
                    <i class="fas fa-users w-5 h-5"></i>
                    <span class="font-medium">Manage Guests</span>
                </a>

                <!-- Customers -->
                <a href="customer.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg 
text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth">
                    <i class="fas fa-user-circle w-5 h-5"></i>
                    <span class="font-medium">Manage Customers</span>
                </a>

                <!-- Settings -->
                <a href="settings.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth">
                    <i class="fas fa-cog w-5 h-5"></i>
                    <span class="font-medium">Settings</span>
                </a>

            </div>

            <!-- Bottom Section -->
            <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200 
dark:border-slate-700">
                <!-- Theme Toggle -->
                <button id="themeToggle" class="w-full flex items-center justify-between px-4 py-2.5 
rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth 
mb-4">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-moon w-5 h-5"></i>
                        <span class="font-medium">Dark Mode</span>
                    </div>
                    <div class="relative inline-block w-10 h-6 rounded-full bg-gray-200 dark:bg-slate-700" 
id="themeToggleIndicator">
                        <div class="absolute inset-y-0 left-0 w-6 h-6 transform translate-x-0 
dark:translate-x-4 bg-white dark:bg-primary-400 rounded-full shadow-md"></div>
                    </div>
                </button>

                <!-- Logout -->
                <a href="../logout.php" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg 
text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-smooth">
                    <i class="fas fa-sign-out-alt w-5 h-5"></i>
                    <span class="font-medium">Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 ml-64" id="mainContent">
      <!-- Top Navigation Bar -->
      <nav class="bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 px-6 py-3 
sticky top-0 z-20 shadow-sm">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <button id="mobileSidebarToggle" class="md:hidden p-2 rounded-lg hover:bg-gray-100 
dark:hover:bg-slate-700 transition-smooth">
                    <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
                </button>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Theme Management</h2>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Notifications -->
                <div class="relative">
                    <button id="notificationBtn" class="p-2 rounded-lg hover:bg-gray-100 
dark:hover:bg-slate-700 transition-smooth relative">
                        <i class="far fa-bell text-gray-600 dark:text-gray-300"></i>
                        <?php if (!$notificationsRead): ?>
                        <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full" id="notificationDot"></span>
                        <?php endif; ?>
                    </button>
                    <!-- Notification Dropdown -->
                    <div id="notificationPopup" class="hidden absolute right-0 mt-2 w-80 bg-white 
dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 z-50">
                        <div class="p-4 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-800 
dark:text-white">Notifications</h3>
                            <button id="markAllReadBtn" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium">
                                Mark All Read
                            </button>
                        </div>
                        <div class="p-2 max-h-80 overflow-y-auto">
                            <?php
                            // Fetch recent reservations (last 7 days)
                            $notifSql = "SELECT b.id, b.package_name, b.event_date, u.name AS customer_name, b.created_at 
                                         FROM bookings b 
                                         JOIN users u ON b.user_id = u.id 
                                         WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                                         ORDER BY b.created_at DESC 
                                         LIMIT 5";
                            $notifResult = $conn->query($notifSql);
                            
                            if ($notifResult && $notifResult->num_rows > 0) {
                                while($notif = $notifResult->fetch_assoc()) {
                                    $timeAgo = time_elapsed_string($notif['created_at']);
                                    echo '<div class="p-3 border-b border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-smooth">';
                                    echo '<div class="flex items-start">';
                                    echo '<div class="bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full p-2 mr-3">';
                                    echo '<i class="fas fa-calendar-plus"></i>';
                                    echo '</div>';
                                    echo '<div>';
                                    echo '<p class="text-gray-800 dark:text-white text-sm font-medium">';
                                    echo 'New ' . ucwords(strtolower(htmlspecialchars($notif['package_name']))) . ' booking';
                                    echo '</p>';
                                    echo '<p class="text-gray-600 dark:text-gray-300 text-xs">';
                                    echo 'By ' . htmlspecialchars($notif['customer_name']) . ' for ' . date('M d, Y', strtotime($notif['event_date']));
                                    echo '</p>';
                                    echo '<p class="text-gray-500 dark:text-gray-400 text-xs mt-1">' . $timeAgo . '</p>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="p-4 text-center text-gray-500 dark:text-gray-400">';
                                echo '<i class="fas fa-bell text-2xl mb-2"></i>';
                                echo '<p>No new notifications</p>';
                                echo '</div>';
                            }
                            
                            // Helper function to format time elapsed
                            function time_elapsed_string($datetime, $full = false) {
                                $now = new DateTime;
                                $ago = new DateTime($datetime);
                                $diff = $now->diff($ago);
                                
                                $diff->w = floor($diff->d / 7);
                                $diff->d -= $diff->w * 7;
                                
                                $string = array(
                                    'y' => 'year',
                                    'm' => 'month',
                                    'w' => 'week',
                                    'd' => 'day',
                                    'h' => 'hour',
                                    'i' => 'minute',
                                    's' => 'second',
                                );
                                
                                foreach ($string as $k => &$v) {
                                    if ($diff->$k) {
                                        $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
                                    } else {
                                        unset($string[$k]);
                                    }
                                }
                                
                                if (!$full) $string = array_slice($string, 0, 1);
                                return $string ? implode(', ', $string) . ' ago' : 'just now';
                            }
                            ?>
                        </div>
                        <div class="p-3 border-t border-gray-100 dark:border-slate-700 text-center">
                            <a href="reservations.php" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium">
                                View All Notifications
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Profile (No Dropdown) -->
                <div class="flex items-center space-x-3 p-2">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($username); 
?>&background=6366F1&color=fff" 
                         alt="Profile" 
                         class="w-8 h-8 rounded-full">
                    <span class="text-gray-700 dark:text-gray-200 font-medium"><?php echo 
ucfirst(htmlspecialchars($username)); ?></span>
                </div>
            </div>
        </div>
      </nav>

      <!-- Main Content Area with Hero Section -->
      <div class="p-6 bg-gray-50 dark:bg-slate-900 min-h-screen">
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-primary-600 to-indigo-600 rounded-xl shadow-lg mb-6 
overflow-hidden">
            <div class="relative p-8">
                <div class="relative z-10">
                    <h2 class="text-2xl font-bold text-white mb-2">Theme Management</h2>
                    <p class="text-primary-100">Customize your venue themes and services</p>
                </div>
                <!-- Decorative Elements -->
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full 
blur-2xl"></div>
                <div class="absolute bottom-0 left-0 -mb-4 -ml-4 w-32 h-32 bg-white/10 rounded-full 
blur-2xl"></div>
            </div>
        </div>

        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Total Themes -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm stat-card p-6 border 
border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 dark:text-gray-400 font-medium">Total Themes</h3>
                    <span class="p-2 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 
dark:text-indigo-400 rounded-lg">
                        <i class="fas fa-palette"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-gray-800 dark:text-white"><?php echo count($themes); 
?></p>
            </div>

            <!-- Total Services -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm stat-card p-6 border 
border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 dark:text-gray-400 font-medium">Total Services</h3>
                    <span class="p-2 bg-purple-100 dark:bg-purple-900/30 text-purple-600 
dark:text-purple-400 rounded-lg">
                        <i class="fas fa-hand-holding-heart"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-gray-800 dark:text-white"><?php echo count($services); 
?></p>
            </div>

            <!-- Last Updated -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm stat-card p-6 border 
border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 dark:text-gray-400 font-medium">Last Updated</h3>
                    <span class="p-2 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 
rounded-lg">
                        <i class="fas fa-calendar-check"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-gray-800 dark:text-white"><?php echo date("d M Y"); 
?></p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3 mb-6">
            <button id="addThemeBtn" class="flex items-center px-5 py-2.5 bg-indigo-600 text-white 
rounded-lg hover:bg-indigo-700 transition-smooth">
                <i class="fas fa-palette mr-2"></i>
                Add Theme
            </button>
            <button id="addServiceBtn" class="flex items-center px-5 py-2.5 bg-white dark:bg-slate-800 
text-gray-700 dark:text-white border border-gray-200 dark:border-slate-700 rounded-lg hover:bg-gray-50 
dark:hover:bg-slate-700 transition-smooth">
                <i class="fas fa-plus mr-2"></i>
                Add Service
            </button>
        </div>

        <!-- Main Content Sections -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Themes Section -->
          <div class="lg:col-span-2 space-y-6">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-semibold text-gray-700 dark:text-white">
                <i class="fas fa-palette text-indigo-600 mr-2"></i> Theme Collection
              </h2>
              <div class="text-sm text-gray-500 dark:text-gray-400">
                Showing <?php echo count($themes); ?> themes
              </div>
            </div>
            
            <!-- Theme Filters -->
            <div class="mb-6">
              <div class="flex flex-wrap items-center gap-2">
                <button class="package-filter active px-3 py-1.5 text-sm rounded-full bg-indigo-100 
text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 hover:bg-indigo-200 dark:hover:bg-indigo-900/50 
transition-smooth" 
                        data-package="all">
                  All Themes
                </button>
                <?php foreach($packages as $package): ?>
                  <button class="package-filter px-3 py-1.5 text-sm rounded-full bg-gray-100 text-gray-700 
dark:bg-slate-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-600 transition-smooth" 
                          data-package="<?php echo $package['id']; ?>">
                    <?php echo htmlspecialchars($package['name']); ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
            
            <?php if(count($themes) > 0): ?>
              <!-- Themes organized by package -->
              <?php foreach($packageThemes as $packageId => $package): ?>
                <div class="mb-8 package-section" data-package-id="<?php echo $packageId; ?>">
                  <div class="flex items-center mb-4">
                    <h3 class="text-lg font-semibold text-indigo-700 dark:text-indigo-400 border-b-2 
border-indigo-200 dark:border-indigo-900 pb-1">
                      <?php echo htmlspecialchars($package['package_name']); ?>
                      <span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-1">(<?php echo 
count($package['themes']); ?> themes)</span>
                    </h3>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach($package['themes'] as $theme): ?>
                    <div class="bg-white dark:bg-slate-800 rounded-xl overflow-hidden shadow-sm 
hover:shadow-md transition-all duration-300 group border border-gray-100 dark:border-slate-700">
                      <div class="relative aspect-w-16 aspect-h-9 overflow-hidden">
                        <img src="../uploads/themes/<?php echo htmlspecialchars($theme['image_path']); ?>" 
                            alt="<?php echo htmlspecialchars($theme['name']); ?>" 
                            class="w-full h-48 object-cover group-hover:scale-105 transition-transform 
duration-500 ease-in-out">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent 
opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end">
                          <div class="p-4 w-full">
                            <div class="flex justify-between items-center">
                              <h3 class="text-white font-bold truncate"><?php echo 
htmlspecialchars($theme['name']); ?></h3>
                              <div class="flex space-x-2">
                                <button class="edit-theme-btn p-2 bg-blue-500 text-white rounded-full 
hover:bg-blue-600 transition-smooth"
                                        data-id="<?php echo $theme['id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($theme['name']); ?>"
                                        data-image="<?php echo htmlspecialchars($theme['image_path']); ?>"
                                        data-description="<?php echo 
htmlspecialchars($theme['description']); ?>"
                                        data-packages="<?php echo htmlspecialchars($theme['packages']); 
?>">
                                  <i class="fas fa-edit"></i>
                                </button>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="p-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white"><?php echo 
htmlspecialchars($theme['name']); ?></h3>
                        <?php if(!empty($theme['description'])): ?>
                          <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 line-clamp-2"><?php echo 
htmlspecialchars($theme['description']); ?></p>
                        <?php endif; ?>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="bg-white dark:bg-slate-800 rounded-xl p-10 text-center border border-gray-100 
dark:border-slate-700">
                <div class="flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                  <i class="fas fa-palette text-5xl mb-4"></i>
                  <h3 class="text-xl font-medium mb-2">No Themes Available</h3>
                  <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">Add your first theme by 
clicking the "Add Theme" button above.</p>
                </div>
              </div>
            <?php endif; ?>
          </div>
          
          <!-- Services Section -->
          <div class="lg:col-span-1 space-y-6">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-semibold text-gray-700 dark:text-white">
                <i class="fas fa-hand-holding-heart text-indigo-600 mr-2"></i> Services
              </h2>
            </div>
            
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 
dark:border-slate-700 overflow-hidden">
              <?php if(count($services) > 0): ?>
              <ul class="divide-y divide-gray-100 dark:divide-slate-700">
                <?php foreach($services as $service): ?>
                <li class="hover:bg-gray-50 dark:hover:bg-slate-700/25 transition-smooth">
                  <div class="p-4">
                    <div class="flex items-center justify-between">
                      <div>
                        <h3 class="font-semibold text-gray-800 dark:text-white cursor-pointer 
service-info-btn" data-id="<?php echo $service['id']; ?>" data-name="<?php echo 
htmlspecialchars($service['name']); ?>"><?php echo htmlspecialchars($service['name']); ?></h3>
                      </div>
                      <div class="flex space-x-1">
                        <button class="add-to-service-btn p-2 text-green-600 dark:text-green-400 
hover:bg-green-50 dark:hover:bg-green-900/20 rounded transition-smooth"
                                data-id="<?php echo $service['id']; ?>"
                                data-name="<?php echo htmlspecialchars($service['name']); ?>">
                          <i class="fas fa-plus-circle"></i>
                        </button>
                        <button class="edit-service-btn p-2 text-blue-600 dark:text-blue-400 
hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded transition-smooth"
                                data-id="<?php echo $service['id']; ?>"
                                data-name="<?php echo htmlspecialchars($service['name']); ?>">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button class="delete-service-btn p-2 text-red-600 dark:text-red-400 
hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-smooth"
                                data-id="<?php echo $service['id']; ?>">
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </li>
                <?php endforeach; ?>
              </ul>
              <?php else: ?>
              <div class="p-10 text-center">
                <div class="flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                  <i class="fas fa-hand-holding-heart text-5xl mb-4"></i>
                  <h3 class="text-xl font-medium mb-2">No Services Available</h3>
                  <p class="text-gray-500 dark:text-gray-400">Add your first service to get started.</p>
                </div>
              </div>
              <?php endif; ?>
            </div>
            
            <!-- Quick Info Card -->
            <div class="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 
dark:to-purple-900/20 rounded-xl p-6 border border-indigo-100 dark:border-indigo-900/50">
              <h3 class="font-semibold text-indigo-800 dark:text-indigo-300 mb-3">Quick Tips</h3>
              <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                <li class="flex items-start">
                  <i class="fas fa-info-circle text-indigo-500 dark:text-indigo-400 mt-1 mr-2"></i>
                  <span>Add appealing images to showcase your themes properly</span>
                </li>
                <li class="flex items-start">
                  <i class="fas fa-info-circle text-indigo-500 dark:text-indigo-400 mt-1 mr-2"></i>
                  <span>Keep service names concise and descriptive</span>
                </li>
                <li class="flex items-start">
                  <i class="fas fa-info-circle text-indigo-500 dark:text-indigo-400 mt-1 mr-2"></i>
                  <span>Regularly update pricing to reflect current offerings</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Theme Modal -->
      <div id="themeModal" class="modal">
        <div class="modal-content bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white" id="modalTitle">Add New 
Theme</h2>
            <button id="closeModalBtn" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <form id="themeForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_theme">
            <input type="hidden" id="themeId" name="themeId">
            
            <div class="mb-4">
              <label for="themeName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 
mb-1">Theme Name</label>
              <input type="text" id="themeName" name="themeName" class="w-full px-3 py-2 border 
border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 rounded-md focus:outline-none 
focus:ring-2 focus:ring-indigo-500 dark:text-white" required>
            </div>

            <div class="mb-4">
              <label for="themeDescription" class="block text-sm font-medium text-gray-700 
dark:text-gray-300 mb-1">Description</label>
              <textarea id="themeDescription" name="themeDescription" rows="3" class="w-full px-3 py-2 
border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 rounded-md focus:outline-none 
focus:ring-2 focus:ring-indigo-500 dark:text-white"></textarea>
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Available For 
Packages</label>
              <div class="max-h-28 overflow-y-auto border border-gray-300 dark:border-slate-600 bg-white 
dark:bg-slate-700 rounded-md p-2">
                <?php if(count($packages) > 0): ?>
                  <?php foreach($packages as $package): ?>
                    <div class="flex items-center space-x-2 py-1">
                      <input type="checkbox" id="package_<?php echo $package['id']; ?>" 
name="themePackages[]" value="<?php echo $package['id']; ?>" class="rounded text-indigo-600 
focus:ring-indigo-500 dark:bg-slate-600 dark:border-slate-500">
                      <label for="package_<?php echo $package['id']; ?>" class="text-sm text-gray-700 
dark:text-gray-200"><?php echo htmlspecialchars($package['name']); ?></label>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="text-sm text-gray-500 dark:text-gray-400 py-1">No packages available</p>
                <?php endif; ?>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Select all packages this theme can 
be used with</p>
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Theme 
Image</label>
              <div class="image-preview dark:border-slate-600" id="imagePreview">
                <i class="fas fa-image text-gray-400 dark:text-gray-500 text-4xl"></i>
              </div>
              <input type="file" id="themeImage" name="themeImage" accept="image/*" class="w-full text-sm 
text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 
file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-900/30 
dark:file:text-indigo-400 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/50">
              <p id="imageNote" class="text-xs text-gray-500 dark:text-gray-400 mt-1 hidden">Leave empty 
to keep current image</p>
            </div>
            
            <div class="mb-4">
              <label for="priceRange" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Price Range</label>
              <input type="text" id="priceRange" name="priceRange" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-white" placeholder="e.g. 5,000 - 10,000">
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Enter numbers only, the ₱ symbol will be added automatically</p>
            </div>

            <div class="flex justify-end space-x-3">
              <button type="button" id="cancelBtn" class="px-4 py-2 bg-gray-100 dark:bg-slate-700 
text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 
transition-smooth">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg 
hover:bg-indigo-700 transition-smooth">Save Theme</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Service Modal -->
      <div id="serviceModal" class="modal">
        <div class="modal-content bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white" id="serviceModalTitle">Add New 
Service</h2>
            <button id="closeServiceModalBtn" class="text-gray-400 hover:text-gray-600 
dark:hover:text-gray-300">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <form id="serviceForm">
            <input type="hidden" name="action" value="add_service">
            <input type="hidden" id="serviceId" name="serviceId">
            
            <div class="mb-4">
              <label for="serviceName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 
mb-1">Service Name</label>
              <input type="text" id="serviceName" name="serviceName" class="w-full px-3 py-2 border 
border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 rounded-md focus:outline-none 
focus:ring-2 focus:ring-indigo-500 dark:text-white" required>
            </div>

            <div class="flex justify-end space-x-3">
              <button type="button" id="cancelServiceBtn" class="px-4 py-2 bg-gray-100 dark:bg-slate-700 
text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 
transition-smooth">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg 
hover:bg-indigo-700 transition-smooth">Save Service</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div id="deleteConfirmModal" class="modal">
        <div class="modal-content bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg max-w-md mx-auto">
          <div class="text-center">
            <div class="mb-4">
              <i class="fas fa-exclamation-circle text-red-500 text-5xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Confirm Deletion</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6">Are you sure you want to delete this item? 
This action cannot be undone.</p>
            <div class="flex justify-center space-x-4">
              <button id="cancelDelete" class="px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-700 
dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 transition-smooth">
                Cancel
              </button>
              <button id="confirmDelete" class="px-4 py-2 bg-red-600 text-white rounded-lg 
hover:bg-red-700 transition-smooth">
                Yes, Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add to Service Modal -->
      <div id="addToServiceModal" class="modal">
        <div class="modal-content bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white" id="addToServiceTitle">Add 
Item to Service</h2>
            <button id="closeAddToServiceBtn" class="text-gray-400 hover:text-gray-600 
dark:hover:text-gray-300">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <form id="addToServiceForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_to_service">
            <input type="hidden" id="serviceIdForItem" name="serviceId">
            <input type="hidden" id="serviceType" name="serviceType">
            
            <div class="mb-4">
              <p class="text-gray-600 dark:text-gray-300 mb-3">Adding item to: <span id="serviceName" 
class="font-semibold"></span></p>
            </div>
            
            <div class="mb-4">
              <label for="itemName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 
mb-1">Name</label>
              <input type="text" id="itemName" name="itemName" class="w-full px-3 py-2 border 
border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 rounded-md focus:outline-none 
focus:ring-2 focus:ring-indigo-500 dark:text-white" required>
            </div>

            <div class="mb-4">
              <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 
mb-1">Phone Number</label>
              <input type="text" id="phone" name="phone" class="w-full px-3 py-2 border border-gray-300 
dark:border-slate-600 bg-white dark:bg-slate-700 rounded-md focus:outline-none focus:ring-2 
focus:ring-indigo-500 dark:text-white">
            </div>
            
            <div class="mb-4">
              <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 
mb-1">Email</label>
              <input type="email" id="email" name="email" class="w-full px-3 py-2 border border-gray-300 
dark:border-slate-600 bg-white dark:bg-slate-700 rounded-md focus:outline-none focus:ring-2 
focus:ring-indigo-500 dark:text-white">
            </div>
            
            <div class="mb-4">
              <label for="priceRange" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Price Range</label>
              <input type="text" id="priceRange" name="priceRange" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-white" placeholder="e.g. 5,000 - 10,000">
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Enter numbers only, the ₱ symbol will be added automatically</p>
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Image</label>
              <div class="image-preview dark:border-slate-600" id="itemImagePreview">
                <i class="fas fa-image text-gray-400 dark:text-gray-500 text-4xl"></i>
              </div>
              <input type="file" id="itemImage" name="itemImage" accept="image/*" class="w-full text-sm 
text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 
file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-900/30 
dark:file:text-indigo-400 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/50">
            </div>
            
            <div class="flex justify-end space-x-3">
              <button type="button" id="cancelAddToServiceBtn" class="px-4 py-2 bg-gray-100 
dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 
transition-smooth">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg 
hover:bg-indigo-700 transition-smooth">Save Item</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Notification Element -->
      <div id="notification" class="notification"></div>

      <!-- Success Message Toast -->
      <div id="successToast" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg 
shadow-lg transform translate-y-[-100px] opacity-0 transition-all duration-300 z-50 flex items-center">
        <i class="fas fa-check-circle mr-2"></i>
        <span id="successMessage"></span>
      </div>

      <!-- Service Info Modal -->
      <div id="serviceInfoModal" class="modal">
        <div class="modal-content bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white" id="serviceInfoTitle">Service 
Details</h2>
            <button id="closeServiceInfoBtn" class="text-gray-400 hover:text-gray-600 
dark:hover:text-gray-300">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <div id="serviceInfoContent">
            <div class="mb-4">
              <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">Service Items</h3>
              <div id="serviceItemsList" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 
max-h-80 overflow-y-auto">
                <div class="flex justify-center items-center py-6">
                  <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
                </div>
              </div>
            </div>
            
            <div class="flex justify-end space-x-3">
              <button type="button" id="closeServiceInfoModalBtn" class="px-4 py-2 bg-gray-100 
dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 
transition-smooth">Close</button>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const themeModal = document.getElementById('themeModal');
      const serviceModal = document.getElementById('serviceModal');
      const addThemeBtn = document.getElementById('addThemeBtn');
      const addServiceBtn = document.getElementById('addServiceBtn');
      const closeModalBtn = document.getElementById('closeModalBtn');
      const closeServiceModalBtn = document.getElementById('closeServiceModalBtn');
      const cancelBtn = document.getElementById('cancelBtn');
      const cancelServiceBtn = document.getElementById('cancelServiceBtn');
      const themeForm = document.getElementById('themeForm');
      const serviceForm = document.getElementById('serviceForm');
      const imagePreview = document.getElementById('imagePreview');
      const themeImage = document.getElementById('themeImage');
      const imageNote = document.getElementById('imageNote');
      const notification = document.getElementById('notification');
      const deleteConfirmModal = document.getElementById('deleteConfirmModal');

      // Function to show notification
      function showNotification(message, type = 'success') {
        notification.textContent = message;
        notification.className = `notification ${type}`;
        notification.classList.add('show');
        
        // Hide notification after 3 seconds
        setTimeout(() => {
          notification.classList.remove('show');
        }, 3000);
      }

      // Function to show success toast
      function showSuccessToast(message) {
        const toast = document.getElementById('successToast');
        const messageEl = document.getElementById('successMessage');
        messageEl.textContent = message;
        toast.classList.remove('translate-y-[-100px]', 'opacity-0');
        
        setTimeout(() => {
          toast.classList.add('translate-y-[-100px]', 'opacity-0');
        }, 3000);
      }

      // Show theme modal
      addThemeBtn.addEventListener('click', () => {
        themeModal.classList.add('show');
        document.getElementById('modalTitle').textContent = 'Add New Theme';
        themeForm.reset();
        themeForm.action.value = 'add_theme';
        imagePreview.innerHTML = '<i class="fas fa-image text-gray-400 dark:text-gray-500 text-4xl"></i>';
        themeImage.required = true;
        imageNote.classList.add('hidden');
      });

      // Show service modal
      addServiceBtn.addEventListener('click', () => {
        serviceModal.classList.add('show');
        document.getElementById('serviceModalTitle').textContent = 'Add New Service';
        serviceForm.reset();
        serviceForm.action.value = 'add_service';
      });

      // Close modals
      [closeModalBtn, cancelBtn].forEach(btn => {
        btn.addEventListener('click', () => {
          themeModal.classList.remove('show');
        });
      });

      [closeServiceModalBtn, cancelServiceBtn].forEach(btn => {
        btn.addEventListener('click', () => {
          serviceModal.classList.remove('show');
        });
      });

      // Image previews
      themeImage.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function(e) {
            imagePreview.innerHTML = `<img src="${e.target.result}" alt="Theme preview">`;
          }
          reader.readAsDataURL(file);
        }
      });

      // Edit theme buttons
      document.querySelectorAll('.edit-theme-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const themeId = this.getAttribute('data-id');
          const themeName = this.getAttribute('data-name');
          const themeImage = this.getAttribute('data-image');
          const themeDescription = this.getAttribute('data-description') || '';
          const themePackages = this.getAttribute('data-packages') || '';
          
          document.getElementById('modalTitle').textContent = 'Edit Theme';
          document.getElementById('themeId').value = themeId;
          document.getElementById('themeName').value = themeName;
          document.getElementById('themeDescription').value = themeDescription;
          
          // Update form action
          themeForm.querySelector('input[name="action"]').value = 'edit_theme';
          
          // Check appropriate package checkboxes
          const packageIds = themePackages.split(',');
          document.querySelectorAll('input[name="themePackages[]"]').forEach(checkbox => {
            checkbox.checked = packageIds.includes(checkbox.value);
          });
          
          // Show current image
          imagePreview.innerHTML = `<img src="../uploads/themes/${themeImage}" alt="Theme preview">`;
          
          // Make image upload optional for edit
          document.getElementById('themeImage').required = false;
          imageNote.classList.remove('hidden');
          
          themeModal.classList.add('show');
        });
      });

      // Delete theme buttons
      let itemToDelete = null;
      let deleteType = '';
      
      document.querySelectorAll('.delete-theme-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          itemToDelete = this.getAttribute('data-id');
          deleteType = 'theme';
          deleteConfirmModal.classList.add('show');
        });
      });

      // Edit service buttons
      document.querySelectorAll('.edit-service-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const serviceId = this.getAttribute('data-id');
          const serviceName = this.getAttribute('data-name');
          
          document.getElementById('serviceModalTitle').textContent = 'Edit Service';
          document.getElementById('serviceId').value = serviceId;
          document.getElementById('serviceName').value = serviceName;
          
          // Update form action
          serviceForm.querySelector('input[name="action"]').value = 'edit_service';
          
          serviceModal.classList.add('show');
        });
      });

      // Delete service buttons
      document.querySelectorAll('.delete-service-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          itemToDelete = this.getAttribute('data-id');
          deleteType = 'service';
          deleteConfirmModal.classList.add('show');
        });
      });

      // Handle cancel delete
      const cancelDeleteBtn = document.getElementById('cancelDelete');
      const confirmDeleteBtn = document.getElementById('confirmDelete');

      cancelDeleteBtn.addEventListener('click', () => {
        deleteConfirmModal.classList.remove('show');
        itemToDelete = null;
        deleteType = '';
      });

      // Handle confirm delete
      confirmDeleteBtn.addEventListener('click', async () => {
        if (!itemToDelete || !deleteType) return;
        
        const formData = new FormData();
        formData.append('action', `delete_${deleteType}`);
        formData.append(`${deleteType}Id`, itemToDelete);
        
        try {
          const response = await fetch('theme.php', {
            method: 'POST',
            body: formData
          });
          
          const data = await response.json();
          
          if (data.success) {
            showSuccessToast(data.message || `${deleteType} deleted successfully!`);
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            showNotification(data.error || `Error deleting ${deleteType}. Please try again.`, 'error');
          }
        } catch (error) {
          console.error('Error:', error);
          showNotification(`Error deleting ${deleteType}. Please try again.`, 'error');
        } finally {
          deleteConfirmModal.classList.remove('show');
          itemToDelete = null;
          deleteType = '';
        }
      });

      // Form submissions
      themeForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        try {
          const response = await fetch('theme.php', {
            method: 'POST',
            body: formData
          });
          
          const data = await response.json();
          
          if (data.success) {
            themeModal.classList.remove('show');
            showSuccessToast(data.message || 'Theme saved successfully!');
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            let errorMessage = 'Error saving theme. Please try again.';
            if (data.error) {
              errorMessage = data.error;
            }
            showNotification(errorMessage, 'error');
          }
        } catch (error) {
          console.error('Error saving theme:', error);
          showNotification('Error saving theme. Please try again.', 'error');
        }
      });

      serviceForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        try {
          const response = await fetch('theme.php', {
            method: 'POST',
            body: formData
          });
          
          const data = await response.json();
          
          if (data.success) {
            serviceModal.classList.remove('show');
            showSuccessToast(data.message || 'Service saved successfully!');
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            let errorMessage = 'Error saving service. Please try again.';
            if (data.error) {
              errorMessage = data.error;
            }
            showNotification(errorMessage, 'error');
          }
        } catch (error) {
          console.error('Error saving service:', error);
          showNotification('Error saving service. Please try again.', 'error');
        }
      });

      // Close delete confirmation modal when clicking outside
      deleteConfirmModal.addEventListener('click', function(e) {
        if (e.target === this) {
          this.classList.remove('show');
          itemToDelete = null;
          deleteType = '';
        }
      });

      // Package filter functionality
      const packageFilters = document.querySelectorAll('.package-filter');
      const packageSections = document.querySelectorAll('.package-section');
      
      packageFilters.forEach(filter => {
        filter.addEventListener('click', function() {
          const packageId = this.getAttribute('data-package');
          
          // Update active filter button
          packageFilters.forEach(btn => {
            btn.classList.remove('active', 'bg-indigo-100', 'text-indigo-700', 'dark:bg-indigo-900/30', 'dark:text-indigo-400');
            btn.classList.add('bg-gray-100', 'text-gray-700', 'dark:bg-slate-700', 'dark:text-gray-300');
          });
          
          this.classList.add('active', 'bg-indigo-100', 'text-indigo-700', 'dark:bg-indigo-900/30', 'dark:text-indigo-400');
          this.classList.remove('bg-gray-100', 'text-gray-700', 'dark:bg-slate-700', 'dark:text-gray-300');
          
          // Show/hide package sections
          if (packageId === 'all') {
            packageSections.forEach(section => {
              section.style.display = 'block';
            });
          } else {
            packageSections.forEach(section => {
              const sectionPackageId = section.getAttribute('data-package-id');
              if (sectionPackageId === packageId) {
                section.style.display = 'block';
              } else {
                section.style.display = 'none';
              }
            });
          }
        });
      });

      // Prevent closing when clicking inside the modal content
      document.querySelectorAll('.modal-content').forEach(content => {
        content.addEventListener('click', function(e) {
          e.stopPropagation();
        });
      });

      // Close modal when clicking outside the content
      document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
          if (e.target === this) {
            this.classList.remove('show');
          }
        });
      });

      // Close modal with Escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.remove('show');
          });
        }
      });

      // Mobile menu toggle
      const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
      mobileSidebarToggle.addEventListener('click', function() {
        const sidebar = document.querySelector('aside');
        sidebar.classList.toggle('hidden');
        document.body.classList.toggle('overflow-hidden');
        
        // Add overlay for mobile menu
        let overlay = document.getElementById('sidebarOverlay');
        if (!overlay) {
          overlay = document.createElement('div');
          overlay.id = 'sidebarOverlay';
          overlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-20 md:hidden';
          overlay.addEventListener('click', function() {
            sidebar.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            overlay.remove();
          });
          document.body.appendChild(overlay);
        } else {
          overlay.remove();
        }
      });

      // Notification popup functionality
      const notificationBtn = document.getElementById('notificationBtn');
      const notificationPopup = document.getElementById('notificationPopup');
      let isPopupVisible = false;

      // Notification Mark All Read functionality
      const markAllReadBtn = document.getElementById('markAllReadBtn');
      const notificationDot = document.getElementById('notificationDot');
      
      if (markAllReadBtn) {
          markAllReadBtn.addEventListener('click', function(e) {
              e.stopPropagation();
              
              // Send AJAX request to mark notifications as read
              fetch('theme.php', {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/x-www-form-urlencoded',
                  },
                  body: 'mark_read=1'
              })
              .then(response => response.json())
              .then(data => {
                  if (data.success) {
                      // Hide the notification dot
                      if (notificationDot) {
                          notificationDot.style.display = 'none';
                      }
                  }
              })
              .catch(error => {
                  console.error('Error marking notifications as read:', error);
              });
          });
      }

      notificationBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        isPopupVisible = !isPopupVisible;
        notificationPopup.classList.toggle('hidden');
      });

      // Close popup when clicking outside
      document.addEventListener('click', function(e) {
        if (isPopupVisible && !notificationPopup.contains(e.target) && 
!notificationBtn.contains(e.target)) {
          notificationPopup.classList.add('hidden');
          isPopupVisible = false;
        }
      });

      // Theme Toggle
      const themeToggle = document.getElementById('themeToggle');
      const html = document.documentElement;
      const themeToggleIndicator = document.getElementById('themeToggleIndicator');
      
      // Check for saved theme preference, otherwise use system preference
      if (localStorage.theme === 'dark' || (!('theme' in localStorage) && 
window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        html.classList.add('dark');
      } else {
        html.classList.remove('dark');
      }
      
      // Update toggle indicator position based on current theme
      function updateToggleIndicator() {
        const darkModeActive = html.classList.contains('dark');
        const toggleIndicator = themeToggleIndicator.querySelector('div');
        
        if (darkModeActive) {
          toggleIndicator.classList.add('translate-x-4');
          toggleIndicator.classList.remove('translate-x-0');
        } else {
          toggleIndicator.classList.add('translate-x-0');
          toggleIndicator.classList.remove('translate-x-4');
        }
      }
      
      // Initialize toggle position
      updateToggleIndicator();
      
      themeToggle.addEventListener('click', function() {
        html.classList.toggle('dark');
        localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
        updateToggleIndicator();
      });

      // Sidebar Collapse
      const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('mainContent');
      let isSidebarCollapsed = false;

      sidebarCollapseBtn.addEventListener('click', function() {
        isSidebarCollapsed = !isSidebarCollapsed;
        
        if (isSidebarCollapsed) {
          sidebar.style.width = '5rem';
          mainContent.style.marginLeft = '5rem';
          
          // Hide text in sidebar when collapsed
          const sidebarTexts = sidebar.querySelectorAll('span:not(.sr-only)');
          sidebarTexts.forEach(text => {
            text.style.display = 'none';
          });
          
          // Hide theme toggle indicator
          const themeToggleIndicator = document.getElementById('themeToggleIndicator');
          if (themeToggleIndicator) {
            themeToggleIndicator.style.display = 'none';
          }
        } else {
          sidebar.style.width = '16rem';
          mainContent.style.marginLeft = '16rem';
          
          // Show text in sidebar
          const sidebarTexts = sidebar.querySelectorAll('span:not(.sr-only)');
          sidebarTexts.forEach(text => {
            text.style.display = 'inline';
          });
          
          // Show theme toggle indicator
          const themeToggleIndicator = document.getElementById('themeToggleIndicator');
          if (themeToggleIndicator) {
            themeToggleIndicator.style.display = 'block';
          }
        }
      });

      // Add to service button functionality
      const addToServiceModal = document.getElementById('addToServiceModal');
      const closeAddToServiceBtn = document.getElementById('closeAddToServiceBtn');
      const cancelAddToServiceBtn = document.getElementById('cancelAddToServiceBtn');
      const addToServiceForm = document.getElementById('addToServiceForm');
      const itemImagePreview = document.getElementById('itemImagePreview');
      const itemImage = document.getElementById('itemImage');
      
      // Ensure all buttons have event listeners
      document.querySelectorAll('.add-to-service-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const serviceId = this.getAttribute('data-id');
          const serviceName = this.getAttribute('data-name');
          
          document.getElementById('serviceIdForItem').value = serviceId;
          document.getElementById('serviceName').textContent = serviceName;
          
          // Determine service type based on service name
          let serviceType = '';
          
          if (serviceName.toLowerCase().includes('band') || serviceName.toLowerCase().includes('music')) {
            serviceType = 'band';
            document.getElementById('addToServiceTitle').textContent = 'Add Band';
          } 
          else if (serviceName.toLowerCase().includes('photo') || serviceName.toLowerCase().includes('camera')) {
            serviceType = 'photographer';
            document.getElementById('addToServiceTitle').textContent = 'Add Photographer';
          }
          else {
            serviceType = 'generic';
            document.getElementById('addToServiceTitle').textContent = 'Add Item';
          }
          
          document.getElementById('serviceType').value = serviceType;
          
          // Reset form and show modal
          addToServiceForm.reset();
          itemImagePreview.innerHTML = '<i class="fas fa-image text-gray-400 dark:text-gray-500 text-4xl"></i>';
          addToServiceModal.classList.add('show');
        });
      });
      
      // Close add to service modal
      [closeAddToServiceBtn, cancelAddToServiceBtn].forEach(btn => {
        btn.addEventListener('click', () => {
          addToServiceModal.classList.remove('show');
        });
      });
      
      // Item image preview
      itemImage.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function(e) {
            itemImagePreview.innerHTML = `<img src="${e.target.result}" alt="Item preview">`;
          }
          reader.readAsDataURL(file);
        }
      });
      
      // Add to service form submission
      addToServiceForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        // Format price range with peso sign if needed
        const priceRange = formData.get('priceRange');
        if (priceRange && !priceRange.includes('₱')) {
          formData.set('priceRange', priceRange);
        }
        
        try {
          const response = await fetch('service_items.php', {
            method: 'POST',
            body: formData
          });
          
          const data = await response.json();
          
          if (data.success) {
            addToServiceModal.classList.remove('show');
            showSuccessToast(data.message || 'Item added successfully!');
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            let errorMessage = 'Error adding item. Please try again.';
            if (data.error) {
              errorMessage = data.error;
            }
            showNotification(errorMessage, 'error');
          }
        } catch (error) {
          console.error('Error adding item:', error);
          showNotification('Error adding item. Please try again.', 'error');
        }
      });

      // Service Info Modal
      const serviceInfoModal = document.getElementById('serviceInfoModal');
      const closeServiceInfoBtn = document.getElementById('closeServiceInfoBtn');
      const closeServiceInfoModalBtn = document.getElementById('closeServiceInfoModalBtn');
      const serviceInfoTitle = document.getElementById('serviceInfoTitle');
      const serviceItemsList = document.getElementById('serviceItemsList');
      
      document.querySelectorAll('.service-info-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
          const serviceId = this.getAttribute('data-id');
          const serviceName = this.getAttribute('data-name');
          
          // Set modal title
          serviceInfoTitle.textContent = serviceName + ' Details';
          
          // Show modal with loading state
          serviceInfoModal.classList.add('show');
          serviceItemsList.innerHTML = `
            <div class="flex justify-center items-center py-6">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
            </div>
          `;
          
          try {
            // Fetch service items for this service
            const response = await fetch(`get_service_items.php?service_id=${serviceId}`);
            const data = await response.json();
            
            if (data.success) {
              if (data.items && data.items.length > 0) {
                let itemsHtml = '';
                data.items.forEach(item => {
                  itemsHtml += `
                    <div class="border-b border-gray-100 dark:border-gray-700 py-3 last:border-0">
                      <div class="flex items-center">
                        ${item.image_path ? `
                          <div class="w-12 h-12 rounded-full overflow-hidden mr-3 bg-gray-100 dark:bg-gray-700">
                            <img src="../uploads/service_items/${item.image_path}" alt="${item.name}" class="w-full h-full object-cover">
                          </div>
                        ` : `
                          <div class="w-12 h-12 rounded-full overflow-hidden mr-3 bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                            <i class="fas fa-user text-gray-400"></i>
                          </div>
                        `}
                        <div>
                          <h4 class="font-medium text-gray-800 dark:text-white">${item.name}</h4>
                          <p class="text-sm text-gray-500 dark:text-gray-400">
                            ${item.price_range ? item.price_range : 'No price range specified'}
                          </p>
                        </div>
                      </div>
                      <div class="mt-2">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                          <i class="fas fa-phone-alt text-gray-400 mr-1"></i>
                          ${item.phone ? item.phone : 'No phone number provided'}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                          <i class="fas fa-envelope text-gray-400 mr-1"></i>
                          ${item.email ? item.email : 'No email provided'}
                        </p>
                      </div>
                    </div>
                  `;
                });
                serviceItemsList.innerHTML = itemsHtml;
              } else {
                serviceItemsList.innerHTML = `
                  <div class="text-center py-4">
                    <i class="fas fa-info-circle text-gray-400 text-2xl mb-2"></i>
                    <p class="text-gray-500 dark:text-gray-400">No items found for this service.</p>
                  </div>
                `;
              }
            } else {
              serviceItemsList.innerHTML = `
                <div class="text-center py-4">
                  <i class="fas fa-exclamation-circle text-red-400 text-2xl mb-2"></i>
                  <p class="text-gray-500 dark:text-gray-400">Error loading service items.</p>
                </div>
              `;
            }
          } catch (error) {
            console.error('Error fetching service items:', error);
            serviceItemsList.innerHTML = `
              <div class="text-center py-4">
                <i class="fas fa-exclamation-circle text-red-400 text-2xl mb-2"></i>
                <p class="text-gray-500 dark:text-gray-400">Error loading service items. Please try again.</p>
              </div>
            `;
          }
        });
      });
      
      // Close service info modal
      [closeServiceInfoBtn, closeServiceInfoModalBtn].forEach(btn => {
        btn.addEventListener('click', () => {
          serviceInfoModal.classList.remove('show');
        });
      });
    });
  </script>
</body>
</html>



