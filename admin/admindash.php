<?php
session_start();

include '../db/config.php';
// Include the event status update script
include_once '../db/update_event_status.php';

// First, reset any events that might have been incorrectly marked as completed
$resetSql = "UPDATE bookings 
             SET status = 'confirmed', updated_at = NOW() 
             WHERE status = 'completed' 
             AND (
                 (event_date > CURDATE()) 
                 OR 
                 (event_date = CURDATE() AND event_end_time > CURTIME())
                 OR
                 payment_status NOT IN ('paid', 'partially_paid')
             )";
$conn->query($resetSql);

// Now run the update function to mark completed events
updateCompletedEventStatus();

// Check if notification has been read
$notificationsRead = isset($_SESSION['notifications_read']) ? $_SESSION['notifications_read'] : false;

// Handle marking notifications as read via AJAX
if (isset($_POST['mark_read']) && $_POST['mark_read'] == 1) {
    $_SESSION['notifications_read'] = true;
    echo json_encode(['success' => true]);
    exit;
}

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php"); // Redirect to login if not authenticated
  exit();
}

// Fetch the admin's username from the session
$username = $_SESSION['user_name'];

// Fetch all reservations from the database
$reservations = [];
$sql = "SELECT r.id, r.event_date, r.event_start_time, r.event_end_time, COUNT(bg.id) as guest_count, r.status, u.name as customer_name 
        FROM bookings r 
        JOIN users u ON r.user_id = u.id 
        LEFT JOIN guests bg ON r.id = bg.booking_id 
        GROUP BY r.id
        ORDER BY r.event_date DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $reservations[] = $row;
  }
}

// Add these queries at the top of the file after the existing reservations query
// Get total reservations count
$totalReservations = 0;
$sqlTotal = "SELECT COUNT(*) as total FROM bookings";
$resultTotal = $conn->query($sqlTotal);
if ($resultTotal && $row = $resultTotal->fetch_assoc()) {
    $totalReservations = $row['total'];
}

// Get upcoming events count
$upcomingEvents = 0;
$sqlUpcoming = "SELECT COUNT(*) as upcoming FROM bookings 
                WHERE event_date >= CURDATE() 
                AND status != 'cancelled'";
$resultUpcoming = $conn->query($sqlUpcoming);
if ($resultUpcoming && $row = $resultUpcoming->fetch_assoc()) {
    $upcomingEvents = $row['upcoming'];
}

// Get monthly revenue
$monthlyRevenue = 0;
$sqlRevenue = "SELECT COALESCE(SUM(total_amount), 0) as revenue 
               FROM bookings 
               WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
               AND YEAR(created_at) = YEAR(CURRENT_DATE())
               AND status != 'cancelled'";
$resultRevenue = $conn->query($sqlRevenue);
if ($resultRevenue && $row = $resultRevenue->fetch_assoc()) {
    $monthlyRevenue = $row['revenue'];
}

// Get reservation counts by month for the current year (for Monthly chart)
$monthly_data = array_fill(0, 12, 0); // Initialize with zeros for all 12 months
$monthly_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$sqlMonthly = "SELECT MONTH(event_date) as month, COUNT(*) as count 
               FROM bookings 
               WHERE YEAR(event_date) = YEAR(CURRENT_DATE())
               GROUP BY MONTH(event_date)";
$resultMonthly = $conn->query($sqlMonthly);
if ($resultMonthly) {
    while ($row = $resultMonthly->fetch_assoc()) {
        // Adjust for 0-based array (months are 1-12 in database)
        $monthly_data[$row['month']-1] = (int)$row['count'];
    }
}

// Get reservation counts by year (for Yearly chart)
$yearly_data = [];
$yearly_labels = [];
$sqlYearly = "SELECT YEAR(event_date) as year, COUNT(*) as count 
              FROM bookings 
              GROUP BY YEAR(event_date)
              ORDER BY year ASC";
$resultYearly = $conn->query($sqlYearly);
if ($resultYearly) {
    while ($row = $resultYearly->fetch_assoc()) {
        $yearly_labels[] = $row['year'];
        $yearly_data[] = (int)$row['count'];
    }
}

// Get event popularity for this month (for This Month chart)
$event_types_month = [];
$event_counts_month = [];
$sqlEventMonth = "SELECT LOWER(package_name) as package_lower, package_name, COUNT(*) as count 
                  FROM bookings 
                  WHERE MONTH(event_date) = MONTH(CURRENT_DATE()) 
                  AND YEAR(event_date) = YEAR(CURRENT_DATE())
                  GROUP BY LOWER(package_name), package_name 
                  ORDER BY count DESC";
$resultEventMonth = $conn->query($sqlEventMonth);
if ($resultEventMonth) {
    while ($row = $resultEventMonth->fetch_assoc()) {
        $event_types_month[] = ucwords(strtolower($row['package_name']));
        $event_counts_month[] = (int)$row['count'];
    }
}

// Get all-time event popularity (for All Time chart)
$event_types_all = [];
$event_counts_all = [];
$sqlEventAll = "SELECT LOWER(package_name) as package_lower, package_name, COUNT(*) as count 
                FROM bookings 
                GROUP BY LOWER(package_name), package_name
                ORDER BY count DESC";
$resultEventAll = $conn->query($sqlEventAll);
if ($resultEventAll) {
    while ($row = $resultEventAll->fetch_assoc()) {
        $event_types_all[] = ucwords(strtolower($row['package_name']));
        $event_counts_all[] = (int)$row['count'];
    }
}

// Check if there's no yearly data and create some placeholder
if (empty($yearly_labels)) {
    $current_year = date('Y');
    $yearly_labels = range($current_year-4, $current_year);
    $yearly_data = array_fill(0, 5, 0);
}

// Check if there's no event data and create some placeholders
if (empty($event_types_month)) {
    $event_types_month = ['Wedding', 'Birthday', 'Corporate', 'Anniversary', 'Seminar', 'Other'];
    $event_counts_month = [0, 0, 0, 0, 0, 0];
}
if (empty($event_types_all)) {
    $event_types_all = $event_types_month;
    $event_counts_all = [0, 0, 0, 0, 0, 0];
}

