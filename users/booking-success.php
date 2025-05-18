<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if there's a success message in session
if(!isset($_SESSION['booking_success'])) {
    header("Location: index.php");
    exit();
}

// Clear the success message from session
unset($_SESSION['booking_success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Barn & Backyard | Booking Success</title>
    <link rel="icon" href="img/barn-backyard.svg" type="image/svg+xml"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            padding: 20px;
        }

        .success-container {
            max-width: 600px;
            width: 100%;
            text-align: center;
            background: #ffffff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .success-icon {
            font-size: 60px;
            color: #4CAF50;
            margin-bottom: 20px;
        }

        h1 {
            color: #1a1a1a;
            font-size: 24px;
            margin-bottom: 15px;
        }

        p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .redirect-text {
            font-size: 14px;
            color: #888;
            margin-top: 20px;
        }

        .btn-home {
            display: inline-block;
            padding: 12px 30px;
            background: #4a90e2;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-home:hover {
            background: #357abd;
            transform: translateY(-2px);
        }

        @media (max-width: 480px) {
            .success-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <i class="fas fa-check-circle success-icon"></i>
        <h1>Booking Successful!</h1>
        <p>Your event has been successfully booked. Thank you for choosing The Barn & Backyard!</p>
        <a href="../index.php" class="btn-home">Back to Home</a>
        <p class="redirect-text">You will be redirected to the home page in <span id="countdown">5</span> seconds...</p>
    </div>

    <script>
        // Countdown timer
        let seconds = 5;
        const countdownElement = document.getElementById('countdown');
        
        const countdown = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = '../index.php';
            }
        }, 1000);
    </script>
</body>
</html>