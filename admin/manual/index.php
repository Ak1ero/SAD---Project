<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id'])) {
  header("Location: ../../login.php");
  exit();
}

// Database connection
include '../../db/config.php';

// Fetch the admin's username from the session
$username = $_SESSION['user_name'];

// Fetch packages for dropdown
$packages = [];
$sql = "SELECT * FROM event_packages";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $packages[] = $row;
  }
}

// Fetch themes for dropdown
$themes = [];
$sql = "SELECT * FROM themes";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $themes[] = $row;
  }
}

// Fetch services for dropdown
$services = [];
$sql = "SELECT * FROM services";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $services[] = $row;
  }
}

// Fetch service items for each service
$serviceItems = [];
$sql = "SELECT * FROM service_items ORDER BY name ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    if (!isset($serviceItems[$row['service_id']])) {
      $serviceItems[$row['service_id']] = [];
    }
    $serviceItems[$row['service_id']][] = $row;
  }
}

// Get booked dates from bookings table
$bookedDates = [];
$sql = "SELECT DISTINCT event_date FROM bookings WHERE status != 'cancelled'";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $bookedDates[] = $row['event_date'];
  }
}

// Remove duplicates (redundant with DISTINCT in SQL, but kept for safety)
$bookedDates = array_unique($bookedDates);

