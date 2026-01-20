<?php
/**
 * POS Handler for Receptionist
 * Manages point of sale transactions, shifts, and reporting
 */

header('Content-Type: application/json');

session_start();

// Check authentication
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Receptionist') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/connection.php';
$conn = getDBConnection();

$employeeId = $_SESSION['employee_id'] ?? null;
$employeeName = $_SESSION['employee_name'] ?? null;
$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch ($action) {
        case 'start_session':
            handleStartSession($conn, $employeeId, $employeeName);
            break;
        case 'end_session':
            handleEndSession($conn, $employeeId);
            break;
        case 'get_current_session':
            handleGetCurrentSession($conn, $employeeId);
            break;
        case 'add_transaction':
            handleAddTransaction($conn, $employeeId, $employeeName);
            break;
        case 'get_transactions':
            handleGetTransactions($conn, $employeeId);
            break;
        case 'get_session_summary':
            handleGetSessionSummary($conn, $employeeId);
            break;
        case 'process_payment':
            handleProcessPayment($conn, $employeeId, $employeeName);
            break;
        case 'get_daily_summary':
            handleGetDailySummary($conn);
            break;
        case 'void_transaction':
            handleVoidTransaction($conn, $employeeId);
            break;
        case 'get_rates':
            handleGetRates($conn);
            break;
        case 'search_client':
            handleSearchClient($conn);
            break;
        case 'process_membership':
            handleProcessMembership($conn, $employeeId, $employeeName);
            break;
        case 'get_memberships':
            handleGetMemberships($conn);
            break;
        case 'get_transaction':
            handleGetTransaction($conn);
            break;
        case 'save_report':
            handleSaveReport($conn, $employeeId, $employeeName);
            break;
        case 'get_session_reports':
            handleGetSessionReports($conn);
            break;
        case 'get_last_sessions':
            handleGetLastSessions($conn);
            break;
        case 'get_recent_sessions':
            handleGetRecentSessions($conn);
            break;
        case 'get_receptionists':
            handleGetReceptionists($conn);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ===================== HANDLER FUNCTIONS =====================

function handleStartSession($conn, $employeeId, $employeeName) {
    // Ensure employeeId is an integer
    $employeeId = (int)$employeeId;
    
    // Check if already has an open session
    $stmt = $conn->prepare("SELECT session_id FROM pos_sessions WHERE employee_id = ? AND status = 'Open'");
    $stmt->execute([$employeeId]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('You already have an open POS session');
    }

    $openingBalance = $_POST['opening_balance'] ?? 0;

    $stmt = $conn->prepare("
        INSERT INTO pos_sessions (employee_id, employee_name, opening_balance, status)
        VALUES (?, ?, ?, 'Open')
    ");
    $stmt->execute([$employeeId, $employeeName, $openingBalance]);

    echo json_encode([
        'success' => true,
        'message' => 'POS session started',
        'session_id' => $conn->lastInsertId()
    ]);
}

function handleEndSession($conn, $employeeId) {
    // Ensure employeeId is an integer
    $employeeId = (int)$employeeId;
    
    // Get active session
    $stmt = $conn->prepare("SELECT session_id FROM pos_sessions WHERE employee_id = ? AND status = 'Open'");
    $execResult = $stmt->execute([$employeeId]);
    
    if (!$execResult) {
        $error = $stmt->errorInfo();
        throw new Exception('Database error: ' . $error[2]);
    }
    
    $result = $stmt->fetch();

    if (!$result) {
        // This should not happen if session was properly created
        // Log for debugging
        error_log("DEBUG: No open session found for employee_id=$employeeId. Session might have been closed already or employee_id mismatch.");
        
        throw new Exception('No active POS session found. Please start a new session.');
    }

    $sessionId = $result['session_id'];
    $closingBalance = $_POST['closing_balance'] ?? 0;
    $notes = $_POST['notes'] ?? '';

    // Calculate totals from transactions
    $stmt = $conn->prepare("
        SELECT 
            SUM(amount) as total_sales,
            SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END) as cash_total,
            SUM(CASE WHEN payment_method != 'Cash' THEN amount ELSE 0 END) as digital_total
        FROM pos_transactions
        WHERE session_id = ?
    ");
    $stmt->execute([$sessionId]);
    $totals = $stmt->fetch();

    $totalSales = $totals['total_sales'] ?? 0;
    $cashTotal = $totals['cash_total'] ?? 0;
    $digitalTotal = $totals['digital_total'] ?? 0;

    // Update session with correct parameter count
    $stmt = $conn->prepare("
        UPDATE pos_sessions
        SET 
            end_time = NOW(),
            closing_balance = ?,
            total_sales = ?,
            total_cash = ?,
            total_digital = ?,
            notes = ?,
            status = 'Closed'
        WHERE session_id = ?
    ");
    $stmt->execute([
        $closingBalance,
        $totalSales,
        $cashTotal,
        $digitalTotal,
        $notes,
        $sessionId
    ]);

    // Update daily report
    updateDailyReport($conn);

    echo json_encode([
        'success' => true,
        'message' => 'POS session closed',
        'session_id' => $sessionId,
        'total_sales' => $totalSales,
        'cash_total' => $cashTotal,
        'digital_total' => $digitalTotal
    ]);
}

function handleGetCurrentSession($conn, $employeeId) {
    // Ensure employeeId is an integer
    $employeeId = (int)$employeeId;
    
    $stmt = $conn->prepare("
        SELECT * FROM pos_sessions 
        WHERE employee_id = ? AND status = 'Open'
        ORDER BY start_time DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId]);
    $session = $stmt->fetch();

    if ($session) {
        // Get transaction count and totals
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as transaction_count,
                SUM(amount) as total
            FROM pos_transactions
            WHERE session_id = ?
        ");
        $stmt->execute([$session['session_id']]);
        $stats = $stmt->fetch();
        $session['transaction_count'] = $stats['transaction_count'] ?? 0;
        $session['current_total'] = $stats['total'] ?? 0;
    }

    echo json_encode([
        'success' => true,
        'session' => $session
    ]);
}

function handleAddTransaction($conn, $employeeId, $employeeName) {
    // Get active session
    $stmt = $conn->prepare("SELECT session_id FROM pos_sessions WHERE employee_id = ? AND status = 'Open'");
    $stmt->execute([$employeeId]);
    $result = $stmt->fetch();

    if (!$result) {
        throw new Exception('No active POS session. Please start a session first.');
    }

    $sessionId = $result['session_id'];

    // Validate required fields
    $transactionType = $_POST['transaction_type'] ?? null;
    $amount = floatval($_POST['amount'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? null;
    $description = $_POST['description'] ?? '';
    $clientId = $_POST['client_id'] ?? null;
    $clientName = $_POST['client_name'] ?? '';

    if (!$transactionType || !$paymentMethod || $amount <= 0) {
        throw new Exception('Missing required transaction fields');
    }

    // Generate receipt number
    $receiptNumber = generateReceiptNumber($conn, $sessionId);

    // Insert transaction
    $stmt = $conn->prepare("
        INSERT INTO pos_transactions (
            session_id, employee_id, employee_name, client_id, client_name,
            transaction_type, description, amount, payment_method,
            transaction_date, transaction_time, receipt_number, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, 'Completed')
    ");
    $stmt->execute([
        $sessionId, $employeeId, $employeeName, $clientId, $clientName,
        $transactionType, $description, $amount, $paymentMethod, $receiptNumber
    ]);

    $transactionId = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Transaction added',
        'transaction_id' => $transactionId,
        'receipt_number' => $receiptNumber
    ]);
}

function handleGetTransactions($conn, $employeeId) {
    // Get date from GET parameter or use today
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Get current session
    $stmt = $conn->prepare("SELECT session_id FROM pos_sessions WHERE employee_id = ? AND status = 'Open'");
    $stmt->execute([$employeeId]);
    $result = $stmt->fetch();

    if ($result) {
        // Load transactions from active session
        $sessionId = $result['session_id'];
        $stmt = $conn->prepare("
            SELECT * FROM pos_transactions
            WHERE session_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$sessionId]);
    } else {
        // Load transactions for the specified date (from all sessions)
        $stmt = $conn->prepare("
            SELECT * FROM pos_transactions
            WHERE employee_id = ? AND DATE(transaction_date) = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$employeeId, $date]);
    }

    $transactions = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'count' => count($transactions)
    ]);
}

