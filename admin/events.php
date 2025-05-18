<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php"); // Redirect to login if not authenticated
  exit();
}

// Fetch the admin's username from the session
$username = $_SESSION['user_name'];

// Check if notification has been read
$notificationsRead = isset($_SESSION['notifications_read']) ? $_SESSION['notifications_read'] : false;

// Handle marking notifications as read via AJAX
if (isset($_POST['mark_read']) && $_POST['mark_read'] == 1) {
    $_SESSION['notifications_read'] = true;
    echo json_encode(['success' => true]);
    exit;
}

include '../db/config.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit_event']) && $_POST['edit_event'] == 1) {
        // Update existing event
        $id = intval($_POST['event_id']);
        $event_name = mysqli_real_escape_string($conn, $_POST['event_name']);
        $price = floatval($_POST['price']);
        $package_details = mysqli_real_escape_string($conn, $_POST['package_details']);
        $inclusions = mysqli_real_escape_string($conn, $_POST['inclusions']);
        $terms = mysqli_real_escape_string($conn, $_POST['terms']);
        $status = isset($_POST['status']) ? mysqli_real_escape_string($conn, $_POST['status']) : 'active';
        $guest_capacity = intval($_POST['guest_capacity']);

        // Image upload handling for edit
        $update_image = false;
        $image_path = '';

        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] == 0) {
            $upload_dir = '../uploads/events/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = time() . '_' . basename($_FILES['event_image']['name']);
            $target_file = $upload_dir . $file_name;
            $check = getimagesize($_FILES['event_image']['tmp_name']);
            if ($check !== false) {
                if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
                    $image_path = 'uploads/events/' . $file_name;
                    $update_image = true;
                    if (!empty($_POST['current_image']) && file_exists('../' . $_POST['current_image'])) {
                        unlink('../' . $_POST['current_image']);
                    }
                }
            }
        }

        // Update data in database
        if ($update_image) {
            $sql = "UPDATE event_packages SET 
                    name = '$event_name', 
                    price = $price, 
                    description = '$package_details', 
                    inclusions = '$inclusions', 
                    terms = '$terms', 
                    image_path = '$image_path',
                    status = '$status',
                    guest_capacity = $guest_capacity 
                    WHERE id = $id";
        } else {
            $sql = "UPDATE event_packages SET 
                    name = '$event_name', 
                    price = $price, 
                    description = '$package_details', 
                    inclusions = '$inclusions', 
                    terms = '$terms',
                    status = '$status',
                    guest_capacity = $guest_capacity 
                    WHERE id = $id";
        }

        if ($conn->query($sql) === TRUE) {
            header("Location: events.php?update_success=1");
            exit();
        } else {
            header("Location: events.php?update_error=1&sql_error=" . urlencode($conn->error));
            exit();
        }
    } else {
        // Insert new event
        $event_name = mysqli_real_escape_string($conn, $_POST['event_name']);
        $price = floatval($_POST['price']);
        $package_details = mysqli_real_escape_string($conn, $_POST['package_details']);
        $inclusions = mysqli_real_escape_string($conn, $_POST['inclusions']);
        $terms = mysqli_real_escape_string($conn, $_POST['terms']);
        $status = isset($_POST['status']) ? mysqli_real_escape_string($conn, $_POST['status']) : 'active';
        $guest_capacity = intval($_POST['guest_capacity']);

        // Image upload handling
        $image_path = '';
        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] == 0) {
            $upload_dir = '../uploads/events/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = time() . '_' . basename($_FILES['event_image']['name']);
            $target_file = $upload_dir . $file_name;
            $check = getimagesize($_FILES['event_image']['tmp_name']);
            if ($check !== false) {
                if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
                    $image_path = 'uploads/events/' . $file_name;
                }
            }
        }

        // Insert data into database
        $sql = "INSERT INTO event_packages (name, price, description, inclusions, terms, image_path, status, guest_capacity) 
                VALUES ('$event_name', $price, '$package_details', '$inclusions', '$terms', '$image_path', '$status', $guest_capacity)";

        if ($conn->query($sql) === TRUE) {
            header("Location: events.php?success=1");
            exit();
        } else {
            header("Location: events.php?error=1");
            exit();
        }
    }
}

// Check if delete request is submitted
if(isset($_GET['delete']) && !empty($_GET['delete'])) {
  $id = intval($_GET['delete']);
  
  // Get image path before deleting
  $sql = "SELECT image_path FROM event_packages WHERE id = $id";
  $result = $conn->query($sql);
  
  if($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $image_path = $row['image_path'];
    
    // Delete the image file if it exists
    if(!empty($image_path) && file_exists('../' . $image_path)) {
      unlink('../' . $image_path);
    }
    
    // Delete the record
    $sql = "DELETE FROM event_packages WHERE id = $id";
    if($conn->query($sql) === TRUE) {
      header("Location: events.php?delete_success=1");
      exit();
    } else {
      header("Location: events.php?delete_error=1");
      exit();
    }
  }
}

// Get success or error messages
$success_message = '';
$error_message = '';

if(isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Event package added successfully!";
}
if(isset($_GET['error']) && $_GET['error'] == 1) {
    $error_message = "Error adding event package. Please try again.";
}
if(isset($_GET['delete_success']) && $_GET['delete_success'] == 1) {
  $success_message = "Event package deleted successfully!";
}
if(isset($_GET['delete_error']) && $_GET['delete_error'] == 1) {
  $error_message = "Error deleting event package. Please try again.";
}
if(isset($_GET['update_success']) && $_GET['update_success'] == 1) {
  $success_message = "Event package updated successfully!";
}
if(isset($_GET['update_error']) && $_GET['update_error'] == 1) {
  $error_message = "Error updating event package. Please try again.";
}

// Fetch all event packages from database with search and filter
$event_packages = [];
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build the SQL query based on search and filter
$sql = "SELECT ep.*, 
        (SELECT COUNT(*) FROM bookings b WHERE b.package_name = ep.name) as booking_count 
        FROM event_packages ep 
        WHERE 1=1";

// Add search condition if search term is provided
if (!empty($search_term)) {
  $sql .= " AND (ep.name LIKE '%$search_term%' OR ep.description LIKE '%$search_term%')";
}

// Add status filter condition
if ($status_filter == 'active') {
  $sql .= " AND ep.status = 'active'";
} else if ($status_filter == 'inactive') {
  $sql .= " AND ep.status = 'inactive'";
}

// Add order by
$sql .= " ORDER BY ep.id DESC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $event_packages[] = $row;
  }
}

// Get current search and filter values for form
$current_search = htmlspecialchars($search_term);
$current_filter = $status_filter;

// Update the upcoming reservations query to only show paid reservations
$upcoming_reservations_sql = "SELECT 
    b.id,
    b.event_date,
    b.event_start_time,
    b.event_end_time,
    COUNT(bg.id) as guest_count,
    b.package_name,
    b.total_amount,
    u.name as customer_name,
    pt.status
FROM bookings b
LEFT JOIN users u ON b.user_id = u.id
LEFT JOIN payment_transactions pt ON b.id = pt.booking_id
LEFT JOIN guests bg ON b.id = bg.booking_id
WHERE pt.status = 'paid' 
AND CONCAT(b.event_date, ' ', b.event_end_time) >= NOW()
GROUP BY b.id
ORDER BY b.event_date ASC, b.event_start_time ASC
LIMIT 3";

$upcoming_reservations = [];
$upcoming_result = $conn->query($upcoming_reservations_sql);

if ($upcoming_result && $upcoming_result->num_rows > 0) {
    while($row = $upcoming_result->fetch_assoc()) {
        $upcoming_reservations[] = $row;
    }
}

// Add these queries before the dashboard cards section

// Count total event packages
$total_events_sql = "SELECT COUNT(*) as count FROM event_packages";
$total_events_result = $conn->query($total_events_sql);
$total_events = $total_events_result->fetch_assoc()['count'];

// Count upcoming events (paid reservations for future dates)
$upcoming_events_sql = "SELECT COUNT(DISTINCT b.id) as count 
                        FROM bookings b 
                        JOIN payment_transactions pt ON b.id = pt.booking_id 
                        WHERE b.event_date >= CURDATE() 
                        AND pt.status = 'paid'";
$upcoming_events_result = $conn->query($upcoming_events_sql);
$upcoming_events = $upcoming_events_result->fetch_assoc()['count'];

// Calculate total revenue from paid transactions
$revenue_sql = "SELECT SUM(amount) as total FROM payment_transactions 
                WHERE status = 'paid'";
$revenue_result = $conn->query($revenue_sql);
$total_revenue = $revenue_result->fetch_assoc()['total'] ?: 0;

// Calculate booking rate (confirmed bookings / total bookings)
$booking_rate_sql = "SELECT 
                      (SELECT COUNT(*) FROM bookings WHERE status IN ('confirmed', 'completed') OR payment_status = 'paid') as confirmed,
                      COUNT(*) as total 
                    FROM bookings";
$booking_rate_result = $conn->query($booking_rate_sql);
$booking_data = $booking_rate_result->fetch_assoc();
$booking_rate = ($booking_data['total'] > 0) ? 
                ($booking_data['confirmed'] / $booking_data['total'] * 100) : 0;
