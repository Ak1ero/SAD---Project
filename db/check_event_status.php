<?php
// Debug script to check event status logic

// Include the event status update file
include_once 'update_event_status.php';

// Set error reporting for troubleshooting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add some basic styling
echo '<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1200px; margin: 0 auto; }
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    button { padding: 10px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
    button:hover { background-color: #45a049; }
    .explanation { background-color: #e7f3fe; border-left: 6px solid #2196F3; padding: 10px; margin-bottom: 20px; }
    .status-flow { margin: 15px 0; display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 10px; }
    .status-step { padding: 8px 15px; border-radius: 20px; margin: 0 5px; font-weight: bold; }
    .pending { background-color: #fff3cd; color: #664d03; }
    .confirmed { background-color: #d1e7dd; color: #0f5132; }
    .paid { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
    .completed { background-color: #cfe2ff; color: #084298; }
    .cancelled { background-color: #f8d7da; color: #842029; }
    .arrow { font-size: 20px; color: #6c757d; }
</style>';

// Run the status check function
echo "<h2>Event Status Debugging Tool</h2>";

echo "<div class='explanation'>";
echo "<p><strong>This tool shows the event status progression:</strong></p>";
echo "<div class='status-flow'>";
echo "<span class='status-step pending'>Pending</span>";
echo "<span class='arrow'>→</span>";
echo "<span class='status-step confirmed'>Confirmed</span>";
echo "<span class='arrow'>→</span>";
echo "<span class='status-step paid'>Paid</span>";
echo "<span class='arrow'>→</span>";
echo "<span class='status-step completed'>Completed</span>";
echo "</div>";
echo "<p><strong>Status Progression Explained:</strong></p>";
echo "<ol>";
echo "<li><strong>Pending</strong> - Initial status when booking is created</li>";
echo "<li><strong>Confirmed</strong> - When admin confirms the booking</li>";
echo "<li><strong>Paid</strong> - When payment is recorded (status shown as Paid in UI)</li>";
echo "<li><strong>Completed</strong> - When the event date and end time have passed</li>";
echo "</ol>";
echo "<p>Note: Cancelled status can occur at any stage before completion.</p>";
echo "</div>";

// Call the debug function
checkEventsStatus();

// Button to manually update statuses
echo "<hr>";
echo "<h3>Manual Status Update</h3>";
echo "<form method='post'>";
echo "<button type='submit' name='update_status'>Update Event Statuses Now</button>";
echo "</form>";

// Process manual update if requested
if (isset($_POST['update_status'])) {
    // First, reset any events that might have been incorrectly marked as completed
    $conn = $GLOBALS['conn'];
    $resetSql = "UPDATE bookings 
                SET status = 'confirmed', updated_at = NOW() 
                WHERE status = 'completed' 
                AND (
                    (event_date > CURDATE()) 
                    OR 
                    (event_date = CURDATE() AND event_end_time > CURTIME())
                )";
    $conn->query($resetSql);
    $resetCount = $conn->affected_rows;
    
    // Now run the update function to mark completed events
    $updatedCount = updateCompletedEventStatus();
    
    echo "<p style='color: green; font-weight: bold;'>";
    if ($resetCount > 0) {
        echo "Reset $resetCount events from 'completed' back to 'confirmed'.<br>";
    }
    echo "Updated $updatedCount events to 'completed' status.</p>";
    echo "<p>Refresh the page to see updated statuses.</p>";
    
    // Provide link to refresh
    echo "<a href='" . $_SERVER['PHP_SELF'] . "' style='display: inline-block; margin-top: 10px; padding: 5px 10px; background-color: #2196F3; color: white; text-decoration: none;'>Refresh Page</a>";
}

// Add simple JavaScript to highlight rows
echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('table tr');
        rows.forEach(row => {
            const statusCell = row.querySelector('td:nth-child(9)'); // The 'Should Be' column
            if (statusCell) {
                if (statusCell.textContent.includes('Should be Completed')) {
                    row.style.backgroundColor = '#cfe2ff';
                }
            }
            
            const displayedStatus = row.querySelector('td:nth-child(8)'); // The 'Displayed Status' column
            if (displayedStatus) {
                if (displayedStatus.textContent === 'Pending') {
                    displayedStatus.style.backgroundColor = '#fff3cd';
                    displayedStatus.style.color = '#664d03';
                } else if (displayedStatus.textContent === 'Confirmed') {
                    displayedStatus.style.backgroundColor = '#d1e7dd';
                    displayedStatus.style.color = '#0f5132';
                } else if (displayedStatus.textContent === 'Paid') {
                    displayedStatus.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                    displayedStatus.style.color = '#10b981';
                } else if (displayedStatus.textContent === 'Should be Completed') {
                    displayedStatus.style.backgroundColor = '#cfe2ff';
                    displayedStatus.style.color = '#084298';
                }
            }
        });
    });
</script>";
?> 