function handleGetSessionSummary($conn, $employeeId) {
    // Ensure employeeId is an integer
    $employeeId = (int)$employeeId;
    
    $stmt = $conn->prepare("SELECT session_id FROM pos_sessions WHERE employee_id = ? AND status = 'Open'");
    $stmt->execute([$employeeId]);
    $result = $stmt->fetch();

    if (!$result) {
        echo json_encode(['success' => true, 'summary' => null]);
        return;
    }

    $sessionId = $result['session_id'];

    $stmt = $conn->prepare("
        SELECT
            ps.*,
            COUNT(pt.transaction_id) as transaction_count,
            SUM(CASE WHEN pt.payment_method = 'Cash' THEN pt.amount ELSE 0 END) as cash_total,
            SUM(CASE WHEN pt.payment_method != 'Cash' THEN pt.amount ELSE 0 END) as digital_total,
            SUM(pt.amount) as total_sales
        FROM pos_sessions ps
        LEFT JOIN pos_transactions pt ON ps.session_id = pt.session_id
        WHERE ps.session_id = ?
        GROUP BY ps.session_id
    ");
    $stmt->execute([$sessionId]);
    $summary = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'summary' => $summary
    ]);
}

function handleProcessPayment($conn, $employeeId, $employeeName) {
    // This handles recording payments from other systems into POS
    $paymentData = json_decode(file_get_contents('php://input'), true);

    if (!$paymentData) {
        $paymentData = $_POST;
    }

    $stmt = $conn->prepare("SELECT session_id FROM pos_sessions WHERE employee_id = ? AND status = 'Open'");
    $stmt->execute([$employeeId]);
    $result = $stmt->fetch();

    if (!$result) {
        throw new Exception('No active POS session');
    }

    $sessionId = $result['session_id'];
    $receiptNumber = generateReceiptNumber($conn, $sessionId);

    $stmt = $conn->prepare("
        INSERT INTO pos_transactions (
            session_id, employee_id, employee_name, client_id, client_name,
            transaction_type, description, amount, payment_method,
            reference_id, reference_type,
            transaction_date, transaction_time, receipt_number, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, 'Completed')
    ");
    $stmt->execute([
        $sessionId, $employeeId, $employeeName,
        $paymentData['client_id'] ?? null,
        $paymentData['client_name'] ?? '',
        $paymentData['transaction_type'] ?? 'Service',
        $paymentData['description'] ?? '',
        $paymentData['amount'] ?? 0,
        $paymentData['payment_method'] ?? 'Cash',
        $paymentData['reference_id'] ?? null,
        $paymentData['reference_type'] ?? null,
        $receiptNumber
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Payment recorded',
        'receipt_number' => $receiptNumber
    ]);
}

function handleGetDailySummary($conn) {
    $today = date('Y-m-d');

    $stmt = $conn->prepare("
        SELECT * FROM pos_daily_reports
        WHERE report_date = ?
    ");
    $stmt->execute([$today]);
    $report = $stmt->fetch();

    if (!$report) {
        // Generate report from transactions
        $stmt = $conn->prepare("
            SELECT
                COUNT(*) as total_transactions,
                SUM(amount) as total_sales,
                SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END) as cash_total,
                SUM(CASE WHEN payment_method = 'GCash' THEN amount ELSE 0 END) as gcash_total,
                SUM(CASE WHEN payment_method = 'Bank Transfer - BDO' THEN amount ELSE 0 END) as bank_transfer_bdo_total,
                SUM(CASE WHEN payment_method = 'Bank Transfer - BPI' THEN amount ELSE 0 END) as bank_transfer_bpi_total,
                SUM(CASE WHEN payment_method = 'Bank Transfer - Other' THEN amount ELSE 0 END) as bank_transfer_other_total,
                SUM(CASE WHEN payment_method = 'Credit Card' THEN amount ELSE 0 END) as credit_card_total,
                SUM(CASE WHEN payment_method = 'Debit Card' THEN amount ELSE 0 END) as debit_card_total,
                SUM(CASE WHEN payment_method = 'Over the Counter' THEN amount ELSE 0 END) as over_the_counter_total,
                SUM(CASE WHEN payment_method = 'Mobile Payment' THEN amount ELSE 0 END) as mobile_payment_total
            FROM pos_transactions pt
            WHERE DATE(pt.transaction_date) = ?
        ");
        $stmt->execute([$today]);
        $report = $stmt->fetch();
    }

    echo json_encode([
        'success' => true,
        'report' => $report
    ]);
}

function handleVoidTransaction($conn, $employeeId) {
    $transactionId = $_POST['transaction_id'] ?? null;
    $reason = $_POST['reason'] ?? '';

    if (!$transactionId) {
        throw new Exception('Transaction ID required');
    }

    // Verify ownership
    $stmt = $conn->prepare("
        SELECT employee_id FROM pos_transactions WHERE transaction_id = ?
    ");
    $stmt->execute([$transactionId]);
    $trans = $stmt->fetch();

    if (!$trans || $trans['employee_id'] != $employeeId) {
        throw new Exception('You can only void your own transactions');
    }

    $stmt = $conn->prepare("
        UPDATE pos_transactions
        SET status = 'Cancelled'
        WHERE transaction_id = ?
    ");
    $stmt->execute([$transactionId]);

    echo json_encode([
        'success' => true,
        'message' => 'Transaction voided'
    ]);
}

function handleGetRates($conn) {
    $stmt = $conn->prepare("
        SELECT rate_id, rate_name, price, description, applies_to
        FROM rates
        WHERE is_active = 1
        ORDER BY rate_name
    ");
    $stmt->execute();
    $rates = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'rates' => $rates
    ]);
}

function handleGetMemberships($conn) {
    $stmt = $conn->prepare("SELECT membership_id, plan_name, monthly_fee, renewal_fee, duration_days, is_base_membership FROM memberships WHERE status = 'Active' ORDER BY plan_name");
    $stmt->execute();
    $memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'memberships' => $memberships
    ]);
}

function handleGetTransaction($conn) {
    $transactionId = $_GET['transaction_id'] ?? null;
    if (!$transactionId) {
        echo json_encode(['success' => false, 'message' => 'transaction_id required']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM pos_transactions WHERE transaction_id = ? LIMIT 1");
    $stmt->execute([$transactionId]);
    $trans = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trans) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        return;
    }

    $detail = ['transaction' => $trans];

    // If linked to a payment or membership reference, fetch details
    if (!empty($trans['reference_id']) && !empty($trans['reference_type'])) {
        if (strtolower($trans['reference_type']) === 'membership' || $trans['transaction_type'] === 'Membership') {
            $stmt = $conn->prepare("SELECT * FROM unified_payments WHERE payment_id = ? LIMIT 1");
            $stmt->execute([$trans['reference_id']]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($payment) $detail['payment'] = $payment;

            $stmt = $conn->prepare("SELECT * FROM client_memberships WHERE id = ? LIMIT 1");
            $stmt->execute([$trans['reference_id']]);
            $cm = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cm) $detail['client_membership'] = $cm;
        }
    }

    echo json_encode(['success' => true, 'detail' => $detail]);
}

function handleSearchClient($conn) {
    $search = $_GET['q'] ?? '';
    $search = "%{$search}%";

    $stmt = $conn->prepare("
        SELECT 
            client_id, 
            CONCAT(first_name, ' ', last_name) as name,
            email, 
            phone
        FROM clients
        WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)
        AND status = 'Active'
        LIMIT 10
    ");
    $stmt->execute([$search, $search, $search, $search]);
    $clients = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'clients' => $clients
    ]);
}

