<?php
session_start();

if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Receptionist') {
    header('Location: ../index.php');
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

$employeeName    = $_SESSION['employee_name'] ?? 'Receptionist';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Empire Fitness</title>

    <!-- Shared CSS -->
    <link rel="stylesheet" href="css/receptionist-dashboard.css">
    <!-- Page CSS -->
    <link rel="stylesheet" href="css/payment-history.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="../css/realtime-notifications.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-user-id="<?php echo htmlspecialchars($_SESSION['employee_id']); ?>"
      data-user-role="<?php echo htmlspecialchars($_SESSION['employee_role']); ?>"
      data-user-name="<?php echo htmlspecialchars($_SESSION['employee_name']); ?>">
    <!-- Notifications Container -->
    <div id="notifications"></div>
<button id="sidebar-toggle" class="sidebar-toggle">
    <i class="fas fa-bars"></i>
</button>

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
        <div class="profile-section" onclick="window.location.href='profile.php'" style="cursor:pointer;">
            <div class="profile-avatar"><?php echo $employeeInitial; ?></div>
            <div class="profile-info">
                <div class="profile-name"><?php echo htmlspecialchars($employeeName); ?></div>
                <div class="profile-role">
                    <?php echo htmlspecialchars($_SESSION['employee_role']); ?>
                </div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="receptionistDashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'receptionistDashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i><span>Dashboard</span>
        </a>

        <div class="nav-divider">OPERATIONS</div>
        <a href="pos.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'active' : ''; ?>">
            <i class="fas fa-cash-register"></i><span>Point of Sale</span>
        </a>

        <div class="nav-divider">MEMBERS</div>
        <a href="members_list.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'members_list.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i><span>Members</span>
        </a>

        <div class="nav-divider">PAYMENTS</div>
        <a href="payment_history.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'payment_history.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i><span>Payment History</span>
        </a>

        <div class="nav-divider">SCHEDULE & REPORTS</div>
        <a href="schedule_classes.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'schedule_classes.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i><span>Class Schedule</span>
        </a>
        <a href="daily_report.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'daily_report.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i><span>Daily Report</span>
        </a>

        <div class="nav-divider">ACCOUNT</div>
        <a href="profile.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i><span>My Profile</span>
        </a>
        <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i><span>Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php" class="nav-item logout">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </a>
    </div>
</div>

<div class="main-content" id="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <h1>Payment History</h1>
            <p class="breadcrumb">
                <i class="fas fa-home"></i> Home / Payments / History
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

    <div class="page-wrapper">
        <!-- Stats -->
        <div class="payment-stats">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Collected</h3>
                    <p class="stat-number">₱<span id="total-collected">0.00</span></p>
                    <span class="stat-change neutral">All-time payments</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3>Today’s Payments</h3>
                    <p class="stat-number">₱<span id="today-collected">0.00</span></p>
                    <span class="stat-change positive">
                        <span id="today-count">0</span> transactions
                    </span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-filter"></i>
                </div>
                <div class="stat-content">
                    <h3>Filtered Results</h3>
                    <p class="stat-number"><span id="filter-count">0</span> records</p>
                    <span class="stat-change neutral">Matching current filters</span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e9ecef;">
            <button class="tab-btn" id="tab-all-payments" onclick="switchPaymentTab('all')" style="padding: 12px 20px; border: none; background: none; cursor: pointer; color: #2c3e50; font-weight: 600; border-bottom: 3px solid #d41c1c;">
                <i class="fas fa-history"></i> All Payments
            </button>
            <button class="tab-btn" id="tab-pos-transactions" onclick="switchPaymentTab('pos')" style="padding: 12px 20px; border: none; background: none; cursor: pointer; color: #6c757d; font-weight: 600; border-bottom: 3px solid transparent;">
                <i class="fas fa-cash-register"></i> POS Transactions
            </button>
        </div>

        <!-- Filters -->

        <!-- Filters -->
        <div class="history-filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="filter-date-from">Date From</label>
                    <input type="date" id="filter-date-from">
                </div>
                <div class="filter-group">
                    <label for="filter-date-to">Date To</label>
                    <input type="date" id="filter-date-to">
                </div>
                <div class="filter-group">
                    <label for="filter-type">Payment Type</label>
                    <select id="filter-type">
                        <option value="">All</option>
                        <option value="Membership">Membership (New/Renewal)</option>
                        <option value="Monthly">Monthly Fee</option>
                        <option value="Daily">Daily (Walk-in/Guest)</option>
                        <option value="Service">Service (PT Session)</option>
                        <option value="Class">Class</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-method">Method</label>
                    <select id="filter-method">
                        <option value="">All</option>
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                        <option value="Bank Transfer - BDO">Bank Transfer - BDO</option>
                        <option value="Bank Transfer - BPI">Bank Transfer - BPI</option>
                        <option value="Bank Transfer - Other">Bank Transfer - Other</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Debit Card">Debit Card</option>
                        <option value="Over the Counter">Over the Counter</option>
                        <option value="Mobile Payment">Mobile Payment</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-status">Status</label>
                    <select id="filter-status">
                        <option value="">All</option>
                        <option value="Paid">Paid</option>
                        <option value="Pending">Pending</option>
                        <option value="Refunded">Refunded</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="filter-row">
                <div class="filter-group search-group">
                    <label for="filter-search">Search</label>
                    <input type="text" id="filter-search"
                           placeholder="Client name, reference, item...">
                </div>
                <div class="filter-actions">
                    <button class="btn btn-secondary" id="reset-filters-btn">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button class="btn btn-primary" id="apply-filters-btn">
                        <i class="fas fa-search"></i> Apply
                    </button>
                </div>
            </div>
        </div>
        <!-- Table -->
        <div class="payments-table history-table" id="all-payments-table">
            <table id="history-table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th>Proof</th>
                </tr>
                </thead>
                <tbody id="history-tbody">
                <tr class="empty-row">
                    <td colspan="9" class="text-center">
                        <i class="fas fa-inbox"></i>
                        <p>No payment records found</p>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- POS Transactions Table -->
        <div class="payments-table history-table" id="pos-transactions-table" style="display: none;">
            <table id="pos-table">
                <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Receipt #</th>
                    <th>Type</th>
                    <th>Client</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Receptionist</th>
                    <th>Session</th>
                </tr>
                </thead>
                <tbody id="pos-tbody">
                <tr class="empty-row">
                    <td colspan="9" class="text-center">
                        <i class="fas fa-inbox"></i>
                        <p>No POS transactions found</p>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast">
    <div class="toast-content">
        <i class="toast-icon fas fa-info-circle"></i>
        <span class="toast-message" id="toast-message">Message</span>
    </div>
</div>

<script src="js/receptionist-dashboard.js"></script>
<script src="js/payment-history.js"></script>
<script src="../js/realtime-notifications.js"></script>
</body>
</html>
