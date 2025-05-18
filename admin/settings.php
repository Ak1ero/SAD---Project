<?php
session_start();

include '../db/config.php';
include '../db/system_settings.php';

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php"); // Redirect to login if not authenticated
  exit();
}

// Fetch the admin's username from the session
$username = $_SESSION['user_name'];

// Check if the form is submitted
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Update maintenance mode
  $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
  
  // Save settings to database
  if (update_setting($conn, 'maintenance_mode', $maintenance_mode)) {
    $success_message = 'Settings updated successfully!';
    
    // Log the action
    $action = $maintenance_mode === '1' ? 'enabled' : 'disabled';
    $log_message = "Admin {$username} {$action} maintenance mode";
    error_log($log_message);
  } else {
    $error_message = 'Failed to update settings. Please try again.';
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

// Get current maintenance mode setting
$current_maintenance_mode = is_maintenance_mode($conn);
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>The Barn & Backyard | Settings</title>
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
            'bounce-slow': 'bounce 2s infinite',
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
    
    /* Scrollbar for dark mode */
    .dark ::-webkit-scrollbar-thumb {
      background: #30363d;
    }

    /* Notification animation */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade-in {
      animation: fadeIn 0.3s ease-out forwards;
    }

    /* Modal Animation */
    .modal-appear {
      animation: modalAppear 0.3s forwards;
    }
    
    .modal-content-appear {
      animation: modalContentAppear 0.3s forwards;
    }
    
    .modal-disappear {
      animation: modalDisappear 0.3s forwards;
    }
    
    .modal-content-disappear {
      animation: modalContentDisappear 0.3s forwards;
    }
    
    @keyframes modalAppear {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    @keyframes modalContentAppear {
      from { opacity: 0; transform: scale(0.95) translateY(-20px); }
      to { opacity: 1; transform: scale(1) translateY(0); }
    }
    
    @keyframes modalDisappear {
      from { opacity: 1; }
      to { opacity: 0; }
    }
    
    @keyframes modalContentDisappear {
      from { opacity: 1; transform: scale(1) translateY(0); }
      to { opacity: 0; transform: scale(0.95) translateY(-20px); }
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
            <button id="sidebarCollapseBtn" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-smooth">
                <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
            </button>
        </div>

        <!-- Navigation Menu -->
        <nav class="p-4">
            <div class="space-y-2">
                <!-- Dashboard -->
                <a href="admindash.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50 transition-smooth">
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
                <a href="settings.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 transition-smooth">
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
                    <div class="relative inline-block w-10 h-6 transition duration-200 ease-in-out rounded-full bg-gray-200 dark:bg-slate-700" id="themeToggleIndicator">
                        <div class="absolute inset-y-0 left-0 w-6 h-6 transition duration-200 ease-in-out transform translate-x-0 dark:translate-x-4 bg-white dark:bg-primary-400 rounded-full shadow-md"></div>
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
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Settings</h2>
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
                    <span class="text-gray-700 dark:text-gray-200 font-medium"><?php echo ucfirst(htmlspecialchars($username)); ?></span>
                </div>
            </div>
        </div>
      </nav>

      <!-- Main Content Area -->
      <div class="p-6 bg-gray-50 dark:bg-slate-900 min-h-screen">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">Settings</h2>

        <!-- Settings Navigation Tabs -->
        <div class="mb-6 border-b border-gray-200 dark:border-slate-700">
          <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
            <li class="mr-2">
              <a href="#general" class="inline-block p-4 border-b-2 border-primary-500 text-primary-600 dark:text-primary-400 rounded-t-lg active" aria-current="page">
                General
              </a>
            </li>
          </ul>
        </div>

        <!-- General Settings -->
        <div id="general" class="settings-section active">
          <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6 mb-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">General Settings</h3>
            
            <?php if (!empty($success_message)): ?>
            <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-200 dark:text-green-800" role="alert">
              <span class="font-medium">Success!</span> <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
              <span class="font-medium">Error!</span> <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
              <div class="grid gap-6 mb-6 md:grid-cols-2">
                <div>
                  <label for="site_name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Site Name</label>
                  <input type="text" id="site_name" name="site_name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-slate-700 dark:border-slate-600 dark:placeholder-gray-400 dark:text-white" value="The Barn & Backyard" required>
                </div>
                <div>
                  <label for="contact_email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Contact Email</label>
                  <input type="email" id="contact_email" name="contact_email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-slate-700 dark:border-slate-600 dark:placeholder-gray-400 dark:text-white" value="contact@barnnbackyard.com" required>
                </div>
              </div>
              <div class="mb-6">
                <label for="site_description" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Site Description</label>
                <textarea id="site_description" name="site_description" rows="4" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-slate-700 dark:border-slate-600 dark:placeholder-gray-400 dark:text-white">Your premier event venue for weddings, corporate events, and special occasions.</textarea>
              </div>
              <div class="flex items-center mb-4">
                <input id="maintenance_mode" name="maintenance_mode" type="checkbox" value="1" class="w-4 h-4 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-slate-700 dark:border-slate-600" <?php echo $current_maintenance_mode ? 'checked' : ''; ?>>
                <label for="maintenance_mode" class="ml-2 text-sm font-medium text-gray-900 dark:text-white">Maintenance Mode</label>
              </div>
              <div class="flex items-center text-sm text-gray-500 dark:text-gray-400 mb-4 <?php echo $current_maintenance_mode ? 'block' : 'hidden'; ?>" id="maintenanceModeInfo">
                <i class="fas fa-info-circle mr-2 text-primary-500"></i>
                <p>When maintenance mode is enabled, only administrators can access the website. All other visitors will see a maintenance page.</p>
              </div>
              <button type="submit" class="text-white bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">Save Changes</button>
            </form>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Maintenance Mode Confirmation Modal -->
  <div id="maintenanceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
          <i class="fas fa-tools mr-2 text-yellow-500"></i>
          Enable Maintenance Mode
        </h3>
        <button id="closeMaintenanceModal" class="text-gray-400 hover:text-gray-500 focus:outline-none">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="mb-6">
        <div class="p-4 mb-4 text-sm text-yellow-700 bg-yellow-100 rounded-lg dark:bg-yellow-200 dark:text-yellow-800">
          <div class="flex">
            <i class="fas fa-exclamation-triangle mr-2 text-xl"></i>
            <div>
              <p class="font-bold mb-1">Warning!</p>
              <p>Enabling maintenance mode will make your website inaccessible to regular users.</p>
            </div>
          </div>
        </div>
        <p class="text-gray-600 dark:text-gray-300 mb-4">
          When maintenance mode is active:
        </p>
        <ul class="list-disc pl-5 space-y-2 text-gray-600 dark:text-gray-300">
          <li>All visitors will see a maintenance page instead of your website</li>
          <li>Only administrators will be able to access the site</li>
          <li>You'll need to disable maintenance mode when you're done with updates</li>
        </ul>
      </div>
      <div class="flex justify-end space-x-3">
        <button id="cancelMaintenanceMode" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 transition-smooth">
          Cancel
        </button>
        <button id="confirmMaintenanceMode" class="px-5 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-smooth">
          <i class="fas fa-tools mr-2"></i>
          Enable Maintenance Mode
        </button>
      </div>
    </div>
  </div>

  <!-- Disable Maintenance Mode Confirmation Modal -->
  <div id="disableMaintenanceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
          <i class="fas fa-check-circle mr-2 text-green-500"></i>
          Disable Maintenance Mode
        </h3>
        <button class="closeModal text-gray-400 hover:text-gray-500 focus:outline-none">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="mb-6">
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-200 dark:text-green-800">
          <div class="flex">
            <i class="fas fa-info-circle mr-2 text-xl"></i>
            <div>
              <p class="font-bold mb-1">Site will be live!</p>
              <p>Disabling maintenance mode will make your website accessible to everyone again.</p>
            </div>
          </div>
        </div>
        <p class="text-gray-600 dark:text-gray-300 mb-2">
          Are you sure your website is ready to go live?
        </p>
      </div>
      <div class="flex justify-end space-x-3">
        <button id="cancelDisableMaintenance" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 transition-smooth">
          Cancel
        </button>
        <button id="confirmDisableMaintenance" class="px-5 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-smooth">
          <i class="fas fa-globe mr-2"></i>
          Make Site Live
        </button>
      </div>
    </div>
  </div>

  <!-- JavaScript for sidebar and theme functionality -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
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

      // Maintenance mode checkbox toggle
      const maintenanceCheckbox = document.getElementById('maintenance_mode');
      const maintenanceInfo = document.getElementById('maintenanceModeInfo');
      const maintenanceModal = document.getElementById('maintenanceModal');
      const disableMaintenanceModal = document.getElementById('disableMaintenanceModal');
      
      // Function to show modal with animation
      function showModal(modal) {
        modal.classList.remove('hidden');
        modal.classList.add('modal-appear');
        const modalContent = modal.querySelector('.bg-white, .dark\\:bg-slate-800');
        modalContent.classList.add('modal-content-appear');
      }
      
      // Function to hide modal with animation
      function hideModal(modal) {
        modal.classList.add('modal-disappear');
        const modalContent = modal.querySelector('.bg-white, .dark\\:bg-slate-800');
        modalContent.classList.add('modal-content-disappear');
        
        // Remove the modal after animation completes
        setTimeout(() => {
          modal.classList.add('hidden');
          modal.classList.remove('modal-appear', 'modal-disappear');
          modalContent.classList.remove('modal-content-appear', 'modal-content-disappear');
        }, 300);
      }
      
      if (maintenanceCheckbox && maintenanceInfo) {
        maintenanceCheckbox.addEventListener('change', function(e) {
          // Prevent default checkbox behavior until confirmed
          e.preventDefault();
          
          if (!this.checked) {
            // Show disable maintenance mode modal
            showModal(disableMaintenanceModal);
          } else {
            // Show enable maintenance mode modal
            showModal(maintenanceModal);
          }
          
          // Revert checkbox state until confirmed
          this.checked = !this.checked;
        });
        
        // Confirm enable maintenance mode
        document.getElementById('confirmMaintenanceMode').addEventListener('click', function() {
          maintenanceCheckbox.checked = true;
          maintenanceInfo.classList.remove('hidden');
          hideModal(maintenanceModal);
        });
        
        // Cancel enable maintenance mode
        document.getElementById('cancelMaintenanceMode').addEventListener('click', function() {
          hideModal(maintenanceModal);
        });
        
        // Close button for enable modal
        document.getElementById('closeMaintenanceModal').addEventListener('click', function() {
          hideModal(maintenanceModal);
        });
        
        // Confirm disable maintenance mode
        document.getElementById('confirmDisableMaintenance').addEventListener('click', function() {
          maintenanceCheckbox.checked = false;
          maintenanceInfo.classList.add('hidden');
          hideModal(disableMaintenanceModal);
        });
        
        // Cancel disable maintenance mode
        document.getElementById('cancelDisableMaintenance').addEventListener('click', function() {
          hideModal(disableMaintenanceModal);
        });
        
        // Close button for disable modal
        document.querySelector('#disableMaintenanceModal .closeModal').addEventListener('click', function() {
          hideModal(disableMaintenanceModal);
        });
        
        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
          if (e.target === maintenanceModal) {
            hideModal(maintenanceModal);
          }
          if (e.target === disableMaintenanceModal) {
            hideModal(disableMaintenanceModal);
          }
        });
      }

      // Notification popup functionality
      const notificationBtn = document.getElementById('notificationBtn');
      const notificationPopup = document.getElementById('notificationPopup');
      let isPopupVisible = false;

      notificationBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          isPopupVisible = !isPopupVisible;
          
          if (isPopupVisible) {
            notificationPopup.classList.remove('hidden');
            // Add small delay to ensure the hidden class is removed before adding animation
            setTimeout(() => {
              notificationPopup.classList.add('animate-fade-in');
            }, 10);
          } else {
            notificationPopup.classList.add('hidden');
            notificationPopup.classList.remove('animate-fade-in');
          }
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
          if (isPopupVisible && !notificationPopup.contains(e.target)) {
              notificationPopup.classList.add('hidden');
              isPopupVisible = false;
          }
      });

      // Theme Toggle
      const themeToggle = document.getElementById('themeToggle');
      const html = document.documentElement;
      
      // Check for saved theme preference, otherwise use system preference
      if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          html.classList.add('dark');
      } else {
          html.classList.remove('dark');
      }
      
      themeToggle.addEventListener('click', function() {
          html.classList.toggle('dark');
          localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
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
    });
  </script>
</body>
</html> 