// Organize themes by package
$themesByPackage = [];
foreach ($themes as $theme) {
  if (!empty($theme['packages'])) {
    $packageIds = explode(',', $theme['packages']);
    foreach ($packageIds as $packageId) {
      if (!isset($themesByPackage[$packageId])) {
        $themesByPackage[$packageId] = [];
      }
      $themesByPackage[$packageId][] = $theme;
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>The Barn & Backyard | Manual Reservation</title>
  <link rel="icon" href="../../img/barn-backyard.svg" type="image/svg+xml"/>
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
          }
        },
      },
    }
  </script>
  
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Flatpickr Date Picker -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  
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
    
    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 100;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.4);
    }
    
    .modal-content {
      background-color: #fefefe;
      margin: 10% auto;
      padding: 20px;
      border-radius: 12px;
      width: 80%;
      max-width: 800px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .dark .modal-content {
      background-color: #1e293b;
      color: #e2e8f0;
    }
    
    .modal-close {
      float: right;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
    }
    
    .service-item-card {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 12px;
      margin-bottom: 12px;
      transition: all 0.2s;
    }
    
    .dark .service-item-card {
      border-color: #334155;
    }
    
    .service-item-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .dark .service-item-card:hover {
      box-shadow: 0 4px 6px rgba(0,0,0,0.4);
    }
    
    /* Flatpickr Custom Styles */
    .flatpickr-day.flatpickr-disabled, 
    .flatpickr-day.flatpickr-disabled:hover,
    .flatpickr-day.booked,
    .flatpickr-day.booked:hover {
      background-color: #ffcdd2 !important;
      color: #d32f2f !important;
      text-decoration: line-through;
      font-weight: bold;
      border-color: transparent !important;
      cursor: not-allowed;
    }
    
    .flatpickr-day.today {
      border-color: #0ea5e9 !important;
    }
    
    .flatpickr-day.selected,
    .flatpickr-day.selected:hover {
      background-color: #0ea5e9 !important;
      border-color: #0ea5e9 !important;
    }
    
    .dark .flatpickr-calendar {
      background: #1e293b;
      box-shadow: 0 3px 13px rgba(0, 0, 0, 0.25);
      color: #e2e8f0;
    }
    
    .dark .flatpickr-months,
    .dark .flatpickr-weekdays {
      color: #e2e8f0;
    }
    
    .dark .flatpickr-month {
      background: #1e293b;
    }
    
    .dark .flatpickr-weekday {
      color: #94a3b8;
    }
    
    .dark .flatpickr-day {
      color: #e2e8f0;
    }
    
    .dark .flatpickr-day:hover {
      background: #334155;
    }
    
    .dark .flatpickr-day.today {
      border-color: #0ea5e9 !important;
    }
    
    .dark .flatpickr-day.flatpickr-disabled,
    .dark .flatpickr-day.flatpickr-disabled:hover,
    .dark .flatpickr-day.booked,
    .dark .flatpickr-day.booked:hover {
      background-color: #4a1e1b !important;
      color: #f87171 !important;
    }
    
    .date-legend {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      margin-top: 4px;
      font-size: 0.75rem;
    }
    
    .date-legend-item {
      display: flex;
      align-items: center;
      margin-left: 10px;
    }
    
    .date-legend-color {
      width: 12px;
      height: 12px;
      border-radius: 3px;
      margin-right: 4px;
    }
    
    .date-legend-available {
      background-color: #ffffff;
      border: 1px solid #e5e7eb;
    }
    
    .dark .date-legend-available {
      background-color: #1e293b;
      border: 1px solid #475569;
    }
    
    .date-legend-booked {
      background-color: #ffcdd2;
    }
    
    .dark .date-legend-booked {
      background-color: #4a1e1b;
    }
    
    /* Time Picker Styles */
    .time-range-container {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    
    .time-picker-wrapper {
      position: relative;
    }
    
    .time-picker-icon {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #6b7280;
      pointer-events: none;
    }
    
    .dark .time-picker-icon {
      color: #9ca3af;
    }
    
    .duration-display {
      margin-top: 0.5rem;
      font-size: 0.8rem;
      color: #6b7280;
      text-align: right;
    }
    
    .dark .duration-display {
      color: #9ca3af;
    }
    
    .flatpickr-time .numInputWrapper,
    .flatpickr-time .flatpickr-am-pm {
      height: auto;
    }
    
    .flatpickr-time input.flatpickr-hour,
    .flatpickr-time input.flatpickr-minute {
      font-size: 16px;
    }
    
    .dark .flatpickr-time {
      background: #1e293b;
      border-top: 1px solid #334155;
    }
    
    .dark .flatpickr-time input, 
    .dark .flatpickr-time .flatpickr-am-pm {
      color: #e2e8f0;
      background: #1e293b;
    }
    
    .dark .flatpickr-time .flatpickr-time-separator {
      color: #e2e8f0;
    }
    
    .dark .flatpickr-time input:hover,
    .dark .flatpickr-time .flatpickr-am-pm:hover {
      background: #334155;
    }
  </style>
</head>
<body class="bg-gray-50 dark:bg-slate-900 font-sans">
  <!-- Dashboard Container -->
  <div class="flex min-h-screen">
    <!-- Main Content -->
    <main class="flex-1">
      <!-- Top Navigation Bar -->
      <nav class="bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 px-6 py-3 
sticky top-0 z-20 shadow-sm">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="../admindash.php" class="p-2 rounded-lg hover:bg-gray-100 
dark:hover:bg-slate-700 transition-smooth">
                    <i class="fas fa-arrow-left text-gray-600 dark:text-gray-300"></i>
                </a>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Manual Reservation</h2>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Profile -->
                <div class="flex items-center space-x-3 p-2">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($username); ?>&background=6366F1&color=fff" 
                         alt="Profile" 
                         class="w-8 h-8 rounded-full">
                    <span class="text-gray-700 dark:text-gray-200 font-medium"><?php echo 
ucfirst(htmlspecialchars($username)); ?></span>
                </div>
            </div>
        </div>
      </nav>

      <!-- Main Content Area -->
      <div class="p-6 bg-gray-50 dark:bg-slate-900 min-h-screen">
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-primary-600 to-indigo-600 rounded-xl shadow-lg mb-6 
overflow-hidden">
            <div class="relative p-8">
                <div class="relative z-10">
                    <h2 class="text-2xl font-bold text-white mb-2">Manual Reservation</h2>
                    <p class="text-primary-100">Create and manage direct cash</p>
                </div>
                <!-- Decorative Elements -->
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full 
blur-2xl"></div>
                <div class="absolute bottom-0 left-0 -mb-4 -ml-4 w-32 h-32 bg-white/10 rounded-full 
blur-2xl"></div>
            </div>
        </div>

        <!-- Manual Reservation Form -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Create New Manual Reservation</h3>
          
          <form id="manualReservationForm" method="POST" action="process_reservation.php">
            <!-- Customer Information -->
            <div class="mb-6">
              <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
                <i class="fas fa-user-circle mr-2 text-primary-500"></i>Customer Information
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                  <label for="customerName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
                  <input type="text" id="customerName" name="customerName" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white" required>
                </div>
                <div>
                  <label for="customerEmail" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                  <input type="email" id="customerEmail" name="customerEmail" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white" required>
                </div>
                <div>
                  <label for="customerPhone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                  <input type="tel" id="customerPhone" name="customerPhone" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white" required>
                </div>
                <div>
                  <label for="customerAddress" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                  <input type="text" id="customerAddress" name="customerAddress" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white">
                </div>
              </div>
            </div>
            
            <!-- Event Details -->
            <div class="mb-6">
              <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
                <i class="fas fa-calendar-alt mr-2 text-primary-500"></i>Event Details
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                  <label for="eventDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Event Date</label>
                  <input type="text" id="eventDate" name="eventDate" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white" placeholder="Select a date" required>
                  <div class="date-legend">
                    <div class="date-legend-item">
                      <div class="date-legend-color date-legend-available"></div>
                      <span class="text-gray-500 dark:text-gray-400">Available</span>
                    </div>
                    <div class="date-legend-item">
                      <div class="date-legend-color date-legend-booked"></div>
                      <span class="text-gray-500 dark:text-gray-400">Booked</span>
                    </div>
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Event Time</label>
                  <div class="time-range-container">
                    <div class="time-picker-wrapper">
                      <input type="text" id="eventStartTime" name="eventTime" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white" placeholder="Start Time" required>
                      <div class="time-picker-icon">
                        <i class="fas fa-clock"></i>
                      </div>
                    </div>
                    <div class="time-picker-wrapper">
                      <input type="text" id="eventEndTime" name="eventEndTime" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white" placeholder="End Time" required>
                      <div class="time-picker-icon">
                        <i class="fas fa-clock"></i>
                      </div>
                    </div>
                  </div>
                  <div class="duration-display" id="durationDisplay">Event duration: <span id="durationHours">0</span> hours</div>
                </div>
                <div>
                  <label for="packageId" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Package</label>
                  <select id="packageId" name="packageName" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white" required>
                    <option value="">Select Package</option>
                    <?php foreach($packages as $package): ?>
                      <option value="<?php echo htmlspecialchars($package['name']); ?>" data-capacity="<?php echo $package['guest_capacity'] ?? ''; ?>"><?php echo htmlspecialchars($package['name']); ?> - ₱<?php echo number_format($package['price'], 2); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label for="themeId" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Theme</label>
                  <select id="themeId" name="themeName" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white">
                    <option value="">Select Theme (Optional)</option>
                    <?php foreach($themes as $theme): ?>
                      <option value="<?php echo htmlspecialchars($theme['name']); ?>" data-packages="<?php echo $theme['packages']; ?>" class="theme-option"><?php echo htmlspecialchars($theme['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label for="guestCount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Number of Guests</label>
                  <input type="number" id="guestCount" name="guestCount" min="1" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white" required>
                </div>
              </div>
            </div>
            
            <!-- Additional Services -->
            <div class="mb-6">
              <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
                <i class="fas fa-concierge-bell mr-2 text-primary-500"></i>Additional Services
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-3">
                <?php foreach($services as $service): ?>
                  <div class="flex items-start p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <input type="checkbox" id="service_<?php echo $service['id']; ?>" name="services[]" value="<?php echo htmlspecialchars($service['name']); ?>" class="mt-1 mr-3">
                    <label for="service_<?php echo $service['id']; ?>" class="text-sm font-medium text-gray-700 dark:text-gray-300 flex-1 cursor-pointer service-details-btn" data-service-id="<?php echo $service['id']; ?>">
                      <?php echo htmlspecialchars($service['name']); ?>
                      <span class="ml-1 text-xs text-primary-600 dark:text-primary-400">
                        <i class="fas fa-info-circle"></i> View options
                      </span>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            
            <!-- Payment Information -->
            <div class="mb-6 p-4 bg-gray-50 dark:bg-slate-700 rounded-lg">
              <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
                <i class="fas fa-money-bill-wave mr-2 text-primary-500"></i>Payment Information
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="paymentMethod" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Method</label>
                  <input type="text" value="Cash" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-gray-100 dark:bg-slate-600 text-gray-700 dark:text-gray-300" readonly>
                  <input type="hidden" id="paymentMethod" name="paymentMethod" value="cash">
                </div>
                <div>
                  <label for="amountPaid" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount Paid (₱)</label>
                  <input type="number" id="amountPaid" name="amountPaid" min="0" step="0.01" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white" required>
                </div>
              </div>
            </div>
            
            <!-- Form Buttons -->
            <div class="flex justify-end space-x-3">
              <a href="admin/manual/index.php" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-smooth">
                Cancel
              </a>
              <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-smooth">
                <i class="fas fa-save mr-2"></i>Create Reservation
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Notifications -->
      <div id="notification" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg transform translate-y-[-100px] opacity-0 transition-all duration-300 z-50 flex items-center hidden">
        <i class="fas fa-check-circle mr-2 text-xl"></i>
        <span id="notificationMessage" class="text-base font-medium"></span>
      </div>
      
      <!-- Service Items Modal -->
      <div id="serviceItemsModal" class="modal">
        <div class="modal-content">
          <span class="modal-close">&times;</span>
          <h3 id="serviceModalTitle" class="text-xl font-semibold mb-4 text-gray-800 dark:text-white"></h3>
          <div id="serviceItemsContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[60vh] overflow-y-auto p-2">
            <!-- Service items will be loaded here -->
          </div>
          <div class="mt-4 flex justify-end">
            <button type="button" id="confirmServiceItems" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-smooth">
              <i class="fas fa-check mr-2"></i>Confirm Selection
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('manualReservationForm');
      const packageSelect = document.getElementById('packageId');
      const themeSelect = document.getElementById('themeId');
      const amountPaidInput = document.getElementById('amountPaid');
      const guestCountInput = document.getElementById('guestCount');
      const eventDateInput = document.getElementById('eventDate');
      const themeOptions = document.querySelectorAll('.theme-option');
      const serviceDetailsButtons = document.querySelectorAll('.service-details-btn');
      const modal = document.getElementById('serviceItemsModal');
      const modalClose = document.querySelector('.modal-close');
      const serviceModalTitle = document.getElementById('serviceModalTitle');
      const serviceItemsContainer = document.getElementById('serviceItemsContainer');
      const confirmServiceItemsBtn = document.getElementById('confirmServiceItems');
      
      // Store booked dates from the server
      const bookedDates = <?php echo json_encode($bookedDates); ?>;
      
      // Initialize Flatpickr date picker with booked dates disabled
      const datePicker = flatpickr(eventDateInput, {
        minDate: "today",
        dateFormat: "Y-m-d",
        disable: bookedDates,
        inline: false,
        onChange: function(selectedDates, dateStr, instance) {
          // Additional logic when date changes (if needed)
        },
        onDayCreate: function(dObj, dStr, fp, dayElem) {
          // Add special class to booked dates for custom styling
          // Convert the date to YYYY-MM-DD format for comparison
          const dateStr = dayElem.dateObj.toISOString().split('T')[0];
          // Only mark exact matches as booked
          if (bookedDates.includes(dateStr)) {
            dayElem.classList.add('booked');
          }
        }
      });
      
      // Set amount paid based on package selection
      packageSelect.addEventListener('change', function() {
        if (this.value) {
          const selectedOption = this.options[this.selectedIndex];
          const packagePrice = parseFloat(selectedOption.textContent.split('₱')[1].replace(',', ''));
          
          // Update amount paid based on package price and any selected services
          updateTotalAmount();
          
          // Set guest capacity if available
          const guestCapacity = selectedOption.getAttribute('data-capacity');
          if (guestCapacity) {
            guestCountInput.value = guestCapacity;
          }
          
          // Get the original package ID for theme filtering
          // Find the package ID by the name we selected
          const selectedPackageName = this.value;
          let selectedPackageId = null;
          
          <?php foreach($packages as $package): ?>
          if ("<?php echo htmlspecialchars($package['name']); ?>" === selectedPackageName) {
            selectedPackageId = "<?php echo $package['id']; ?>";
          }
          <?php endforeach; ?>
          
          // Filter themes based on selected package ID
          if (selectedPackageId) {
            filterThemesByPackage(selectedPackageId);
          } else {
            // No matching package ID found
            filterThemesByPackage('');
          }
        } else {
          amountPaidInput.value = '';
          guestCountInput.value = '';
          // Reset theme filter
          filterThemesByPackage('');
        }
      });
      
      // Filter themes function
      function filterThemesByPackage(packageId) {
        // First reset the theme select
        themeSelect.value = '';
        
        // Show all options first (except the first placeholder option)
        Array.from(themeSelect.options).forEach((option, index) => {
          if (index === 0) return; // Skip the default option
          option.style.display = '';
        });
        
        // If no package is selected, show all themes and return
        if (!packageId) return;
        
        // Now hide options that don't match the package
        Array.from(themeSelect.options).forEach((option, index) => {
          if (index === 0) return; // Skip the default option
          
          const packages = option.getAttribute('data-packages');
          if (!packages) {
            option.style.display = 'none';
          } else {
            const packageIds = packages.split(',');
            if (!packageIds.includes(packageId)) {
              option.style.display = 'none';
            }
          }
        });
      }
      
      // Store selected service items
      let selectedServiceItems = {};
      let currentServiceId = null;
      
      // Open service details modal
      serviceDetailsButtons.forEach(button => {
        button.addEventListener('click', function(event) {
          // Prevent checkbox toggle when clicking on the label
          if (!event.target.matches('input[type="checkbox"]')) {
            event.preventDefault();
          }
          
          const serviceId = this.getAttribute('data-service-id');
          currentServiceId = serviceId;
          const serviceName = this.textContent.trim().replace('View options', '').trim();
          
          // Set modal title
          serviceModalTitle.textContent = serviceName + ' Options';
          
          // Clear previous items
          serviceItemsContainer.innerHTML = '';
          
          // Load service items
          loadServiceItems(serviceId);
          
          // Show modal
          modal.style.display = 'block';
        });
      });
      
      // Load service items function
      function loadServiceItems(serviceId) {
        // This could be an AJAX call to fetch items from the server
        // For now, we'll use the PHP data we prepared
        const serviceItems = <?php echo json_encode($serviceItems); ?>;
        
        if (serviceItems[serviceId] && serviceItems[serviceId].length > 0) {
          serviceItems[serviceId].forEach(item => {
            const itemCard = document.createElement('div');
            itemCard.className = 'service-item-card';
            
            // Check if this item is already selected
            const isChecked = selectedServiceItems[serviceId] && 
                             selectedServiceItems[serviceId].some(si => si.id === item.id);
            
            let imageHtml = '';
            if (item.image_path && item.image_path.trim() !== '') {
              imageHtml = `<img src="../../uploads/service_items/${item.image_path}" alt="${item.name}" class="w-full h-40 object-cover rounded-md mb-2">`;
            } else {
              imageHtml = `<div class="w-full h-40 bg-gray-200 dark:bg-slate-700 flex items-center justify-center rounded-md mb-2">
                            <i class="fas fa-image text-gray-400 text-3xl"></i>
                           </div>`;
            }
            
            itemCard.innerHTML = `
              <div class="flex items-start mb-2">
                <input type="checkbox" id="service_item_${item.id}" 
                       class="service-item-checkbox mt-1 mr-2" 
                       data-item-id="${item.id}" 
                       data-item-name="${item.name}"
                       ${isChecked ? 'checked' : ''}>
                <label for="service_item_${item.id}" class="font-semibold text-gray-800 dark:text-white cursor-pointer flex-1">
                  ${item.name}
                </label>
              </div>
              ${imageHtml}
              <p class="text-sm text-gray-600 dark:text-gray-300">${item.price_range || ''}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                ${item.phone ? `<i class="fas fa-phone mr-1"></i>${item.phone}<br>` : ''}
                ${item.email ? `<i class="fas fa-envelope mr-1"></i>${item.email}` : ''}
              </p>
            `;
            
            serviceItemsContainer.appendChild(itemCard);
            
            // Add event listener to the checkbox
            const checkbox = itemCard.querySelector(`#service_item_${item.id}`);
            checkbox.addEventListener('change', function() {
              handleServiceItemSelection(serviceId, item, this.checked);
            });
            
            // Check if the image loaded correctly, show placeholder if not
            const img = itemCard.querySelector('img');
            if (img) {
              img.onerror = function() {
                this.onerror = null;
                this.parentNode.innerHTML = `<div class="w-full h-40 bg-gray-200 dark:bg-slate-700 flex items-center justify-center rounded-md mb-2">
                                              <i class="fas fa-image text-gray-400 text-3xl"></i>
                                              <p class="text-xs text-gray-500 mt-1">Image not found</p>
                                            </div>`;
              };
            }
          });
        } else {
          serviceItemsContainer.innerHTML = '<p class="text-gray-500 dark:text-gray-400 col-span-2 text-center py-8">No service items available</p>';
        }
      }
      
      // Handle service item selection
      function handleServiceItemSelection(serviceId, item, isSelected) {
        if (!selectedServiceItems[serviceId]) {
          selectedServiceItems[serviceId] = [];
        }
        
        if (isSelected) {
          // Add item to selected items if not already present
          if (!selectedServiceItems[serviceId].some(si => si.id === item.id)) {
            selectedServiceItems[serviceId].push({
              id: item.id,
              name: item.name
            });
          }
        } else {
          // Remove item from selected items
          selectedServiceItems[serviceId] = selectedServiceItems[serviceId].filter(si => si.id !== item.id);
        }
        
        // Update service label to show selection count
        updateServiceLabel(serviceId);
      }
      
      // Update service label to show selection count
      function updateServiceLabel(serviceId) {
        const serviceLabel = document.querySelector(`label[data-service-id="${serviceId}"]`);
        const selectedCount = selectedServiceItems[serviceId]?.length || 0;
        
        // Get the original service name without any badges
        let serviceName = serviceLabel.textContent.replace(/\(\d+ selected\)/, '').replace('View options', '').trim();
        
        // Update the label text
        if (selectedCount > 0) {
          serviceLabel.innerHTML = `${serviceName} <span class="ml-1 text-xs bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300 px-2 py-0.5 rounded-full">(${selectedCount} selected)</span> <span class="ml-1 text-xs text-primary-600 dark:text-primary-400"><i class="fas fa-info-circle"></i> View options</span>`;
        } else {
          serviceLabel.innerHTML = `${serviceName} <span class="ml-1 text-xs text-primary-600 dark:text-primary-400"><i class="fas fa-info-circle"></i> View options</span>`;
        }
        
        // Check the service checkbox if there are selections
        const serviceCheckbox = document.querySelector(`#service_${serviceId}`);
        if (serviceCheckbox) {
          serviceCheckbox.checked = selectedCount > 0;
        }
      }
      
      // Confirm service item selection
      confirmServiceItemsBtn.addEventListener('click', function() {
        if (currentServiceId) {
          updateServiceLabel(currentServiceId);
        }
        
        // Create hidden inputs for selected service items to be submitted with the form
        const container = document.getElementById('selectedServiceItemsContainer');
        if (!container) {
          const hiddenContainer = document.createElement('div');
          hiddenContainer.id = 'selectedServiceItemsContainer';
          hiddenContainer.style.display = 'none';
          document.getElementById('manualReservationForm').appendChild(hiddenContainer);
        }
        
        // Clear previous hidden inputs
        document.getElementById('selectedServiceItemsContainer').innerHTML = '';
        
        // Add hidden inputs for all selected service items
        Object.keys(selectedServiceItems).forEach(serviceId => {
          if (selectedServiceItems[serviceId].length > 0) {
            selectedServiceItems[serviceId].forEach(item => {
              const hiddenInput = document.createElement('input');
              hiddenInput.type = 'hidden';
              hiddenInput.name = 'service_items[]';
              hiddenInput.value = item.id;
              document.getElementById('selectedServiceItemsContainer').appendChild(hiddenInput);
            });
          }
        });
        
        // Update total amount to include service costs
        updateTotalAmount();
        
        // Close the modal
        modal.style.display = 'none';
        
        // Show confirmation notification
        showNotification('Service items selected successfully!');
      });
      
      // Function to update the total amount based on selected services
      function updateTotalAmount() {
        // Get base package price
        let totalAmount = 0;
        if (packageSelect.value) {
          const selectedOption = packageSelect.options[packageSelect.selectedIndex];
          totalAmount = parseFloat(selectedOption.textContent.split('₱')[1].replace(',', ''));
        }
        
        // Get all service items data
        const serviceItems = <?php echo json_encode($serviceItems); ?>;
        
        // Add service costs
        Object.keys(selectedServiceItems).forEach(serviceId => {
          if (selectedServiceItems[serviceId].length > 0) {
            // Loop through each selected service item to get its price
            let serviceTotalPrice = 0;
            
            selectedServiceItems[serviceId].forEach(selectedItem => {
              // Find the item in our data
              if (serviceItems[serviceId]) {
                const item = serviceItems[serviceId].find(i => i.id === selectedItem.id);
                if (item && item.price_range) {
                  // Parse price from price_range
                  const itemPrice = parseFloat(item.price_range.replace(/[^\d.]/g, ''));
                  if (!isNaN(itemPrice)) {
                    serviceTotalPrice += itemPrice;
                  } else {
                    // Fallback to default price
                    serviceTotalPrice += 15000;
                  }
                } else {
                  // Fallback to default price
                  serviceTotalPrice += 15000;
                }
              } else {
                // Fallback to default price for this service
                serviceTotalPrice += 15000;
              }
            });
            
            // Add the total price for this service to the total amount
            totalAmount += serviceTotalPrice;
          }
        });
        
        // Update amount paid field with the total
        amountPaidInput.value = totalAmount.toFixed(2);
      }
      
      // Close modal
      modalClose.addEventListener('click', function() {
        modal.style.display = 'none';
      });
      
      // Close modal when clicking outside
      window.addEventListener('click', function(event) {
        if (event.target === modal) {
          modal.style.display = 'none';
        }
      });
      
      // Notification function
      function showNotification(message, type = 'success') {
        const notification = document.getElementById('notification');
        const notificationMessage = document.getElementById('notificationMessage');
        
        notification.classList.remove('hidden', 'bg-green-500', 'bg-red-500');
        notification.classList.add(type === 'success' ? 'bg-green-500' : 'bg-red-500');
        
        notificationMessage.textContent = message;
        
        // Show notification
        notification.classList.remove('translate-y-[-100px]', 'opacity-0');
        notification.classList.add('translate-y-0', 'opacity-100');
        
        // Hide after longer time for success messages (5 seconds) vs errors (3 seconds)
        setTimeout(() => {
          notification.classList.add('translate-y-[-100px]', 'opacity-0');
          notification.classList.remove('translate-y-0', 'opacity-100');
        }, type === 'success' ? 5000 : 3000);
      }
      
      // Show notification if there's a URL parameter
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('success')) {
        const bookingRef = urlParams.get('booking_reference') || '';
        const successMsg = bookingRef ? `Reservation created successfully! Reference: ${bookingRef}` : 'Reservation created successfully!';
        showNotification(successMsg);
        
        // Reset form after successful submission
        form.reset();
        
        // Reset any selected service items
        selectedServiceItems = {};
        
        // Reset service labels
        document.querySelectorAll('.service-details-btn').forEach(label => {
          const serviceId = label.getAttribute('data-service-id');
          const serviceName = label.textContent.replace(/\(\d+ selected\)/, '').replace('View options', '').trim();
          label.innerHTML = `${serviceName} <span class="ml-1 text-xs text-primary-600 dark:text-primary-400"><i class="fas fa-info-circle"></i> View options</span>`;
        });
        
        // Reset checkboxes
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
          checkbox.checked = false;
        });
      } else if (urlParams.has('error')) {
        showNotification(urlParams.get('error'), 'error');
      }
      
      // Initialize time pickers
      const startTimePicker = flatpickr("#eventStartTime", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "h:i K",
        time_24hr: false,
        minuteIncrement: 30,
        defaultHour: 9,
        defaultMinute: 0,
        onChange: calculateDuration
      });
      
      const endTimePicker = flatpickr("#eventEndTime", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "h:i K",
        time_24hr: false,
        minuteIncrement: 30,
        defaultHour: 17,
        defaultMinute: 0,
        onChange: calculateDuration
      });
      
      // Calculate duration between start and end time
      function calculateDuration() {
        const startTimeVal = document.getElementById('eventStartTime').value;
        const endTimeVal = document.getElementById('eventEndTime').value;
        
        if (startTimeVal && endTimeVal) {
          // Create date objects for today with the selected times
          const today = new Date();
          const startDate = new Date(today.toDateString() + ' ' + startTimeVal);
          let endDate = new Date(today.toDateString() + ' ' + endTimeVal);
          
          // If end time is earlier than start time, assume it's still the same day but spans overnight
          // We're not going to modify the actual date since we're only booking for a single day
          let isOvernight = false;
          if (endDate < startDate) {
            // Clone the end date before modification for duration calculation
            const tempEndDate = new Date(endDate);
            tempEndDate.setDate(tempEndDate.getDate() + 1);
            // Calculate difference in hours
            const diffHours = (tempEndDate - startDate) / (1000 * 60 * 60);
            document.getElementById('durationHours').textContent = diffHours.toFixed(1);
            isOvernight = true;
          } else {
            // Normal case - end time is after start time on the same day
            const diffHours = (endDate - startDate) / (1000 * 60 * 60);
            document.getElementById('durationHours').textContent = diffHours.toFixed(1);
          }
          
          // Validate time range - only show error if not overnight
          if (!isOvernight && endDate <= startDate) {
            showNotification('End time must be after start time', 'error');
          }
        }
      }
      
      // Initialize duration calculation on page load
      calculateDuration();
      
      // Handle form submission
      form.addEventListener('submit', function(event) {
        const startTimeVal = document.getElementById('eventStartTime').value;
        const endTimeVal = document.getElementById('eventEndTime').value;
        
        if (startTimeVal && endTimeVal) {
          // Create date objects for today with the selected times
          const today = new Date();
          const startDate = new Date(today.toDateString() + ' ' + startTimeVal);
          const endDate = new Date(today.toDateString() + ' ' + endTimeVal);
          
          // Allow overnight events (end time before start time)
          if (endDate < startDate) {
            // This is valid - event spans overnight
            return; // Allow form submission
          }
          
          // For same-day events, ensure end time is after start time
          if (endDate <= startDate) {
            event.preventDefault();
            showNotification('End time must be after start time', 'error');
          }
        }
      });
    });
  </script>
</body>
</html> 