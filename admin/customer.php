<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php"); // Redirect to login if not authenticated
  exit();
}

// Fetch the admin's username from the session
$username = $_SESSION['user_name'];

include '../db/config.php';

// Handle AJAX delete request
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);
    
    // Delete the user from the database
    $deleteSql = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("i", $userId);
    $success = $stmt->execute();
    
    // Return JSON response
    header('Content-Type: application/json');
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }
    exit;
}

// Fetch all users from the database
$users = [];
$sql = "SELECT id, name, email, phone, birthday FROM users ORDER BY id DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $users[] = $row;
  }
}

// Check if notification has been read
$notificationsRead = isset($_SESSION['notifications_read']) ? $_SESSION['notifications_read'] : false;

// Handle marking notifications as read via AJAX
if (isset($_POST['mark_read']) && $_POST['mark_read'] == 1) {
    $_SESSION['notifications_read'] = true;
    echo json_encode(['success' => true]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>The Barn & Backyard | Customer Management</title>
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
      
      /* Removed transition for smoother page load */
      .transition-smooth {
          /* transition: all 0.2s ease-in-out; */
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
      
      .dark .dark\:text-gray-300,
      .dark .dark\:text-gray-200 {
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
      
      .dark .dark\:hover\:bg-slate-700:hover {
          background-color: #21262d !important;
      }
      
      .dark .dark\:hover\:bg-slate-700\/25:hover {
          background-color: rgba(33, 38, 45, 0.25) !important;
      }
      
      .dark .dark\:bg-primary-900\/20 {
          background-color: rgba(12, 74, 110, 0.2) !important;
      }
      
      /* Dark Mode Styles for Toggle Circle - Updated to match theme.php */
      .dark #themeToggleIndicator {
          background-color: #1e293b !important;
      }
      
      .dark #themeToggleIndicator div {
          background-color: #60a5fa !important;
          box-shadow: 0 0 5px rgba(96, 165, 250, 0.5) !important;
      }

      /* Toast notification animations */
      @keyframes slideInRight {
          from {
              transform: translateX(100%);
              opacity: 0;
          }
          to {
              transform: translateX(0);
              opacity: 1;
          }
      }
      
      @keyframes fadeOut {
          from {
              opacity: 1;
          }
          to {
              opacity: 0;
          }
      }
      
      .toast-enter {
          animation: slideInRight 0.3s ease-out forwards;
      }
      
      .toast-exit {
          animation: fadeOut 0.3s ease-out forwards;
      }
      
      /* Row deletion animation */
      tr {
          transition: opacity 0.3s ease, transform 0.3s ease;
      }
      
      tr.deleting {
          opacity: 0;
          transform: translateX(20px);
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
          <a href="guest.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
            <i class="fas fa-users w-5 h-5"></i>
            <span class="font-medium sidebar-text">Manage Guests</span>
          </a>

          <!-- Customers -->
          <a href="customer.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400">
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
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Customer Management</h2>
          </div>
          
          <div class="flex items-center space-x-4">
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
              <h2 class="text-2xl font-bold text-white mb-2">Customer Management</h2>
              <p class="text-primary-100">Manage and view all registered customers on the platform.</p>
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
              <input type="text" id="searchCustomers" placeholder="Search customers by name or email..." 
                class="pl-10 w-full p-3 bg-gray-50 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:text-white">
            </div>
          </div>
        </div>
        
        <!-- Customer List -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden mb-6">
          <div class="p-6 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center">
            <div>
              <h3 class="text-lg font-semibold text-gray-800 dark:text-white">All Customers</h3>
              <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">View and manage all registered customers</p>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="text-left bg-gray-50 dark:bg-slate-700/50">
                  <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                  <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                  <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</th>
                  <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Birthday</th>
                  <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                <?php if(count($users) > 0): ?>
                  <?php foreach($users as $user): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/25 transition-opacity duration-300" data-customer-id="<?php echo $user['id']; ?>">
                      <td class="px-6 py-4 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($user['name']); ?></td>
                      <td class="px-6 py-4 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($user['email']); ?></td>
                      <td class="px-6 py-4 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($user['phone']); ?></td>
                      <td class="px-6 py-4 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($user['birthday']); ?></td>
                      <td class="px-6 py-4 text-center">
                        <button class="deleteCustomerBtn inline-flex justify-center px-3 py-1 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 text-xs font-medium rounded-md hover:bg-red-200 dark:hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 transition-colors" data-customer-id="<?php echo $user['id']; ?>">
                          Delete
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                      <div class="flex flex-col items-center justify-center">
                        <i class="fas fa-users-slash text-3xl mb-3 text-gray-400 dark:text-gray-600"></i>
                        <p class="text-lg font-medium">No customers found</p>
                        <p class="text-sm">There are no registered customers in the system.</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Add the customer view modal -->
  <div id="viewCustomerModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" id="viewCustomerOverlay"></div>
    <div class="relative top-20 mx-auto p-5 w-full max-w-2xl">
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg overflow-hidden">
        <!-- Modal header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
            Customer Details
          </h3>
          <button id="closeViewCustomerModal" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Modal body -->
        <div class="p-6">
          <div id="customerDetails" class="space-y-4">
            <!-- Customer information will be loaded here -->
            <div class="animate-pulse">
              <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-4"></div>
              <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-4"></div>
              <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-2/3 mb-4"></div>
              <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/5 mb-4"></div>
            </div>
          </div>
        </div>

        <!-- Modal footer -->
        <div class="flex items-center justify-end p-4 border-t border-gray-200 dark:border-gray-700">
          <button id="closeViewCustomerBtn" class="inline-flex justify-center px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Add the delete confirmation modal -->
  <div id="deleteCustomerModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" id="deleteCustomerOverlay"></div>
    <div class="relative top-20 mx-auto p-5 w-full max-w-md">
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg overflow-hidden">
        <!-- Modal header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
            Confirm Deletion
          </h3>
          <button id="closeDeleteCustomerModal" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Modal body -->
        <div class="p-6">
          <div class="flex items-center justify-center mb-4 text-red-500 dark:text-red-400">
            <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <p class="text-center text-gray-700 dark:text-gray-300">Are you sure you want to delete this customer? This action cannot be undone.</p>
          <input type="hidden" id="deleteCustomerId" value="">
        </div>

        <!-- Modal footer -->
        <div class="flex items-center justify-end p-4 space-x-3 border-t border-gray-200 dark:border-gray-700">
          <button id="cancelDeleteBtn" class="inline-flex justify-center px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors">
            Cancel
          </button>
          <button id="confirmDeleteBtn" class="inline-flex justify-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 transition-colors">
            Yes, Delete
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Add JavaScript code for enhanced functionality -->
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
      
      notificationBtn.addEventListener('click', function(e) {
        e.stopPropagation();
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
      
      // Close notification popup when clicking outside
      document.addEventListener('click', function(e) {
        if (notificationPopup && !notificationPopup.contains(e.target) && e.target !== notificationBtn) {
          notificationPopup.classList.add('hidden');
        }
      });

      // Customer search functionality
      const searchCustomers = document.getElementById('searchCustomers');
      const customerRows = document.querySelectorAll('tbody tr');
      
      if (searchCustomers) {
        searchCustomers.addEventListener('keyup', function() {
          const searchTerm = this.value.toLowerCase().trim();
          
          customerRows.forEach(row => {
            const customerData = row.textContent.toLowerCase();
            if (customerData.includes(searchTerm)) {
              row.style.display = '';
            } else {
              row.style.display = 'none';
            }
          });
        });
      }

      // Customer View Modal Functionality
      const viewCustomerBtns = document.querySelectorAll('.viewCustomerBtn');
      const viewCustomerModal = document.getElementById('viewCustomerModal');
      const viewCustomerOverlay = document.getElementById('viewCustomerOverlay');
      const closeViewCustomerModal = document.getElementById('closeViewCustomerModal');
      const closeViewCustomerBtn = document.getElementById('closeViewCustomerBtn');
      const customerDetails = document.getElementById('customerDetails');

      if (viewCustomerBtns.length > 0) {
        viewCustomerBtns.forEach(btn => {
          btn.addEventListener('click', function() {
            const customerId = this.getAttribute('data-customer-id');
            viewCustomerModal.classList.remove('hidden');
            
            // Demo customer data - In a real application, you would fetch this from the server
            setTimeout(() => {
              customerDetails.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Full Name</p>
                    <p class="text-base text-gray-900 dark:text-white">John Doe</p>
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</p>
                    <p class="text-base text-gray-900 dark:text-white">john.doe@example.com</p>
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</p>
                    <p class="text-base text-gray-900 dark:text-white">+1 234 567 890</p>
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Birthday</p>
                    <p class="text-base text-gray-900 dark:text-white">January 1, 1990</p>
                  </div>
                  <div class="md:col-span-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Address</p>
                    <p class="text-base text-gray-900 dark:text-white">123 Main St, Anytown, USA</p>
                  </div>
                  <div class="md:col-span-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Registration Date</p>
                    <p class="text-base text-gray-900 dark:text-white">March 15, 2023</p>
                  </div>
                </div>
              `;
            }, 1000);
          });
        });

        // Close modal functions
        if (closeViewCustomerModal) {
          closeViewCustomerModal.addEventListener('click', function() {
            viewCustomerModal.classList.add('hidden');
          });
        }

        if (closeViewCustomerBtn) {
          closeViewCustomerBtn.addEventListener('click', function() {
            viewCustomerModal.classList.add('hidden');
          });
        }

        if (viewCustomerOverlay) {
          viewCustomerOverlay.addEventListener('click', function() {
            viewCustomerModal.classList.add('hidden');
          });
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape' && !viewCustomerModal.classList.contains('hidden')) {
            viewCustomerModal.classList.add('hidden');
          }
        });
      }

      // Customer Delete Modal Functionality
      const deleteCustomerBtns = document.querySelectorAll('.deleteCustomerBtn');
      const deleteCustomerModal = document.getElementById('deleteCustomerModal');
      const deleteCustomerOverlay = document.getElementById('deleteCustomerOverlay');
      const closeDeleteCustomerModal = document.getElementById('closeDeleteCustomerModal');
      const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
      const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
      const deleteCustomerId = document.getElementById('deleteCustomerId');

      // Function to show toast notifications
      function showToast(message, type = 'success') {
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
          toastContainer = document.createElement('div');
          toastContainer.id = 'toastContainer';
          toastContainer.className = 'fixed top-4 right-4 z-50 flex flex-col space-y-4 max-w-md';
          document.body.appendChild(toastContainer);
        }
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `flex items-center p-4 mb-4 rounded-lg shadow-lg ${
          type === 'success' 
            ? 'bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300 border-l-4 border-green-500' 
            : 'bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300 border-l-4 border-red-500'
        }`;
        
        // Add toast content
        toast.innerHTML = `
          <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-lg ${
            type === 'success' 
              ? 'text-green-500 bg-green-100 dark:bg-green-800 dark:text-green-200' 
              : 'text-red-500 bg-red-100 dark:bg-red-800 dark:text-red-200'
          }">
            <i class="fas ${type === 'success' ? 'fa-check' : 'fa-exclamation-circle'}"></i>
          </div>
          <div class="ml-3 text-sm font-normal">${message}</div>
          <button type="button" class="ml-auto -mx-1.5 -my-1.5 rounded-lg p-1.5 inline-flex h-8 w-8 ${
            type === 'success' 
              ? 'text-green-500 hover:bg-green-200 dark:hover:bg-green-800/50' 
              : 'text-red-500 hover:bg-red-200 dark:hover:bg-red-800/50'
          } focus:outline-none">
            <i class="fas fa-times"></i>
          </button>
        `;
        
        // Add to DOM
        toastContainer.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
          toast.classList.add('toast-enter');
        }, 10);
        
        // Add close button functionality
        const closeButton = toast.querySelector('button');
        closeButton.addEventListener('click', () => {
          toast.classList.add('toast-exit');
          setTimeout(() => {
            toast.remove();
          }, 300);
        });
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
          if (toast.parentNode) {
            toast.classList.add('toast-exit');
            setTimeout(() => {
              if (toast.parentNode) {
                toast.remove();
              }
            }, 300);
          }
        }, 5000);
      }

      if (deleteCustomerBtns.length > 0) {
        deleteCustomerBtns.forEach(btn => {
          btn.addEventListener('click', function() {
            const customerId = this.getAttribute('data-customer-id');
            deleteCustomerId.value = customerId;
            deleteCustomerModal.classList.remove('hidden');
          });
        });

        // Close modal functions
        if (closeDeleteCustomerModal) {
          closeDeleteCustomerModal.addEventListener('click', function() {
            deleteCustomerModal.classList.add('hidden');
          });
        }

        if (cancelDeleteBtn) {
          cancelDeleteBtn.addEventListener('click', function() {
            deleteCustomerModal.classList.add('hidden');
          });
        }

        if (deleteCustomerOverlay) {
          deleteCustomerOverlay.addEventListener('click', function() {
            deleteCustomerModal.classList.add('hidden');
          });
        }

        // Delete confirmation
        if (confirmDeleteBtn) {
          confirmDeleteBtn.addEventListener('click', function() {
            const customerId = deleteCustomerId.value;
            const row = document.querySelector(`tr[data-customer-id="${customerId}"]`);
            
            // Show loading state
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
            
            // Send AJAX request to delete the customer
            fetch('customer.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: `delete_user=1&user_id=${customerId}`
            })
            .then(response => response.json())
            .then(data => {
              deleteCustomerModal.classList.add('hidden');
              
              if (data.success) {
                // Show success message
                showToast('Customer deleted successfully', 'success');
                
                // Find and remove the customer row from the table
                const customerRows = document.querySelectorAll('tbody tr');
                customerRows.forEach(row => {
                  const rowCustomerId = row.querySelector('.deleteCustomerBtn').getAttribute('data-customer-id');
                  if (rowCustomerId === customerId) {
                    row.classList.add('deleting');
                    setTimeout(() => {
                      row.remove();
                      
                      // If no customers left, show empty state
                      if (document.querySelectorAll('tbody tr').length === 0) {
                        const tbody = document.querySelector('tbody');
                        tbody.innerHTML = `
                          <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                              <div class="flex flex-col items-center justify-center">
                                <i class="fas fa-users-slash text-3xl mb-3 text-gray-400 dark:text-gray-600"></i>
                                <p class="text-lg font-medium">No customers found</p>
                                <p class="text-sm">There are no registered customers in the system.</p>
                              </div>
                            </td>
                          </tr>
                        `;
                      }
                    }, 300);
                  }
                });
              } else {
                // Show error message
                showToast('Failed to delete customer', 'error');
              }
            })
            .catch(error => {
              console.error('Error deleting customer:', error);
              showToast('An error occurred while deleting the customer', 'error');
              deleteCustomerModal.classList.add('hidden');
            })
            .finally(() => {
              // Reset button state
              confirmDeleteBtn.disabled = false;
              confirmDeleteBtn.innerHTML = 'Yes, Delete';
            });
          });
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape' && !deleteCustomerModal.classList.contains('hidden')) {
            deleteCustomerModal.classList.add('hidden');
          }
        });
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


