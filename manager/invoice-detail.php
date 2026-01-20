<?php
session_start();

// Check if user is logged in and has admin/manager role
if (!isset($_SESSION['employee_id']) || ($_SESSION['employee_role'] !== 'Manager' && $_SESSION['employee_role'] !== 'Admin')) {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

$invoice_id = intval($_GET['invoice_id'] ?? 0);
if (!$invoice_id) {
    header("Location: commission-management.php");
    exit;
}

$employeeName = $_SESSION['employee_name'] ?? 'Manager';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));

// Fetch invoice details
try {
    $invoice_stmt = $conn->prepare("
        SELECT 
            ci.*,
            CONCAT(c.first_name, ' ', c.last_name) as coach_name,
            c.email as coach_email,
            c.phone as coach_phone,
            e.first_name as created_by_first,
            e.last_name as created_by_last
        FROM coach_invoices ci
        JOIN coach c ON ci.coach_id = c.coach_id
        LEFT JOIN employees e ON ci.generated_by = e.employee_id
        WHERE ci.invoice_id = ?
    ");
    $invoice_stmt->execute([$invoice_id]);
    $invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        header("Location: commission-management.php");
        exit;
    }

    // Fetch line items (commissions)
    $items_stmt = $conn->prepare("
        SELECT 
            cc.*,
            CONCAT(cl.first_name, ' ', cl.last_name) as client_name
        FROM coach_commissions cc
        LEFT JOIN clients cl ON cc.client_id = cl.client_id
        WHERE cc.invoice_id = ?
        ORDER BY cc.transaction_date
    ");
    $items_stmt->execute([$invoice_id]);
    $line_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $total_gross = array_sum(array_column($line_items, 'gross_amount'));
    $total_commission_amount = array_sum(array_column($line_items, 'commission_amount'));
    $total_coach_earnings = array_sum(array_column($line_items, 'coach_earnings'));

} catch (Exception $e) {
    header("Location: commission-management.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?> - Empire Fitness</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/manager-components.css">
    <link rel="stylesheet" href="css/invoice-detail.css">
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
                <h1>Invoice Detail</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Finance / 
                    <a href="commission-management.php">Commission Management</a> / Invoice
                </p>
            </div>
            <div class="topbar-right">
                <div class="user-profile">
                    <div class="user-avatar"><?php echo $employeeInitial; ?></div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($employeeName); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars($_SESSION['employee_role']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Container -->
        <div class="invoice-container">
            <!-- Invoice Header -->
            <div class="invoice-header">
                <div class="header-left">
                    <div class="invoice-title">
                        <h2>INVOICE</h2>
                        <p class="invoice-number"><?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                    </div>
                </div>
                <div class="header-right">
                    <div class="invoice-status">
                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $invoice['status'])); ?>">
                            <?php echo htmlspecialchars($invoice['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Invoice Details -->
            <div class="invoice-details">
                <div class="details-section">
                    <h4>COACH INFORMATION</h4>
                    <div class="detail-row">
                        <span class="label">Name:</span>
                        <span class="value"><?php echo htmlspecialchars($invoice['coach_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Email:</span>
                        <span class="value"><?php echo htmlspecialchars($invoice['coach_email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Phone:</span>
                        <span class="value"><?php echo htmlspecialchars($invoice['coach_phone'] ?? 'N/A'); ?></span>
                    </div>
                </div>

                <div class="details-section">
                    <h4>INVOICE INFORMATION</h4>
                    <div class="detail-row">
                        <span class="label">Issue Date:</span>
                        <span class="value"><?php echo date('M d, Y', strtotime($invoice['issued_date'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Due Date:</span>
                        <span class="value"><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Invoice Period:</span>
                        <span class="value">
                            <?php echo date('M d, Y', strtotime($invoice['from_date'])); ?> - 
                            <?php echo date('M d, Y', strtotime($invoice['to_date'])); ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Created By:</span>
                        <span class="value">
                            <?php echo htmlspecialchars($invoice['created_by_first'] . ' ' . $invoice['created_by_last']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Line Items Table -->
            <div class="line-items-section">
                <h3>Commission Breakdown</h3>
                <table class="line-items-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Service</th>
                            <th>Client</th>
                            <th>Gross Amount</th>
                            <th>Commission Rate</th>
                            <th>Commission Amount</th>
                            <th>Coach Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($line_items as $item): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($item['transaction_date'])); ?></td>
                            <td><?php echo htmlspecialchars($item['service_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['client_name'] ?? 'N/A'); ?></td>
                            <td>₱<?php echo number_format($item['gross_amount'], 2); ?></td>
                            <td><?php echo number_format($item['commission_rate'], 2); ?>%</td>
                            <td>₱<?php echo number_format($item['commission_amount'], 2); ?></td>
                            <td>₱<?php echo number_format($item['coach_earnings'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="invoice-totals">
                    <div class="total-row">
                        <span>Transactions:</span>
                        <span><?php echo count($line_items); ?></span>
                    </div>
                    <div class="total-row">
                        <span>Total Gross:</span>
                        <span>₱<?php echo number_format($total_gross, 2); ?></span>
                    </div>
                    <div class="total-row">
                        <span>Total Commission:</span>
                        <span>₱<?php echo number_format($total_commission_amount, 2); ?></span>
                    </div>
                    <div class="total-row grand-total">
                        <span>Amount Due:</span>
                        <span>₱<?php echo number_format($invoice['total_commission_due'], 2); ?></span>
                    </div>
                    <?php if ($invoice['paid_amount'] > 0): ?>
                    <div class="total-row">
                        <span>Amount Paid:</span>
                        <span>₱<?php echo number_format($invoice['paid_amount'], 2); ?></span>
                    </div>
                    <div class="total-row">
                        <span>Balance:</span>
                        <span>₱<?php echo number_format($invoice['total_commission_due'] - $invoice['paid_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Information -->
            <?php if ($invoice['paid_amount'] > 0): ?>
            <div class="payment-info-section">
                <h3>Payment Information</h3>
                <div class="detail-row">
                    <span class="label">Payment Method:</span>
                    <span class="value"><?php echo htmlspecialchars($invoice['payment_method'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Payment Reference:</span>
                    <span class="value"><?php echo htmlspecialchars($invoice['payment_reference'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Payment Date:</span>
                    <span class="value"><?php echo $invoice['paid_date'] ? date('M d, Y', strtotime($invoice['paid_date'])) : 'N/A'; ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Notes:</span>
                    <span class="value"><?php echo htmlspecialchars($invoice['notes'] ?? 'No notes'); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="invoice-actions">
                <?php if (in_array($invoice['status'], ['Issued', 'Partially Paid', 'Overdue'])): ?>
                <button class="btn-primary" onclick="openRecordPaymentModal()">
                    <i class="fas fa-money-bill-wave"></i> Record Payment
                </button>
                <?php endif; ?>

                <?php if (!in_array($invoice['status'], ['Paid', 'Waived'])): ?>
                <button class="btn-secondary" onclick="openWaiveModal()">
                    <i class="fas fa-times-circle"></i> Waive Commission
                </button>
                <button class="btn-secondary" onclick="sendPaymentReminder()">
                    <i class="fas fa-bell"></i> Send Reminder
                </button>
                <?php endif; ?>

                <button class="btn-secondary" onclick="generatePDF()">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>
                <button class="btn-secondary" onclick="history.back()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            </div>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div id="recordPaymentModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2><i class="fas fa-money-bill-wave"></i> Record Payment</h2>
                <button class="modal-close" onclick="closeRecordPaymentModal()">&times;</button>
            </div>
            <form id="recordPaymentForm" method="POST" action="includes/invoice_handler.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Payment Amount *</label>
                            <input type="number" name="payment_amount" step="0.01" required placeholder="₱ 0.00">
                        </div>
                        <div class="form-group">
                            <label>Payment Date *</label>
                            <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Payment Method *</label>
                            <select name="payment_method" required>
                                <option value="">Select method</option>
                                <option value="Cash">Cash</option>
                                <option value="GCash">GCash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Check">Check</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Payment Reference</label>
                            <input type="text" name="payment_reference" placeholder="e.g., Transaction ID, Check #">
                        </div>
                        <div class="form-group full-width">
                            <label>Receipt/Proof File</label>
                            <input type="file" name="receipt_file" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="form-group full-width">
                            <label>Notes</label>
                            <textarea name="notes" rows="3" placeholder="Optional notes about this payment"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeRecordPaymentModal()">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Waive Commission Modal -->
    <div id="waiveModal" class="modal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <h2><i class="fas fa-times-circle"></i> Waive Commission</h2>
                <button class="modal-close" onclick="closeWaiveModal()">&times;</button>
            </div>
            <form id="waiveForm" method="POST" action="includes/invoice_handler.php">
                <input type="hidden" name="action" value="waive_commission">
                <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>This action will mark the entire invoice as waived. Please provide a reason.</span>
                    </div>
                    <div class="form-group">
                        <label>Reason for Waiver *</label>
                        <textarea name="reason" rows="4" required placeholder="Explain why this commission is being waived..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeWaiveModal()">Cancel</button>
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-check"></i> Confirm Waiver
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/sidebar.js"></script>
    <script src="js/invoice-detail.js"></script>
</body>
</html>