$booking_rate = round($booking_rate);
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>The Barn & Backyard | Events Management</title>  
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
                  animation: {
                      'fade-in': 'fadeIn 0.5s ease-out',
                      'slide-in': 'slideIn 0.5s ease-out',
                      'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                  },
                  keyframes: {
                      fadeIn: {
                          '0%': { opacity: '0' },
                          '100%': { opacity: '1' },
                      },
                      slideIn: {
                          '0%': { transform: 'translateY(-10px)', opacity: '0' },
                          '100%': { transform: 'translateY(0)', opacity: '1' },
                      },
                  },
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
  
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
  
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="sidebar-style.css">

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
    
    .stat-card:hover {
        /* Removing hover animation effects */
        /* transform: translateY(-5px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); */
    }
    
    .dark .stat-card:hover {
        /* Removing dark mode hover effects */
        /* box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); */
    }
    
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
  </style>
</head>
<body class="bg-gray-50 dark:bg-slate-900 font-sans">
    <!-- Dashboard Container -->
    <div class="flex min-h-screen">
        <!-- Modern Sidebar -->
        <aside class="w-64 bg-white dark:bg-slate-800 fixed h-screen border-r border-gray-200 dark:border-slate-700 z-30" id="sidebar">
            <!-- Sidebar Header -->
            <div class="h-16 flex items-center justify-between px-6 border-b border-gray-200 dark:border-slate-700">
                <span class="text-lg font-semibold text-gray-800 dark:text-white">B&B Admin</span>
                <button id="sidebarCollapseBtn" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700">
                    <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
                </button>
            </div>

            <!-- Navigation Menu -->
            <nav class="p-4">
                <div class="space-y-2">
                    <!-- Dashboard -->
                    <a href="admindash.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
                        <i class="fas fa-chart-line w-5 h-5"></i>
                        <span class="font-medium">Dashboard</span>
                    </a>

                    <!-- Theme -->
                    <a href="theme.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
                        <i class="fas fa-palette w-5 h-5"></i>
                        <span class="font-medium">Manage Services</span>
                    </a>

                    <!-- Reservations -->
                    <a href="reservations.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
                        <i class="far fa-calendar-check w-5 h-5"></i>
                        <span class="font-medium">Manage Reservations</span>
                    </a>

                    <!-- Events -->
                    <a href="events.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400">
                        <i class="fas fa-glass-cheers w-5 h-5"></i>
                        <span class="font-medium">Manage Events</span>
                    </a>

                    <!-- Guests -->
                    <a href="guest.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
                        <i class="fas fa-users w-5 h-5"></i>
                        <span class="font-medium">Manage Guests</span>
                    </a>

                    <!-- Customers -->
                    <a href="customer.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
                        <i class="fas fa-user-circle w-5 h-5"></i>
                        <span class="font-medium">Manage Customers</span>
                    </a>

                    <!-- Settings -->
                    <a href="settings.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
                        <i class="fas fa-cog w-5 h-5"></i>
                        <span class="font-medium">Settings</span>
                    </a>
                </div>

                <!-- Bottom Section -->
                <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200 dark:border-slate-700">
                    <!-- Theme Toggle -->
                    <button id="themeToggle" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 mb-4">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-moon w-5 h-5"></i>
                            <span class="font-medium">Dark Mode</span>
                        </div>
                        <div class="relative inline-block w-10 h-6 rounded-full bg-gray-200 dark:bg-slate-700" id="themeToggleIndicator">
                            <div class="absolute inset-y-0 left-0 w-6 h-6 transform translate-x-0 dark:translate-x-4 bg-white dark:bg-primary-400 rounded-full shadow-md"></div>
                        </div>
                    </button>

                    <!-- Logout -->
                    <a href="../logout.php" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                        <i class="fas fa-sign-out-alt w-5 h-5"></i>
                        <span class="font-medium">Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-64" id="mainContent">
            <!-- Top Navigation Bar -->
            <nav class="bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 px-6 py-3 sticky top-0 z-20 shadow-sm">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <button id="mobileSidebarToggle" class="md:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-smooth">
                            <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
                        </button>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Events Management</h2>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <div class="relative">
                            <button id="notificationBtn" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-smooth relative">
                                <i class="far fa-bell text-gray-600 dark:text-gray-300"></i>
                                <?php if (!$notificationsRead): ?>
                                <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full" id="notificationDot"></span>
                                <?php endif; ?>
                            </button>
                            <!-- Notification Dropdown -->
                            <div id="notificationPopup" class="hidden absolute right-0 mt-2 w-80 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 z-50">
                                <div class="p-4 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center">
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Notifications</h3>
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
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($username); ?>&background=6366F1&color=fff" 
                                 alt="Profile" 
                                 class="w-8 h-8 rounded-full">
                            <span class="text-gray-700 dark:text-gray-200 font-medium hidden sm:inline"><?php echo ucfirst(htmlspecialchars($username)); ?></span>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content Area -->
            <div class="p-6 bg-gray-50 dark:bg-slate-900 min-h-screen">
                <!-- Welcome Banner -->
                <div class="bg-gradient-to-r from-primary-600 to-indigo-600 rounded-xl shadow-lg mb-6 overflow-hidden">
                    <div class="relative p-8">
                        <div class="relative z-10">
                            <h2 class="text-2xl font-bold text-white mb-2">Manage Events</h2>
                            <p class="text-primary-100">Create and manage your event packages to offer a variety of options to your customers.</p>
                        </div>
                        <!-- Decorative Elements -->
                        <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
                        <div class="absolute bottom-0 left-0 -mb-4 -ml-4 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if($success_message): ?>
                <div class="bg-green-100 dark:bg-green-900/30 border-l-4 border-green-500 text-green-700 dark:text-green-300 p-4 mb-6 rounded shadow-sm">
                  <div class="flex">
                    <div class="flex-shrink-0">
                      <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm"><?php echo $success_message; ?></p>
                    </div>
                  </div>
                </div>
                <?php endif; ?>
                
                <?php if($error_message): ?>
                <div class="bg-red-100 dark:bg-red-900/30 border-l-4 border-red-500 text-red-700 dark:text-red-300 p-4 mb-6 rounded shadow-sm">
                  <div class="flex">
                    <div class="flex-shrink-0">
                      <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm"><?php echo $error_message; ?></p>
                    </div>
                  </div>
                </div>
                <?php endif; ?>
                
                <!-- Upcoming Events Section -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden mb-6">
                    <div class="p-6 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-700 dark:text-white">Upcoming Events</h4>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Calendar view of all scheduled events</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button id="prevMonthBtn" class="p-2 rounded-full bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 transition-smooth">
                                <i class="fas fa-chevron-left text-gray-700 dark:text-gray-300"></i>
                            </button>
                            <span id="currentMonthYear" class="py-2 px-3 font-medium text-gray-700 dark:text-white"></span>
                            <button id="nextMonthBtn" class="p-2 rounded-full bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 transition-smooth">
                                <i class="fas fa-chevron-right text-gray-700 dark:text-gray-300"></i>
                            </button>
                            <button id="todayButton" class="px-4 py-2 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-lg hover:bg-primary-200 dark:hover:bg-primary-900/50 transition-smooth text-sm font-medium">
                                Today
                            </button>
                        </div>
                    </div>
                    <div class="p-4">
                        <!-- Calendar grid system -->
                        <div class="grid grid-cols-7 gap-1">
                            <!-- Days of week header -->
                            <div class="text-center font-medium text-gray-600 dark:text-gray-400 p-2">Sun</div>
                            <div class="text-center font-medium text-gray-600 dark:text-gray-400 p-2">Mon</div>
                            <div class="text-center font-medium text-gray-600 dark:text-gray-400 p-2">Tue</div>
                            <div class="text-center font-medium text-gray-600 dark:text-gray-400 p-2">Wed</div>
                            <div class="text-center font-medium text-gray-600 dark:text-gray-400 p-2">Thu</div>
                            <div class="text-center font-medium text-gray-600 dark:text-gray-400 p-2">Fri</div>
                            <div class="text-center font-medium text-gray-600 dark:text-gray-400 p-2">Sat</div>
                        </div>
                        
                        <!-- Calendar days container -->
                        <div id="inlineCalendarGrid" class="grid grid-cols-7 gap-1 mt-1">
                            <!-- Calendar cells will be added dynamically by JavaScript -->
                        </div>
                        
                        <!-- Event Legend -->
                        <div class="mt-4 border-t border-gray-200 dark:border-slate-700 pt-4">
                            <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Event Types</h5>
                            <div id="inlineLegendContainer" class="flex flex-wrap gap-x-4 gap-y-2 mt-2">
                                <!-- Legend items will be inserted here by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Events Content -->
                <div class="flex justify-between items-center mb-6">
                  <h3 class="text-2xl font-bold text-gray-800 dark:text-white">Manage Event Packages</h3>
                  <button id="addEventBtn" class="bg-primary-600 hover:bg-primary-700 dark:bg-primary-600 dark:hover:bg-primary-700 text-white px-5 py-2.5 rounded-lg font-medium transition-smooth flex items-center shadow-sm">
                    <i class="fas fa-plus mr-2"></i> Add New Package
                  </button>
                </div>

                <!-- Event List -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden mb-6">
                  <div class="p-6 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center">
                    <h4 class="text-lg font-semibold text-gray-700 dark:text-white">Current Event Packages</h4>
                    <form action="" method="get" class="flex space-x-2">
                      <div class="relative">
                        <input type="text" name="search" placeholder="Search packages..." 
                               value="<?php echo $current_search; ?>"
                               class="pl-10 pr-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-slate-700 dark:text-white text-sm">
                        <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 dark:text-gray-500"></i>
                      </div>
                      <select name="status" onchange="this.form.submit()" class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-slate-700 dark:text-white text-sm">
                        <option value="all" <?php echo $current_filter == 'all' ? 'selected' : ''; ?>>All Packages</option>
                        <option value="active" <?php echo $current_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $current_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                      </select>
                      <?php if (!empty($search_term) || $status_filter != 'all'): ?>
                        <a href="events.php" class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-slate-600 transition-smooth text-sm flex items-center">
                          <i class="fas fa-times mr-1"></i> Clear
                        </a>
                      <?php endif; ?>
                    </form>
                  </div>
                  <div class="overflow-x-auto">
                    <table class="w-full">
                      <thead>
                        <tr class="text-left bg-gray-50 dark:bg-slate-700/50">
                          <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Event Package</th>
                          <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Price</th>
                          <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Capacity</th>
                          <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                          <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bookings</th>
                          <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                        <?php if(count($event_packages) > 0): ?>
                          <?php foreach($event_packages as $package): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/25 transition-smooth">
                              <td class="px-6 py-4">
                                <div class="flex items-center">
                                  <div class="h-12 w-12 flex-shrink-0 rounded-lg overflow-hidden">
                                    <?php if(!empty($package['image_path'])): ?>
                                      <img src="../<?php echo $package['image_path']; ?>" alt="<?php echo $package['name']; ?>" class="h-full w-full object-cover">
                                    <?php else: ?>
                                      <div class="h-full w-full bg-gray-200 dark:bg-slate-700 flex items-center justify-center">
                                        <i class="fas fa-image text-gray-400 dark:text-gray-500"></i>
                                      </div>
                                    <?php endif; ?>
                                  </div>
                                  <div class="ml-4">
                                    <p class="text-gray-800 dark:text-white font-medium"><?php echo $package['name']; ?></p>
                                    <p class="text-gray-500 dark:text-gray-400 text-sm">Package #<?php echo $package['id']; ?></p>
                                  </div>
                                </div>
                              </td>
                              <td class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">₱<?php echo number_format($package['price'], 2); ?></td>
                              <td class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300"><?php echo isset($package['guest_capacity']) ? $package['guest_capacity'] . ' guests' : 'Not specified'; ?></td>
                              <td class="px-6 py-4">
                                <?php if($package['status'] == 'active'): ?>
                                  <span class="px-2 py-1 text-xs rounded-full bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">Active</span>
                                <?php else: ?>
                                  <span class="px-2 py-1 text-xs rounded-full bg-gray-100 dark:bg-slate-700 text-gray-800 dark:text-gray-300">Inactive</span>
                                <?php endif; ?>
                              </td>
                              <td class="px-6 py-4">
                                <div class="flex items-center">
                                  <div class="w-16 bg-gray-200 dark:bg-slate-700 rounded-full h-2.5">
                                    <?php 
                                      // Calculate percentage for progress bar (max at 100%)
                                      $booking_count = $package['booking_count'] ?? 0;
                                      $max_bookings = 20; // Assuming 20 bookings is "full" capacity
                                      $percentage = min(100, ($booking_count / $max_bookings) * 100);
                                    ?>
                                    <div class="bg-green-500 dark:bg-green-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                  </div>
                                  <span class="ml-2 text-sm text-gray-600 dark:text-gray-400"><?php echo $booking_count; ?> bookings</span>
                                </div>
                              </td>
                              <td class="px-6 py-4">
                                <div class="flex space-x-3">
                                  <button class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 transition-smooth edit-package" 
                                          data-id="<?php echo $package['id']; ?>"
                                          data-name="<?php echo htmlspecialchars($package['name']); ?>"
                                          data-price="<?php echo $package['price']; ?>"
                                          data-description="<?php echo htmlspecialchars($package['description']); ?>"
                                          data-inclusions="<?php echo htmlspecialchars($package['inclusions']); ?>"
                                          data-terms="<?php echo htmlspecialchars($package['terms']); ?>"
                                          data-image="<?php echo $package['image_path']; ?>"
                                          data-status="<?php echo $package['status']; ?>"
                                          data-guest-capacity="<?php echo isset($package['guest_capacity']) ? $package['guest_capacity'] : ''; ?>"
                                          title="Edit">
                                    <i class="fas fa-edit"></i>
                                  </button>
                                  <button class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-smooth delete-package" 
                                          data-id="<?php echo $package['id']; ?>"
                                          title="Delete">
                                    <i class="fas fa-trash"></i>
                                  </button>
                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No event packages found. Add your first package!</td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                  <div class="px-6 py-4 border-t border-gray-200 dark:border-slate-700 flex items-center justify-between">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Showing 1 to <?php echo count($event_packages); ?> of <?php echo count($event_packages); ?> entries</p>
                    <div class="flex space-x-1">
                      <button class="px-3 py-1 border border-gray-300 dark:border-slate-600 rounded text-sm text-gray-700 dark:text-gray-300 disabled:opacity-50 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth">Previous</button>
                      <button class="px-3 py-1 border border-gray-300 dark:border-slate-600 rounded text-sm bg-primary-600 text-white hover:bg-primary-700 transition-smooth">1</button>
                      <button class="px-3 py-1 border border-gray-300 dark:border-slate-600 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth">2</button>
                      <button class="px-3 py-1 border border-gray-300 dark:border-slate-600 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth">3</button>
                      <button class="px-3 py-1 border border-gray-300 dark:border-slate-600 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth">Next</button>
                    </div>
                  </div>
                </div>

<!-- Add Event Form Modal -->
<div id="eventModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden backdrop-blur-sm transition-all duration-300">
  <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-100 mx-4 my-8">
    <!-- Modal Header -->
    <div class="p-6 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center sticky top-0 bg-white dark:bg-slate-800 z-10">
      <h3 class="text-xl font-bold text-gray-800 dark:text-white">Add New Event Package</h3>
      <button id="closeModal" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition-smooth">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>

    <!-- Modal Body -->
    <form class="p-6 bg-gray-50 dark:bg-slate-900" action="" method="post" enctype="multipart/form-data">
      <!-- Grid for Input Fields -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Event Name -->
        <div class="relative">
          <label for="event_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Event Package Name
          </label>
          <input type="text" id="event_name" name="event_name" required
            class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-2 focus:ring-primary-400 focus:border-primary-400 dark:bg-slate-700 dark:text-white text-sm transition-smooth">
        </div>

        <!-- Price -->
        <div class="relative">
          <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Price (₱)
          </label>
          <input type="number" id="price" name="price" required min="0" step="0.01"
            class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-2 focus:ring-primary-400 focus:border-primary-400 dark:bg-slate-700 dark:text-white text-sm transition-smooth">
        </div>

        <!-- Guest Capacity -->
        <div class="relative">
          <label for="guest_capacity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Guest Capacity
          </label>
          <input type="number" id="guest_capacity" name="guest_capacity" required min="1" step="1"
            class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-2 focus:ring-primary-400 focus:border-primary-400 dark:bg-slate-700 dark:text-white text-sm transition-smooth">
        </div>
      </div>

      <!-- Event Image Upload -->
      <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Event Image
        </label>
        <div class="flex items-center justify-center w-full">
          <label class="flex flex-col w-full h-36 border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-smooth bg-white dark:bg-slate-800 shadow-sm">
            <div class="flex flex-col items-center justify-center pt-5">
              <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 dark:text-gray-500 mb-2 group-hover:text-primary-500 transition-smooth"></i>
              <p class="text-sm text-gray-500 dark:text-gray-400">Click to upload or drag and drop</p>
              <p class="text-xs text-gray-400 dark:text-gray-500">PNG, JPG, GIF up to 10MB</p>
            </div>
            <input type="file" id="event_image" name="event_image" class="hidden" accept="image/*">
          </label>
        </div>
        <div id="image_preview" class="mt-3 hidden">
          <div class="relative w-40 h-40 rounded-lg overflow-hidden shadow-lg">
            <img id="preview_img" src="#" alt="Preview" class="w-full h-full object-cover">
            <button type="button" id="remove_image" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md hover:bg-red-600 transition-smooth">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Package Details -->
      <div class="mb-6">
        <details class="border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm bg-white dark:bg-slate-800 overflow-hidden" open>
          <summary class="text-sm font-medium text-gray-700 dark:text-gray-300 p-4 cursor-pointer bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth flex items-center justify-between">
            <span class="flex items-center">
              <i class="fas fa-box-open mr-2 text-primary-500 dark:text-primary-400"></i>
              Package Details
            </span>
            <i class="fas fa-chevron-down text-gray-400 dark:text-gray-500"></i>
          </summary>
          <div class="p-5 border-t border-gray-100 dark:border-slate-700">
            <!-- Additional Package Details -->
            <div class="relative">
              <label for="package_details" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Additional Package Details
              </label>
              <textarea id="package_details" name="package_details" rows="4"
                class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-2 focus:ring-primary-400 focus:border-primary-400 dark:bg-slate-700 dark:text-white text-sm transition-smooth resize-none"></textarea>
            </div>
          </div>
        </details>
      </div>

      <!-- Inclusions -->
      <div class="grid grid-cols-1 gap-6 mb-6">
        <details class="border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm bg-white dark:bg-slate-800 overflow-hidden">
          <summary class="text-sm font-medium text-gray-700 dark:text-gray-300 p-4 cursor-pointer bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth flex items-center justify-between">
            <span class="flex items-center"><i class="fas fa-check-circle text-green-500 dark:text-green-400 mr-2"></i> Inclusions</span>
            <i class="fas fa-chevron-down text-gray-400 dark:text-gray-500"></i>
          </summary>
          <div class="p-5 border-t border-gray-100 dark:border-slate-700">
            <div class="relative">
              <label for="inclusions" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                What's included in this package?
              </label>
              <textarea id="inclusions" name="inclusions" rows="4"
                class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-2 focus:ring-green-400 focus:border-green-400 dark:bg-slate-700 dark:text-white text-sm transition-smooth resize-none"></textarea>
            </div>
          </div>
        </details>
      </div>

      <!-- Terms & Conditions -->
      <details class="border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm bg-white dark:bg-slate-800 overflow-hidden mb-6">
        <summary class="text-sm font-medium text-gray-700 dark:text-gray-300 p-4 cursor-pointer bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth flex items-center justify-between">
          <span class="flex items-center"><i class="fas fa-file-contract text-gray-500 dark:text-gray-400 mr-2"></i> Terms & Conditions</span>
          <i class="fas fa-chevron-down text-gray-400 dark:text-gray-500"></i>
        </summary>
        <div class="p-5 border-t border-gray-100 dark:border-slate-700">
          <div class="relative">
            <label for="terms" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Booking terms and cancellation policy
            </label>
            <textarea id="terms" name="terms" rows="4"
              class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-2 focus:ring-gray-400 focus:border-gray-400 dark:bg-slate-700 dark:text-white text-sm transition-smooth resize-none"></textarea>
          </div>
        </div>
      </details>

      <!-- Package Status -->
      <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Package Status
        </label>
        <div class="flex space-x-4">
          <label class="inline-flex items-center">
            <input type="radio" name="status" value="active" checked class="form-radio h-4 w-4 text-primary-600 border-gray-300 dark:border-slate-600 focus:ring-primary-500">
            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
          </label>
          <label class="inline-flex items-center">
            <input type="radio" name="status" value="inactive" class="form-radio h-4 w-4 text-primary-600 border-gray-300 dark:border-slate-600 focus:ring-primary-500">
            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Inactive</span>
          </label>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="flex justify-end space-x-3 mt-8">
        <button type="button" id="cancelBtn" class="px-5 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth shadow-sm flex items-center">
          <i class="fas fa-times mr-2"></i> Cancel
        </button>
        <button type="submit" class="px-5 py-2.5 bg-primary-600 dark:bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 dark:hover:bg-primary-700 transition-smooth shadow-sm flex items-center">
          <i class="fas fa-save mr-2"></i> Save Package
        </button>
      </div>
    </form>
  </div>
</div>
      
        <!-- Edit Event Form Modal -->
        <div id="editEventModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden backdrop-blur-sm transition-all duration-300">
          <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-100 mx-4 my-8">
            <!-- Modal Header -->
            <div class="p-6 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center sticky top-0 bg-white dark:bg-slate-800 z-10">
              <h3 class="text-xl font-bold text-gray-800 dark:text-white">Edit Event Package</h3>
              <button id="closeEditModal" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition-smooth">
                <i class="fas fa-times text-xl"></i>
              </button>
            </div>

            <!-- Modal Body -->
            <form class="p-6 bg-gray-50 dark:bg-slate-900" action="" method="post" enctype="multipart/form-data">
              <input type="hidden" name="edit_event" value="1">
              <input type="hidden" name="event_id" id="edit_event_id">
              <input type="hidden" name="current_image" id="edit_current_image">
              
              <!-- Grid for Input Fields -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Event Name -->
                <div class="relative">
                  <label for="edit_event_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Event Package Name
                  </label>
                  <input type="text" id="edit_event_name" name="event_name" required
                    class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-2 focus:ring-primary-400 focus:border-primary-400 dark:bg-slate-700 dark:text-white text-sm transition-smooth">
                </div>

                <!-- Price -->
                <div class="relative">
                  <label for="edit_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Price (₱)
                  </label>
                  <input type="number" id="edit_price" name="price" required min="0" step="0.01"
                    class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-2 focus:ring-primary-400 focus:border-primary-400 dark:bg-slate-700 dark:text-white text-sm transition-smooth">
                </div>

                <!-- Guest Capacity -->
                <div class="relative">
                  <label for="edit_guest_capacity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Guest Capacity
                  </label>
                  <input type="number" id="edit_guest_capacity" name="guest_capacity" required min="1" step="1"
                    class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-2 focus:ring-primary-400 focus:border-primary-400 dark:bg-slate-700 dark:text-white text-sm transition-smooth">
                </div>
              </div>

              <!-- Event Image Upload -->
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Event Image
                </label>
                <div class="flex items-center justify-center w-full">
                  <label class="flex flex-col w-full h-36 border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-smooth bg-white dark:bg-slate-800 shadow-sm">
                    <div class="flex flex-col items-center justify-center pt-5">
                      <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 dark:text-gray-500 mb-2 group-hover:text-primary-500 transition-smooth"></i>
                      <p class="text-sm text-gray-500 dark:text-gray-400">Click to upload or drag and drop</p>
                      <p class="text-xs text-gray-400 dark:text-gray-500">PNG, JPG, GIF up to 10MB</p>
                    </div>
                    <input type="file" id="edit_event_image" name="event_image" class="hidden" accept="image/*">
                  </label>
                </div>
                <div id="edit_image_preview" class="mt-3 hidden">
                  <div class="relative w-40 h-40 rounded-lg overflow-hidden shadow-lg">
                    <img id="edit_preview_img" src="#" alt="Preview" class="w-full h-full object-cover">
                    <button type="button" id="edit_remove_image" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md hover:bg-red-600 transition-smooth">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Package Details -->
              <div class="mb-6">
                <details class="border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm bg-white dark:bg-slate-800 overflow-hidden" open>
                  <summary class="text-sm font-medium text-gray-700 dark:text-gray-300 p-4 cursor-pointer bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth flex items-center justify-between">
                    <span class="flex items-center">
                      <i class="fas fa-box-open mr-2 text-primary-500 dark:text-primary-400"></i>
                      Package Details
                    </span>
                    <i class="fas fa-chevron-down text-gray-400 dark:text-gray-500"></i>
                  </summary>
                  <div class="p-5 border-t border-gray-100 dark:border-slate-700">
                    <!-- Additional Package Details -->
                    <div class="relative">
                      <label for="edit_package_details" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Additional Package Details
                      </label>
                      <textarea id="edit_package_details" name="package_details" rows="3"
                        class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-2 focus:ring-primary-400 focus:border-primary-400 dark:bg-slate-700 dark:text-white text-sm transition-smooth resize-none"></textarea>
                    </div>
                  </div>
                </details>
              </div>

              <!-- Inclusions -->
              <div class="grid grid-cols-1 gap-6 mb-6">
                <details class="border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm bg-white dark:bg-slate-800 overflow-hidden" open>
                  <summary class="text-sm font-medium text-gray-700 dark:text-gray-300 p-4 cursor-pointer bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth flex items-center justify-between">
                    <span class="flex items-center"><i class="fas fa-check-circle text-green-500 dark:text-green-400 mr-2"></i> Inclusions</span>
                    <i class="fas fa-chevron-down text-gray-400 dark:text-gray-500"></i>
                  </summary>
                  <div class="p-5 border-t border-gray-100 dark:border-slate-700">
                    <div class="relative">
                      <label for="edit_inclusions" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        What's included in this package?
                      </label>
                      <textarea id="edit_inclusions" name="inclusions" rows="4"
                        class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-2 focus:ring-green-400 focus:border-green-400 dark:bg-slate-700 dark:text-white text-sm transition-smooth resize-none"></textarea>
                    </div>
                  </div>
                </details>
              </div>

              <!-- Terms & Conditions -->
              <details class="border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm bg-white dark:bg-slate-800 overflow-hidden mb-6" open>
                <summary class="text-sm font-medium text-gray-700 dark:text-gray-300 p-4 cursor-pointer bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth flex items-center justify-between">
                  <span class="flex items-center"><i class="fas fa-file-contract text-gray-500 dark:text-gray-400 mr-2"></i> Terms & Conditions</span>
                  <i class="fas fa-chevron-down text-gray-400 dark:text-gray-500"></i>
                </summary>
                <div class="p-5 border-t border-gray-100 dark:border-slate-700">
                  <div class="relative">
                    <label for="edit_terms" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      Booking terms and cancellation policy
                    </label>
                    <textarea id="edit_terms" name="terms" rows="4"
                      class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-2 focus:ring-gray-400 focus:border-gray-400 dark:bg-slate-700 dark:text-white text-sm transition-smooth resize-none"></textarea>
                  </div>
                </div>
              </details>

              <!-- Package Status -->
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Package Status
                </label>
                <div class="flex space-x-4">
                  <label class="inline-flex items-center">
                    <input type="radio" name="status" id="edit_status_active" value="active" class="form-radio h-4 w-4 text-primary-600 border-gray-300 dark:border-slate-600 focus:ring-primary-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                  </label>
                  <label class="inline-flex items-center">
                    <input type="radio" name="status" id="edit_status_inactive" value="inactive" class="form-radio h-4 w-4 text-primary-600 border-gray-300 dark:border-slate-600 focus:ring-primary-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Inactive</span>
                  </label>
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="flex justify-end space-x-3 mt-8">
                <button type="button" id="cancelEditBtn" class="px-5 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth shadow-sm flex items-center">
                  <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 bg-primary-600 dark:bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 dark:hover:bg-primary-700 transition-smooth shadow-sm flex items-center">
                  <i class="fas fa-save mr-2"></i> Update Package
                </button>
              </div>
            </form>
          </div>
        </div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden backdrop-blur-sm transition-all duration-300">
  <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all duration-300 scale-100 mx-4">
    <div class="p-6 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center bg-red-50 dark:bg-red-900/20 rounded-t-xl">
      <h3 class="text-xl font-bold text-red-700 dark:text-red-400">Confirm Deletion</h3>
      <button id="closeDeleteModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 transition-smooth">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="p-6 dark:text-white">
      <div class="flex items-center mb-4">
        <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center text-red-600 dark:text-red-400 mr-4">
          <i class="fas fa-exclamation-triangle text-xl"></i>
        </div>
        <p class="text-gray-700 dark:text-gray-300">Are you sure you want to delete this event package? This action cannot be undone.</p>
      </div>
      <div class="mt-8 flex justify-end space-x-3">
        <button id="cancelDeleteBtn" class="px-5 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-smooth shadow-sm">
          Cancel
        </button>
        <a id="confirmDeleteBtn" href="#" class="px-5 py-2.5 bg-red-600 dark:bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 dark:hover:bg-red-700 transition-smooth shadow-sm">
          Yes, Delete
        </a>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('translate-x-0');
            sidebar.classList.toggle('-translate-x-full');
            
            // Add overlay for mobile menu
            let overlay = document.getElementById('sidebarOverlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlay';
                overlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-20 md:hidden';
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('translate-x-0');
                    sidebar.classList.add('-translate-x-full');
                    document.body.classList.remove('overflow-hidden');
                    overlay.remove();
                });
                document.body.appendChild(overlay);
                document.body.classList.add('overflow-hidden');
            } else {
                overlay.remove();
                document.body.classList.remove('overflow-hidden');
            }
        });
    }

    // Sidebar Collapse
    const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
    const mainContent = document.getElementById('mainContent');
    let isSidebarCollapsed = false;

    if (sidebarCollapseBtn) {
        sidebarCollapseBtn.addEventListener('click', function() {
            isSidebarCollapsed = !isSidebarCollapsed;
            sidebar.style.width = isSidebarCollapsed ? '5rem' : '16rem';
            mainContent.style.marginLeft = isSidebarCollapsed ? '5rem' : '16rem';
            
            // Hide text in sidebar when collapsed
            const sidebarTexts = sidebar.querySelectorAll('span:not(.sr-only)');
            sidebarTexts.forEach(text => {
                text.style.display = isSidebarCollapsed ? 'none' : 'inline';
            });
            
            // Hide/show theme toggle indicator
            const themeToggleIndicator = document.getElementById('themeToggleIndicator');
            if (themeToggleIndicator) {
                themeToggleIndicator.style.display = isSidebarCollapsed ? 'none' : 'block';
            }
        });
    }

    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;
    
    // Check for saved theme preference, otherwise use system preference
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        html.classList.add('dark');
    } else {
        html.classList.remove('dark');
    }
    
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            html.classList.toggle('dark');
            localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
        });
    }

    // Add Event Modal
    const addEventModal = document.getElementById('eventModal');
    const addEventBtn = document.getElementById('addEventBtn');
    const closeModal = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');

    // Add Event Button Click
    if(addEventBtn) {
        addEventBtn.addEventListener('click', function() {
            addEventModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }

    // Close Add Event Modal
    if(closeModal) {
        closeModal.addEventListener('click', function() {
            addEventModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }

    if(cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            addEventModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }

    // Edit Event Modal
    const editEventModal = document.getElementById('editEventModal');
    const editButtons = document.querySelectorAll('.edit-package');
    const closeEditModal = document.getElementById('closeEditModal');
    const cancelEditBtn = document.getElementById('cancelEditBtn');

    // Edit Button Click
    editButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const price = this.getAttribute('data-price');
            const description = this.getAttribute('data-description');
            const inclusions = this.getAttribute('data-inclusions');
            const terms = this.getAttribute('data-terms');
            const image = this.getAttribute('data-image');
            const status = this.getAttribute('data-status');
            const guestCapacity = this.getAttribute('data-guest-capacity');

            // Populate edit form
            document.getElementById('edit_event_id').value = id;
            document.getElementById('edit_event_name').value = name;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_package_details').value = description || '';
            document.getElementById('edit_inclusions').value = inclusions || '';
            document.getElementById('edit_terms').value = terms || '';
            document.getElementById('edit_current_image').value = image || '';
            document.getElementById('edit_guest_capacity').value = guestCapacity || '';

            // Set status
            if (status === 'active') {
                document.getElementById('edit_status_active').checked = true;
            } else {
                document.getElementById('edit_status_inactive').checked = true;
            }

            // Show image preview if available
            const editPreviewContainer = document.getElementById('edit_image_preview');
            const editPreviewImage = document.getElementById('edit_preview_img');
            if (image) {
                editPreviewImage.src = '../' + image;
                editPreviewContainer.classList.remove('hidden');
            } else {
                editPreviewContainer.classList.add('hidden');
            }

            // Show modal
            editEventModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close Edit Modal
    if(closeEditModal) {  
        closeEditModal.addEventListener('click', function() {
            editEventModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }

    if(cancelEditBtn) {
        cancelEditBtn.addEventListener('click', function() {
            editEventModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }

    // Delete Modal
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const deleteButtons = document.querySelectorAll('.delete-package');
    const closeDeleteModal = document.getElementById('closeDeleteModal');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    // Delete Button Click
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            confirmDeleteBtn.href = `events.php?delete=${id}`;
            deleteConfirmModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close Delete Modal
    if(closeDeleteModal) {
        closeDeleteModal.addEventListener('click', function() {
            deleteConfirmModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }

    if(cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function() {
            deleteConfirmModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }

    // Image Preview Functionality
    const fileInput = document.getElementById('event_image');
    const previewContainer = document.getElementById('image_preview');
    const previewImage = document.getElementById('preview_img');
    const removeButton = document.getElementById('remove_image');

    if(fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                }
                reader.readAsDataURL(file);
            }
        });
    }

    if(removeButton) {
        removeButton.addEventListener('click', function() {
            fileInput.value = '';
            previewContainer.classList.add('hidden');
        });
    }

    // Edit image preview functionality
    const editFileInput = document.getElementById('edit_event_image');
    const editPreviewContainer = document.getElementById('edit_image_preview');
    const editPreviewImage = document.getElementById('edit_preview_img');
    const editRemoveButton = document.getElementById('edit_remove_image');

    if(editFileInput) {
        editFileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    editPreviewImage.src = e.target.result;
                    editPreviewContainer.classList.remove('hidden');
                }
                reader.readAsDataURL(file);
            }
        });
    }

    if(editRemoveButton) {
        editRemoveButton.addEventListener('click', function() {
            editFileInput.value = '';
            document.getElementById('edit_current_image').value = '';
            editPreviewContainer.classList.add('hidden');
        });
    }

    // Notification popup functionality
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationPopup = document.getElementById('notificationPopup');
    let isPopupVisible = false;

    if (notificationBtn && notificationPopup) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            isPopupVisible = !isPopupVisible;
            notificationPopup.classList.toggle('hidden');
            
            if (isPopupVisible) {
                notificationPopup.classList.add('animate-fade-in');
            }
        });

        // Add Mark All Read functionality
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        const notificationDot = document.getElementById('notificationDot');
        
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                
                // Send AJAX request to mark notifications as read
                fetch('events.php', {
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

        // Close popup when clicking outside
        document.addEventListener('click', function(e) {
            if (isPopupVisible && !notificationPopup.contains(e.target) && e.target !== notificationBtn) {
                notificationPopup.classList.add('hidden');
                isPopupVisible = false;
            }
        });
    }

    // Add animation to stats cards
    const statCards = document.querySelectorAll('.stat-card');
    if (statCards.length > 0) {
        statCards.forEach((card, index) => {
            // Remove animation for immediate display without fade-in effect
            // setTimeout(() => {
            //     card.classList.add('animate-fade-in');
            // }, index * 100);
        });
    }

    // Handle keyboard events for modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Close any open modals when Escape key is pressed
            if (!addEventModal.classList.contains('hidden')) {
                addEventModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
            
            if (!editEventModal.classList.contains('hidden')) {
                editEventModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
            
            if (!deleteConfirmModal.classList.contains('hidden')) {
                deleteConfirmModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
            
            if (isPopupVisible) {
                notificationPopup.classList.add('hidden');
                isPopupVisible = false;
            }
        }
    });
});
</script>

