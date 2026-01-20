<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();

try {
    $client_id = $_POST['client_id'] ?? null;
    
    if (!$client_id) {
        throw new Exception('Client ID required');
    }
    
    // Check if QR code already exists
    $checkSql = "SELECT qr_code_hash FROM member_qr_codes WHERE client_id = :client_id AND is_active = 1";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute(['client_id' => $client_id]);
    
    if ($checkStmt->rowCount() > 0) {
        $existing = $checkStmt->fetch();
        echo json_encode([
            'success' => true,
            'qr_code_hash' => $existing['qr_code_hash'],
            'message' => 'QR code already exists'
        ]);
        exit;
    }
    
    // Get member info
    $memberSql = "SELECT email FROM clients WHERE client_id = :client_id";
    $memberStmt = $conn->prepare($memberSql);
    $memberStmt->execute(['client_id' => $client_id]);
    $member = $memberStmt->fetch();
    
    if (!$member) {
        throw new Exception('Member not found');
    }
    
    // Generate unique QR code hash
    $qrCodeHash = 'EF-' . str_pad($client_id, 6, '0', STR_PAD_LEFT) . '-' . 
                  md5($client_id . $member['email'] . time());
    
    // Insert QR code
    $insertSql = "INSERT INTO member_qr_codes (client_id, qr_code_hash, is_active) 
                 VALUES (:client_id, :qr_hash, 1)";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->execute([
        'client_id' => $client_id,
        'qr_hash' => $qrCodeHash
    ]);
    
    echo json_encode([
        'success' => true,
        'qr_code_hash' => $qrCodeHash,
        'message' => 'QR code generated successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
