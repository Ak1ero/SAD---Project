<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include '../db/config.php';
// Include the event status update script
include_once '../db/update_event_status.php';

// Check if notification has been read
$notificationsRead = isset($_SESSION['notifications_read']) ? $_SESSION['notifications_read'] : false;

// Handle marking notifications as read via AJAX
if (isset($_POST['mark_read']) && $_POST['mark_read'] == 1) {
    $_SESSION['notifications_read'] = true;
    echo json_encode(['success' => true]);
    exit;
}

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

// Initialize filter variables
$search = isset($_GET['search']) ? $_GET['search'] : '';
$package_filter = isset($_GET['package']) ? $_GET['package'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Pagination settings
$items_per_page = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Build the WHERE clause based on filters
$where_conditions = ["1=1"]; // Always true condition to start

if ($search) {
    $search = $conn->real_escape_string($search);
    $where_conditions[] = "(b.booking_reference LIKE '%$search%' 
                          OR u.name LIKE '%$search%' 
                          OR b.package_name LIKE '%$search%')";
}

if ($package_filter) {
    $package_filter = $conn->real_escape_string($package_filter);
    $where_conditions[] = "b.package_name = '$package_filter'";
}

if ($status_filter) {
    $status_filter = $conn->real_escape_string($status_filter);
    if ($status_filter === 'confirmed') {
        $where_conditions[] = "(b.status = 'confirmed' AND b.payment_status != 'paid' AND b.payment_status != 'partially_paid')";
    } elseif ($status_filter === 'paid') {
        $where_conditions[] = "(b.payment_status = 'paid' AND b.status != 'completed')";
    } elseif ($status_filter === 'partially_paid') {
        $where_conditions[] = "(b.payment_status = 'partially_paid')";
    } elseif ($status_filter === 'completed') {
        $where_conditions[] = "(b.status = 'completed')";
    } else {
        $where_conditions[] = "b.status = '$status_filter'";
    }
}

if ($date_filter) {
    $date_filter = $conn->real_escape_string($date_filter);
    $where_conditions[] = "DATE(b.event_date) = '$date_filter'";
}

// Combine WHERE conditions
$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM bookings b 
              LEFT JOIN users u ON b.user_id = u.id 
              $where_clause";
$count_result = $conn->query($count_sql);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $items_per_page);

// Main query with filters and pagination
$sql = "SELECT b.*, 
        b.status as booking_status,
        u.name as customer_name,
        u.email as customer_email,
        (SELECT COUNT(*) FROM guests bg WHERE bg.booking_id = b.id) as guest_count,
        (SELECT COALESCE(SUM(
            CASE 
                WHEN bs.service_price > 0 THEN bs.service_price
                ELSE (
                    SELECT CAST(REPLACE(REPLACE(si.price_range, '₱', ''), ',', '') AS DECIMAL(10,2))
                    FROM service_items si
                    JOIN services s ON si.service_id = s.id
                    WHERE LOWER(s.name) = LOWER(bs.service_name)
                    LIMIT 1
                )
            END
        ), 0) FROM booking_services bs WHERE bs.booking_id = b.id) as services_total,
        b.total_amount,
        b.event_start_time,
        b.event_end_time,
        b.payment_status
        FROM bookings b 
        LEFT JOIN users u ON b.user_id = u.id 
        $where_clause
        ORDER BY b.created_at DESC
        LIMIT $offset, $items_per_page";

$result = $conn->query($sql);