<!-- Mobile Sidebar Overlay (for small screens) -->
<style>
@media (max-width: 768px) {
    #sidebar {
        transform: translateX(-100%);
        position: fixed;
        z-index: 40;
        top: 0;
        left: 0;
        height: 100vh;
        width: 16rem !important;
        transition: transform 0.3s ease-in-out;
    }
    
    #sidebar.translate-x-0 {
        transform: translateX(0);
    }
    
    #mainContent {
        margin-left: 0 !important;
    }
}
</style>

<!-- Calendar Modal -->
<div id="calendarModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden backdrop-blur-sm transition-all duration-300">
  <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-100 mx-4 my-8">
    <div class="p-6 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center sticky top-0 bg-white dark:bg-slate-800 z-10">
      <h3 class="text-xl font-bold text-gray-800 dark:text-white">Event Calendar</h3>
      <button id="closeCalendarModal" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition-smooth">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    
    <!-- Calendar Navigation -->
    <div class="px-6 pt-6 flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <button id="prevMonth" class="p-2 rounded-full bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 transition-smooth">
          <i class="fas fa-chevron-left text-gray-700 dark:text-gray-300"></i>
        </button>
        <button id="nextMonth" class="p-2 rounded-full bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 transition-smooth">
          <i class="fas fa-chevron-right text-gray-700 dark:text-gray-300"></i>
        </button>
        <h4 id="currentMonthYear" class="text-lg font-medium text-gray-800 dark:text-white ml-2">Month Year</h4>
      </div>
      <button id="todayBtn" class="px-4 py-2 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-lg hover:bg-primary-200 dark:hover:bg-primary-900/50 transition-smooth text-sm font-medium">
        Today
      </button>
    </div>
    
    <!-- Calendar Grid -->
    <div class="p-6">
      <!-- Days of week header -->
      <div class="grid grid-cols-7 mb-2">
        <div class="text-center text-sm font-medium text-gray-600 dark:text-gray-400">Sun</div>
        <div class="text-center text-sm font-medium text-gray-600 dark:text-gray-400">Mon</div>
        <div class="text-center text-sm font-medium text-gray-600 dark:text-gray-400">Tue</div>
        <div class="text-center text-sm font-medium text-gray-600 dark:text-gray-400">Wed</div>
        <div class="text-center text-sm font-medium text-gray-600 dark:text-gray-400">Thu</div>
        <div class="text-center text-sm font-medium text-gray-600 dark:text-gray-400">Fri</div>
        <div class="text-center text-sm font-medium text-gray-600 dark:text-gray-400">Sat</div>
      </div>
      
      <!-- Calendar days -->
      <div id="calendarGrid" class="grid grid-cols-7 gap-1">
        <!-- Calendar cells will be added dynamically -->
      </div>
    </div>
    
    <!-- Event Legend -->
    <div class="px-6 pb-6">
      <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Event Types</h5>
      <div class="flex flex-wrap gap-x-4 gap-y-2 mt-2">
        <!-- Legend items will be inserted here -->
      </div>
    </div>
  </div>
