<?php
session_start();

// Check if user is logged in and has admin/manager role
if (!isset($_SESSION['employee_id']) || ($_SESSION['employee_role'] !== 'Manager' && $_SESSION['employee_role'] !== 'Admin')) {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

$employeeName = $_SESSION['employee_name'] ?? 'Manager';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));

// Fetch commission summary statistics
try {
    // Total pending commissions
    $totalPending = $conn->query("
        SELECT COALESCE(SUM(cc.commission_amount), 0) as total
        FROM coach_commissions cc
        WHERE cc.commission_status = 'Pending'
    ")->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Monthly collections
    $monthlyCollections = $conn->query("
        SELECT COALESCE(SUM(ci.paid_amount), 0) as total
        FROM coach_invoices ci
        WHERE MONTH(ci.issued_date) = MONTH(NOW())
        AND YEAR(ci.issued_date) = YEAR(NOW())
        AND ci.status IN ('Paid', 'Partially Paid')
    ")->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Outstanding invoices count
    $outstandingCount = $conn->query("
        SELECT COUNT(*) as count
        FROM coach_invoices
        WHERE status IN ('Issued', 'Overdue', 'Partially Paid')
    ")->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total invoiced amount
    $totalInvoiced = $conn->query("
        SELECT COALESCE(SUM(ci.total_commission_due), 0) as total
        FROM coach_invoices ci
    ")->fetch(PDO::FETCH_ASSOC)['total'];

} catch (Exception $e) {
    $totalPending = 0;
    $monthlyCollections = 0;
    $outstandingCount = 0;
    $totalInvoiced = 0;
}

// Fetch coaches with commission summary
try {
    $stmt = $conn->query("
        SELECT 
            c.coach_id,
            CONCAT(c.first_name, ' ', c.last_name) as coach_name,
            c.email,
            c.phone,
            COALESCE(SUM(CASE WHEN cc.commission_status = 'Pending' THEN cc.commission_amount ELSE 0 END), 0) as pending_balance,
            COALESCE(SUM(CASE WHEN cc.commission_status = 'Invoiced' THEN cc.commission_amount ELSE 0 END), 0) as invoiced_balance,
            COALESCE(SUM(CASE WHEN cc.commission_status = 'Paid' THEN cc.commission_amount ELSE 0 END), 0) as paid_total,
            COUNT(DISTINCT CASE WHEN cc.commission_status = 'Pending' THEN cc.commission_id END) as pending_count,
            c.status,
            MAX(ci.issued_date) as last_invoice_date,
            COUNT(DISTINCT ci.invoice_id) as total_invoices
        FROM coach c
        LEFT JOIN coach_commissions cc ON c.coach_id = cc.coach_id
        LEFT JOIN coach_invoices ci ON c.coach_id = ci.coach_id
        WHERE c.status = 'Active'
        GROUP BY c.coach_id, c.first_name, c.last_name, c.email, c.phone, c.status
        ORDER BY pending_balance DESC
    ");
    $coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $coaches = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Management - Empire Fitness</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/manager-components.css">
    <link rel="stylesheet" href="css/commission-management.css">
    <link rel="stylesheet" href="css/button-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar_navigation.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <h1>Commission Management</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Finance / Commission Management
                </p>
            </div>
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search coaches...">
                </div>
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

        <!-- Summary Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-content">
                    <h3>Pending Commissions</h3>
                    <p class="stat-number">₱<?php echo number_format($totalPending, 2); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-clock"></i> Awaiting invoice
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Monthly Collections</h3>
                    <p class="stat-number">₱<?php echo number_format($monthlyCollections, 2); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> This month
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3>Outstanding Invoices</h3>
                    <p class="stat-number"><?php echo number_format($outstandingCount); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-file-invoice"></i> Need payment
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Invoiced</h3>
                    <p class="stat-number">₱<?php echo number_format($totalInvoiced, 2); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-history"></i> All-time
                    </span>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="action-left">
                <button class="btn-primary" onclick="generateMonthlyInvoices()">
                    <i class="fas fa-file-invoice-dollar"></i> Generate Monthly Invoices
                </button>
                <button class="btn-secondary" onclick="viewPaymentHistory()">
                    <i class="fas fa-history"></i> Payment History
                </button>
            </div>
            <div class="action-right">
                <select id="filterStatus" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="Active">Active Coaches</option>
                </select>
                <select id="sortBy" class="filter-select">
                    <option value="balance">Sort by Balance</option>
                    <option value="name">Sort by Name</option>
                    <option value="recent">Most Recent Invoice</option>
                </select>
            </div>
        </div>

        <!-- Coaches Commission Table -->
        <div class="commission-table-container">
            <table class="commission-table" id="commissionTable">
                <thead>
                    <tr>
                        <th>Coach Name</th>
                        <th>Email</th>
                        <th>Pending Balance</th>
                        <th>Invoiced Balance</th>
                        <th>Total Paid</th>
                        <th>Last Invoice</th>
                        <th>Invoices</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coaches as $coach): ?>
                    <tr class="coach-row" data-coach-id="<?php echo $coach['coach_id']; ?>">
                        <td class="coach-name-cell">
                            <strong><?php echo htmlspecialchars($coach['coach_name']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($coach['email']); ?></td>
                        <td class="balance-cell pending">
                            ₱<?php echo number_format($coach['pending_balance'], 2); ?>
                            <span class="badge"><?php echo $coach['pending_count']; ?></span>
                        </td>
                        <td class="balance-cell invoiced">
                            ₱<?php echo number_format($coach['invoiced_balance'], 2); ?>
                        </td>
                        <td class="balance-cell paid">
                            ₱<?php echo number_format($coach['paid_total'], 2); ?>
                        </td>
                        <td>
                            <?php echo $coach['last_invoice_date'] ? date('M d, Y', strtotime($coach['last_invoice_date'])) : 'N/A'; ?>
                        </td>
                        <td>
                            <span class="badge info"><?php echo $coach['total_invoices']; ?></span>
                        </td>
                        <td class="action-cell">
                            <button class="btn-action btn-small" onclick="generateInvoice(<?php echo $coach['coach_id']; ?>)" title="Generate Invoice">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </button>
                            <button class="btn-action btn-small" onclick="viewHistory(<?php echo $coach['coach_id']; ?>)" title="View History">
                                <i class="fas fa-history"></i>
                            </button>
                            <button class="btn-action btn-small dropdown-toggle" onclick="toggleDropdown(this)" title="More">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a href="#" onclick="viewPaymentHistory(<?php echo $coach['coach_id']; ?>); return false;">
                                    <i class="fas fa-money-bill"></i> Payment History
                                </a>
                                <a href="#" onclick="viewCoachCommissions(<?php echo $coach['coach_id']; ?>); return false;">
                                    <i class="fas fa-list"></i> View Commissions
                                </a>
                                <a href="#" onclick="viewCoachProfile(<?php echo $coach['coach_id']; ?>); return false;">
                                    <i class="fas fa-user"></i> View Profile
                                </a>
                                <a href="#" onclick="sendPaymentReminder(<?php echo $coach['coach_id']; ?>); return false;">
                                    <i class="fas fa-bell"></i> Send Reminder
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($coaches)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Coaches Found</h3>
                <p>There are currently no active coaches with commission records.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Generate Invoice Modal -->
    <div id="generateInvoiceModal" class="modal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <h2><i class="fas fa-file-invoice-dollar"></i> Generate Invoice</h2>
                <button class="modal-close" onclick="closeGenerateInvoiceModal()">&times;</button>
            </div>
            <form id="generateInvoiceForm" method="POST" action="includes/invoice_handler.php">
                <input type="hidden" name="action" value="generate_invoice">
                <input type="hidden" name="coach_id" id="invoiceCoachId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Invoice Month *</label>
                        <input type="month" name="invoice_month" id="invoiceMonth" required>
                    </div>
                    <div class="form-group">
                        <label>Due Date *</label>
                        <input type="date" name="due_date" id="dueDate" required>
                    </div>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <span>This will consolidate all pending commissions for the selected month into an invoice.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeGenerateInvoiceModal()">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check"></i> Generate Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Invoice History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 id="historyModalTitle"><i class="fas fa-history"></i> Invoice History</h2>
                <button class="modal-close" onclick="document.getElementById('historyModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" id="historyContent">
                <!-- Loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="document.getElementById('historyModal').classList.remove('active')">Close</button>
            </div>
        </div>
    </div>

    <!-- Payment History Modal -->
    <div id="paymentHistoryModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 id="paymentHistoryModalTitle"><i class="fas fa-money-bill"></i> Payment History</h2>
                <button class="modal-close" onclick="document.getElementById('paymentHistoryModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" id="paymentHistoryContent">
                <!-- Loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="document.getElementById('paymentHistoryModal').classList.remove('active')">Close</button>
            </div>
        </div>
    </div>

    <script src="js/sidebar.js"></script>
    <script src="js/commission-management.js"></script>
</body>
</html>