// Fetch the admin's username from the session
$username = $_SESSION['user_name'];
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Barn & Backyard | Reservations Management</title>
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
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .dark .stat-card:hover {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
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

        /* Additional Custom Styles */
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
        
        .status-partially_paid {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .status-paid {
            background-color: rgba(147, 51, 234, 0.1);
            color: #8b5cf6;
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

        .dark .status-partially_paid {
            background-color: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }

        .payment-paid {
            background-color: #d1fae5;
            color: #065f46;
        }

        .dark .payment-paid {
            background-color: rgba(6, 95, 70, 0.3);
            color: #6ee7b7;
        }

        .payment-unpaid {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .dark .payment-unpaid {
            background-color: rgba(146, 64, 14, 0.3);
            color: #fcd34d;
        }
        
        /* Capitalization styles */
        .capitalize {
            text-transform: capitalize;
        }
        
        .custom-theme-name {
            text-transform: capitalize;
        }
        
        /* Ensure all service names are capitalized */
        #servicesList p:first-child {
            text-transform: capitalize;
        }
        
        /* Confirmation Modal Styles */
        .confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .confirmation-container {
            background-color: white;
            border-radius: 1rem;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            padding: 2rem;
            position: relative;
            transform: translateY(20px);
            animation: slideUp 0.3s ease forwards;
        }

        .dark .confirmation-container {
            background-color: #161b22;
            border: 1px solid #30363d;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .confirmation-icon {
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }

        .icon-confirm {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .dark .icon-confirm {
            background-color: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }

        .icon-cancel {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .dark .icon-cancel {
            background-color: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .confirmation-title {
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 0.75rem;
        }
        
        .dark .confirmation-title {
            color: #f0f6fc;
        }

        .confirmation-message {
            text-align: center;
            color: #6b7280;
            margin-bottom: 2rem;
        }
        
        .dark .confirmation-message {
            color: #8b949e;
        }

        .confirmation-booking-ref {
            font-weight: 600;
            color: #6366f1;
        }
        
        .dark .confirmation-booking-ref {
            color: #818cf8;
        }

        .confirmation-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .confirmation-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            min-width: 120px;
        }

        .btn-cancel {
            background-color: #f3f4f6;
            color: #1f2937;
        }
        
        .dark .btn-cancel {
            background-color: #21262d;
            color: #c9d1d9;
        }

        .btn-cancel:hover {
            background-color: #e5e7eb;
        }
        
        .dark .btn-cancel:hover {
            background-color: #30363d;
        }

        .btn-confirm-action {
            color: white;
        }

        .btn-confirm-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-confirm-yes {
            background-color: #10b981;
        }

        .btn-confirm-yes:hover {
            background-color: #059669;
        }

        .btn-cancel-yes {
            background-color: #ef4444;
        }

        .btn-cancel-yes:hover {
            background-color: #dc2626;
        }
        
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

        /* Utility classes for smooth transitions */
        .transition-smooth {
            transition: all 0.2s ease-in-out;
        }
        
        .transition-opacity {
            transition: opacity 0.3s ease-in-out;
        }
        
        .transition-all {
            transition: all 0.3s ease-in-out;
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
                    <a href="reservations.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400">
                        <i class="far fa-calendar-check w-5 h-5"></i>
                        <span class="font-medium">Manage Reservations</span>
                    </a>

                    <!-- Events -->
                    <a href="events.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700/50">
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
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Reservations Management</h2>
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
                            <h2 class="text-2xl font-bold text-white mb-2">Manage Reservations</h2>
                            <p class="text-primary-100">View, filter, and manage all your event bookings in one place.</p>
                        </div>
                        <!-- Decorative Elements -->
                        <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
                        <div class="absolute bottom-0 left-0 -mb-4 -ml-4 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="bg-white dark:bg-slate-800 p-5 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 mb-6">
                    <form action="" method="GET" class="md:flex items-center gap-3 mb-4">
                        <div class="relative flex-1 mb-3 md:mb-0">
                            <input type="text" name="search" placeholder="Search by reference, customer or event type..." value="<?php echo htmlspecialchars($search); ?>" class="w-full pl-10 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-primary-400 focus:border-primary-400 dark:bg-slate-700 dark:text-white">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400 dark:text-gray-500"></i>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <select name="package" class="px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-primary-400 focus:border-primary-400 dark:bg-slate-700 dark:text-white">
                                <option value="">All Packages</option>
                                <?php
                                // Fetch package names
                                $packageSql = "SELECT DISTINCT package_name FROM bookings ORDER BY package_name ASC";
                                $packageResult = $conn->query($packageSql);
                                if ($packageResult && $packageResult->num_rows > 0) {
                                    while($packageRow = $packageResult->fetch_assoc()) {
                                        $selected = ($package_filter == $packageRow['package_name']) ? 'selected' : '';
                                        echo "<option value=\"" . htmlspecialchars($packageRow['package_name']) . "\" $selected>" . 
                                              ucwords(htmlspecialchars($packageRow['package_name'])) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                            
                            <select name="status" class="px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-primary-400 focus:border-primary-400 dark:bg-slate-700 dark:text-white">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="partially_paid" <?php echo $status_filter === 'partially_paid' ? 'selected' : ''; ?>>Partially Paid</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            
                            <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" class="px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-primary-400 focus:border-primary-400 dark:bg-slate-700 dark:text-white">
                            
                            <button type="submit" class="px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg flex items-center justify-center transition-smooth">
                                <i class="fas fa-filter mr-2"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Reservations Table -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-slate-700/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Booking Ref</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Event Package</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while($booking = $result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/25 transition-smooth">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($booking['booking_reference']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                <?php echo htmlspecialchars($booking['customer_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                <?php echo ucwords(strtolower(htmlspecialchars($booking['package_name']))); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                <?php 
                                                echo date('M d, Y', strtotime($booking['event_date'])) . '<br>' .
                                                     '<span class="text-xs text-gray-500 dark:text-gray-400">' . 
                                                     date('h:i A', strtotime($booking['event_start_time'])) . ' - ' . 
                                                     date('h:i A', strtotime($booking['event_end_time'])) . 
                                                     '</span>';
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs rounded-full 
                                                    <?php
                                                    if ($booking['booking_status'] === 'cancelled') {
                                                        echo 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
                                                    } elseif ($booking['booking_status'] === 'completed') {
                                                        echo 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300';
                                                    } elseif ($booking['booking_status'] === 'confirmed' && $booking['payment_status'] === 'partially_paid') {
                                                        // Check if this is a cash payment
                                                        $cashQuery = "SELECT payment_method FROM payment_transactions WHERE booking_id = {$booking['id']} ORDER BY created_at DESC LIMIT 1";
                                                        $cashResult = $conn->query($cashQuery);
                                                        $isCashPayment = false;
                                                        
                                                        if ($cashResult && $cashResult->num_rows > 0) {
                                                            $paymentData = $cashResult->fetch_assoc();
                                                            $isCashPayment = (strtolower($paymentData['payment_method']) === 'cash');
                                                        }
                                                        
                                                        if ($isCashPayment) {
                                                            echo 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300';
                                                        } else {
                                                            echo 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300';
                                                        }
                                                    } elseif ($booking['booking_status'] === 'confirmed' && $booking['payment_status'] !== 'paid') {
                                                        echo 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300';
                                                    } elseif (($booking['booking_status'] === 'confirmed' || $booking['booking_status'] === 'pending') && $booking['payment_status'] === 'paid') {
                                                        echo 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300';
                                                    } else {
                                                        echo 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300';
                                                    }
                                                    ?>">
                                                    <?php 
                                                    if ($booking['booking_status'] === 'cancelled') {
                                                        echo '<i class="fas fa-ban mr-1"></i> Cancelled';
                                                    } elseif ($booking['booking_status'] === 'completed') {
                                                        echo '<i class="fas fa-flag-checkered mr-1" style="color: #0e9f6e;"></i> Completed';
                                                    } elseif ($booking['booking_status'] === 'confirmed' && $booking['payment_status'] === 'partially_paid') {
                                                        // Check if this is a cash payment
                                                        $cashQuery = "SELECT payment_method FROM payment_transactions WHERE booking_id = {$booking['id']} ORDER BY created_at DESC LIMIT 1";
                                                        $cashResult = $conn->query($cashQuery);
                                                        $isCashPayment = false;
                                                        
                                                        if ($cashResult && $cashResult->num_rows > 0) {
                                                            $paymentData = $cashResult->fetch_assoc();
                                                            $isCashPayment = (strtolower($paymentData['payment_method']) === 'cash');
                                                        }
                                                        
                                                        if ($isCashPayment) {
                                                            echo '<i class="fas fa-credit-card mr-1"></i> Paid (Cash)';
                                                        } else {
                                                            echo '<i class="fas fa-percentage mr-1" style="color: #3b82f6;"></i> Partially Paid';
                                                        }
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
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-700 dark:text-gray-300">
                                                ₱<?php 
                                                $total = $booking['total_amount'] + $booking['services_total'];
                                                echo number_format($total, 2, '.', ','); 
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button onclick="viewBooking('<?php echo $booking['id']; ?>')" 
                                                        class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 mr-3 transition-smooth">
                                                    View
                                                </button>
                                                <?php if ($booking['booking_status'] === 'pending'): ?>
                                                    <button onclick="updateBookingStatus('<?php echo $booking['id']; ?>', 'confirmed')" 
                                                            class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 mr-3 transition-smooth">
                                                        Confirm
                                                    </button>
                                                    <button onclick="updateBookingStatus('<?php echo $booking['id']; ?>', 'cancelled')" 
                                                            class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-smooth">
                                                        Cancel
                                                    </button>
                                                <?php elseif ($booking['payment_status'] === 'partially_paid' && $booking['booking_status'] !== 'completed'): ?>
                                                    <button onclick="showPaymentStatusModal('<?php echo $booking['id']; ?>', '<?php echo $booking['booking_reference']; ?>')" 
                                                            class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-smooth">
                                                        Update Status
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No bookings found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="px-6 py-3 bg-white dark:bg-slate-800 border-t border-gray-200 dark:border-slate-700">
                        <div class="flex flex-col sm:flex-row items-center justify-between">
                            <div class="text-sm text-gray-700 dark:text-gray-300 mb-4 sm:mb-0">
                                Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                                <span class="font-medium"><?php echo min($offset + $items_per_page, $total_records); ?></span> of 
                                <span class="font-medium"><?php echo $total_records; ?></span> results
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <!-- Mobile Pagination -->
                                    <div class="sm:hidden flex space-x-2">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?php echo ($page-1); ?>&search=<?php echo urlencode($search); ?>&package=<?php echo urlencode($package_filter); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>" 
                                               class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-md hover:bg-gray-50 dark:hover:bg-slate-600 transition-smooth">
                                                Previous
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($page < $total_pages): ?>
                                            <a href="?page=<?php echo ($page+1); ?>&search=<?php echo urlencode($search); ?>&package=<?php echo urlencode($package_filter); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>" 
                                               class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-md hover:bg-gray-50 dark:hover:bg-slate-600 transition-smooth">
                                                Next
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Desktop Pagination -->
                                    <div class="hidden sm:flex">
                                        <!-- Previous Button -->
                                        <a href="<?php echo $page > 1 ? '?page='.($page-1).'&search='.urlencode($search).'&package='.urlencode($package_filter).'&status='.urlencode($status_filter).'&date='.urlencode($date_filter) : '#'; ?>" 
                                           class="<?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?> relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-600 transition-smooth">
                                            <span class="sr-only">Previous</span>
                                            <i class="fas fa-chevron-left"></i>
                                        </a>

                                        <!-- Page Numbers -->
                                        <?php
                                        // Calculate range of pages to show
                                        $start_page = max(1, min($page - 1, $total_pages - 2));
                                        $end_page = min($total_pages, max(3, $page + 1));
                                        
                                        // Ensure we always show at least 3 pages if available
                                        if ($end_page - $start_page + 1 < 3) {
                                            if ($start_page == 1) {
                                                $end_page = min($total_pages, 3);
                                            } else {
                                                $start_page = max(1, $total_pages - 2);
                                            }
                                        }

                                        // Display page numbers
                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&package=<?php echo urlencode($package_filter); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>" 
                                               class="<?php echo $i === $page ? 'z-10 bg-primary-50 dark:bg-primary-900/20 border-primary-500 dark:border-primary-500 text-primary-600 dark:text-primary-400' : 'bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-600'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium transition-smooth">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <!-- Next Button -->
                                        <a href="<?php echo $page < $total_pages ? '?page='.($page+1).'&search='.urlencode($search).'&package='.urlencode($package_filter).'&status='.urlencode($status_filter).'&date='.urlencode($date_filter) : '#'; ?>" 
                                           class="<?php echo $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?> relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-600 transition-smooth">
                                            <span class="sr-only">Next</span>
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </div>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Reservation Details Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden backdrop-blur-sm">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto transform scale-100 transition-all duration-300">
                <!-- Modal Header -->
                <div class="p-6 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center sticky top-0 bg-white dark:bg-slate-800 z-10">
                    <h3 class="text-xl font-semibold text-gray-800 dark:text-white">Reservation Details</h3>
                    <button onclick="closeModal()" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition-smooth">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Modal Content -->
                <div class="p-6 bg-white dark:bg-slate-800">
                    <!-- Content will be loaded here -->
                </div>
                
                <!-- Modal Footer -->
                <div class="p-6 border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 flex justify-end sticky bottom-0">
                    <button onclick="closeModal()" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 dark:bg-primary-600 dark:hover:bg-primary-700 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal for Booking Actions -->
    <div id="confirmationModal" class="confirmation-modal">
        <div class="confirmation-container">
            <div id="confirmationIcon" class="confirmation-icon">
                <i id="confirmationIconSymbol" class="fas fa-check-circle"></i>
            </div>
            <h3 id="confirmationTitle" class="confirmation-title">Confirm Reservation</h3>
            <p id="confirmationMessage" class="confirmation-message">
                Are you sure you want to confirm reservation <span id="confirmationRef" class="confirmation-booking-ref"></span>?
            </p>
            <div class="confirmation-actions">
                <button id="cancelActionBtn" class="confirmation-btn btn-cancel">No, Cancel</button>
                <button id="confirmActionBtn" class="confirmation-btn btn-confirm-action btn-confirm-yes">Yes, Confirm</button>
            </div>
        </div>
    </div>

    <!-- Modal Content Template -->
    <template id="modalContentTemplate">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white dark:bg-slate-800 p-4">
            <!-- Booking Information -->
            <div class="bg-gray-50 dark:bg-slate-700 p-5 rounded-lg shadow-sm">
                <h4 class="text-lg font-semibold text-indigo-800 dark:text-indigo-300 border-b border-gray-200 dark:border-slate-600 pb-2 mb-4">Booking Information</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Booking Reference</p>
                        <p class="font-medium text-gray-900 dark:text-white" id="bookingRef"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Package Name</p>
                        <p class="font-medium text-gray-900 dark:text-white capitalize" id="packageName"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Event Date</p>
                        <p class="font-medium text-gray-900 dark:text-white" id="eventDate"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Event Time</p>
                        <p class="font-medium text-gray-900 dark:text-white" id="eventTime"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Guest Count</p>
                        <p class="font-medium text-gray-900 dark:text-white" id="guestCount"></p>
                    </div>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="bg-gray-50 dark:bg-slate-700 p-5 rounded-lg shadow-sm">
                <h4 class="text-lg font-semibold text-indigo-800 dark:text-indigo-300 border-b border-gray-200 dark:border-slate-600 pb-2 mb-4">Customer Information</h4>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Name</p>
                        <p class="font-medium text-gray-900 dark:text-white" id="customerName"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Email</p>
                        <p class="font-medium text-gray-900 dark:text-white" id="customerEmail"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Phone Number</p>
                        <p class="font-medium text-gray-900 dark:text-white" id="customerPhone"></p>
                    </div>
                </div>
            </div>

            <!-- Theme Information -->
            <div class="bg-gray-50 dark:bg-slate-700 p-5 rounded-lg shadow-sm">
                <h4 class="text-lg font-semibold text-indigo-800 dark:text-indigo-300 border-b border-gray-200 dark:border-slate-600 pb-2 mb-4">Theme Information</h4>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Selected Theme</p>
                    <p class="font-medium text-gray-900 dark:text-white capitalize" id="themeName"></p>
                </div>
                <div id="customThemeDetails" class="hidden mt-3 bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
                    <p class="text-sm text-gray-800 dark:text-gray-200 mb-1">Custom Theme Details</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400" id="customThemeDescription"></p>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="bg-gray-50 dark:bg-slate-700 p-5 rounded-lg shadow-sm">
                <h4 class="text-lg font-semibold text-indigo-800 dark:text-indigo-300 border-b border-gray-200 dark:border-slate-600 pb-2 mb-4">Payment Information</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Base Amount</p>
                        <p class="font-medium text-gray-900 dark:text-white" id="baseAmount"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Services Total</p>
                        <p class="font-medium text-gray-900 dark:text-white" id="servicesTotal"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total Amount</p>
                        <p class="font-medium text-lg text-indigo-600 dark:text-indigo-400" id="totalAmount"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Paid Amount</p>
                        <p class="font-medium text-green-600 dark:text-green-400" id="paidAmount"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Remaining Balance</p>
                        <p class="font-medium text-red-600 dark:text-red-400" id="remainingBalance"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Payment Method</p>
                        <p class="font-medium text-gray-900 dark:text-white capitalize" id="paymentMethod"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Payment Status</p>
                        <p class="font-medium" id="paymentStatus"></p>
                    </div>
                </div>
                
                <!-- Payment Receipt Section -->
                <div id="receiptSection" class="mt-4 pt-4 border-t border-gray-200 dark:border-slate-600 hidden">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Payment Receipt</p>
                    <div class="flex flex-col items-center">
                        <a id="viewReceiptBtn" href="#" target="_blank" class="inline-flex items-center px-4 py-2 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-lg hover:bg-primary-200 dark:hover:bg-primary-900/50 transition-all">
                            <i class="fas fa-receipt mr-2"></i> View Receipt
                        </a>
                        <img id="receiptThumbnail" src="" alt="Receipt Thumbnail" class="hidden mt-3 max-w-full max-h-40 rounded-lg border border-gray-200 dark:border-slate-600 cursor-pointer shadow-sm hover:shadow-md transition-shadow" onclick="window.open(this.src, '_blank')">
                    </div>
                </div>
            </div>

            <!-- Additional Services -->
            <div class="bg-gray-50 dark:bg-slate-700 p-5 rounded-lg shadow-sm md:col-span-2">
                <h4 class="text-lg font-semibold text-indigo-800 dark:text-indigo-300 border-b border-gray-200 dark:border-slate-600 pb-2 mb-4">Additional Services</h4>
                <div id="servicesList" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <!-- Services will be inserted here -->
                </div>
            </div>
        </div>
    </template>

    <!-- Payment Status Update Modal -->
    <div id="paymentStatusModal" class="confirmation-modal">
        <div class="confirmation-container">
            <div class="confirmation-icon icon-confirm">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <h3 class="confirmation-title">Update Payment Status</h3>
            <p class="confirmation-message">
                Are you sure you want to mark booking <span id="paymentStatusRef" class="confirmation-booking-ref"></span> as fully paid?
            </p>
            <div class="confirmation-actions">
                <button id="cancelPaymentUpdateBtn" class="confirmation-btn btn-cancel">No, Cancel</button>
                <button id="confirmPaymentUpdateBtn" class="confirmation-btn btn-confirm-action btn-confirm-yes">Yes, Mark as Paid</button>
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
                    // Ensure transition class is applied before toggling
                    sidebar.classList.add('transition-all', 'duration-300');
                    
                    // Use requestAnimationFrame to ensure styles are applied before visual changes
                    requestAnimationFrame(() => {
                        sidebar.classList.toggle('translate-x-0');
                        sidebar.classList.toggle('-translate-x-full');
                        
                        // Add overlay for mobile menu with fade-in transition
                        let overlay = document.getElementById('sidebarOverlay');
                        if (!overlay) {
                            overlay = document.createElement('div');
                            overlay.id = 'sidebarOverlay';
                            overlay.className = 'fixed inset-0 bg-black bg-opacity-0 z-20 md:hidden transition-all duration-300';
                            document.body.appendChild(overlay);
                            
                            // Trigger reflow before applying opacity for smooth transition
                            overlay.getBoundingClientRect();
                            overlay.classList.add('bg-opacity-50');
                            
                            overlay.addEventListener('click', function() {
                                sidebar.classList.remove('translate-x-0');
                                sidebar.classList.add('-translate-x-full');
                                document.body.classList.remove('overflow-hidden');
                                
                                // Fade out overlay before removing
                                overlay.classList.remove('bg-opacity-50');
                                overlay.classList.add('bg-opacity-0');
                                
                                setTimeout(() => {
                                    overlay.remove();
                                }, 300);
                            });
                            
                            document.body.classList.add('overflow-hidden');
                        } else {
                            // Fade out overlay before removing
                            overlay.classList.remove('bg-opacity-50');
                            overlay.classList.add('bg-opacity-0');
                            
                            setTimeout(() => {
                                overlay.remove();
                                document.body.classList.remove('overflow-hidden');
                            }, 300);
                        }
                    });
                });
            }

            // Sidebar Collapse
            const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
            const mainContent = document.getElementById('mainContent');
            let isSidebarCollapsed = false;

            if (sidebarCollapseBtn) {
                sidebarCollapseBtn.addEventListener('click', function() {
                    // Apply transition class before making changes
                    sidebar.classList.add('transition-all');
                    mainContent.classList.add('transition-all');
                    
                    // Use requestAnimationFrame to ensure styles are applied before changes
                    requestAnimationFrame(() => {
                        isSidebarCollapsed = !isSidebarCollapsed;
                        sidebar.style.width = isSidebarCollapsed ? '5rem' : '16rem';
                        mainContent.style.marginLeft = isSidebarCollapsed ? '5rem' : '16rem';
                        
                        // Hide text in sidebar when collapsed with transition
                        const sidebarTexts = sidebar.querySelectorAll('span:not(.sr-only)');
                        sidebarTexts.forEach(text => {
                            text.classList.add('transition-opacity');
                            text.style.opacity = isSidebarCollapsed ? '0' : '1';
                            setTimeout(() => {
                                text.style.display = isSidebarCollapsed ? 'none' : 'inline';
                            }, isSidebarCollapsed ? 300 : 0);
                        });
                        
                        // Hide/show theme toggle indicator
                        const themeToggleIndicator = document.getElementById('themeToggleIndicator');
                        if (themeToggleIndicator) {
                            themeToggleIndicator.classList.add('transition-opacity');
                            themeToggleIndicator.style.opacity = isSidebarCollapsed ? '0' : '1';
                            setTimeout(() => {
                                themeToggleIndicator.style.display = isSidebarCollapsed ? 'none' : 'block';
                            }, isSidebarCollapsed ? 300 : 0);
                        }
                    });
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
                        fetch('reservations.php', {
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
            }

            // Filter form functionality
            const filterForm = document.getElementById('filterForm');
            const formInputs = filterForm.querySelectorAll('input, select');

            formInputs.forEach(input => {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            });

            // Update search input handling
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function(e) {
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        filterForm.submit();
                    }, 500);
                });
            }
        });

        // Modal functionality
        function viewBooking(bookingId) {
            // Show loading state
            document.getElementById('viewModal').classList.remove('hidden');
            const modalContent = document.querySelector('#viewModal .p-6:not(.border-t):not(.border-b)');
            modalContent.innerHTML = `
                <div class="flex items-center justify-center h-32">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 dark:border-indigo-400"></div>
                </div>
            `;

            // Fetch booking details using AJAX
            fetch(`get-booking-details.php?id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }

                    // Reset modal content using the template
                    const template = document.getElementById('modalContentTemplate');
                    modalContent.innerHTML = template.innerHTML;

                    // Populate modal with booking details
                    document.getElementById('bookingRef').textContent = data.booking_reference || 'N/A';
                    document.getElementById('packageName').textContent = data.package_name || 'N/A';
                    document.getElementById('eventDate').textContent = formatDate(data.event_date);
                    document.getElementById('eventTime').textContent = `${formatTime(data.event_start_time)} - ${formatTime(data.event_end_time)}`;
                    document.getElementById('themeName').textContent = data.theme_name || 'N/A';
                    document.getElementById('guestCount').textContent = data.guest_count || '0';
                    
                    document.getElementById('customerName').textContent = data.customer_name || 'N/A';
                    document.getElementById('customerEmail').textContent = data.customer_email || 'N/A';
                    document.getElementById('customerPhone').textContent = data.customer_phone || 'N/A';
                    
                    // Handle payment method display
                    const paymentMethodElement = document.getElementById('paymentMethod');
                    const paymentMethodValue = data.payment_method || 'Pending';
                    paymentMethodElement.textContent = paymentMethodValue;
                    if (paymentMethodValue === 'Pending') {
                        paymentMethodElement.className = 'font-medium text-yellow-600 dark:text-yellow-400 capitalize';
                    } else {
                        paymentMethodElement.className = 'font-medium text-gray-900 dark:text-white capitalize';
                    }

                    // Handle payment amounts
                    document.getElementById('baseAmount').textContent = `₱${formatNumber(data.base_amount || 0)}`;
                    document.getElementById('servicesTotal').textContent = `₱${formatNumber(data.services_total || 0)}`;
                    document.getElementById('totalAmount').textContent = `₱${formatNumber(data.total_amount || 0)}`;
                    document.getElementById('paidAmount').textContent = `₱${formatNumber(data.paid_amount || 0)}`;
                    document.getElementById('remainingBalance').textContent = `₱${formatNumber(data.remaining_balance || 0)}`;
                    
                    // Set payment status with appropriate styling
                    const paymentStatusElement = document.getElementById('paymentStatus');
                    const paymentStatus = data.payment_status || 'pending';
                    const paymentMethod = data.payment_method || '';
                    
                    // Always show 'Paid' for cash payments, regardless of actual status
                    let displayStatus = paymentStatus;
                    if (paymentMethod.toLowerCase() === 'cash' && paymentStatus === 'partially_paid') {
                        displayStatus = 'paid';
                    }
                    
                    paymentStatusElement.textContent = capitalizeFirstLetter(displayStatus);
                    paymentStatusElement.className = getPaymentStatusClass(displayStatus);

                    // Add payment status indicator to remaining balance
                    const remainingBalanceElement = document.getElementById('remainingBalance');
                    if (data.remaining_balance === 0 && data.paid_amount > 0) {
                        remainingBalanceElement.className = 'font-medium text-green-600 dark:text-green-400';
                    } else {
                        remainingBalanceElement.className = 'font-medium text-red-600 dark:text-red-400';
                    }
                    
                    // For cash payment, ensure remaining balance is shown as 0
                    if (paymentMethod.toLowerCase() === 'cash') {
                        remainingBalanceElement.textContent = '₱0.00';
                        remainingBalanceElement.className = 'font-medium text-green-600 dark:text-green-400';
                        
                        // Also ensure paid amount shows the total
                        document.getElementById('paidAmount').textContent = document.getElementById('totalAmount').textContent;
                    }

                    // Handle receipt display
                    const receiptSection = document.getElementById('receiptSection');
                    const viewReceiptBtn = document.getElementById('viewReceiptBtn');
                    const receiptThumbnail = document.getElementById('receiptThumbnail');
                    
                    if (data.receipt_path) {
                        receiptSection.classList.remove('hidden');
                        viewReceiptBtn.href = data.receipt_path;
                        viewReceiptBtn.innerHTML = '<i class="fas fa-receipt mr-2"></i> View Receipt';
                        
                        // Reset button styles
                        viewReceiptBtn.classList.remove('bg-gray-100', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'cursor-default');
                        viewReceiptBtn.classList.add('bg-primary-100', 'dark:bg-primary-900/30', 'text-primary-700', 'dark:text-primary-300');
                        viewReceiptBtn.onclick = null; // Remove any previous onclick handler
                        
                        // Show thumbnail preview for image file types
                        const fileExtension = data.receipt_path.split('.').pop().toLowerCase();
                        const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        
                        if (imageExtensions.includes(fileExtension)) {
                            receiptThumbnail.src = data.receipt_path;
                            receiptThumbnail.classList.remove('hidden');
                        } else {
                            receiptThumbnail.classList.add('hidden');
                        }
                    } else if (data.payment_status === 'paid' || data.payment_status === 'partially_paid') {
                        // Payment made but no receipt uploaded (likely cash payment)
                        receiptSection.classList.remove('hidden');
                        viewReceiptBtn.href = "#";
                        viewReceiptBtn.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i> No receipt available';
                        viewReceiptBtn.classList.remove('bg-primary-100', 'dark:bg-primary-900/30', 'text-primary-700', 'dark:text-primary-300');
                        viewReceiptBtn.classList.add('bg-gray-100', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'cursor-default');
                        viewReceiptBtn.onclick = function(e) { e.preventDefault(); };
                        receiptThumbnail.classList.add('hidden');
                    } else {
                        receiptSection.classList.add('hidden');
                    }

                    // Populate services list
                    const servicesList = document.getElementById('servicesList');
                    servicesList.innerHTML = ''; // Clear existing services
                    if (data.services && data.services.length > 0) {
                        data.services.forEach(service => {
                            servicesList.innerHTML += `
                                <div class="p-4 bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-600 rounded-lg shadow-sm">
                                    <div class="flex justify-between items-center">
                                        <p class="font-medium text-gray-800 dark:text-white capitalize">${service.service_name || 'Unnamed Service'}</p>
                                        <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">₱${formatNumber(service.price || 0)}</p>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        servicesList.innerHTML = '<p class="text-gray-500 dark:text-gray-400">No additional services</p>';
                    }

                    // Handle custom theme details
                    const customThemeDetails = document.getElementById('customThemeDetails');
                    const customThemeDescription = document.getElementById('customThemeDescription');
                    
                    if (data.is_custom_theme && data.custom_theme_description) {
                        customThemeDetails.classList.remove('hidden');
                        customThemeDescription.textContent = data.custom_theme_description;
                    } else {
                        customThemeDetails.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalContent.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                            <p class="text-gray-800 dark:text-white font-medium">Failed to load booking details</p>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mt-2">${error.message || 'An unexpected error occurred'}</p>
                            <button onclick="closeModal()" class="mt-4 px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-800 dark:text-white rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600">Close</button>
                        </div>
                    `;
                });
        }

        function closeModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        function capitalizeFirstLetter(string) {
            if (!string) return '';
            // Split the string by spaces and capitalize each word
            return string.split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                .join(' ');
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function formatTime(timeString) {
            if (!timeString) return 'N/A';
            try {
                return new Date(`2000-01-01T${timeString}`).toLocaleTimeString('en-PH', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
            } catch (e) {
                return timeString;
            }
        }

        function formatNumber(number) {
            return Number(number).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function getPaymentStatusClass(status) {
            switch(status) {
                case 'paid':
                    return 'text-green-600 dark:text-green-400 font-semibold';
                case 'partially_paid':
                    return 'text-blue-600 dark:text-blue-400 font-semibold';
                case 'pending':
                    return 'text-yellow-600 dark:text-yellow-400 font-semibold';
                case 'cancelled':
                    return 'text-red-600 dark:text-red-400 font-semibold';
                default:
                    return 'text-gray-600 dark:text-gray-400 font-semibold';
            }
        }

        // Close modal when clicking outside
        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Status update functionality
        function updateBookingStatus(bookingId, status) {
            // Get the booking reference from the row
            const row = document.querySelector(`button[onclick*="${bookingId}"]`).closest('tr');
            const bookingRef = row.querySelector('td:first-child').innerText.trim();
            
            // Show confirmation modal instead of browser confirm
            showConfirmationModal(bookingId, bookingRef, status);
        }

        // Confirmation modal functions
        function showConfirmationModal(bookingId, bookingRef, status) {
            const modal = document.getElementById('confirmationModal');
            const confirmationRef = document.getElementById('confirmationRef');
            const confirmationTitle = document.getElementById('confirmationTitle');
            const confirmationMessage = document.getElementById('confirmationMessage');
            const confirmationIcon = document.getElementById('confirmationIcon');
            const confirmationIconSymbol = document.getElementById('confirmationIconSymbol');
            const confirmActionBtn = document.getElementById('confirmActionBtn');
            
            // Set booking reference
            confirmationRef.textContent = bookingRef;
            
            // Configure modal based on action type
            if (status === 'confirmed') {
                confirmationTitle.textContent = 'Confirm Reservation';
                confirmationMessage.innerHTML = `Are you sure you want to confirm reservation <span id="confirmationRef" class="confirmation-booking-ref">${bookingRef}</span>?`;
                confirmationIcon.className = 'confirmation-icon icon-confirm';
                confirmationIconSymbol.className = 'fas fa-check-circle';
                confirmActionBtn.className = 'confirmation-btn btn-confirm-action btn-confirm-yes';
                confirmActionBtn.textContent = 'Yes, Confirm';
            } else if (status === 'cancelled') {
                confirmationTitle.textContent = 'Cancel Reservation';
                confirmationMessage.innerHTML = `Are you sure you want to cancel reservation <span id="confirmationRef" class="confirmation-booking-ref">${bookingRef}</span>? This action cannot be undone.`;
                confirmationIcon.className = 'confirmation-icon icon-cancel';
                confirmationIconSymbol.className = 'fas fa-times-circle';
                confirmActionBtn.className = 'confirmation-btn btn-confirm-action btn-cancel-yes';
                confirmActionBtn.textContent = 'Yes, Cancel';
            }
            
            // Store bookingId and status for later use
            modal.dataset.bookingId = bookingId;
            modal.dataset.status = status;
            
            // Show modal
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Set up event handlers
            document.getElementById('cancelActionBtn').onclick = hideConfirmationModal;
            document.getElementById('confirmActionBtn').onclick = proceedWithUpdate;
            
            // Close when clicking outside
            modal.onclick = function(event) {
                if (event.target === modal) {
                    hideConfirmationModal();
                }
            };
        }
        
        function hideConfirmationModal() {
            const modal = document.getElementById('confirmationModal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
        
        function proceedWithUpdate() {
            const modal = document.getElementById('confirmationModal');
            const bookingId = modal.dataset.bookingId;
            const status = modal.dataset.status;
            
            // Hide modal
            hideConfirmationModal();
            
            // Find the status cell and store original content
            const row = document.querySelector(`button[onclick*="${bookingId}"]`).closest('tr');
            const statusCell = row.querySelector('td:nth-child(5) span');
            const actionsCell = row.querySelector('td:last-child');
            const originalStatus = statusCell.innerHTML;
            const originalActions = actionsCell.innerHTML;

            // Show loading state
            statusCell.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            // Send request to update status
            fetch('update-booking-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=${bookingId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update status display
                    if (status === 'confirmed') {
                        statusCell.className = 'px-2 py-1 text-xs rounded-full bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300';
                        statusCell.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Confirmed';
                    } else if (status === 'cancelled') {
                        statusCell.className = 'px-2 py-1 text-xs rounded-full bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
                        statusCell.innerHTML = '<i class="fas fa-ban mr-1"></i> Cancelled';
                    }

                    // Update actions cell to only show View button
                    actionsCell.innerHTML = `
                        <button onclick="viewBooking('${bookingId}')" 
                                class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 mr-3 transition-smooth">
                            View
                        </button>`;

                    // Show success notification
                    showSuccessNotification(data.message);
                    
                    // If SMS was sent, show an SMS icon in the notification
                    if (status === 'confirmed') {
                        if (data.sms_sent) {
                            // Add a small animation to the row to highlight the SMS sent
                            row.classList.add('bg-green-50', 'dark:bg-green-900/10');
                            setTimeout(() => {
                                row.classList.remove('bg-green-50', 'dark:bg-green-900/10');
                            }, 3000);
                        } else if (data.sms_error) {
                            // Show SMS error notification
                            showErrorNotification('SMS Notification Issue', data.sms_error);
                        }
                        
                        // Handle email invitation results
                        if (data.email_sent) {
                            // Show email success notification
                            showSuccessNotification(`Email invitations were sent to guests.`);
                            // Add a visual indicator to the row
                            const emailIndicator = document.createElement('span');
                            emailIndicator.className = 'ml-2 px-2 py-1 text-xs rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 animate-pulse';
                            emailIndicator.innerHTML = '<i class="fas fa-envelope mr-1"></i> Invitations Sent';
                            
                            // Add it next to the status
                            statusCell.after(emailIndicator);
                            
                            // Stop the animation after a few seconds
                            setTimeout(() => {
                                emailIndicator.classList.remove('animate-pulse');
                            }, 5000);
                        } else if (data.email_error) {
                            // Show email error notification
                            showErrorNotification('Email Invitation Issue', data.email_error);
                        }
                    } else if (status === 'cancelled') {
                        if (data.sms_sent) {
                            // Add a small animation to the row to highlight the SMS sent
                            row.classList.add('bg-red-50', 'dark:bg-red-900/10');
                            setTimeout(() => {
                                row.classList.remove('bg-red-50', 'dark:bg-red-900/10');
                            }, 3000);
                        } else if (data.sms_error) {
                            // Show SMS error notification
                            showErrorNotification('SMS Notification Issue', data.sms_error);
                        }
                    }
                } else {
                    // Restore original content on error
                    statusCell.innerHTML = originalStatus;
                    actionsCell.innerHTML = originalActions;
                    showErrorNotification('Update Failed', data.message || 'Failed to update booking status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Restore original content on error
                statusCell.innerHTML = originalStatus;
                actionsCell.innerHTML = originalActions;
                showErrorNotification('System Error', 'An error occurred while updating the booking status');
            });
        }
        
        function showSuccessNotification(message) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300 px-4 py-3 rounded-lg shadow-lg z-50 animate-fade-in flex items-center';
            
            // Check if the message contains "SMS notification"
            const hasSmsNotification = message.includes('SMS notification sent');
            
            // Check if it's a cancellation message
            const isCancellation = message.toLowerCase().includes('cancelled');
            
            notification.innerHTML = `
                <i class="fas fa-${hasSmsNotification ? 'sms' : (isCancellation ? 'ban' : 'check-circle')} mr-2"></i>
                <span>${message}</span>
            `;
            
            // Add to DOM
            document.body.appendChild(notification);
            
            // Remove after 5 seconds (extended from 3 for SMS notifications)
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-10px)';
                notification.style.transition = 'opacity 0.5s, transform 0.5s';
                
                setTimeout(() => {
                    notification.remove();
                }, 500);
            }, hasSmsNotification ? 5000 : 3000);
        }
        
        function showErrorNotification(title, message) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 px-4 py-3 rounded-lg shadow-lg z-50 animate-fade-in flex flex-col';
            
            notification.innerHTML = `
                <div class="flex items-center mb-1">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span class="font-semibold">${title}</span>
                </div>
                <p class="text-sm pl-6">${message}</p>
            `;
            
            // Add to DOM
            document.body.appendChild(notification);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-10px)';
                notification.style.transition = 'opacity 0.5s, transform 0.5s';
                
                setTimeout(() => {
                    notification.remove();
                }, 500);
            }, 5000);
        }

        // Payment Status Update Modal Functions
        function showPaymentStatusModal(bookingId, bookingRef) {
            const modal = document.getElementById('paymentStatusModal');
            const paymentStatusRef = document.getElementById('paymentStatusRef');
            
            // Set booking reference
            paymentStatusRef.textContent = bookingRef;
            
            // Store bookingId for later use
            modal.dataset.bookingId = bookingId;
            
            // Show modal
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Set up event handlers
            document.getElementById('cancelPaymentUpdateBtn').onclick = hidePaymentStatusModal;
            document.getElementById('confirmPaymentUpdateBtn').onclick = updatePaymentStatus;
            
            // Close when clicking outside
            modal.onclick = function(event) {
                if (event.target === modal) {
                    hidePaymentStatusModal();
                }
            };
        }
        
        function hidePaymentStatusModal() {
            const modal = document.getElementById('paymentStatusModal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
        
        function updatePaymentStatus() {
            const modal = document.getElementById('paymentStatusModal');
            const bookingId = modal.dataset.bookingId;
            
            // Hide modal
            hidePaymentStatusModal();
            
            // Find the status cell and store original content
            const row = document.querySelector(`button[onclick*="showPaymentStatusModal('${bookingId}'"]`).closest('tr');
            const statusCell = row.querySelector('td:nth-child(5) span');
            const actionsCell = row.querySelector('td:last-child');
            const originalStatus = statusCell.innerHTML;
            const originalActions = actionsCell.innerHTML;

            // Show loading state
            statusCell.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            // Send request to update payment status
            fetch('update-payment-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=${bookingId}&status=paid`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update status display
                    statusCell.className = 'px-2 py-1 text-xs rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300';
                    statusCell.innerHTML = '<i class="fas fa-credit-card mr-1"></i> Paid';

                    // Update actions cell to only show View button
                    actionsCell.innerHTML = `
                        <button onclick="viewBooking('${bookingId}')" 
                                class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 mr-3 transition-smooth">
                            View
                        </button>`;

                    // Show success notification
                    showSuccessNotification('Payment status updated successfully');
                } else {
                    // Restore original content on error
                    statusCell.innerHTML = originalStatus;
                    actionsCell.innerHTML = originalActions;
                    showErrorNotification('Update Failed', data.message || 'Failed to update payment status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Restore original content on error
                statusCell.innerHTML = originalStatus;
                actionsCell.innerHTML = originalActions;
                showErrorNotification('System Error', 'An error occurred while updating the payment status');
            });
        }
    </script>

    <!-- Success message from URL parameters -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we have a success parameter in the URL
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const message = urlParams.get('message');
            
            if (success === '1' && message) {
                // Show success notification
                showSuccessNotification(decodeURIComponent(message));
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>