</div>

<!-- Event Details Popover -->
<div id="eventDetailsPopover" class="hidden fixed bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 z-50 w-72">
  <div class="px-4 py-3 border-b border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-700 rounded-t-lg">
    <h5 id="eventDate" class="font-medium text-gray-700 dark:text-white"></h5>
  </div>
  <div id="eventList" class="p-4 max-h-64 overflow-y-auto">
    <!-- Event items will be added dynamically -->
  </div>
</div>

<!-- Event Details Modal -->
<div id="eventDetailsModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden backdrop-blur-sm transition-all duration-300">
  <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-100 mx-4 my-8 animate-fade-in">
    <!-- Modal Header -->
    <div class="p-6 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center sticky top-0 bg-white dark:bg-slate-800 z-10">
      <h3 id="eventModalTitle" class="text-xl font-bold text-gray-800 dark:text-white">Event Details</h3>
      <button id="closeEventDetailsModal" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 p-2 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    
    <!-- Modal Body -->
    <div class="p-6">
      <!-- Event Header -->
      <div class="mb-6 flex flex-col sm:flex-row justify-between">
        <div>
          <h4 id="modalEventTitle" class="text-lg font-semibold text-gray-800 dark:text-white mb-1"></h4>
          <p id="modalEventDate" class="text-gray-600 dark:text-gray-400"></p>
        </div>
        <div class="mt-4 sm:mt-0">
          <span id="modalEventStatus" class="px-3 py-1 text-xs rounded-full font-medium inline-block"></span>
        </div>
      </div>
      
      <!-- Loading Indicator -->
      <div id="eventModalLoading" class="flex flex-col items-center justify-center py-12 hidden">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 dark:border-primary-400 mb-4"></div>
        <p class="text-gray-600 dark:text-gray-400">Loading event details...</p>
      </div>
      
      <!-- Event Details Content -->
      <div id="eventModalContent" class="hidden">
        <!-- Event Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div>
            <h5 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Event Information</h5>
            <div class="space-y-2">
              <div class="flex">
                <span class="text-gray-500 dark:text-gray-400 w-20">Time:</span>
                <span id="modalEventTime" class="text-gray-800 dark:text-white"></span>
              </div>
              <div class="flex">
                <span class="text-gray-500 dark:text-gray-400 w-20">Package:</span>
                <span id="modalEventPackage" class="text-gray-800 dark:text-white"></span>
              </div>
              <div class="flex">
                <span class="text-gray-500 dark:text-gray-400 w-20">Amount:</span>
                <span id="modalEventAmount" class="text-gray-800 dark:text-white"></span>
              </div>
            </div>
          </div>
          <div>
            <h5 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Customer Details</h5>
            <div class="space-y-2">
              <div class="flex">
                <span class="text-gray-500 dark:text-gray-400 w-20">Name:</span>
                <span id="modalCustomerName" class="text-gray-800 dark:text-white"></span>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Footer Actions -->
        <div class="mt-6 flex justify-end space-x-3">
          <a id="viewEventDetailsBtn" href="#" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-smooth">
            View Full Details
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Add the Calendar JavaScript here
document.addEventListener('DOMContentLoaded', function() {
  // Calendar Modal Elements
  const calendarModal = document.getElementById('calendarModal');
  const closeCalendarModal = document.getElementById('closeCalendarModal');
  const prevMonthBtn = document.getElementById('prevMonth');
  const nextMonthBtn = document.getElementById('nextMonth');
  const todayBtn = document.getElementById('todayBtn');
  const currentMonthYearEl = document.getElementById('currentMonthYear');
  const calendarGrid = document.getElementById('calendarGrid');
  
  // Event Popover Elements
  const eventDetailsPopover = document.getElementById('eventDetailsPopover');
  const eventDate = document.getElementById('eventDate');
  const eventList = document.getElementById('eventList');
  
  let currentDate = new Date();
  let currentMonth = currentDate.getMonth();
  let currentYear = currentDate.getFullYear();
  
  // Booking data structure (this would come from your PHP in the real implementation)
  // Structure: { "YYYY-MM-DD": [{ title, time, package, customer, status, id }] }
  let bookingData = {};
  
  // Fetch booking data with AJAX
  function fetchBookingData() {
    // This would be your AJAX call to get booking data
    // For now, we'll use dummy data
    
    // Sample data - in real implementation, you would fetch this from the server
    bookingData = {
      <?php 
        // Get all future bookings for the calendar
        $calendar_sql = "SELECT 
            MIN(b.id) as id,
            b.event_date,
            MIN(b.event_start_time) as event_start_time,
            MIN(b.event_end_time) as event_end_time,
            MIN(b.package_name) as package_name,
            MIN(b.theme_name) as theme_name,
            MIN(b.total_amount) as total_amount,
            MIN(u.name) as customer_name,
            MIN(pt.status) as status
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN payment_transactions pt ON b.id = pt.booking_id
        WHERE (pt.status = 'paid' OR pt.status = 'pending') 
        AND b.status != 'cancelled'
        GROUP BY b.event_date
        ORDER BY b.event_date ASC, b.event_start_time ASC";
        
        $calendar_result = $conn->query($calendar_sql);
        
        $calendar_data = array();
        if ($calendar_result && $calendar_result->num_rows > 0) {
            while($row = $calendar_result->fetch_assoc()) {
                $date = $row['event_date'];
                // We are now using GROUP BY in SQL so we should only get one event per date
                // Reset array to ensure only one event per date
                $calendar_data[$date] = array();
                
                // Format times
                $start = new DateTime($row['event_start_time']);
                $end = new DateTime($row['event_end_time']);
                $time_range = $start->format('g:i A') . ' - ' . $end->format('g:i A');
                
                $event_data = array(
                    'id' => $row['id'],
                    'title' => $row['package_name'],
                    'time' => $time_range,
                    'package' => $row['package_name'],
                    'customer' => $row['customer_name'],
                    'amount' => $row['total_amount'],
                    'status' => $row['status'] ?: 'pending'
                );
                
                // Add event to calendar data - will be the only event for this date
                $calendar_data[$date][] = $event_data;
            }
            
            // Output as JavaScript object
            foreach($calendar_data as $date => $events) {
                echo "'$date': " . json_encode($events) . ",\n";
            }
        }
      ?>
    };
    
    // After fetching data, render the calendar
    renderCalendar();
  }
  
  // Define Philippine Holidays
  function getPhilippineHolidays(year) {
    // Regular holidays
    const holidays = {
      // Regular Holidays
      [`${year}-01-01`]: "New Year's Day",
      [`${year}-04-09`]: "Maundy Thursday", // Approximate date for 2023
      [`${year}-04-10`]: "Good Friday", // Approximate date for 2023
      [`${year}-04-15`]: "Day of Valor",
      [`${year}-05-01`]: "Labor Day",
      [`${year}-06-12`]: "Independence Day",
      [`${year}-06-28`]: "Eid al-Adha", // Approximate date for 2023
      [`${year}-08-21`]: "Ninoy Aquino Day",
      [`${year}-08-28`]: "National Heroes Day", // Last Monday of August
      [`${year}-11-01`]: "All Saints' Day",
      [`${year}-11-30`]: "Bonifacio Day",
      [`${year}-12-08`]: "Feast of the Immaculate Conception",
      [`${year}-12-25`]: "Christmas Day",
      [`${year}-12-30`]: "Rizal Day",
      
      // Special Non-working Days
      [`${year}-02-25`]: "EDSA People Power Revolution",
      [`${year}-04-08`]: "Black Saturday", // Approximate date for 2023
      [`${year}-11-02`]: "All Souls' Day",
      [`${year}-12-24`]: "Christmas Eve",
      [`${year}-12-31`]: "New Year's Eve",
      [`${year}-01-22`]: "Chinese New Year" // Approximate date for 2023
    };
    
    return holidays;
  }
  
  // Function to capitalize first letter of each word
  function capitalizeWords(str) {
    return str.replace(/\w\S*/g, function(txt) {
      return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
    });
  }
  
  // Define package colors mapping
  const packageColors = {
    'wedding package': {
      bg: 'bg-pink-500',
      lightBg: 'bg-pink-100 dark:bg-pink-900/30',
      text: 'text-pink-800 dark:text-pink-300',
      indicator: 'bg-pink-500'
    },
    'birthday package': {
      bg: 'bg-purple-500',
      lightBg: 'bg-purple-100 dark:bg-purple-900/30',
      text: 'text-purple-800 dark:text-purple-300',
      indicator: 'bg-purple-500'
    },
    'christening package': {
      bg: 'bg-blue-500',
      lightBg: 'bg-blue-100 dark:bg-blue-900/30',
      text: 'text-blue-800 dark:text-blue-300',
      indicator: 'bg-blue-500'
    },
    'corporate event package': {
      bg: 'bg-emerald-500',
      lightBg: 'bg-emerald-100 dark:bg-emerald-900/30',
      text: 'text-emerald-800 dark:text-emerald-300',
      indicator: 'bg-emerald-500'
    },
    'corporate package': {
      bg: 'bg-emerald-500',
      lightBg: 'bg-emerald-100 dark:bg-emerald-900/30',
      text: 'text-emerald-800 dark:text-emerald-300',
      indicator: 'bg-emerald-500'
    },
    'debut package': {
      bg: 'bg-violet-500',
      lightBg: 'bg-violet-100 dark:bg-violet-900/30',
      text: 'text-violet-800 dark:text-violet-300',
      indicator: 'bg-violet-500'
    }
  };

  // Function to get package colors (with fallback)
  function getPackageColors(packageName) {
    if (!packageName) return defaultColors;
    
    const defaultColors = {
      bg: 'bg-gray-500',
      lightBg: 'bg-gray-100 dark:bg-gray-900/30',
      text: 'text-gray-800 dark:text-gray-300',
      indicator: 'bg-gray-500'
    };

    // Clean up package name for matching
    const cleanPackageName = packageName.toLowerCase().trim();
    
    // Try exact match first
    if (packageColors[cleanPackageName]) {
      return packageColors[cleanPackageName];
    }
    
    // Try to match partial package names
    for (const [key, colors] of Object.entries(packageColors)) {
      if (cleanPackageName.includes(key) || key.includes(cleanPackageName)) {
        return colors;
      }
    }
    
    return defaultColors;
  }

  // Update the Event Legend
  function updateEventLegend() {
    const legendContainer = document.querySelector('.px-6.pb-6 .flex.flex-wrap');
    if (!legendContainer) return;

    legendContainer.innerHTML = '';
    
    // Add legend items for each package type
    const legendItems = [
      { name: 'Wedding', color: packageColors['wedding package'].indicator },
      { name: 'Birthday', color: packageColors['birthday package'].indicator },
      { name: 'Christening', color: packageColors['christening package'].indicator },
      { name: 'Corporate', color: packageColors['corporate package'].indicator },
      { name: 'Debut', color: packageColors['debut package'].indicator },
      { name: 'Holiday', color: 'bg-orange-500' },
      { name: 'Event Over', color: 'bg-red-500' }
    ];
    
    legendItems.forEach(item => {
      const legendElement = document.createElement('div');
      legendElement.className = 'flex items-center';
      legendElement.innerHTML = `
        <div class="w-4 h-4 rounded-full ${item.color} mr-2"></div>
        <span class="text-sm text-gray-700 dark:text-gray-300">${item.name}</span>
      `;
      legendContainer.appendChild(legendElement);
    });
  }
  
  // Render the calendar
  function renderCalendar() {
    // Update month and year display
    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    currentMonthYearEl.textContent = `${monthNames[currentMonth]} ${currentYear}`;
    
    // Clear previous calendar
    calendarGrid.innerHTML = '';
    
    // Get first day of month and total days
    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    
    // Get holidays for current year
    const holidays = getPhilippineHolidays(currentYear);
    
    // Create empty cells for days before the first day of month
    for (let i = 0; i < firstDay; i++) {
      const emptyCell = document.createElement('div');
      emptyCell.className = 'h-24 border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/25 rounded-lg opacity-50';
      calendarGrid.appendChild(emptyCell);
    }
    
    // Create cells for each day of month
    for (let day = 1; day <= daysInMonth; day++) {
      const dateCell = document.createElement('div');
      const dateString = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
      const isToday = isDateToday(currentYear, currentMonth, day);
      const isHoliday = holidays[dateString];
      
      dateCell.className = `h-24 border ${isToday ? 'border-primary-500 dark:border-primary-500' : isHoliday ? 'border-orange-500 dark:border-orange-500' : 'border-gray-200 dark:border-slate-700'} bg-white dark:bg-slate-800 rounded-lg overflow-hidden flex flex-col relative hover:shadow-md transition-shadow duration-200`;
      
      // Add day number
      const dayNumber = document.createElement('div');
      dayNumber.className = `text-right p-1 ${isToday ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-300' : isHoliday ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300' : 'text-gray-700 dark:text-gray-300'}`;
      dayNumber.textContent = day;
      dateCell.appendChild(dayNumber);
      
      // If it's a holiday, add the holiday name
      if (isHoliday) {
        const holidayIndicator = document.createElement('div');
        holidayIndicator.className = 'px-1 text-xs font-medium bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300 truncate';
        holidayIndicator.textContent = holidays[dateString];
        holidayIndicator.title = holidays[dateString]; // For hover tooltip
        dateCell.appendChild(holidayIndicator);
      }
      
      // Check if there are events for this day
      if (bookingData[dateString]) {
        const events = bookingData[dateString];
        const eventsContainer = document.createElement('div');
        eventsContainer.className = 'px-1 overflow-hidden flex-1';
        
        // Determine if any events are over
        const now = new Date();
        const isAnyEventOver = events.some(event => {
          const eventEndTime = new Date(dateString + ' ' + event.time.split(' - ')[1]);
          return eventEndTime < now;
        });
        
        // Only show first event plus count of other events
        if (events.length > 0) {
          // Get package colors for first event
          const packageColors = getPackageColors(events[0].package);
          
          // Add indicator with color based on event status and package type
          const indicator = document.createElement('div');
          indicator.className = `h-3 w-3 rounded-full absolute top-1 left-1 ${isAnyEventOver ? 'bg-red-500' : packageColors.indicator}`;
          dateCell.appendChild(indicator);
          
          // Add event preview with package-specific colors
          const eventPreview = document.createElement('div');
          eventPreview.className = `text-xs p-1 rounded mb-1 cursor-pointer hover:bg-opacity-80 ${
            isAnyEventOver ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300' 
                       : `${packageColors.lightBg} ${packageColors.text}`
          }`;
          
          // Show first event title and count of remaining events if any
          eventPreview.textContent = capitalizeWords(events[0].title);
          // Remove the count indicator since we're filtering to only have one event per day
          // if (events.length > 1) {
          //   eventPreview.textContent += ` (+${events.length - 1} more)`;
          // }
          
          // Add click handler for the first event
          eventPreview.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent cell click handler from firing
            openEventDetailsModal(events[0], dateString, formattedDate(dateString));
          });
          
          eventsContainer.appendChild(eventPreview);
          
          // If there are multiple events with different packages, add a hint
          // if (events.length > 1) {
          //   const uniquePackages = new Set(events.map(e => e.package.toLowerCase()));
          //   if (uniquePackages.size > 1) {
          //     const multiPackageHint = document.createElement('div');
          //     multiPackageHint.className = 'text-xs text-gray-500 dark:text-gray-400 px-1';
          //     multiPackageHint.textContent = 'Multiple event types';
          //     eventsContainer.appendChild(multiPackageHint);
          //   }
          // }
        }
        
        dateCell.appendChild(eventsContainer);
        
        // Make cell clickable to show event details
        dateCell.addEventListener('click', (e) => {
          showEventDetails(dateString, events, e);
        });
        
        // Add double-click to directly open modal if there's only one event
        if (events.length === 1) {
          dateCell.addEventListener('dblclick', (e) => {
            e.preventDefault();
            openEventDetailsModal(events[0], dateString, formattedDate(dateString));
          });
          dateCell.title = "Double-click to view event details";
        }
        
        dateCell.classList.add('booked');
      }
      
      calendarGrid.appendChild(dateCell);
    }

    // Function to format date for modal display
    function formattedDate(dateString) {
      return new Date(dateString).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    }

    // Update the legend
    updateEventLegend();
  }
  
  // Check if a date is today
  function isDateToday(year, month, day) {
    const today = new Date();
    return today.getDate() === day && today.getMonth() === month && today.getFullYear() === year;
  }
  
  // Show event details popover
  function showEventDetails(dateString, events, e) {
    // Format date for display
    const formattedDate = new Date(dateString).toLocaleDateString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
    
    // Update popover title
    eventDate.textContent = formattedDate;
    
    // Clear previous event list
    eventList.innerHTML = '';
    
    // Add events to the list
    events.forEach(event => {
      const eventItem = document.createElement('div');
      eventItem.className = 'mb-3 pb-3 border-b border-gray-200 dark:border-slate-700 last:mb-0 last:pb-0 last:border-b-0';
      
      const now = new Date();
      const eventEndTime = new Date(dateString + ' ' + event.time.split(' - ')[1]);
      const isEventOver = eventEndTime < now;
      
      const packageColors = getPackageColors(event.package);
      
      eventItem.innerHTML = `
        <div class="flex justify-between items-start">
          <div>
            <h6 class="font-medium text-gray-800 dark:text-white cursor-pointer hover:text-primary-600 dark:hover:text-primary-400 event-title" data-event-id="${event.id}" data-event-date="${dateString}">${capitalizeWords(event.title)}</h6>
            <p class="text-sm text-gray-600 dark:text-gray-400">${event.time}</p>
            <p class="text-sm text-gray-500 dark:text-gray-500">${event.customer}</p>
          </div>
          <span class="px-2 py-1 text-xs rounded-full ${isEventOver ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300' : packageColors.lightBg + ' ' + packageColors.text}">
            ${isEventOver ? 'Event Over' : ''}
          </span>
        </div>
        <div class="mt-2">
          <p class="text-sm font-medium text-gray-600 dark:text-gray-400">₱${parseFloat(event.amount).toLocaleString()}</p>
          <a href="reservations.php?view=${event.id}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline mt-1 inline-block">
            View Details
          </a>
        </div>
      `;
      
      eventList.appendChild(eventItem);
      
      // Add event listener for clicking on event title in popover
      eventItem.querySelector('.event-title').addEventListener('click', function() {
        openEventDetailsModal(event, dateString, formattedDate);
      });
    });
    
    // Position the popover near the click
    const rect = e.currentTarget.getBoundingClientRect();
    const popoverWidth = 288; // w-72 = 18rem = 288px
    
    let left = e.clientX;
    let top = rect.bottom + window.scrollY + 5;
    
    // Adjust position if popover would go off screen
    if (left + popoverWidth > window.innerWidth) {
      left = window.innerWidth - popoverWidth - 10;
    }
    
    eventDetailsPopover.style.left = `${left}px`;
    eventDetailsPopover.style.top = `${top}px`;
    
    // Show the popover
    eventDetailsPopover.classList.remove('hidden');
    
    // Add document click listener to close popover
    setTimeout(() => {
      document.addEventListener('click', closePopover);
    }, 10);
  }
  
  // Open event details modal
  function openEventDetailsModal(event, dateString, formattedDate) {
    // Close the popover first if it's open
    if (!eventDetailsPopover.classList.contains('hidden')) {
      eventDetailsPopover.classList.add('hidden');
      document.removeEventListener('click', closePopover);
    }
    
    // Get elements
    const eventDetailsModal = document.getElementById('eventDetailsModal');
    const modalEventTitle = document.getElementById('modalEventTitle');
    const modalEventDate = document.getElementById('modalEventDate');
    const modalEventTime = document.getElementById('modalEventTime');
    const modalEventPackage = document.getElementById('modalEventPackage');
    const modalEventAmount = document.getElementById('modalEventAmount');
    const modalCustomerName = document.getElementById('modalCustomerName');
    const modalEventStatus = document.getElementById('modalEventStatus');
    const viewEventDetailsBtn = document.getElementById('viewEventDetailsBtn');
    const loadingIndicator = document.getElementById('eventModalLoading');
    const modalContent = document.getElementById('eventModalContent');
    
    // Show loading indicator and hide content
    loadingIndicator.classList.remove('hidden');
    modalContent.classList.add('hidden');
    
    // Show the modal
    eventDetailsModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Simulate loading (can be replaced with actual AJAX call if needed)
    setTimeout(() => {
      // Determine if event is over
      const now = new Date();
      const eventEndTime = new Date(dateString + ' ' + event.time.split(' - ')[1]);
      const isEventOver = eventEndTime < now;
      
      // Get package colors
      const packageColors = getPackageColors(event.package);
      
      // Update modal content
      modalEventTitle.textContent = capitalizeWords(event.title);
      modalEventDate.textContent = formattedDate;
      modalEventTime.textContent = event.time;
      modalEventPackage.textContent = capitalizeWords(event.package);
      modalEventAmount.textContent = '₱' + parseFloat(event.amount).toLocaleString();
      modalCustomerName.textContent = event.customer;
      
      // Set status with appropriate styling
      modalEventStatus.className = `px-3 py-1 text-xs rounded-full font-medium inline-block ${
        isEventOver ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300' : packageColors.lightBg + ' ' + packageColors.text
      }`;
      modalEventStatus.textContent = isEventOver ? 'Event Over' : 'Scheduled';
      
      // Add event over indication if applicable
      if (isEventOver) {
        modalEventTitle.classList.add('line-through', 'text-gray-500', 'dark:text-gray-400');
      } else {
        modalEventTitle.classList.remove('line-through', 'text-gray-500', 'dark:text-gray-400');
      }
      
      // Update view details link
      viewEventDetailsBtn.href = `reservations.php?view=${event.id}`;
      
      // Hide loading and show content
      loadingIndicator.classList.add('hidden');
      modalContent.classList.remove('hidden');
    }, 500); // Simulate 500ms loading time for better UX
  }

  // Add event listeners for closing the event details modal
  document.addEventListener('DOMContentLoaded', function() {
    const eventDetailsModal = document.getElementById('eventDetailsModal');
    
    if (!eventDetailsModal) return; // Exit if modal doesn't exist
    
    const closeEventDetailsModal = document.getElementById('closeEventDetailsModal');
    const modalContent = eventDetailsModal.querySelector('.bg-white.dark\\:bg-slate-800');
    
    // Ensure the close button works
    if (closeEventDetailsModal) {
      closeEventDetailsModal.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        eventDetailsModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
      });
    }
    
    // Close when clicking outside the modal content
    eventDetailsModal.addEventListener('click', function(e) {
      if (e.target === eventDetailsModal) {
        eventDetailsModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
      }
    });
    
    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        if (!eventDetailsModal.classList.contains('hidden')) {
          eventDetailsModal.classList.add('hidden');
          document.body.style.overflow = 'auto';
        }
      }
    });
  });
  
  // Close event details popover
  function closePopover(e) {
    if (!eventDetailsPopover.contains(e.target) && !e.target.classList.contains('booked')) {
      eventDetailsPopover.classList.add('hidden');
      document.removeEventListener('click', closePopover);
    }
  }
  
  // View Calendar button click
  if (viewCalendarBtn) {
    viewCalendarBtn.addEventListener('click', function() {
      fetchBookingData();
      calendarModal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    });
  }
  
  // Close Calendar Modal
  if (closeCalendarModal) {
    closeCalendarModal.addEventListener('click', function() {
      calendarModal.classList.add('hidden');
      document.body.style.overflow = 'auto';
      // Hide popover if open
      eventDetailsPopover.classList.add('hidden');
    });
  }
  
  // Month navigation
  if (prevMonthBtn) {
    prevMonthBtn.addEventListener('click', function() {
      currentMonth--;
      if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
      }
      renderCalendar();
    });
  }
  
  if (nextMonthBtn) {
    nextMonthBtn.addEventListener('click', function() {
      currentMonth++;
      if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
      }
      renderCalendar();
    });
  }
  
  // Today button
  if (todayBtn) {
    todayBtn.addEventListener('click', function() {
      const today = new Date();
      currentMonth = today.getMonth();
      currentYear = today.getFullYear();
      renderCalendar();
    });
  }
  
  // Close calendar modal on escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      if (!calendarModal.classList.contains('hidden')) {
        calendarModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
      }
      // Also hide popover
      eventDetailsPopover.classList.add('hidden');
    }
  });
});
</script>

