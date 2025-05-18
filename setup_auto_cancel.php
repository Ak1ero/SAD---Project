<?php
// Set timezone
date_default_timezone_set('Asia/Manila');

// Get the absolute path to the auto_cancel_unpaid_bookings.php script
$scriptPath = realpath('auto_cancel_unpaid_bookings.php');
$phpPath = PHP_BINARY; // Get the path to the PHP executable

// Check if the script exists
$scriptExists = file_exists($scriptPath);

// Get server OS
$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Auto-Cancel System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <style>
        body {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
        }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            overflow-x: auto;
        }
        .alert-info code {
            color: #0c5460;
            background-color: #d1ecf1;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Setup Auto-Cancel System</h1>
        
        <div class="alert alert-info">
            <h4 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Current Status</h4>
            <p>Current time: <strong><?php echo date('F j, Y g:i:s A'); ?></strong> (Asia/Manila Timezone)</p>
            
            <?php if ($scriptExists): ?>
            <p><i class="fas fa-check-circle text-success me-2"></i> <strong>Script Found:</strong> The auto-cancel script was found at <code><?php echo htmlspecialchars($scriptPath); ?></code></p>
            <?php else: ?>
            <p><i class="fas fa-times-circle text-danger me-2"></i> <strong>Error:</strong> The auto-cancel script could not be found at the expected location.</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>About Auto-Cancel</h2>
            </div>
            <div class="card-body">
                <p>The auto-cancel feature automatically cancels confirmed bookings that remain unpaid after 1 hour.</p>
                <p>For this to work automatically, you need to set up a scheduled task or cron job that runs the script regularly.</p>
                <p><strong>Important:</strong> The script needs to run frequently to catch bookings that have passed the 1-hour mark.</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Setting Up Automated Execution</h2>
            </div>
            <div class="card-body">
                <?php if ($isWindows): ?>
                <h3>Windows Task Scheduler (for Windows servers)</h3>
                <ol>
                    <li>Open Task Scheduler (search for it in the Start menu)</li>
                    <li>Click "Create Basic Task..."</li>
                    <li>Enter a name like "Event Auto-Cancel" and a description</li>
                    <li>Select "Daily" for the trigger</li>
                    <li>Set the start time to now and recur every 1 day</li>
                    <li>Select "Start a program" for the action</li>
                    <li>Browse to your PHP executable (usually <code><?php echo htmlspecialchars($phpPath); ?></code>)</li>
                    <li>Add the script path as an argument: <code><?php echo htmlspecialchars($scriptPath); ?></code></li>
                    <li>Check "Open the Properties dialog..." before finishing</li>
                    <li>In the Properties dialog, go to the Triggers tab</li>
                    <li>Edit the trigger and check "Repeat task every" and set it to 15 minutes</li>
                    <li>Set "for a duration of" to 1 day</li>
                    <li>Click OK to save</li>
                </ol>
                <p>Command for Windows Task Scheduler:</p>
                <pre><?php echo htmlspecialchars($phpPath); ?> <?php echo htmlspecialchars($scriptPath); ?></pre>
                
                <?php else: ?>
                <h3>Cron Job (for Linux/Unix servers)</h3>
                <ol>
                    <li>Open your terminal</li>
                    <li>Type <code>crontab -e</code> to edit your cron jobs</li>
                    <li>Add the following line to run the script every 15 minutes:</li>
                </ol>
                <pre>*/15 * * * * php <?php echo htmlspecialchars($scriptPath); ?></pre>
                <?php endif; ?>
                
                <div class="alert alert-warning mt-3">
                    <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i> No Access to Scheduler?</h4>
                    <p>If you don't have access to cron jobs or Task Scheduler (common on shared hosting), you can:</p>
                    <ol>
                        <li>Use a <a href="https://www.easycron.com/" target="_blank">free cron job service</a> that calls your script via URL</li>
                        <li>Create a URL endpoint version of the auto-cancel script</li>
                    </ol>
                    <p>URL to use with external cron services:</p>
                    <pre>http://<?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>/run_cancel.php?key=YOUR_SECRET_KEY</pre>
                    <p>Make sure to add security to prevent unauthorized access!</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Manual Testing</h2>
            </div>
            <div class="card-body">
                <p>To test if the auto-cancel is working correctly:</p>
                <ol>
                    <li>Create a test booking</li>
                    <li>Update its status to "confirmed" in the database</li>
                    <li>Wait 1 hour</li>
                    <li>Run the auto-cancel script manually</li>
                    <li>Verify the booking was cancelled</li>
                </ol>
                <p>You can manually run the script right now by clicking the button below:</p>
                <a href="run_cancel.php" class="btn btn-success">
                    <i class="fas fa-play me-2"></i> Run Auto-Cancel Script Now
                </a>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home me-2"></i> Return to Homepage
            </a>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 