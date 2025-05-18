<?php
session_start();
include '../db/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - The Barn & Backyard</title>
    <link rel="icon" href="../img/barn-backyard.svg" type="image/svg+xml"/>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        h1, h2, h3 {
            font-family: 'Playfair Display', serif;
        }
        
        :root {
            --primary-color: #6366F1;
            --primary-dark: #4F46E5;
            --primary-light: #EEF2FF;
            --secondary-color: #E0E7FF;
            --accent-color: #3F3D56;
            --light-bg: #F9FAFB;
            --dark-text: #1F2937;
            --light-text: #9CA3AF;
            --header-font: 'Playfair Display', serif;
            --body-font: 'Poppins', sans-serif;
            --transition-normal: all 0.3s ease;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Nav styling */
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
        
        /* Button styling */
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            transition: var(--transition-normal);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Hero section */
        .hero-about {
            background-image: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('../img/e-1.jpg');
            background-size: cover;
            background-position: center;
            height: 400px;
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: var(--primary-color);
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 0.5rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background-color: white;
            border: 2px solid var(--primary-color);
        }
        
        /* Team members */
        .team-member {
            transition: var(--transition-normal);
        }
        
        .team-member:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            opacity: 0;
            animation: fadeIn 0.8s ease forwards;
        }
        
        .delay-1 { animation-delay: 0.2s; }
        .delay-2 { animation-delay: 0.4s; }
        .delay-3 { animation-delay: 0.6s; }
        .delay-4 { animation-delay: 0.8s; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="sticky top-0 z-50 backdrop-filter backdrop-blur-lg bg-white bg-opacity-90 shadow-md transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="../index.php" class="flex items-center">
                        <span class="font-bold text-2xl text-gray-800">The Barn & Backyard</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="../index.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Home</a>
                    <a href="reviews.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Reviews</a>
                    <a href="packages.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Packages</a>
                    <a href="gallery.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Gallery</a>
                    <a href="about.php" class="py-2 text-gray-800 font-medium nav-link active">About</a>
                </div>

                <!-- User Menu -->
                <div class="md:flex items-center">
                    <?php if (isset($_SESSION['user_name'])): ?>
                        <div class="relative" id="userMenuContainer">
                            <button id="userMenuButton" class="flex items-center space-x-2 px-4 py-2 rounded-full bg-white shadow-sm hover:shadow-md transition-all text-gray-800 font-medium focus:outline-none">
                                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div id="userMenuDropdown" class="absolute right-0 mt-3 w-48 bg-white rounded-xl shadow-xl py-2 hidden transform transition-all opacity-0 scale-95">
                                <a href="../users/profile.php" class="flex items-center px-4 py-3 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-user-circle mr-2 text-gray-600"></i>
                                    My Profile
                                </a>
                                <a href="../users/my-bookings.php" class="flex items-center px-4 py-3 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-calendar-check mr-2 text-gray-600"></i>
                                    My Bookings
                                </a>
                                <div class="border-t border-gray-200 my-1"></div>
                                <a href="../logout.php" class="flex items-center px-4 py-3 text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt mr-2"></i>
                                    Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="../login.php" class="py-2 px-5 btn-primary rounded-full shadow-md transform transition-all duration-300 flex items-center space-x-2">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Login / Signup</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button id="mobileMenuButton" class="p-2 rounded-lg hover:bg-gray-100 transition-all focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Mobile menu -->
            <div id="mobileMenu" class="md:hidden hidden py-4 border-t border-gray-100">
                <div class="flex flex-col space-y-3">
                    <a href="../index.php" class="py-3 px-4 text-gray-600 font-medium rounded-lg hover:bg-gray-100">Home</a>
                    <a href="reviews.php" class="py-3 px-4 text-gray-600 font-medium rounded-lg hover:bg-gray-100">Reviews</a>
                    <a href="packages.php" class="py-3 px-4 text-gray-600 font-medium rounded-lg hover:bg-gray-100">Packages</a>
                    <a href="gallery.php" class="py-3 px-4 text-gray-600 font-medium rounded-lg hover:bg-gray-100">Gallery</a>
                    <a href="about.php" class="py-3 px-4 text-gray-800 font-medium rounded-lg bg-gray-100">About</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-about flex items-center justify-center">
        <div class="container mx-auto text-center text-white px-4">
            <h1 class="text-5xl font-bold mb-4">Our Vision & Mission</h1>
            <p class="text-xl mb-8 max-w-2xl mx-auto">Revolutionizing event planning in South Cotabato through technology and exceptional service</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-16">
        <!-- Our Purpose Section -->
        <div class="max-w-4xl mx-auto mb-20">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Why We Exist</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto">Creating an excellent bridge between beautiful venues and memorable celebrations</p>
            </div>
            
            <div class="prose prose-lg max-w-none text-gray-700">
                <p class="mb-6">We started The Barn & Backyard because we believe planning your special day should be filled with excitement, not stress. Picture this: you're planning your dream event, but instead of getting lost in endless venue searches and complicated bookings, you're actually enjoying the process. That's the experience we want to create for you.</p>
                
                <p class="mb-6">We're not just another booking platform  we're your event planning partner. Whether you're a couple planning your wedding, a family organizing a reunion, or a company hosting a corporate event, we're here to make your journey smooth and enjoyable. Our platform brings together amazing venues and creative planners, all while keeping things simple and stress free.</p>
                
                <p>At The Barn & Backyard, we're passionate about turning your event dreams into reality. From the moment you start browsing venues to the final celebration, we're with you every step of the way, making sure your special moments are as perfect as you imagined them.</p>
            </div>
        </div>
        
        <!-- Vision and Mission Section -->
        <div class="max-w-4xl mx-auto mb-20">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Our Vision & Mission</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto">Guiding principles that drive our commitment to excellence</p>
            </div>
            
            <div class="grid md:grid-cols-2 gap-12">
                <div class="bg-white p-8 rounded-xl shadow-md">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-eye text-indigo-500 mr-3 text-2xl"></i>
                        Our Vision
                    </h3>
                    <p class="text-gray-700">To become the leading event venue platform in South Cotabato, transforming how people plan and experience their special moments through innovative technology and exceptional service.</p>
                </div>
                
                <div class="bg-white p-8 rounded-xl shadow-md">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-bullseye text-indigo-500 mr-3 text-2xl"></i>
                        Our Mission
                    </h3>
                    <p class="text-gray-700">To simplify event planning by connecting people with perfect venues, providing intuitive tools, and delivering outstanding service that makes every celebration memorable.</p>
                </div>
            </div>
        </div>

        <!-- Core Values Section -->
        <div class="max-w-4xl mx-auto mb-20">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Our Core Values</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto">The principles that guide everything we do</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-xl shadow-md">
                    <div class="text-primary-600 mb-4">
                        <i class="fas fa-heart text-4xl text-indigo-500"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-3">Passion</h3>
                    <p class="text-gray-700">We are deeply committed to creating exceptional experiences and go above and beyond to make every event special.</p>
                </div>
                
                <div class="bg-white p-8 rounded-xl shadow-md">
                    <div class="text-primary-600 mb-4">
                        <i class="fas fa-lightbulb text-4xl text-indigo-500"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-3">Innovation</h3>
                    <p class="text-gray-700">We continuously seek new ways to improve and enhance the event planning experience through technology and creativity.</p>
                </div>
                
                <div class="bg-white p-8 rounded-xl shadow-md">
                    <div class="text-primary-600 mb-4">
                        <i class="fas fa-handshake text-4xl text-indigo-500"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-3">Integrity</h3>
                    <p class="text-gray-700">We operate with honesty, transparency, and respect in all our interactions with clients, partners, and team members.</p>
                </div>
            </div>
        </div>
        
        <!-- Our Technology Section -->
        <div class="max-w-4xl mx-auto mb-20">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Our Technology</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto">Innovative solutions designed for simplicity and efficiency</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-xl shadow-md hover:shadow-lg transition-all">
                    <div class="text-primary-600 mb-4">
                        <i class="fas fa-calendar-alt text-4xl text-indigo-500"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-3">Smart Booking System</h3>
                    <p class="text-gray-700">Our real-time availability calendar with instant confirmation eliminates double-bookings and streamlines the reservation process.</p>
                </div>
                
                <div class="bg-white p-8 rounded-xl shadow-md hover:shadow-lg transition-all">
                    <div class="text-primary-600 mb-4">
                        <i class="fas fa-credit-card text-4xl text-indigo-500"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-3">Secure Payments</h3>
                    <p class="text-gray-700">Our secure payment system offers flexible options with down payments and full payments. All transactions are protected with bank-level security for your peace of mind.</p>
                </div>
                
                <div class="bg-white p-8 rounded-xl shadow-md hover:shadow-lg transition-all">
                    <div class="text-primary-600 mb-4">
                        <i class="fas fa-mobile-alt text-4xl text-indigo-500"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-3">Mobile Optimization</h3>
                    <p class="text-gray-700">Plan your event on-the-go with our fully responsive platform that works seamlessly across all devices.</p>
                </div>
            </div>
        </div>           
            <div class="text-center mt-12">
                <p class="text-xl text-gray-700 italic">"Our vision is to make Polomolok South Cotabato not just a destination for beautiful events, but a model for how technology can transform an entire industry for the better."</p>
                <p class="mt-4 text-gray-600">— The Barn & Backyard Team</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Column 1: Contact & Social -->
                <div>
                    <h3 class="text-lg font-bold mb-4">The Barn & Backyard</h3>
                    <p class="mb-4 text-gray-400">Creating perfect moments for every occasion.</p>
                    <div class="flex space-x-4 mb-6">
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-twitter"></i>
                        </a>
                    </div>
                    <p class="text-gray-400 flex items-center mb-2">
                        <i class="fas fa-map-marker-alt mr-2 text-indigo-400"></i>
                        Pioneer Avenue, Polomolok, South Cotabato
                    </p>
                    <p class="text-gray-400 flex items-center">
                        <i class="fas fa-envelope mr-2 text-indigo-400"></i>
                        info@barnbackyard.com
                    </p>
                </div>
                
                <!-- Column 2: Quick Links -->
                <div>
                    <h3 class="text-lg font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="../index.php" class="text-gray-400 hover:text-white transition-colors">Home</a></li>
                        <li><a href="packages.php" class="text-gray-400 hover:text-white transition-colors">Packages</a></li>
                        <li><a href="gallery.php" class="text-gray-400 hover:text-white transition-colors">Gallery</a></li>
                        <li><a href="reviews.php" class="text-gray-400 hover:text-white transition-colors">Reviews</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-white transition-colors">About Us</a></li>
                    </ul>
                </div>
                
                <!-- Column 3: Newsletter -->
                <div>
                    <h3 class="text-lg font-bold mb-4">Newsletter</h3>
                    <p class="text-gray-400 mb-4">Subscribe to receive updates on new events and promotions.</p>
                    <form class="mb-4">
                        <div class="flex">
                            <input type="email" placeholder="Your email" class="px-4 py-2 rounded-l-lg w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <button type="submit" class="bg-indigo-600 text-white px-4 rounded-r-lg hover:bg-indigo-700 transition-colors">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-6 text-center">
                <p class="text-gray-500">© 2025 The Barn & Backyard Event Reservation System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // User Menu Toggle
        const userMenuButton = document.getElementById('userMenuButton');
        const userMenuDropdown = document.getElementById('userMenuDropdown');
        const userMenuContainer = document.getElementById('userMenuContainer');

        if (userMenuButton && userMenuDropdown && userMenuContainer) {
            // Toggle menu on button click
            userMenuButton.addEventListener('click', function(e) {
                e.stopPropagation();
                userMenuDropdown.classList.toggle('hidden');
                userMenuDropdown.classList.toggle('opacity-0');
                userMenuDropdown.classList.toggle('scale-95');
                userMenuDropdown.classList.toggle('opacity-100');
                userMenuDropdown.classList.toggle('scale-100');
            });

            // Close menu when clicking elsewhere
            document.addEventListener('click', function(e) {
                if (!userMenuContainer.contains(e.target)) {
                    userMenuDropdown.classList.add('hidden', 'opacity-0', 'scale-95');
                    userMenuDropdown.classList.remove('opacity-100', 'scale-100');
                }
            });
        }
        
        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileMenu = document.getElementById('mobileMenu');
        
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });
        }
        
        // Animations on scroll
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        const fadeElements = document.querySelectorAll('.fade-in');
        fadeElements.forEach(el => {
            el.style.animationPlayState = 'paused';
            observer.observe(el);
        });
    });
    </script>
</body>
</html>