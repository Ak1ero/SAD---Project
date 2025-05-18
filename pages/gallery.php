<?php
session_start();
include '../db/config.php';

// Function to get all images from a directory
function getGalleryImages($dir) {
    $images = [];
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            // Skip . and .. directories and non-image files
            if ($file != '.' && $file != '..' && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'])) {
                $images[] = $file;
            }
        }
    }
    return $images;
}

// Define our gallery categories
$categories = [
    'wedding' => 'Wedding Celebrations',
    'corporate' => 'Corporate Events',
    'birthday' => 'Birthday Parties',
    'debut' => 'Debut Celebrations',
    'christening' => 'Christening Events',
    'venue' => 'Venue Features'
];

// Get active category from URL parameter, default to 'all'
$activeCategory = isset($_GET['category']) ? $_GET['category'] : 'all';

// Validate the category
if ($activeCategory != 'all' && !array_key_exists($activeCategory, $categories)) {
    $activeCategory = 'all';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery - The Barn & Backyard</title>
    <link rel="icon" href="../img/barn-backyard.svg" type="image/svg+xml"/>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Add lightbox CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" />
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        h1, h2, h3 {
            font-family: 'Playfair Display', serif;
        }
        
        /* Core variables */
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
            --transition-slow: all 0.5s ease;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Navigation */
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
        
        /* Button styles */
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
        
        /* Gallery specific styles */
        .hero-gallery {
            background-image: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('../img/e-1.jpg');
            background-size: cover;
            background-position: center;
            height: 400px;
        }
        
        /* Gallery grid */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .gallery-item {
            position: relative;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: var(--transition-normal);
            cursor: pointer;
            height: 0;
            padding-bottom: 100%; /* Creates a square aspect ratio */
        }
        
        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }
        
        .gallery-item img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition-slow);
        }
        
        .gallery-item:hover img {
            transform: scale(1.05);
        }
        
        .gallery-item-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(0deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 100%);
            padding: 1.5rem 1rem;
            color: white;
            opacity: 0;
            transform: translateY(20px);
            transition: var(--transition-normal);
        }
        
        .gallery-item:hover .gallery-item-overlay {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Category filter pills */
        .category-filter {
            transition: var(--transition-normal);
            border: 1px solid #E5E7EB;
        }
        
        .category-filter.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .category-filter:hover:not(.active) {
            background-color: var(--primary-light);
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        /* 3D Carousel */
        .gallery-3d-carousel {
            position: relative;
            height: 600px;
            perspective: 1200px;
            transform-style: preserve-3d;
            margin: 0 auto;
            overflow: visible;
        }
        
        .gallery-carousel-container {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.8s ease;
        }
        
        .carousel-slide {
            position: absolute;
            width: 400px;
            height: 400px;
            left: 50%;
            top: 50%;
            background-size: cover;
            background-position: center;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5), 0 5px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.6s cubic-bezier(0.23, 1, 0.32, 1);
            overflow: hidden;
            cursor: pointer;
            transform-style: preserve-3d;
        }
        
        .carousel-slide::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.3) 100%);
            z-index: 1;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }
        
        .carousel-slide:hover::before {
            opacity: 0.3;
        }
        
        .carousel-slide.active {
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.6), 0 10px 25px rgba(0, 0, 0, 0.4);
        }
        
        .carousel-slide-content {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 2rem;
            color: white;
            z-index: 2;
        }
        
        .carousel-arrows {
            position: absolute;
            top: 50%;
            width: 100%;
            transform: translateY(-50%);
            display: flex;
            justify-content: space-between;
            z-index: 20;
            pointer-events: none;
        }
        
        .carousel-arrow {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            box-shadow: var(--shadow-md);
            cursor: pointer;
            pointer-events: auto;
            transition: var(--transition-normal);
            border: none;
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        
        .carousel-arrow:hover {
            background-color: white;
            transform: scale(1.1);
            box-shadow: var(--shadow-lg);
        }
        
        .carousel-arrow.prev {
            margin-left: 1rem;
        }
        
        .carousel-arrow.next {
            margin-right: 1rem;
        }
        
        .carousel-dots {
            position: absolute;
            bottom: 2rem;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            z-index: 20;
        }
        
        .carousel-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: var(--transition-normal);
        }
        
        .carousel-dot.active {
            background-color: white;
            transform: scale(1.2);
        }
        
        .carousel-dot:hover:not(.active) {
            background-color: rgba(255, 255, 255, 0.8);
        }
        
        /* Masonry Gallery */
        .masonry-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            grid-auto-rows: 10px;
            grid-gap: 1rem;
        }
        
        .masonry-item {
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: var(--transition-normal);
            cursor: pointer;
            position: relative;
        }
        
        .masonry-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25), 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        
        .masonry-img {
            width: 100%;
            height: auto;
            display: block;
            transition: var(--transition-slow);
        }
        
        .masonry-item:hover .masonry-img {
            transform: scale(1.05);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .staggered-fade-in > * {
            opacity: 0;
            animation: fadeIn 0.5s ease forwards;
        }
        
        .staggered-fade-in > *:nth-child(1) { animation-delay: 0.1s; }
        .staggered-fade-in > *:nth-child(2) { animation-delay: 0.15s; }
        .staggered-fade-in > *:nth-child(3) { animation-delay: 0.2s; }
        .staggered-fade-in > *:nth-child(4) { animation-delay: 0.25s; }
        .staggered-fade-in > *:nth-child(5) { animation-delay: 0.3s; }
        .staggered-fade-in > *:nth-child(6) { animation-delay: 0.35s; }
        .staggered-fade-in > *:nth-child(7) { animation-delay: 0.4s; }
        .staggered-fade-in > *:nth-child(8) { animation-delay: 0.45s; }
        .staggered-fade-in > *:nth-child(9) { animation-delay: 0.5s; }
        .staggered-fade-in > *:nth-child(10) { animation-delay: 0.55s; }
        .staggered-fade-in > *:nth-child(11) { animation-delay: 0.6s; }
        .staggered-fade-in > *:nth-child(12) { animation-delay: 0.65s; }
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
                    <a href="gallery.php" class="py-2 text-gray-800 font-medium nav-link active">Gallery</a>
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
                    <a href="gallery.php" class="py-3 px-4 text-gray-800 font-medium rounded-lg bg-gray-100">Gallery</a>
                    <a href="about.php" class="py-3 px-4 text-gray-600 font-medium rounded-lg hover:bg-gray-100">About</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-gallery flex items-center justify-center">
        <div class="container mx-auto text-center text-white px-4">
            <h1 class="text-5xl font-bold mb-4">Our Event Gallery</h1>
            <p class="text-xl mb-8 max-w-2xl mx-auto">Explore our collection of beautiful moments and events at The Barn & Backyard</p>
            <div class="flex justify-center">
                <a href="<?php echo isset($_SESSION['user_id']) ? '../users/booking-form.php' : '../login.php'; ?>" class="btn-primary px-8 py-3 rounded-full text-lg shadow-lg hover:shadow-2xl transform transition-all duration-300">
                    Book Your Event Now
                </a>
            </div>
        </div>
    </div>

    <!-- Main Gallery Section -->
    <div class="container mx-auto px-4 py-12">
        <!-- Category Filters -->
        <div class="mb-12 text-center">
            <h2 class="text-4xl font-bold mb-6 text-gray-800">Our Photo Gallery</h2>
            <p class="text-lg text-gray-600 mb-8 max-w-3xl mx-auto">Browse our collection of memorable events and beautiful settings</p>
        </div>
        
        <!-- 3D Carousel Gallery -->
        <div class="relative mx-auto max-w-6xl mb-16">
            <!-- Carousel Container -->
            <div id="gallery-carousel" class="gallery-3d-carousel">
                <div class="gallery-carousel-container">
                    <!-- Carousel slides will be populated by JavaScript -->
                    <?php
                    // Get 6 random images for the carousel
                    $carouselImages = [];
                    foreach ($categories as $category => $name) {
                        $categoryDir = "../img/gallery/{$category}";
                        $images = getGalleryImages($categoryDir);
                        
                        foreach ($images as $image) {
                            $carouselImages[] = [
                                'path' => "img/gallery/{$category}/{$image}",
                                'category' => $category,
                                'categoryName' => $name,
                                'title' => ucfirst(pathinfo($image, PATHINFO_FILENAME)),
                                'file' => $image
                            ];
                        }
                    }
                    
                    // Randomize and limit to 6
                    shuffle($carouselImages);
                    $carouselImages = array_slice($carouselImages, 0, 6);
                    
                    // Create placeholder slides
                    foreach ($carouselImages as $index => $image):
                        $title = str_replace('-', ' ', $image['title']);
                        $title = str_replace('_', ' ', $title);
                        $isActive = $index === 0 ? 'active' : '';
                    ?>
                    <div class="carousel-slide <?php echo $isActive; ?>" 
                         data-index="<?php echo $index; ?>"
                         data-title="<?php echo htmlspecialchars($title); ?>"
                         data-category="<?php echo htmlspecialchars($image['categoryName']); ?>"
                         style="background-image: url('../<?php echo $image['path']; ?>');">
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($carouselImages)): ?>
                    <div class="carousel-loading flex flex-col items-center justify-center h-full">
                        <div class="w-16 h-16 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin mb-4"></div>
                        <p class="text-gray-600">Loading gallery...</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Carousel Navigation Dots -->
                <div class="carousel-dots">
                    <?php for ($i = 0; $i < count($carouselImages); $i++): ?>
                    <div class="carousel-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></div>
                    <?php endfor; ?>
                </div>
                
                <!-- Carousel Controls -->
                <div class="carousel-arrows">
                    <button id="prev-btn" class="carousel-arrow prev">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button id="next-btn" class="carousel-arrow next">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
                </div>
        
        <!-- Featured Gallery - Masonry Layout -->
        <div class="mt-16">
            <h3 class="text-3xl font-bold mb-6 text-center text-gray-800">Featured Moments</h3>
            <p class="text-lg text-gray-600 mb-10 text-center max-w-3xl mx-auto">A curated collection of our best moments and stunning venues</p>
            
            <?php
            // Get images based on active category
            $featuredImages = [];
            
            // Get from all categories
            foreach ($categories as $category => $name) {
                $categoryDir = "../img/gallery/{$category}";
                $images = getGalleryImages($categoryDir);
                
                foreach ($images as $image) {
                    $featuredImages[] = [
                        'path' => "img/gallery/{$category}/{$image}",
                        'category' => $category,
                        'categoryName' => $name,
                        'title' => ucfirst(pathinfo($image, PATHINFO_FILENAME)),
                        'file' => $image
                    ];
                }
            }
            
            // Randomize images
            shuffle($featuredImages);
            
            // Limit to 20 images for performance
            $featuredImages = array_slice($featuredImages, 0, 20);
            
            $hasImages = !empty($featuredImages);
            
            if ($hasImages):
            ?>
            <div class="masonry-gallery" id="masonry-gallery">
                <?php foreach ($featuredImages as $index => $image):
                    $title = str_replace('-', ' ', $image['title']);
                    $title = str_replace('_', ' ', $title);
                ?>
                <div class="masonry-item featured-item"
                     data-category="<?php echo htmlspecialchars($image['category']); ?>"
                     data-title="<?php echo htmlspecialchars($title); ?>"
                     data-description="<?php echo htmlspecialchars($image['categoryName']); ?>">
                    <img src="../<?php echo $image['path']; ?>" 
                         alt="<?php echo htmlspecialchars($title); ?>" 
                         class="masonry-img">
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-gallery flex flex-col items-center justify-center text-center p-8 border border-gray-200 rounded-xl bg-white shadow-sm">
                <i class="fas fa-images text-5xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-700 mb-2">No Images Available</h3>
                <p class="text-gray-500 mb-6 max-w-md">We're working on adding beautiful photos to our gallery. Please check back soon!</p>
            </div>
            <?php endif; ?>
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

    <!-- GLightbox JS -->
    <script src="https://cdn.jsdelivr.net/gh/mcstudios/glightbox/dist/js/glightbox.min.js"></script>

    <!-- Add this JavaScript code just before the closing </body> tag -->
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
        
        // Initialize GLightbox for any images
        const lightbox = GLightbox({
            touchNavigation: true,
            loop: true,
            autoplayVideos: true
        });
        
        // Enhanced 3D Carousel Implementation
        class Carousel3D {
            constructor(options) {
                this.container = document.querySelector(options.container);
                this.slides = Array.from(this.container.querySelectorAll('.carousel-slide'));
                this.dotsContainer = this.container.querySelector('.carousel-dots');
                this.dots = Array.from(this.container.querySelectorAll('.carousel-dot'));
                this.prevBtn = document.getElementById(options.prevBtn);
                this.nextBtn = document.getElementById(options.nextBtn);
                
                this.currentIndex = 0;
                this.slideCount = this.slides.length;
                this.isAnimating = false;
                this.autoPlayDelay = options.autoPlayDelay || 5000;
                this.autoPlayTimer = null;
                
                this.init();
            }
            
            init() {
                if (this.slideCount === 0) return;
                
                // Set initial position
                this.positionSlides();
                
                // Set event listeners
                this.setEventListeners();
                
                // Start autoplay
                this.startAutoPlay();
            }
            
            setEventListeners() {
                // Next/Prev button clicks
                if (this.prevBtn) {
                    this.prevBtn.addEventListener('click', () => this.prev());
                }
                
                if (this.nextBtn) {
                    this.nextBtn.addEventListener('click', () => this.next());
                }
                
                // Navigation dot clicks
                this.dots.forEach((dot, index) => {
                    dot.addEventListener('click', () => this.goToSlide(index));
                });
                
                // Carousel hover pause autoplay
                this.container.addEventListener('mouseenter', () => this.stopAutoPlay());
                this.container.addEventListener('mouseleave', () => this.startAutoPlay());
                
                // Slide click events
                this.slides.forEach((slide, index) => {
                    slide.addEventListener('click', () => {
                        // If it's the current slide, show in lightbox
                        if (index === this.currentIndex) {
                            const bgImg = slide.style.backgroundImage.replace('url(\'', '').replace('\')', '');
                            
                            const myGallery = GLightbox({
                                elements: [{
                                    'href': bgImg,
                                    'type': 'image'
                                }]
                            });
                            myGallery.open();
                        } else {
                            // Otherwise, navigate to that slide
                            this.goToSlide(index);
                        }
                    });
                });
                
                // Keyboard navigation
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowLeft') {
                        this.prev();
                    } else if (e.key === 'ArrowRight') {
                        this.next();
                    }
                });
                
                // Touch events
                let touchStartX = 0;
                let touchEndX = 0;
                
                this.container.addEventListener('touchstart', (e) => {
                    touchStartX = e.touches[0].clientX;
                    this.stopAutoPlay();
                }, { passive: true });
                
                this.container.addEventListener('touchend', (e) => {
                    touchEndX = e.changedTouches[0].clientX;
                    const diff = touchStartX - touchEndX;
                    
                    if (Math.abs(diff) > 50) { // Threshold of 50px
                        if (diff > 0) {
                            this.next();
                        } else {
                            this.prev();
                        }
                    }
                    
                    this.startAutoPlay();
                }, { passive: true });
            }
            
            startAutoPlay() {
                if (this.slideCount <= 1) return;
                
                this.stopAutoPlay();
                this.autoPlayTimer = setInterval(() => {
                    this.next();
                }, this.autoPlayDelay);
            }
            
            stopAutoPlay() {
                if (this.autoPlayTimer) {
                    clearInterval(this.autoPlayTimer);
                    this.autoPlayTimer = null;
                }
            }
            
            positionSlides() {
                if (this.slideCount <= 1) {
                    if (this.slideCount === 1) {
                        this.slides[0].classList.add('active');
                        this.slides[0].style.transform = 'translateX(-50%) translateY(-50%) scale(1)';
                        this.slides[0].style.zIndex = '10';
                        this.slides[0].style.opacity = '1';
                    }
                    return;
                }
                
                // Position each slide
                this.slides.forEach((slide, index) => {
                    // Remove active class from all slides
                    slide.classList.remove('active');
                    
                    // Calculate the angle position
                    const theta = 2 * Math.PI * (index - this.currentIndex) / this.slideCount;
                    
                    // Calculate position on the circle
                    const radius = 320; // Slightly larger radius
                    const x = -Math.sin(theta) * radius;
                    const z = -Math.cos(theta) * radius;
                    const y = Math.sin(theta * 2) * 30; // Add slight vertical movement for more 3D effect
                    
                    // Calculate scale based on z position
                    const scale = this.mapRange(z, -radius, radius, 0.65, 1);
                    
                    // Calculate rotation to enhance 3D effect
                    const rotateY = this.mapRange(x, -radius, radius, 15, -15); // Rotate toward center
                    
                    // Set transformation with 3D rotation
                    slide.style.transform = `translateX(calc(-50% + ${x}px)) translateY(calc(-50% + ${y}px)) translateZ(${z}px) rotateY(${rotateY}deg) scale(${scale})`;
                    
                    // Set z-index based on z position (closer = higher z-index)
                    slide.style.zIndex = Math.round(this.mapRange(z, -radius, radius, 1, 9));
                    
                    // Set opacity based on z position
                    slide.style.opacity = this.mapRange(z, -radius, radius, 0.5, 1);
                    
                    // Set filter to enhance depth perception
                    const blur = this.mapRange(z, -radius, radius, 1, 0);
                    const brightness = this.mapRange(z, -radius, radius, 0.7, 1);
                    slide.style.filter = `blur(${blur}px) brightness(${brightness})`;
                    
                    // Handle active slide
                    if (index === this.currentIndex) {
                        slide.classList.add('active');
                        slide.style.transform = 'translateX(-50%) translateY(-50%) translateZ(150px) rotateY(0deg) scale(1.1)';
                        slide.style.zIndex = '10';
                        slide.style.opacity = '1';
                        slide.style.filter = 'blur(0) brightness(1.05)';
                    }
                });
                
                // Update navigation dots
                this.updateDots();
            }
            
            updateDots() {
                this.dots.forEach((dot, index) => {
                    dot.classList.toggle('active', index === this.currentIndex);
                });
            }
            
            goToSlide(index) {
                if (index === this.currentIndex || this.isAnimating) return;
                if (index < 0) index = this.slideCount - 1;
                if (index >= this.slideCount) index = 0;
                
                this.isAnimating = true;
                this.currentIndex = index;
                this.positionSlides();
                
                // Prevent new animations for a short duration
                setTimeout(() => {
                    this.isAnimating = false;
                }, 600);
            }
            
            next() {
                this.goToSlide(this.currentIndex + 1);
            }
            
            prev() {
                this.goToSlide(this.currentIndex - 1);
            }
            
            // Utility function: Map value from one range to another
            mapRange(value, low1, high1, low2, high2) {
                return low2 + (high2 - low2) * (value - low1) / (high1 - low1);
            }
        }
        
        // Initialize the 3D Carousel
        if (document.querySelector('.gallery-3d-carousel')) {
            const carousel3D = new Carousel3D({
                container: '.gallery-3d-carousel',
                prevBtn: 'prev-btn',
                nextBtn: 'next-btn',
                autoPlayDelay: 5000
            });
        }
        
        // Masonry Layout
        const masonryGallery = document.getElementById('masonry-gallery');
        if (masonryGallery) {
            // Get all masonry items and their images
            const masonryItems = Array.from(masonryGallery.querySelectorAll('.masonry-item'));
            
            // Function to calculate and set the height of each item based on the image aspect ratio
            function resizeMasonryItems() {
                masonryItems.forEach(item => {
                    const img = item.querySelector('img');
                    if (img.complete) {
                        applyMasonryHeight(item, img);
                    } else {
                        img.addEventListener('load', () => {
                            applyMasonryHeight(item, img);
                        });
                    }
                });
            }
            
            // Calculate and apply the correct height to the item
            function applyMasonryHeight(item, img) {
                const rowGap = parseInt(window.getComputedStyle(masonryGallery).getPropertyValue('grid-row-gap'));
                const rowHeight = parseInt(window.getComputedStyle(masonryGallery).getPropertyValue('grid-auto-rows'));
                
                const imgHeight = img.naturalHeight;
                const imgWidth = img.naturalWidth;
                const itemWidth = item.clientWidth;
                const itemHeight = imgHeight * (itemWidth / imgWidth); // maintain aspect ratio
                
                const rowSpan = Math.ceil((itemHeight + rowGap) / (rowHeight + rowGap));
                item.style.gridRowEnd = 'span ' + rowSpan;
            }
            
            // Initial setup
            resizeMasonryItems();
            
            // Reset on window resize
            window.addEventListener('resize', resizeMasonryItems);
            
            // Make featured gallery items clickable for lightbox
            const featuredItems = document.querySelectorAll('.featured-item');
            featuredItems.forEach(item => {
                item.addEventListener('click', () => {
                    const imgSrc = item.querySelector('img').src;
                    
                    const featuredLightbox = GLightbox({
                        elements: [{
                            'href': imgSrc,
                            'type': 'image'
                        }]
                    });
                    featuredLightbox.open();
                });
            });
        }
    });
    </script>
</body>
</html>