// Get event revenue data
$event_revenue = [];
$event_revenue_labels = [];
$sqlEventRevenue = "SELECT package_name, SUM(total_amount) as total_revenue 
                    FROM bookings 
                    WHERE status != 'cancelled'
                    GROUP BY package_name 
                    ORDER BY total_revenue DESC";
$resultEventRevenue = $conn->query($sqlEventRevenue);
if ($resultEventRevenue) {
    while ($row = $resultEventRevenue->fetch_assoc()) {
        $event_revenue_labels[] = ucwords(strtolower($row['package_name']));
        $event_revenue[] = (float)$row['total_revenue'];
    }
}

// If no data, add placeholder
if (empty($event_revenue_labels)) {
    $event_revenue_labels = ['Wedding', 'Birthday', 'Corporate', 'Anniversary', 'Seminar', 'Other'];
    $event_revenue = [0, 0, 0, 0, 0, 0];
}

// Get monthly revenue data for the current year
$month_revenue = array_fill(0, 12, 0);
$sqlMonthRevenue = "SELECT MONTH(created_at) as month, SUM(total_amount) as revenue 
                    FROM bookings 
                    WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
                    AND status != 'cancelled'
                    GROUP BY MONTH(created_at)
                    ORDER BY month ASC";
$resultMonthRevenue = $conn->query($sqlMonthRevenue);
if ($resultMonthRevenue) {
    while ($row = $resultMonthRevenue->fetch_assoc()) {
        // Adjust for 0-based array (months are 1-12 in database)
        $month_revenue[$row['month']-1] = (float)$row['revenue'];
    }
}

