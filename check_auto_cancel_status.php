<?php
// Include database configuration
include 'db/config.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Function to check the last log entry
function getLastLogExecutionTime() {
    $logFile = 'auto_cancel_log.txt';
    if (!file_exists($logFile)) {
        return ['status' => 'error', 'message' => 'Log file does not exist'];
    }
    
    // Get the last 30 lines of the log
    $lines = array_slice(file($logFile), -30);
    $lastExecutionStart = null;
    $lastExecutionEnd = null;
    
    foreach ($lines as $line) {
        if (strpos($line, 'AUTO-CANCEL EXECUTION STARTED') !== false) {
            preg_match('/\[(.*?)\]/', $line, $matches);
            if (!empty($matches[1])) {
                $lastExecutionStart = $matches[1];
            }
        }
        
        if (strpos($line, 'AUTO-CANCEL EXECUTION FINISHED') !== false) {
            preg_match('/\[(.*?)\]/', $line, $matches);
            if (!empty($matches[1])) {
                $lastExecutionEnd = $matches[1];
            }
        }
    }
    
    if ($lastExecutionStart) {
        $lastExecution = $lastExecutionStart;
        $lastExecutionTime = strtotime($lastExecution);
        $currentTime = time();
        $differenceMinutes = round(($currentTime - $lastExecutionTime) / 60);
        
        if ($differenceMinutes < 10) {
            $status = 'success';
            $message = "Auto-cancel ran recently ($differenceMinutes minutes ago)";
        } else {
            $status = 'warning';
            $message = "Auto-cancel last ran $differenceMinutes minutes ago";
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'last_execution' => $lastExecution,
            'minutes_ago' => $differenceMinutes
        ];
    }
    
    return ['status' => 'error', 'message' => 'No execution found in log'];
}

// Function to check for overdue bookings that should be cancelled
function checkOverdueBookings($conn) {
    $cutoff_time = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    $sql = "SELECT COUNT(*) as count FROM bookings 
            WHERE status = 'confirmed' 
            AND payment_status NOT IN ('paid') 
            AND updated_at < ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cutoff_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        return [
            'status' => 'danger',
            'message' => "Found {$row['count']} bookings that should be cancelled but haven't been",
            'count' => $row['count']
        ];
    } else {
        return [
            'status' => 'success',
            'message' => 'No overdue bookings found that need cancellation',
            'count' => 0
        ];
    }
}

// Function to check upcoming cancellations
function checkUpcomingCancellations($conn) {
    $sql = "SELECT id, booking_reference, updated_at, 
            TIMESTAMPDIFF(MINUTE, updated_at, NOW()) as minutes_since_update,
            TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(updated_at, INTERVAL 1 HOUR)) as minutes_left
            FROM bookings 
            WHERE status = 'confirmed' 
            AND payment_status NOT IN ('paid')
            AND updated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY updated_at ASC";
    
    $result = $conn->query($sql);
    $upcoming = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $upcoming[] = [
                'id' => $row['id'],
                'reference' => $row['booking_reference'],
                'updated_at' => $row['updated_at'],
                'minutes_left' => $row['minutes_left'],
                'minutes_passed' => $row['minutes_since_update']
            ];
        }
    }
    
    return $upcoming;
}

// Check if we should run the script manually
if (isset($_POST['run_script'])) {
    // Execute the auto-cancel script and capture output
    ob_start();
    include 'auto_cancel_unpaid_bookings.php';
    $scriptOutput = ob_get_clean();
}

// Get the status checks
$lastExecution = getLastLogExecutionTime();
$overdueBookings = checkOverdueBookings($conn);
$upcomingCancellations = checkUpcomingCancellations($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto-Cancel System Status</title>
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
        .system-status {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .status-success {
            background-color: #d4edda;
            border-left: 5px solid #28a745;
            color: #155724;
        }
        .status-warning {
            background-color: #fff3cd;
            border-left: 5px solid #ffc107;
            color: #856404;
        }
        .status-danger {
            background-color: #f8d7da;
            border-left: 5px solid #dc3545;
            color: #721c24;
        }
        .log-output {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
            font-size: 0.9rem;
        }
        .countdown {
            font-weight: bold;
            color: #dc3545;
        }
    </style>
    <script>
        // Auto refresh the page every 60 seconds
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Auto-Cancel System Status</h1>
        
        <div class="system-status status-<?php echo $lastExecution['status']; ?>">
            <h4>
                <?php if ($lastExecution['status'] === 'success'): ?>
                    <i class="fas fa-check-circle me-2"></i>
                <?php elseif ($lastExecution['status'] === 'warning'): ?>
                    <i class="fas fa-exclamation-triangle me-2"></i>
                <?php else: ?>
                    <i class="fas fa-times-circle me-2"></i>
                <?php endif; ?>
                System Status: <?php echo ucfirst($lastExecution['status']); ?>
            </h4>
            <p class="m-0"><?php echo $lastExecution['message']; ?></p>
            <?php if ($lastExecution['status'] !== 'error'): ?>
                <p class="small text-muted mb-0">Last Run: <?php echo $lastExecution['last_execution']; ?></p>
            <?php endif; ?>
        </div>
        
        <div class="system-status status-<?php echo $overdueBookings['status']; ?> mb-4">
            <h4>
                <?php if ($overdueBookings['status'] === 'success'): ?>
                    <i class="fas fa-check-circle me-2"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-triangle me-2"></i>
                <?php endif; ?>
                Bookings Status
            </h4>
            <p><?php echo $overdueBookings['message']; ?></p>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h2>Manual Control</h2>
                    </div>
                    <div class="card-body">
                        <p>Current Server Time: <strong><?php echo date('F j, Y g:i:s A'); ?></strong></p>
                        <p>You can manually run the auto-cancel script if needed:</p>
                        
                        <form method="post">
                            <button type="submit" name="run_script" class="btn btn-primary mb-3">
                                <i class="fas fa-play me-2"></i> Run Auto-Cancel Now
                            </button>
                        </form>
                        
                        <p><a href="setup_auto_cancel.php" class="btn btn-outline-secondary">
                            <i class="fas fa-cog me-2"></i> Go to Setup Page
                        </a></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h2>Upcoming Cancellations</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingCancellations)): ?>
                            <div class="alert alert-info">No upcoming cancellations found.</div>
                        <?php else: ?>
                            <p>These bookings will be automatically cancelled when they reach 1 hour unpaid:</p>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Reference</th>
                                        <th>Time Passed</th>
                                        <th>Cancellation In</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingCancellations as $booking): ?>
                                        <tr>
                                            <td><?php echo $booking['id']; ?></td>
                                            <td><?php echo $booking['reference']; ?></td>
                                            <td><?php echo $booking['minutes_passed']; ?> min</td>
                                            <td class="countdown"><?php echo $booking['minutes_left']; ?> min</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($scriptOutput)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h2>Script Output</h2>
            </div>
            <div class="card-body">
                <div class="log-output"><?php echo $scriptOutput; ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card mt-4">
            <div class="card-header">
                <h2>Troubleshooting Tips</h2>
            </div>
            <div class="card-body">
                <h5>If Auto-Cancel Isn't Working:</h5>
                <ol>
                    <li>Make sure the <code>EventBookingAutoCancel</code> task is properly set up in Task Scheduler</li>
                    <li>Check that your XAMPP server is running (Apache and MySQL services)</li>
                    <li>Look at the <code>auto_cancel_log.txt</code> and <code>auto_cancel_output.log</code> files for errors</li>
                    <li>Try running the script manually using the button above</li>
                    <li>Make sure your database connection is working properly</li>
                </ol>
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