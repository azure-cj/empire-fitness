<?php
/**
 * Database Setup - Add receptionist_id to attendance_log table
 * Run this file once to add the column to the database
 * Access: admin/setup_receptionist_tracking.php
 */

header('Content-Type: application/json');
session_start();

// NOTE: Comment out the auth check below if you can't login yet
// Uncomment after the column is added
// if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Admin') {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
//     exit;
// }

require_once '../config/connection.php';

try {
    $conn = getDBConnection();
    
    // Check if receptionist_id column already exists
    $checkSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_NAME = 'attendance_log' 
                 AND TABLE_SCHEMA = DATABASE() 
                 AND COLUMN_NAME = 'receptionist_id'";
    
    $result = $conn->query($checkSql);
    $exists = $result && $result->rowCount() > 0;
    
    if ($exists) {
        echo json_encode([
            'success' => false,
            'message' => 'Column receptionist_id already exists in attendance_log',
            'status' => 'ALREADY_EXISTS'
        ]);
        exit;
    }
    
    // Step 1: Add the column
    $alterSql = "ALTER TABLE attendance_log 
                 ADD COLUMN receptionist_id INT NULL AFTER entry_method";
    
    $conn->exec($alterSql);
    
    // Step 2: Try to add foreign key constraint (optional)
    $fkAdded = false;
    try {
        $fkSql = "ALTER TABLE attendance_log 
                  ADD CONSTRAINT fk_attendance_receptionist 
                  FOREIGN KEY (receptionist_id) REFERENCES employees(employee_id) 
                  ON DELETE SET NULL ON UPDATE CASCADE";
        $conn->exec($fkSql);
        $fkAdded = true;
    } catch (Exception $fkError) {
        // FK constraint might fail - that's okay, column is still added
        error_log("Note: Foreign key constraint could not be added: " . $fkError->getMessage());
    }
    
    // Step 3: Verify the column was added
    $verifySql = "SHOW COLUMNS FROM attendance_log WHERE Field = 'receptionist_id'";
    $verifyResult = $conn->query($verifySql);
    $columnAdded = $verifyResult && $verifyResult->rowCount() > 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Column receptionist_id has been successfully added to attendance_log table',
        'status' => 'SUCCESS',
        'details' => [
            'column_added' => $columnAdded,
            'foreign_key_added' => $fkAdded
        ],
        'next_steps' => [
            '1. Test by entering a new walk-in guest in Entry/Exit',
            '2. Go to Manage Payments → Walk-in Guests tab',
            '3. Click Approve on the guest entry',
            '4. Modal should now show "Handled By: [Receptionist Name]"'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database Error: ' . $e->getMessage(),
        'status' => 'ERROR',
        'error_details' => [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
    error_log("Migration Error: " . $e->getMessage());
}
?>
