<?php
session_start();

if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Receptionist') {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';

$receptionist_name = $_SESSION['employee_name'] ?? 'Receptionist';
$employeeInitial = strtoupper(substr($receptionist_name, 0, 1));

$paymentMethods = ['Cash', 'GCash', 'PayMaya'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payment - Empire Fitness</title>
    <link rel="stylesheet" href="css/receptionist-dashboard.css">
    <link rel="stylesheet" href="css/entry-exit.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="../css/realtime-notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .payment-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            animation: fadeIn 0.3s ease-in-out;
            align-items: center;
            justify-content: center;
        }
        .payment-modal-overlay.active {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .payment-modal {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            animation: slideUp 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .payment-modal-header {
            padding: 25px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .payment-modal-header h2 {
            margin: 0;
            font-size: 20px;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .payment-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }
        .payment-modal-close:hover {
            color: #333;
        }
        .payment-modal-body {
            padding: 25px;
        }
        .payment-form-group {
            margin-bottom: 20px;
        }
        .payment-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        .payment-form-group label.required::after {
            content: " *";
            color: #e74c3c;
        }
        .payment-form-group input,
        .payment-form-group select,
        .payment-form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .payment-form-group input:focus,
        .payment-form-group select:focus,
        .payment-form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        .payment-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .payment-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .payment-summary-row:last-child {
            margin-bottom: 0;
            border-top: 2px solid #ddd;
            padding-top: 10px;
            font-weight: 600;
            font-size: 16px;
            color: #2c3e50;
        }
        .payment-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .payment-modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .btn-payment {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-payment-cancel {
            background: #e0e0e0;
            color: #333;
        }
        .btn-payment-cancel:hover {
            background: #d0d0d0;
        }
        .btn-payment-submit {
            background: #27ae60;
            color: white;
        }
        .btn-payment-submit:hover {
            background: #229954;
        }
        .error-message {
            background: #fadbd8;
            color: #c0392b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 4px solid #c0392b;
            display: none;
        }
    </style>
</head>
<body data-user-id="<?php echo htmlspecialchars($_SESSION['employee_id']); ?>"
      data-user-role="<?php echo htmlspecialchars($_SESSION['employee_role']); ?>"
      data-user-name="<?php echo htmlspecialchars($_SESSION['employee_name']); ?>">
    <!-- Notifications Container -->
    <div id="notifications"></div>
    <button class="sidebar-toggle" id="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-circle"><i class="fas fa-dumbbell"></i></div>
                <div class="logo-text">
                    <h2>EMPIRE FITNESS</h2>
                    <p>Reception Desk</p>
                </div>
            </div>
        </div>

        <div class="profile-section" onclick="window.location.href='profile.php'" style="cursor: pointer;">
            <div class="profile-avatar"><?php echo $employeeInitial; ?></div>
            <div class="profile-info">
                <div class="profile-name"><?php echo htmlspecialchars($receptionist_name); ?></div>
                <div class="profile-role">Receptionist</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="receptionistDashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'receptionistDashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <div class="nav-divider">OPERATIONS</div>
            <a href="combined_pos_entry.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'combined_pos_entry.php' ? 'active' : ''; ?>"><i class="fas fa-sync-alt"></i><span>POS + Entry/Exit</span></a>
            <a href="manage_entry_exit.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_entry_exit.php' ? 'active' : ''; ?>"><i class="fas fa-door-open"></i><span>Entry/Exit Only</span></a>
            <a href="pos.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'active' : ''; ?>"><i class="fas fa-cash-register"></i><span>POS Only</span></a>
            <div class="nav-divider">MEMBER MANAGEMENT</div>
            <a href="members_list.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'members_list.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i><span>Members List</span></a>
            <div class="nav-divider">PAYMENTS</div>
            <a href="manage_payments.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_payments.php' ? 'active' : ''; ?>"><i class="fas fa-credit-card"></i><span>Process Payments</span></a>
            <a href="payment_history.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'payment_history.php' ? 'active' : ''; ?>"><i class="fas fa-history"></i><span>Payment History</span></a>
            <div class="nav-divider">SCHEDULING</div>
            <a href="schedule_classes.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'schedule_classes.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i><span>Class Schedule</span></a>
            <div class="nav-divider">REPORTS</div>
            <a href="daily_report.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'daily_report.php' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i><span>Daily Report</span></a>
            <div class="nav-divider">ACCOUNT</div>
            <a href="profile.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i><span>My Profile</span></a>
            <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i><span>Settings</span></a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-item logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content" id="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-cash-register"></i> Process Payment</h1>
                <p class="page-subtitle">Handle payments for gym entrance and services</p>
            </div>
            <div class="current-time" id="current-time">00:00:00 AM</div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> New Payment</h3>
            </div>
            <div class="card-body">
                <form id="quick-payment-form" onsubmit="event.preventDefault(); openPaymentModal();">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label for="payment-client-type" class="required">Client Type</label>
                            <select id="payment-client-type" class="form-input" onchange="handleClientTypeChange()" required>
                                <option value="">-- Select Type --</option>
                                <option value="member">Member (by ID)</option>
                                <option value="walkin">Walk-in Guest</option>
                            </select>
                        </div>
                        <div id="client-id-field" style="display: none;">
                            <label for="payment-client-id">Member ID</label>
                            <input type="text" id="payment-client-id" class="form-input" placeholder="Enter member ID">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label for="payment-type" class="required">Payment Type</label>
                            <select id="payment-type" class="form-input" required>
                                <option value="">-- Select Type --</option>
                                <option value="daily_entrance">Daily Entrance</option>
                                <option value="membership">Membership</option>
                                <option value="class">Class/Package</option>
                                <option value="pt_session">PT Session</option>
                                <option value="other">Other Service</option>
                            </select>
                        </div>
                        <div>
                            <label for="payment-amount" class="required">Amount (PHP)</label>
                            <input type="number" id="payment-amount" class="form-input" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                        <i class="fas fa-arrow-right"></i> Proceed to Payment
                    </button>
                </form>
            </div>
        </div>
    </main>

    <div class="payment-modal-overlay" id="payment-modal-overlay">
        <div class="payment-modal">
            <div class="payment-modal-header">
                <h2><i class="fas fa-credit-card"></i> Payment Details</h2>
                <button class="payment-modal-close" onclick="closePaymentModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="payment-modal-body">
                <div class="error-message" id="payment-error"></div>
                <form id="payment-form">
                    <div class="payment-card">
                        <div style="font-weight: 600; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                            <i class="fas fa-user"></i> Client Information
                        </div>
                        <div class="payment-form-group">
                            <label for="modal-client-name" class="required">Client Name</label>
                            <input type="text" id="modal-client-name" class="form-input" required>
                        </div>
                        <div class="payment-form-group">
                            <label for="modal-client-contact">Contact Number</label>
                            <input type="tel" id="modal-client-contact" class="form-input" placeholder="Optional">
                        </div>
                    </div>

                    <div class="payment-card">
                        <div style="font-weight: 600; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                            <i class="fas fa-money-bill"></i> Amount
                        </div>
                        <div class="payment-summary">
                            <div class="payment-summary-row">
                                <span>Amount Due:</span>
                                <span style="font-weight: 500;">PHP <span id="modal-total">0.00</span></span>
                            </div>
                        </div>
                    </div>

                    <div class="payment-card">
                        <div style="font-weight: 600; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                            <i class="fas fa-credit-card"></i> Payment Method
                        </div>
                        <div class="payment-form-group">
                            <label for="modal-payment-method" class="required">Method</label>
                            <select id="modal-payment-method" class="form-input" onchange="handlePaymentMethodChange()" required>
                                <option value="">-- Select Payment Method --</option>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?php echo htmlspecialchars($method); ?>"><?php echo htmlspecialchars($method); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="payment-form-group" id="reference-number-field" style="display: none;">
                            <label for="modal-reference-number">Reference Number</label>
                            <input type="text" id="modal-reference-number" class="form-input" placeholder="e.g., GCash ref">
                            <small style="color: #666; margin-top: 5px; display: block;">For non-cash payments, enter the transaction reference</small>
                        </div>
                        <div class="payment-form-group" id="received-amount-field" style="display: none;">
                            <label for="modal-received-amount">Amount Received</label>
                            <input type="number" id="modal-received-amount" class="form-input" step="0.01" placeholder="0.00" onchange="calculateChange()">
                            <small style="color: #666; margin-top: 5px; display: block;">Change: PHP <span id="modal-change">0.00</span></small>
                        </div>
                    </div>

                    <div class="payment-form-group">
                        <label for="modal-payment-notes">Notes (Optional)</label>
                        <textarea id="modal-payment-notes" class="form-input" placeholder="Add any notes" rows="3"></textarea>
                    </div>

                    <input type="hidden" id="modal-payment-amount">
                    <input type="hidden" id="modal-client-id">
                    <input type="hidden" id="modal-payment-type">
                </form>
            </div>
            <div class="payment-modal-footer">
                <button class="btn-payment btn-payment-cancel" onclick="closePaymentModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-payment btn-payment-submit" onclick="submitPayment()">
                    <i class="fas fa-check"></i> Complete Payment
                </button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast">
        <div class="toast-content">
            <i class="toast-icon"></i>
            <span class="toast-message"></span>
        </div>
    </div>

    <script src="js/receptionist-dashboard.js"></script>
    <script>
        function openPaymentModal() {
            const clientType = document.getElementById('payment-client-type').value;
            const paymentType = document.getElementById('payment-type').value;
            const amount = parseFloat(document.getElementById('payment-amount').value);

            if (!clientType || !paymentType || !amount) {
                showToast('Please fill in all required fields', 'error');
                return;
            }

            if (clientType === 'member') {
                const clientId = document.getElementById('payment-client-id').value;
                if (!clientId) {
                    showToast('Please enter a member ID', 'error');
                    return;
                }
                document.getElementById('modal-client-id').value = clientId;
                document.getElementById('modal-client-name').value = 'Member ' + clientId;
            } else {
                document.getElementById('modal-client-id').value = '';
                document.getElementById('modal-client-name').value = '';
            }

            document.getElementById('modal-payment-type').value = paymentType;
            document.getElementById('modal-payment-amount').value = amount;
            document.getElementById('modal-total').textContent = amount.toFixed(2);

            document.getElementById('payment-modal-overlay').classList.add('active');
        }

        function closePaymentModal() {
            document.getElementById('payment-modal-overlay').classList.remove('active');
            document.getElementById('payment-form').reset();
            document.getElementById('payment-error').style.display = 'none';
        }

        function handleClientTypeChange() {
            const clientType = document.getElementById('payment-client-type').value;
            document.getElementById('client-id-field').style.display = clientType === 'member' ? 'block' : 'none';
        }

        function handlePaymentMethodChange() {
            const method = document.getElementById('modal-payment-method').value;
            const refField = document.getElementById('reference-number-field');
            const amtField = document.getElementById('received-amount-field');

            if (method === 'Cash') {
                refField.style.display = 'none';
                amtField.style.display = 'block';
            } else if (method) {
                refField.style.display = 'block';
                amtField.style.display = 'none';
            } else {
                refField.style.display = 'none';
                amtField.style.display = 'none';
            }
        }

        function calculateChange() {
            const total = parseFloat(document.getElementById('modal-total').textContent) || 0;
            const received = parseFloat(document.getElementById('modal-received-amount').value) || 0;
            document.getElementById('modal-change').textContent = (received - total >= 0 ? received - total : 0).toFixed(2);
        }

        function submitPayment() {
            const errorDiv = document.getElementById('payment-error');
            const method = document.getElementById('modal-payment-method').value;
            const total = parseFloat(document.getElementById('modal-total').textContent);

            if (!method) {
                errorDiv.textContent = 'Please select a payment method';
                errorDiv.style.display = 'block';
                return;
            }

            if (method === 'Cash') {
                const received = parseFloat(document.getElementById('modal-received-amount').value);
                if (!received || received < total) {
                    errorDiv.textContent = 'Amount received must be at least PHP ' + total.toFixed(2);
                    errorDiv.style.display = 'block';
                    return;
                }
            } else {
                const reference = document.getElementById('modal-reference-number').value;
                if (!reference) {
                    errorDiv.textContent = 'Reference number required for ' + method;
                    errorDiv.style.display = 'block';
                    return;
                }
            }

            const paymentData = {
                client_id: document.getElementById('modal-client-id').value || null,
                client_name: document.getElementById('modal-client-name').value,
                payment_type: document.getElementById('modal-payment-type').value,
                amount: parseFloat(document.getElementById('modal-payment-amount').value),
                payment_method: method,
                reference_number: document.getElementById('modal-reference-number').value || null,
                received_amount: document.getElementById('modal-received-amount').value || null,
                notes: document.getElementById('modal-payment-notes').value
            };

            fetch('includes/process_payment_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(paymentData)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Payment processed! Receipt #' + data.data.receipt_number, 'success');
                    setTimeout(() => {
                        closePaymentModal();
                        document.getElementById('quick-payment-form').reset();
                    }, 1500);
                } else {
                    errorDiv.textContent = data.message || 'Error processing payment';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(e => {
                errorDiv.textContent = 'Network error: ' + e.message;
                errorDiv.style.display = 'block';
            });
        }

        function showToast(msg, type = 'info') {
            const toast = document.getElementById('toast');
            toast.querySelector('.toast-message').textContent = msg;
            toast.className = 'toast toast-' + type;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        document.getElementById('current-time').textContent = new Date().toLocaleTimeString('en-US');
        setInterval(() => {
            document.getElementById('current-time').textContent = new Date().toLocaleTimeString('en-US');
        }, 1000);
    </script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>