// Change the records per page from 5 to 3
$recordsPerPage = 3;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// Get total number of records for pagination
$totalRecordsQuery = "SELECT COUNT(*) as total FROM bookings";
$totalResult = $conn->query($totalRecordsQuery);
$totalRecords = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get average customer satisfaction rating
$customerSatisfaction = 0;
$sqlSatisfaction = "SELECT AVG(rating) as avg_rating FROM reviews";
$resultSatisfaction = $conn->query($sqlSatisfaction);
if ($resultSatisfaction && $row = $resultSatisfaction->fetch_assoc()) {
    $customerSatisfaction = round($row['avg_rating'], 1);
}
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>The Barn & Backyard | Admin Dashboard</title>
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
          // Remove animation definitions that cause flash effects
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
  
  <!-- Chart.js with Animations -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
  
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
      background: #475569;
    }
    
    /* Card Hover Effects */  
    .stat-card {
      /* Removing transition and hover effects for smoother dashboard */
      /* transition: all 0.3s ease; */
    }
    

    
    /* Chart Tooltip Styling */
    .chart-tooltip {
      background-color: rgba(255, 255, 255, 0.95);
      border-radius: 6px;
      padding: 10px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      border: 1px solid rgba(0, 0, 0, 0.05);
      font-family: 'Inter', sans-serif;
      font-size: 12px;
      color: #1e293b;
    }
    
    .dark .chart-tooltip {
      background-color: rgba(15, 23, 42, 0.95);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #e2e8f0;
    }
    
    /* Smooth transitions */
    .transition-smooth {
      /* Removing transition for smoother dashboard */
      /* transition: all 0.2s ease-in-out; */
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
    
    /* Scrollbar for dark mode */
    .dark ::-webkit-scrollbar-thumb {
      background: #30363d;
    }

    /* Status badges styling */
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: capitalize;
    }
    
    .status-pending {
        background-color: #fff3cd;
        color: #664d03;
    }
    
    .status-confirmed {
        background-color: #d1e7dd;
        color: #0f5132;
    }
    
    .status-cancelled {
        background-color: #f8d7da;
        color: #842029;
    }
    
    .status-completed {
        background-color: rgba(14, 159, 110, 0.1);
        color: #0e9f6e;
    }

    .dark .status-completed {
        background-color: rgba(14, 159, 110, 0.2);
        color: #34d399;
    }

    /* Add the status-paid class to the CSS */
    .status-paid {
        background-color: rgba(147, 51, 234, 0.1);
        color: #8b5cf6;
    }

    /* Add styling for partially_paid status */
    .status-partially_paid {
        background-color: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }
    
    .dark .status-partially_paid {
        background-color: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade-in {
      animation: fadeIn 0.3s ease-out forwards;
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
                <a href="#" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 transition-smooth">
                    <i class="fas fa-chart-line w-5 h-5"></i>
                    <span class="font-medium">Dashboard</span>
                </a>

                <!-- Theme -->
                <a href="theme.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth">
                    <i class="fas fa-palette w-5 h-5"></i>
                    <span class="font-medium">Manage Services</span>
                </a>

                <!-- Reservations -->
                <a href="reservations.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth">
                    <i class="far fa-calendar-check w-5 h-5"></i>
                    <span class="font-medium">Manage Reservations</span>
                </a>

                <!-- Events -->
                <a href="events.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth">
                    <i class="fas fa-glass-cheers w-5 h-5"></i>
                    <span class="font-medium">Manage Events</span>
                </a>

                <!-- Guests -->
                <a href="guest.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth">
                    <i class="fas fa-users w-5 h-5"></i>
                    <span class="font-medium">Manage Guests</span>
                </a>

                <!-- Customers -->
                <a href="customer.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth">
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
            <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200 dark:border-slate-700">
                <!-- Theme Toggle -->
                <button id="themeToggle" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth mb-4">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-moon w-5 h-5"></i>
                        <span class="font-medium">Dark Mode</span>
                    </div>
                    <div class="relative inline-block w-10 h-6 rounded-full bg-gray-200 dark:bg-slate-700" id="themeToggleIndicator">
                        <div class="absolute inset-y-0 left-0 w-6 h-6 transform translate-x-0 dark:translate-x-4 bg-white dark:bg-primary-400 rounded-full shadow-md"></div>
                    </div>
                </button>

                <!-- Logout -->
                <a href="../logout.php" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-smooth">
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
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Dashboard</h2>
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
                    <span class="text-gray-700 dark:text-gray-200 font-medium"><?php echo ucfirst(htmlspecialchars($username)); ?></span>
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
                    <h2 class="text-2xl font-bold text-white mb-2">Welcome back, <?php echo htmlspecialchars($username); ?>!</h2>
                    <p class="text-primary-100">Here's what's happening with your events today.</p>
                </div>
                <!-- Decorative Elements -->
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
                <div class="absolute bottom-0 left-0 -mb-4 -ml-4 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Total Reservations -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm stat-card p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 dark:text-gray-400 font-medium">Total Reservations</h3>
                    <span class="p-2 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg">
                        <i class="fas fa-calendar-check"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-gray-800 dark:text-white"><?php echo number_format($totalReservations); ?></p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                </p>
            </div>

            <!-- Upcoming Events -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm stat-card p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 dark:text-gray-400 font-medium">Upcoming Events</h3>
                    <span class="p-2 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-lg">
                        <i class="fas fa-glass-cheers"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-gray-800 dark:text-white"><?php echo number_format($upcomingEvents); ?></p>
            </div>

            <!-- Monthly Revenue -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm stat-card p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 dark:text-gray-400 font-medium">Monthly Revenue</h3>
                    <span class="p-2 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-lg">
                        <i class="fas fa-peso-sign"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-gray-800 dark:text-white">₱<?php echo number_format($monthlyRevenue, 2); ?></p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                </p>
            </div>

            <!-- Customer Satisfaction -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm stat-card p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 dark:text-gray-400 font-medium">Customer Satisfaction</h3>
                    <span class="p-2 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 rounded-lg">
                        <i class="fas fa-star"></i>
                    </span>
                </div>
                <div class="flex items-center space-x-2">
                    <p class="text-3xl font-bold text-gray-800 dark:text-white">
                        <?php echo $customerSatisfaction > 0 ? $customerSatisfaction : 'N/A'; ?>
                    </p>
                    <span class="text-yellow-400">
                        <i class="fas fa-star"></i>
                    </span>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                    Based on customer reviews
                </p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <a href="manual/index.php" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 rounded-xl shadow-sm p-6 text-white transition-smooth flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-lg mb-1">Create Manual Reservation</h3>
                    <p class="text-blue-100 text-sm">Process cash</p>
                </div>
                <div class="p-3 bg-white/20 rounded-full">
                    <i class="fas fa-hand-holding-usd text-2xl"></i>
                </div>
            </a>
            <a href="reservations.php" class="bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 border border-gray-200 dark:border-slate-700 rounded-xl shadow-sm p-6 flex items-center justify-between transition-smooth">
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-white text-lg mb-1">All Reservations</h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">View & manage all bookings</p>
                </div>
                <div class="p-3 bg-gray-100 dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 rounded-full">
                    <i class="fas fa-calendar-check text-2xl"></i>
                </div>
            </a>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Existing Reservations Chart -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-gray-700 dark:text-white font-semibold">Reservations Over Time</h3>
                    <div class="flex space-x-2">
                        <button data-chart="reservations" data-view="monthly" class="px-3 py-1 text-sm bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 rounded-md transition-smooth">Monthly</button>
                        <button data-chart="reservations" data-view="yearly" class="px-3 py-1 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md transition-smooth">Yearly</button>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="reservationsChart"></canvas>
                </div>
            </div>

            <!-- Existing Event Popularity Chart -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-gray-700 dark:text-white font-semibold">Event Popularity</h3>
                    <div class="flex space-x-2">
                        <button data-chart="popularity" data-view="this-month" class="px-3 py-1 text-sm bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 rounded-md transition-smooth">This Month</button>
                        <button data-chart="popularity" data-view="all-time" class="px-3 py-1 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md transition-smooth">All Time</button>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="eventPopularityChart"></canvas>
                </div>
            </div>

            <!-- New Event Revenue Chart -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-gray-700 dark:text-white font-semibold">Event Revenue Distribution</h3>
                </div>
                <div class="h-64">
                    <canvas id="eventRevenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Monthly Revenue Distribution Chart (Horizontal Bar) -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-gray-700 dark:text-white font-semibold">Monthly Revenue Distribution</h3>
            </div>
            <div class="h-64">
                <canvas id="monthlyRevenueChart"></canvas>
            </div>
        </div>

        <!-- Recent Reservations Section -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden mb-6">
            <div class="p-6 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center">
                <h4 class="text-lg font-semibold text-gray-700 dark:text-white">Recent Reservations</h4>
                <button id="viewAllReservationsBtn" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 text-sm font-medium transition-smooth">View All</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left bg-gray-50 dark:bg-slate-700/50">
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Event</th>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                        <?php
                        // Fetch recent reservations with pagination
                        $sql = "SELECT 
                            b.id,
                            b.package_name,
                            b.event_date,
                            b.event_start_time,
                            b.event_end_time,
                            b.total_amount,
                            u.name as customer_name,
                            b.payment_status,
                            b.status as booking_status
                            FROM bookings b 
                            LEFT JOIN users u ON b.user_id = u.id 
                            ORDER BY b.created_at DESC 
                            LIMIT " . $recordsPerPage . " OFFSET " . $offset;

                        $result = $conn->query($sql);

                        if ($result->num_rows > 0):
                            while($booking = $result->fetch_assoc()):
                                // Determine icon and color based on package name
                                $iconClass = 'fas fa-glass-cheers';
                                $bgColor = 'bg-indigo-100';
                                $textColor = 'text-indigo-500';
                                
                                if (stripos($booking['package_name'], 'corporate') !== false) {
                                    $iconClass = 'fas fa-briefcase';
                                    $bgColor = 'bg-blue-100';
                                    $textColor = 'text-blue-500';
                                } elseif (stripos($booking['package_name'], 'birthday') !== false) {
                                    $iconClass = 'fas fa-birthday-cake';
                                    $bgColor = 'bg-pink-100';
                                    $textColor = 'text-pink-500';
                                }
                        ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/25 transition-smooth">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0 <?php echo $bgColor . ' dark:bg-slate-700 ' . $textColor . ' dark:text-gray-300'; ?> rounded-lg flex items-center justify-center">
                                                <i class="<?php echo $iconClass; ?>"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-gray-800 dark:text-white font-medium">
                                                    <?php echo ucwords(strtolower(htmlspecialchars($booking['package_name']))); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="text-gray-800 dark:text-gray-200"><?php echo date('M d, Y', strtotime($booking['event_date'])); ?></span><br>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            <?php echo date('h:i A', strtotime($booking['event_start_time'])) . ' - ' . 
                                                 date('h:i A', strtotime($booking['event_end_time'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">₱<?php echo number_format($booking['total_amount'], 2, '.', ','); ?></td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                      <span class="status-badge 
                                        <?php 
                                        if ($booking['booking_status'] === 'cancelled') {
                                          echo 'status-cancelled';
                                        } elseif ($booking['booking_status'] === 'completed') {
                                          echo 'status-completed';
                                        } elseif ($booking['booking_status'] === 'confirmed' && $booking['payment_status'] === 'partially_paid') {
                                          echo 'status-partially_paid';
                                        } elseif ($booking['booking_status'] === 'confirmed' && $booking['payment_status'] !== 'paid') {
                                          echo 'status-confirmed';
                                        } elseif (($booking['booking_status'] === 'confirmed' || $booking['booking_status'] === 'pending') && $booking['payment_status'] === 'paid') {
                                          echo 'status-paid';
                                        } else {
                                          echo 'status-pending';
                                        }
                                        ?>">
                                        <?php 
                                        if ($booking['booking_status'] === 'cancelled') {
                                          echo '<i class="fas fa-ban mr-1"></i> Cancelled';
                                        } elseif ($booking['booking_status'] === 'completed') {
                                          echo '<i class="fas fa-flag-checkered mr-1" style="color: #0e9f6e;"></i> Completed';
                                        } elseif ($booking['booking_status'] === 'confirmed' && $booking['payment_status'] === 'partially_paid') {
                                          echo '<i class="fas fa-percentage mr-1" style="color: #3b82f6;"></i> Partially Paid';
                                        } elseif ($booking['booking_status'] === 'confirmed' && $booking['payment_status'] !== 'paid') {
                                          echo '<i class="fas fa-check-circle mr-1"></i> Confirmed';
                                        } elseif (($booking['booking_status'] === 'confirmed' || $booking['booking_status'] === 'pending') && $booking['payment_status'] === 'paid') {
                                          echo '<i class="fas fa-credit-card mr-1"></i> Paid';
                                        } else {
                                          echo '<i class="fas fa-clock mr-1"></i> Pending';
                                        }
                                        ?>
                                      </span>
                                    </td>
                                </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">No recent reservations found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Pagination controls -->
                <div class="px-4 py-3 bg-white dark:bg-slate-800 border-t border-gray-200 dark:border-slate-700">
                    <div class="flex flex-col sm:flex-row items-center justify-between">
                        <div class="text-sm text-gray-700 dark:text-gray-200 mb-4 sm:mb-0">
                            Showing <?php echo min($totalRecords, $offset + 1); ?> to <?php echo min($totalRecords, $offset + $recordsPerPage); ?> of <?php echo $totalRecords; ?> entries
                        </div>
                        
                        <!-- Pagination buttons -->
                        <?php if($totalPages > 1): ?>
                        <div class="flex space-x-1">
                            <?php if($page > 1): ?>
                                <a href="?page=<?php echo ($page - 1); ?>" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-md hover:bg-gray-50 dark:hover:bg-slate-600 transition-smooth">
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                            // Determine range of page numbers to show
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            // Show first page if not in range
                            if ($startPage > 1) {
                                echo '<a href="?page=1" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-md hover:bg-gray-50 dark:hover:bg-slate-600 transition-smooth">1</a>';
                                if ($startPage > 2) {
                                    echo '<span class="px-4 py-2 text-gray-500 dark:text-gray-400">...</span>';
                                }
                            }
                            
                            // Show page numbers
                            for($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" 
                                   class="inline-flex items-center px-4 py-2 text-sm font-medium <?php echo ($page == $i) ? 'bg-indigo-600 text-white' : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600'; ?> border border-gray-300 dark:border-slate-600 rounded-md transition-smooth">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; 
                            
                            // Show last page if not in range
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<span class="px-4 py-2 text-gray-500 dark:text-gray-400">...</span>';
                                }
                                echo '<a href="?page=' . $totalPages . '" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-md hover:bg-gray-50 dark:hover:bg-slate-600 transition-smooth">' . $totalPages . '</a>';
                            }
                            ?>
                            
                            <?php if($page < $totalPages): ?>
                                <a href="?page=<?php echo ($page + 1); ?>" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-md hover:bg-gray-50 dark:hover:bg-slate-600 transition-smooth">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Reservations Modal -->
  <div id="reservationsModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden backdrop-blur-sm">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto mx-4 my-8">
      <div class="p-6 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center sticky top-0 bg-gradient-to-r from-indigo-600 to-blue-500 z-10">
        <h3 class="text-xl font-bold text-white">All Reservations</h3>
        <button id="closeReservationsModal" class="text-white hover:text-gray-200">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="p-6">
        <table class="w-full">
          <thead>
            <tr class="text-left bg-gray-50 dark:bg-slate-700/50">
              <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
              <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Event Date</th>
              <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Time</th>
              <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Guests</th>
              <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
            <?php if(count($reservations) > 0): ?>
              <?php foreach($reservations as $reservation): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/25 transition-smooth">
                  <td class="px-6 py-4 text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($reservation['customer_name']); ?></td>
                  <td class="px-6 py-4 text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($reservation['event_date']); ?></td>
                  <td class="px-6 py-4 text-gray-800 dark:text-gray-200">
                    <?php 
                      echo date('h:i A', strtotime($reservation['event_start_time'])) . ' - ' . 
                           date('h:i A', strtotime($reservation['event_end_time'])); 
                    ?>
                  </td>
                  <td class="px-6 py-4 text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($reservation['guest_count']); ?></td>
                  <td class="whitespace-nowrap px-6 py-4">
                    <span class="status-badge 
                      <?php 
                      if ($reservation['status'] === 'cancelled') {
                        echo 'status-cancelled';
                      } elseif ($reservation['status'] === 'completed') {
                        echo 'status-completed';
                      } elseif ($reservation['status'] === 'confirmed' && isset($reservation['payment_status']) && $reservation['payment_status'] !== 'paid') {
                        echo 'status-confirmed';
                      } elseif (($reservation['status'] === 'confirmed' || $reservation['status'] === 'pending') && isset($reservation['payment_status']) && $reservation['payment_status'] === 'paid') {
                        echo 'status-paid';
                      } elseif ($reservation['status'] === 'confirmed' && isset($reservation['payment_status']) && $reservation['payment_status'] === 'partially_paid') {
                        echo 'status-partially_paid';
                      } else {
                        echo 'status-pending';
                      }
                      ?>">
                      <?php 
                      if ($reservation['status'] === 'cancelled') {
                        echo '<i class="fas fa-ban mr-1"></i> Cancelled';
                      } elseif ($reservation['status'] === 'completed') {
                        echo '<i class="fas fa-flag-checkered mr-1" style="color: #0e9f6e;"></i> Completed';
                      } elseif ($reservation['status'] === 'confirmed' && isset($reservation['payment_status']) && $reservation['payment_status'] !== 'paid') {
                        echo '<i class="fas fa-check-circle mr-1"></i> Confirmed';
                      } elseif (($reservation['status'] === 'confirmed' || $reservation['status'] === 'pending') && isset($reservation['payment_status']) && $reservation['payment_status'] === 'paid') {
                        echo '<i class="fas fa-credit-card mr-1"></i> Paid';
                      } elseif ($reservation['status'] === 'confirmed' && isset($reservation['payment_status']) && $reservation['payment_status'] === 'partially_paid') {
                        echo '<i class="fas fa-percentage mr-1" style="color: #3b82f6;"></i> Partially Paid';
                      } else {
                        echo '<i class="fas fa-clock mr-1"></i> Pending';
                      }
                      ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No reservations found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Chart.js Script with Toggle Functionality -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // ---- RESERVATIONS CHART ----
      const reservationsCtx = document.getElementById('reservationsChart').getContext('2d');
      
      // Define datasets for monthly and yearly views using PHP data
      const monthlyData = {
        labels: <?php echo json_encode($monthly_labels); ?>,
        datasets: [{
          label: 'Reservations',
          data: <?php echo json_encode($monthly_data); ?>,
          borderColor: '#6366F1',
          backgroundColor: 'rgba(99, 102, 241, 0.1)',
          tension: 0.3,
          fill: true,
          pointBackgroundColor: '#6366F1',
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      };
      
      const yearlyData = {
        labels: <?php echo json_encode($yearly_labels); ?>,
        datasets: [{
          label: 'Reservations',
          data: <?php echo json_encode($yearly_data); ?>,
          borderColor: '#8B5CF6',
          backgroundColor: 'rgba(139, 92, 246, 0.1)',
          tension: 0.3,
          fill: true,
          pointBackgroundColor: '#8B5CF6',
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      };
      
      // Create the initial reservations chart with monthly data
      const reservationsChart = new Chart(reservationsCtx, {
        type: 'line',
        data: monthlyData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: {
            duration: 1000, // Restore animation with 1 second duration
            easing: 'easeOutQuart'
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: 'rgba(255, 255, 255, 0.95)',
              titleColor: '#1e293b',
              bodyColor: '#1e293b',
              borderColor: 'rgba(0, 0, 0, 0.1)',
              borderWidth: 1,
              padding: 10,
              cornerRadius: 6,
              displayColors: false,
              callbacks: {
                title: function(tooltipItems) {
                  return tooltipItems[0].label;
                },
                label: function(context) {
                  return `Reservations: ${context.parsed.y}`;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)'
              },
              ticks: {
                precision: 0
              }
            },
            x: {
              grid: {
                display: false
              }
            }
          }
        }
      });
      
      // Get the toggle buttons for reservations chart
      const monthlyBtn = document.querySelector('[data-chart="reservations"][data-view="monthly"]');
      const yearlyBtn = document.querySelector('[data-chart="reservations"][data-view="yearly"]');
      
      // Add click events for reservation chart toggle
      monthlyBtn.addEventListener('click', function() {
        reservationsChart.data = monthlyData;
        reservationsChart.update();
        
        // Toggle active button styling
        monthlyBtn.classList.add('bg-indigo-100', 'text-indigo-600', 'dark:bg-indigo-900/30', 'dark:text-indigo-400');
        monthlyBtn.classList.remove('text-gray-500', 'dark:text-gray-400', 'hover:bg-gray-100', 'dark:hover:bg-slate-700');
        yearlyBtn.classList.remove('bg-indigo-100', 'text-indigo-600', 'dark:bg-indigo-900/30', 'dark:text-indigo-400');
        yearlyBtn.classList.add('text-gray-500', 'dark:text-gray-400', 'hover:bg-gray-100', 'dark:hover:bg-slate-700');
      });
      
      yearlyBtn.addEventListener('click', function() {
        reservationsChart.data = yearlyData;
        reservationsChart.update();
        
        // Toggle active button styling
        yearlyBtn.classList.add('bg-indigo-100', 'text-indigo-600', 'dark:bg-indigo-900/30', 'dark:text-indigo-400');
        yearlyBtn.classList.remove('text-gray-500', 'dark:text-gray-400', 'hover:bg-gray-100', 'dark:hover:bg-slate-700');
        monthlyBtn.classList.remove('bg-indigo-100', 'text-indigo-600', 'dark:bg-indigo-900/30', 'dark:text-indigo-400');
        monthlyBtn.classList.add('text-gray-500', 'dark:text-gray-400', 'hover:bg-gray-100', 'dark:hover:bg-slate-700');
      });
      
      // ---- EVENT POPULARITY CHART ----
      const popularityCtx = document.getElementById('eventPopularityChart').getContext('2d');
      
      const thisMonthData = {
        labels: <?php echo json_encode($event_types_month); ?>,
        datasets: [{
          label: 'Bookings This Month',
          data: <?php echo json_encode($event_counts_month); ?>,
          backgroundColor: [
            'rgba(255, 99, 132, 0.8)',   // Red
            'rgba(54, 162, 235, 0.8)',   // Blue
            'rgba(255, 206, 86, 0.8)',   // Yellow
            'rgba(75, 192, 192, 0.8)',   // Teal
            'rgba(153, 102, 255, 0.8)',  // Purple
            'rgba(255, 159, 64, 0.8)',   // Orange
            'rgba(233, 30, 99, 0.8)',    // Pink
            'rgba(156, 39, 176, 0.8)',   // Deep Purple
            'rgba(3, 169, 244, 0.8)',    // Light Blue
            'rgba(0, 150, 136, 0.8)'     // Green
          ],
          borderRadius: 6
        }]
      };
      
      const allTimeData = {
        labels: <?php echo json_encode($event_types_all); ?>,
        datasets: [{
          label: 'All Time Bookings',
          data: <?php echo json_encode($event_counts_all); ?>,
          backgroundColor: [
            'rgba(255, 99, 132, 0.8)',   // Red
            'rgba(54, 162, 235, 0.8)',   // Blue
            'rgba(255, 206, 86, 0.8)',   // Yellow
            'rgba(75, 192, 192, 0.8)',   // Teal
            'rgba(153, 102, 255, 0.8)',  // Purple
            'rgba(255, 159, 64, 0.8)',   // Orange
            'rgba(233, 30, 99, 0.8)',    // Pink
            'rgba(156, 39, 176, 0.8)',   // Deep Purple
            'rgba(3, 169, 244, 0.8)',    // Light Blue
            'rgba(0, 150, 136, 0.8)'     // Green
          ],
          borderRadius: 6
        }]
      };
      
      const popularityChart = new Chart(popularityCtx, {
        type: 'bar',
        data: thisMonthData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: {
            duration: 1000, // Restore animation with 1 second duration
            easing: 'easeOutQuart'
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: 'rgba(255, 255, 255, 0.95)',
              titleColor: '#1e293b',
              bodyColor: '#1e293b',
              borderColor: 'rgba(0, 0, 0, 0.1)',
              borderWidth: 1,
              padding: 10,
              cornerRadius: 6,
              displayColors: true,
              callbacks: {
                label: function(context) {
                  return `Bookings: ${context.raw}`;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                display: false
              },
              ticks: {
                precision: 0,
                stepSize: 1
              }
            },
            x: {
              grid: {
                display: false
              }
            }
          }
        }
      });
      
      // Get the toggle buttons for the popularity chart
      const thisMonthBtn = document.querySelector('[data-chart="popularity"][data-view="this-month"]');
      const allTimeBtn = document.querySelector('[data-chart="popularity"][data-view="all-time"]');
      
      // Add click events for popularity chart toggle
      thisMonthBtn.addEventListener('click', function() {
        popularityChart.data = thisMonthData;
        popularityChart.update();
        
        // Toggle active button styling
        thisMonthBtn.classList.add('bg-indigo-100', 'text-indigo-600', 'dark:bg-indigo-900/30', 'dark:text-indigo-400');
        thisMonthBtn.classList.remove('text-gray-500', 'dark:text-gray-400', 'hover:bg-gray-100', 'dark:hover:bg-slate-700');
        allTimeBtn.classList.remove('bg-indigo-100', 'text-indigo-600', 'dark:bg-indigo-900/30', 'dark:text-indigo-400');
        allTimeBtn.classList.add('text-gray-500', 'dark:text-gray-400', 'hover:bg-gray-100', 'dark:hover:bg-slate-700');
      });
      
      allTimeBtn.addEventListener('click', function() {
        popularityChart.data = allTimeData;
        popularityChart.update();
        
        // Toggle active button styling
        allTimeBtn.classList.add('bg-indigo-100', 'text-indigo-600', 'dark:bg-indigo-900/30', 'dark:text-indigo-400');
        allTimeBtn.classList.remove('text-gray-500', 'dark:text-gray-400', 'hover:bg-gray-100', 'dark:hover:bg-slate-700');
        thisMonthBtn.classList.remove('bg-indigo-100', 'text-indigo-600', 'dark:bg-indigo-900/30', 'dark:text-indigo-400');
        thisMonthBtn.classList.add('text-gray-500', 'dark:text-gray-400', 'hover:bg-gray-100', 'dark:hover:bg-slate-700');
      });

      // ---- EVENT REVENUE CHART ----
      const revenueCtx = document.getElementById('eventRevenueChart').getContext('2d');
      const revenueData = {
        labels: <?php echo json_encode($event_revenue_labels); ?>,
        datasets: [{
          data: <?php echo json_encode($event_revenue); ?>,
          backgroundColor: [
            'rgba(255, 99, 132, 0.8)',   // Red
            'rgba(54, 162, 235, 0.8)',   // Blue
            'rgba(255, 206, 86, 0.8)',   // Yellow
            'rgba(75, 192, 192, 0.8)',   // Teal
            'rgba(153, 102, 255, 0.8)',  // Purple
            'rgba(255, 159, 64, 0.8)',   // Orange
            'rgba(233, 30, 99, 0.8)',    // Pink
            'rgba(156, 39, 176, 0.8)',   // Deep Purple
            'rgba(3, 169, 244, 0.8)',    // Light Blue
            'rgba(0, 150, 136, 0.8)'     // Green
          ],
          borderWidth: 1
        }]
      };

      const revenueChart = new Chart(revenueCtx, {
        type: 'pie',
        data: revenueData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: {
            duration: 1000, // Restore animation with 1 second duration
            easing: 'easeOutQuart'
          },
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                padding: 20,
                usePointStyle: true,
                pointStyle: 'circle'
              }
            },
            tooltip: {
              backgroundColor: 'rgba(255, 255, 255, 0.95)',
              titleColor: '#1e293b',
              bodyColor: '#1e293b',
              borderColor: 'rgba(0, 0, 0, 0.1)',
              borderWidth: 1,
              padding: 10,
              cornerRadius: 6,
              displayColors: true,
              callbacks: {
                label: function(context) {
                  const value = context.raw;
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = ((value / total) * 100).toFixed(1);
                  return `₱${value.toLocaleString()} (${percentage}%)`;
                }
              }
            }
          }
        }
      });

      // ---- MONTHLY REVENUE DISTRIBUTION CHART (HORIZONTAL BAR) ----
      const monthlyRevenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
      const monthlyRevenueData = {
        labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        datasets: [{
          label: 'Revenue (₱)',
          data: <?php echo json_encode($month_revenue); ?>,
          backgroundColor: [
            'rgba(53, 162, 235, 0.8)',   // January - Blue
            'rgba(255, 99, 132, 0.8)',   // February - Red
            'rgba(75, 192, 192, 0.8)',   // March - Teal
            'rgba(255, 206, 86, 0.8)',   // April - Yellow
            'rgba(153, 102, 255, 0.8)',  // May - Purple
            'rgba(255, 159, 64, 0.8)',   // June - Orange
            'rgba(233, 30, 99, 0.8)',    // July - Pink
            'rgba(156, 39, 176, 0.8)',   // August - Deep Purple
            'rgba(3, 169, 244, 0.8)',    // September - Light Blue
            'rgba(0, 150, 136, 0.8)',    // October - Green
            'rgba(121, 85, 72, 0.8)',    // November - Brown
            'rgba(33, 150, 243, 0.8)'    // December - Blue
          ],
          borderWidth: 1,
          borderRadius: 6
        }]
      };

      const monthlyRevenueChart = new Chart(monthlyRevenueCtx, {
        type: 'bar',
        data: monthlyRevenueData,
        options: {
          indexAxis: 'y', // This makes the bar chart horizontal
          responsive: true,
          maintainAspectRatio: false,
          animation: {
            duration: 1000, // Restore animation with 1 second duration
            easing: 'easeOutQuart'
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: 'rgba(255, 255, 255, 0.95)',
              titleColor: '#1e293b',
              bodyColor: '#1e293b',
              borderColor: 'rgba(0, 0, 0, 0.1)',
              borderWidth: 1,
              padding: 10,
              cornerRadius: 6,
              displayColors: true,
              callbacks: {
                label: function(context) {
                  const value = context.raw;
                  return `Revenue: ₱${value.toLocaleString()}`;
                }
              }
            }
          },
          scales: {
            x: {
              beginAtZero: true,
              grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)'
              },
              ticks: {
                callback: function(value) {
                  return '₱' + value.toLocaleString();
                }
              }
            },
            y: {
              grid: {
                display: false
              }
            }
          }
        }
      });

      // Mobile menu toggle
      document.querySelector('button.md\\:hidden').addEventListener('click', function() {
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

      // Reservations modal functionality
      const reservationsModal = document.getElementById('reservationsModal');
      const viewAllReservationsBtn = document.getElementById('viewAllReservationsBtn');
      const closeReservationsModal = document.getElementById('closeReservationsModal');

      viewAllReservationsBtn.addEventListener('click', function() {
        reservationsModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      });

      closeReservationsModal.addEventListener('click', function() {
        reservationsModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
      });

      // Notification popup functionality
      const notificationBtn = document.getElementById('notificationBtn');
      const notificationPopup = document.getElementById('notificationPopup');
      let isPopupVisible = false;

      notificationBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          isPopupVisible = !isPopupVisible;
          notificationPopup.classList.toggle('hidden');
          
          if (isPopupVisible) {
            // Remove animation
            // notificationPopup.classList.add('animate-fade-in');
          }
      });

      // Close popup when clicking outside
      document.addEventListener('click', function(e) {
          if (isPopupVisible && !notificationPopup.contains(e.target)) {
              notificationPopup.classList.add('hidden');
              isPopupVisible = false;
          }
      });

      // Theme Toggle
      const themeToggle = document.getElementById('themeToggle');
      const html = document.documentElement;
      
      // Notification Mark All Read functionality
      const markAllReadBtn = document.getElementById('markAllReadBtn');
      const notificationDot = document.getElementById('notificationDot');
      
      if (markAllReadBtn) {
          markAllReadBtn.addEventListener('click', function(e) {
              e.stopPropagation();
              
              // Send AJAX request to mark notifications as read
              fetch('admindash.php', {
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
      
      // Check for saved theme preference, otherwise use system preference
      if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          html.classList.add('dark');
      } else {
          html.classList.remove('dark');
      }
      
      themeToggle.addEventListener('click', function() {
          html.classList.toggle('dark');
          localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
          updateChartsTheme();
      });

      // Sidebar Collapse
      const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('mainContent');
      let isSidebarCollapsed = false;

      sidebarCollapseBtn.addEventListener('click', function() {
          isSidebarCollapsed = !isSidebarCollapsed;
          
          // Immediate changes without animations
          sidebar.style.width = isSidebarCollapsed ? '5rem' : '16rem';
          mainContent.style.marginLeft = isSidebarCollapsed ? '5rem' : '16rem';
          
          // Hide text in sidebar when collapsed
          const sidebarTexts = sidebar.querySelectorAll('span:not(.sr-only)');
          sidebarTexts.forEach(text => {
              text.style.display = isSidebarCollapsed ? 'none' : 'inline';
          });
          
          // Hide/show theme toggle indicator
          const themeToggleIndicator = document.getElementById('themeToggleIndicator');
          themeToggleIndicator.style.display = isSidebarCollapsed ? 'none' : 'block';
      });

      // Chart.js Dark Mode Support
      function updateChartsTheme() {
          const isDark = html.classList.contains('dark');
          const chartDefaults = {
              color: isDark ? '#ffffff' : '#1e293b',
              borderColor: isDark ? '#475569' : '#e2e8f0',
              grid: {
                  color: isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)'
              }
          };

          Chart.defaults.color = chartDefaults.color;
          Chart.defaults.borderColor = chartDefaults.borderColor;
          
          // Update existing charts
          Chart.instances.forEach(chart => {
              // Update grid colors
              chart.options.scales.y.grid.color = chartDefaults.grid.color;
              chart.options.scales.x.grid.color = chartDefaults.grid.color;
              
              // Update text colors
              chart.options.scales.y.ticks.color = chartDefaults.color;
              chart.options.scales.x.ticks.color = chartDefaults.color;
              
              // Update tooltip colors
              chart.options.plugins.tooltip = {
                  backgroundColor: isDark ? 'rgba(30, 41, 59, 0.8)' : 'rgba(255, 255, 255, 0.8)',
                  titleColor: isDark ? '#ffffff' : '#1e293b',
                  bodyColor: isDark ? '#ffffff' : '#1e293b',
                  borderColor: isDark ? '#475569' : '#e2e8f0',
                  borderWidth: 1,
                  padding: 10,
                  cornerRadius: 6,
                  displayColors: true,
                  callbacks: chart.options.plugins.tooltip.callbacks
              };
              
              // Update background colors for bar chart
              if (chart.config.type === 'bar') {
                  chart.data.datasets[0].backgroundColor = isDark ? [
                      'rgba(129, 140, 248, 0.8)',
                      'rgba(139, 92, 246, 0.8)',
                      'rgba(168, 85, 247, 0.8)',
                      'rgba(217, 70, 239, 0.8)',
                      'rgba(236, 72, 153, 0.8)',
                      'rgba(244, 63, 94, 0.8)'
                  ] : [
                      'rgba(99, 102, 241, 0.8)',
                      'rgba(79, 70, 229, 0.8)',
                      'rgba(139, 92, 246, 0.8)',
                      'rgba(168, 85, 247, 0.8)',
                      'rgba(217, 70, 239, 0.8)',
                      'rgba(236, 72, 153, 0.8)'
                  ];
              }
              
              // Update line colors and fill for line chart
              if (chart.config.type === 'line') {
                  chart.data.datasets[0].borderColor = isDark ? '#818cf8' : '#6366F1';
                  chart.data.datasets[0].backgroundColor = isDark ? 'rgba(129, 140, 248, 0.1)' : 'rgba(99, 102, 241, 0.1)';
                  chart.data.datasets[0].pointBackgroundColor = isDark ? '#818cf8' : '#6366F1';
              }
              
              chart.update();
          });

          // Update revenue chart theme
          if (revenueChart) {
              revenueChart.options.plugins.tooltip = {
                  backgroundColor: isDark ? 'rgba(30, 41, 59, 0.8)' : 'rgba(255, 255, 255, 0.8)',
                  titleColor: isDark ? '#ffffff' : '#1e293b',
                  bodyColor: isDark ? '#ffffff' : '#1e293b',
                  borderColor: isDark ? '#475569' : '#e2e8f0',
                  borderWidth: 1,
                  padding: 10,
                  cornerRadius: 6,
                  displayColors: true,
                  callbacks: revenueChart.options.plugins.tooltip.callbacks
              };
              
              revenueChart.options.plugins.legend.labels.color = isDark ? '#ffffff' : '#1e293b';
              revenueChart.update();
          }

          // Update monthly revenue chart theme
          if (monthlyRevenueChart) {
              // Update tooltip colors
              monthlyRevenueChart.options.plugins.tooltip = {
                  backgroundColor: isDark ? 'rgba(30, 41, 59, 0.8)' : 'rgba(255, 255, 255, 0.8)',
                  titleColor: isDark ? '#ffffff' : '#1e293b',
                  bodyColor: isDark ? '#ffffff' : '#1e293b',
                  borderColor: isDark ? '#475569' : '#e2e8f0',
                  borderWidth: 1,
                  padding: 10,
                  cornerRadius: 6,
                  displayColors: true,
                  callbacks: monthlyRevenueChart.options.plugins.tooltip.callbacks
              };
              
              // Update colors for dark mode
              monthlyRevenueChart.options.scales.x.grid.color = chartDefaults.grid.color;
              monthlyRevenueChart.options.scales.x.ticks.color = chartDefaults.color;
              monthlyRevenueChart.options.scales.y.ticks.color = chartDefaults.color;
              
              // More vibrant colors for dark mode
              if (isDark) {
                  monthlyRevenueChart.data.datasets[0].backgroundColor = [
                      'rgba(129, 140, 248, 0.8)',  // January
                      'rgba(244, 114, 182, 0.8)',  // February
                      'rgba(45, 212, 191, 0.8)',   // March
                      'rgba(250, 204, 21, 0.8)',   // April
                      'rgba(168, 85, 247, 0.8)',   // May
                      'rgba(251, 146, 60, 0.8)',   // June
                      'rgba(236, 72, 153, 0.8)',   // July
                      'rgba(192, 132, 252, 0.8)',  // August
                      'rgba(14, 165, 233, 0.8)',   // September
                      'rgba(34, 197, 94, 0.8)',    // October
                      'rgba(180, 83, 9, 0.8)',     // November
                      'rgba(59, 130, 246, 0.8)'    // December
                  ];
              }
              
              monthlyRevenueChart.update();
          }
      }

      // Initialize chart themes
      updateChartsTheme();
      
      // Remove the following animation code for stat cards
      /* 
      // Add smooth animations to stats cards on page load
      const statCards = document.querySelectorAll('.stat-card');
      statCards.forEach((card, index) => {
          setTimeout(() => {
              card.classList.add('animate-fade-in');
          }, 100 * index);
      });
      */
    });
  </script>
</body>
</html>