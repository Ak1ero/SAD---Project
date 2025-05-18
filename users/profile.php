<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

include '../db/config.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch user information
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Redirect if user data not found
if (!$user) {
    $_SESSION['error'] = "User data not found. Please log in again.";
    header('Location: ../logout.php');
    exit();
}

// Get booking statistics
$bookingsQuery = $conn->prepare("SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings
    FROM bookings WHERE user_id = ?");
$bookingsQuery->bind_param("i", $user_id);
$bookingsQuery->execute();
$bookingStats = $bookingsQuery->get_result()->fetch_assoc();

// Initialize booking stats if they don't exist
if (!$bookingStats) {
    $bookingStats = [
        'total_bookings' => 0,
        'confirmed_bookings' => 0,
        'completed_bookings' => 0
    ];
}

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Get form data
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $birthday = trim($_POST['birthday']);
        
        // Validate input
        if (empty($name) || empty($email) || empty($phone) || empty($birthday)) {
            $error = "Please fill in all fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif (!preg_match("/^[0-9]{10,15}$/", $phone)) {
            $error = "Invalid phone number. It must be 10-15 digits.";
        } else {
            // Check if email exists and is not the current user's email
            $checkEmailStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkEmailStmt->bind_param("si", $email, $user_id);
            $checkEmailStmt->execute();
            $checkEmailResult = $checkEmailStmt->get_result();
            
            if ($checkEmailResult->num_rows > 0) {
                $error = "Email already exists. Please use a different email.";
            } else {
                // Update user profile
                $updateStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, birthday = ? WHERE id = ?");
                $updateStmt->bind_param("ssssi", $name, $email, $phone, $birthday, $user_id);
                
                if ($updateStmt->execute()) {
                    $message = "Profile updated successfully!";
                    
                    // Refresh user data
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                } else {
                    $error = "Failed to update profile. Please try again.";
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Get password data
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate passwords
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "Please fill in all password fields.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password should be at least 8 characters long.";
        } else {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update the password
                $updatePasswordStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updatePasswordStmt->bind_param("si", $hashed_password, $user_id);
                
                if ($updatePasswordStmt->execute()) {
                    $message = "Password changed successfully!";
                } else {
                    $error = "Failed to update password. Please try again.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - The Barn & Backyard</title>
    <link rel="icon" href="../img/barn-backyard.svg" type="image/svg+xml"/>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #5D5FEF;
            --secondary-color: #E0E7FF;
            --accent-color: #3F3D56;
            --light-bg: #F9FAFB;
            --dark-color: #1f2937;
            --header-font: 'Cormorant Garamond', serif;
            --body-font: 'Montserrat', sans-serif;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        }
        
        body {
            font-family: var(--body-font);
            background-color: var(--light-bg);
            min-height: 100vh;
            color: var(--dark-color);
            line-height: 1.6;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: var(--header-font);
            font-weight: 600;
        }
        
        .nav-link {
            position: relative;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background-color: var(--primary-color);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .tab-active {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-input {
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(93, 95, 239, 0.1);
        }
        
        .profile-header {
            background: linear-gradient(to right, #4338ca, #5D5FEF);
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.2;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="sticky top-0 z-50 backdrop-filter backdrop-blur-lg bg-white bg-opacity-90 shadow-sm transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <a href="../index.php" class="flex items-center space-x-2">
                    <i class="fas fa-barn text-primary text-xl" style="color: var(--primary-color);"></i>
                    <span class="font-bold text-xl text-gray-800">The Barn & Backyard</span>
                </a>
                <!-- Navigation Links -->
                <div class="flex space-x-6">
                    <a href="../index.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Home</a>
                    <a href="my-bookings.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">My Bookings</a>
                    <a href="profile.php" class="py-2 text-primary font-medium nav-link hover:text-primary">My Profile</a>
                    <a href="../logout.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-5xl mx-auto px-4 py-8">
        <!-- Profile Header -->
        <div class="profile-header rounded-2xl p-8 mb-8 text-white">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="flex items-center mb-4 md:mb-0">
                    <div class="bg-white/20 backdrop-blur-sm p-5 rounded-full mr-6">
                        <i class="fas fa-user text-4xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></h1>
                        <p class="text-white/80">Member since <?php echo isset($user['created_at']) ? date('F Y', strtotime($user['created_at'])) : 'Unknown'; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Booking Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-700">Total Bookings</h3>
                        <div class="w-12 h-12 flex items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $bookingStats['total_bookings']; ?></p>
                    <p class="text-sm text-gray-500 mt-1">All-time reservations</p>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-700">Upcoming Events</h3>
                        <div class="w-12 h-12 flex items-center justify-center rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $bookingStats['confirmed_bookings']; ?></p>
                    <p class="text-sm text-gray-500 mt-1">Confirmed reservations</p>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-700">Past Events</h3>
                        <div class="w-12 h-12 flex items-center justify-center rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-history"></i>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $bookingStats['completed_bookings']; ?></p>
                    <p class="text-sm text-gray-500 mt-1">Completed events</p>
                </div>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
        <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <p><?php echo $message; ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <p><?php echo $error; ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Profile Tabs -->
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <!-- Tab Navigation -->
            <div class="flex border-b">
                <button class="tab-button tab-active flex-1 py-4 font-medium text-center border-b-2 border-indigo-500" data-tab="profile-info">
                    <i class="fas fa-user-edit mr-2"></i>Profile Information
                </button>
                <button class="tab-button flex-1 py-4 font-medium text-center text-gray-500 border-b-2 border-transparent" data-tab="security">
                    <i class="fas fa-lock mr-2"></i>Security Settings
                </button>
            </div>
            
            <!-- Tab Content -->
            <div class="tab-content p-8" id="profile-info">
                <form method="POST" action="" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" class="form-input w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="form-input w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="form-input w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div>
                            <label for="birthday" class="block text-sm font-medium text-gray-700 mb-1">Birthday</label>
                            <input type="date" id="birthday" name="birthday" value="<?php echo htmlspecialchars($user['birthday'] ?? ''); ?>" class="form-input w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="update_profile" class="px-6 py-3 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="tab-content p-8 hidden" id="security">
                <form method="POST" action="" class="space-y-6">
                    <div class="max-w-md mx-auto">
                        <div class="mb-5">
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-input w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div class="mb-5">
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-input w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <p class="text-xs text-gray-500 mt-1">Password must be at least 8 characters long</p>
                        </div>
                        
                        <div class="mb-6">
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div class="flex justify-center">
                            <button type="submit" name="change_password" class="px-6 py-3 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                <i class="fas fa-key mr-2"></i>Change Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t mt-12 py-8">
        <div class="max-w-5xl mx-auto px-4 text-center text-gray-600">
            <div class="flex justify-center space-x-6 mb-4">
                <a href="#" class="text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="#" class="text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="#" class="text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fab fa-twitter"></i>
                </a>
            </div>
            <p>© 2025 The Barn & Backyard. All rights reserved.</p>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab functionality
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons
                tabButtons.forEach(btn => {
                    btn.classList.remove('tab-active');
                    btn.classList.add('text-gray-500', 'border-transparent');
                });
                
                // Add active class to clicked button
                button.classList.add('tab-active');
                button.classList.remove('text-gray-500', 'border-transparent');
                
                // Hide all tab contents
                tabContents.forEach(content => {
                    content.classList.add('hidden');
                });
                
                // Show the corresponding tab content
                const tabId = button.getAttribute('data-tab');
                document.getElementById(tabId).classList.remove('hidden');
            });
        });
    });
    </script>
</body>
</html>