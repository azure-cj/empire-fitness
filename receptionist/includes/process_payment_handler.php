<?php
session_start();

// Check if user is logged in and has receptionist role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Receptionist') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../../config/connection.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($input['attendance_id']) && empty($input['client_name'])) {
        throw new Exception('Client information is required');
    }

    if (empty($input['payment_method'])) {
        throw new Exception('Payment method is required');
    }

    if (empty($input['amount'])) {
        throw new Exception('Amount is required');
    }

    $attendance_id = $input['attendance_id'] ?? null;
    $client_id = $input['client_id'] ?? null;
    $client_name = $input['client_name'] ?? 'Walk-in Guest';
    $payment_method = $input['payment_method'];
    $amount = floatval($input['amount']);
    $reference_number = $input['reference_number'] ?? null;
    $received_amount = $input['received_amount'] ?? null;
    $notes = $input['notes'] ?? '';

    // Validate non-cash payment has reference
    if ($payment_method !== 'Cash' && empty($reference_number)) {
        throw new Exception('Reference number is required for ' . $payment_method);
    }

    // Validate cash payment has received amount
    if ($payment_method === 'Cash' && empty($received_amount)) {
        throw new Exception('Amount received is required for cash payments');
    }

    if ($payment_method === 'Cash' && floatval($received_amount) < $amount) {
        throw new Exception('Amount received must be at least ₱' . number_format($amount, 2));
    }

    // Begin transaction
    $conn->begin_transaction();

    // Insert payment record into a unified payments table (adjust table name as needed)
    $paymentSql = "INSERT INTO unified_payments 
                   (client_id, payment_type, amount, payment_method, 
                    reference_number, payment_status, notes, created_at, updated_at) 
                   VALUES (?, 'Daily Entrance', ?, ?, ?, 'Completed', ?, NOW(), NOW())";
    
    $stmt = $conn->prepare($paymentSql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('idss', $client_id, $amount, $payment_method, $reference_number, $notes);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert payment: ' . $stmt->error);
    }

    $payment_id = $conn->insert_id;

    // If attendance_id provided, update the attendance record
    if ($attendance_id) {
        $updateSql = "UPDATE attendance_log 
                     SET payment_id = ?, temp_payment_status = 'paid', status = 'Completed'
                     WHERE attendance_id = ?";
        
        $updateStmt = $conn->prepare($updateSql);
        if (!$updateStmt) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $updateStmt->bind_param('ii', $payment_id, $attendance_id);
        
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update attendance: ' . $updateStmt->error);
        }

        $updateStmt->close();
    }

    $stmt->close();

    // Commit transaction
    $conn->commit();

    // Return success response with receipt details
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'data' => [
            'payment_id' => $payment_id,
            'receipt_number' => 'RCP' . str_pad($payment_id, 6, '0', STR_PAD_LEFT),
            'client_name' => $client_name,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'timestamp' => date('Y-m-d H:i:s'),
            'change' => $payment_method === 'Cash' ? floatval($received_amount) - $amount : 0
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction if error
    if (isset($conn)) {
        $conn->rollback();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
