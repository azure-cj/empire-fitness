<?php
session_start();
require_once '../../config/connection.php';

// Check if user is logged in and has admin/manager role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Super Admin', 'Admin', 'Manager'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $conn = getDBConnection();

    try {
        switch ($action) {

            // Return a paginated list of payments (AJAX friendly)
            case 'list':
                $page = max(1, intval($_POST['page'] ?? 1));
                $perPage = max(1, intval($_POST['per_page'] ?? 25));
                $offset = ($page - 1) * $perPage;

                $dateFrom = $_POST['date_from'] ?? date('Y-m-01');
                $dateTo = $_POST['date_to'] ?? date('Y-m-d');
                $paymentType = $_POST['payment_type'] ?? 'all';
                $paymentStatus = $_POST['payment_status'] ?? 'all';
                $paymentMethod = $_POST['payment_method'] ?? 'all';

                $where = " WHERE payment_date BETWEEN ? AND ? ";
                $params = [$dateFrom, $dateTo];

                if ($paymentType !== 'all') {
                    $where .= " AND payment_type = ? ";
                    $params[] = $paymentType;
                }
                if ($paymentStatus !== 'all') {
                    $where .= " AND payment_status = ? ";
                    $params[] = $paymentStatus;
                }
                if ($paymentMethod !== 'all') {
                    $where .= " AND payment_method = ? ";
                    $params[] = $paymentMethod;
                }

                // total count
                $countSql = "SELECT COUNT(*) as total FROM unified_payment_report " . $where;
                $stmt = $conn->prepare($countSql);
                $stmt->execute($params);
                $total = (int)$stmt->fetchColumn();

                // select page
                $sql = "SELECT * FROM unified_payment_report " . $where . " ORDER BY payment_date DESC, payment_id DESC LIMIT ? OFFSET ?";
                $stmt = $conn->prepare($sql);
                $execParams = array_merge($params, [$perPage, $offset]);
                // Need to bind integers for LIMIT/OFFSET
                $i = 1;
                foreach ($params as $p) {
                    $stmt->bindValue($i++, $p);
                }
                $stmt->bindValue($i++, (int)$perPage, PDO::PARAM_INT);
                $stmt->bindValue($i++, (int)$offset, PDO::PARAM_INT);

                $stmt->execute();
                $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // calculate totals (sum of Paid amounts)
                $paidSql = "SELECT COALESCE(SUM(amount),0) as total_paid, COUNT(*) as total_transactions FROM unified_payment_report " . $where;
                $stmt2 = $conn->prepare($paidSql);
                $stmt2->execute($params);
                $summary = $stmt2->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'payments' => $payments,
                    'summary' => [
                        'total_paid' => (float)$summary['total_paid'],
                        'total_transactions' => (int)$summary['total_transactions'],
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => $total
                    ]
                ]);
                break;

            // Get single payment details
            case 'get':
                if (!isset($_POST['payment_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
                    exit;
                }

                $stmt = $conn->prepare("
                    SELECT upr.*,
                           CONCAT(c.first_name, ' ', c.last_name) AS client_name,
                           c.email AS client_email,
                           CONCAT(e.first_name, ' ', e.last_name) AS created_by_name
                    FROM unified_payment_report upr
                    LEFT JOIN clients c ON upr.client_id = c.client_id
                    LEFT JOIN employees e ON upr.created_by = e.employee_id
                    WHERE upr.payment_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$_POST['payment_id']]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($payment) {
                    echo json_encode(['success' => true, 'payment' => $payment]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Payment not found']);
                }
                break;

            // Mark payment as paid (updates unified_payments and, if needed, attendance_with_payments)
            case 'mark_as_paid':
                if (!isset($_POST['payment_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
                    exit;
                }
                $paymentId = intval($_POST['payment_id']);

                // Update unified_payments table if exists (primary store)
                $stmt = $conn->prepare("UPDATE unified_payments SET payment_status = 'Paid' WHERE payment_id = ?");
                $result = $stmt->execute([$paymentId]);

                // Also attempt to update attendance_with_payments if row exists referencing this payment
                $stmt2 = $conn->prepare("UPDATE attendance_with_payments SET payment_status = 'Paid' WHERE payment_id = ?");
                $stmt2->execute([$paymentId]);

                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Payment marked as paid successfully!' : 'Error updating payment status'
                ]);
                break;

            // Export CSV using unified_payment_report
            case 'export_csv':
                // Get filter parameters
                $dateFrom = $_POST['date_from'] ?? date('Y-m-01');
                $dateTo = $_POST['date_to'] ?? date('Y-m-d');
                $paymentType = $_POST['payment_type'] ?? 'all';
                $paymentStatus = $_POST['payment_status'] ?? 'all';
                $paymentMethod = $_POST['payment_method'] ?? 'all';

                // Build query
                $sql = "SELECT * FROM unified_payment_report WHERE payment_date BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];

                if ($paymentType !== 'all') {
                    $sql .= " AND payment_type = ?";
                    $params[] = $paymentType;
                }

                if ($paymentStatus !== 'all') {
                    $sql .= " AND payment_status = ?";
                    $params[] = $paymentStatus;
                }

                if ($paymentMethod !== 'all') {
                    $sql .= " AND payment_method = ?";
                    $params[] = $paymentMethod;
                }

                $sql .= " ORDER BY payment_date DESC, payment_id DESC";

                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Create CSV content
                $csv = "Payment ID,Date,Client Name,Client Email,Type,Reference ID,Item Name,Amount,Method,Status,Remarks,Created At\n";

                foreach ($payments as $payment) {
                    $csv .= sprintf(
                        "%d,%s,%s,%s,%s,%s,%s,%.2f,%s,%s,%s,%s\n",
                        $payment['payment_id'],
                        $payment['payment_date'],
                        '"' . str_replace('"', '""', $payment['client_name']) . '"',
                        $payment['client_email'] ?? '',
                        $payment['payment_type'] ?? '',
                        $payment['reference_id'] ?? '',
                        '"' . str_replace('"', '""', $payment['item_name'] ?? '') . '"',
                        $payment['amount'] ?? 0.00,
                        $payment['payment_method'] ?? '',
                        $payment['payment_status'] ?? '',
                        '"' . str_replace('"', '""', $payment['remarks'] ?? '') . '"',
                        $payment['created_at'] ?? ''
                    );
                }

                // Calculate totals
                $totalRevenue = 0.0;
                $totalTransactions = count($payments);
                foreach ($payments as $p) {
                    if (isset($p['payment_status']) && $p['payment_status'] === 'Paid') {
                        $totalRevenue += (float)$p['amount'];
                    }
                }

                $csv .= "\n";
                $csv .= "Summary\n";
                $csv .= "Total Transactions," . $totalTransactions . "\n";
                $csv .= "Total Revenue,₱" . number_format($totalRevenue, 2) . "\n";
                $csv .= "Date Range," . $dateFrom . " to " . $dateTo . "\n";
                $csv .= "Generated," . date('Y-m-d H:i:s') . "\n";

                echo json_encode([
                    'success' => true,
                    'csv' => $csv,
                    'filename' => 'sales_report_' . date('Y-m-d_His') . '.csv'
                ]);
                break;

            case 'export_pdf':
                // Placeholder
                echo json_encode([
                    'success' => false,
                    'message' => 'PDF export feature is coming soon. Please use CSV export for now.'
                ]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>