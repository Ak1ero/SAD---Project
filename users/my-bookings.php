<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../db/config.php';
// Include the event status update script
include_once '../db/update_event_status.php';

// First, reset any events that might have been incorrectly marked as completed
$resetSql = "UPDATE bookings 
             SET status = 'confirmed', updated_at = NOW() 
             WHERE status = 'completed' 
             AND (
                 (event_date > CURDATE()) 
                 OR 
                 (event_date = CURDATE() AND event_end_time > CURTIME())
                 OR
                 payment_status NOT IN ('paid', 'partially_paid')
             )";
$conn->query($resetSql);

// Now run the update function to mark completed events
updateCompletedEventStatus();

// Fetch user's bookings with guest count and services
$user_id = $_SESSION['user_id'];
$sql = "SELECT b.*, 
        (SELECT COUNT(*) FROM guests bg WHERE bg.booking_id = b.id) as guest_count,
        (SELECT GROUP_CONCAT(service_name SEPARATOR ', ') FROM booking_services bs WHERE bs.booking_id = b.id) as services,
        COALESCE((SELECT SUM(service_price) FROM booking_services bs WHERE bs.booking_id = b.id), 0) as services_total,
        b.total_amount as base_amount,
        COALESCE(pt.status, 'unpaid') as payment_status,
        pt.payment_method,
        pt.amount as payment_amount
        FROM bookings b 
        LEFT JOIN payment_transactions pt ON b.id = pt.booking_id
        WHERE b.user_id = ? 
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations | The Barn & Backyard</title>
    <link rel="icon" href="../img/barn-backyard.svg" type="image/svg+xml"/>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #5D5FEF;
            --secondary-color: #E0E7FF;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-color: #f9fafb;
            --dark-color: #1f2937;
            --gray-color: #6b7280;
            --header-font: 'Cormorant Garamond', serif;
            --body-font: 'Montserrat', sans-serif;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: var(--body-font);
        }

        body {
            background-color: #f9fafb;
            min-height: 100vh;
            color: var(--dark-color);
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--header-font);
            font-weight: 600;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            position: relative;
            padding: 3rem 0;
            margin-bottom: 2rem;
            text-align: center;
            background: linear-gradient(135deg, #f0f4ff, #e6effe);
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%235D5FEF' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .dashboard-title {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 0px 2px 4px rgba(93, 95, 239, 0.1);
        }

        .header-subtitle {
            color: var(--dark-color);
            opacity: 0.8;
            font-weight: 400;
            margin-bottom: 2rem;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border-radius: 0.5rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            gap: 0.5rem;
            box-shadow: 0 4px 6px rgba(93, 95, 239, 0.25);
        }

        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(93, 95, 239, 0.3);
        }

        .action-button.secondary {
            background-color: #2c3e50;
        }

        .action-button.secondary:hover {
            background-color: #1a252f;
        }

        .reservations-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .reservations-card:hover {
            box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.07), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .reservations-header {
            background: var(--light-color);
            padding: 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reservations-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .reservations-table th {
            background-color: rgba(93, 95, 239, 0.05);
            font-weight: 600;
            color: var(--primary-color);
            padding: 1.25rem 1.5rem;
            text-align: left;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .reservations-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            vertical-align: middle;
        }

        .reservations-table tr:last-child td {
            border-bottom: none;
        }

        .reservations-table tr {
            transition: background-color 0.2s ease;
        }

        .reservations-table tr:hover {
            background-color: rgba(93, 95, 239, 0.02);
        }

        .booking-reference {
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .booking-reference i {
            opacity: 0.7;
            font-size: 0.9rem;
        }

        .event-date {
            font-weight: 500;
            white-space: nowrap;
        }

        .event-time {
            white-space: nowrap;
            color: var(--gray-color);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .package-name {
            font-weight: 500;
            color: var(--dark-color);
        }

        .theme-name {
            color: var(--gray-color);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .amount {
            font-weight: 600;
        }

        .amount-paid {
            color: var(--success-color);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: capitalize;
            letter-spacing: 0.025em;
            gap: 0.35rem;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #664d03;
        }
        
        .status-confirmed {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #842029;
        }
        
        .status-completed {
            background-color: #cfe2ff;
            color: #084298;
        }

        .status-paid {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .status-unpaid {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .status-partially_paid {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1.2rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            gap: 0.5rem;
            transition: all 0.3s ease;
            min-width: 110px; /* Ensure minimum width for buttons */
        }

        .btn-view {
            background-color: #f3f4f6;
            color: var(--dark-color);
        }

        .btn-view:hover {
            background-color: #e5e7eb;
        }

        .btn-pay {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-pay:hover {
            background-color: #4b4dcb;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(93, 95, 239, 0.25);
        }

        .btn-cancel {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            white-space: nowrap;
        }

        .btn-cancel:hover {
            background-color: rgba(239, 68, 68, 0.2);
        }

        .expand-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 1.2rem;
            transition: transform 0.3s ease;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .expand-btn:hover {
            background-color: rgba(93, 95, 239, 0.05);
        }

        .expanded .expand-btn {
            transform: rotate(180deg);
            background-color: rgba(93, 95, 239, 0.1);
        }

        .reservation-details {
            display: none;
            background-color: rgba(93, 95, 239, 0.02);
            padding: 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .detail-item {
            padding: 1.25rem;
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .detail-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-label i {
            color: var(--primary-color);
            opacity: 0.7;
        }

        .detail-value {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 1.1rem;
        }

        .empty-state {
            background-color: white;
            border-radius: 1rem;
            padding: 4rem 2rem;
            text-align: center;
            box-shadow: var(--card-shadow);
        }

        .empty-icon {
            width: 6rem;
            height: 6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(93, 95, 239, 0.1);
            border-radius: 50%;
            margin: 0 auto 2rem;
            color: var(--primary-color);
            font-size: 3rem;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .empty-title {
            font-size: 1.75rem;
            color: var(--dark-color);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .empty-description {
            color: var(--gray-color);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        @media (max-width: 1024px) {
            .detail-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .page-header {
                padding: 2rem 1rem;
            }
            
            .dashboard-title {
                font-size: 2rem;
            }
            
            .reservations-table thead {
                display: none;
            }
            
            .reservations-table, 
            .reservations-table tbody, 
            .reservations-table tr, 
            .reservations-table td {
                display: block;
                width: 100%;
            }
            
            .reservations-table tr {
                margin-bottom: 1rem;
                border-bottom: 1px solid rgba(0, 0, 0, 0.05);
                position: relative;
            }
            
            .reservations-table td {
                display: flex;
                text-align: right;
                padding: 0.75rem 1rem;
                border-bottom: none;
            }

            .reservations-table td[data-label="Actions"] {
                padding: 0.75rem 1rem 1.5rem 1rem;
                flex-wrap: wrap;
            }
            
            .reservations-table td::before {
                content: attr(data-label);
                font-weight: 600;
                margin-right: auto;
                color: var(--primary-color);
            }
            
            .action-buttons {
                justify-content: flex-end;
                flex-wrap: wrap;
                width: 100%;
                row-gap: 10px;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .reservations-table td[data-label="Actions"] {
                padding: 0.75rem 1rem 1.5rem 1rem;
            }
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-container {
            background-color: white;
            border-radius: 1rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            padding: 2rem;
            position: relative;
            transform: translateY(20px);
            animation: slideUp 0.3s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-icon {
            width: 70px;
            height: 70px;
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            font-size: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            text-align: center;
            margin-bottom: 0.75rem;
            font-family: var(--header-font);
        }

        .modal-message {
            text-align: center;
            color: var(--gray-color);
            margin-bottom: 2rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .modal-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            min-width: 120px;
        }

        .modal-btn-cancel {
            background-color: #f3f4f6;
            color: var(--dark-color);
        }

        .modal-btn-cancel:hover {
            background-color: #e5e7eb;
        }

        .modal-btn-confirm {
            background-color: var(--danger-color);
            color: white;
        }

        .modal-btn-confirm:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="header-content">
                <h1 class="dashboard-title">My Reservations</h1>
                <p class="header-subtitle">Manage your event bookings in one place</p>
                <a href="../index.php" class="action-button secondary">
                    <i class="fas fa-arrow-left"></i> Back to Homepage
                </a>
            </div>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="reservations-card">
                <div class="reservations-header">
                    <h2>Your Upcoming Events</h2>
                </div>
                <div class="reservations-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Booking Reference</th>
                                <th>Event Date</th>
                                <th>Package & Theme</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($booking = $result->fetch_assoc()): ?>
                                <tr class="reservation-row" data-id="<?php echo $booking['id']; ?>" onclick="toggleDetails('<?php echo $booking['id']; ?>')">
                                    <td data-label="Booking Reference">
                                        <div class="booking-reference">
                                            <i class="fas fa-receipt"></i>
                                            #<?php echo htmlspecialchars($booking['booking_reference']); ?>
                                        </div>
                                    </td>
                                    <td data-label="Event Date">
                                        <div class="event-date"><?php echo date('F d, Y', strtotime($booking['event_date'])); ?></div>
                                        <div class="event-time">
                                            <?php 
                                            $start = date('h:i A', strtotime($booking['event_start_time']));
                                            $end = date('h:i A', strtotime($booking['event_end_time']));
                                            echo $start . ' - ' . $end; 
                                            ?>
                                        </div>
                                    </td>
                                    <td data-label="Package & Theme">
                                        <div class="package-name"><?php echo ucwords(strtolower(htmlspecialchars($booking['package_name']))); ?></div>
                                        <div class="theme-name"><?php echo ucwords(strtolower(htmlspecialchars($booking['theme_name']))); ?></div>
                                    </td>
                                    <td data-label="Amount">
                                        <div class="amount">
                                            ₱<?php 
                                            $total = $booking['base_amount'] + $booking['services_total'];
                                            echo number_format($total, 2, '.', ','); 
                                            ?>
                                        </div>
                                        <?php if($booking['payment_status'] == 'paid' && $booking['payment_amount']): ?>
                                        <div class="amount-paid">
                                            Paid: ₱<?php echo number_format($booking['payment_amount'], 2, '.', ','); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status">
                                        <?php if($booking['status'] == 'pending'): ?>
                                            <span class="status-badge status-pending">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php elseif($booking['status'] == 'confirmed' && $booking['payment_status'] == 'partially_paid'): ?>
                                            <span class="status-badge status-partially_paid">
                                                <i class="fas fa-percentage"></i> Partially Paid
                                            </span>
                                        <?php elseif($booking['status'] == 'confirmed' && $booking['payment_status'] != 'paid'): ?>
                                            <span class="status-badge status-confirmed">
                                                <i class="fas fa-check-circle"></i> Confirmed
                                            </span>
                                        <?php elseif(($booking['status'] == 'confirmed' || $booking['status'] == 'pending') && $booking['payment_status'] == 'paid'): ?>
                                            <span class="status-badge status-paid">
                                                <i class="fas fa-credit-card"></i> Paid
                                            </span>
                                        <?php elseif($booking['status'] == 'completed'): ?>
                                            <span class="status-badge status-completed">
                                                <i class="fas fa-flag-checkered"></i> Completed
                                            </span>
                                        <?php elseif($booking['status'] == 'cancelled'): ?>
                                            <span class="status-badge status-cancelled">
                                                <i class="fas fa-times-circle"></i> Cancelled
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge">
                                                <i class="fas fa-exclamation-circle"></i> <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="action-buttons">
                                            <?php if($booking['payment_status'] == 'paid'): ?>
                                                <button class="btn btn-view" onclick="viewBookingDetails('<?php echo $booking['id']; ?>'); event.stopPropagation();">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                            <?php endif; ?>
                                            <?php if($booking['status'] != 'cancelled' && $booking['status'] != 'completed'): ?>
                                                <?php if($booking['payment_status'] != 'paid'): ?>
                                                    <button class="btn btn-pay" onclick="window.location.href='payment.php?booking_id=<?php echo $booking['id']; ?>'; event.stopPropagation();">
                                                        <i class="fas fa-credit-card"></i> Pay Now
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if($booking['status'] == 'pending'): ?>
                                                    <button class="btn btn-cancel" onclick="showCancelModal('<?php echo $booking['id']; ?>', '<?php echo htmlspecialchars($booking['booking_reference']); ?>'); event.stopPropagation();">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="reservation-details-row" id="details-<?php echo $booking['id']; ?>" style="display: none;">
                                    <td colspan="7">
                                        <div class="reservation-details">
                                            <div class="detail-grid">
                                                <div class="detail-item">
                                                    <div class="detail-label">
                                                        <i class="fas fa-users"></i> Guest Count
                                                    </div>
                                                    <div class="detail-value"><?php echo $booking['guest_count']; ?> guests</div>
                                                </div>
                                                <div class="detail-item">
                                                    <div class="detail-label">
                                                        <i class="fas fa-concierge-bell"></i> Additional Services
                                                    </div>
                                                    <div class="detail-value">
                                                        <?php echo $booking['services'] ? htmlspecialchars($booking['services']) : 'None'; ?>
                                                    </div>
                                                </div>
                                                <div class="detail-item">
                                                    <div class="detail-label">
                                                        <i class="fas fa-tags"></i> Services Total
                                                    </div>
                                                    <div class="detail-value">₱<?php echo number_format($booking['services_total'], 2, '.', ','); ?></div>
                                                </div>
                                                <div class="detail-item">
                                                    <div class="detail-label">
                                                        <i class="fas fa-file-invoice-dollar"></i> Base Amount
                                                    </div>
                                                    <div class="detail-value">₱<?php echo number_format($booking['base_amount'], 2, '.', ','); ?></div>
                                                </div>
                                                <div class="detail-item">
                                                    <div class="detail-label">
                                                        <i class="fas fa-money-check-alt"></i> Payment Method
                                                    </div>
                                                    <div class="detail-value">
                                                        <?php echo $booking['payment_method'] ? ucfirst(htmlspecialchars($booking['payment_method'])) : 'Not paid yet'; ?>
                                                    </div>
                                                </div>
                                                <div class="detail-item">
                                                    <div class="detail-label">
                                                        <i class="fas fa-calendar-plus"></i> Booking Date
                                                    </div>
                                                    <div class="detail-value"><?php echo date('F d, Y', strtotime($booking['created_at'])); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <h3 class="empty-title">No Reservations Found</h3>
                <p class="empty-description">You haven't made any reservations yet. Start planning your perfect event at The Barn & Backyard!</p>
                <a href="booking-form.php" class="action-button">
                    <i class="fas fa-plus"></i> Create Reservation
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Booking Details Modal -->
    <div id="bookingDetailsModal" class="modal-overlay">
        <div class="modal-container" style="max-width: 650px; padding: 0; overflow: hidden; border-radius: 1rem; max-height: 90vh; overflow-y: auto;">
            <div style="background: linear-gradient(135deg, var(--primary-color), #4b4dcb); color: white; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10;">
                <h3 style="margin: 0; font-size: 1.5rem; font-weight: 600; font-family: var(--header-font);">Reservation Details</h3>
                <button onclick="hideBookingDetailsModal()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="bookingDetailsContent" style="padding: 1.5rem;">
                <!-- Header Section -->
                <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <div style="background-color: rgba(93, 95, 239, 0.1); color: var(--primary-color); width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.8rem; color: var(--gray-color);">Booking Reference</div>
                                <div style="font-weight: 600; font-size: 1.1rem; color: var(--primary-color);" id="modal-booking-reference"></div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div style="text-align: right; margin-bottom: 0.5rem;">
                            <div style="font-size: 0.8rem; color: var(--gray-color);">Booking Status</div>
                            <div style="font-weight: 600; color: var(--success-color);" id="modal-status"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Event Details -->
                <h4 style="margin-bottom: 1rem; font-size: 1.1rem; color: var(--dark-color); border-left: 3px solid var(--primary-color); padding-left: 0.6rem;">Event Information</h4>
                <div class="detail-grid" style="grid-template-columns: repeat(2, 1fr); gap: 0.8rem; margin-bottom: 1.5rem;">
                    <div class="detail-item" style="border-radius: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; padding: 0.8rem;">
                        <div class="detail-label" style="color: var(--gray-color); font-size: 0.75rem;"><i class="fas fa-calendar" style="color: var(--primary-color);"></i> Event Date</div>
                        <div class="detail-value" style="font-weight: 600; font-size: 0.95rem;" id="modal-event-date"></div>
                    </div>
                    <div class="detail-item" style="border-radius: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; padding: 0.8rem;">
                        <div class="detail-label" style="color: var(--gray-color); font-size: 0.75rem;"><i class="fas fa-clock" style="color: var(--primary-color);"></i> Event Time</div>
                        <div class="detail-value" style="font-weight: 600; font-size: 0.95rem;" id="modal-event-time"></div>
                    </div>
                    <div class="detail-item" style="border-radius: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; padding: 0.8rem;">
                        <div class="detail-label" style="color: var(--gray-color); font-size: 0.75rem;"><i class="fas fa-box" style="color: var(--primary-color);"></i> Package</div>
                        <div class="detail-value" style="font-weight: 600; font-size: 0.95rem;" id="modal-package"></div>
                    </div>
                    <div class="detail-item" style="border-radius: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; padding: 0.8rem;">
                        <div class="detail-label" style="color: var(--gray-color); font-size: 0.75rem;"><i class="fas fa-palette" style="color: var(--primary-color);"></i> Theme</div>
                        <div class="detail-value" style="font-weight: 600; font-size: 0.95rem;" id="modal-theme"></div>
                    </div>
                    <div class="detail-item" style="border-radius: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; padding: 0.8rem;">
                        <div class="detail-label" style="color: var(--gray-color); font-size: 0.75rem;"><i class="fas fa-users" style="color: var(--primary-color);"></i> Guest Count</div>
                        <div class="detail-value" style="font-weight: 600; font-size: 0.95rem;" id="modal-guest-count"></div>
                    </div>
                    <div class="detail-item" style="border-radius: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; padding: 0.8rem;">
                        <div class="detail-label" style="color: var(--gray-color); font-size: 0.75rem;"><i class="fas fa-calendar-plus" style="color: var(--primary-color);"></i> Booking Date</div>
                        <div class="detail-value" style="font-weight: 600; font-size: 0.95rem;" id="modal-booking-date"></div>
                    </div>
                </div>
                
                <!-- Financial Details -->
                <h4 style="margin-bottom: 1rem; font-size: 1.1rem; color: var(--dark-color); border-left: 3px solid var(--success-color); padding-left: 0.6rem;">Payment Information</h4>
                <div class="detail-grid" style="grid-template-columns: repeat(2, 1fr); gap: 0.8rem;">
                    <div class="detail-item" style="border-radius: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; padding: 0.8rem;">
                        <div class="detail-label" style="color: var(--gray-color); font-size: 0.75rem;"><i class="fas fa-file-invoice-dollar" style="color: var(--success-color);"></i> Base Amount</div>
                        <div class="detail-value" style="font-weight: 600; font-size: 0.95rem;" id="modal-base-amount"></div>
                    </div>
                    <div class="detail-item" style="border-radius: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; padding: 0.8rem;">
                        <div class="detail-label" style="color: var(--gray-color); font-size: 0.75rem;"><i class="fas fa-concierge-bell" style="color: var(--success-color);"></i> Additional Services</div>
                        <div class="detail-value" style="font-weight: 600; font-size: 0.95rem;" id="modal-services"></div>
                    </div>
                    <div class="detail-item" style="border-radius: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; padding: 0.8rem;">
                        <div class="detail-label" style="color: var(--gray-color); font-size: 0.75rem;"><i class="fas fa-tags" style="color: var(--success-color);"></i> Services Total</div>
                        <div class="detail-value" style="font-weight: 600; font-size: 0.95rem;" id="modal-services-total"></div>
                    </div>
                    <div class="detail-item" style="border-radius: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; padding: 0.8rem;">
                        <div class="detail-label" style="color: var(--gray-color); font-size: 0.75rem;"><i class="fas fa-money-check-alt" style="color: var(--success-color);"></i> Payment Method</div>
                        <div class="detail-value" style="font-weight: 600; font-size: 0.95rem;" id="modal-payment-method"></div>
                    </div>
                    <div class="detail-item" style="border-radius: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; padding: 0.8rem;">
                        <div class="detail-label" style="color: var(--gray-color); font-size: 0.75rem;"><i class="fas fa-money-bill-alt" style="color: var(--success-color);"></i> Payment Status</div>
                        <div class="detail-value" style="font-weight: 600; font-size: 0.95rem;" id="modal-payment-status"></div>
                    </div>
                    <div class="detail-item" style="border-radius: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; background-color: rgba(16, 185, 129, 0.05); padding: 0.8rem;">
                        <div class="detail-label" style="color: var(--gray-color); font-size: 0.75rem;"><i class="fas fa-money-bill-wave" style="color: var(--success-color);"></i> Total Amount</div>
                        <div class="detail-value" style="font-weight: 700; font-size: 1.1rem; color: var(--success-color);" id="modal-total-amount"></div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div style="margin-top: 1.5rem; text-align: center; background-color: #f9fafb; padding: 0.8rem; border-radius: 0.6rem; margin-left: -1.5rem; margin-right: -1.5rem; margin-bottom: -1.5rem; border-top: 1px solid #eee;">
                    <button id="sendInvitationsBtn" class="btn btn-pay" style="background: var(--primary-color); margin-bottom: 0.8rem; min-width: 220px;" onclick="sendInvitations(); return false;">
                        <i class="fas fa-envelope"></i> Send Invitation to Guests
                    </button>
                    <p style="margin: 0; color: var(--gray-color); font-size: 0.8rem;">
                        Thank you for choosing <strong>The Barn & Backyard</strong> for your event!
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Invitation Results Modal -->
    <div id="invitationResultsModal" class="modal-overlay">
        <div class="modal-container" style="max-width: 500px; padding: 0; overflow: hidden; border-radius: 1rem;">
            <div style="background: linear-gradient(135deg, var(--primary-color), #4b4dcb); color: white; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.5rem; font-weight: 600; font-family: var(--header-font);">Invitation Results</h3>
                <button onclick="hideInvitationResultsModal()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div style="padding: 1.5rem;">
                <!-- Loading indicator -->
                <div id="invitationLoading" style="text-align: center; padding: 2rem 0;">
                    <div style="display: inline-block; width: 50px; height: 50px; border: 5px solid rgba(93, 95, 239, 0.1); border-radius: 50%; border-top-color: var(--primary-color); animation: spin 1s linear infinite;"></div>
                    <p style="margin-top: 1rem; font-weight: 500;">Sending invitations...</p>
                </div>
                
                <!-- Success content -->
                <div id="invitationSuccess" style="display: none;">
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <div style="width: 70px; height: 70px; background-color: rgba(16, 185, 129, 0.1); color: var(--success-color); font-size: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <i class="fas fa-check"></i>
                        </div>
                        <h4 style="font-size: 1.2rem; margin-bottom: 0.5rem;">Invitations Sent!</h4>
                        <p id="invitationSuccessMessage" style="color: var(--gray-color);">Your invitations have been sent successfully.</p>
                    </div>
                    
                    <div style="background-color: #f9fafb; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Total Guests:</span>
                            <span id="totalGuestsCount" style="font-weight: 600;">0</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Successfully Sent:</span>
                            <span id="successCount" style="font-weight: 600; color: var(--success-color);">0</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Failed:</span>
                            <span id="failureCount" style="font-weight: 600; color: var(--danger-color);">0</span>
                        </div>
                    </div>
                    
                    <div id="failedGuestsContainer" style="display: none;">
                        <h5 style="font-size: 1rem; margin-bottom: 0.5rem;">Failed Invitations:</h5>
                        <ul id="failedGuestsList" style="margin-top: 0.5rem; padding-left: 1.5rem; color: var(--gray-color);"></ul>
                    </div>
                    
                    <button onclick="hideInvitationResultsModal()" class="btn" style="width: 100%; background-color: #f3f4f6; margin-top: 1rem;">
                        Close
                    </button>
                </div>
                
                <!-- Error content -->
                <div id="invitationError" style="display: none; text-align: center;">
                    <div style="width: 70px; height: 70px; background-color: rgba(239, 68, 68, 0.1); color: var(--danger-color); font-size: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 style="font-size: 1.2rem; margin-bottom: 0.5rem;">Error</h4>
                    <p id="invitationErrorMessage" style="color: var(--gray-color); margin-bottom: 1.5rem;">Failed to send invitations.</p>
                    
                    <button onclick="hideInvitationResultsModal()" class="btn" style="width: 100%; background-color: #f3f4f6;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div id="cancelModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 class="modal-title">Cancel Reservation</h3>
            <p class="modal-message">
                Are you sure you want to cancel your reservation <span id="bookingRef"></span>? This action cannot be undone.
            </p>
            <div class="modal-actions">
                <button id="cancelBtn" class="modal-btn modal-btn-cancel">No, Keep it</button>
                <button id="confirmCancelBtn" class="modal-btn modal-btn-confirm">Yes, Cancel</button>
            </div>
        </div>
    </div>

    <script>
        let currentBookingId = null;

        function showCancelModal(bookingId, bookingRef) {
            currentBookingId = bookingId;
            document.getElementById('bookingRef').textContent = `#${bookingRef}`;
            const modal = document.getElementById('cancelModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function hideCancelModal() {
            const modal = document.getElementById('cancelModal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
            currentBookingId = null;
        }

        function viewBookingDetails(bookingId) {
            // Make currentBookingId available for other functions
            currentBookingId = bookingId;
            
            // Collect data from the row
            const row = document.querySelector(`.reservation-row[data-id="${bookingId}"]`);
            const detailsRow = document.getElementById(`details-${bookingId}`);
            
            // Get data from the current row
            const bookingRef = row.querySelector('.booking-reference').textContent.trim();
            const eventDate = row.querySelector('.event-date').textContent.trim();
            const eventTime = row.querySelector('.event-time').textContent.trim();
            const packageName = row.querySelector('.package-name').textContent.trim();
            const themeName = row.querySelector('.theme-name').textContent.trim();
            const amount = row.querySelector('.amount').textContent.trim();
            
            // Get status
            let status = '';
            const statusBadge = row.querySelector('.status-badge');
            if (statusBadge) {
                status = statusBadge.textContent.trim();
            }
            
            // Get data from the details row
            let guestCount = '';
            let services = '';
            let servicesTotal = '';
            let baseAmount = '';
            let paymentMethod = '';
            let bookingDate = '';
            
            if (detailsRow) {
                const detailItems = detailsRow.querySelectorAll('.detail-item');
                detailItems.forEach(item => {
                    const label = item.querySelector('.detail-label').textContent.trim();
                    const value = item.querySelector('.detail-value').textContent.trim();
                    
                    if (label.includes('Guest Count')) guestCount = value;
                    if (label.includes('Additional Services')) services = value;
                    if (label.includes('Services Total')) servicesTotal = value;
                    if (label.includes('Base Amount')) baseAmount = value;
                    if (label.includes('Payment Method')) paymentMethod = value;
                    if (label.includes('Booking Date')) bookingDate = value;
                });
            }
            
            // Update modal content
            document.getElementById('modal-booking-reference').textContent = bookingRef;
            document.getElementById('modal-event-date').textContent = eventDate;
            document.getElementById('modal-event-time').textContent = eventTime;
            document.getElementById('modal-package').textContent = packageName;
            document.getElementById('modal-theme').textContent = themeName;
            document.getElementById('modal-guest-count').textContent = guestCount || 'N/A';
            document.getElementById('modal-services').textContent = services || 'None';
            document.getElementById('modal-services-total').textContent = servicesTotal || '₱0.00';
            document.getElementById('modal-base-amount').textContent = baseAmount || 'N/A';
            document.getElementById('modal-total-amount').textContent = amount;
            document.getElementById('modal-payment-method').textContent = paymentMethod || 'Not paid yet';
            document.getElementById('modal-payment-status').textContent = status.includes('Paid') ? 'Paid' : (status.includes('Partially') ? 'Partially Paid' : 'Unpaid');
            document.getElementById('modal-booking-date').textContent = bookingDate || 'N/A';
            document.getElementById('modal-status').textContent = status;
            
            // Show modal
            const modal = document.getElementById('bookingDetailsModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function hideBookingDetailsModal() {
            const modal = document.getElementById('bookingDetailsModal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        document.getElementById('cancelBtn').addEventListener('click', hideCancelModal);
        
        document.getElementById('confirmCancelBtn').addEventListener('click', function() {
            if (currentBookingId) {
                cancelBooking(currentBookingId);
            }
        });

        function cancelBooking(bookingId) {
            fetch(`cancel-booking.php?id=${bookingId}`, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                hideCancelModal();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to cancel reservation');
                }
            })
            .catch(error => {
                hideCancelModal();
                console.error('Error:', error);
                alert('An error occurred while canceling the reservation');
            });
        }

        function toggleDetails(bookingId) {
            const detailsRow = document.getElementById(`details-${bookingId}`);
            const currentRow = document.querySelector(`.reservation-row[data-id="${bookingId}"]`);
            
            if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
                detailsRow.style.display = 'table-row';
                currentRow.classList.add('expanded');
            } else {
                detailsRow.style.display = 'none';
                currentRow.classList.remove('expanded');
            }
        }

        // Close modals if clicking on overlay
        document.getElementById('cancelModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideCancelModal();
            }
        });
        
        document.getElementById('bookingDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideBookingDetailsModal();
            }
        });
        
        document.getElementById('invitationResultsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideInvitationResultsModal();
            }
        });
        
        // New functions for handling invitations
        function sendInvitations() {
            if (!currentBookingId) {
                alert('Booking ID not found. Please try again.');
                return;
            }
            
            // Hide booking details modal
            hideBookingDetailsModal();
            
            // Show invitation results modal with loading state
            showInvitationResultsModal('loading');
            
            // Send AJAX request to send invitations
            const formData = new FormData();
            formData.append('booking_id', currentBookingId);
            
            fetch('../email/send-invitations.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success state
                    showInvitationResultsModal('success', data);
                } else {
                    // Show error state
                    showInvitationResultsModal('error', data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showInvitationResultsModal('error', { message: 'An error occurred while sending invitations.' });
            });
        }
        
        function showInvitationResultsModal(state, data = {}) {
            const modal = document.getElementById('invitationResultsModal');
            const loadingSection = document.getElementById('invitationLoading');
            const successSection = document.getElementById('invitationSuccess');
            const errorSection = document.getElementById('invitationError');
            
            // Reset all sections
            loadingSection.style.display = 'none';
            successSection.style.display = 'none';
            errorSection.style.display = 'none';
            
            // Show the appropriate section based on state
            if (state === 'loading') {
                loadingSection.style.display = 'block';
            } else if (state === 'success') {
                successSection.style.display = 'block';
                
                // Update success message and counts
                document.getElementById('invitationSuccessMessage').textContent = data.message || 'Invitations sent successfully.';
                document.getElementById('totalGuestsCount').textContent = data.total || 0;
                document.getElementById('successCount').textContent = data.success_count || 0;
                document.getElementById('failureCount').textContent = data.failure_count || 0;
                
                // Show failed guests if any
                const failedGuestsContainer = document.getElementById('failedGuestsContainer');
                const failedGuestsList = document.getElementById('failedGuestsList');
                
                if (data.failure_count > 0 && data.failed_guests && data.failed_guests.length > 0) {
                    failedGuestsContainer.style.display = 'block';
                    failedGuestsList.innerHTML = '';
                    
                    data.failed_guests.forEach(guest => {
                        const li = document.createElement('li');
                        li.textContent = guest;
                        failedGuestsList.appendChild(li);
                    });
                } else {
                    failedGuestsContainer.style.display = 'none';
                }
                
            } else if (state === 'error') {
                errorSection.style.display = 'block';
                document.getElementById('invitationErrorMessage').textContent = data.message || 'Failed to send invitations.';
            }
            
            // Show the modal
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function hideInvitationResultsModal() {
            const modal = document.getElementById('invitationResultsModal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        // Add keyframe animation for loading spinner
        const styleSheet = document.createElement('style');
        styleSheet.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>