function handleProcessMembership($conn, $employeeId, $employeeName) {
    $clientId = $_POST['client_id'] ?? null;
    $membershipPlan = $_POST['membership_plan'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? 'Cash';
    $transactionType = $_POST['transaction_type'] ?? 'Membership';

    if (!$clientId) {
        throw new Exception('Client ID is required for membership processing');
    }

    // Find membership plan by id or name
    if (is_numeric($membershipPlan) && intval($membershipPlan) > 0) {
        $stmt = $conn->prepare("SELECT * FROM memberships WHERE membership_id = ? AND status = 'Active'");
        $stmt->execute([intval($membershipPlan)]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM memberships WHERE LOWER(plan_name) LIKE ? AND status = 'Active' LIMIT 1");
        $stmt->execute(['%' . strtolower($membershipPlan) . '%']);
    }

    $membership = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$membership) {
        throw new Exception('Selected membership plan not found or inactive');
    }

    // If this is a renewal, ensure the client has a base membership
    if ($transactionType === 'Membership Renewal') {
        $stmt = $conn->prepare("SELECT cm.* FROM client_memberships cm JOIN memberships m ON cm.membership_id = m.membership_id WHERE cm.client_id = ? AND cm.status = 'Active' AND m.is_base_membership = 1 LIMIT 1");
        $stmt->execute([$clientId]);
        $base = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$base) {
            echo json_encode(['success' => false, 'message' => 'Client has no base membership. Please add a base membership before performing a renewal.']);
            return;
        }
    }

    // Check for an existing active membership of the same type
    $stmt = $conn->prepare("SELECT * FROM client_memberships WHERE client_id = ? AND membership_id = ? AND status = 'Active' LIMIT 1");
    $stmt->execute([$clientId, $membership['membership_id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    // Determine duration
    $durationDays = intval($membership['duration_days'] ?? 0);
    if ($durationDays <= 0) $durationDays = 30; // fallback

    try {
        $conn->beginTransaction();

        if ($existing) {
            // Extend existing membership by durationDays — preserve remaining days by adding to end_date
            $existingEnd = $existing['end_date'];
            $newEnd = date('Y-m-d', strtotime($existingEnd . " + {$durationDays} days"));

            $stmt = $conn->prepare("UPDATE client_memberships SET end_date = ?, renewal_count = renewal_count + 1, last_renewal_date = NOW() WHERE id = ?");
            $stmt->execute([$newEnd, $existing['id']]);

            $membershipRecordId = $existing['id'];
        } else {
            // Create new client membership record
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime("+{$durationDays} days"));

            $stmt = $conn->prepare("INSERT INTO client_memberships (client_id, membership_id, start_date, end_date, status, is_renewal, renewal_count, created_at) VALUES (?, ?, ?, ?, 'Active', 0, 0, NOW())");
            $stmt->execute([$clientId, $membership['membership_id'], $startDate, $endDate]);
            $membershipRecordId = $conn->lastInsertId();
        }

        // Record unified payment
        $stmt = $conn->prepare("INSERT INTO unified_payments (client_id, payment_type, reference_id, payment_date, amount, payment_method, payment_status, remarks, created_by, created_at) VALUES (?, 'Membership', ?, CURDATE(), ?, ?, 'Paid', ?, ?, NOW())");
        $remarks = 'Membership ' . ($transactionType === 'Membership Renewal' ? 'Renewal' : 'Purchase') . ' - ' . $membership['plan_name'];
        $stmt->execute([$clientId, $membershipRecordId, $amount, $paymentMethod, $remarks, $employeeId]);
        $paymentId = $conn->lastInsertId();

        // Update client_memberships to reference payment if column exists (payment_id)
        $cols = $conn->query("SHOW COLUMNS FROM client_memberships LIKE 'payment_id'")->fetch(PDO::FETCH_ASSOC);
        if ($cols) {
            $stmt = $conn->prepare("UPDATE client_memberships SET payment_id = ? WHERE id = ?");
            $stmt->execute([$paymentId, $membershipRecordId]);
        }

        // Add POS transaction for this membership sale
        $stmt = $conn->prepare("SELECT session_id FROM pos_sessions WHERE employee_id = ? AND status = 'Open'");
        $stmt->execute([$employeeId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            throw new Exception('No active POS session. Please start a session first.');
        }
        $sessionId = $session['session_id'];
        $receiptNumber = generateReceiptNumber($conn, $sessionId);

        // description will include membership plan and client name
        $clientName = null;
        $cstmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM clients WHERE client_id = ?");
        $cstmt->execute([$clientId]);
        $cinfo = $cstmt->fetch(PDO::FETCH_ASSOC);
        if ($cinfo) $clientName = $cinfo['name'];

        $desc = $membership['plan_name'] . ' - ' . ($transactionType === 'Membership Renewal' ? 'Renewal' : 'Purchase');

        $stmt = $conn->prepare("INSERT INTO pos_transactions (session_id, employee_id, employee_name, client_id, client_name, transaction_type, description, amount, payment_method, reference_id, reference_type, transaction_date, transaction_time, receipt_number, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, 'Completed')");
        $stmt->execute([$sessionId, $employeeId, $employeeName, $clientId, $clientName, $transactionType, $desc, $amount, $paymentMethod, $paymentId, 'Membership', $receiptNumber]);

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Membership processed successfully', 'membership_id' => $membershipRecordId, 'payment_id' => $paymentId]);
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

// ===================== UTILITY FUNCTIONS =====================

function generateReceiptNumber($conn, $sessionId) {
    $date = date('Ymd');
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM pos_transactions
        WHERE session_id = ?
    ");
    $stmt->execute([$sessionId]);
    $result = $stmt->fetch();
    $count = intval($result['count']) + 1;

    return sprintf("RCP-%s-%05d", $date, $count);
}

function updateDailyReport($conn) {
    $today = date('Y-m-d');

    // Check if report exists
    $stmt = $conn->prepare("SELECT report_id FROM pos_daily_reports WHERE report_date = ?");
    $stmt->execute([$today]);

    if ($stmt->rowCount() == 0) {
        // Create new report
        $stmt = $conn->prepare("INSERT INTO pos_daily_reports (report_date) VALUES (?)");
        $stmt->execute([$today]);
    }

    // Update report with latest data from transactions
    $stmt = $conn->prepare("
        UPDATE pos_daily_reports
        SET
            total_sales = (SELECT SUM(amount) FROM pos_transactions WHERE DATE(transaction_date) = ?),
            total_transactions = (SELECT COUNT(*) FROM pos_transactions WHERE DATE(transaction_date) = ?),
            cash_transactions_count = (SELECT COUNT(*) FROM pos_transactions WHERE DATE(transaction_date) = ? AND payment_method = 'Cash'),
            cash_total = (SELECT SUM(amount) FROM pos_transactions WHERE DATE(transaction_date) = ? AND payment_method = 'Cash'),
            gcash_total = (SELECT SUM(amount) FROM pos_transactions WHERE DATE(transaction_date) = ? AND payment_method = 'GCash'),
            bank_transfer_bdo_total = (SELECT SUM(amount) FROM pos_transactions WHERE DATE(transaction_date) = ? AND payment_method = 'Bank Transfer - BDO'),
            bank_transfer_bpi_total = (SELECT SUM(amount) FROM pos_transactions WHERE DATE(transaction_date) = ? AND payment_method = 'Bank Transfer - BPI'),
            bank_transfer_other_total = (SELECT SUM(amount) FROM pos_transactions WHERE DATE(transaction_date) = ? AND payment_method = 'Bank Transfer - Other'),
            credit_card_total = (SELECT SUM(amount) FROM pos_transactions WHERE DATE(transaction_date) = ? AND payment_method = 'Credit Card'),
            debit_card_total = (SELECT SUM(amount) FROM pos_transactions WHERE DATE(transaction_date) = ? AND payment_method = 'Debit Card'),
            over_the_counter_total = (SELECT SUM(amount) FROM pos_transactions WHERE DATE(transaction_date) = ? AND payment_method = 'Over the Counter'),
            mobile_payment_total = (SELECT SUM(amount) FROM pos_transactions WHERE DATE(transaction_date) = ? AND payment_method = 'Mobile Payment'),
            total_sessions = (SELECT COUNT(DISTINCT session_id) FROM pos_transactions WHERE DATE(transaction_date) = ?)
        WHERE report_date = ?
    ");
    $stmt->execute([$today, $today, $today, $today, $today, $today, $today, $today, $today, $today, $today, $today, $today]);
}

function handleSaveReport($conn, $employeeId, $employeeName) {
    $sessionId = $_POST['session_id'] ?? null;
    
    if (!$sessionId) {
        throw new Exception('Session ID is required');
    }

    // Get session data
    $stmt = $conn->prepare("
        SELECT 
            ps.session_id,
            ps.employee_id,
            ps.employee_name,
            ps.opening_balance,
            ps.closing_balance,
            ps.total_sales,
            ps.total_cash,
            ps.total_digital,
            ps.start_time,
            ps.end_time,
            ps.notes,
            COUNT(pt.transaction_id) as transaction_count
        FROM pos_sessions ps
        LEFT JOIN pos_transactions pt ON ps.session_id = pt.session_id
        WHERE ps.session_id = ?
        GROUP BY ps.session_id
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        throw new Exception('Session not found');
    }

    // Get all transactions for this session
    $stmt = $conn->prepare("
        SELECT 
            transaction_type,
            payment_method,
            amount
        FROM pos_transactions
        WHERE session_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$sessionId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate breakdown
    $breakdown = [
        'by_type' => [],
        'by_payment' => []
    ];

    foreach ($transactions as $trans) {
        // By type
        if (!isset($breakdown['by_type'][$trans['transaction_type']])) {
            $breakdown['by_type'][$trans['transaction_type']] = 0;
        }
        $breakdown['by_type'][$trans['transaction_type']] += (float)$trans['amount'];

        // By payment method
        if (!isset($breakdown['by_payment'][$trans['payment_method']])) {
            $breakdown['by_payment'][$trans['payment_method']] = 0;
        }
        $breakdown['by_payment'][$trans['payment_method']] += (float)$trans['amount'];
    }

    // Generate report data
    $reportData = [
        'report_id' => 'REPORT-' . date('YmdHis'),
        'session_id' => $session['session_id'],
        'receptionist_name' => $session['employee_name'],
        'receptionist_id' => $session['employee_id'],
        'report_date' => date('Y-m-d'),
        'report_time' => date('H:i:s'),
        'session_start' => $session['start_time'],
        'session_end' => $session['end_time'],
        'opening_balance' => (float)$session['opening_balance'],
        'closing_balance' => (float)$session['closing_balance'],
        'total_sales' => (float)$session['total_sales'],
        'total_cash' => (float)$session['total_cash'],
        'total_digital' => (float)$session['total_digital'],
        'transaction_count' => (int)$session['transaction_count'],
        'breakdown_by_type' => $breakdown['by_type'],
        'breakdown_by_payment' => $breakdown['by_payment'],
        'notes' => $session['notes']
    ];

    // Return report data
    echo json_encode([
        'success' => true,
        'message' => 'Report generated successfully',
        'report' => $reportData
    ]);
}

function handleGetSessionReports($conn) {
    $date = $_GET['date'] ?? date('Y-m-d');
    $employeeId = $_GET['employee_id'] ?? null;

    $query = "
        SELECT 
            session_id,
            employee_id,
            employee_name,
            opening_balance,
            closing_balance,
            total_sales,
            total_cash,
            total_digital,
            transaction_count,
            start_time,
            end_time,
            notes
        FROM pos_sessions
        WHERE DATE(start_time) = ?
    ";
    
    $params = [$date];
    
    if ($employeeId) {
        $query .= " AND employee_id = ?";
        $params[] = $employeeId;
    }
    
    $query .= " ORDER BY start_time DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'sessions' => $sessions,
        'count' => count($sessions)
    ]);
}

function handleGetReceptionists($conn) {
    $stmt = $conn->prepare("
        SELECT DISTINCT employee_id, employee_name
        FROM pos_sessions
        WHERE status = 'Closed'
        ORDER BY employee_name
    ");
    $stmt->execute();
    $receptionists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'receptionists' => $receptionists
    ]);
}

function handleGetLastSessions($conn) {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        
        // Get closed sessions only
        $sql = "SELECT 
                    session_id,
                    employee_id,
                    employee_name,
                    opening_balance,
                    closing_balance,
                    total_sales,
                    total_cash,
                    total_digital,
                    start_time,
                    end_time,
                    status
                FROM pos_sessions
                WHERE status = 'Closed' AND end_time IS NOT NULL
                ORDER BY end_time DESC
                LIMIT :limit";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        
        foreach ($sessions as $session) {
            $closing = (float)($session['closing_balance'] ?? 0);
            $opening = (float)($session['opening_balance'] ?? 0);
            $cash = (float)($session['total_cash'] ?? 0);
            
            $result[] = [
                'session_id' => (int)$session['session_id'],
                'employee_name' => $session['employee_name'] ?? 'Unknown',
                'opening_balance' => $opening,
                'closing_balance' => $closing,
                'balance_diff' => $closing - $opening,
                'total_sales' => (float)($session['total_sales'] ?? 0),
                'total_cash' => $cash,
                'total_digital' => (float)($session['total_digital'] ?? 0),
                'start_time' => $session['start_time'],
                'end_time' => $session['end_time'],
                'expected_cash' => $opening + $cash
            ];
        }

        echo json_encode([
            'success' => true,
            'sessions' => $result,
            'count' => count($result)
        ]);
        
    } catch (Exception $e) {
        error_log("handleGetLastSessions Error: " . $e->getMessage());
        error_log("Stack: " . $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function handleGetRecentSessions($conn) {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        // Get recent sessions - only columns that definitely exist
        $sql = "SELECT 
                    session_id,
                    employee_id,
                    employee_name,
                    opening_balance,
                    closing_balance,
                    total_sales,
                    total_cash,
                    total_digital,
                    start_time,
                    end_time,
                    status
                FROM pos_sessions
                ORDER BY start_time DESC
                LIMIT :limit";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$sessions) {
            echo json_encode([
                'success' => true,
                'sessions' => [],
                'count' => 0
            ]);
            return;
        }

        $sessionsWithData = [];
        
        foreach ($sessions as $session) {
            $sessionId = (int)$session['session_id'];
            
            // Get payment breakdown from transactions
            $paymentSql = "SELECT 
                            payment_method,
                            COUNT(*) as transaction_count,
                            SUM(amount) as total_amount
                        FROM pos_transactions
                        WHERE session_id = :session_id
                        GROUP BY payment_method";
            
            $paymentStmt = $conn->prepare($paymentSql);
            $paymentStmt->bindParam(':session_id', $sessionId, PDO::PARAM_INT);
            $paymentStmt->execute();
            $paymentBreakdown = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize all payment totals
            $paymentTotals = [
                'cash_total' => 0.00,
                'gcash_total' => 0.00,
                'bank_transfer_bdo_total' => 0.00,
                'bank_transfer_bpi_total' => 0.00,
                'bank_transfer_other_total' => 0.00,
                'credit_card_total' => 0.00,
                'debit_card_total' => 0.00,
                'over_the_counter_total' => 0.00,
                'mobile_payment_total' => 0.00
            ];
            
            $transactionCount = 0;
            
            // Map payments from pos_transactions
            foreach ($paymentBreakdown as $payment) {
                $method = strtoupper(trim($payment['payment_method']));
                $amount = (float)$payment['total_amount'];
                $transactionCount += (int)$payment['transaction_count'];
                
                if ($method === 'CASH') {
                    $paymentTotals['cash_total'] = $amount;
                } elseif ($method === 'GCASH') {
                    $paymentTotals['gcash_total'] = $amount;
                } elseif (strpos($method, 'BDO') !== false) {
                    $paymentTotals['bank_transfer_bdo_total'] = $amount;
                } elseif (strpos($method, 'BPI') !== false) {
                    $paymentTotals['bank_transfer_bpi_total'] = $amount;
                } elseif (strpos($method, 'OTHER') !== false) {
                    $paymentTotals['bank_transfer_other_total'] = $amount;
                } elseif (strpos($method, 'CREDIT') !== false) {
                    $paymentTotals['credit_card_total'] = $amount;
                } elseif (strpos($method, 'DEBIT') !== false) {
                    $paymentTotals['debit_card_total'] = $amount;
                } elseif (strpos($method, 'COUNTER') !== false) {
                    $paymentTotals['over_the_counter_total'] = $amount;
                } elseif (strpos($method, 'MOBILE') !== false) {
                    $paymentTotals['mobile_payment_total'] = $amount;
                }
            }
            
            $sessionsWithData[] = [
                'session_id' => $sessionId,
                'employee_name' => $session['employee_name'] ?? 'Unknown',
                'opening_balance' => (float)($session['opening_balance'] ?? 0),
                'closing_balance' => (float)($session['closing_balance'] ?? 0),
                'total_sales' => (float)($session['total_sales'] ?? 0),
                'cash_total' => $paymentTotals['cash_total'],
                'gcash_total' => $paymentTotals['gcash_total'],
                'bank_transfer_bdo_total' => $paymentTotals['bank_transfer_bdo_total'],
                'bank_transfer_bpi_total' => $paymentTotals['bank_transfer_bpi_total'],
                'bank_transfer_other_total' => $paymentTotals['bank_transfer_other_total'],
                'credit_card_total' => $paymentTotals['credit_card_total'],
                'debit_card_total' => $paymentTotals['debit_card_total'],
                'over_the_counter_total' => $paymentTotals['over_the_counter_total'],
                'mobile_payment_total' => $paymentTotals['mobile_payment_total'],
                'transaction_count' => $transactionCount,
                'start_time' => $session['start_time'],
                'end_time' => $session['end_time'] ?? null,
                'status' => $session['status'] ?? 'Open'
            ];
        }

        echo json_encode([
            'success' => true,
            'sessions' => $sessionsWithData,
            'count' => count($sessionsWithData)
        ]);
        
    } catch (Exception $e) {
        error_log("handleGetRecentSessions Error: " . $e->getMessage());
        error_log("Stack: " . $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>
