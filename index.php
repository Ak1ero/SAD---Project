<?php
session_start();
include 'db/config.php';
include 'db/system_settings.php';

maintenance_check($conn);

$sql = "SELECT *, image_path FROM event_packages LIMIT 3";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Barn & Backyard | Event Venue</title>
    <link rel="icon" href="img/barn-backyard.svg" type="image/svg+xml"/>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        #loading-screen {
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
        
        .loader-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
        }
        
        .barn-loader {
            width: 120px;
            height: 120px;
            position: relative;
        }
        
        .barn-roof {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 40%;
            background-color: var(--primary-color);
            clip-path: polygon(0 100%, 50% 0, 100% 100%);
            transform-origin: bottom center;
            animation: roofAppear 2s ease-out forwards;
        }
        
        .barn-body {
            position: absolute;
            top: 40%;
            left: 10%;
            width: 80%;
            height: 60%;
            background-color: var(--accent-color);
            transform: scaleY(0);
            transform-origin: top center;
            animation: bodyGrow 1.5s ease-out 0.5s forwards;
        }
        
        .barn-door {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%) scaleY(0);
            width: 30%;
            height: 40%;
            background-color: var(--secondary-color);
            transform-origin: bottom center;
            animation: doorOpen 1s ease-out 2s forwards;
        }
        
        .loading-text {
            font-family: var(--header-font);
            font-size: 1.5rem;
            color: var(--accent-color);
            opacity: 0;
            animation: fadeIn 1s ease-out 1s forwards;
        }
        
        .loading-subtext {
            font-family: var(--body-font);
            color: #666;
            margin-top: 8px;
            opacity: 0;
            animation: fadeIn 1s ease-out 1.5s forwards;
        }
        
        .loading-progress {
            width: 200px;
            height: 4px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
            margin-top: 1rem;
        }
        
        .progress-bar {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 0%;
            background: linear-gradient(to right, var(--secondary-color), var(--primary-color));
            animation: progressAnim 4.5s ease-out forwards;
        }
        
        @keyframes roofAppear {
            0% { transform: scaleX(0); }
            100% { transform: scaleX(1); }
        }
        
        @keyframes bodyGrow {
            0% { transform: scaleY(0); }
            100% { transform: scaleY(1); }
        }
        
        @keyframes doorOpen {
            0% { transform: translateX(-50%) scaleY(0); }
            100% { transform: translateX(-50%) scaleY(1); }
        }
        
        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }
        
        @keyframes progressAnim {
            0% { width: 0%; }
            20% { width: 20%; }
            50% { width: 50%; }
            80% { width: 80%; }
            100% { width: 100%; }
        }
        
        /* Hide main content until loading is complete */
        .content-hidden {
            opacity: 0;
            visibility: hidden;
        }
        
        /* Content appear animation */
        .content-appear {
            animation: contentAppear 1s ease-out forwards;
        }
        
        @keyframes contentAppear {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        body {
            font-family: var(--body-font);
            background-color: var(--light-bg);
            color: #374151;
            line-height: 1.6;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: var(--header-font);
            font-weight: 600;
            line-height: 1.2;
        }
        
        .hero-slide {
            height: 90vh;
            background-position: center;
        }
        
        .transition-all {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .package-card {
            transition: all 0.3s ease;
        }
        .package-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Package image effect */
        .package-card .h-48 {
            overflow: hidden;
        }
        
        .package-card .h-48 img {
            transition: transform 0.5s ease;
        }
        
        .package-card:hover .h-48 img {
            transform: scale(1.1);
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
        
        .btn-secondary {
            background-color: var(--accent-color);
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background-color: #2d2c40;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(63, 61, 86, 0.3);
        }
        
        .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .section-heading {
            position: relative;
            display: inline-block;
            margin-bottom: 1.5rem;
        }
        
        .section-heading::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 3px;
        }
        
        .testimonial-card {
            transition: all 0.3s ease;
            border-radius: 1rem;
            overflow: hidden;
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        }
        
        .feature-icon {
            transition: all 0.3s ease;
        }
        
        .package-card:hover .feature-icon {
            transform: scale(1.1);
        }
        
        .preload-bg {
            position: absolute;
            height: 1px;
            width: 1px;
            opacity: 0;
            z-index: -1;
        }
        
        /* Animation for testimonials */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #4b4dcb;
        }
        
        /* Animated underline effect for nav links */
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
        
        /* Modal scrolling fix */
        #modal .bg-white {
            max-height: 80vh;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

<!-- Loading Screen -->
<div id="loading-screen">
    <div class="loader-container">
        <div class="barn-loader">
            <div class="barn-roof"></div>
            <div class="barn-body"></div>
            <div class="barn-door"></div>
        </div>
        <div class="loading-text">The Barn & Backyard</div>
        <div class="loading-subtext" style="font-family: var(--body-font); color: #666; margin-top: 8px; opacity: 0; animation: fadeIn 1s ease-out 1.5s forwards;">Creating unforgettable events</div>
        <div class="loading-progress">
            <div class="progress-bar"></div>
        </div>
        <div id="loading-percentage" style="font-family: var(--body-font); font-size: 0.875rem; color: var(--primary-color); margin-top: 8px; font-weight: 600;">0%</div>
    </div>
</div>

<!-- Main Content Container - Initially Hidden -->
<div id="main-content" class="content-hidden">

<!-- Navigation -->
<nav class="sticky top-0 z-50 backdrop-filter backdrop-blur-lg bg-white bg-opacity-90 shadow-md transition-all">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <!-- Logo on the left -->
            <div class="flex items-center">
                <a href="index.php" class="flex items-center">
                    <span class="font-bold text-2xl text-gray-800 font-header">The Barn & Backyard</span>
                </a>
            </div>

            <!-- Centered Links -->
            <div class="hidden md:flex items-center space-x-10">
                <a href="index.php" class="py-2 text-gray-800 font-medium nav-link active">Home</a>
                <a href="pages/reviews.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Reviews</a>
                <a href="pages/packages.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Packages</a>
                <a href="pages/gallery.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Gallery</a>
                <a href="pages/about.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">About</a>
            </div>

            <!-- User Name and Dropdown -->
            <div class="md:flex items-center">
                <?php if (isset($_SESSION['user_name'])): ?>
                    <!-- Dropdown Menu -->
                    <div class="relative">
                        <button id="userDropdownBtn" class="flex items-center space-x-2 px-4 py-2 rounded-full bg-white shadow-sm hover:shadow-md transition-all text-gray-800 font-medium focus:outline-none">
                            <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <!-- Dropdown Content -->
                        <div id="userDropdown" class="absolute right-0 mt-3 w-48 bg-white rounded-xl shadow-xl py-2 hidden transform transition-all opacity-0 scale-95">
                            <a href="users/profile.php" class="flex items-center px-4 py-3 text-gray-800 hover:bg-gray-100">
                                <i class="fas fa-user-circle mr-2 text-gray-600"></i>
                                My Profile
                            </a>
                            <a href="users/my-bookings.php" class="flex items-center px-4 py-3 text-gray-800 hover:bg-gray-100">
                                <i class="fas fa-calendar-check mr-2 text-gray-600"></i>
                                My Bookings
                            </a>
                            <div class="border-t border-gray-200 my-1"></div>
                            <a href="logout.php" class="flex items-center px-4 py-3 text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt mr-2"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Display Login / Signup button if not logged in -->
                    <div class="md:flex items-center">
                    <a href="login.php" class="py-2 px-5 btn-primary rounded-full shadow-md transform transition-all duration-300 flex items-center space-x-2">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login / Signup</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center">
                <button id="mobileMenuBtn" class="p-2 rounded-lg hover:bg-gray-100 transition-all focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div id="mobileMenu" class="md:hidden hidden py-4 border-t border-gray-100">
            <div class="flex flex-col space-y-3">
                <a href="index.php" class="py-3 px-4 text-gray-800 font-medium rounded-lg bg-gray-100">Home</a>
                <a href="pages/reviews.php" class="py-3 px-4 text-gray-600 font-medium rounded-lg hover:bg-gray-100">Reviews</a>
                <a href="pages/packages.php" class="py-3 px-4 text-gray-600 font-medium rounded-lg hover:bg-gray-100">Packages</a>
                <a href="pages/gallery.php" class="py-3 px-4 text-gray-600 font-medium rounded-lg hover:bg-gray-100">Gallery</a>
                <a href="pages/about.php" class="py-3 px-4 text-gray-600 font-medium rounded-lg hover:bg-gray-100">About</a>
            </div>
        </div>
    </div>
</nav>

<!-- Hero Section with Carousel -->
<div class="swiper-container hero-slide relative">
    <div class="swiper-wrapper">
        <!-- Slide 1 -->
        <div class="swiper-slide">
            <div class="bg-cover bg-center h-full" style="background-image: url('img/e-1.jpg');">
                <div class="flex items-center justify-center h-full bg-black bg-opacity-50">
                    <div class="text-center max-w-4xl px-4 transform transition-all duration-700 translate-y-0">
                        <span class="text-white text-sm sm:text-base uppercase tracking-widest font-medium mb-3 inline-block">Your Dream Venue</span>
                        <h1 class="text-white text-4xl sm:text-5xl md:text-6xl font-bold mb-6 leading-tight">Elegant Weddings</h1>
                        <p class="text-white text-lg md:text-xl mb-8 max-w-2xl mx-auto opacity-90">Create unforgettable memories in our rustic yet elegant barn venue.</p>
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                            <a href="pages/packages.php" class="px-8 py-3 btn-primary rounded-full text-sm sm:text-base font-medium">
                                View Packages
                            </a>
                            <a href="#how-it-works" class="px-8 py-3 bg-white bg-opacity-20 backdrop-filter backdrop-blur-sm text-white border border-white border-opacity-40 rounded-full text-sm sm:text-base font-medium hover:bg-opacity-30 transition-all">
                                Learn More
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Slide 2 -->
        <div class="swiper-slide">
            <div class="bg-cover bg-center h-full" style="background-image: url('img/e-2.jpg');">
                <div class="flex items-center justify-center h-full bg-black bg-opacity-50">
                    <div class="text-center max-w-4xl px-4 transform transition-all duration-700 translate-y-0">
                        <span class="text-white text-sm sm:text-base uppercase tracking-widest font-medium mb-3 inline-block">Professional & Elegant</span>
                        <h1 class="text-white text-4xl sm:text-5xl md:text-6xl font-bold mb-6 leading-tight">Corporate Events</h1>
                        <p class="text-white text-lg md:text-xl mb-8 max-w-2xl mx-auto opacity-90">Host your business gatherings in a professional setting with a touch of rustic charm.</p>
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                            <a href="pages/packages.php" class="px-8 py-3 btn-primary rounded-full text-sm sm:text-base font-medium">
                                View Packages
                            </a>
                            <a href="#how-it-works" class="px-8 py-3 bg-white bg-opacity-20 backdrop-filter backdrop-blur-sm text-white border border-white border-opacity-40 rounded-full text-sm sm:text-base font-medium hover:bg-opacity-30 transition-all">
                                Learn More
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Slide 3 -->
        <div class="swiper-slide">
            <div class="bg-cover bg-center h-full" style="background-image: url('img/4.jpg');">
                <div class="flex items-center justify-center h-full bg-black bg-opacity-50">
                    <div class="text-center max-w-4xl px-4 transform transition-all duration-700 translate-y-0">
                        <span class="text-white text-sm sm:text-base uppercase tracking-widest font-medium mb-3 inline-block">Celebrate in Style</span>
                        <h1 class="text-white text-4xl sm:text-5xl md:text-6xl font-bold mb-6 leading-tight">Birthday Celebrations</h1>
                        <p class="text-white text-lg md:text-xl mb-8 max-w-2xl mx-auto opacity-90">Celebrate your special day in a unique and intimate setting that everyone will remember.</p>
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                            <a href="pages/packages.php" class="px-8 py-3 btn-primary rounded-full text-sm sm:text-base font-medium">
                                View Packages
                            </a>
                            <a href="#how-it-works" class="px-8 py-3 bg-white bg-opacity-20 backdrop-filter backdrop-blur-sm text-white border border-white border-opacity-40 rounded-full text-sm sm:text-base font-medium hover:bg-opacity-30 transition-all">
                                Learn More
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Add Pagination -->
    <div class="swiper-pagination"></div>
    <!-- Add Navigation -->
    <div class="swiper-button-next text-white"></div>
    <div class="swiper-button-prev text-white"></div>
</div>

<!-- How It Works Section -->
<div id="how-it-works" class="max-w-7xl mx-auto px-4 py-24">
    <div class="text-center mb-16">
        <span class="text-sm font-medium tracking-wider uppercase text-indigo-600 mb-2 inline-block">Simple Process</span>
        <h2 class="text-4xl font-bold mb-4 section-heading">How It Works</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">Planning your event with us is simple and stress-free. Follow these easy steps to create your perfect occasion.</p>
    </div>
    
    <!-- Timeline style layout -->
    <div class="relative">
        <!-- Timeline line -->
        <div class="hidden md:block absolute left-1/2 transform -translate-x-1/2 h-full w-1 bg-gradient-to-b from-blue-100 via-purple-100 to-pink-100 rounded-full"></div>
        
        <div class="space-y-16 md:space-y-24 relative">
            <!-- Step 1 -->
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-1/2 md:pr-16 mb-6 md:mb-0 md:text-right">
                    <h3 class="text-2xl font-bold mb-3">Choose Your Date</h3>
                    <p class="text-gray-600">Browse our availability calendar and select your preferred date and time for your event. We offer flexible scheduling to accommodate your needs.</p>
                </div>
                
                <div class="md:absolute md:left-1/2 md:transform md:-translate-x-1/2 z-10 md:mx-auto flex flex-shrink-0 items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full shadow-lg mb-6 md:mb-0">
                    <i class="fas fa-calendar-alt text-2xl text-white"></i>
                </div>
                
                <div class="md:w-1/2 md:pl-16 md:text-left"></div>
            </div>
            
            <!-- Step 2 -->
            <div class="flex flex-col md:flex-row-reverse items-center">
                <div class="md:w-1/2 md:pl-16 mb-6 md:mb-0 md:text-left">
                    <h3 class="text-2xl font-bold mb-3">Customize Your Event</h3>
                    <p class="text-gray-600">Select from our range of packages and add any custom requirements for your special occasion. We're here to make your vision come to life.</p>
                </div>
                
                <div class="md:absolute md:left-1/2 md:transform md:-translate-x-1/2 z-10 md:mx-auto flex flex-shrink-0 items-center justify-center w-16 h-16 bg-gradient-to-r from-green-500 to-teal-500 rounded-full shadow-lg mb-6 md:mb-0">
                    <i class="fas fa-cogs text-2xl text-white"></i>
                </div>
                
                <div class="md:w-1/2 md:pr-16 md:text-right"></div>
            </div>
            
            <!-- Step 3 -->
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-1/2 md:pr-16 mb-6 md:mb-0 md:text-right">
                    <h3 class="text-2xl font-bold mb-3">Confirm Booking</h3>
                    <p class="text-gray-600">Review your details, make your deposit payment, and receive instant confirmation. Our secure booking system makes it easy.</p>
                </div>
                
                <div class="md:absolute md:left-1/2 md:transform md:-translate-x-1/2 z-10 md:mx-auto flex flex-shrink-0 items-center justify-center w-16 h-16 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full shadow-lg mb-6 md:mb-0">
                    <i class="fas fa-check-circle text-2xl text-white"></i>
                </div>
                
                <div class="md:w-1/2 md:pl-16 md:text-left"></div>
            </div>
            
            <!-- Step 4 -->
            <div class="flex flex-col md:flex-row-reverse items-center">
                <div class="md:w-1/2 md:pl-16 mb-6 md:mb-0 md:text-left">
                    <h3 class="text-2xl font-bold mb-3">Enjoy Your Event</h3>
                    <p class="text-gray-600">Arrive on your special day and our team will ensure everything runs perfectly. We take care of the details so you can enjoy your moment.</p>
                </div>
                
                <div class="md:absolute md:left-1/2 md:transform md:-translate-x-1/2 z-10 md:mx-auto flex flex-shrink-0 items-center justify-center w-16 h-16 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-full shadow-lg mb-6 md:mb-0">
                    <i class="fas fa-glass-cheers text-2xl text-white"></i>
                </div>
                
                <div class="md:w-1/2 md:pr-16 md:text-right"></div>
            </div>
        </div>
    </div>
</div>

<!-- Packages Section -->
<div class="py-24 bg-gradient-to-b from-white to-indigo-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-16">
            <span class="text-sm font-medium tracking-wider uppercase text-indigo-600 mb-2 inline-block">Tailored for You</span>
            <h2 class="text-4xl font-bold mb-4 section-heading">Our Event Packages</h2>
            <p class="text-gray-600 max-w-2xl mx-auto text-lg">Choose from our carefully designed packages to make your event planning simple and stress-free.</p>
        </div>
        
        <div class="grid md:grid-cols-3 gap-10">
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden flex flex-col transform transition-all duration-300 package-card hover:scale-105">
                        <!-- Package Image -->
                        <div class="h-48 overflow-hidden">
                            <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                        </div>
                        
                        <!-- Package Header with Name -->
                        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-6 text-center">
                            <h3 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($row['name']); ?></h3>
                        </div>
                        
                        <!-- Package Content -->
                        <div class="p-6 flex-grow flex flex-col">
                            <p class="text-gray-600 mb-6 flex-grow"><?php echo htmlspecialchars(substr($row['description'], 0, 120)) . (strlen($row['description']) > 120 ? '...' : ''); ?></p>
                            
                            <div class="flex justify-between items-center mb-6">
                                <p class="text-3xl font-bold text-gray-800">₱<?php echo number_format(floatval($row['price']), 0, '.', ','); ?></p>
                                <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">Available</span>
                            </div>
                            
                            <button onclick="openModal('<?php echo htmlspecialchars($row['id']); ?>', '<?php echo htmlspecialchars($row['image_path']); ?>')" 
                                class="w-full btn-primary rounded-lg py-3 font-semibold flex items-center justify-center">
                                <span>View Details</span>
                                <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-3 p-8 bg-white rounded-xl shadow text-center">
                    <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-600">No packages available at the moment. Please check back later.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-16">
            <a href="pages/packages.php" class="inline-block btn-secondary rounded-lg py-4 px-8 font-semibold shadow-lg hover:shadow-xl">
                View All Packages
                <i class="fas fa-chevron-right ml-2"></i>
            </a>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-70 backdrop-filter backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[80vh] overflow-auto transform transition-all duration-300 ease-in-out scale-95 opacity-0">
        <!-- Modal Header -->
        <div class="relative h-56">
            <img id="modal-image" src="" alt="Package Image" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/50 to-transparent"></div>
            <button onclick="closeModal()" class="absolute top-4 right-4 bg-white/20 backdrop-filter backdrop-blur-sm rounded-full p-2 hover:bg-white/40 transition-all duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <div class="absolute bottom-4 left-6 text-white">
                <span class="text-sm font-medium text-indigo-300 uppercase tracking-wider">Package Details</span>
                <h3 id="modal-title" class="text-2xl font-bold mb-1"></h3>
                <p id="modal-price" class="text-lg font-semibold bg-indigo-600/70 backdrop-filter backdrop-blur-sm px-3 py-1 rounded-full inline-block"></p>
            </div>
        </div>
        
        <!-- Modal Body -->
        <div class="px-6 py-5">
            <!-- Quick Stats -->
            <div class="mb-6 grid grid-cols-2 gap-3">
                <!-- Status -->
                <div class="bg-indigo-50 rounded-xl overflow-hidden py-3 px-4 text-center">
                    <i class="fas fa-calendar-check text-indigo-600 text-lg mb-1"></i>
                    <p class="text-xs font-medium text-gray-500">Status</p>
                    <p class="text-sm font-semibold text-green-600">Available for Booking</p>
                </div>
                
                <!-- Guest Capacity -->
                <div class="bg-indigo-50 rounded-xl overflow-hidden py-3 px-4 text-center">
                    <i class="fas fa-users text-indigo-600 text-lg mb-1"></i>
                    <p class="text-xs font-medium text-gray-500">Guest Capacity</p>
                    <p id="modal-capacity" class="text-sm font-semibold text-gray-800">Loading...</p>
                </div>
            </div>

            <!-- Package Details -->
            <div class="mb-6">
                <h4 class="text-base font-bold mb-3 text-gray-800 flex items-center">
                    <i class="fas fa-list-ul text-indigo-600 mr-2"></i>
                    Package Description
                </h4>
                <div id="modal-details" class="text-sm leading-relaxed text-gray-600 bg-gray-50 p-4 rounded-xl"></div>
            </div>

            <!-- What's Included -->
            <div class="mb-6">
                <h4 class="text-base font-bold mb-3 text-gray-800 flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    What's Included
                </h4>
                <ul id="modal-inclusions" class="text-sm space-y-2 text-gray-600 bg-gray-50 p-4 rounded-xl"></ul>
            </div>

            <!-- Terms & Conditions -->
            <div>
                <h4 class="text-base font-bold mb-3 text-gray-800 flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    Terms & Conditions
                </h4>
                <button onclick="openTermsModal()" class="w-full text-left">
                    <div id="modal-terms" class="p-4 bg-gray-50 rounded-xl text-sm text-gray-600 hover:bg-gray-100 transition-all duration-300">
                        <div class="flex justify-between items-center">
                            <span class="line-clamp-1">Click to view full terms and conditions</span>
                            <i class="fas fa-external-link-alt text-indigo-600"></i>
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="border-t border-gray-100 px-6 py-4 bg-gray-50 sticky bottom-0">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-shield-alt text-green-600 text-sm"></i>
                    <span class="text-xs font-medium text-gray-800">100% Secure Booking</span>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- If user is logged in, show regular booking link -->
                    <a href="users/booking-form.php?package=<?php echo urlencode($current_package_name); ?>" class="btn-primary rounded-lg py-3 px-6 font-medium flex items-center justify-center space-x-2 text-center" id="bookingLink">
                        <span>Book This Package</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <!-- If user is not logged in, show login redirect button -->
                    <a href="login.php" class="btn-primary rounded-lg py-3 px-6 font-medium flex items-center justify-center space-x-2 text-center">
                        <span>Login to Book</span>
                        <i class="fas fa-sign-in-alt"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Testimonials Section -->
<div class="py-24 bg-gray-900 text-white relative overflow-hidden">
    <!-- Background circles for visual interest -->
    <div class="absolute -top-24 -right-24 w-96 h-96 bg-indigo-500 opacity-10 rounded-full"></div>
    <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-purple-500 opacity-10 rounded-full"></div>
    
    <div class="max-w-6xl mx-auto px-4 relative z-10">
        <div class="text-center mb-16">
            <span class="text-sm font-medium tracking-wider uppercase text-indigo-400 mb-2 inline-block">TESTIMONIALS</span>
            <h2 class="text-4xl font-bold mb-4 section-heading">What Our Clients Say</h2>
            <p class="text-gray-300 max-w-2xl mx-auto text-lg">Hear from our satisfied clients who have celebrated their special moments with us.</p>
        </div>
        <div id="testimonials-container" class="grid md:grid-cols-3 gap-8">
            <!-- Testimonials will be loaded dynamically here -->
            <!-- Loading placeholders -->
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="bg-gray-800 p-6 rounded-2xl testimonial-card border border-gray-700 relative flex flex-col animate-pulse">
                <div class="mb-6">
                    <div class="flex mb-4">
                        <div class="h-5 w-24 bg-gray-700 rounded"></div>
                    </div>
                    <div class="h-4 bg-gray-700 rounded mb-2"></div>
                    <div class="h-4 bg-gray-700 rounded mb-2"></div>
                    <div class="h-4 bg-gray-700 rounded mb-2"></div>
                    <div class="h-4 bg-gray-700 rounded mb-2 w-3/4"></div>
                </div>
                <div class="mt-auto">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gray-700 rounded-full"></div>
                        <div class="ml-4">
                            <div class="h-4 bg-gray-700 rounded w-24 mb-2"></div>
                            <div class="h-3 bg-gray-700 rounded w-32"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
        <div class="text-center mt-12">
            <a href="pages/reviews.php" class="inline-block py-4 px-8 rounded-lg border-2 border-white hover:bg-white hover:text-gray-900 transition-all duration-300">
                Read More Reviews
                <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</div>

<!-- Call to Action -->
<div class="py-24 bg-gradient-to-r from-indigo-600 to-purple-600 text-white relative overflow-hidden">
    <div class="absolute top-0 left-0 w-full h-full pattern-dots-lg text-indigo-500 opacity-10"></div>
    
    <div class="max-w-5xl mx-auto px-4 text-center relative z-10">
        <h2 class="text-4xl font-bold mb-6">Ready to Plan Your Perfect Event?</h2>
        <p class="text-xl text-indigo-100 mb-10 max-w-3xl mx-auto">Let us help you create a memorable experience for you and your guests. Book your event date today.</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="pages/packages.php" class="px-8 py-4 bg-white text-indigo-600 font-bold rounded-lg hover:bg-gray-100 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                Browse Packages
            </a>
            <a href="<?php echo isset($_SESSION['user_id']) ? 'users/booking-form.php' : 'login.php'; ?>" class="px-8 py-4 bg-transparent text-white font-bold rounded-lg border-2 border-white hover:bg-white hover:text-indigo-600 transition-all">
                Book Now
            </a>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="bg-gray-900 text-white">
    <!-- Top Wave Separator -->
    <div class="w-full h-20 bg-gradient-to-r from-indigo-600 to-purple-600 relative overflow-hidden">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320" class="absolute bottom-0 w-full">
            <path fill="#111827" fill-opacity="1" d="M0,192L48,186.7C96,181,192,171,288,181.3C384,192,480,224,576,229.3C672,235,768,213,864,181.3C960,149,1056,107,1152,101.3C1248,96,1344,128,1392,144L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
        </svg>
    </div>
    
    <div class="max-w-6xl mx-auto px-4 pt-12 pb-8">
        <div class="grid md:grid-cols-3 lg:grid-cols-4 gap-12">
            <!-- Column 1: Brand & Social -->
            <div class="lg:col-span-1">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold mb-2">The Barn & Backyard</h2>
                    <p class="text-gray-400">Creating perfect moments for every occasion.</p>
                </div>
                <div class="flex space-x-4 mb-8">
                    <a href="#" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-indigo-500 transition-all">
                        <i class="fab fa-facebook-f text-gray-300"></i>
                    </a>
                    <a href="#" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-indigo-500 transition-all">
                        <i class="fab fa-instagram text-gray-300"></i>
                    </a>
                    <a href="#" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-indigo-500 transition-all">
                        <i class="fab fa-twitter text-gray-300"></i>
                    </a>
                </div>
            </div>
            
            <!-- Column 2: Quick Links -->
            <div>
                <h3 class="text-lg font-bold mb-4 relative inline-block">
                    Quick Links
                    <span class="absolute -bottom-1 left-0 w-10 h-1 bg-indigo-500 rounded-full"></span>
                </h3>
                <ul class="space-y-3">
                    <li><a href="index.php" class="text-gray-400 hover:text-white transition-colors">Home</a></li>
                    <li><a href="pages/packages.php" class="text-gray-400 hover:text-white transition-colors">Packages</a></li>
                    <li><a href="pages/gallery.php" class="text-gray-400 hover:text-white transition-colors">Gallery</a></li>
                    <li><a href="pages/reviews.php" class="text-gray-400 hover:text-white transition-colors">Reviews</a></li>
                    <li><a href="pages/about.php" class="text-gray-400 hover:text-white transition-colors">About Us</a></li>
                </ul>
            </div>
            
            <!-- Column 3: Contact Us -->
            <div>
                <h3 class="text-lg font-bold mb-4 relative inline-block">
                    Contact Us
                    <span class="absolute -bottom-1 left-0 w-10 h-1 bg-indigo-500 rounded-full"></span>
                </h3>
                <ul class="space-y-3">
                    <li class="flex items-start space-x-3">
                        <i class="fas fa-map-marker-alt text-indigo-400 mt-1.5"></i>
                        <span class="text-gray-400">Pioneer Avenue, Polomolok, South Cotabato</span>
                    </li>
                    <li class="flex items-center space-x-3">
                        <i class="fas fa-phone text-indigo-400"></i>
                        <a href="tel:5551234567" class="text-gray-400 hover:text-white transition-colors">09629737496</a>
                    </li>
                    <li class="flex items-center space-x-3">
                        <i class="fas fa-envelope text-indigo-400"></i>
                        <a href="mailto:info@barnbackyard.com" class="text-gray-400 hover:text-white transition-colors">info@barnbackyard.com</a>
                    </li>
                </ul>
            </div>
            
            <!-- Column 4: Newsletter -->
            <div>
                <h3 class="text-lg font-bold mb-4 relative inline-block">
                    Newsletter
                    <span class="absolute -bottom-1 left-0 w-10 h-1 bg-indigo-500 rounded-full"></span>
                </h3>
                <p class="text-gray-400 mb-4">Subscribe to our newsletter for updates on events, special offers, and venue news.</p>
                <form>
                    <div class="relative">
                        <input type="email" placeholder="Your email address" class="w-full px-4 py-3 rounded-lg text-gray-800 bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        <button type="submit" class="absolute right-1.5 top-1.5 bg-indigo-600 text-white rounded-lg p-2 hover:bg-indigo-700 transition-all">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="border-t border-gray-800 mt-12 pt-8 text-center">
            <p class="text-gray-500">© 2025 The Barn & Backyard Event Reservation System. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Terms & Conditions Modal -->
<div id="termsModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-60 backdrop-filter backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden transform transition-all duration-300 ease-in-out scale-95 opacity-0">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-indigo-600">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-file-contract mr-2"></i>
                Terms & Conditions
            </h3>
            <button onclick="closeTermsModal()" class="text-white hover:text-gray-200 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto max-h-[calc(80vh-140px)]">
            <div id="termsContent" class="prose max-w-none text-gray-600">
                <!-- Terms content will be inserted here -->
            </div>
        </div>
    </div>
</div>

<!-- Back to Top Button -->
<button id="backToTopBtn" class="fixed bottom-6 right-6 p-3 rounded-full bg-indigo-600 text-white shadow-lg z-40 transform transition-all duration-300 scale-0 hover:bg-indigo-700">
    <i class="fas fa-chevron-up"></i>
</button>

<!-- Add preloader divs for background images -->
<div class="hidden">
    <div class="preload-bg" style="background-image: url('img/e-1.jpg');"></div>
    <div class="preload-bg" style="background-image: url('img/e-2.jpg');"></div>
    <div class="preload-bg" style="background-image: url('img/4.jpg');"></div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Create loading indicator
        const loadingIndicator = document.createElement('div');
        loadingIndicator.id = 'page-loader';
        loadingIndicator.innerHTML = `
            <div class="fixed inset-0 bg-white z-50 flex items-center justify-center">
                <div class="text-center">
                    <div class="inline-block w-16 h-16 relative">
                        <div class="absolute top-0 left-0 w-full h-full border-4 border-indigo-200 rounded-full animate-ping"></div>
                        <div class="absolute top-0 left-0 w-full h-full border-4 border-t-indigo-600 border-r-transparent border-b-transparent border-l-transparent rounded-full animate-spin"></div>
                    </div>
                    <p class="mt-4 text-gray-700 font-medium">Loading experience...</p>
                </div>
            </div>
        `;
        document.body.appendChild(loadingIndicator);
        
        // Load testimonials from the server
        fetch('get_testimonials.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.testimonials && data.testimonials.length > 0) {
                    // Get the testimonials container
                    const testimonialsContainer = document.getElementById('testimonials-container');
                    
                    // Clear placeholder loading content
                    testimonialsContainer.innerHTML = '';
                    
                    // Array of colors for the avatar backgrounds
                    const avatarColors = [
                        'bg-indigo-600',  // Blue/purple
                        'bg-purple-600',  // Purple
                        'bg-pink-600',    // Pink
                        'bg-yellow-600',  // Yellow
                        'bg-green-600',   // Green
                        'bg-red-600'      // Red
                    ];
                    
                    // Corresponding text colors
                    const textColors = [
                        'text-indigo-400',
                        'text-purple-400',
                        'text-pink-400',
                        'text-yellow-400',
                        'text-green-400',
                        'text-red-400'
                    ];
                    
                    // Add each testimonial to the container
                    data.testimonials.forEach((testimonial, index) => {
                        // Determine color index based on initial or index
                        const colorIndex = index % avatarColors.length;
                        
                        // Create testimonial HTML
                        const testimonialHTML = `
                            <div class="bg-gray-800 p-6 rounded-2xl testimonial-card border border-gray-700 relative flex flex-col opacity-0 transform translate-y-8" 
                                 style="animation: fadeInUp 0.6s ease-out ${index * 0.2}s forwards;">
                                <div class="mb-6">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-400 absolute -top-4 -left-2 opacity-50" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
                                    </svg>
                                    <div class="flex mb-4">
                                        ${Array.from({length: testimonial.rating}, (_, i) => 
                                            `<span class="text-yellow-400">★</span>`
                                        ).join('')}
                                        ${Array.from({length: 5 - testimonial.rating}, (_, i) => 
                                            `<span class="text-gray-600">★</span>`
                                        ).join('')}
                                    </div>
                                    <p class="italic mb-4 text-gray-300">"${testimonial.review_text}"</p>
                                </div>
                                <div class="mt-auto">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 ${avatarColors[colorIndex]} rounded-full flex items-center justify-center text-xl font-bold text-white">
                                            ${testimonial.first_initial}
                                        </div>
                                        <div class="ml-4">
                                            <p class="font-bold">${testimonial.user_name}</p>
                                            <p class="text-sm ${textColors[colorIndex]}">${testimonial.event_type}, ${testimonial.created_at}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        // Add to container
                        testimonialsContainer.innerHTML += testimonialHTML;
                    });
                    
                    // If there are fewer than 3 testimonials, add placeholders
                    if (data.testimonials.length < 3) {
                        const remainingPlaceholders = 3 - data.testimonials.length;
                        for (let i = 0; i < remainingPlaceholders; i++) {
                            const placeholderHTML = `
                                <div class="bg-gray-800 p-6 rounded-2xl testimonial-card border border-gray-700 relative flex flex-col opacity-50">
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-400 absolute -top-4 -left-2 opacity-50" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
                                        </svg>
                                        <div class="flex mb-4">
                                            <span class="text-yellow-400">★</span>
                                            <span class="text-yellow-400">★</span>
                                            <span class="text-yellow-400">★</span>
                                            <span class="text-yellow-400">★</span>
                                            <span class="text-yellow-400">★</span>
                                        </div>
                                        <p class="italic mb-4 text-gray-300">Be the first to leave a review for this type of event!</p>
                                    </div>
                                    <div class="mt-auto">
                                        <div class="flex items-center">
                                            <div class="w-12 h-12 bg-gray-700 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user text-gray-500"></i>
                                            </div>
                                            <div class="ml-4">
                                                <p class="font-bold">Your Name Here</p>
                                                <p class="text-sm text-gray-400">Future Happy Client</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            testimonialsContainer.innerHTML += placeholderHTML;
                        }
                    }
                } else {
                    // No testimonials found, show default placeholders
                    const testimonialsContainer = document.getElementById('testimonials-container');
                    testimonialsContainer.innerHTML = `
                        <div class="md:col-span-3 text-center py-8">
                            <i class="fas fa-comment-dots text-4xl text-indigo-400 mb-4"></i>
                            <h3 class="text-xl font-bold mb-2">Be Our First Reviewer!</h3>
                            <p class="text-gray-400">Book an event with us and share your experience.</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading testimonials:', error);
                // Show error message or fallback to default testimonials
                const testimonialsContainer = document.getElementById('testimonials-container');
                testimonialsContainer.innerHTML = `
                    <div class="md:col-span-3 text-center py-8">
                        <i class="fas fa-exclamation-circle text-4xl text-yellow-400 mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Couldn't Load Testimonials</h3>
                        <p class="text-gray-400">Please check back later to see what our clients have to say.</p>
                    </div>
                `;
            });
        
        // Force reflow to ensure proper loading of resources
        setTimeout(() => {
            try {
                // Initialize Swiper with error handling
                var swiper = new Swiper('.swiper-container', {
                    loop: true,
                    effect: 'fade',
                    fadeEffect: {
                      crossFade: true
                    },
                    speed: 1000,
                    autoplay: {
                        delay: 5000,
                        disableOnInteraction: false,
                    },
                    pagination: {
                        el: '.swiper-pagination',
                        clickable: true,
                    },
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev',
                    },
                    on: {
                        init: function() {
                            console.log('Swiper initialized successfully');
                        },
                        slideChange: function() {
                            // Update slide indicator
                            const slideNumber = document.querySelector('.slide-number');
                            if (slideNumber) {
                                const currentSlide = this.realIndex + 1;
                                slideNumber.textContent = currentSlide.toString().padStart(2, '0');
                            }
                            
                            // Add animation to current slide content
                            const activeSlide = this.slides[this.activeIndex];
                            if (activeSlide) {
                                const content = activeSlide.querySelector('.text-center');
                                if (content) {
                                    content.classList.add('translate-y-0', 'opacity-100');
                                    content.classList.remove('translate-y-10', 'opacity-0');
                                }
                            }
                        }
                    }
                });
                
                // Remove loading indicator after Swiper is initialized
                const loader = document.getElementById('page-loader');
                if (loader) loader.classList.add('opacity-0');
                setTimeout(() => {
                    if (loader) loader.remove();
                }, 500);
                
            } catch (error) {
                console.error('Swiper initialization error:', error);
                // Remove loading indicator even if there's an error
                const loader = document.getElementById('page-loader');
                if (loader) loader.remove();
            }
            
            // User dropdown functionality
            const userDropdownBtn = document.getElementById('userDropdownBtn');
            const userDropdown = document.getElementById('userDropdown');
            
            if (userDropdownBtn && userDropdown) {
                userDropdownBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('hidden');
                    userDropdown.classList.toggle('opacity-0');
                    userDropdown.classList.toggle('scale-95');
                    userDropdown.classList.toggle('opacity-100');
                    userDropdown.classList.toggle('scale-100');
                });
                
                // Close the dropdown when clicking elsewhere on the page
                document.addEventListener('click', function(e) {
                    if (userDropdownBtn && !userDropdownBtn.contains(e.target)) {
                        userDropdown.classList.add('hidden', 'opacity-0', 'scale-95');
                        userDropdown.classList.remove('opacity-100', 'scale-100');
                    }
                });
            }
            
            // Mobile menu
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            
            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }
            
            // Back to top button
            const backToTopBtn = document.getElementById('backToTopBtn');
            
            if (backToTopBtn) {
                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > 300) {
                        backToTopBtn.classList.remove('scale-0');
                        backToTopBtn.classList.add('scale-100');
                    } else {
                        backToTopBtn.classList.remove('scale-100');
                        backToTopBtn.classList.add('scale-0');
                    }
                });
                
                backToTopBtn.addEventListener('click', function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }
        }, 100);
    });

    // Define global variable to store current package name
    let current_package_name = '';

    function openModal(packageId, imagePath) {
        const modal = document.getElementById('modal');
        const modalTitle = document.getElementById('modal-title');
        const modalDetails = document.getElementById('modal-details');
        const modalInclusions = document.getElementById('modal-inclusions');
        const modalTerms = document.getElementById('modal-terms');
        const modalPrice = document.getElementById('modal-price');
        const modalImage = document.getElementById('modal-image');
        const modalCapacity = document.getElementById('modal-capacity');
        const modalContent = modal.querySelector('.bg-white');
        const bookingLink = document.getElementById('bookingLink');

        // Show the modal with loading state
        modal.classList.remove('hidden');
        
        // Enable scrolling in the modal while preventing body scroll
        document.body.style.overflow = 'hidden';
        
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
        
        modalTitle.textContent = 'Loading...';
        modalCapacity.textContent = 'Loading...';
        
        // Set a default image if the path is invalid
        if (!imagePath || imagePath === 'undefined') {
            imagePath = 'img/default-package.jpg';
        }

        // Set the image path directly
        modalImage.src = imagePath;
        modalImage.onerror = function() {
            this.src = 'img/default-package.jpg';
            this.onerror = null;
        };

        // Fetch package details using AJAX with timeout
        const fetchTimeout = setTimeout(() => {
            modalTitle.textContent = 'Error loading data';
            modalDetails.textContent = 'Request timed out. Please try again later.';
            modalCapacity.textContent = 'Not available';
        }, 10000); // 10 second timeout

        fetch(`get_package_details.php?id=${packageId}`)
            .then(response => {
                clearTimeout(fetchTimeout);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                modalTitle.textContent = data.name || 'Package Details';
                modalDetails.textContent = data.description || 'No description available';
                modalPrice.textContent = `₱${number_format(parseFloat(data.price || 0))}`;
                
                // Store the package name for booking
                current_package_name = data.name;
                
                // Update the booking link with the package name
                if (bookingLink && current_package_name) {
                    bookingLink.href = `users/booking-form.php?package=${encodeURIComponent(current_package_name)}`;
                }
                
                // Update guest capacity info
                if (data.guest_capacity) {
                    modalCapacity.textContent = `${data.guest_capacity} guests`;
                } else {
                    modalCapacity.textContent = 'Not specified';
                }

                // Update inclusions
                if (data.inclusions) {
                    modalInclusions.innerHTML = data.inclusions.split('\n').map(inclusion => `
                        <li class="flex items-start mb-2 last:mb-0">
                            <i class="fas fa-check-circle text-green-500 mt-0.5 mr-2"></i>
                            <span>${inclusion.trim()}</span>
                        </li>
                    `).join('');
                } else {
                    modalInclusions.innerHTML = '<li class="flex items-center text-gray-500"><i class="fas fa-info-circle text-gray-400 mr-2"></i>No inclusions specified</li>';
                }

                // Store terms data for the terms modal
                modalTerms.dataset.fullTerms = data.terms || 'No terms specified';
                
                // Show only the preview button initially
                modalTerms.innerHTML = `
                    <div class="flex justify-between items-center">
                        <span class="line-clamp-1">Click to view full terms and conditions</span>
                        <i class="fas fa-external-link-alt text-indigo-600"></i>
                    </div>
                `;
            })
            .catch(error => {
                clearTimeout(fetchTimeout);
                console.error('Error:', error);
                modalTitle.textContent = 'Error Loading Package';
                modalDetails.textContent = 'Failed to load package details. Please try again later.';
                modalInclusions.innerHTML = '<li class="flex items-center text-gray-500"><i class="fas fa-exclamation-circle text-red-400 mr-2"></i>Unable to load inclusions</li>';
                modalCapacity.textContent = 'Not available';
            });
    }

    function closeModal() {
        const modal = document.getElementById('modal');
        const modalContent = modal.querySelector('.bg-white');
        
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            // Restore normal scrolling
            document.body.style.overflow = '';
        }, 300);
    }

    // Add this helper function for number formatting
    function number_format(number) {
        return new Intl.NumberFormat('en-US', {
            maximumFractionDigits: 0
        }).format(number);
    }

    function openTermsModal() {
        const termsModal = document.getElementById('termsModal');
        const termsContent = document.getElementById('termsContent');
        const modalTerms = document.getElementById('modal-terms');
        const modalContent = termsModal.querySelector('.transform');
        
        // Get the full terms from the data attribute
        const terms = modalTerms.dataset.fullTerms;
        
        // Format the terms content with proper styling
        if (terms && terms !== 'No terms specified') {
            termsContent.innerHTML = `
                <div class="space-y-4">
                    ${terms.split('\n').map(paragraph => 
                        paragraph.trim() ? `<p class="mb-4">${paragraph}</p>` : ''
                    ).join('')}
                </div>
            `;
        } else {
            termsContent.innerHTML = '<p class="text-gray-500 italic">No terms specified</p>';
        }
        
        // Show the modal
        termsModal.classList.remove('hidden');
        
        // Animate the modal
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
        
        document.body.style.overflow = 'hidden';
    }

    function closeTermsModal() {
        const termsModal = document.getElementById('termsModal');
        termsModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('modal');
        const termsModal = document.getElementById('termsModal');
        
        if (event.target === modal) {
            closeModal();
        }
        
        if (event.target === termsModal) {
            closeTermsModal();
        }
    };

    // Check login status before booking
    function checkLoginAndBook() {
        <?php if (!isset($_SESSION['user_id'])): ?>
            alert('Please login first to make a booking.');
            window.location.href = 'login.php';
            return false;
        <?php endif; ?>
        return true;
    }
</script>

<?php $conn->close(); ?>
</div> <!-- End of main-content container -->

<!-- Loading screen handler -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const loadingScreen = document.getElementById('loading-screen');
        const mainContent = document.getElementById('main-content');
        const loadingPercentage = document.getElementById('loading-percentage');
        
        // Update percentage counter
        let percent = 0;
        const percentInterval = setInterval(function() {
            percent += 1;
            if (percent <= 100) {
                loadingPercentage.textContent = percent + '%';
            } else {
                clearInterval(percentInterval);
            }
        }, 30); // Updates every 30ms to reach 100% in 3 seconds
        
        // Simulate 3 second loading time
        setTimeout(function() {
            // Ensure we're at 100%
            loadingPercentage.textContent = '100%';
            
            // Hide loading screen with fade out
            loadingScreen.style.opacity = '0';
            loadingScreen.style.visibility = 'hidden';
            
            // Show main content
            mainContent.classList.remove('content-hidden');
            mainContent.classList.add('content-appear');
            
            // Remove loading screen after animation completes
            setTimeout(function() {
                loadingScreen.remove();
            }, 500);
            
            // Stop the percentage interval if it's still running
            clearInterval(percentInterval);
        }, 3000); // 3 seconds
    });
</script>
</body>
</html>