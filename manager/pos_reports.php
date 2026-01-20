<?php
session_start();

// Check if user is logged in and has Manager role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Manager') {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';

// Get manager info
$manager_name = $_SESSION['employee_name'] ?? 'Manager';
$managerInitial = strtoupper(substr($manager_name, 0, 1));

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Validate dates
if (strtotime($start_date) > strtotime($end_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
}

// Get filter parameters
$filter_method = isset($_GET['filter_method']) ? $_GET['filter_method'] : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filter_employee = isset($_GET['filter_employee']) ? $_GET['filter_employee'] : '';
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

try {
    $conn = getDBConnection();
    
    // Get all transactions in date range
    $sql = "SELECT 
                pt.transaction_id,
                pt.receipt_number,
                pt.employee_id,
                pt.client_id,
                pt.transaction_type,
                pt.amount,
                pt.payment_method,
                pt.transaction_date,
                pt.transaction_time,
                pt.status,
                COALESCE(e.first_name, 'N/A') as employee_name,
                COALESCE(c.first_name, 'Guest') as client_name
            FROM pos_transactions pt
            LEFT JOIN employees e ON pt.employee_id = e.employee_id
            LEFT JOIN clients c ON pt.client_id = c.client_id
            WHERE pt.transaction_date BETWEEN :start_date AND :end_date
            AND pt.status = 'Completed'
            ORDER BY pt.transaction_date DESC, pt.transaction_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    
    $all_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Apply filters
    $filtered_transactions = $all_transactions;
    
    if ($filter_method) {
        $filtered_transactions = array_filter($filtered_transactions, function($t) {
            return $t['payment_method'] === $_GET['filter_method'];
        });
        $filtered_transactions = array_values($filtered_transactions);
    }
    
    if ($filter_type) {
        $filtered_transactions = array_filter($filtered_transactions, function($t) {
            return $t['transaction_type'] === $_GET['filter_type'];
        });
        $filtered_transactions = array_values($filtered_transactions);
    }
    
    if ($filter_employee) {
        $filtered_transactions = array_filter($filtered_transactions, function($t) {
            return $t['employee_id'] === intval($_GET['filter_employee']);
        });
        $filtered_transactions = array_values($filtered_transactions);
    }
    
    // Calculate statistics
    $total_revenue = array_sum(array_column($all_transactions, 'amount'));
    $total_transactions = count($all_transactions);
    $days_in_range = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400 + 1);
    
    $average_per_transaction = $total_transactions > 0 ? $total_revenue / $total_transactions : 0;
    $average_per_day = $total_revenue / $days_in_range;
    
    // Get payment method breakdown
    $payment_methods = [];
    foreach ($all_transactions as $t) {
        $method = $t['payment_method'];
        if (!isset($payment_methods[$method])) {
            $payment_methods[$method] = ['count' => 0, 'amount' => 0];
        }
        $payment_methods[$method]['count']++;
        $payment_methods[$method]['amount'] += $t['amount'];
    }
    
    // Sort by amount
    uasort($payment_methods, function($a, $b) {
        return $b['amount'] <=> $a['amount'];
    });
    
    // Get transaction type breakdown
    $transaction_types = [];
    foreach ($all_transactions as $t) {
        $type = $t['transaction_type'];
        if (!isset($transaction_types[$type])) {
            $transaction_types[$type] = ['count' => 0, 'amount' => 0];
        }
        $transaction_types[$type]['count']++;
        $transaction_types[$type]['amount'] += $t['amount'];
    }
    
    // Sort by amount
    uasort($transaction_types, function($a, $b) {
        return $b['amount'] <=> $a['amount'];
    });
    
    // Get employee performance
    $employee_performance = [];
    $employee_names = [];
    
    foreach ($all_transactions as $t) {
        $emp_id = $t['employee_id'];
        if (!isset($employee_performance[$emp_id])) {
            $employee_performance[$emp_id] = [
                'count' => 0,
                'amount' => 0,
                'name' => $t['employee_name']
            ];
            $employee_names[$emp_id] = $t['employee_name'];
        }
        $employee_performance[$emp_id]['count']++;
        $employee_performance[$emp_id]['amount'] += $t['amount'];
    }
    
    // Sort by amount
    uasort($employee_performance, function($a, $b) {
        return $b['amount'] <=> $a['amount'];
    });
    
    // Get unique values for filters
    $unique_methods = array_unique(array_column($all_transactions, 'payment_method'));
    $unique_types = array_unique(array_column($all_transactions, 'transaction_type'));
    $unique_employees = [];
    foreach ($all_transactions as $t) {
        if ($t['employee_id']) {
            $unique_employees[$t['employee_id']] = $t['employee_name'];
        }
    }
    
    // Pagination
    $items_per_page = 20;
    $total_items = count($filtered_transactions);
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = min($current_page, $total_pages);
    
    $offset = ($current_page - 1) * $items_per_page;
    $paginated_transactions = array_slice($filtered_transactions, $offset, $items_per_page);
    
    // Get reconciliation data for single day
    $reconciliation_data = null;
    if ($start_date === $end_date) {
        $sql_attendance = "SELECT COUNT(*) as total FROM attendance_log WHERE DATE(check_in_time) = :date";
        $stmt_attendance = $conn->prepare($sql_attendance);
        $stmt_attendance->bindParam(':date', $start_date);
        $stmt_attendance->execute();
        $attendance_count = $stmt_attendance->fetch()['total'];
        
        $reconciliation_data = [
            'attendance_count' => $attendance_count,
            'transaction_count' => count($all_transactions),
            'unlinked_attendance' => $attendance_count - count($all_transactions),
            'discrepancy' => abs($attendance_count - count($all_transactions))
        ];
    }
    
} catch (Exception $e) {
    error_log("POS Reports Error: " . $e->getMessage());
    $all_transactions = [];
    $filtered_transactions = [];
    $total_revenue = 0;
    $total_transactions = 0;
    $days_in_range = 1;
    $average_per_transaction = 0;
    $average_per_day = 0;
    $payment_methods = [];
    $transaction_types = [];
    $employee_performance = [];
}

