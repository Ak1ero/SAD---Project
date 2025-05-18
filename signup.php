<?php
session_start();
include 'db/config.php'; // Include your database configuration

$error = '';
$success = ''; // Added variable to store success message

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $birthday = trim($_POST['birthday']);
    $password = trim($_POST['password']);

    // Validate input
    if (empty($name) || empty($email) || empty($phone) || empty($birthday) || empty($password)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match("/^[0-9]{10,15}$/", $phone)) {
        $error = "Invalid phone number. It must be 10-15 digits.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = "Email already exists. Please use a different email.";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user into the database
                $insert_stmt = $conn->prepare("INSERT INTO users (name, email, phone, birthday, password) VALUES (?, ?, ?, ?, ?)");
                if ($insert_stmt) {
                    $insert_stmt->bind_param("sssss", $name, $email, $phone, $birthday, $hashed_password);
                    if ($insert_stmt->execute()) {
                        // Registration successful - set success message instead of redirecting
                        $success = "Registration successful! You can now <a href='login.php' class='text-indigo-700 font-medium'>login to your account</a>.";
                        // Clear the form data
                        $name = $email = $phone = $birthday = $password = "";
                    } else {
                        $error = "Database error. Please try again later.";
                    }
                    $insert_stmt->close();
                } else {
                    $error = "Database error. Please try again later.";
                }
            }
            $stmt->close();
        } else {
            $error = "Database error. Please try again later.";
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup - The Barn & Backyard</title>
    <link rel="icon" href="img/barn-backyard.svg" type="image/svg+xml"/>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #5D5FEF;
            --secondary-color: #E0E7FF;
            --accent-color: #3F3D56;
            --light-bg: #F9FAFB;
            --header-font: 'Cormorant Garamond', serif;
            --body-font: 'Montserrat', sans-serif;
        }
        
        body {
            font-family: var(--body-font);
            background-color: var(--light-bg);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: var(--header-font);
        }
        
        .input-field {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .input-field:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(93, 95, 239, 0.1);
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: #4b4dcb;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(93, 95, 239, 0.3);
        }
        
        .nav-link {
            position: relative;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary-color);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .brand-text {
            background: linear-gradient(90deg, var(--primary-color), #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .gradient-bg {
            background: linear-gradient(120deg, var(--primary-color), #8b5cf6);
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
        }
        
        .floating-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            z-index: -1;
            opacity: 0.6;
        }
        
        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(10px, -10px) rotate(5deg); }
            100% { transform: translate(0, 0) rotate(0deg); }
        }
        
        /* Animation classes */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        /* Login page animations */
        @keyframes subtleFloat {
            0% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0); }
        }
        
        @keyframes subtleRotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .animate-float {
            animation: subtleFloat 6s ease-in-out infinite;
        }
        
        .animate-slow-spin {
            animation: subtleRotate 20s linear infinite;
        }
        
        .animate-gradient {
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
        }
        
        /* Nav link animation styles */
        .nav-link {
            position: relative;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary-color);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .nav-link.active::after {
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Animated Shapes Background -->
    <div class="fixed inset-0 overflow-hidden -z-1">
        <div class="floating-shape gradient-bg w-96 h-96 -top-48 -left-48 opacity-20" style="animation: float 12s infinite ease-in-out;"></div>
        <div class="floating-shape gradient-bg w-96 h-96 -bottom-48 -right-48 opacity-20" style="animation: float 15s infinite ease-in-out 2s;"></div>
        <div class="floating-shape bg-indigo-300 w-64 h-64 top-1/4 right-1/3 opacity-10" style="animation: float 10s infinite ease-in-out 1s;"></div>
    </div>

    <!-- Navigation -->
    <nav class="sticky top-0 z-50 backdrop-filter backdrop-blur-lg bg-white bg-opacity-90 shadow-sm transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-2">
                        <i class="fas fa-barn text-primary text-xl" style="color: var(--primary-color);"></i>
                        <span class="font-bold text-xl text-gray-800">The Barn & Backyard</span>
                    </a>
                </div>
                
                <!-- Navigation Links -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Home</a>
                    <a href="pages/reviews.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Reviews</a>
                    <a href="pages/packages.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Packages</a>
                    <a href="pages/gallery.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Gallery</a>
                    <a href="pages/about.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">About</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Error Message -->
    <?php if (!empty($error)): ?>
    <div class="fixed top-24 left-1/2 transform -translate-x-1/2 z-50 animate-fade-in">
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-lg flex items-center">
            <i class="fas fa-exclamation-circle mr-3 text-red-500"></i>
            <p class="font-medium"><?php echo $error; ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Success Message -->
    <?php if (!empty($success)): ?>
    <div class="fixed top-24 left-1/2 transform -translate-x-1/2 z-50 animate-fade-in">
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-lg flex items-center">
            <i class="fas fa-check-circle mr-3 text-green-500"></i>
            <p class="font-medium"><?php echo $success; ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Signup Section -->
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="max-w-5xl w-full grid grid-cols-1 md:grid-cols-2 gap-0 shadow-2xl rounded-2xl overflow-hidden animate-fade-in">
            <!-- Left Column - Design Element -->
            <div class="hidden md:block relative overflow-hidden">
                <!-- Decorative background with gradient -->
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-600 via-purple-600 to-indigo-800 animate-gradient"></div>
                
                <!-- Decorative patterns -->
                <div class="absolute inset-0 opacity-10">
                    <div class="absolute top-0 left-0 w-full h-full animate-slow-spin" style="background-image: url('data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.2'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
                </div>
                
                <!-- Floating circles -->
                <div class="absolute w-32 h-32 rounded-full bg-white opacity-10 -top-10 -left-10 animate-float" style="animation-delay: 0s;"></div>
                <div class="absolute w-24 h-24 rounded-full bg-white opacity-10 bottom-20 right-10 animate-float" style="animation-delay: 2s;"></div>
                <div class="absolute w-16 h-16 rounded-full bg-white opacity-10 bottom-32 left-20 animate-float" style="animation-delay: 1s;"></div>
                
                <!-- Content -->
                <div class="relative h-full flex flex-col justify-center items-center p-12 z-10">
                    <div class="w-20 h-20 rounded-full bg-white/10 backdrop-blur-sm flex items-center justify-center mb-8 animate-pulse">
                        <i class="fas fa-user-plus text-white text-3xl"></i>
                    </div>
                    <h2 class="text-white text-3xl font-bold mb-6 text-center animate-fade-in" style="animation-delay: 0.2s;">Join Our Community</h2>
                    <p class="text-white text-center mb-8 animate-fade-in" style="animation-delay: 0.4s;">Create an account to start planning your perfect event at The Barn & Backyard.</p>
                    <!-- Decorative dots -->
                    <div class="flex space-x-3 mt-4 animate-fade-in" style="animation-delay: 0.6s;">
                        <span class="w-2 h-2 rounded-full bg-white/30"></span>
                        <span class="w-2 h-2 rounded-full bg-white"></span>
                        <span class="w-2 h-2 rounded-full bg-white/30"></span>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Signup Form -->
            <div class="glass-card p-8 md:p-12 bg-white">
                <div class="mb-8 text-center md:text-left">
                    <h1 class="text-3xl font-bold mb-2 brand-text">Create Account</h1>
                    <p class="text-gray-500">Fill in your details to get started</p>
                </div>
                
                <form action="signup.php" method="POST">
                    <div class="space-y-5">
                        <!-- Full Name Field -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input type="text" id="name" name="name" required 
                                    class="input-field block w-full pl-10 pr-3 py-3 text-gray-700 focus:outline-none"
                                    placeholder="Enter your full name"
                                    value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
                            </div>
                        </div>
                        
                        <!-- Email Field -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input type="email" id="email" name="email" required 
                                    class="input-field block w-full pl-10 pr-3 py-3 text-gray-700 focus:outline-none"
                                    placeholder="you@example.com"
                                    value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                            </div>
                        </div>
                        
                        <!-- Phone Number Field -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-phone text-gray-400"></i>
                                </div>
                                <input type="tel" id="phone" name="phone" required 
                                    pattern="[0-9]{10,15}" title="Enter a valid phone number (10-15 digits)"
                                    class="input-field block w-full pl-10 pr-3 py-3 text-gray-700 focus:outline-none"
                                    placeholder="Your phone number"
                                    value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>">
                            </div>
                        </div>
                        
                        <!-- Date of Birth Field -->
                        <div>
                            <label for="birthday" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-calendar-alt text-gray-400"></i>
                                </div>
                                <input type="date" id="birthday" name="birthday" required 
                                    class="input-field block w-full pl-10 pr-3 py-3 text-gray-700 focus:outline-none"
                                    value="<?php echo isset($birthday) ? htmlspecialchars($birthday) : ''; ?>">
                            </div>
                        </div>
                        
                        <!-- Password Field -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" id="password" name="password" required 
                                    class="input-field block w-full pl-10 pr-3 py-3 text-gray-700 focus:outline-none"
                                    placeholder="Create a strong password">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-eye text-gray-400 cursor-pointer toggle-password"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button type="submit" 
                                class="btn-primary w-full flex justify-center py-3 px-4 rounded-lg text-sm font-medium text-white focus:outline-none shadow-lg group">
                                <span>Create Account</span>
                                <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Login Link -->
                <div class="mt-8 text-center">
                    <p class="text-gray-600">Already have an account? 
                        <a href="login.php" class="font-medium brand-text hover:underline">
                            Sign in
                        </a>
                    </p>
                </div>
                
                <!-- Terms Notice -->
                <div class="mt-6 text-center text-xs text-gray-500">
                    <p>By creating an account, you agree to our <a href="#" class="text-primary hover:underline" style="color: var(--primary-color);">Terms</a> and <a href="#" class="text-primary hover:underline" style="color: var(--primary-color);">Privacy Policy</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const togglePassword = document.querySelector('.toggle-password');
            const passwordInput = document.querySelector('#password');
            
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Change the eye icon
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
            
            // Floating shapes animation
            const shapes = document.querySelectorAll('.floating-shape');
            shapes.forEach((shape, index) => {
                shape.style.animationDelay = `${index * 2}s`;
            });
            
            // Auto-hide messages after 5 seconds
            const messages = document.querySelectorAll('.fixed.top-24');
            if (messages.length > 0) {
                setTimeout(() => {
                    messages.forEach(message => {
                        message.style.opacity = '0';
                        message.style.transform = 'translateY(-20px) translateX(-50%)';
                        setTimeout(() => {
                            message.style.display = 'none';
                        }, 300);
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html>
