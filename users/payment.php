<?php
session_start();
include '../db/config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['booking_id'])) {
    header("Location: my-bookings.php");
    exit();
}

$booking_id = $_GET['booking_id'];
$user_id = $_SESSION['user_id'];

// Fetch booking details
$sql = "SELECT b.*, 
        COALESCE((SELECT SUM(service_price) FROM booking_services bs WHERE bs.booking_id = b.id), 0) as services_total,
        b.total_amount as base_amount
        FROM bookings b 
        WHERE b.id = ? AND b.user_id = ? AND b.status = 'confirmed'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    header("Location: my-bookings.php");
    exit();
}

$total_amount = $booking['base_amount'] + $booking['services_total'];
$deposit_amount = round($total_amount * 0.5, 2); // Calculate 50% deposit amount
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Barn & Backyard - Payment</title>
    <link rel="icon" href="../img/barn-backyard.svg" type="image/svg+xml"/> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #334155;
        }
        
        .payment-container {
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            border: 1px solid #f0f0f0;
        }
        
        .payment-option {
            background: white;
            border: 1px solid #e4e9f2;
            border-radius: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
        }
        
        .payment-option:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .payment-option:hover {
            border-color: #d1d5db;
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06);
        }
        
        .payment-option:hover:before {
            opacity: 1;
        }
        
        .payment-option.selected {
            border-color: #6366f1;
            background: #fafbff;
            box-shadow: 0 12px 24px rgba(99, 102, 241, 0.15);
        }
        
        .payment-option.selected:before {
            opacity: 1;
        }
        
        .payment-amount-option {
            background: white;
            border: 1px solid #e4e9f2;
            border-radius: 16px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .payment-amount-option:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #10b981, #059669);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .payment-amount-option:hover {
            border-color: #d1d5db;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }
        
        .payment-amount-option:hover:before {
            opacity: 1;
        }
        
        .payment-amount-option.selected {
            border-color: #10b981;
            background: #f8fefc;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.15);
        }
        
        .payment-amount-option.selected:before {
            opacity: 1;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 35px;
            border-radius: 24px;
            max-width: 650px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            transform: translateY(30px);
            animation: modal-appear 0.4s forwards;
            margin-bottom: 50px;
        }
        
        @keyframes modal-appear {
            to {
                transform: translateY(0);
            }
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            background: white;
            color: #4b5563;
            border-radius: 12px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            background: #f9fafb;
            transform: translateX(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }
        
        .pay-button {
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .pay-button:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: translateX(-100%);
        }
        
        .pay-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.4);
        }
        
        .pay-button:hover:after {
            animation: shine 1.5s ease-out;
        }
        
        @keyframes shine {
            to {
                transform: translateX(100%);
            }
        }
        
        .summary-card {
            background: linear-gradient(145deg, #ffffff, #f9fafb);
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }
        
        input[type="text"], 
        input[type="tel"], 
        input[type="file"] {
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 14px 18px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02) inset;
        }
        
        input[type="text"]:focus, 
        input[type="tel"]:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
            outline: none;
        }
        
        .submit-button {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            box-shadow: 0 8px 16px rgba(99, 102, 241, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .submit-button:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: translateX(-100%);
        }
        
        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(99, 102, 241, 0.4);
        }
        
        .submit-button:hover:after {
            animation: shine 1.5s ease-out;
        }
        
        .section-heading {
            position: relative;
            display: inline-block;
            margin-bottom: 1.5rem;
        }
        
        .section-heading:after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, #6366f1, #a855f7);
            border-radius: 2px;
        }
        
        .feature-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #f0f4ff;
            color: #6366f1;
            margin-right: 16px;
        }
        
        .detail-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .detail-row:hover {
            transform: translateX(5px);
        }
    </style>
