<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Super Admin', 'Admin', 'Manager'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

// Get filter parameters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$paymentType = isset($_GET['payment_type']) ? $_GET['payment_type'] : 'all';
$paymentStatus = isset($_GET['payment_status']) ? $_GET['payment_status'] : 'all';
$paymentMethod = isset($_GET['payment_method']) ? $_GET['payment_method'] : 'all';

try {
    // Build query with filters
    $sql = "SELECT p.*, 
            CONCAT(c.first_name, ' ', c.last_name) as client_name,
            c.email as client_email,
            CONCAT(e.first_name, ' ', e.last_name) as created_by_name
            FROM unified_payments p
            LEFT JOIN clients c ON p.client_id = c.client_id
            LEFT JOIN employees e ON p.created_by = e.employee_id
            WHERE p.payment_date BETWEEN :date_from AND :date_to";
    
    $params = [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ];
    
    if ($paymentType !== 'all') {
        $sql .= " AND p.payment_type = :payment_type";
        $params['payment_type'] = $paymentType;
    }
    
    if ($paymentStatus !== 'all') {
        $sql .= " AND p.payment_status = :payment_status";
        $params['payment_status'] = $paymentStatus;
    }
    
    if ($paymentMethod !== 'all') {
        $sql .= " AND p.payment_method = :payment_method";
        $params['payment_method'] = $paymentMethod;
    }
    
    $sql .= " ORDER BY p.payment_date DESC, p.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $totalRevenue = array_sum(array_column(array_filter($payments, fn($p) => $p['payment_status'] === 'Paid'), 'amount'));
    $totalTransactions = count($payments);
    $paidTransactions = count(array_filter($payments, fn($p) => $p['payment_status'] === 'Paid'));
    $pendingTransactions = count(array_filter($payments, fn($p) => $p['payment_status'] === 'Pending'));
    
    // Revenue by payment type
    $revenueByType = [];
    foreach ($payments as $payment) {
        if ($payment['payment_status'] === 'Paid') {
            if (!isset($revenueByType[$payment['payment_type']])) {
                $revenueByType[$payment['payment_type']] = 0;
            }
            $revenueByType[$payment['payment_type']] += $payment['amount'];
        }
    }
    
    // Revenue by payment method
    $revenueByMethod = [];
    foreach ($payments as $payment) {
        if ($payment['payment_status'] === 'Paid') {
            $method = $payment['payment_method'] ?? 'Cash';
            if (!isset($revenueByMethod[$method])) {
                $revenueByMethod[$method] = 0;
            }
            $revenueByMethod[$method] += $payment['amount'];
        }
    }
    
    // Daily revenue for chart
    $dailyRevenue = [];
    foreach ($payments as $payment) {
        if ($payment['payment_status'] === 'Paid') {
            $date = $payment['payment_date'];
            if (!isset($dailyRevenue[$date])) {
                $dailyRevenue[$date] = 0;
            }
            $dailyRevenue[$date] += $payment['amount'];
        }
    }
    ksort($dailyRevenue);
    
} catch (Exception $e) {
    $payments = [];
    $totalRevenue = 0;
    $totalTransactions = 0;
    $paidTransactions = 0;
    $pendingTransactions = 0;
    $revenueByType = [];
    $revenueByMethod = [];
    $dailyRevenue = [];
}

