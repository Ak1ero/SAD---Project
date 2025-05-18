<?php
// Include database configuration
include 'db/config.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Check if the form was submitted
$output = null;
if (isset($_POST['run_script'])) {
    // Execute the auto-cancel script and capture output
    ob_start();
    include 'auto_cancel_unpaid_bookings.php';
    $output = ob_get_clean();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Run Auto-Cancel Unpaid Bookings</title>
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
        .output-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 500px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Run Auto-Cancel Unpaid Bookings</h1>
        
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="card-title">Current Time</h2>
                <p class="lead"><?php echo date('F j, Y g:i:s A'); ?> (Asia/Manila Timezone)</p>
                
                <h4 class="mt-3">Important:</h4>
                <p>This tool will cancel any confirmed bookings that remain unpaid more than 1 hour after confirmation.</p>
                
                <form method="post" action="">
                    <button type="submit" name="run_script" class="btn btn-primary">
                        <i class="fas fa-play me-2"></i> Run Auto-Cancel Script Now
                    </button>
                </form>
            </div>
        </div>
        
        <?php if ($output): ?>
        <div class="card">
            <div class="card-header">
                <h2>Script Output</h2>
            </div>
            <div class="card-body">
                <div class="output-box">
                    <?php echo $output; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Manual Verification</h2>
            </div>
            <div class="card-body">
                <p>To manually check all confirmed unpaid bookings:</p>
                
                <?php
                // Query to find all confirmed unpaid bookings
                $sql = "SELECT id, booking_reference, updated_at, payment_status, 
                        TIMESTAMPDIFF(MINUTE, updated_at, NOW()) as minutes_since_update
                        FROM bookings 
                        WHERE status = 'confirmed' 
                        AND payment_status NOT IN ('paid')
                        ORDER BY updated_at DESC";
                
                $result = $conn->query($sql);
                
                if ($result && $result->num_rows > 0):
                ?>
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Reference</th>
                            <th>Updated At</th>
                            <th>Minutes Since Update</th>
                            <th>Payment Status</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): 
                            $minutes_left = 60 - $row['minutes_since_update'];
                            $row_class = '';
                            $status = '';
                            
                            if ($minutes_left <= 0) {
                                $row_class = 'table-danger';
                                $status = 'Should be cancelled!';
                            } else if ($minutes_left < 15) {
                                $row_class = 'table-warning';
                                $status = 'Will be cancelled in ' . $minutes_left . ' minutes';
                            } else {
                                $status = 'Will be cancelled in ' . $minutes_left . ' minutes';
                            }
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['booking_reference']; ?></td>
                            <td><?php echo $row['updated_at']; ?></td>
                            <td><?php echo $row['minutes_since_update']; ?></td>
                            <td><?php echo $row['payment_status']; ?></td>
                            <td><?php echo $status; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert alert-info">
                    No confirmed unpaid bookings found.
                </div>
                <?php endif; ?>
                
                <a href="index.php" class="btn btn-secondary mt-3">
                    <i class="fas fa-home me-2"></i> Return to Homepage
                </a>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 