</head>
<body class="min-h-screen py-12 px-4 sm:px-6">
    <div class="container mx-auto max-w-5xl">
        <div class="mb-8">
            <a href="my-bookings.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                <span>Back to My Bookings</span>
            </a>
        </div>

        <div class="payment-container p-8 md:p-10 mb-10">
            <h1 class="text-3xl font-bold mb-3 text-gray-800">Complete Your Payment</h1>
            <p class="text-gray-500 mb-10 text-lg">Secure your booking by completing the payment process</p>
            
            <!-- Booking Summary -->
            <div class="mb-12">
                <h2 class="section-heading text-xl font-semibold flex items-center">
                    <i class="fas fa-receipt text-indigo-500 mr-2"></i>
                    Booking Summary
                </h2>
                <div class="summary-card p-8 mt-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="detail-row">
                            <div class="feature-icon">
                                <i class="fas fa-hashtag"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Booking Reference</p>
                                <p class="font-medium text-lg"><?php echo $booking['booking_reference']; ?></p>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="feature-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Event Date</p>
                                <p class="font-medium text-lg"><?php echo date('F d, Y', strtotime($booking['event_date'])); ?></p>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="feature-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Event Time</p>
                                <p class="font-medium text-lg">
                                    <?php 
                                    $start = date('h:i A', strtotime($booking['event_start_time']));
                                    $end = date('h:i A', strtotime($booking['event_end_time']));
                                    echo $start . ' - ' . $end; 
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="feature-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Package</p>
                                <p class="font-medium text-lg"><?php echo ucwords(strtolower(htmlspecialchars($booking['package_name']))); ?></p>
                            </div>
                        </div>
                        <div class="detail-row md:col-span-2">
                            <div class="feature-icon bg-green-50 text-green-500">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Total Amount</p>
                                <p class="font-semibold text-2xl text-green-600">₱<?php echo number_format($total_amount, 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Methods -->
            <h2 class="section-heading text-xl font-semibold flex items-center">
                <i class="fas fa-credit-card text-indigo-500 mr-2"></i>
                Select Payment Method
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 my-8">
                <!-- GCash -->
                <div class="payment-option p-7 cursor-pointer group" onclick="selectPayment('gcash')">
                    <div class="flex justify-center mb-5 transition-transform duration-300 group-hover:scale-110">
                        <img src="../img/cashg.png" alt="GCash" class="h-16 object-contain">
                    </div>
                    <h3 class="text-center font-medium text-lg mb-2">GCash</h3>
                    <p class="text-center text-gray-500">Fast and secure mobile payment</p>
                </div>

                <!-- PayMaya -->
                <div class="payment-option p-7 cursor-pointer group" onclick="selectPayment('paymaya')">
                    <div class="flex justify-center mb-5 transition-transform duration-300 group-hover:scale-110">
                        <img src="../img/maya.webp" alt="PayMaya" class="h-16 object-contain">
                    </div>
                    <h3 class="text-center font-medium text-lg mb-2">PayMaya</h3>
                    <p class="text-center text-gray-500">Digital wallet for easy payments</p>
                </div>
            </div>

            <!-- Pay Now Button -->
            <div class="text-center mt-12">
                <button onclick="proceedToPayment()" class="pay-button text-white px-12 py-4 rounded-full font-medium text-lg flex items-center justify-center mx-auto">
                    <i class="fas fa-lock mr-2"></i>
                    Complete Payment
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-bold text-gray-800" id="modalTitle">Payment Details</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Payment Option Selection (only for GCash and PayMaya) -->
            <div id="paymentAmountSelection" class="mb-8 hidden">
                <h3 class="font-semibold text-lg mb-5 text-indigo-600 section-heading">Choose Payment Option</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="payment-amount-option p-6 rounded-xl text-center group" onclick="selectPaymentAmount('full')">
                        <h4 class="font-medium text-lg mb-2">Full Payment</h4>
                        <p class="text-green-600 font-semibold text-2xl mb-3">₱<?php echo number_format($total_amount, 2); ?></p>
                        <p class="text-gray-500 text-sm">Pay the entire amount now</p>
                    </div>
                    <div class="payment-amount-option p-6 rounded-xl text-center group" onclick="selectPaymentAmount('deposit')">
                        <h4 class="font-medium text-lg mb-2">50% Deposit</h4>
                        <p class="text-green-600 font-semibold text-2xl mb-3">₱<?php echo number_format($deposit_amount, 2); ?></p>
                        <p class="text-gray-500 text-sm">Pay 50% now, 50% later</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Receiver Details (Left Side) -->
                <div class="bg-gray-50 p-7 rounded-xl">
                    <h3 class="font-semibold text-lg mb-5 text-indigo-600 section-heading">Receiver Details</h3>
                    <div id="gcashDetails" class="payment-details">
                        <div class="detail-row mb-5">
                            <div class="feature-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Account Name</p>
                                <p class="font-medium text-lg">Barn & Backyard Services</p>
                            </div>
                        </div>
                        <div class="detail-row mb-6">
                            <div class="feature-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">GCash Number</p>
                                <p class="font-medium text-lg">09623478901</p>
                            </div>
                        </div>
                        <div class="flex justify-center mt-4">
                            <div class="bg-white p-3 rounded-xl shadow-md">
                                <img src="../img/frame.png" alt="GCash QR Code" class="w-48 rounded-lg">
                            </div>
                        </div>
                    </div>

                    <div id="paymayaDetails" class="payment-details hidden">
                        <div class="detail-row mb-5">
                            <div class="feature-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Account Name</p>
                                <p class="font-medium text-lg">Barn & Backyard Services</p>
                            </div>
                        </div>
                        <div class="detail-row mb-6">
                            <div class="feature-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">PayMaya Number</p>
                                <p class="font-medium text-lg">09623478901</p>
                            </div>
                        </div>
                        <div class="flex justify-center mt-4">
                            <div class="bg-white p-3 rounded-xl shadow-md">
                                <img src="../img/frame2.png" alt="PayMaya QR Code" class="w-48 rounded-lg">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sender Details (Right Side) -->
                <div>
                    <h3 class="font-semibold text-lg mb-5 text-indigo-600 section-heading">Your Payment Details</h3>
                    <form id="paymentForm" action="process-payment.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                        <input type="hidden" name="payment_method" id="paymentMethod">
                        <input type="hidden" name="payment_type" id="paymentType" value="full">
                        <input type="hidden" name="total_amount" id="totalAmount" value="<?php echo $total_amount; ?>">
                        <input type="hidden" name="deposit_amount" value="<?php echo $deposit_amount; ?>">

                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-medium mb-2">Your Name</label>
                            <input type="text" name="account_name" class="w-full" required>
                        </div>

                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-medium mb-2">Your Phone Number</label>
                            <input type="tel" name="phone_number" class="w-full" required>
                        </div>

                        <div class="mb-8">
                            <label class="block text-gray-700 text-sm font-medium mb-2">Payment Receipt</label>
                            <div class="border border-dashed border-gray-300 rounded-xl p-4 bg-gray-50">
                                <input type="file" name="receipt" accept="image/*" class="w-full" required>
                                <p class="text-sm text-gray-500 mt-2">Upload a screenshot or photo of your payment receipt</p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="submit-button text-white px-8 py-3 rounded-lg text-lg">
                                <i class="fas fa-paper-plane mr-2"></i> Submit Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedPaymentAmount = 'full'; // Default to full payment
        
        function selectPayment(method) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });

            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
        }
        
        function selectPaymentAmount(type) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-amount-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Set the payment type and update form
            selectedPaymentAmount = type;
            document.getElementById('paymentType').value = type;
            
            // Update the amount display in the form
            if (type === 'deposit') {
                const depositAmount = <?php echo $deposit_amount; ?>;
                document.getElementById('totalAmount').value = depositAmount;
            } else {
                const fullAmount = <?php echo $total_amount; ?>;
                document.getElementById('totalAmount').value = fullAmount;
            }
        }

        function proceedToPayment() {
            const selectedPayment = document.querySelector('.payment-option.selected');
            if (!selectedPayment) {
                alert('Please select a payment method');
                return;
            }

            // Get the payment method
            const paymentMethod = selectedPayment.querySelector('h3').textContent.toLowerCase();
            
            // Show the payment modal
            showPaymentModal(paymentMethod);
        }

        function showPaymentModal(method) {
            // Show modal with appropriate title
            const modal = document.getElementById('paymentModal');
            const modalTitle = document.getElementById('modalTitle');
            const paymentMethod = document.getElementById('paymentMethod');
            const gcashDetails = document.getElementById('gcashDetails');
            const paymayaDetails = document.getElementById('paymayaDetails');
            const paymentAmountSelection = document.getElementById('paymentAmountSelection');

            switch(method) {
                case 'gcash':
                    modalTitle.textContent = 'GCash Payment Details';
                    gcashDetails.classList.remove('hidden');
                    paymayaDetails.classList.add('hidden');
                    paymentAmountSelection.classList.remove('hidden');
                    break;
                case 'paymaya':
                    modalTitle.textContent = 'PayMaya Payment Details';
                    gcashDetails.classList.add('hidden');
                    paymayaDetails.classList.remove('hidden');
                    paymentAmountSelection.classList.remove('hidden');
                    break;
                default:
                    paymentAmountSelection.classList.add('hidden');
            }

            paymentMethod.value = method;
            
            // Select the full payment option by default
            selectPaymentAmount('full');
            document.querySelector('.payment-amount-option').classList.add('selected');
            
            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Show loading state
            Swal.fire({
                title: 'Processing Payment',
                text: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('process-payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Payment Successful!',
                        text: 'Your payment has been processed successfully.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        window.location.href = 'my-bookings.php';
                    });
                } else {
                    throw new Error(data.message || 'Failed to process payment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'An error occurred while processing the payment',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        });
    </script>

    <!-- Add SweetAlert2 for better alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html></html>