<!-- Add event listeners for upcoming events in the main page -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Add listeners for upcoming events section
  const upcomingEvents = document.querySelectorAll('.flex.items-center.p-4.border');
  
  upcomingEvents.forEach(eventCard => {
    eventCard.addEventListener('click', function() {
      // Extract event details from the card
      const eventTitle = eventCard.querySelector('h5').textContent;
      const customerName = eventCard.querySelector('p:nth-of-type(1)').textContent.trim();
      const guestCount = eventCard.querySelector('.text-xs.text-gray-400').textContent.trim();
      const amount = eventCard.querySelector('.text-sm.text-gray-500:nth-of-type(1)').textContent;
      const status = eventCard.querySelector('.px-3.py-1').textContent.trim();
      const timeRange = eventCard.querySelector('.text-sm.text-gray-500:nth-of-type(2)').textContent;
      
      // Extract date from the card
      const month = eventCard.querySelector('.text-sm').textContent;
      const day = eventCard.querySelector('.text-lg').textContent;
      const year = new Date().getFullYear();
      const dateObj = new Date(`${month} ${day}, ${year}`);
      
      // Format date for display
      const formattedDate = dateObj.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
      
      // Create event object
      const event = {
        id: eventCard.getAttribute('data-id') || '0',
        title: eventTitle,
        package: eventTitle,
        time: timeRange,
        customer: customerName,
        amount: amount.replace('₱', ''),
        status: status.toLowerCase().includes('paid') ? 'paid' : 'pending'
      };
      
      // Prepare date string (YYYY-MM-DD format)
      const dateString = dateObj.toISOString().split('T')[0];
      
      // Open modal with event details
      openEventDetailsModal(event, dateString, formattedDate);
    });
    
    // Add cursor pointer class for better UX
    eventCard.classList.add('cursor-pointer');
  });
});
</script>

