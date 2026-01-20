<?php
// Start output buffering to catch any errors
ob_start();

session_start();

// Clear any output that might have been generated
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Receptionist') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get filters
$dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
$dateTo   = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;
$type     = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : null;
$method   = isset($_GET['method']) && $_GET['method'] !== '' ? $_GET['method'] : null;
$status   = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
$search   = isset($_GET['search']) && $_GET['search'] !== '' ? $_GET['search'] : null;

try {
    // FIXED: Correct path - go up TWO levels from receptionist/includes/
    require_once '../../config/connection.php';
    $conn = getDBConnection();
    
    // Build query
    $sql = "
        SELECT
            up.payment_id,
            up.client_id,
            up.payment_type,
            up.payment_date,
            up.payment_method,
            up.amount,
            up.payment_status,
            up.remarks,
            up.reference_id
        FROM unified_payments up
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply filters
    if ($dateFrom !== null) {
        $sql .= " AND up.payment_date >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo !== null) {
        $sql .= " AND up.payment_date <= ?";
        $params[] = $dateTo;
    }
    
    if ($type !== null) {
        $sql .= " AND up.payment_type = ?";
        $params[] = $type;
    }
    
    if ($method !== null) {
        $sql .= " AND up.payment_method = ?";
        $params[] = $method;
    }
    
    if ($status !== null) {
        $sql .= " AND up.payment_status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY up.payment_date DESC, up.payment_id DESC LIMIT 500";
    
    // Execute query
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process results
    $responseRows = [];
    $totalCollected = 0;
    $todayCollected = 0;
    $todayCount = 0;
    $today = date('Y-m-d');
    
    foreach ($rows as $row) {
        // Get client name
        $clientName = 'Guest/Walk-in';
        if ($row['client_id']) {
            try {
                $clientStmt = $conn->prepare("SELECT first_name, last_name FROM clients WHERE client_id = ?");
                $clientStmt->execute([$row['client_id']]);
                $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
                if ($client) {
                    $clientName = trim($client['first_name'] . ' ' . $client['last_name']);
                }
            } catch (Exception $e) {
                // If client lookup fails, use default name
            }
        }
        
        // Get detailed item name based on payment type
        $itemName = '';
        try {
            switch ($row['payment_type']) {
                case 'Membership':
                    $membershipStmt = $conn->prepare("
                        SELECT m.plan_name 
                        FROM client_memberships cm
                        JOIN memberships m ON cm.membership_id = m.membership_id
                        WHERE cm.id = ?
                        LIMIT 1
                    ");
                    $membershipStmt->execute([$row['reference_id']]);
                    $membership = $membershipStmt->fetch(PDO::FETCH_ASSOC);
                    $itemName = $membership ? 'Membership: ' . $membership['plan_name'] : 'Membership Payment';
                    break;
                    
                case 'Monthly':
                    $monthlyStmt = $conn->prepare("
                        SELECT m.plan_name 
                        FROM client_memberships cm
                        JOIN memberships m ON cm.membership_id = m.membership_id
                        WHERE cm.id = ?
                        LIMIT 1
                    ");
                    $monthlyStmt->execute([$row['reference_id']]);
                    $monthly = $monthlyStmt->fetch(PDO::FETCH_ASSOC);
                    $itemName = $monthly ? 'Monthly: ' . $monthly['plan_name'] : 'Monthly Payment';
                    break;
                    
                case 'Daily':
                    $itemName = 'Walk-in / Guest Entry';
                    break;
                    
                case 'Service':
                    $itemName = 'PT Service / Personal Training';
                    break;
                    
                case 'Class':
                    $classStmt = $conn->prepare("
                        SELECT cl.class_name 
                        FROM class_bookings cb
                        JOIN class_schedules cs ON cb.schedule_id = cs.schedule_id
                        JOIN classes cl ON cs.class_id = cl.class_id
                        WHERE cb.booking_id = ?
                        LIMIT 1
                    ");
                    $classStmt->execute([$row['reference_id']]);
                    $class = $classStmt->fetch(PDO::FETCH_ASSOC);
                    $itemName = $class ? 'Class: ' . $class['class_name'] : 'Class Booking';
                    break;
                    
                default:
                    $itemName = $row['payment_type'] . ' Payment';
                    break;
            }
        } catch (Exception $e) {
            $itemName = $row['payment_type'] . ' Payment';
        }
        
        // Look for payment proof
        $proofUrl = null;
        if ($row['client_id']) {
            try {
                $proofStmt = $conn->prepare("
                    SELECT payment_proof 
                    FROM pendingregistrations 
                    WHERE client_id = ? 
                    AND payment_proof IS NOT NULL
                    ORDER BY submitted_at DESC
                    LIMIT 1
                ");
                $proofStmt->execute([$row['client_id']]);
                $proof = $proofStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($proof && !empty($proof['payment_proof'])) {
                    $proofUrl = "../uploads/payment_proofs/" . $proof['payment_proof'];
                }
            } catch (Exception $e) {
                // Proof lookup failed, continue without it
            }
        }
        
        // Calculate totals
        $amount = floatval($row['amount']);
        if ($row['payment_status'] === 'Paid') {
            $totalCollected += $amount;
            
            if ($row['payment_date'] === $today) {
                $todayCollected += $amount;
                $todayCount++;
            }
        }
        
        // Apply search filter if provided
        if ($search !== null) {
            $matchFound = false;
            
            if (stripos($clientName, $search) !== false) $matchFound = true;
            if (stripos('REF-' . $row['payment_id'], $search) !== false) $matchFound = true;
            if (stripos($itemName, $search) !== false) $matchFound = true;
            
            if (!$matchFound) continue;
        }
        
        // Build response row
        $responseRows[] = [
            'paymentid' => $row['payment_id'],
            'clientid' => $row['client_id'],
            'clientname' => $clientName,
            'itemname' => $itemName,
            'paymenttype' => $row['payment_type'],
            'paymentdate' => $row['payment_date'],
            'paymentmethod' => $row['payment_method'],
            'amount' => $row['amount'],
            'paymentstatus' => $row['payment_status'],
            'referenceid' => 'REF-' . $row['payment_id'],
            'remarks' => $row['remarks'],
            'proof_url' => $proofUrl
        ];
    }
    
    // Send response
    echo json_encode([
        'success' => true,
        'data' => $responseRows,
        'total_collected' => number_format($totalCollected, 2, '.', ''),
        'today_collected' => number_format($todayCollected, 2, '.', ''),
        'today_count' => $todayCount
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>