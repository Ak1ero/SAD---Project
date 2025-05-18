<?php
session_start();
include 'db/config.php';
include 'db/system_settings.php';

// Always allow access to login page, even in maintenance mode

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Validate input
    if (empty($input) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // First, try to log in as a user (check if input is an email)
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            // User login
            $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $input);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    
                    // Verify the password
                    if (password_verify($password, $user['password'])) {
                        // Password is correct, start a new session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_type'] = 'user';
                        $_SESSION['user_name'] = $user['name']; // Store user's name
                        header("Location: index.php"); // Redirect to user home page
                        exit();
                    } else {
                        $error = "Invalid password.";
                    }
                } else {
                    $error = "No account found with that email.";
                }
                $stmt->close();
            } else {
                $error = "Database error. Please try again later.";
            }
        } else {
            // If the input is not an email, try to log in as an admin (using username)
            $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ?");
            if ($stmt) {
                $stmt->bind_param("s", $input);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $admin = $result->fetch_assoc();
                    
                    // Hash the input password using SHA-256
                    $hashed_input_password = hash('sha256', $password);
                    
                    // Compare the hashed input password with the stored hash
                    if ($hashed_input_password === $admin['password']) {
                        // Password is correct, start a new session
                        $_SESSION['user_id'] = $admin['id'];
                        $_SESSION['user_type'] = 'admin';
                        $_SESSION['user_name'] = $admin['username']; // Store admin username
                        header("Location: admin/admindash.php"); // Redirect to admin dashboard
                        exit();
                    } else {
                        $error = "Invalid password.";
                    }
                } else {
                    $error = "No account found with that username.";
                }
                $stmt->close();
            } else {
                $error = "Database error. Please try again later.";
            }
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
    <title>Login - The Barn & Backyard</title>
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
        
        /* Loading Screen Animation */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
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
            bottom: -2px;
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
                <p class="font-medium"><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Login Section -->
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="max-w-5xl w-full grid grid-cols-1 md:grid-cols-2 gap-0 shadow-2xl rounded-2xl overflow-hidden animate-fade-in">
            <!-- Left Column - Image -->
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
                        <i class="fas fa-barn text-white text-3xl"></i>
                    </div>
                    <h2 class="text-white text-3xl font-bold mb-6 text-center animate-fade-in" style="animation-delay: 0.2s;">Welcome Back</h2>
                    <p class="text-white text-center mb-8 animate-fade-in" style="animation-delay: 0.4s;">Sign in to access your account and continue planning your perfect event.</p>
                    
                    <!-- Decorative dots -->
                    <div class="flex space-x-3 mt-4 animate-fade-in" style="animation-delay: 0.6s;">
                        <span class="w-2 h-2 rounded-full bg-white/30"></span>
                        <span class="w-2 h-2 rounded-full bg-white"></span>
                        <span class="w-2 h-2 rounded-full bg-white/30"></span>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Login Form -->
            <div class="glass-card p-8 md:p-12 bg-white">
                <div class="mb-8 text-center md:text-left">
                    <h1 class="text-3xl font-bold mb-2 brand-text">Sign In</h1>
                    <p class="text-gray-500">Enter your credentials to access your account</p>
                </div>
                
                <form action="login.php" method="POST">
                    <div class="space-y-6">
                        <!-- Email Field -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email or Username</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input type="text" id="email" name="email" required 
                                    class="input-field block w-full pl-10 pr-3 py-3 text-gray-700 focus:outline-none"
                                    placeholder="Enter your email or username">
                            </div>
                        </div>
                        
                        <!-- Password Field -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                                <a href="#" class="text-xs text-primary hover:text-indigo-700" style="color: var(--primary-color);">Forgot password?</a>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" id="password" name="password" required 
                                    class="input-field block w-full pl-10 pr-3 py-3 text-gray-700 focus:outline-none"
                                    placeholder="••••••••">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-eye text-gray-400 cursor-pointer toggle-password"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Remember Me -->
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox" 
                                class="h-4 w-4 text-primary focus:ring-indigo-500 border-gray-300 rounded" style="color: var(--primary-color);">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-600">Remember me</label>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" 
                            class="btn-primary w-full flex justify-center py-3 px-4 rounded-lg text-sm font-medium text-white focus:outline-none shadow-lg group">
                            <span>Sign in</span>
                            <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                        </button>
                    </div>
                    
                    <!-- Sign Up Link -->
                    <div class="mt-8 text-center">
                        <p class="text-gray-600">Don't have an account? 
                            <a href="signup.php" class="font-medium brand-text hover:underline">
                                Sign up
                            </a>
                        </p>
                    </div>
                </form>
                
                <!-- Social Login Divider -->
                <div class="mt-8 relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">or continue with</span>
                    </div>
                </div>
                
                <!-- Social Login Buttons -->
                <div class="mt-6 grid grid-cols-3 gap-3">
                    <button type="button" class="w-full py-2.5 px-4 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 flex justify-center items-center transition">
                        <i class="fab fa-google text-red-500"></i>
                    </button>
                    <button type="button" class="w-full py-2.5 px-4 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 flex justify-center items-center transition">
                        <i class="fab fa-facebook-f text-blue-600"></i>
                    </button>
                    <button type="button" class="w-full py-2.5 px-4 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 flex justify-center items-center transition">
                        <i class="fab fa-apple text-gray-800"></i>
                    </button>
                </div>
                
                <!-- Terms Notice -->
                <div class="mt-6 text-center text-xs text-gray-500">
                    <p>By signing in, you agree to our <a href="#" class="text-primary hover:underline" style="color: var(--primary-color);">Terms</a> and <a href="#" class="text-primary hover:underline" style="color: var(--primary-color);">Privacy Policy</a></p>
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
            
            // Auto-hide error message after 5 seconds
            const errorMessage = document.querySelector('.border-red-500');
            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.style.opacity = '0';
                    errorMessage.style.transform = 'translateY(-20px) translateX(-50%)';
                    setTimeout(() => {
                        errorMessage.style.display = 'none';
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>