<!-- Add a direct event listener for the close button -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Close button for event details modal
  const closeEventDetailsBtn = document.getElementById('closeEventDetailsModal');
  const eventDetailsModal = document.getElementById('eventDetailsModal');
  
  console.log('Setting up event details modal close button:', closeEventDetailsBtn);
  
  if (closeEventDetailsBtn && eventDetailsModal) {
    closeEventDetailsBtn.onclick = function(e) {
      console.log('Close button clicked');
      e.preventDefault();
      e.stopPropagation();
      eventDetailsModal.classList.add('hidden');
      document.body.style.overflow = 'auto';
    };
    
    // Also add event listener in case onclick property is overridden
    closeEventDetailsBtn.addEventListener('click', function(e) {
      console.log('Close button clicked (event listener)');
      e.preventDefault();
      e.stopPropagation();
      eventDetailsModal.classList.add('hidden');
      document.body.style.overflow = 'auto';
    });
  }
});
</script>

<!-- Inline Calendar Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  console.log("DOM fully loaded, initializing calendar...");
  
  // When the page loads, automatically initialize the inline calendar
  initInlineCalendar();
  
  // Function to initialize the inline calendar on the Upcoming Events section
  function initInlineCalendar() {
    console.log("Initializing inline calendar...");
    
    // Get elements for the inline calendar
    const inlineCalendarGrid = document.getElementById('inlineCalendarGrid');
    const prevMonthBtn = document.getElementById('prevMonthBtn');
    const nextMonthBtn = document.getElementById('nextMonthBtn');
    const todayButton = document.getElementById('todayButton');
    const currentMonthYearEl = document.getElementById('currentMonthYear');
    const inlineLegendContainer = document.getElementById('inlineLegendContainer');
    
    if (!inlineCalendarGrid) {
      console.error("Calendar grid element not found!");
      return;
    }
    
    // State variables
    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();
    let bookingData = {};
    
    // Define package colors for event types
    const packageColorMappings = {
      'wedding package': {
        bg: 'bg-pink-500',
        lightBg: 'bg-pink-100 dark:bg-pink-900/30',
        text: 'text-pink-800 dark:text-pink-300',
        indicator: 'bg-pink-500'
      },
      'birthday package': {
        bg: 'bg-purple-500',
        lightBg: 'bg-purple-100 dark:bg-purple-900/30',
        text: 'text-purple-800 dark:text-purple-300',
        indicator: 'bg-purple-500'
      },
      'christening package': {
        bg: 'bg-blue-500',
        lightBg: 'bg-blue-100 dark:bg-blue-900/30',
        text: 'text-blue-800 dark:text-blue-300',
        indicator: 'bg-blue-500'
      },
      'corporate event package': {
        bg: 'bg-emerald-500',
        lightBg: 'bg-emerald-100 dark:bg-emerald-900/30',
        text: 'text-emerald-800 dark:text-emerald-300',
        indicator: 'bg-emerald-500'
      },
      'corporate package': {
        bg: 'bg-emerald-500',
        lightBg: 'bg-emerald-100 dark:bg-emerald-900/30',
        text: 'text-emerald-800 dark:text-emerald-300',
        indicator: 'bg-emerald-500'
      },
      'debut package': {
        bg: 'bg-violet-500',
        lightBg: 'bg-violet-100 dark:bg-violet-900/30',
        text: 'text-violet-800 dark:text-violet-300',
        indicator: 'bg-violet-500'
      }
    };
    
    // Initialize the inline calendar by fetching data and rendering
    fetchCalendarData();
    
    // Add navigation event listeners
    if (prevMonthBtn) {
      prevMonthBtn.addEventListener('click', function() {
        currentMonth--;
        if (currentMonth < 0) {
          currentMonth = 11;
          currentYear--;
        }
        renderCalendar();
      });
    }
    
    if (nextMonthBtn) {
      nextMonthBtn.addEventListener('click', function() {
        currentMonth++;
        if (currentMonth > 11) {
          currentMonth = 0;
          currentYear++;
        }
        renderCalendar();
      });
    }
    
    if (todayButton) {
      todayButton.addEventListener('click', function() {
        currentMonth = currentDate.getMonth();
        currentYear = currentDate.getFullYear();
        renderCalendar();
      });
    }
    
    // Fetch booking data for calendar
    function fetchCalendarData() {
      console.log("Fetching calendar data...");
      // Using the same data structure from the original calendar
      bookingData = {
        <?php 
          // Get all bookings for the calendar
          $calendar_sql = "SELECT 
              MIN(b.id) as id,
              b.event_date,
              MIN(b.event_start_time) as event_start_time,
              MIN(b.event_end_time) as event_end_time,
              MIN(b.package_name) as package_name,
              MIN(b.theme_name) as theme_name,
              MIN(b.total_amount) as total_amount,
              MIN(u.name) as customer_name,
              MIN(pt.status) as status
          FROM bookings b
          LEFT JOIN users u ON b.user_id = u.id
          LEFT JOIN payment_transactions pt ON b.id = pt.booking_id
          WHERE (pt.status = 'paid' OR pt.status = 'pending') 
          AND b.status != 'cancelled'
          GROUP BY b.event_date
          ORDER BY b.event_date ASC, b.event_start_time ASC";
          
          $calendar_result = $conn->query($calendar_sql);
          
          $calendar_data = array();
          if ($calendar_result && $calendar_result->num_rows > 0) {
              while($row = $calendar_result->fetch_assoc()) {
                  $date = $row['event_date'];
                  // We are now using GROUP BY in SQL so we should only get one event per date
                  // Reset array to ensure only one event per date
                  $calendar_data[$date] = array();
                  
                  // Format times
                  $start = new DateTime($row['event_start_time']);
                  $end = new DateTime($row['event_end_time']);
                  $time_range = $start->format('g:i A') . ' - ' . $end->format('g:i A');
                  
                  $event_data = array(
                      'id' => $row['id'],
                      'title' => $row['package_name'],
                      'time' => $time_range,
                      'package' => $row['package_name'],
                      'customer' => $row['customer_name'],
                      'amount' => $row['total_amount'],
                      'status' => $row['status'] ?: 'pending'
                  );
                  
                  $calendar_data[$date][] = $event_data;
              }
              
              // Output as JavaScript object
              foreach($calendar_data as $date => $events) {
                  echo "'$date': " . json_encode($events) . ",\n";
              }
          }
        ?>
      };
      
      console.log("Data fetched, rendering calendar...");
      renderCalendar();
      updateLegend();
    }
    
    // Render the inline calendar grid
    function renderCalendar() {
      console.log("Rendering calendar for: " + currentMonth + "/" + currentYear);
      
      // Update month and year display
      const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
      if (currentMonthYearEl) {
        currentMonthYearEl.textContent = `${monthNames[currentMonth]} ${currentYear}`;
      }
      
      // Clear previous calendar cells
      if (!inlineCalendarGrid) {
        console.error("Calendar grid element not found!");
        return;
      }
      
      inlineCalendarGrid.innerHTML = '';
      
      // Get first day of month and total days
      const firstDay = new Date(currentYear, currentMonth, 1).getDay();
      const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
      
      // Get holidays
      const holidays = getPhilippineHolidays(currentYear);
      
      console.log("First day of month: " + firstDay + ", Days in month: " + daysInMonth);
      
      // Create empty cells for days before the first day of month
      for (let i = 0; i < firstDay; i++) {
        const emptyCell = document.createElement('div');
        emptyCell.className = 'h-20 border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/25 rounded-lg opacity-50';
        inlineCalendarGrid.appendChild(emptyCell);
      }
      
      // Create cells for each day of month
      for (let day = 1; day <= daysInMonth; day++) {
        const dateCell = document.createElement('div');
        const dateString = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isToday = isDateToday(currentYear, currentMonth, day);
        const isHoliday = holidays[dateString];
        
        dateCell.className = `h-20 border ${isToday ? 'border-primary-500 dark:border-primary-500' : isHoliday ? 'border-orange-500 dark:border-orange-500' : 'border-gray-200 dark:border-slate-700'} bg-white dark:bg-slate-800 rounded-lg overflow-hidden flex flex-col relative hover:shadow-md transition-shadow duration-200`;
        
        // Add day number
        const dayNumber = document.createElement('div');
        dayNumber.className = `text-right p-1 ${isToday ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-300' : isHoliday ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300' : 'text-gray-700 dark:text-gray-300'}`;
        dayNumber.textContent = day;
        dateCell.appendChild(dayNumber);
        
        // If it's a holiday, add the holiday name
        if (isHoliday) {
          const holidayIndicator = document.createElement('div');
          holidayIndicator.className = 'px-1 text-xs font-medium bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300 truncate';
          holidayIndicator.textContent = holidays[dateString];
          holidayIndicator.title = holidays[dateString]; // For hover tooltip
          dateCell.appendChild(holidayIndicator);
        }
        
        // Check if there are events for this day
        if (bookingData[dateString]) {
          const events = bookingData[dateString];
          const eventsContainer = document.createElement('div');
          eventsContainer.className = 'px-1 overflow-hidden flex-1';
          
          // Determine if any events are over
          const now = new Date();
          const isAnyEventOver = events.some(event => {
            const eventEndTime = new Date(dateString + ' ' + event.time.split(' - ')[1]);
            return eventEndTime < now;
          });
          
          // Only show first event plus count of other events
          if (events.length > 0) {
            // Get package colors for first event
            const packageColors = getPackageColors(events[0].package);
            
            // Add indicator with color based on event status and package type
            const indicator = document.createElement('div');
            indicator.className = `h-3 w-3 rounded-full absolute top-1 left-1 ${isAnyEventOver ? 'bg-red-500' : packageColors.indicator}`;
            dateCell.appendChild(indicator);
            
            // Add event preview with package-specific colors
            const eventPreview = document.createElement('div');
            eventPreview.className = `text-xs p-1 rounded mb-1 cursor-pointer hover:bg-opacity-80 ${
              isAnyEventOver ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300' 
                        : `${packageColors.lightBg} ${packageColors.text}`
            }`;
            
            // Show first event title and count of remaining events if any
            eventPreview.textContent = capitalizeWords(events[0].title);
            // Remove the count indicator since we're filtering to only have one event per day
            // if (events.length > 1) {
            //   eventPreview.textContent += ` (+${events.length - 1} more)`;
            // }
            
            // Add click handler for the first event
            eventPreview.addEventListener('click', (e) => {
              e.stopPropagation();
              openEventDetailsModal(events[0], dateString, formatDate(dateString));
            });
            
            eventsContainer.appendChild(eventPreview);
            
            // If there are multiple events with different packages, add a hint
            // if (events.length > 1) {
            //   const uniquePackages = new Set(events.map(e => e.package.toLowerCase()));
            //   if (uniquePackages.size > 1) {
            //     const multiPackageHint = document.createElement('div');
            //     multiPackageHint.className = 'text-xs text-gray-500 dark:text-gray-400 px-1';
            //     multiPackageHint.textContent = 'Multiple event types';
            //     eventsContainer.appendChild(multiPackageHint);
            //   }
            // }
          }
          
          dateCell.appendChild(eventsContainer);
          
          // Make cell clickable to show event details
          dateCell.addEventListener('click', (e) => {
            showEventDetails(dateString, events, e);
          });
          
          // Add double-click to directly open modal if there's only one event
          if (events.length === 1) {
            dateCell.addEventListener('dblclick', (e) => {
              e.preventDefault();
              openEventDetailsModal(events[0], dateString, formatDate(dateString));
            });
            dateCell.title = "Double-click to view event details";
          }
          
          dateCell.classList.add('booked');
        }
        
        inlineCalendarGrid.appendChild(dateCell);
      }
      
      console.log("Calendar rendering complete.");
    }
    
    // Update the inline calendar legend
    function updateLegend() {
      if (!inlineLegendContainer) {
        console.error("Legend container element not found!");
        return;
      }

      inlineLegendContainer.innerHTML = '';
      
      // Add legend items for each package type
      const legendItems = [
        { name: 'Wedding', color: packageColorMappings['wedding package'].indicator },
        { name: 'Birthday', color: packageColorMappings['birthday package'].indicator },
        { name: 'Christening', color: packageColorMappings['christening package'].indicator },
        { name: 'Corporate', color: packageColorMappings['corporate package'].indicator },
        { name: 'Debut', color: packageColorMappings['debut package'].indicator },
        { name: 'Holiday', color: 'bg-orange-500' },
        { name: 'Event Over', color: 'bg-red-500' }
      ];
      
      legendItems.forEach(item => {
        const legendElement = document.createElement('div');
        legendElement.className = 'flex items-center';
        legendElement.innerHTML = `
          <div class="w-4 h-4 rounded-full ${item.color} mr-2"></div>
          <span class="text-sm text-gray-700 dark:text-gray-300">${item.name}</span>
        `;
        inlineLegendContainer.appendChild(legendElement);
      });
      
      console.log("Legend updated.");
    }
    
    // Helper Functions
    
    // Format a date string
    function formatDate(dateString) {
      return new Date(dateString).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    }
    
    // Define Philippine Holidays
    function getPhilippineHolidays(year) {
      return {
        [`${year}-01-01`]: "New Year's Day",
        [`${year}-04-09`]: "Maundy Thursday",
        [`${year}-04-10`]: "Good Friday",
        [`${year}-04-15`]: "Day of Valor",
        [`${year}-05-01`]: "Labor Day",
        [`${year}-06-12`]: "Independence Day",
        [`${year}-08-21`]: "Ninoy Aquino Day",
        [`${year}-08-28`]: "National Heroes Day",
        [`${year}-11-01`]: "All Saints' Day",
        [`${year}-11-30`]: "Bonifacio Day",
        [`${year}-12-08`]: "Feast of the Immaculate Conception",
        [`${year}-12-25`]: "Christmas Day",
        [`${year}-12-30`]: "Rizal Day",
        [`${year}-02-25`]: "EDSA People Power Revolution",
        [`${year}-11-02`]: "All Souls' Day",
        [`${year}-12-24`]: "Christmas Eve",
        [`${year}-12-31`]: "New Year's Eve"
      };
    }
    
    // Function to capitalize first letter of each word
    function capitalizeWords(str) {
      if (!str) return '';
      return str.replace(/\w\S*/g, function(txt) {
        return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
      });
    }
    
    // Check if a date is today
    function isDateToday(year, month, day) {
      const today = new Date();
      return today.getDate() === day && today.getMonth() === month && today.getFullYear() === year;
    }
    
    // Function to get package colors (with fallback)
    function getPackageColors(packageName) {
      const defaultColors = {
        bg: 'bg-gray-500',
        lightBg: 'bg-gray-100 dark:bg-gray-900/30',
        text: 'text-gray-800 dark:text-gray-300',
        indicator: 'bg-gray-500'
      };
      
      if (!packageName) return defaultColors;
      
      // Clean up package name for matching
      const cleanPackageName = packageName.toString().toLowerCase().trim();
      
      // Try exact match first
      if (packageColorMappings[cleanPackageName]) {
        return packageColorMappings[cleanPackageName];
      }
      
      // Try to match partial package names
      for (const [key, colors] of Object.entries(packageColorMappings)) {
        if (cleanPackageName.includes(key) || key.includes(cleanPackageName)) {
          return colors;
        }
      }
      
      return defaultColors;
    }
    
    // Show event details popover
    function showEventDetails(dateString, events, e) {
      const eventDetailsPopover = document.getElementById('eventDetailsPopover');
      const eventDate = document.getElementById('eventDate');
      const eventList = document.getElementById('eventList');
      
      if (!eventDetailsPopover || !eventDate || !eventList) {
        console.error("Popover elements not found!");
        return;
      }
      
      // Format date for display
      const formattedDate = formatDate(dateString);
      
      // Update popover title
      eventDate.textContent = formattedDate;
      
      // Clear previous event list
      eventList.innerHTML = '';
      
      // Add events to the list
      events.forEach(event => {
        const eventItem = document.createElement('div');
        eventItem.className = 'mb-3 pb-3 border-b border-gray-200 dark:border-slate-700 last:mb-0 last:pb-0 last:border-b-0';
        
        const now = new Date();
        const eventEndTime = new Date(dateString + ' ' + event.time.split(' - ')[1]);
        const isEventOver = eventEndTime < now;
        
        const packageColors = getPackageColors(event.package);
        
        eventItem.innerHTML = `
          <div class="flex justify-between items-start">
            <div>
              <h6 class="font-medium text-gray-800 dark:text-white cursor-pointer hover:text-primary-600 dark:hover:text-primary-400 event-title" data-event-id="${event.id}" data-event-date="${dateString}">${capitalizeWords(event.title)}</h6>
              <p class="text-sm text-gray-600 dark:text-gray-400">${event.time}</p>
              <p class="text-sm text-gray-500 dark:text-gray-500">${event.customer}</p>
            </div>
            <span class="px-2 py-1 text-xs rounded-full ${isEventOver ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300' : packageColors.lightBg + ' ' + packageColors.text}">
              ${isEventOver ? 'Event Over' : ''}
            </span>
          </div>
          <div class="mt-2">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">₱${parseFloat(event.amount).toLocaleString()}</p>
            <a href="reservations.php?view=${event.id}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline mt-1 inline-block">
              View Details
            </a>
          </div>
        `;
        
        eventList.appendChild(eventItem);
        
        // Add event listener for clicking on event title in popover
        eventItem.querySelector('.event-title').addEventListener('click', function() {
          openEventDetailsModal(event, dateString, formattedDate);
        });
      });
      
      // Position the popover near the click
      const rect = e.currentTarget.getBoundingClientRect();
      const popoverWidth = 288; // w-72 = 18rem = 288px
      
      let left = e.clientX;
      let top = rect.bottom + window.scrollY + 5;
      
      // Adjust position if popover would go off screen
      if (left + popoverWidth > window.innerWidth) {
        left = window.innerWidth - popoverWidth - 10;
      }
      
      eventDetailsPopover.style.left = `${left}px`;
      eventDetailsPopover.style.top = `${top}px`;
      
      // Show the popover
      eventDetailsPopover.classList.remove('hidden');
      
      // Add document click listener to close popover
      setTimeout(() => {
        document.addEventListener('click', closePopover);
      }, 10);
    }
    
    // Open event details modal
    function openEventDetailsModal(event, dateString, formattedDate) {
      // Get all modal elements
      const eventDetailsPopover = document.getElementById('eventDetailsPopover');
      const eventDetailsModal = document.getElementById('eventDetailsModal');
      const modalEventTitle = document.getElementById('modalEventTitle');
      const modalEventDate = document.getElementById('modalEventDate');
      const modalEventTime = document.getElementById('modalEventTime');
      const modalEventPackage = document.getElementById('modalEventPackage');
      const modalEventAmount = document.getElementById('modalEventAmount');
      const modalCustomerName = document.getElementById('modalCustomerName');
      const modalEventStatus = document.getElementById('modalEventStatus');
      const viewEventDetailsBtn = document.getElementById('viewEventDetailsBtn');
      const loadingIndicator = document.getElementById('eventModalLoading');
      const modalContent = document.getElementById('eventModalContent');
      
      // Close popover if open
      if (eventDetailsPopover && !eventDetailsPopover.classList.contains('hidden')) {
        eventDetailsPopover.classList.add('hidden');
        document.removeEventListener('click', closePopover);
      }
      
      if (!eventDetailsModal || !modalEventTitle || !modalEventDate || !modalEventTime || 
          !modalEventPackage || !modalEventAmount || !modalCustomerName || !modalEventStatus ||
          !viewEventDetailsBtn || !loadingIndicator || !modalContent) {
        console.error("Some modal elements are missing");
        return;
      }
      
      // Show loading indicator and hide content
      loadingIndicator.classList.remove('hidden');
      modalContent.classList.add('hidden');
      
      // Show the modal
      eventDetailsModal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      
      // Show event details after a short delay
      setTimeout(() => {
        // Determine if event is over
        const now = new Date();
        const eventEndTime = new Date(dateString + ' ' + event.time.split(' - ')[1]);
        const isEventOver = eventEndTime < now;
        
        // Get package colors
        const packageColors = getPackageColors(event.package);
        
        // Update modal content
        modalEventTitle.textContent = capitalizeWords(event.title);
        modalEventDate.textContent = formattedDate;
        modalEventTime.textContent = event.time;
        modalEventPackage.textContent = capitalizeWords(event.package);
        modalEventAmount.textContent = '₱' + parseFloat(event.amount).toLocaleString();
        modalCustomerName.textContent = event.customer;
        
        // Set status with appropriate styling
        modalEventStatus.className = `px-3 py-1 text-xs rounded-full font-medium inline-block ${
          isEventOver ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300' : packageColors.lightBg + ' ' + packageColors.text
        }`;
        modalEventStatus.textContent = isEventOver ? 'Event Over' : 'Scheduled';
        
        // Add event over indication if applicable
        if (isEventOver) {
          modalEventTitle.classList.add('line-through', 'text-gray-500', 'dark:text-gray-400');
        } else {
          modalEventTitle.classList.remove('line-through', 'text-gray-500', 'dark:text-gray-400');
        }
        
        // Update view details link
        viewEventDetailsBtn.href = `reservations.php?view=${event.id}`;
        
        // Hide loading and show content
        loadingIndicator.classList.add('hidden');
        modalContent.classList.remove('hidden');
      }, 500);
    }
    
    // Close event details popover
    function closePopover(e) {
      const eventDetailsPopover = document.getElementById('eventDetailsPopover');
      if (eventDetailsPopover && !eventDetailsPopover.contains(e.target) && !e.target.classList.contains('booked')) {
        eventDetailsPopover.classList.add('hidden');
        document.removeEventListener('click', closePopover);
      }
    }
  }
});
</script>
</body>
</html>

