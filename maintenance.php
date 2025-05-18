<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Barn & Backyard - Maintenance Mode</title>
    
    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: linear-gradient(135deg, rgba(93, 95, 239, 0.05) 25%, transparent 25%),
                linear-gradient(225deg, rgba(93, 95, 239, 0.05) 25%, transparent 25%),
                linear-gradient(45deg, rgba(93, 95, 239, 0.05) 25%, transparent 25%),
                linear-gradient(315deg, rgba(93, 95, 239, 0.05) 25%, #F9FAFB 25%);
            background-position: 40px 0, 40px 0, 0 0, 0 0;
            background-size: 80px 80px;
            background-repeat: repeat;
        }
        
        h1, h2, h3 {
            font-family: var(--header-font);
        }
        
        .maintenance-container {
            max-width: 700px;
            padding: 2rem;
            text-align: center;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .maintenance-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), #8B5CF6);
            z-index: 2;
        }
        
        .barn-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 2rem;
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
        }
        
        .barn-body {
            position: absolute;
            top: 40%;
            left: 10%;
            width: 80%;
            height: 60%;
            background-color: var(--accent-color);
        }
        
        .barn-door {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 30%;
            height: 40%;
            background-color: var(--secondary-color);
        }
        
        .tools-animation {
            animation: swing 2s infinite alternate ease-in-out;
            transform-origin: center top;
        }
        
        .progress-bar {
            height: 6px;
            background: #E0E7FF;
            border-radius: 3px;
            overflow: hidden;
            margin: 2rem 0;
        }
        
        .progress-bar-inner {
            height: 100%;
            background: linear-gradient(to right, var(--primary-color), #8B5CF6);
            width: 75%;
            border-radius: 3px;
            animation: progress 3s ease infinite alternate;
        }
        
        .countdown-container {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .countdown-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            min-width: 80px;
        }
        
        .countdown-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .countdown-label {
            font-size: 0.75rem;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .feature-item {
            background: white;
            padding: 1.25rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            text-align: left;
        }
        
        .feature-icon {
            background: var(--secondary-color);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        @keyframes swing {
            0% { transform: rotate(-5deg); }
            100% { transform: rotate(5deg); }
        }
        
        @keyframes progress {
            0% { width: 15%; }
            100% { width: 85%; }
        }
        
        .bg-pattern {
            position: absolute;
            height: 100%;
            width: 100%;
            opacity: 0.5;
            z-index: -1;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="maintenance-container">
        <div class="barn-icon">
            <div class="barn-roof"></div>
            <div class="barn-body"></div>
            <div class="barn-door"></div>
        </div>
        
        <div class="tools-animation mt-4 mb-8 flex justify-center">
            <i class="fas fa-tools text-4xl text-gray-600"></i>
        </div>
        
        <h1 class="text-4xl font-bold text-gray-800 mb-4">We're Making Improvements</h1>
        
        <p class="text-lg text-gray-600 mb-4">
            The Barn & Backyard is currently undergoing scheduled maintenance. 
            We'll be back soon with an even better experience for planning your special events.
        </p>
        
        <div class="progress-bar">
            <div class="progress-bar-inner"></div>
        </div>
        
        <h3 class="text-xl font-semibold text-gray-700">Estimated Completion</h3>
        
        <div class="countdown-container">
            <div class="countdown-item">
                <span id="hours" class="countdown-value">02</span>
                <span class="countdown-label">Hours</span>
            </div>
            <div class="countdown-item">
                <span id="minutes" class="countdown-value">45</span>
                <span class="countdown-label">Minutes</span>
            </div>
            <div class="countdown-item">
                <span id="seconds" class="countdown-value">30</span>
                <span class="countdown-label">Seconds</span>
            </div>
        </div>
        
        <p class="text-gray-500 italic mb-8">We're working as fast as we can!</p>
        
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">New Booking Features</h4>
                <p class="text-gray-600 text-sm">We're adding exciting new options to our event booking system.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-paint-brush"></i>
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">Fresh Design</h4>
                <p class="text-gray-600 text-sm">Upgrading our design to make planning your events even easier.</p>
            </div>
        </div>
        
        <div class="text-sm text-gray-500 mt-8">
            <p>If you're an administrator, please <a href="login.php" class="text-indigo-600 hover:underline">log in here</a>.</p>
        </div>
    </div>
    
    <script>
        // Countdown Timer
        // Set the future date (3 hours from now by default)
        const targetDate = new Date();
        targetDate.setHours(targetDate.getHours() + 3);
        
        function updateCountdown() {
            const currentDate = new Date();
            const difference = targetDate - currentDate;
            
            if (difference <= 0) {
                // Maintenance completed, redirect to homepage after a few seconds
                document.getElementById('hours').textContent = '00';
                document.getElementById('minutes').textContent = '00';
                document.getElementById('seconds').textContent = '00';
                
                // Reload the page after 5 seconds to check if maintenance is off
                setTimeout(() => {
                    window.location.reload();
                }, 5000);
                return;
            }
            
            const hours = Math.floor(difference / (1000 * 60 * 60));
            const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((difference % (1000 * 60)) / 1000);
            
            document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
            document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
        }
        
        // Update the countdown every second
        setInterval(updateCountdown, 1000);
        updateCountdown();
        
        // Check if maintenance mode is still active
        // This function will check every 30 seconds if maintenance mode has been turned off
        function checkMaintenanceStatus() {
            fetch('check_maintenance.php', { 
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (!data.maintenance_mode) {
                    // Maintenance mode is off, redirect to homepage
                    window.location.href = 'index.php';
                }
            })
            .catch(error => {
                console.error('Error checking maintenance status:', error);
            });
        }
        
        // Initial check and then check every 30 seconds
        checkMaintenanceStatus();
        setInterval(checkMaintenanceStatus, 30000);
    </script>
</body>
</html> 