// Top payment method
$top_payment_method = reset($payment_methods);
$top_payment_method = $top_payment_method ? key($payment_methods) : 'N/A';

// Top employee
$top_employee = reset($employee_performance);
$top_employee_name = $top_employee ? $top_employee['name'] : 'N/A';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Reports - Empire Fitness</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --accent: #e74c3c;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --text: #333;
        }

      

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: var(--text);
        }

        

        /* Header */
        .page-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-info h1 {
            color: var(--dark);
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header-info p {
            color: #666;
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto auto auto;
            gap: 15px;
            align-items: flex-end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Stat Cards */
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.danger { border-left-color: var(--danger); }

        .stat-icon {
            font-size: 32px;
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dc3545;
            border-radius: 50%;
            color: white;
        }

        .stat-card.success .stat-icon { color: white; background: #dc3545; }
        .stat-card.warning .stat-icon { color: white; background: #dc3545; }
        .stat-card.danger .stat-icon { color: white; background: #dc3545; }

        .stat-content h3 {
            font-size: 14px;
            color: #999;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .stat-content .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
        }

        /* Tabs */
        .tab-navigation {
            display: flex;
            gap: 0;
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
            background: white;
            border-radius: 12px 12px 0 0;
            overflow: hidden;
        }

        .tab-button {
            flex: 1;
            padding: 18px;
            border: none;
            background: white;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }

        .tab-button:hover {
            background: #f9f9f9;
            color: var(--primary);
        }

        .tab-button.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: white;
        }

        .tab-content {
            display: none;
            background: white;
            padding: 30px;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #f9f9f9;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: var(--text);
            border-bottom: 2px solid #eee;
        }

        tbody td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success { background: #e8f8f5; color: var(--success); }
        .badge-warning { background: #fef5e7; color: var(--warning); }
        .badge-danger { background: #fadbd8; color: var(--danger); }
        .badge-info { background: #ebf5fb; color: #3498db; }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            color: var(--primary);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination span.current {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            cursor: default;
        }

        .pagination span.disabled {
            color: #999;
            cursor: not-allowed;
            opacity: 0.5;
        }

        /* Reconciliation Cards */
        .reconciliation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .reconciliation-card {
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .reconciliation-card.warning {
            background: #fff8e1;
            border-left: 4px solid var(--warning);
        }

        .reconciliation-card.success {
            background: #e8f8f5;
            border-left: 4px solid var(--success);
        }

        /* No data message */
        .no-data {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Overview info */
        .overview-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .info-item {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            border-left: 3px solid var(--primary);
        }

        .info-item label {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
        }

        .info-item .value {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        /* Print Styles */
        @media print {
            .page-header,
            .filter-section,
            .header-actions,
            .tab-navigation,
            .pagination,
            .no-print {
                display: none !important;
            }

            .tab-content {
                display: block !important;
                page-break-inside: avoid;
            }

            .tab-content:not(:last-child) {
                page-break-after: always;
            }

            body {
                background: white;
            }

            .container {
                padding: 0;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }

            .tab-navigation {
                flex-wrap: wrap;
            }

            .tab-button {
                flex: 1 1 50%;
            }

            .stat-cards {
                grid-template-columns: 1fr;
            }

            .stat-card {
                flex-direction: column;
                text-align: center;
            }

            table {
                font-size: 12px;
            }

            thead th, tbody td {
                padding: 10px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                margin-top: 15px;
                flex-wrap: wrap;
            }

            .btn-icon {
                flex: 1 1 45%;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar_navigation.php'; ?>

    <main class="main-content">
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-info">
                <h1><i class="fas fa-chart-bar"></i> POS Reports</h1>
                <p>Comprehensive point of sale transaction analysis</p>
            </div>
            <div class="header-actions no-print">
                <button class="btn-primary" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
                <button class="btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section no-print">
            <form method="get" action="">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Generate Report
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Summary Statistics -->
        <div class="stat-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Revenue</h3>
                    <div class="value">₱<?php echo number_format($total_revenue, 2); ?></div>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Transactions</h3>
                    <div class="value"><?php echo number_format($total_transactions); ?></div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3>Avg Per Transaction</h3>
                    <div class="value">₱<?php echo number_format($average_per_transaction, 2); ?></div>
                </div>
            </div>

            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3>Avg Per Day</h3>
                    <div class="value">₱<?php echo number_format($average_per_day, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation no-print">
            <button class="tab-button active" onclick="switchPageTab(event, 'summary')">
                <i class="fas fa-chart-pie"></i> Summary
            </button>
            <button class="tab-button" onclick="switchPageTab(event, 'payment_methods')">
                <i class="fas fa-credit-card"></i> Payment Methods
            </button>
            <button class="tab-button" onclick="switchPageTab(event, 'transaction_types')">
                <i class="fas fa-tag"></i> Transaction Types
            </button>
            <button class="tab-button" onclick="switchPageTab(event, 'employee_performance')">
                <i class="fas fa-user-tie"></i> Employees
            </button>
            <button class="tab-button" onclick="switchPageTab(event, 'reconciliation')">
                <i class="fas fa-balance-scale"></i> Reconciliation
            </button>
            <button class="tab-button" onclick="switchPageTab(event, 'transactions')">
                <i class="fas fa-list"></i> Transactions
            </button>
        </div>

        <!-- Tab Contents -->

        <!-- Summary Tab -->
        <div id="summary" class="tab-content active">
            <div class="overview-info">
                <div class="info-item">
                    <label>Report Period</label>
                    <div class="value"><?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></div>
                </div>
                <div class="info-item">
                    <label>Days Covered</label>
                    <div class="value"><?php echo intval($days_in_range); ?></div>
                </div>
                <div class="info-item">
                    <label>Top Payment Method</label>
                    <div class="value"><?php echo htmlspecialchars($top_payment_method); ?></div>
                </div>
                <div class="info-item">
                    <label>Top Performer</label>
                    <div class="value"><?php echo htmlspecialchars($top_employee_name); ?></div>
                </div>
            </div>

            <?php if (count($all_transactions) > 0): ?>
                <h3 style="margin-top: 30px; margin-bottom: 20px;">Payment Method Distribution</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Payment Method</th>
                                <th class="text-right">Count</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_methods as $method => $data): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($method); ?></strong></td>
                                    <td class="text-right"><?php echo number_format($data['count']); ?></td>
                                    <td class="text-right">₱<?php echo number_format($data['amount'], 2); ?></td>
                                    <td class="text-right"><?php echo number_format(($data['amount'] / $total_revenue) * 100, 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <h3>No transactions found</h3>
                    <p>Try adjusting your date range.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Methods Tab -->
        <div id="payment_methods" class="tab-content">
            <?php if (count($payment_methods) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Payment Method</th>
                                <th class="text-right">Transaction Count</th>
                                <th class="text-right">Total Amount</th>
                                <th class="text-right">Average Amount</th>
                                <th class="text-right">% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_methods as $method => $data): 
                                $avg = $data['count'] > 0 ? $data['amount'] / $data['count'] : 0;
                                $percentage = ($data['amount'] / $total_revenue) * 100;
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($method); ?></strong></td>
                                    <td class="text-right"><?php echo number_format($data['count']); ?></td>
                                    <td class="text-right">₱<?php echo number_format($data['amount'], 2); ?></td>
                                    <td class="text-right">₱<?php echo number_format($avg, 2); ?></td>
                                    <td class="text-right"><strong><?php echo number_format($percentage, 1); ?>%</strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="background: #f0f0f0; font-weight: 700;">
                                <td><strong>TOTAL</strong></td>
                                <td class="text-right"><?php echo number_format($total_transactions); ?></td>
                                <td class="text-right">₱<?php echo number_format($total_revenue, 2); ?></td>
                                <td class="text-right">₱<?php echo number_format($average_per_transaction, 2); ?></td>
                                <td class="text-right">100.0%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <h3>No data available</h3>
                </div>
            <?php endif; ?>
        </div>

        <!-- Transaction Types Tab -->
        <div id="transaction_types" class="tab-content">
            <?php if (count($transaction_types) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Transaction Type</th>
                                <th class="text-right">Count</th>
                                <th class="text-right">Total Amount</th>
                                <th class="text-right">Average Amount</th>
                                <th class="text-right">% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transaction_types as $type => $data): 
                                $avg = $data['count'] > 0 ? $data['amount'] / $data['count'] : 0;
                                $percentage = ($data['amount'] / $total_revenue) * 100;
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-info"><?php echo htmlspecialchars($type); ?></span>
                                    </td>
                                    <td class="text-right"><?php echo number_format($data['count']); ?></td>
                                    <td class="text-right">₱<?php echo number_format($data['amount'], 2); ?></td>
                                    <td class="text-right">₱<?php echo number_format($avg, 2); ?></td>
                                    <td class="text-right"><strong><?php echo number_format($percentage, 1); ?>%</strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="background: #f0f0f0; font-weight: 700;">
                                <td><strong>TOTAL</strong></td>
                                <td class="text-right"><?php echo number_format($total_transactions); ?></td>
                                <td class="text-right">₱<?php echo number_format($total_revenue, 2); ?></td>
                                <td class="text-right">₱<?php echo number_format($average_per_transaction, 2); ?></td>
                                <td class="text-right">100.0%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <h3>No data available</h3>
                </div>
            <?php endif; ?>
        </div>

        <!-- Employee Performance Tab -->
        <div id="employee_performance" class="tab-content">
            <?php if (count($employee_performance) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th class="text-right">Transactions</th>
                                <th class="text-right">Total Sales</th>
                                <th class="text-right">Avg Transaction</th>
                                <th class="text-right">% Contribution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employee_performance as $emp_id => $data): 
                                $avg = $data['count'] > 0 ? $data['amount'] / $data['count'] : 0;
                                $contribution = ($data['amount'] / $total_revenue) * 100;
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($data['name']); ?></strong>
                                    </td>
                                    <td class="text-right"><?php echo number_format($data['count']); ?></td>
                                    <td class="text-right">₱<?php echo number_format($data['amount'], 2); ?></td>
                                    <td class="text-right">₱<?php echo number_format($avg, 2); ?></td>
                                    <td class="text-right"><strong><?php echo number_format($contribution, 1); ?>%</strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="background: #f0f0f0; font-weight: 700;">
                                <td><strong>TOTAL</strong></td>
                                <td class="text-right"><?php echo number_format($total_transactions); ?></td>
                                <td class="text-right">₱<?php echo number_format($total_revenue, 2); ?></td>
                                <td class="text-right">₱<?php echo number_format($average_per_transaction, 2); ?></td>
                                <td class="text-right">100.0%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <h3>No data available</h3>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reconciliation Tab -->
        <div id="reconciliation" class="tab-content">
            <?php if ($reconciliation_data): ?>
                <div class="reconciliation-grid">
                    <div class="reconciliation-card success">
                        <h4 style="margin-bottom: 10px;">Total Attendance</h4>
                        <div style="font-size: 32px; font-weight: 700; color: var(--success);">
                            <?php echo $reconciliation_data['attendance_count']; ?>
                        </div>
                    </div>
                    <div class="reconciliation-card success">
                        <h4 style="margin-bottom: 10px;">Total Transactions</h4>
                        <div style="font-size: 32px; font-weight: 700; color: var(--success);">
                            <?php echo $reconciliation_data['transaction_count']; ?>
                        </div>
                    </div>
                    <div class="reconciliation-card <?php echo $reconciliation_data['discrepancy'] > 0 ? 'warning' : 'success'; ?>">
                        <h4 style="margin-bottom: 10px;">Discrepancy</h4>
                        <div style="font-size: 32px; font-weight: 700; color: <?php echo $reconciliation_data['discrepancy'] > 0 ? 'var(--warning)' : 'var(--success)'; ?>;">
                            <?php echo $reconciliation_data['discrepancy']; ?>
                        </div>
                    </div>
                </div>

                <?php if ($reconciliation_data['discrepancy'] > 0): ?>
                    <div style="background: #fef5e7; border-left: 4px solid var(--warning); padding: 20px; border-radius: 8px; margin-top: 20px;">
                        <h4 style="color: var(--warning); margin-bottom: 10px;">
                            <i class="fas fa-exclamation-triangle"></i> Reconciliation Alert
                        </h4>
                        <p>There is a discrepancy of <strong><?php echo $reconciliation_data['discrepancy']; ?></strong> record(s) between attendance and transactions.</p>
                    </div>
                <?php else: ?>
                    <div style="background: #e8f8f5; border-left: 4px solid var(--success); padding: 20px; border-radius: 8px; margin-top: 20px;">
                        <h4 style="color: var(--success); margin-bottom: 10px;">
                            <i class="fas fa-check-circle"></i> Perfect Match
                        </h4>
                        <p>All attendance records are matched with transactions.</p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-calendar"></i>
                    <h3>Select a single date to view reconciliation</h3>
                    <p>Reconciliation data is only available when comparing a single day.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Transactions Tab -->
        <div id="transactions" class="tab-content">
            <div class="filter-section" style="margin-bottom: 20px; background: #f9f9f9;">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="filter_method">Payment Method</label>
                        <select id="filter_method" onchange="applyFilters()">
                            <option value="">All Methods</option>
                            <?php foreach ($unique_methods as $method): ?>
                                <option value="<?php echo htmlspecialchars($method); ?>" 
                                    <?php echo $filter_method === $method ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($method); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filter_type">Transaction Type</label>
                        <select id="filter_type" onchange="applyFilters()">
                            <option value="">All Types</option>
                            <?php foreach ($unique_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" 
                                    <?php echo $filter_type === $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filter_employee">Employee</label>
                        <select id="filter_employee" onchange="applyFilters()">
                            <option value="">All Employees</option>
                            <?php foreach ($unique_employees as $emp_id => $emp_name): ?>
                                <option value="<?php echo htmlspecialchars($emp_id); ?>" 
                                    <?php echo $filter_employee === strval($emp_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" onclick="clearFilters()" class="btn-icon btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </div>

            <?php if (count($filtered_transactions) > 0): ?>
                <p style="margin-bottom: 20px; color: #666;">
                    Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $items_per_page, $total_items); ?></strong> 
                    of <strong><?php echo $total_items; ?></strong> transactions
                </p>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Employee</th>
                                <th>Client</th>
                                <th>Type</th>
                                <th class="text-right">Amount</th>
                                <th>Payment</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated_transactions as $transaction): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($transaction['receipt_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($transaction['employee_name']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['client_name']); ?></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo htmlspecialchars($transaction['transaction_type']); ?>
                                        </span>
                                    </td>
                                    <td class="text-right"><strong>₱<?php echo number_format($transaction['amount'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($transaction['payment_method']); ?></td>
                                    <td>
                                        <?php 
                                            $date = date('M d, Y', strtotime($transaction['transaction_date']));
                                            $time = date('H:i A', strtotime($transaction['transaction_time']));
                                            echo "$date<br><small>$time</small>";
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>&page=1<?php echo $filter_method ? "&filter_method=" . htmlspecialchars($filter_method) : ""; ?><?php echo $filter_type ? "&filter_type=" . htmlspecialchars($filter_type) : ""; ?><?php echo $filter_employee ? "&filter_employee=" . htmlspecialchars($filter_employee) : ""; ?>">
                                <i class="fas fa-chevron-left"></i> First
                            </a>
                            <a href="?start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>&page=<?php echo $current_page - 1; ?><?php echo $filter_method ? "&filter_method=" . htmlspecialchars($filter_method) : ""; ?><?php echo $filter_type ? "&filter_type=" . htmlspecialchars($filter_type) : ""; ?><?php echo $filter_employee ? "&filter_employee=" . htmlspecialchars($filter_employee) : ""; ?>">
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-left"></i> First</span>
                            <span class="disabled"><i class="fas fa-chevron-left"></i> Prev</span>
                        <?php endif; ?>

                        <span class="current">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>&page=<?php echo $current_page + 1; ?><?php echo $filter_method ? "&filter_method=" . htmlspecialchars($filter_method) : ""; ?><?php echo $filter_type ? "&filter_type=" . htmlspecialchars($filter_type) : ""; ?><?php echo $filter_employee ? "&filter_employee=" . htmlspecialchars($filter_employee) : ""; ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                            <a href="?start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>&page=<?php echo $total_pages; ?><?php echo $filter_method ? "&filter_method=" . htmlspecialchars($filter_method) : ""; ?><?php echo $filter_type ? "&filter_type=" . htmlspecialchars($filter_type) : ""; ?><?php echo $filter_employee ? "&filter_employee=" . htmlspecialchars($filter_employee) : ""; ?>">
                                Last <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                            <span class="disabled">Last <i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <h3>No transactions found</h3>
                    <p>Try adjusting your filters or date range.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function switchPageTab(event, tabName) {
            event.preventDefault();

            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => tab.classList.remove('active'));

            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(btn => btn.classList.remove('active'));

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function applyFilters() {
            const startDate = new URLSearchParams(window.location.search).get('start_date') || '<?php echo htmlspecialchars($start_date); ?>';
            const endDate = new URLSearchParams(window.location.search).get('end_date') || '<?php echo htmlspecialchars($end_date); ?>';
            const filterMethod = document.getElementById('filter_method').value;
            const filterType = document.getElementById('filter_type').value;
            const filterEmployee = document.getElementById('filter_employee').value;

            let url = `?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;
            if (filterMethod) url += `&filter_method=${encodeURIComponent(filterMethod)}`;
            if (filterType) url += `&filter_type=${encodeURIComponent(filterType)}`;
            if (filterEmployee) url += `&filter_employee=${encodeURIComponent(filterEmployee)}`;

            window.location.href = url;
        }

        function clearFilters() {
            const startDate = new URLSearchParams(window.location.search).get('start_date') || '<?php echo htmlspecialchars($start_date); ?>';
            const endDate = new URLSearchParams(window.location.search).get('end_date') || '<?php echo htmlspecialchars($end_date); ?>';
            window.location.href = `?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;
        }

        function exportToCSV() {
            const startDate = '<?php echo htmlspecialchars($start_date); ?>';
            const endDate = '<?php echo htmlspecialchars($end_date); ?>';
            
            let csv = 'EMPIRE FITNESS - POS REPORT\n';
            csv += `Period: ${startDate} to ${endDate}\n\n`;
            
            csv += 'SUMMARY STATISTICS\n';
            csv += `Total Revenue,₱<?php echo number_format($total_revenue, 2); ?>\n`;
            csv += `Total Transactions,<?php echo $total_transactions; ?>\n`;
            csv += `Average Per Transaction,₱<?php echo number_format($average_per_transaction, 2); ?>\n`;
            csv += `Average Per Day,₱<?php echo number_format($average_per_day, 2); ?>\n\n`;
            
            csv += 'PAYMENT METHOD BREAKDOWN\n';
            csv += 'Method,Count,Amount,Percentage\n';
            <?php foreach ($payment_methods as $method => $data): 
                $percentage = ($data['amount'] / $total_revenue) * 100;
            ?>
            csv += `<?php echo htmlspecialchars($method); ?>,<?php echo $data['count']; ?>,₱<?php echo number_format($data['amount'], 2); ?>,<?php echo number_format($percentage, 1); ?>%\n`;
            <?php endforeach; ?>
            
            csv += '\nTRANSACTION TYPE BREAKDOWN\n';
            csv += 'Type,Count,Amount,Percentage\n';
            <?php foreach ($transaction_types as $type => $data): 
                $percentage = ($data['amount'] / $total_revenue) * 100;
            ?>
            csv += `<?php echo htmlspecialchars($type); ?>,<?php echo $data['count']; ?>,₱<?php echo number_format($data['amount'], 2); ?>,<?php echo number_format($percentage, 1); ?>%\n`;
            <?php endforeach; ?>
            
            csv += '\nEMPLOYEE PERFORMANCE\n';
            csv += 'Employee,Transactions,Total Sales,Avg Transaction,% Contribution\n';
            <?php foreach ($employee_performance as $emp_id => $data): 
                $avg = $data['count'] > 0 ? $data['amount'] / $data['count'] : 0;
                $contribution = ($data['amount'] / $total_revenue) * 100;
            ?>
            csv += `<?php echo htmlspecialchars($data['name']); ?>,<?php echo $data['count']; ?>,₱<?php echo number_format($data['amount'], 2); ?>,₱<?php echo number_format($avg, 2); ?>,<?php echo number_format($contribution, 1); ?>%\n`;
            <?php endforeach; ?>

            const element = document.createElement('a');
            element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv));
            element.setAttribute('download', `pos-report-${startDate}-to-${endDate}.csv`);
            element.style.display = 'none';
            document.body.appendChild(element);
            element.click();
            document.body.removeChild(element);
        }
    </script>
    </main>
</body>
</html>
