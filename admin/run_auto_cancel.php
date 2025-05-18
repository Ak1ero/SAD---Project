<?php
session_start();
include '../db/config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Function to run the auto-cancel script and return output
function runAutoCancelScript() {
    // Execute the auto-cancel script and capture output
    ob_start();
    include '../auto_cancel_unpaid_bookings.php';
    $output = ob_get_clean();
    
    return $output;
}

// Process AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $output = runAutoCancelScript();
    
    // Return response as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Auto-cancel script executed successfully',
        'output' => $output
    ]);
    exit();
}

// If not AJAX, display admin page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto-Cancel Unpaid Bookings - Admin</title>
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="container">
        <h1>Auto-Cancel Unpaid Bookings</h1>
        <p>This tool will cancel all confirmed bookings that have not been paid within 1 hour.</p>
        
        <div class="card">
            <div class="card-header">
                <h2>Manual Run</h2>
            </div>
            <div class="card-body">
                <p>Click the button below to manually run the auto-cancel script:</p>
                <button id="runScriptBtn" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Run Auto-Cancel Script
                </button>
                
                <div id="resultBox" class="mt-4" style="display: none;">
                    <h3>Script Output:</h3>
                    <pre id="scriptOutput" class="bg-light p-3" style="max-height: 300px; overflow-y: auto;"></pre>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h2>About Auto-Cancel</h2>
            </div>
            <div class="card-body">
                <p>The auto-cancel script helps maintain the integrity of your booking system by:</p>
                <ul>
                    <li>Preventing users from holding reservations indefinitely without payment</li>
                    <li>Freeing up event dates for serious customers</li>
                    <li>Automatically cancelling confirmed bookings that remain unpaid after 1 hour</li>
                </ul>
                <p><strong>Note:</strong> For automated running, set up a cron job to execute <code>auto_cancel_unpaid_bookings.php</code> every 15 minutes.</p>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('runScriptBtn').addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            
            // Disable button and show loading
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running...';
            
            // Call the script via AJAX
            fetch('run_auto_cancel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show results
                    document.getElementById('scriptOutput').textContent = data.output;
                    document.getElementById('resultBox').style.display = 'block';
                    
                    // Show success message
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    throw new Error(data.message || 'Failed to run script');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'An error occurred while running the script',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            })
            .finally(() => {
                // Re-enable button
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    </script>
</body>
</html> 