$employeeName = $_SESSION['employee_name'] ?? 'Admin';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Empire Fitness</title>
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/employee-management.css">
    <link rel="stylesheet" href="css/sales.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="../css/realtime-notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <p>Admin Portal</p>
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
            <a href="adminDashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'adminDashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>Dashboard</span>
    </a>
    <a href="employee_management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'employee_management.php' ? 'active' : ''; ?>">
        <i class="fas fa-users-cog"></i>
        <span>Employee Management</span>
    </a>
    
    <div class="nav-divider">RATES & PRICING</div>
    
    <a href="membership_plans.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'membership_plans.php' ? 'active' : ''; ?>">
        <i class="fas fa-crown"></i>
        <span>Membership Plans</span>
    </a>
    <a href="rates.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'rates.php' ? 'active' : ''; ?>">
        <i class="fas fa-money-bill-wave"></i>
        <span>Rates & Fees</span>
    </a>
    
    <div class="nav-divider">REPORTS</div>
    
    <a href="sales.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i>
        <span>Sales Reports</span>
    </a>
    
    <a href="gym_activity.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'gym_activity.php' ? 'active' : ''; ?>">
        <i class="fas fa-door-open"></i>
        <span>Recent User Activity</span>
    </a>
    
    <div class="nav-divider">ACCOUNT</div>
    
    <a href="profile.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
        <i class="fas fa-user-circle"></i>
        <span>My Profile</span>
    </a>
    <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
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
                <h1>Sales Reports</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Reports / Sales
                </p>
            </div>
        </div>

        <!-- Alert Box -->
        <div id="alertBox" class="alert-box"></div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Revenue</h3>
                    <p class="stat-number">₱<?php echo number_format($totalRevenue, 2); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> From paid transactions
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Transactions</h3>
                    <p class="stat-number"><?php echo number_format($totalTransactions); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-calendar"></i> <?php echo date('M d', strtotime($dateFrom)) . ' - ' . date('M d', strtotime($dateTo)); ?>
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon teal">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Paid Transactions</h3>
                    <p class="stat-number"><?php echo number_format($paidTransactions); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-check"></i> Successfully paid
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>Pending Payments</h3>
                    <p class="stat-number"><?php echo number_format($pendingTransactions); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-hourglass-half"></i> Awaiting payment
                    </span>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="sales.php" class="filters-form">
                <div class="filter-group">
                    <label for="date_from">From Date:</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="filter-group">
                    <label for="date_to">To Date:</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
                <div class="filter-group">
                    <label for="payment_type">Payment Type:</label>
                    <select id="payment_type" name="payment_type">
                        <option value="all" <?= $paymentType === 'all' ? 'selected' : '' ?>>All Types</option>
                        <option value="Membership" <?= $paymentType === 'Membership' ? 'selected' : '' ?>>Membership</option>
                        <option value="Monthly" <?= $paymentType === 'Monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="Daily" <?= $paymentType === 'Daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="Service" <?= $paymentType === 'Service' ? 'selected' : '' ?>>Service</option>
                        <option value="Class" <?= $paymentType === 'Class' ? 'selected' : '' ?>>Class</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="payment_status">Status:</label>
                    <select id="payment_status" name="payment_status">
                        <option value="all" <?= $paymentStatus === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="Paid" <?= $paymentStatus === 'Paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="Pending" <?= $paymentStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Refunded" <?= $paymentStatus === 'Refunded' ? 'selected' : '' ?>>Refunded</option>
                        <option value="Cancelled" <?= $paymentStatus === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="payment_method">Method:</label>
                    <select id="payment_method" name="payment_method">
                        <option value="all" <?= $paymentMethod === 'all' ? 'selected' : '' ?>>All Methods</option>
                        <option value="Cash" <?= $paymentMethod === 'Cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="Credit Card" <?= $paymentMethod === 'Credit Card' ? 'selected' : '' ?>>Credit Card</option>
                        <option value="Debit Card" <?= $paymentMethod === 'Debit Card' ? 'selected' : '' ?>>Debit Card</option>
                        <option value="Bank Transfer" <?= $paymentMethod === 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="Mobile Payment" <?= $paymentMethod === 'Mobile Payment' ? 'selected' : '' ?>>Mobile Payment</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button type="button" onclick="resetFilters()" class="btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button type="button" onclick="exportReport('csv')" class="btn-export">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                    <button type="button" onclick="exportReport('pdf')" class="btn-export">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                </div>
            </form>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-container">
                <h3><i class="fas fa-chart-line"></i> Daily Revenue Trend</h3>
                <canvas id="dailyRevenueChart"></canvas>
            </div>
            <div class="chart-container">
                <h3><i class="fas fa-chart-pie"></i> Revenue by Payment Type</h3>
                <canvas id="paymentTypeChart"></canvas>
            </div>
            <div class="chart-container">
                <h3><i class="fas fa-chart-bar"></i> Revenue by Payment Method</h3>
                <canvas id="paymentMethodChart"></canvas>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="table-section">
            <h3><i class="fas fa-list"></i> Transaction Details</h3>
            <div class="table-container">
                <table class="employees-table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($payments) > 0): ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><strong>#<?= $payment['payment_id'] ?></strong></td>
                                    <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                    <td>
                                        <div class="client-info">
                                            <strong><?= htmlspecialchars($payment['client_name']) ?></strong>
                                            <small><?= htmlspecialchars($payment['client_email']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="payment-type-badge type-<?= strtolower($payment['payment_type']) ?>">
                                            <?= htmlspecialchars($payment['payment_type']) ?>
                                        </span>
                                    </td>
                                    <td class="amount-cell">₱<?= number_format($payment['amount'], 2) ?></td>
                                    <td>
                                        <span class="payment-method-badge method-<?= strtolower(str_replace(' ', '-', $payment['payment_method'])) ?>">
                                            <i class="fas fa-<?= $payment['payment_method'] === 'Cash' ? 'money-bill-wave' : ($payment['payment_method'] === 'Credit Card' ? 'credit-card' : 'wallet') ?>"></i>
                                            <?= htmlspecialchars($payment['payment_method']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($payment['payment_status']) ?>">
                                            <i class="fas fa-circle"></i>
                                            <?= htmlspecialchars($payment['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($payment['created_by_name'] ?? 'System') ?></td>
                                    <td>
                                        <button onclick="viewPayment(<?= $payment['payment_id'] ?>)" class="btn-action btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($payment['payment_status'] === 'Pending'): ?>
                                        <button onclick="markAsPaid(<?= $payment['payment_id'] ?>)" class="btn-action btn-edit" title="Mark as Paid">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="no-data">
                                    <i class="fas fa-receipt" style="font-size: 48px; opacity: 0.3;"></i>
                                    <p>No transactions found for the selected filters</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Payment Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <h3><i class="fas fa-receipt"></i> Payment Details</h3>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <script>
        // Pass PHP data to JavaScript
        const dailyRevenueData = <?= json_encode($dailyRevenue) ?>;
        const revenueByTypeData = <?= json_encode($revenueByType) ?>;
        const revenueByMethodData = <?= json_encode($revenueByMethod) ?>;
    </script>
    <script src="../js/admin-dashboard.js"></script>
    <script src="../js/sales.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>