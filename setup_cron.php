<?php
/**
 * Cron Job Setup Information
 * 
 * This file provides information on how to set up the cron job for the auto-cancel function.
 * It is intended for administrator reference only.
 */

// Get the absolute path to the auto_cancel_unpaid_bookings.php script
$scriptPath = realpath(__DIR__ . '/auto_cancel_unpaid_bookings.php');
$phpPath = PHP_BINARY; // Get the path to the PHP binary

// Create the cron job command
$cronCommand = "*/15 * * * * $phpPath $scriptPath > /dev/null 2>&1";

// Check if the script exists
$scriptExists = file_exists($scriptPath);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Cron Job for Auto-Cancel Unpaid Bookings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin-bottom: 20px;
        }
        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
        }
        .error-box {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 20px;
        }
        code {
            font-family: Consolas, Monaco, 'Andale Mono', monospace;
            background-color: #f5f5f5;
            padding: 2px 5px;
            border-radius: 3px;
        }
        pre {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Cron Job Setup for Auto-Cancel Unpaid Bookings</h1>
        
        <?php if (!$scriptExists): ?>
        <div class="error-box">
            <strong>Error:</strong> The auto-cancel script could not be found at the expected location.
            Please ensure the file <code>auto_cancel_unpaid_bookings.php</code> exists in the root directory.
        </div>
        <?php else: ?>
        <div class="info-box">
            <strong>Script Found:</strong> The auto-cancel script was found at <code><?php echo htmlspecialchars($scriptPath); ?></code>
        </div>
        <?php endif; ?>
        
        <h2>What is this for?</h2>
        <p>
            The auto-cancel feature will cancel any confirmed bookings that have not been paid within 1 hour.
            This helps prevent users from holding reservations indefinitely without payment.
        </p>
        
        <h2>Setting up the Cron Job</h2>
        <p>To set up automatic cancellation of unpaid bookings, you need to create a cron job:</p>
        
        <h3>For Linux/Unix Servers:</h3>
        <ol>
            <li>Access your server via SSH or terminal</li>
            <li>Run <code>crontab -e</code> to edit your cron jobs</li>
            <li>Add the following line to the file:</li>
        </ol>
        <pre><?php echo htmlspecialchars($cronCommand); ?></pre>
        <p>This will run the script every 15 minutes.</p>
        
        <h3>For Windows Servers:</h3>
        <ol>
            <li>Open Task Scheduler</li>
            <li>Create a new Basic Task</li>
            <li>Set it to run daily, and in the advanced settings, set it to repeat every hour</li>
            <li>For the action, set it to run a program:</li>
        </ol>
        <pre>Program/script: <?php echo htmlspecialchars($phpPath); ?>
Arguments: <?php echo htmlspecialchars($scriptPath); ?></pre>
        
        <h3>For cPanel:</h3>
        <ol>
            <li>Log in to your cPanel account</li>
            <li>Find and click on "Cron Jobs" under the "Advanced" section</li>
            <li>Select "Every 15 Minutes" from the "Common Settings" dropdown</li>
            <li>Enter the following command in the "Command" field:</li>
        </ol>
        <pre>php <?php echo htmlspecialchars($scriptPath); ?> > /dev/null 2>&1</pre>
        
        <div class="warning-box">
            <strong>Note:</strong> The exact steps may vary depending on your hosting provider. 
            Contact your hosting provider's support if you need assistance setting up cron jobs.
        </div>
        
        <h2>Testing the Script</h2>
        <p>
            To test if the auto-cancel script is working correctly, you can run it manually by visiting:
            <code>admin/run_auto_cancel.php</code>
        </p>
        <p>
            This requires admin login and will show you the output of the script.
        </p>
    </div>
</body>
</html> 