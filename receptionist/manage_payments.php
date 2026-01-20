<?php
session_start();

// Check if user is logged in and has receptionist role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Receptionist') {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

$employeeName = $_SESSION['employee_name'] ?? 'Receptionist';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Empire Fitness</title>
    <link rel="stylesheet" href="css/receptionist-dashboard.css">
    <link rel="stylesheet" href="css/manage-payments.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="../css/realtime-notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal-footer {
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body data-user-id="<?php echo htmlspecialchars($_SESSION['employee_id']); ?>"
      data-user-role="<?php echo htmlspecialchars($_SESSION['employee_role']); ?>"
      data-user-name="<?php echo htmlspecialchars($_SESSION['employee_name']); ?>">
    <!-- Notifications Container -->
    <div id="notifications"></div>

    <!-- Sidebar Toggle Button -->
    <button id="sidebar-toggle" class="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-circle">
                    <i class="fas fa-dumbbell"></i>
                </div>
                <div class="logo-text">
                    <h2>EMPIRE FITNESS</h2>
                    <p>Reception Desk</p>
                </div>
            </div>
        </div>

        <div class="profile-section" onclick="window.location.href='profile.php'" style="cursor: pointer;">
            <div class="profile-avatar"><?php echo $employeeInitial; ?></div>
            <div class="profile-info">
                <div class="profile-name"><?php echo htmlspecialchars($employeeName); ?></div>
                <div class="profile-role"><?php echo htmlspecialchars($_SESSION['employee_role']); ?></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="receptionistDashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-divider">OPERATIONS</div>
            
            <a href="pos.php" class="nav-item">
                <i class="fas fa-cash-register"></i>
                <span>Point of Sale</span>
            </a>
            
            <div class="nav-divider">MEMBERS</div>
            
            <a href="members_list.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Members</span>
            </a>
            
            <div class="nav-divider">PAYMENTS</div>
            
            <a href="payment_history.php" class="nav-item">
                <i class="fas fa-history"></i>
                <span>Payment History</span>
            </a>
            
            <div class="nav-divider">SCHEDULE & REPORTS</div>
            
            <a href="schedule_classes.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Class Schedule</span>
            </a>
            <a href="daily_report.php" class="nav-item">
                <i class="fas fa-chart-bar"></i>
                <span>Daily Report</span>
            </a>
            
            <div class="nav-divider">ACCOUNT</div>
            
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            <a href="settings.php" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <h1>Manage Payments</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Payments / Process
                </p>
            </div>
            <div class="topbar-right">
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                </button>
                <div class="user-profile">
                    <div class="user-avatar"><?php echo $employeeInitial; ?></div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($employeeName); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars($_SESSION['employee_role']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-wrapper">
            <!-- Stats Cards -->
            <div class="payment-stats">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Pending Payments</h3>
                        <p class="stat-number"><span id="pending-count">0</span></p>
                        <span class="stat-change negative">
                            <i class="fas fa-exclamation-circle"></i> Awaiting
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Today's Collections</h3>
                        <p class="stat-number">₱<span id="today-total">0.00</span></p>
                        <span class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Collected
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Pending Renewals</h3>
                        <p class="stat-number"><span id="renewals-count">0</span></p>
                        <span class="stat-change neutral">
                            <i class="fas fa-bell"></i> Upcoming
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tabs Section -->
            <div class="payments-section">
                <div class="section-tabs">
                    <button class="tab-btn active" data-tab="walkin-guests">
                        <i class="fas fa-user-tie"></i> Walk-in Guests
                    </button>
                    <button class="tab-btn" data-tab="walkin-members">
                        <i class="fas fa-users"></i> Walk-in Members
                    </button>
                    <button class="tab-btn" data-tab="class-payments">
                        <i class="fas fa-dumbbell"></i> Class Payments
                    </button>
                    <button class="tab-btn" data-tab="otc-class-bookings">
                        <i class="fas fa-credit-card"></i> OTC Class Bookings
                    </button>
                    <button class="tab-btn" data-tab="membership-renewal">
                        <i class="fas fa-id-card"></i> Membership Renewal
                    </button>
                </div>

                <!-- Walk-in Guests Tab -->
                <div class="tab-content active" id="walkin-guests-content">
                    <div class="tab-header">
                        <div class="tab-title">
                            <h3>Walk-in Guest Payments</h3>
                            <p>Recent payments from today's walk-in guests</p>
                        </div>
                        <div class="tab-actions">
                            <input type="text" id="search-walkin" class="search-input" placeholder="Search by name...">
                        </div>
                    </div>

                    <div class="payments-table">
                        <table id="walkin-table">
                            <thead>
                                <tr>
                                    <th>Guest Name</th>
                                    <th>Time In</th>
                                    <th>Duration</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Handled By</th>
                                </tr>
                            </thead>
                            <tbody id="walkin-tbody">
                                <tr class="empty-row">
                                    <td colspan="7" class="text-center">
                                        <i class="fas fa-inbox"></i>
                                        <p>No walk-in guest payments today</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Walk-in Members Tab -->
                <div class="tab-content" id="walkin-members-content">
                    <div class="tab-header">
                        <div class="tab-title">
                            <h3>Walk-in Member Payments</h3>
                            <p>Discounted payments from members visiting as walk-in today</p>
                        </div>
                        <div class="tab-actions">
                            <input type="text" id="search-walkin-members" class="search-input" placeholder="Search by member...">
                        </div>
                    </div>

                    <div class="payments-table">
                        <table id="walkin-members-table">
                            <thead>
                                <tr>
                                    <th>Member Name</th>
                                    <th>Membership Plan</th>
                                    <th>Time In</th>
                                    <th>Discount Rate</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Handled By</th>
                                </tr>
                            </thead>
                            <tbody id="walkin-members-tbody">
                                <tr class="empty-row">
                                    <td colspan="7" class="text-center">
                                        <i class="fas fa-inbox"></i>
                                        <p>No walk-in member payments today</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Class Payments Tab -->
                <div class="tab-content" id="class-payments-content">
                    <div class="tab-header">
                        <div class="tab-title">
                            <h3>Class Session Payments</h3>
                            <p>Collect payments for class bookings paid at counter</p>
                        </div>
                        <div class="tab-actions">
                            <input type="text" id="search-class" class="search-input" placeholder="Search by class...">
                        </div>
                    </div>

                    <div class="payments-table">
                        <table id="class-table">
                            <thead>
                                <tr>
                                    <th>Member Name</th>
                                    <th>Class Name</th>
                                    <th>Schedule</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Process</th>
                                </tr>
                            </thead>
                            <tbody id="class-tbody">
                                <tr class="empty-row">
                                    <td colspan="6" class="text-center">
                                        <i class="fas fa-inbox"></i>
                                        <p>No pending class payments</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Membership Renewal Tab -->
                <div class="tab-content" id="membership-renewal-content">
                    <div class="tab-header">
                        <div class="tab-title">
                            <h3>Membership Renewals</h3>
                            <p>Process membership renewal payments</p>
                        </div>
                        <div class="tab-actions">
                            <input type="text" id="search-renewal" class="search-input" placeholder="Search by member...">
                        </div>
                    </div>

                    <div class="payments-table">
                        <table id="renewal-table">
                            <thead>
                                <tr>
                                    <th>Member Name</th>
                                    <th>Current Plan</th>
                                    <th>Expiry Date</th>
                                    <th>Estimated Renewal</th>
                                    <th>Status</th>
                                    <th>Process</th>
                                </tr>
                            </thead>
                            <tbody id="renewal-tbody">
                                <tr class="empty-row">
                                    <td colspan="6" class="text-center">
                                        <i class="fas fa-inbox"></i>
                                        <p>No pending renewals</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- OTC Class Bookings Tab -->
                <div class="tab-content" id="otc-class-bookings-content">
                    <div class="tab-header">
                        <div class="tab-title">
                            <h3>OTC Class Bookings</h3>
                            <p>Approve or reject over-the-counter class booking payments</p>
                        </div>
                        <div class="tab-actions">
                            <input type="text" id="search-otc-bookings" class="search-input" placeholder="Search by member...">
                        </div>
                    </div>

                    <div class="payments-table">
                        <table id="otc-bookings-table">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Member Name</th>
                                    <th>Class</th>
                                    <th>Scheduled Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="otc-bookings-tbody">
                                <tr class="empty-row">
                                    <td colspan="7" class="text-center">
                                        <i class="fas fa-inbox"></i>
                                        <p>No OTC class bookings pending</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal" id="payment-modal">
        <div class="modal-dialog" style="max-width: 500px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Approve Guest Payment</h2>
                    <button class="modal-close" onclick="closePaymentModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <!-- Guest Details -->
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label style="font-size: 12px; color: #666; font-weight: 600;">GUEST NAME</label>
                                <p style="margin: 5px 0 0 0; font-size: 15px;" id="modal-payee">-</p>
                            </div>
                            <div>
                                <label style="font-size: 12px; color: #666; font-weight: 600;">TIME IN</label>
                                <p style="margin: 5px 0 0 0; font-size: 15px;" id="modal-time-in">-</p>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label style="font-size: 12px; color: #666; font-weight: 600;">RATE TYPE</label>
                                <p style="margin: 5px 0 0 0; font-size: 15px;" id="modal-rate-type">-</p>
                            </div>
                            <div>
                                <label style="font-size: 12px; color: #666; font-weight: 600;">HANDLED BY</label>
                                <p style="margin: 5px 0 0 0; font-size: 15px;" id="modal-receptionist">-</p>
                            </div>
                        </div>
                    </div>

                    <!-- Amount Due -->
                    <div style="text-align: center; background: #f0f8ff; padding: 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #667eea;">
                        <small style="color: #666; font-weight: 600;">AMOUNT DUE</small>
                        <h3 style="margin: 8px 0 0 0; font-size: 32px; color: #27ae60;" id="modal-amount">₱0.00</h3>
                    </div>

                    <!-- Payment Form -->
                    <form id="payment-form">
                        <input type="hidden" id="payment-type" value="">
                        <input type="hidden" id="payment-reference-id" value="">

                        <div class="form-group">
                            <label for="payment-method" class="required">Payment Method</label>
                            <select id="payment-method" required>
                                <option value="">Select payment method</option>
                                <option value="Cash">Cash</option>
                                <option value="GCash">GCash</option>
                                <option value="PayMaya">PayMaya</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="payment-remarks">Remarks (Optional)</label>
                            <textarea id="payment-remarks" rows="2" placeholder="Add any notes about this payment..."></textarea>
                        </div>

                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" id="payment-confirm" required>
                                <span>I confirm this payment has been received</span>
                            </label>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                    <button class="btn btn-primary" id="submit-payment-btn" onclick="submitPayment()">
                        <i class="fas fa-check"></i> Approve Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <div class="toast-content">
            <i class="toast-icon fas fa-check-circle"></i>
            <span class="toast-message" id="toast-message">Payment processed successfully</span>
        </div>
    </div>

    <script src="js/receptionist-dashboard.js"></script>
    <script src="js/manage-payments.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>