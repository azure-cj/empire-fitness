<?php
/**
 * Payment Handler - receptionist/includes/payment_handler.php
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/connection.php';
$conn = getDBConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_walkin_payments':
            getWalkinPayments($conn);
            break;

        case 'get_walkin_member_payments':
            getWalkinMemberPayments($conn);
            break;

        case 'get_class_payments':
            getClassPayments($conn);
            break;

        case 'get_renewals':
            getMembershipRenewals($conn);
            break;

        case 'process_payment':
            processPayment($conn);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

// Get Walk-in Payments Pending
function getWalkinPayments($conn) {
    try {
        $sql = "SELECT 
                    al.attendance_id,
                    al.guest_name,
                    al.time_in,
                    al.discount_type,
                    CASE 
                        WHEN al.discount_type = 'Student' THEN 80.00
                        WHEN al.discount_type = 'Regular' THEN 100.00
                        ELSE COALESCE(r.price, 100.00)
                    END as amount,
                    COALESCE(al.temp_payment_status, 'pending') as status,
                    COALESCE(CONCAT(e.first_name, ' ', e.last_name), 'Unassigned') as receptionist_name,
                    CASE 
                        WHEN al.check_out_timestamp IS NULL THEN CONCAT(FLOOR(TIMESTAMPDIFF(MINUTE, al.check_in_timestamp, NOW()) / 60), 'h ', MOD(TIMESTAMPDIFF(MINUTE, al.check_in_timestamp, NOW()), 60), 'm')
                        ELSE 'Checked out'
                    END as duration
                FROM attendance_log al
                LEFT JOIN rates r ON al.discount_type = r.rate_name AND r.applies_to = 'Guest'
                LEFT JOIN employees e ON al.receptionist_id = e.employee_id
                WHERE al.log_date = CURDATE() 
                AND al.client_id IS NULL 
                AND al.guest_name IS NOT NULL
                ORDER BY al.check_in_timestamp DESC
                LIMIT 100";

        $stmt = $conn->query($sql);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getWalkinPayments: " . $e->getMessage());
        // Try simpler fallback query if main fails
        try {
            $sql = "SELECT 
                        al.attendance_id,
                        al.guest_name,
                        al.time_in,
                        al.discount_type,
                        100.00 as amount,
                        'pending' as status,
                        'Unassigned' as receptionist_name,
                        '-' as duration
                    FROM attendance_log al
                    WHERE al.log_date = CURDATE() 
                    AND al.client_id IS NULL 
                    AND al.guest_name IS NOT NULL
                    ORDER BY al.time_in DESC
                    LIMIT 100";
            $stmt = $conn->query($sql);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            $payments = [];
        }
    }

    echo json_encode([
        'success' => true,
        'payments' => $payments
    ]);
}

// Get Walk-in Member Payments (Members with walk-in discounted rates)
function getWalkinMemberPayments($conn) {
    try {
        $sql = "SELECT 
                    al.attendance_id,
                    CONCAT(c.first_name, ' ', c.last_name) as member_name,
                    cm.membership_id,
                    m.plan_name as membership_plan,
                    al.check_in_timestamp as time_in,
                    CASE 
                        WHEN al.discount_type = 'Student' THEN 'Student'
                        WHEN al.discount_type = 'Regular' THEN 'Regular'
                        ELSE al.discount_type
                    END as discount_rate,
                    CASE 
                        WHEN al.discount_type = 'Student' THEN 80.00
                        WHEN al.discount_type = 'Regular' THEN 100.00
                        ELSE COALESCE(r.price, 100.00)
                    END as amount,
                    COALESCE(al.temp_payment_status, 'pending') as status,
                    COALESCE(CONCAT(e.first_name, ' ', e.last_name), 'Unassigned') as receptionist_name,
                    al.attendance_id as row_id
                FROM attendance_log al
                LEFT JOIN clients c ON al.client_id = c.client_id
                LEFT JOIN client_memberships cm ON c.client_id = cm.client_id AND cm.status = 'Active'
                LEFT JOIN memberships m ON cm.membership_id = m.membership_id
                LEFT JOIN rates r ON al.discount_type = r.rate_name AND r.applies_to = 'Member'
                LEFT JOIN employees e ON al.receptionist_id = e.employee_id
                WHERE al.log_date = CURDATE() 
                AND al.client_id IS NOT NULL 
                AND al.guest_name IS NULL
                ORDER BY al.check_in_timestamp DESC
                LIMIT 100";

        $stmt = $conn->query($sql);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getWalkinMemberPayments: " . $e->getMessage());
        // Fallback simpler query
        try {
            $sql = "SELECT 
                        al.attendance_id,
                        CONCAT(c.first_name, ' ', c.last_name) as member_name,
                        m.plan_name as membership_plan,
                        al.time_in as time_in,
                        'Regular' as discount_rate,
                        100.00 as amount,
                        'pending' as status,
                        'Unassigned' as receptionist_name
                    FROM attendance_log al
                    LEFT JOIN clients c ON al.client_id = c.client_id
                    LEFT JOIN client_memberships cm ON c.client_id = cm.client_id AND cm.status = 'Active'
                    LEFT JOIN memberships m ON cm.membership_id = m.membership_id
                    WHERE al.log_date = CURDATE() 
                    AND al.client_id IS NOT NULL 
                    ORDER BY al.time_in DESC
                    LIMIT 100";
            $stmt = $conn->query($sql);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            $payments = [];
        }
    }

    echo json_encode([
        'success' => true,
        'payments' => $payments
    ]);
}

// Get Class Payments Pending
function getClassPayments($conn) {
    try {
        $sql = "SELECT 
                    cb.booking_id as request_id,
                    CONCAT(c.first_name, ' ', c.last_name) as member_name,
                    cl.class_name,
                    cs.schedule_date,
                    cs.start_time,
                    CASE 
                        WHEN cb.booking_type = 'Single Session' THEN cl.single_session_price
                        WHEN cb.booking_type = 'Package' THEN cp.price
                        ELSE cl.single_session_price
                    END as amount,
                    cb.payment_status,
                    cb.booking_id
                FROM class_bookings cb
                JOIN clients c ON cb.member_id = c.client_id
                JOIN class_schedules cs ON cb.schedule_id = cs.schedule_id
                JOIN classes cl ON cs.class_id = cl.class_id
                LEFT JOIN class_packages cp ON cb.package_id = cp.package_id
                WHERE cs.schedule_date >= CURDATE()
                AND cb.status = 'Booked'
                ORDER BY cs.schedule_date ASC, cs.start_time ASC
                LIMIT 100";

        $stmt = $conn->query($sql);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getClassPayments: " . $e->getMessage());
        // Fallback simpler query
        try {
            $sql = "SELECT 
                        cb.booking_id as request_id,
                        CONCAT(c.first_name, ' ', c.last_name) as member_name,
                        cl.class_name,
                        cs.schedule_date,
                        cs.start_time,
                        500.00 as amount,
                        'Pending' as payment_status
                    FROM class_bookings cb
                    JOIN clients c ON cb.member_id = c.client_id
                    JOIN class_schedules cs ON cb.schedule_id = cs.schedule_id
                    JOIN classes cl ON cs.class_id = cl.class_id
                    WHERE cs.schedule_date >= CURDATE()
                    ORDER BY cs.schedule_date DESC
                    LIMIT 100";
            $stmt = $conn->query($sql);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            $payments = [];
        }
    }

    echo json_encode([
        'success' => true,
        'payments' => $payments
    ]);
}

// Get Membership Renewals
// Get Membership Renewals
function getMembershipRenewals($conn) {
    try {
        $sql = "SELECT 
                    c.client_id,
                    CONCAT(c.first_name, ' ', c.last_name) as client_name,
                    m.plan_name,
                    m.price as plan_price,
                    cm.end_date,
                    m.price as renewal_amount
                FROM clients c
                JOIN client_memberships cm ON c.client_id = cm.client_id
                JOIN memberships m ON cm.membership_id = m.membership_id
                WHERE cm.status = 'Active'
                AND cm.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                ORDER BY cm.end_date ASC
                LIMIT 100";

        $stmt = $conn->query($sql);
        $renewals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getMembershipRenewals: " . $e->getMessage());
        // Fallback simpler query
        try {
            $sql = "SELECT 
                        c.client_id,
                        CONCAT(c.first_name, ' ', c.last_name) as client_name,
                        m.plan_name,
                        cm.end_date,
                        500.00 as renewal_amount,
                        500.00 as plan_price
                    FROM clients c
                    JOIN client_memberships cm ON c.client_id = cm.client_id
                    JOIN memberships m ON cm.membership_id = m.membership_id
                    WHERE cm.status = 'Active'
                    ORDER BY cm.end_date DESC
                    LIMIT 100";
            $stmt = $conn->query($sql);
            $renewals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            $renewals = [];
        }
    }

    echo json_encode([
        'success' => true,
        'renewals' => $renewals,
        'pending_renewals' => count($renewals)
    ]);
}

// Process Payment
function processPayment($conn) {
    $type = $_POST['type'] ?? '';
    // Accept both attendance_id (from walkin entry/exit) and reference_id (from other sources)
    $referenceId = $_POST['attendance_id'] ?? $_POST['reference_id'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $paymentMethod = $_POST['payment_method'] ?? '';
    $remarks = $_POST['remarks'] ?? $_POST['description'] ?? '';

    if (!$type || !$referenceId || !$amount) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields: type=' . $type . ', ref=' . $referenceId . ', amount=' . $amount]);
        return;
    }

    $conn->beginTransaction();

    try {
        if ($type === 'walkin') {
            processWalkinPayment($conn, $referenceId, $amount, $paymentMethod, $remarks);
        } elseif ($type === 'class') {
            processClassPayment($conn, $referenceId, $amount, $paymentMethod, $remarks);
        } elseif ($type === 'renewal') {
            processMembershipRenewal($conn, $referenceId, $amount, $paymentMethod, $remarks);
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Payment processed successfully'
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// Process Walk-in Payment
function processWalkinPayment($conn, $attendanceId, $amount, $paymentMethod, $remarks) {
    // Get receptionist_id from session
    $receptionist_id = $_SESSION['employee_id'] ?? null;
    
    // Insert into unified_payments with NULL client_id
    $paymentSql = "INSERT INTO unified_payments 
                  (client_id, payment_type, payment_date, amount, payment_method, 
                   payment_status, remarks, created_by, created_at, updated_at) 
                  VALUES (NULL, 'Daily', CURDATE(), :amount, :method, 
                          'Paid', :remarks, :employee_id, NOW(), NOW())";

    $paymentStmt = $conn->prepare($paymentSql);
    $paymentStmt->execute([
        'amount' => $amount,
        'method' => $paymentMethod,
        'remarks' => $remarks,
        'employee_id' => $receptionist_id
    ]);

    $paymentId = $conn->lastInsertId();

    // Update attendance_log with payment_id and receptionist_id
    $updateSql = "UPDATE attendance_log 
                 SET payment_id = :payment_id, temp_payment_status = 'paid', receptionist_id = :receptionist_id
                 WHERE attendance_id = :attendance_id";

    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->execute([
        'payment_id' => $paymentId,
        'receptionist_id' => $receptionist_id,
        'attendance_id' => $attendanceId
    ]);
}

// Process Class Payment
function processClassPayment($conn, $requestId, $amount, $paymentMethod, $remarks) {
    // Insert into unified_payments
    $paymentSql = "INSERT INTO unified_payments 
                  (client_id, payment_type, reference_id, payment_date, amount, payment_method, 
                   payment_status, remarks, created_by, created_at, updated_at) 
                  SELECT br.client_id, 'Class', br.request_id, CURDATE(), :amount, :method, 
                         'Paid', :remarks, :employee_id, NOW(), NOW()
                  FROM booking_requests br
                  WHERE br.request_id = :request_id";

    $paymentStmt = $conn->prepare($paymentSql);
    $paymentStmt->execute([
        'amount' => $amount,
        'method' => $paymentMethod,
        'remarks' => $remarks,
        'employee_id' => $_SESSION['employee_id'],
        'request_id' => $requestId
    ]);

    $paymentId = $conn->lastInsertId();

    // Update booking_requests
    $updateSql = "UPDATE booking_requests 
                 SET status = 'Payment Verified', payment_id = :payment_id
                 WHERE request_id = :request_id";

    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->execute([
        'payment_id' => $paymentId,
        'request_id' => $requestId
    ]);
}

// Process Membership Renewal
function processMembershipRenewal($conn, $clientId, $amount, $paymentMethod, $remarks) {
    // Insert into unified_payments
    $paymentSql = "INSERT INTO unified_payments 
                  (client_id, payment_type, payment_date, amount, payment_method, 
                   payment_status, remarks, created_by, created_at, updated_at) 
                  VALUES (:client_id, 'Membership', CURDATE(), :amount, :method, 
                          'Paid', :remarks, :employee_id, NOW(), NOW())";

    $paymentStmt = $conn->prepare($paymentSql);
    $paymentStmt->execute([
        'client_id' => $clientId,
        'amount' => $amount,
        'method' => $paymentMethod,
        'remarks' => $remarks,
        'employee_id' => $_SESSION['employee_id']
    ]);

    // Update client_memberships to extend date
    $renewalSql = "UPDATE client_memberships 
                  SET end_date = DATE_ADD(end_date, INTERVAL 30 DAY),
                      renewal_count = renewal_count + 1,
                      last_renewal_date = CURDATE()
                  WHERE client_id = :client_id AND status = 'Active'
                  LIMIT 1";

    $renewalStmt = $conn->prepare($renewalSql);
    $renewalStmt->execute(['client_id' => $clientId]);
}
?>