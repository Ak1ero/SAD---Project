<?php
session_start();
include '../db/config.php';

// Fetch all event packages
$sql = "SELECT *, image_path FROM event_packages";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packages - The Barn & Backyard</title>
    <link rel="icon" href="../img/barn-backyard.svg" type="image/svg+xml"/>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        h1, h2, h3 {
            font-family: 'Playfair Display', serif;
        }
        .package-card {
            transition: all 0.3s ease;
        }
        .package-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .hero-section {
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../img/e-1.jpg');
            background-size: cover;
            background-position: center;
            height: 400px;
        }
        
        /* Adding CSS variables and additional styles from index.php */
        :root {
            --primary-color: #5D5FEF;
            --secondary-color: #E0E7FF;
            --accent-color: #3F3D56;
            --light-bg: #F9FAFB;
            --header-font: 'Playfair Display', serif;
            --body-font: 'Poppins', sans-serif;
        }
        
        /* Modal styles */
        #modal, #termsModal {
            overscroll-behavior: contain;
        }
        
        #modal > div, #termsModal > div {
            margin: 1.5rem auto;
            max-height: calc(100vh - 3rem);
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
            background-color: #5D5FEF;
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .nav-link.active::after {
            width: 100%;
        }
        
        /* Primary button style */
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
        
        .transition-all {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Animation classes */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }   
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes shimmer {
            0% { background-position: -468px 0; }
            100% { background-position: 468px 0; }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .animate-pulse {
            animation: pulse 1.5s infinite;
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.8s ease forwards;
        }
        
        .staggered-fade-in > * {
            opacity: 0;
            animation: fadeIn 0.5s ease forwards;
        }
        
        .staggered-fade-in > *:nth-child(1) { animation-delay: 0.1s; }
        .staggered-fade-in > *:nth-child(2) { animation-delay: 0.2s; }
        .staggered-fade-in > *:nth-child(3) { animation-delay: 0.3s; }
        .staggered-fade-in > *:nth-child(4) { animation-delay: 0.4s; }
        .staggered-fade-in > *:nth-child(5) { animation-delay: 0.5s; }
        .staggered-fade-in > *:nth-child(6) { animation-delay: 0.6s; }
        
        /* Shimmer effect for loading states */
        .shimmer {
            background: #f6f7f8;
            background-image: linear-gradient(
                to right, 
                #f6f7f8 0%, 
                #edeef1 20%, 
                #f6f7f8 40%, 
                #f6f7f8 100%
            );
            background-repeat: no-repeat;
            background-size: 800px 104px;
            animation: shimmer 1.5s infinite linear;
        }
        
        /* Toast notification styles */
        .toast {
            transform: translateX(120%);
            animation: toast-in 0.3s ease forwards;
        }
        
        @keyframes toast-in {
            to { transform: translateX(0); }
        }
        
        .toast.toast-out {
            animation: toast-out 0.3s ease forwards;
        }
        
        @keyframes toast-out {
            to { transform: translateX(120%); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation (Same as index.php) -->
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
                    <a href="packages.php" class="py-2 text-gray-800 font-medium nav-link active">Packages</a>
                    <a href="gallery.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Gallery</a>
                    <a href="about.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">About</a>
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
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section flex items-center justify-center" style="background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../img/e-1.jpg');">
        <div class="text-center text-white">
            <h1 class="text-5xl font-bold mb-4">Our Event Packages</h1>
            <p class="text-xl max-w-2xl mx-auto">Discover our carefully curated selection of event packages designed to make your special day perfect.</p>
        </div>
    </div>

    <!-- Packages Section -->
    <div class="py-16 px-4 bg-gradient-to-b from-gray-50 to-white">
        <div class="max-w-7xl mx-auto">
            <!-- Section Header -->
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">Select Your Perfect Package</h2>
                <div class="w-24 h-1 bg-primary-600 mx-auto mb-6 rounded-full" style="background-color: var(--primary-color);"></div>
                <p class="text-gray-600 max-w-2xl mx-auto">Each package is thoughtfully designed to provide everything you need for an unforgettable event experience at The Barn & Backyard.</p>
            </div>
            
            <!-- Packages Grid -->
            <?php if ($result->num_rows > 0): ?>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 staggered-fade-in">
                    <?php while($package = $result->fetch_assoc()): ?>
                        <div class="bg-white rounded-xl shadow-lg overflow-hidden package-card group hover:scale-[1.02] transition-all duration-300 flex flex-col">
                            <!-- Image Container with Overlay -->
                            <div class="relative h-72 overflow-hidden">
                                <!-- Image -->
                                <img src="../<?php echo htmlspecialchars($package['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($package['name']); ?>" 
                                     class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                            </div>
                            
                            <!-- Content -->
                            <div class="p-6 flex flex-col flex-grow">
                                <h3 class="text-2xl font-bold mb-3 text-gray-800"><?php echo htmlspecialchars($package['name']); ?></h3>
                                
                                <!-- Description with line clamp -->
                                <p class="text-gray-600 mb-5 line-clamp-2"><?php echo htmlspecialchars($package['description']); ?></p>
                                
                                <!-- Price -->
                                <p class="text-xl font-bold text-gray-800 mb-4">₱<?php echo number_format($package['price'], 2); ?></p>
                                
                                <!-- Spacer to push button to bottom -->
                                <div class="flex-grow"></div>
                                
                                <!-- Action Button -->
                                <button onclick="openModal('<?php echo $package['id']; ?>', '../<?php echo $package['image_path']; ?>')" 
                                        class="w-full py-3 btn-primary rounded-lg hover:bg-primary-700 transition-all flex items-center justify-center space-x-2 group" style="background-color: var(--primary-color);">
                                    <span>View Package Details</span>
                                    <i class="fas fa-arrow-right transform group-hover:translate-x-1 transition-transform"></i>
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="py-16 text-center bg-gray-50 rounded-xl border border-gray-100 animate-fade-in">
                    <i class="fas fa-box-open text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Packages Available</h3>
                    <p class="text-gray-500">We're currently updating our package offerings. Please check back soon.</p>
                </div>
            <?php endif; ?>
            
            <!-- Additional Info Section -->
            <div class="mt-16 bg-gray-50 rounded-xl p-8 border border-gray-100 animate-fade-in-up">
                <div class="grid md:grid-cols-3 gap-8 staggered-fade-in">
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto bg-blue-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-calendar-check text-2xl text-blue-600"></i>
                        </div>
                        <h3 class="font-semibold text-gray-800 mb-2">Easy Booking</h3>
                        <p class="text-gray-600 text-sm">Simple process to secure your date and package of choice.</p>
                    </div>
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto bg-purple-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-star text-2xl text-purple-600"></i>
                        </div>
                        <h3 class="font-semibold text-gray-800 mb-2">Premium Experience</h3>
                        <p class="text-gray-600 text-sm">All packages designed to exceed your expectations.</p>
                    </div>
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto bg-green-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-sliders-h text-2xl text-green-600"></i>
                        </div>
                        <h3 class="font-semibold text-gray-800 mb-2">Customizable Options</h3>
                        <p class="text-gray-600 text-sm">Tailor any package to fit your specific needs and preferences.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Package Details Modal -->
    <div id="modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-60 backdrop-filter backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-8 max-h-[85vh] overflow-y-auto transform transition-all duration-300 ease-in-out">
            <!-- Modal Header with Image -->
            <div class="relative h-56 md:h-64">
                <img id="modal-image" src="" alt="Package Image" class="w-full h-full object-cover">
                
                <!-- Close Button -->
                <button onclick="closeModal()" class="absolute top-4 right-4 bg-white/20 backdrop-blur-sm rounded-full p-2.5 hover:bg-white/40 transition-all duration-300 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                
                <!-- Package Title & Price -->
                <div class="absolute bottom-0 left-0 right-0 p-6 text-white bg-black/50">
                    <div class="max-w-xl">
                        <h3 id="modal-title" class="text-2xl md:text-3xl font-bold mb-2"></h3>
                        <div class="flex items-center">
                            <div id="modal-price" class="text-xl font-semibold mr-3"></div>
                            <div class="flex items-center text-sm bg-white/20 backdrop-blur-sm px-3 py-1 rounded-full">
                                <i class="fas fa-calendar-check mr-2"></i>
                                <span>Available for Booking</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 md:p-8 overflow-y-auto">
                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Main Details Column -->
                    <div class="md:col-span-2 space-y-6">
                        <!-- Package Description -->
                        <div>
                            <h4 class="text-lg font-semibold mb-3 text-gray-800 flex items-center">
                                <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                                Package Description
                            </h4>
                            <div id="modal-details" class="text-gray-600 space-y-3 leading-relaxed"></div>
                        </div>
                        
                        <!-- What's Included -->
                        <div>
                            <h4 class="text-lg font-semibold mb-3 text-gray-800 flex items-center">
                                <i class="fas fa-check-circle mr-2 text-green-600"></i>
                                What's Included
                            </h4>
                            <ul id="modal-inclusions" class="space-y-2 text-gray-600"></ul>
                        </div>
                    </div>
                    
                    <!-- Right Column - Terms and Trust Badge -->
                    <div class="space-y-6">
                        <!-- Guest Capacity -->
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-users mr-2 text-indigo-600"></i>
                                Guest Capacity
                            </h4>
                            <div class="p-3 bg-indigo-50 rounded-lg border border-indigo-100 text-center">
                                <p id="modal-capacity" class="text-gray-700 font-medium text-lg">Loading...</p>
                                <p class="text-xs text-gray-500 mt-1">Maximum number of guests</p>
                            </div>
                        </div>
                        
                        <!-- Terms & Conditions -->
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-file-contract mr-2 text-purple-600"></i>
                                Terms & Conditions
                            </h4>
                            <button onclick="openTermsModal()" class="w-full">
                                <div id="modal-terms" class="p-3 bg-gray-50 rounded-lg text-sm text-gray-600 border border-gray-100 hover:bg-gray-100 transition-all duration-300 text-left">
                                    <div class="flex justify-between items-center">
                                        <span class="line-clamp-1">Click to view full terms and conditions</span>
                                        <i class="fas fa-external-link-alt ml-2 text-gray-400"></i>
                                    </div>
                                </div>
                            </button>
                        </div>
                        
                        <!-- Reviews Section - Simplified button only -->
                        <div class="border-t border-gray-100 pt-6 pb-3">
                            <button onclick="openReviewModal()" class="w-full py-3 px-4 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg transition-all flex items-center justify-center space-x-2">
                                <i class="fas fa-star text-yellow-400 mr-2"></i>
                                <span>Write a Review</span>
                            </button>
                        </div>
                        
                        <!-- Trust Badges -->
                        <div class="flex justify-center space-x-6 pt-4 border-t">
                            <div class="flex items-center justify-center space-x-3">
                                <i class="fas fa-shield-alt text-amber-500"></i>
                                <span class="text-xs font-medium text-gray-800">100% Secure Booking</span>
                            </div>
                            <div class="text-xs text-center text-gray-500">
                                Your booking is protected with our secure payment processing and booking guarantee
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="border-t px-6 md:px-8 py-4 bg-gray-50 sticky bottom-0">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../users/booking-form.php?package=<?php echo urlencode($current_package_name); ?>" class="w-full bg-primary-600 text-white font-semibold py-3 px-6 rounded-xl hover:bg-primary-700 transition-all duration-300 flex items-center justify-center space-x-2 shadow-md" style="background-color: var(--primary-color); --tw-shadow-color: rgba(93, 95, 239, 0.3);">
                        <span>Book This Package Now</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <a href="../login.php" class="w-full bg-primary-600 text-white font-semibold py-3 px-6 rounded-xl hover:bg-primary-700 transition-all duration-300 flex items-center justify-center space-x-2 shadow-md" style="background-color: var(--primary-color); --tw-shadow-color: rgba(93, 95, 239, 0.3);">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        <span>Login to Book</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div id="termsModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-60 backdrop-filter backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-8 max-h-[85vh] overflow-y-auto transform transition-all duration-300 ease-in-out">
            <!-- Modal Header -->
            <div class="relative bg-purple-800 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-file-contract mr-3"></i>
                        Terms & Conditions
                    </h3>
                    <button onclick="closeTermsModal()" class="text-gray-300 hover:text-white transition-colors rounded-full p-1 focus:outline-none">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <div class="mt-2">
                    <p class="text-sm text-gray-300">
                        Please review the following terms and conditions for package booking
                    </p>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto max-h-[calc(85vh-140px)]">
                <div id="termsContent" class="prose max-w-none text-gray-600 space-y-4">
                    <!-- Terms content will be inserted here -->
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                <button onclick="closeTermsModal()" class="px-5 py-2 rounded-lg bg-purple-800 text-white hover:bg-purple-700 transition-all flex items-center space-x-2">
                    <span>Close</span>
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-60 backdrop-filter backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md my-8 max-h-[85vh] overflow-y-auto transform transition-all duration-300 ease-in-out">
            <!-- Modal Header -->
            <div class="relative bg-indigo-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-star mr-3"></i>
                        Share Your Experience
                    </h3>
                    <button onclick="closeReviewModal()" class="text-gray-300 hover:text-white transition-colors rounded-full p-1 focus:outline-none">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <div class="mt-2">
                    <p class="text-sm text-gray-300">
                        Your review helps others learn about our packages
                    </p>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6">
                <?php if(isset($_SESSION['user_id'])): ?>
                <form id="review-form" class="space-y-4">
                    <input type="hidden" id="review-package-id" name="package_id" value="">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rating</label>
                        <div class="star-rating editable flex">
                            <input type="hidden" name="rating" id="rating_value" value="5">
                            <span class="star" data-value="1"><i class="fas fa-star text-yellow-400 text-xl"></i></span>
                            <span class="star" data-value="2"><i class="fas fa-star text-yellow-400 text-xl"></i></span>
                            <span class="star" data-value="3"><i class="fas fa-star text-yellow-400 text-xl"></i></span>
                            <span class="star" data-value="4"><i class="fas fa-star text-yellow-400 text-xl"></i></span>
                            <span class="star" data-value="5"><i class="fas fa-star text-yellow-400 text-xl"></i></span>
                        </div>
                    </div>
                    
                    <div>
                        <label for="review_text" class="block text-sm font-medium text-gray-700 mb-2">Your Review</label>
                        <textarea id="review_text" name="review_text" rows="5" required
                                  class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                  placeholder="Share your experience with this package"></textarea>
                    </div>
                    
                    <button type="submit" class="w-full py-3 px-6 text-white bg-indigo-600 hover:bg-indigo-700 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all flex items-center justify-center">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Submit Review
                    </button>
                </form>
                <?php else: ?>
                <div class="bg-blue-50 border border-blue-100 rounded-lg p-6 text-center">
                    <i class="fas fa-user-lock text-blue-500 text-4xl mb-4"></i>
                    <h4 class="text-lg font-medium text-gray-800 mb-2">Login Required</h4>
                    <p class="text-gray-600 mb-6">Please log in to share your experience with our services</p>
                    <a href="../login.php" class="inline-block py-3 px-6 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Login Now
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Modal Footer (Only shown for logged in users) -->
            <?php if(isset($_SESSION['user_id'])): ?>
            <div class="px-6 py-4 bg-gray-50 border-t flex justify-between items-center">
                <button onclick="closeReviewModal()" class="text-gray-500 hover:text-gray-700 font-medium">
                    Cancel
                </button>
                <div class="text-xs text-gray-500">
                    <i class="fas fa-lock-alt mr-1"></i>
                    Your review will be posted publicly
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Global variable to store current package name
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

            // Show the modal with loading state
            modal.classList.remove('hidden');
            
            // Reset modal scroll position
            setTimeout(() => {
                modal.scrollTop = 0;
                modalContent.scrollTop = 0;
            }, 10);
            
            // Allow modal to scroll but prevent body scrolling
            document.body.style.overflow = 'hidden';
            modal.style.overflow = 'auto';
            
            // Set loading states
            modalTitle.innerHTML = '<div class="h-6 bg-gray-300 animate-pulse rounded w-3/4"></div>';
            modalDetails.innerHTML = `
                <div class="h-4 bg-gray-200 animate-pulse rounded w-full mb-2"></div>
                <div class="h-4 bg-gray-200 animate-pulse rounded w-full mb-2"></div>
                <div class="h-4 bg-gray-200 animate-pulse rounded w-5/6"></div>
            `;
            modalPrice.innerHTML = '<div class="h-6 bg-gray-300 animate-pulse rounded w-24"></div>';
            modalCapacity.innerHTML = '<div class="h-6 bg-gray-300 animate-pulse rounded w-16 mx-auto"></div>';

            // Set the image path directly (with fade-in effect)
            modalImage.style.opacity = '0';
            modalImage.src = imagePath;
            modalImage.onload = function() {
                modalImage.style.transition = 'opacity 0.5s ease';
                modalImage.style.opacity = '1';
            };

            // Fetch package details using AJAX
            fetch(`../get_package_details.php?id=${packageId}`)
                .then(response => response.json())
                .then(data => {
                    // Update content with fade-in effect
                    setTimeout(() => {
                        // Package title with animation
                        modalTitle.textContent = data.name;
                        modalTitle.classList.add('animate-fade-in');
                        
                        // Store the package name globally for the booking link
                        current_package_name = data.name;
                        
                        // Update booking link with the current package
                        const bookingLink = document.querySelector('.border-t.px-6.md\\:px-8.py-4 a');
                        if (bookingLink && current_package_name) {
                            bookingLink.href = `../users/booking-form.php?package=${encodeURIComponent(current_package_name)}`;
                        }
                        
                        // Format and set price
                        modalPrice.textContent = `₱${formatNumber(parseFloat(data.price))}`;
                        
                        // Update guest capacity
                        if (data.guest_capacity && data.guest_capacity > 0) {
                            modalCapacity.textContent = `${data.guest_capacity} guests`;
                        } else {
                            modalCapacity.textContent = 'Not specified';
                        }
                        
                        // Set package description with paragraph spacing
                        if (data.description) {
                            modalDetails.innerHTML = data.description
                                .split('\n\n')
                                .map(paragraph => `<p>${paragraph.trim()}</p>`)
                                .join('');
                        } else {
                            modalDetails.textContent = 'No description available for this package.';
                        }

                        // Update inclusions with icon and formatting
                        if (data.inclusions) {
                            modalInclusions.innerHTML = data.inclusions
                                .split('\n')
                                .filter(item => item.trim().length > 0)
                                .map(inclusion => `
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-green-600 mt-1 mr-3"></i>
                                        <span>${inclusion.trim()}</span>
                                    </li>
                                `).join('');
                        } else {
                            modalInclusions.innerHTML = '<li class="text-gray-500 italic">Inclusions will be discussed upon booking</li>';
                        }

                        // Store terms data for the terms modal
                        modalTerms.dataset.fullTerms = data.terms || 'No specific terms have been specified for this package. Standard venue terms and conditions apply.';
                        
                        // Set the review package ID for later use
                        document.getElementById('review-package-id').value = packageId;
                    }, 300); // Short delay for loading effect
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalTitle.textContent = 'Error Loading Details';
                    modalDetails.innerHTML = '<p class="text-red-500">We encountered a problem loading the package details. Please try again later or contact us directly.</p>';
                    modalCapacity.textContent = 'Not available';
                });
        }

        function closeModal() {
            const modal = document.getElementById('modal');
            modal.classList.add('hidden');
            document.body.style.overflow = ''; // Restore scrolling
        }

        function openTermsModal() {
            const termsModal = document.getElementById('termsModal');
            const termsContent = document.getElementById('termsContent');
            const modalTerms = document.getElementById('modal-terms');
            const termsModalContent = termsModal.querySelector('.bg-white');
            
            const terms = modalTerms.dataset.fullTerms;
            
            // Show loading state
            termsModal.classList.remove('hidden');
            
            // Reset scroll position
            setTimeout(() => {
                termsModal.scrollTop = 0;
                termsModalContent.scrollTop = 0;
            }, 10);
            
            // Allow modal to scroll but prevent body scrolling
            document.body.style.overflow = 'hidden';
            termsModal.style.overflow = 'auto';
            
            // Display terms with better formatting
            if (terms && terms !== 'No terms specified') {
                // Process terms content with better formatting
                const formattedTerms = terms
                    .split('\n\n')
                    .map((section, index) => {
                        // Check if this looks like a section header
                        if (section.length < 100 && !section.includes('.')) {
                            return `<h3 class="text-lg font-semibold text-gray-800 mt-6 mb-2">${section}</h3>`;
                        }
                        
                        // Process paragraphs or bullet points
                        return `<div class="mb-4">
                            ${section.split('\n')
                                .map(line => {
                                    line = line.trim();
                                    // Check for bullet points or numbered lists
                                    if (line.startsWith('-') || line.startsWith('•')) {
                                        return `<div class="flex items-start mb-2">
                                            <span class="text-primary-600 mr-2" style="color: var(--primary-color);">•</span>
                                            <span>${line.substring(1).trim()}</span>
                                        </div>`;
                                    } else if (/^\d+\./.test(line)) {
                                        const num = line.match(/^\d+/)[0];
                                        return `<div class="flex items-start mb-2">
                                            <span class="text-primary-600 mr-2 font-medium" style="color: var(--primary-color);">${num}.</span>
                                            <span>${line.substring(num.length + 1).trim()}</span>
                                        </div>`;
                                    }
                                    return `<p>${line}</p>`;
                                }).join('')}
                        </div>`;
                    }).join('');

                termsContent.innerHTML = formattedTerms;
            } else {
                termsContent.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-file-alt text-gray-300 text-5xl mb-4"></i>
                        <p class="text-gray-500 italic">No specific terms have been specified for this package.</p>
                        <p class="text-gray-500 mt-2">Standard venue terms and conditions apply.</p>
                    </div>
                `;
            }
        }

        function closeTermsModal() {
            const termsModal = document.getElementById('termsModal');
            termsModal.classList.add('hidden');
            document.body.style.overflow = ''; // Restore scrolling
        }

        // Format number with commas and proper decimal places
        function formatNumber(number) {
            return new Intl.NumberFormat('en-PH', {
                maximumFractionDigits: 0
            }).format(number);
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('modal');
            const termsModal = document.getElementById('termsModal');
            const reviewModal = document.getElementById('reviewModal');
            
            if (event.target === modal) {
                closeModal();
            }
            
            if (event.target === termsModal) {
                closeTermsModal();
            }
            
            if (event.target === reviewModal) {
                closeReviewModal();
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
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

                // Close menu when clicking outside
                document.addEventListener('click', function(e) {
                    if (!userMenuContainer.contains(e.target)) {
                        userMenuDropdown.classList.add('hidden', 'opacity-0', 'scale-95');
                        userMenuDropdown.classList.remove('opacity-100', 'scale-100');
                    }
                });

                // Prevent menu from closing when clicking inside dropdown
                userMenuDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });

        // Add these new functions for handling the review modal
        function openReviewModal() {
            const reviewModal = document.getElementById('reviewModal');
            reviewModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent body scrolling
        }
        
        function closeReviewModal() {
            const reviewModal = document.getElementById('reviewModal');
            reviewModal.classList.add('hidden');
            document.body.style.overflow = ''; // Restore body scrolling
        }

        // Update the review form submission handler
        document.getElementById('review-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const packageId = document.getElementById('review-package-id').value;
            const rating = document.getElementById('rating_value').value;
            const reviewText = document.getElementById('review_text').value;
            
            // Create form data
            const formData = new FormData();
            formData.append('package_id', packageId);
            formData.append('rating', rating);
            formData.append('review_text', reviewText);
            
            // Submit the review
            fetch('../submit_review.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear form
                    document.getElementById('review_text').value = '';
                    
                    // Close the review modal
                    closeReviewModal();
                    
                    // Show success message with custom toast
                    showToast('success', 'Thank you for your review!', 'Your feedback helps others make better decisions.');
                } else {
                    showToast('error', 'Error', data.message || 'Error submitting review. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Something went wrong', 'An error occurred while submitting your review. Please try again later.');
            });
        });
        
        // Star rating functionality
        const stars = document.querySelectorAll('.star-rating.editable .star');
        const ratingInput = document.getElementById('rating_value');
        
        stars.forEach(star => {
            star.addEventListener('mouseover', () => {
                const rating = parseInt(star.getAttribute('data-value'));
                highlightStars(rating);
            });
            
            star.addEventListener('mouseout', () => {
                const currentRating = parseInt(ratingInput.value);
                highlightStars(currentRating);
            });
            
            star.addEventListener('click', () => {
                const rating = parseInt(star.getAttribute('data-value'));
                ratingInput.value = rating;
                highlightStars(rating);
            });
        });
        
        function highlightStars(rating) {
            stars.forEach(star => {
                const starValue = parseInt(star.getAttribute('data-value'));
                if (starValue <= rating) {
                    star.querySelector('i').classList.add('text-yellow-400');
                    star.querySelector('i').classList.remove('text-gray-300');
                } else {
                    star.querySelector('i').classList.remove('text-yellow-400');
                    star.querySelector('i').classList.add('text-gray-300');
                }
            });
        }

        // Toast notification functions
        function showToast(type, title, message, duration = 5000) {
            const toastContainer = document.getElementById('toast-container');
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast flex items-center p-4 mb-3 shadow-lg rounded-lg ${type === 'success' ? 'bg-green-50 border-l-4 border-green-500' : 'bg-red-50 border-l-4 border-red-500'}`;
            
            // Toast content
            toast.innerHTML = `
                <div class="flex-shrink-0 mr-4">
                    ${type === 'success' 
                        ? '<i class="fas fa-check-circle text-3xl text-green-500"></i>' 
                        : '<i class="fas fa-exclamation-circle text-3xl text-red-500"></i>'}
                </div>
                <div class="flex-grow">
                    <h4 class="text-sm font-semibold ${type === 'success' ? 'text-green-800' : 'text-red-800'}">${title}</h4>
                    <p class="text-xs mt-1 ${type === 'success' ? 'text-green-600' : 'text-red-600'}">${message}</p>
                </div>
                <button class="flex-shrink-0 ml-2 text-gray-400 hover:text-gray-500" onclick="this.parentElement.classList.add('toast-out');">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            // Add to container
            toastContainer.appendChild(toast);
            
            // Auto-remove after duration
            setTimeout(() => {
                toast.classList.add('toast-out');
                // Remove from DOM after animation completes
                setTimeout(() => toast.remove(), 300);
            }, duration);
            
            // Remove on click of X button
            toast.querySelector('button').addEventListener('click', () => {
                setTimeout(() => toast.remove(), 300);
            });
        }
    </script>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-3 max-w-md"></div>

</body>
</html>

<?php $conn->close(); ?>