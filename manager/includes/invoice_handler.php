<?php
session_start();

// Check authentication
if (!isset($_SESSION['employee_id']) || ($_SESSION['employee_role'] !== 'Manager' && $_SESSION['employee_role'] !== 'Admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../../config/connection.php';
$conn = getDBConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {
    if ($action === 'generate_invoice') {
        $coach_id = intval($_POST['coach_id'] ?? 0);
        $invoice_month = $_POST['invoice_month'] ?? null; // Format: YYYY-MM
        $due_date = $_POST['due_date'] ?? null;

        if (!$coach_id || !$invoice_month || !$due_date) {
            throw new Exception('Missing required fields');
        }

        // Parse the invoice month
        $month_date = new DateTime($invoice_month . '-01');
        $from_date = $month_date->format('Y-m-01');
        $to_date = $month_date->format('Y-m-t');

        // Check if invoice already exists for this coach and month
        $existing = $conn->prepare("
            SELECT invoice_id FROM coach_invoices 
            WHERE coach_id = ? AND YEAR(invoice_month) = ? AND MONTH(invoice_month) = ?
        ");
        $existing->execute([$coach_id, $month_date->format('Y'), $month_date->format('m')]);
        if ($existing->rowCount() > 0) {
            throw new Exception('Invoice already exists for this coach and month');
        }

        // Get all pending commissions for this coach in the specified month
        $commissions_stmt = $conn->prepare("
            SELECT 
                commission_id,
                commission_amount,
                transaction_date,
                service_name,
                client_id
            FROM coach_commissions
            WHERE coach_id = ? 
            AND commission_status = 'Pending'
            AND transaction_date BETWEEN ? AND ?
            ORDER BY transaction_date
        ");
        $commissions_stmt->execute([$coach_id, $from_date, $to_date]);
        $commissions = $commissions_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($commissions)) {
            throw new Exception('No pending commissions found for this period');
        }

        // Calculate totals
        $total_commission = array_sum(array_column($commissions, 'commission_amount'));

        // Generate unique invoice number
        $invoice_number = 'INV-' . date('Y-m', strtotime($invoice_month)) . '-' . str_pad($coach_id, 4, '0', STR_PAD_LEFT);

        // Start transaction
        $conn->beginTransaction();

        try {
            // Create invoice
            $invoice_stmt = $conn->prepare("
                INSERT INTO coach_invoices (
                    coach_id,
                    invoice_number,
                    invoice_month,
                    from_date,
                    to_date,
                    total_commission_due,
                    transaction_count,
                    status,
                    issued_date,
                    due_date,
                    generated_by,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Issued', NOW(), ?, ?, NOW())
            ");
            
            $invoice_stmt->execute([
                $coach_id,
                $invoice_number,
                $invoice_month . '-01',
                $from_date,
                $to_date,
                $total_commission,
                count($commissions),
                $due_date,
                $_SESSION['employee_id']
            ]);

            $invoice_id = $conn->lastInsertId();

            // Update commission records
            $update_stmt = $conn->prepare("
                UPDATE coach_commissions 
                SET commission_status = 'Invoiced', invoice_id = ?
                WHERE commission_id = ?
            ");

            foreach ($commissions as $commission) {
                $update_stmt->execute([$invoice_id, $commission['commission_id']]);
            }

            $conn->commit();

            // Log activity
            $log_stmt = $conn->prepare("
                INSERT INTO coach_activity_logs (coach_id, activity_type, description, ip_address, user_agent, created_at)
                VALUES (?, 'Invoice Generated', ?, ?, ?, NOW())
            ");
            $log_stmt->execute([
                $coach_id,
                'Invoice ' . $invoice_number . ' generated for ' . count($commissions) . ' commissions',
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Invoice generated successfully',
                'invoice_id' => $invoice_id,
                'invoice_number' => $invoice_number,
                'redirect' => 'invoice-detail.php?invoice_id=' . $invoice_id
            ]);

        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }

    } elseif ($action === 'record_payment') {
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $payment_amount = floatval($_POST['payment_amount'] ?? 0);
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_method = $_POST['payment_method'] ?? '';
        $payment_reference = $_POST['payment_reference'] ?? '';
        $notes = $_POST['notes'] ?? '';

        if (!$invoice_id || $payment_amount <= 0) {
            throw new Exception('Invalid payment data');
        }

        // Get invoice details
        $invoice = $conn->prepare("SELECT * FROM coach_invoices WHERE invoice_id = ?");
        $invoice->execute([$invoice_id]);
        $invoice_data = $invoice->fetch(PDO::FETCH_ASSOC);

        if (!$invoice_data) {
            throw new Exception('Invoice not found');
        }

        $conn->beginTransaction();

        try {
            // Update invoice payment
            $new_paid_amount = ($invoice_data['paid_amount'] ?? 0) + $payment_amount;
            $new_status = $new_paid_amount >= $invoice_data['total_commission_due'] ? 'Paid' : 'Partially Paid';

            $update_invoice = $conn->prepare("
                UPDATE coach_invoices 
                SET paid_amount = ?, 
                    status = ?,
                    payment_date = ?,
                    payment_method = ?,
                    payment_reference = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE invoice_id = ?
            ");
            $update_invoice->execute([
                $new_paid_amount,
                $new_status,
                $payment_date,
                $payment_method,
                $payment_reference,
                $notes,
                $invoice_id
            ]);

            // Update related commissions
            if ($new_status === 'Paid') {
                $update_commissions = $conn->prepare("
                    UPDATE coach_commissions 
                    SET commission_status = 'Paid', paid_date = ?
                    WHERE invoice_id = ?
                ");
                $update_commissions->execute([$payment_date, $invoice_id]);
            }

            // Handle receipt file upload if provided
            if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../../uploads/commission_receipts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_ext = pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION);
                $file_name = 'INV-' . $invoice_id . '-' . time() . '.' . $file_ext;
                $file_path = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $file_path)) {
                    $receipt_path = 'uploads/commission_receipts/' . $file_name;
                } else {
                    throw new Exception('Failed to upload receipt file');
                }
            } else {
                $receipt_path = null;
            }

            // Create payment history record if table exists
            try {
                $payment_history = $conn->prepare("
                    INSERT INTO invoice_payment_history (
                        invoice_id, payment_amount, payment_date, payment_method, 
                        receipt_file, notes, recorded_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $payment_history->execute([
                    $invoice_id,
                    $payment_amount,
                    $payment_date,
                    $payment_method,
                    $receipt_path,
                    $notes,
                    $_SESSION['employee_id']
                ]);
            } catch (Exception $e) {
                // Table might not exist yet, continue anyway
            }

            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'new_status' => $new_status,
                'paid_amount' => $new_paid_amount
            ]);

        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }

    } elseif ($action === 'waive_commission') {
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $reason = $_POST['reason'] ?? '';

        if (!$invoice_id) {
            throw new Exception('Invoice ID required');
        }

        $conn->beginTransaction();

        try {
            // Update invoice status
            $update_invoice = $conn->prepare("
                UPDATE coach_invoices 
                SET status = 'Waived', notes = ?, updated_at = NOW()
                WHERE invoice_id = ?
            ");
            $update_invoice->execute([$reason, $invoice_id]);

            // Update related commissions
            $update_commissions = $conn->prepare("
                UPDATE coach_commissions 
                SET commission_status = 'Waived'
                WHERE invoice_id = ?
            ");
            $update_commissions->execute([$invoice_id]);

            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Commission waived successfully'
            ]);

        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    } elseif ($action === 'get_coach_history') {
        // Get invoice history for a coach
        $coach_id = intval($_GET['coach_id'] ?? 0);
        
        if (!$coach_id) {
            throw new Exception('Missing coach_id');
        }

        $stmt = $conn->prepare("
            SELECT 
                invoice_id,
                invoice_number,
                DATE_FORMAT(invoice_month, '%Y-%m') as invoice_month,
                total_commission_due,
                paid_amount,
                status,
                created_at
            FROM coach_invoices
            WHERE coach_id = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$coach_id]);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'invoices' => $invoices
        ]);

    } elseif ($action === 'get_payment_history') {
        // Get payment history for a coach
        $coach_id = intval($_GET['coach_id'] ?? 0);
        
        if (!$coach_id) {
            throw new Exception('Missing coach_id');
        }

        $stmt = $conn->prepare("
            SELECT 
                ci.invoice_id,
                ci.invoice_number,
                ci.paid_amount as payment_amount,
                ci.payment_method,
                ci.payment_reference,
                ci.paid_date,
                ci.total_commission_due
            FROM coach_invoices ci
            WHERE ci.coach_id = ? AND ci.paid_amount > 0
            ORDER BY ci.paid_date DESC
            LIMIT 50
        ");
        $stmt->execute([$coach_id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'payments' => $payments
        ]);

    } else {
        throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
