<?php
session_start();
include '../db/config.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $package_id = $_POST['package_id'];
    $rating = $_POST['rating'];
    $review_text = $_POST['review_text'];

    // Insert review into database
    $stmt = $conn->prepare("INSERT INTO reviews (user_id, package_id, rating, review_text) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $user_id, $package_id, $rating, $review_text);
    
    if ($stmt->execute()) {
        $success_message = "Your review has been submitted successfully!";
    } else {
        $error_message = "Error submitting review: " . $conn->error;
    }
    
    $stmt->close();
}

// Get all packages for dropdown
$packages_query = "SELECT id, name FROM event_packages WHERE status = 'active' ORDER BY name";
$packages_result = $conn->query($packages_query);

// Get all reviews with user and package information
$reviews_query = "SELECT r.*, u.name as user_name, p.name as package_name, p.image_path as package_image 
                 FROM reviews r 
                 JOIN users u ON r.user_id = u.id 
                 JOIN event_packages p ON r.package_id = p.id 
                 ORDER BY r.created_at DESC";
$reviews_result = $conn->query($reviews_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - The Barn & Backyard</title>
    <link rel="icon" href="../img/barn-backyard.svg" type="image/svg+xml"/>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #5D5FEF;
            --secondary-color: #E0E7FF;
            --accent-color: #3F3D56;
            --light-bg: #F9FAFB;
            --header-font: 'Playfair Display', serif;
            --body-font: 'Poppins', sans-serif;
        }
        
        body {
            font-family: var(--body-font);
        }
        h1, h2, h3 {
            font-family: var(--header-font);
        }
        .hero-section {
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../img/e-1.jpg');
            background-size: cover;
            background-position: center;
            height: 400px;
        }
        
        /* Star Rating */
        .star-rating {
            display: inline-flex;
            font-size: 1.5rem;
            color: #FFD700;
        }
        
        .star-rating.editable .star {
            cursor: pointer;
        }
        
        /* Button styles */
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
        
        /* Adding the nav-link styles from index.php */
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
        
        .transition-all {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Review card styling */
        .review-card {
            transition: all 0.3s ease;
        }
        
        .review-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
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
                    <a href="reviews.php" class="py-2 text-gray-800 font-medium nav-link active">Reviews</a>
                    <a href="packages.php" class="py-2 text-gray-600 font-medium nav-link hover:text-gray-800">Packages</a>
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
                
                <!-- Mobile Menu Button -->
                <div class="md:hidden flex items-center">
                    <button class="mobile-menu-button">
                        <i class="fas fa-bars text-gray-600 text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu hidden md:hidden">
        <a href="../index.php" class="block py-4 px-8 text-gray-600 hover:bg-gray-100">Home</a>
        <a href="reviews.php" class="block py-4 px-8 text-gray-800 bg-gray-100">Reviews</a>
        <a href="packages.php" class="block py-4 px-8 text-gray-600 hover:bg-gray-100">Packages</a>
        <a href="gallery.php" class="block py-4 px-8 text-gray-600 hover:bg-gray-100">Gallery</a>
        <a href="about.php" class="block py-4 px-8 text-gray-600 hover:bg-gray-100">About</a>
        
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="../users/my-bookings.php" class="block py-4 px-8 text-gray-600 hover:bg-gray-100">My Bookings</a>
            <a href="../logout.php" class="block py-4 px-8 text-gray-600 hover:bg-gray-100">Logout</a>
        <?php else: ?>
            <a href="../login.php" class="block py-4 px-8 text-gray-600 hover:bg-gray-100">Login</a>
        <?php endif; ?>
    </div>

    <!-- Hero Section -->
    <div class="hero-section flex items-center justify-center">
        <div class="container mx-auto text-center text-white">
            <h1 class="text-5xl font-bold mb-4">Customer Reviews</h1>
            <p class="text-xl mb-8">See what our clients have to say about our services</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-12">
        <?php if(isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                <span class="block sm:inline"><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                <span class="block sm:inline"><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <!-- Remove the review submission form section and replace with a note -->
        <div class="bg-blue-50 border border-blue-200 text-blue-800 px-6 py-4 rounded-lg mb-12">
            <p class="text-center"><i class="fas fa-info-circle mr-2"></i> To leave a review, please view a package and click on the "Write a Review" button in the package details.</p>
        </div>
        
        <!-- Reviews Display Section -->
        <h2 class="text-3xl font-bold mb-8 text-center text-gray-800">What Our Customers Say</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php 
            if ($reviews_result && $reviews_result->num_rows > 0) {
                while($review = $reviews_result->fetch_assoc()): 
                    $image_path = !empty($review['package_image']) ? '../' . $review['package_image'] : '../img/placeholder.jpg';
            ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden review-card">
                    <div class="h-48 overflow-hidden">
                        <img src="<?php echo $image_path; ?>" alt="<?php echo $review['package_name']; ?>" class="w-full h-full object-cover">
                    </div>
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-xl font-bold text-gray-800"><?php echo $review['package_name']; ?></h3>
                            <div class="star-rating">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <span class="star">
                                        <i class="fas fa-star <?php echo ($i <= $review['rating']) ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                    </span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <p class="text-gray-600 mb-4"><?php echo $review['review_text']; ?></p>
                        <div class="flex flex-wrap justify-between items-center pt-4 border-t border-gray-200">
                            <div class="flex items-center">
                                <p class="text-sm font-semibold text-gray-700"><?php echo $review['user_name']; ?></p>
                            </div>
                            <div class="flex items-center my-2 sm:my-0 bg-gray-100 px-3 py-1 rounded-full">
                                <span class="text-sm font-bold text-gray-800"><?php echo $review['rating']; ?>.0</span>
                                <i class="fas fa-star text-yellow-400 ml-1"></i>
                            </div>
                            <div class="flex items-center">
                                <p class="text-sm text-gray-500"><?php echo date("M d, Y", strtotime($review['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php 
                endwhile;
            } else {
            ?>
                <div class="col-span-full bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-comment-slash text-5xl text-gray-400 mb-4"></i>
                    <h3 class="text-2xl font-semibold text-gray-700 mb-2">No Reviews Yet</h3>
                    <p class="text-gray-600 mb-6">Be the first to share your experience with our packages!</p>
                    <a href="packages.php" class="btn-primary py-2 px-6 rounded-md inline-block">
                        View Packages
                    </a>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-2xl font-bold mb-4">The Barn & Backyard</h3>
                    <p class="mb-4">Creating unforgettable events and celebrations in our beautiful venue.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-white hover:text-indigo-300"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white hover:text-indigo-300"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white hover:text-indigo-300"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div>
                    <h4 class="text-xl font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="../index.php" class="hover:text-indigo-300">Home</a></li>
                        <li><a href="packages.php" class="hover:text-indigo-300">Packages</a></li>
                        <li><a href="gallery.php" class="hover:text-indigo-300">Gallery</a></li>
                        <li><a href="about.php" class="hover:text-indigo-300">About</a></li>
                        <li><a href="reviews.php" class="hover:text-indigo-300">Reviews</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xl font-semibold mb-4">Contact Us</h4>
                    <ul class="space-y-2">
                        <li><i class="fas fa-map-marker-alt mr-2"></i> 123 Event Avenue, Manila, Philippines</li>
                        <li><i class="fas fa-phone-alt mr-2"></i> (02) 8123 4567</li>
                        <li><i class="fas fa-envelope mr-2"></i> info@barnbackyard.com</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-8 text-center">
                <p>&copy; <?php echo date("Y"); ?> The Barn & Backyard. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const mobileMenuButton = document.querySelector('.mobile-menu-button');
        const mobileMenu = document.querySelector('.mobile-menu');
        
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
        
        // User menu dropdown
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
    </script>
</body>
</html>