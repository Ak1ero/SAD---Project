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

// Fetch all reservations from the database with guest counts (excluding cancelled)
$reservations = [];
$sql = "SELECT 
        b.id, 
        b.package_name,
        b.event_date, 
        b.event_start_time, 
        b.event_end_time, 
        b.status, 
        b.payment_status,
        u.name as customer_name,
        u.id as user_id, 
        COUNT(bg.id) as guest_count,
        (SELECT COUNT(*) FROM guest_attendance ga WHERE ga.booking_id = b.id) as checked_in_count
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        LEFT JOIN guests bg ON b.id = bg.booking_id 
        WHERE b.status != 'cancelled' 
        AND (b.payment_status = 'paid' OR b.payment_status = 'partially_paid')
        GROUP BY b.id
        ORDER BY b.event_date DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $reservations[] = $row;
  }
}

// Pagination
$recordsPerPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// Get total number of records for pagination (excluding cancelled)
$totalRecordsQuery = "SELECT COUNT(DISTINCT b.id) as total 
                    FROM bookings b 
                    JOIN users u ON b.user_id = u.id 
                    WHERE b.status != 'cancelled' 
                    AND (b.payment_status = 'paid' OR b.payment_status = 'partially_paid')";
$totalResult = $conn->query($totalRecordsQuery);
$totalRecords = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Fetch paginated reservations (excluding cancelled)
$paginatedSql = "SELECT 
                b.id, 
                b.package_name,
                b.event_date, 
                b.event_start_time, 
                b.event_end_time, 
                b.status, 
                b.payment_status,
                u.name as customer_name,
                u.id as user_id, 
                COUNT(bg.id) as guest_count,
                (SELECT COUNT(*) FROM guest_attendance ga WHERE ga.booking_id = b.id) as checked_in_count
                FROM bookings b 
                JOIN users u ON b.user_id = u.id 
                LEFT JOIN guests bg ON b.id = bg.booking_id 
                WHERE b.status != 'cancelled' 
                AND (b.payment_status = 'paid' OR b.payment_status = 'partially_paid')
                GROUP BY b.id
                ORDER BY b.event_date DESC
                LIMIT $recordsPerPage OFFSET $offset";
$paginatedResult = $conn->query($paginatedSql);
$paginatedReservations = [];

