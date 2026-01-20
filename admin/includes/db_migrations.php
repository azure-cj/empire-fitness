<?php
/**
 * Database Migration Script - Add receptionist_id to attendance_log
 * This script safely adds the receptionist_id column if it doesn't exist
 */

header('Content-Type: application/json');
session_start();

// Check if user is admin
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/connection.php';

try {
    $conn = getDBConnection();
    
    // Check if receptionist_id column already exists
    $checkSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_NAME = 'attendance_log' AND COLUMN_NAME = 'receptionist_id'";
    $result = $conn->query($checkSql);
    
    if ($result->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Column receptionist_id already exists']);
        exit;
    }
    
    // Add the column if it doesn't exist
    $alterSql = "ALTER TABLE attendance_log 
                 ADD COLUMN receptionist_id INT NULL 
                 AFTER entry_method";
    
    $conn->exec($alterSql);
    
    // Add foreign key constraint
    $fkSql = "ALTER TABLE attendance_log 
              ADD CONSTRAINT fk_attendance_receptionist 
              FOREIGN KEY (receptionist_id) REFERENCES employees(employee_id) 
              ON DELETE SET NULL ON UPDATE CASCADE";
    
    try {
        $conn->exec($fkSql);
        $fkAdded = true;
    } catch (Exception $e) {
        $fkAdded = false;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Column receptionist_id added successfully',
        'foreign_key_added' => $fkAdded
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