if ($paginatedResult && $paginatedResult->num_rows > 0) {
  while($row = $paginatedResult->fetch_assoc()) {
    $paginatedReservations[] = $row;
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>The Barn & Backyard | Guest Management</title>
  <link rel="icon" href="../img/barn-backyard.svg" type="image/svg+xml"/>
  <script>
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  </script>
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
                          700: '#334155',
                          800: '#1e293b',
                          900: '#0f172a'
                      }
                  },
                  backgroundColor: {
                      'github-dark': '#0d1117',
                      'github-dark-secondary': '#161b22',
                      'github-dark-tertiary': '#21262d'
                  },
                  borderColor: {
                      'github-dark-border': '#30363d'
                  },
                  textColor: {
                      'github-dark-text': '#c9d1d9',
                      'github-dark-text-secondary': '#8b949e'
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
          background: #30363d !important;
      }
      
      /* Card Hover Effects - Disabled for smoother page load */
      .stat-card {
          /* transition: all 0.3s ease; */
      }
      
      .stat-card:hover {
          /* transform: translateY(-5px); */
          /* box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); */
      }
      
      .dark .stat-card:hover {
          /* box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); */
      }
      
      /* Dark Mode Styles for Toggle Circle - Updated to match theme.php */
      .dark #themeToggleIndicator {
          background-color: #1e293b !important;
      }
      
      .dark #themeToggleIndicator div {
          background-color: #60a5fa !important;
          box-shadow: 0 0 5px rgba(96, 165, 250, 0.5) !important;
      }
      
      /* Removed transition for smoother page load */
      .transition-smooth {
          /* transition: all 0.3s ease-in-out !important; */
      }
      
      /* Dark Mode Styles */
      .dark {
          color-scheme: dark;
      }
      
      .dark body {
          background-color: #0d1117 !important;
          color: #c9d1d9 !important;
      }
      
      .dark .bg-white {
          background-color: #161b22 !important;
      }
      
      .dark .bg-gray-50 {
          background-color: #0d1117 !important;
      }
      
      .dark .dark\:bg-slate-900 {
          background-color: #0d1117 !important;
      }
      
      .dark .dark\:bg-slate-800 {
          background-color: #161b22 !important;
      }
      
      .dark .dark\:bg-slate-700 {
          background-color: #21262d !important;
      }
      
      .dark .dark\:border-slate-700 {
          border-color: #30363d !important;
      }
      
      .dark .dark\:border-slate-600 {
          border-color: #30363d !important;
      }
      
      .dark .dark\:text-white {
          color: #c9d1d9 !important;
      }
      
      .dark .dark\:text-gray-300 {
          color: #c9d1d9 !important;
      }
      
      .dark .dark\:text-gray-400 {
          color: #8b949e !important;
      }
      
      .dark .dark\:text-gray-500 {
          color: #6e7681 !important;
      }
      
      .dark .dark\:hover\:bg-slate-700\/50:hover {
          background-color: rgba(33, 38, 45, 0.5) !important;
      }
      
      /* Status badges styling - add if not existing, or update if it exists */
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
      
      .status-paid {
          background-color: rgba(147, 51, 234, 0.1);
          color: #8b5cf6;
      }
      
      .status-partially_paid {
          background-color: rgba(59, 130, 246, 0.1);
          color: #3b82f6;
      }
      
      .dark .status-partially_paid {
          background-color: rgba(59, 130, 246, 0.2);
          color: #60a5fa;
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
        <span class="text-lg font-semibold text-gray-800 dark:text-white sidebar-text">B&B Admin</span>
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
            <span class="font-medium sidebar-text">Dashboard</span>
          </a>

          <!-- Theme -->
          <a href="theme.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
            <i class="fas fa-palette w-5 h-5"></i>
            <span class="font-medium sidebar-text">Manage Services</span>
          </a>

          <!-- Reservations -->
          <a href="reservations.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
            <i class="far fa-calendar-check w-5 h-5"></i>
            <span class="font-medium sidebar-text">Manage Reservations</span>
          </a>

          <!-- Events -->
          <a href="events.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
            <i class="fas fa-glass-cheers w-5 h-5"></i>
            <span class="font-medium sidebar-text">Manage Events</span>
          </a>

          <!-- Guests -->
          <a href="guest.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400">
            <i class="fas fa-users w-5 h-5"></i>
            <span class="font-medium sidebar-text">Manage Guests</span>
          </a>

          <!-- Customers -->
          <a href="customer.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
            <i class="fas fa-user-circle w-5 h-5"></i>
            <span class="font-medium sidebar-text">Manage Customers</span>
          </a>

          <!-- Settings -->
          <a href="settings.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
            <i class="fas fa-cog w-5 h-5"></i>
            <span class="font-medium sidebar-text">Settings</span>
          </a>
        </div>

        <!-- Bottom Section -->
        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200 dark:border-slate-700">
          <!-- Theme Toggle -->
          <button id="themeToggle" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 mb-4">
            <div class="flex items-center space-x-3">
              <i class="fas fa-moon w-5 h-5"></i>
              <span class="font-medium sidebar-text">Dark Mode</span>
            </div>
            <div class="relative inline-block w-10 h-6 rounded-full bg-gray-200 dark:bg-slate-700" id="themeToggleIndicator">
              <div class="absolute inset-y-0 left-0 w-6 h-6 transform translate-x-0 dark:translate-x-4 bg-white dark:bg-primary-400 rounded-full shadow-md"></div>
            </div>
          </button>

          <!-- Logout -->
          <a href="../logout.php" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
            <i class="fas fa-sign-out-alt w-5 h-5"></i>
            <span class="font-medium sidebar-text">Logout</span>
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
            <button id="mobileSidebarToggle" class="md:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700">
              <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
            </button>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Guest Management</h2>
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
              <h2 class="text-2xl font-bold text-white mb-2">Guest Management</h2>
              <p class="text-primary-100">Manage event guests and view guest details for all reservations.</p>
            </div>
            <!-- Decorative Elements -->
            <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
            <div class="absolute bottom-0 left-0 -mb-4 -ml-4 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
          </div>
        </div>

        <!-- Search Box -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-4 mb-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="relative flex-1">
              <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <i class="fas fa-search text-gray-400 dark:text-gray-500"></i>
              </div>
              <input type="text" id="searchGuests" placeholder="Search reservations by customer, event, or date..." 
                class="pl-10 w-full p-3 bg-gray-50 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:text-white">
            </div>
          </div>
        </div>

        <!-- Reservation Table with Guest Counts -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden mb-6">
          <div class="p-6 border-b border-gray-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Reservations & Guests</h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">View all reservations and their associated guest lists</p>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="text-left bg-gray-50 dark:bg-slate-700/50">
                  <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Event</th>
                  <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
                  <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date & Time</th>
                  <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Guest Count</th>
                  <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Check-in Status</th>
                  <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                <?php if(count($paginatedReservations) > 0): ?>
                  <?php foreach($paginatedReservations as $reservation): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/25">
                      <td class="px-6 py-4">
                        <div class="flex items-center">
                          <?php
                          // Determine icon and color based on package name
                          $iconClass = 'fas fa-glass-cheers';
                          $bgColor = 'bg-primary-100 dark:bg-primary-900/30';
                          $textColor = 'text-primary-600 dark:text-primary-400';
                          
                          if (stripos($reservation['package_name'], 'corporate') !== false) {
                              $iconClass = 'fas fa-briefcase';
                              $bgColor = 'bg-blue-100 dark:bg-blue-900/30';
                              $textColor = 'text-blue-600 dark:text-blue-400';
                          } elseif (stripos($reservation['package_name'], 'birthday') !== false) {
                              $iconClass = 'fas fa-birthday-cake';
                              $bgColor = 'bg-pink-100 dark:bg-pink-900/30';
                              $textColor = 'text-pink-600 dark:text-pink-400';
                          }
                          ?>
                          <div class="h-10 w-10 flex-shrink-0 <?php echo $bgColor . ' ' . $textColor; ?> rounded-lg flex items-center justify-center">
                            <i class="<?php echo $iconClass; ?>"></i>
                          </div>
                          <div class="ml-3">
                            <p class="text-gray-800 dark:text-white font-medium">
                              <?php echo ucwords(strtolower(htmlspecialchars($reservation['package_name']))); ?>
                            </p>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($reservation['customer_name']); ?></td>
                      <td class="px-6 py-4">
                        <?php 
                        echo '<span class="text-gray-800 dark:text-white">' . date('M d, Y', strtotime($reservation['event_date'])) . '</span><br>' .
                            '<span class="text-xs text-gray-500 dark:text-gray-400">' . 
                            date('h:i A', strtotime($reservation['event_start_time'])) . ' - ' . 
                            date('h:i A', strtotime($reservation['event_end_time'])) . 
                            '</span>';
                        ?>
                      </td>
                      <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-300">
                          <i class="fas fa-users mr-1"></i> <?php echo $reservation['guest_count']; ?>
                        </span>
                      </td>
                      <td class="px-6 py-4">
                        <?php
                        // Use checked_in_count from the SQL query
                        $checkedInCount = $reservation['checked_in_count'];
                        
                        // Calculate percentage if there are guests
                        $percentage = 0;
                        if ($reservation['guest_count'] > 0) {
                            $percentage = round(($checkedInCount / $reservation['guest_count']) * 100);
                        }
                        
                        // Determine color based on percentage
                        $progressColor = 'bg-red-500';
                        $textColor = 'text-red-800 dark:text-red-400';
                        
                        if ($percentage >= 75) {
                            $progressColor = 'bg-green-500';
                            $textColor = 'text-green-800 dark:text-green-400';
                        } elseif ($percentage >= 50) {
                            $progressColor = 'bg-green-400';
                            $textColor = 'text-green-800 dark:text-green-400';
                        } elseif ($percentage >= 25) {
                            $progressColor = 'bg-yellow-500';
                            $textColor = 'text-yellow-800 dark:text-yellow-400';
                        }
                        ?>
                        
                        <div class="flex flex-col w-full gap-1">
                          <div class="flex justify-between items-center">
                            <span class="text-xs font-medium <?php echo $textColor; ?>">
                              <?php echo $checkedInCount; ?> of <?php echo $reservation['guest_count']; ?> checked in
                            </span>
                            <span class="text-xs font-medium <?php echo $textColor; ?>">
                              <?php echo $percentage; ?>%
                            </span>
                          </div>
                          <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-2.5">
                            <div class="<?php echo $progressColor; ?> h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4">
                        <span class="status-badge 
                          <?php 
                          if ($reservation['status'] === 'cancelled') {
                            echo 'status-cancelled';
                          } elseif ($reservation['status'] === 'completed') {
                            echo 'status-completed';
                          } elseif ($reservation['status'] === 'confirmed' && $reservation['payment_status'] === 'partially_paid') {
                            echo 'status-partially_paid';
                          } elseif ($reservation['status'] === 'confirmed' && $reservation['payment_status'] !== 'paid') {
                            echo 'status-confirmed';
                          } elseif (($reservation['status'] === 'confirmed' || $reservation['status'] === 'pending') && $reservation['payment_status'] === 'paid') {
                            echo 'status-paid';
                          } else {
                            echo 'status-pending';
                          }
                          ?>">
                          <?php 
                          if ($reservation['status'] === 'cancelled') {
                            echo '<i class="fas fa-ban mr-1"></i> Cancelled';
                          } elseif ($reservation['status'] === 'completed') {
                            echo '<i class="fas fa-flag-checkered mr-1" style="color: #0e9f6e;"></i> Completed';
                          } elseif ($reservation['status'] === 'confirmed' && $reservation['payment_status'] === 'partially_paid') {
                            echo '<i class="fas fa-percentage mr-1" style="color: #3b82f6;"></i> Partially Paid';
                          } elseif ($reservation['status'] === 'confirmed' && $reservation['payment_status'] !== 'paid') {
                            echo '<i class="fas fa-check-circle mr-1"></i> Confirmed';
                          } elseif (($reservation['status'] === 'confirmed' || $reservation['status'] === 'pending') && $reservation['payment_status'] === 'paid') {
                            echo '<i class="fas fa-credit-card mr-1"></i> Paid';
                          } else {
                            echo '<i class="fas fa-clock mr-1"></i> Pending';
                          }
                          ?>
                        </span>
                      </td>
                      <td class="px-6 py-4">
                        <div class="flex space-x-2">
                          <button class="view-guests-btn px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg hover:shadow-sm"
                            data-booking-id="<?php echo $reservation['id']; ?>"
                            data-customer-name="<?php echo htmlspecialchars($reservation['customer_name']); ?>"
                            data-event-name="<?php echo htmlspecialchars($reservation['package_name']); ?>"
                            data-event-date="<?php echo date('M d, Y', strtotime($reservation['event_date'])); ?>">
                            <i class="fas fa-users mr-1"></i> View Guests
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                      <div class="flex flex-col items-center justify-center">
                        <i class="fas fa-users-slash text-3xl mb-3 text-gray-400 dark:text-gray-600"></i>
                        <p class="text-lg font-medium">No reservations found</p>
                        <p class="text-sm">There are no paid reservations to display.</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
            
            <!-- Pagination controls -->
            <?php if($totalPages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-slate-700">
              <div class="flex flex-col sm:flex-row items-center justify-between">
                <div class="text-sm text-gray-700 dark:text-gray-300 mb-4 sm:mb-0">
                  Showing <?php echo min($totalRecords, $offset + 1); ?> to <?php echo min($totalRecords, $offset + $recordsPerPage); ?> of <?php echo $totalRecords; ?> reservations
                </div>
                
                <!-- New Pagination Design -->
                <nav aria-label="Page navigation">
                  <ul class="inline-flex -space-x-px text-sm">
                    <?php if($page > 1): ?>
                      <li>
                        <a href="?page=<?php echo ($page - 1); ?>" class="flex items-center justify-center px-3 h-8 ms-0 leading-tight text-gray-500 bg-white border border-e-0 border-gray-300 rounded-s-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">Previous</a>
                      </li>
                    <?php else: ?>
                      <li>
                        <span class="flex items-center justify-center px-3 h-8 ms-0 leading-tight text-gray-300 bg-white border border-e-0 border-gray-300 rounded-s-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-500 cursor-not-allowed">Previous</span>
                      </li>
                    <?php endif; ?>
                    
                    <?php 
                    // Show only 5 page numbers with current page in the middle if possible
                    $startPage = max(1, min($page - 2, $totalPages - 4));
                    $endPage = min($totalPages, $startPage + 4);
                    
                    for($i = $startPage; $i <= $endPage; $i++): ?>
                      <li>
                        <a href="?page=<?php echo $i; ?>" 
                          <?php if($page == $i): ?>
                            aria-current="page" class="flex items-center justify-center px-3 h-8 text-blue-600 border border-gray-300 bg-blue-50 hover:bg-blue-100 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white"
                          <?php else: ?>
                            class="flex items-center justify-center px-3 h-8 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                          <?php endif; ?>>
                          <?php echo $i; ?>
                        </a>
                      </li>
                    <?php endfor; ?>
                    
                    <?php if($page < $totalPages): ?>
                      <li>
                        <a href="?page=<?php echo ($page + 1); ?>" class="flex items-center justify-center px-3 h-8 leading-tight text-gray-500 bg-white border border-gray-300 rounded-e-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">Next</a>
                      </li>
                    <?php else: ?>
                      <li>
                        <span class="flex items-center justify-center px-3 h-8 leading-tight text-gray-300 bg-white border border-gray-300 rounded-e-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-500 cursor-not-allowed">Next</span>
                      </li>
                    <?php endif; ?>
                  </ul>
                </nav>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Guest List Modal -->
  <div id="guestListModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden backdrop-blur-sm transition-all duration-300">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-100 mx-4 my-8">
      <div class="p-6 border-b border-gray-200 dark:border-slate-700 sticky top-0 bg-white dark:bg-slate-800 z-10">
        <div class="flex justify-between items-center">
          <div>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white" id="modalEventTitle">Guest List</h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1" id="modalEventDetails"></p>
            <div id="eventStatusBadge" class="mt-2 inline-block px-3 py-1 text-sm rounded-full font-medium"></div>
          </div>
          <button id="closeGuestModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
      </div>
      <div class="p-6 bg-gray-50 dark:bg-slate-900" id="guestListContainer">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h4 class="text-lg font-medium text-gray-800 dark:text-white">Event Guests</h4>
            <p class="text-gray-500 dark:text-gray-400 text-sm">All guests for this reservation</p>
          </div>
          <div class="flex items-center gap-3">
            <button id="takeAttendanceBtn" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg hover:shadow-sm">
              <i class="fas fa-check-circle mr-1"></i> Take Attendance
            </button>
            <span class="px-3 py-1 bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-300 rounded-full text-sm font-medium">
              <i class="fas fa-users mr-1"></i> <span id="guestCount">0</span> Total
            </span>
          </div>
        </div>
        
        <!-- Guest list will be loaded here via AJAX -->
        <div id="guestListContent" class="mt-4">
          <div class="flex items-center justify-center h-24">
            <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-primary-600 dark:border-primary-400"></div>
            <p class="ml-3 text-gray-600 dark:text-gray-400">Loading guests...</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Guest Code Modal -->
  <div id="guestCodeModal" class="fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center hidden backdrop-blur-sm transition-all duration-300">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all duration-300 scale-100 mx-4 my-8">
      <div class="p-6 border-b border-gray-200 dark:border-slate-700 sticky top-0 bg-white dark:bg-slate-800 z-10">
        <div class="flex justify-between items-center">
          <div>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white" id="codeModalGuestName">Guest Code</h3>
          </div>
          <button id="closeCodeModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
      </div>
      <div class="p-6 bg-gray-50 dark:bg-slate-900 flex flex-col items-center">
        <div class="mb-4">
          <p class="text-gray-500 dark:text-gray-400 text-sm text-center">This is the guest's unique entry code</p>
        </div>
        
        <div id="guestCodeContainer" class="bg-white p-6 rounded-lg shadow-md mb-4 flex items-center justify-center">
          <div id="guestCodeDisplay" class="text-3xl font-bold text-primary-600 tracking-widest"></div>
        </div>
        
        <button id="copyCodeBtn" class="mt-6 px-4 py-2 bg-primary-600 hover:bg-primary-700 dark:bg-primary-600 dark:hover:bg-primary-700 text-white rounded-lg">
          <i class="fas fa-copy mr-2"></i> Copy Code
        </button>
      </div>
    </div>
  </div>

  <!-- Guest Attendance Modal -->
  <div id="attendanceModal" class="fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center hidden backdrop-blur-sm transition-all duration-300">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all duration-300 scale-100 mx-4 my-8">
      <div class="p-6 border-b border-gray-200 dark:border-slate-700 sticky top-0 bg-white dark:bg-slate-800 z-10">
        <div class="flex justify-between items-center">
          <div>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white">Take Attendance</h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1" id="attendanceEventDetails"></p>
          </div>
          <button id="closeAttendanceModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
      </div>
      <div class="p-6 bg-gray-50 dark:bg-slate-900">
        <div id="eventNotStartedWarning" class="mb-4 p-3 bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 rounded-lg hidden">
          <i class="fas fa-exclamation-triangle mr-2"></i> 
          This event has not started yet. Attendance can only be taken once the event begins.
        </div>
        
        <div class="mb-6">
          <label for="guestUniqueCode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Enter Guest Unique Code
          </label>
          <input type="text" id="guestUniqueCode" 
            class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-slate-700 text-gray-800 dark:text-white" 
            placeholder="Enter the unique code">
          <div id="attendanceError" class="mt-2 text-sm text-red-600 dark:text-red-400 hidden"></div>
        </div>
        
        <div class="flex justify-end space-x-3">
          <button type="button" id="cancelAttendanceBtn" 
            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-800 dark:text-gray-200 rounded-lg">
            Cancel
          </button>
          <button type="button" id="submitAttendanceBtn" 
            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
            Check In Guest
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Attendance Success Modal -->
  <div id="attendanceSuccessModal" class="fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center hidden backdrop-blur-sm transition-all duration-300">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all duration-300 scale-100 mx-4 my-8">
      <div class="p-6 border-b border-gray-200 dark:border-slate-700 sticky top-0 bg-white dark:bg-slate-800 z-10">
        <div class="flex justify-between items-center">
          <div>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white">Attendance Confirmed</h3>
          </div>
          <button id="closeSuccessModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
      </div>
      <div class="p-6 bg-gray-50 dark:bg-slate-900 text-center">
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 dark:bg-green-900/30 mb-4">
          <i class="fas fa-check text-2xl text-green-600 dark:text-green-400"></i>
        </div>
        <h4 class="text-lg font-medium text-gray-800 dark:text-white mb-2" id="successGuestName"></h4>
        <p class="text-gray-600 dark:text-gray-400 mb-6" id="successMessage"></p>
        
        <button type="button" id="doneAttendanceBtn" 
          class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg">
          Done
        </button>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Mobile sidebar toggle
      const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
      
      mobileSidebarToggle.addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        
        // Toggle the sidebar without transition classes
        if (sidebar.classList.contains('translate-x-0')) {
          sidebar.classList.remove('translate-x-0');
        } else {
          sidebar.classList.add('translate-x-0');
        }
      });

      // Handle sidebar collapse functionality
      const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('mainContent');
      const themeToggleIndicator = document.getElementById('themeToggleIndicator');
      
      let isSidebarCollapsed = false;
      
      sidebarCollapseBtn.addEventListener('click', function() {
        isSidebarCollapsed = !isSidebarCollapsed;
        
        if (isSidebarCollapsed) {
          sidebar.classList.remove('w-64');
          sidebar.classList.add('w-20');
          mainContent.classList.remove('ml-64');
          mainContent.classList.add('ml-20');
          
          // Hide all text elements in sidebar when collapsed
          const allSidebarTexts = sidebar.querySelectorAll('span');
          allSidebarTexts.forEach(text => {
            text.style.display = 'none';
          });
          
          // Handle theme toggle indicator
          if (themeToggleIndicator) {
            themeToggleIndicator.style.display = 'none';
          }
        } else {
          sidebar.classList.remove('w-20');
          sidebar.classList.add('w-64');
          mainContent.classList.remove('ml-20');
          mainContent.classList.add('ml-64');
          
          // Show all text elements in sidebar when expanded
          const allSidebarTexts = sidebar.querySelectorAll('span');
          allSidebarTexts.forEach(text => {
            text.style.display = 'inline';
          });
          
          // Handle theme toggle indicator
          if (themeToggleIndicator) {
            themeToggleIndicator.style.display = 'block';
          }
        }
      });

      // Theme toggle functionality
      const themeToggle = document.getElementById('themeToggle');
      const html = document.documentElement;
      
      // Check if theme already set in localStorage
      if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        html.classList.add('dark');
      } else {
        html.classList.remove('dark');
      }
      
      themeToggle.addEventListener('click', function() {
        html.classList.toggle('dark');
        localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
      });

      // Notification popup functionality
      const notificationBtn = document.getElementById('notificationBtn');
      const notificationPopup = document.getElementById('notificationPopup');
      
      let isPopupVisible = false;
      
      notificationBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        isPopupVisible = !isPopupVisible;
        notificationPopup.classList.toggle('hidden');
      });
      
      // Handle Mark All Read functionality
      const markAllReadBtn = document.getElementById('markAllReadBtn');
      const notificationDot = document.getElementById('notificationDot');

      if (markAllReadBtn) {
          markAllReadBtn.addEventListener('click', function(e) {
              e.stopPropagation();
              
              // Send AJAX request to mark notifications as read
              fetch('guest.php', {
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
        if (notificationPopup && !notificationPopup.contains(e.target) && e.target !== notificationBtn) {
          notificationPopup.classList.add('hidden');
        }
      });

      // Guest List Modal Functionality
      const guestListModal = document.getElementById('guestListModal');
      const closeGuestModalBtn = document.getElementById('closeGuestModal');
      const viewGuestBtns = document.querySelectorAll('.view-guests-btn');
      const modalEventTitle = document.getElementById('modalEventTitle');
      const modalEventDetails = document.getElementById('modalEventDetails');
      const guestListContent = document.getElementById('guestListContent');
      const guestCountEl = document.getElementById('guestCount');
      const eventStatusBadge = document.getElementById('eventStatusBadge');
      const eventNotStartedWarning = document.getElementById('eventNotStartedWarning');
      
      // Attendance Modal References
      const takeAttendanceBtn = document.getElementById('takeAttendanceBtn');
      const attendanceModal = document.getElementById('attendanceModal');
      const closeAttendanceModal = document.getElementById('closeAttendanceModal');
      const attendanceEventDetails = document.getElementById('attendanceEventDetails');
      const guestUniqueCode = document.getElementById('guestUniqueCode');
      const submitAttendanceBtn = document.getElementById('submitAttendanceBtn');
      const cancelAttendanceBtn = document.getElementById('cancelAttendanceBtn');
      const attendanceError = document.getElementById('attendanceError');
      
      // Success Modal References
      const attendanceSuccessModal = document.getElementById('attendanceSuccessModal');
      const closeSuccessModal = document.getElementById('closeSuccessModal');
      const successGuestName = document.getElementById('successGuestName');
      const successMessage = document.getElementById('successMessage');
      const doneAttendanceBtn = document.getElementById('doneAttendanceBtn');
      
      // Current booking variables
      let currentBookingId = null;
      let currentEventName = '';
      let currentCustomerName = '';
      let currentEventDate = '';
      let currentEventStartTime = '';
      let isEventStarted = false;

      viewGuestBtns.forEach(btn => {
        btn.addEventListener('click', function() {
          const bookingId = this.getAttribute('data-booking-id');
          const customerName = this.getAttribute('data-customer-name');
          const eventName = this.getAttribute('data-event-name');
          const eventDate = this.getAttribute('data-event-date');
          
          // Store current booking details for attendance modal
          currentBookingId = bookingId;
          currentEventName = eventName;
          currentCustomerName = customerName;
          
          // Set modal title and details - capitalize the first letter of the event name
          modalEventTitle.textContent = eventName.charAt(0).toUpperCase() + eventName.slice(1).toLowerCase();
          modalEventDetails.textContent = `${customerName} | ${eventDate}`;
          
          // Show the modal
          guestListModal.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
          
          // Check if event has started
          checkEventStatus(bookingId);
          
          // Load guest list via AJAX
          fetchGuestList(bookingId);
        });
      });

      // Function to check if an event has started
      function checkEventStatus(bookingId) {
        fetch(`check_event_status.php?booking_id=${bookingId}`)
          .then(response => response.json())
          .then(data => {
            isEventStarted = data.is_started;
            
            if (data.is_started) {
              // Event has started - show green badge
              eventStatusBadge.classList.remove('bg-red-100', 'text-red-800', 'dark:bg-red-900/30', 'dark:text-red-400');
              eventStatusBadge.classList.add('bg-green-100', 'text-green-800', 'dark:bg-green-900/30', 'dark:text-green-400');
              eventStatusBadge.innerHTML = '<i class="fas fa-play-circle mr-1"></i> Event In Progress';
              
              // Enable the Take Attendance button
              takeAttendanceBtn.disabled = false;
              takeAttendanceBtn.classList.remove('opacity-50', 'cursor-not-allowed');
              
              // Hide warning in attendance modal
              eventNotStartedWarning.classList.add('hidden');
            } else {
              // Event has not started - show red badge
              eventStatusBadge.classList.remove('bg-green-100', 'text-green-800', 'dark:bg-green-900/30', 'dark:text-green-400');
              eventStatusBadge.classList.add('bg-red-100', 'text-red-800', 'dark:bg-red-900/30', 'dark:text-red-400');
              eventStatusBadge.innerHTML = '<i class="fas fa-clock mr-1"></i> Event Not Started';
              
              // Show warning in attendance modal
              eventNotStartedWarning.classList.remove('hidden');
            }
            
            // Store event details
            currentEventDate = data.event_date;
            currentEventStartTime = data.start_time;
          })
          .catch(error => {
            console.error('Error checking event status:', error);
            eventStatusBadge.classList.add('bg-gray-100', 'text-gray-800', 'dark:bg-gray-900/30', 'dark:text-gray-400');
            eventStatusBadge.textContent = 'Status Unknown';
          });
      }

      // Take Attendance Button Click
      takeAttendanceBtn.addEventListener('click', function() {
        // Check if event has started before proceeding
        if (!isEventStarted) {
          // Show warning message
          const warningModal = document.createElement('div');
          warningModal.className = 'fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center backdrop-blur-sm';
          warningModal.innerHTML = `
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
              <div class="text-center mb-4">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 mb-4">
                  <i class="fas fa-exclamation-triangle text-xl text-red-600 dark:text-red-400"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Event Not Started</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                  This event hasn't started yet. Attendance can only be taken once the event begins.
                </p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                  Event starts on ${currentEventDate} at ${currentEventStartTime}
                </p>
              </div>
              <div class="flex justify-center">
                <button class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg">
                  Understood
                </button>
              </div>
            </div>
          `;
          
          document.body.appendChild(warningModal);
          
          // Add event listener to close the warning
          warningModal.querySelector('button').addEventListener('click', function() {
            document.body.removeChild(warningModal);
          });
          
          return;
        }
        
        // Hide the guest list modal
        guestListModal.classList.add('hidden');
        
        // Set the attendance modal details
        attendanceEventDetails.textContent = `${currentEventName} - ${currentCustomerName}`;
        
        // Clear previous inputs and errors
        guestUniqueCode.value = '';
        attendanceError.classList.add('hidden');
        
        // Show the attendance modal
        attendanceModal.classList.remove('hidden');
        
        // Focus on the input field
        setTimeout(() => {
          guestUniqueCode.focus();
        }, 100);
      });
      
      // Close Attendance Modal
      if (closeAttendanceModal) {
        closeAttendanceModal.addEventListener('click', function() {
          attendanceModal.classList.add('hidden');
          guestListModal.classList.remove('hidden');
        });
      }
      
      if (cancelAttendanceBtn) {
        cancelAttendanceBtn.addEventListener('click', function() {
          attendanceModal.classList.add('hidden');
          guestListModal.classList.remove('hidden');
        });
      }
      
      // Handle Submit Attendance
      submitAttendanceBtn.addEventListener('click', submitAttendance);
      
      // Allow pressing Enter to submit
      guestUniqueCode.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          submitAttendance();
        }
      });
      
      function submitAttendance() {
        // Check if event has started before proceeding
        if (!isEventStarted) {
          attendanceError.textContent = 'This event has not started yet. Attendance can only be taken once the event begins.';
          attendanceError.classList.remove('hidden');
          return;
        }
        
        const uniqueCode = guestUniqueCode.value.trim();
        
        // Validate input
        if (!uniqueCode) {
          attendanceError.textContent = 'Please enter a unique code';
          attendanceError.classList.remove('hidden');
          return;
        }
        
        // Hide error if previously shown
        attendanceError.classList.add('hidden');
        
        // Show loading state
        const originalBtnText = submitAttendanceBtn.innerHTML;
        submitAttendanceBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
        submitAttendanceBtn.disabled = true;
        
        // Create form data
        const formData = new FormData();
        formData.append('unique_code', uniqueCode);
        formData.append('booking_id', currentBookingId);
        
        // Submit attendance
        fetch('take_attendance.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          // Reset button state
          submitAttendanceBtn.innerHTML = originalBtnText;
          submitAttendanceBtn.disabled = false;
          
          if (data.success) {
            // Hide attendance modal
            attendanceModal.classList.add('hidden');
            
            // Set success information
            successGuestName.textContent = data.guest.name;
            
            if (data.guest.already_checked_in) {
              successMessage.textContent = 'This guest has already been checked in.';
            } else {
              successMessage.textContent = 'Guest has been successfully checked in!';
            }
            
            // Show success modal
            attendanceSuccessModal.classList.remove('hidden');
          } else {
            // Show error
            attendanceError.textContent = data.message || 'Error checking in guest. Please try again.';
            attendanceError.classList.remove('hidden');
          }
        })
        .catch(error => {
          console.error('Error checking in guest:', error);
          submitAttendanceBtn.innerHTML = originalBtnText;
          submitAttendanceBtn.disabled = false;
          attendanceError.textContent = 'An error occurred. Please try again.';
          attendanceError.classList.remove('hidden');
        });
      }
      
      // Close Success Modal
      if (closeSuccessModal) {
        closeSuccessModal.addEventListener('click', function() {
          attendanceSuccessModal.classList.add('hidden');
          guestListModal.classList.remove('hidden');
          
          // Refresh the guest list to show updated attendance status
          fetchGuestList(currentBookingId);
        });
      }
      
      if (doneAttendanceBtn) {
        doneAttendanceBtn.addEventListener('click', function() {
          attendanceSuccessModal.classList.add('hidden');
          guestListModal.classList.remove('hidden');
          
          // Refresh the guest list to show updated attendance status
          fetchGuestList(currentBookingId);
        });
      }

      // Search functionality
      const searchInput = document.getElementById('searchGuests');
      const rows = document.querySelectorAll('tbody tr');
      
      if (searchInput) {
        searchInput.addEventListener('keyup', function() {
          const searchTerm = this.value.toLowerCase();
          
          rows.forEach(row => {
            const rowText = row.textContent.toLowerCase();
            if (rowText.includes(searchTerm)) {
              row.style.display = '';
            } else {
              row.style.display = 'none';
            }
          });
        });
      }

      // Handle keyboard events for modals
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          // Close guest modal when Escape key is pressed
          if (!guestListModal.classList.contains('hidden')) {
            guestListModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
          }
          
          // Close code modal when Escape key is pressed
          if (!guestCodeModal.classList.contains('hidden')) {
            guestCodeModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
          }
          
          // Close attendance modal when Escape key is pressed
          if (!attendanceModal.classList.contains('hidden')) {
            attendanceModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
          }
          
          // Close success modal when Escape key is pressed
          if (!attendanceSuccessModal.classList.contains('hidden')) {
            attendanceSuccessModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
          }
          
          if (notificationPopup && !notificationPopup.classList.contains('hidden')) {
            notificationPopup.classList.add('hidden');
          }
        }
      });

      // Guest Code Modal References
      const guestCodeModal = document.getElementById('guestCodeModal');
      const closeCodeModal = document.getElementById('closeCodeModal');
      const codeModalGuestName = document.getElementById('codeModalGuestName');
      const guestCodeDisplay = document.getElementById('guestCodeDisplay');
      const copyCodeBtn = document.getElementById('copyCodeBtn');

      // Close Code Modal
      if (closeCodeModal) {
        closeCodeModal.addEventListener('click', function() {
          guestCodeModal.classList.add('hidden');
          document.body.style.overflow = 'auto';
        });
      }

      // Function to show guest code 
      function showGuestCode(guestName, guestCode, guestId) {
        // Set guest name in modal
        codeModalGuestName.textContent = guestName;
        
        // Display the code
        if (guestCode && guestCode.trim() !== '') {
          // Check if code is a path to a QR code image
          if (guestCode.includes('uploads/qrcodes/')) {
            // Extract just the filename without extension
            const filename = guestCode.split('/').pop().split('.')[0];
            // Further extract just the hash part (after the underscore)
            const parts = filename.split('_');
            const codeToDisplay = parts.length > 1 ? parts[1] : filename;
            guestCodeDisplay.textContent = codeToDisplay;
          } else {
            // Display the code as is
            guestCodeDisplay.textContent = guestCode;
          }
          copyCodeBtn.disabled = false;
        } else {
          guestCodeDisplay.textContent = 'No code available';
          copyCodeBtn.disabled = true;
        }
        
        // Set up copy button
        copyCodeBtn.onclick = function() {
          if (guestCode && guestCode.trim() !== '') {
            let textToCopy = guestCodeDisplay.textContent;
            navigator.clipboard.writeText(textToCopy).then(function() {
              // Show success message
              const originalText = copyCodeBtn.innerHTML;
              copyCodeBtn.innerHTML = '<i class="fas fa-check mr-2"></i> Copied!';
              copyCodeBtn.classList.remove('bg-primary-600', 'hover:bg-primary-700');
              copyCodeBtn.classList.add('bg-green-600', 'hover:bg-green-700');
              
              // Reset button after a delay
              setTimeout(function() {
                copyCodeBtn.innerHTML = originalText;
                copyCodeBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                copyCodeBtn.classList.add('bg-primary-600', 'hover:bg-primary-700');
              }, 2000);
            }).catch(function(err) {
              console.error('Could not copy text: ', err);
            });
          }
        };
        
        // Show the modal
        guestCodeModal.classList.remove('hidden');
      }

      // Update the event listeners to use the new function
      document.querySelectorAll('.view-code').forEach(btn => {
        btn.addEventListener('click', function() {
          const guestId = this.getAttribute('data-id');
          const guestName = this.getAttribute('data-name');
          const guestCode = this.getAttribute('data-code');
          
          showGuestCode(guestName, guestCode, guestId);
        });
      });

      // Add these functions at the end of the document.addEventListener('DOMContentLoaded', function() {...}); block
      
      // Guest list filtering and sorting functionality
      let currentGuestList = [];
      
      function fetchGuestList(bookingId) {
        // Make an AJAX call to fetch guest data from the database
        fetch(`get_guests.php?booking_id=${bookingId}`)
          .then(response => response.json())
          .then(data => {
            // Store the current guest list
            currentGuestList = data;
            
            // Update guest count
            guestCountEl.textContent = data.length;
            
            if (data.length === 0) {
              guestListContent.innerHTML = `
                <div class="flex items-center justify-center h-24">
                  <div class="text-gray-500 dark:text-gray-400 text-center">
                    <i class="fas fa-user-slash text-3xl mb-2"></i>
                    <p>No guests found for this reservation</p>
                  </div>
                </div>
              `;
              return;
            }
            
            // Generate guest cards
            let guestHtml = `
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            `;
            
            data.forEach((guest, index) => {
              // Get initials for avatar
              const initials = guest.name.split(' ')
                .map(n => n[0])
                .join('')
                .toUpperCase();
                
              // Generate a consistent color based on the name
              const colors = ['6366F1', '8B5CF6', 'EC4899', 'F43F5E', '14B8A6', '0EA5E9', 'F59E0B'];
              const colorIndex = guest.name.charCodeAt(0) % colors.length;
              const avatarColor = colors[colorIndex];
              
              guestHtml += `
                <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4 shadow-sm hover:shadow-md">
                  <div class="flex items-center">
                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(initials)}&background=${avatarColor}&color=fff" 
                         alt="Guest Avatar" class="w-12 h-12 rounded-full mr-4">
                    <div class="flex-1">
                      <div class="flex items-center justify-between">
                        <h5 class="font-medium text-gray-800 dark:text-white">${guest.name}</h5>
                        ${parseInt(guest.is_checked_in) ? 
                          `<span class="px-3 py-1 bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 rounded-full text-xs font-medium">
                             <i class="fas fa-check-circle mr-1"></i> Checked In
                           </span>` : 
                          `<span class="px-3 py-1 bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 rounded-full text-xs font-medium">
                             <i class="fas fa-clock mr-1"></i> Not Checked In
                           </span>`
                        }
                      </div>
                      <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        ${guest.check_in_time ? 
                          `<i class="fas fa-calendar-check text-green-500 dark:text-green-400 mr-1"></i> ${new Date(guest.check_in_time).toLocaleString()}` : ''}
                      </div>
                    </div>
                  </div>
                  <div class="mt-3 pt-3 border-t border-gray-100 dark:border-slate-700">
                    <div class="grid grid-cols-1 gap-2 text-sm">
                      <div class="mb-2">
                        <p class="text-gray-500 dark:text-gray-400">Email</p>
                        <p class="font-medium text-gray-700 dark:text-gray-300">${guest.email || 'Not provided'}</p>
                      </div>
                      <div>
                        <p class="text-gray-500 dark:text-gray-400">Phone</p>
                        <p class="font-medium text-gray-700 dark:text-gray-300">${guest.phone || 'Not provided'}</p>
                      </div>
                    </div>
                  </div>
                </div>
              `;
            });
            
            guestHtml += `</div>`;
            
            // Update the guest list content
            guestListContent.innerHTML = guestHtml;
          })
          .catch(error => {
            console.error('Error fetching guest data:', error);
            guestListContent.innerHTML = `
              <div class="flex items-center justify-center h-24">
                <div class="text-red-500 dark:text-red-400 text-center">
                  <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                  <p>Failed to load guest data. Please try again.</p>
                </div>
              </div>
            `;
          });
      }
      
      // Add close modal functionality 
      if (closeGuestModalBtn) {
        closeGuestModalBtn.addEventListener('click', function() {
          guestListModal.classList.add('hidden');
          document.body.style.overflow = 'auto';
          // Reset guest list content
          guestListContent.innerHTML = `
            <div class="flex items-center justify-center h-24">
              <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-primary-600 dark:border-primary-400"></div>
              <p class="ml-3 text-gray-600 dark:text-gray-400">Loading guests...</p>
            </div>
          `;
        });
      }

      // Add event listener for search input
      const searchGuestsInput = document.getElementById('searchGuests');
      const filterGuestsSelect = document.getElementById('filterGuests');
      const sortGuestsSelect = document.getElementById('sortGuests');
      
      function filterAndSortGuests() {
        const searchTerm = searchGuestsInput ? searchGuestsInput.value.toLowerCase() : '';
        const filterValue = filterGuestsSelect ? filterGuestsSelect.value : 'all';
        const sortValue = sortGuestsSelect ? sortGuestsSelect.value : 'name-asc';
        
        const guestCards = document.querySelectorAll('#guestListContent .grid > div');
        
        guestCards.forEach(card => {
          const cardText = card.textContent.toLowerCase();
          const isCheckedIn = card.querySelector('.bg-green-100') !== null;
          
          // Apply search filter
          const matchesSearch = cardText.includes(searchTerm);
          
          // Apply status filter
          let matchesFilter = true;
          if (filterValue === 'checked-in') {
            matchesFilter = isCheckedIn;
          } else if (filterValue === 'not-checked-in') {
            matchesFilter = !isCheckedIn;
          }
          
          // Show or hide based on filters
          if (matchesSearch && matchesFilter) {
            card.style.display = '';
          } else {
            card.style.display = 'none';
          }
        });
        
        // Sort cards (need to collect visible cards and re-append them)
        const grid = document.querySelector('#guestListContent .grid');
        if (grid && sortValue !== 'default') {
          const visibleCards = Array.from(guestCards).filter(card => card.style.display !== 'none');
          
          visibleCards.sort((a, b) => {
            const nameA = a.querySelector('h5').textContent.trim();
            const nameB = b.querySelector('h5').textContent.trim();
            
            if (sortValue === 'name-asc') {
              return nameA.localeCompare(nameB);
            } else if (sortValue === 'name-desc') {
              return nameB.localeCompare(nameA);
            }
            return 0;
          });
          
          // Re-append cards in sorted order
          visibleCards.forEach(card => grid.appendChild(card));
        }
      }
      
      if (searchGuestsInput) {
        searchGuestsInput.addEventListener('input', filterAndSortGuests);
      }
      
      if (filterGuestsSelect) {
        filterGuestsSelect.addEventListener('change', filterAndSortGuests);
      }
      
      if (sortGuestsSelect) {
        sortGuestsSelect.addEventListener('change', filterAndSortGuests);
      }
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
        /* Removing transition for smoother page load */
        /* transition: transform 0.3s ease-in-out; */
      }
      
      #sidebar.translate-x-0 {
        transform: translateX(0);
      }
      
      #mainContent {
        margin-left: 0 !important;
      }
    }
  </style>
</body>
</html>
