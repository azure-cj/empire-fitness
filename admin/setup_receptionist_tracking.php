<?php
/**
 * Database Setup - Add receptionist_id to attendance_log table
 * Run this file once to add the column to the database
 * Access: admin/setup_receptionist_tracking.php
 */

header('Content-Type: application/json');
session_start();

require_once '../config/connection.php';

try {
    $conn = getDBConnection();
    
    // Check if receptionist_id column already exists
    $checkSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_NAME = 'attendance_log' AND TABLE_SCHEMA = 'empire_fitness' AND COLUMN_NAME = 'receptionist_id'";
    
    $result = $conn->query($checkSql);
    $exists = $result && $result->rowCount() > 0;
    
    if ($exists) {
        echo json_encode([
            'success' => false,
            'message' => 'Column receptionist_id already exists'
        ]);
        exit;
    }
    
    // Add the column
    $alterSql = "ALTER TABLE attendance_log 
                 ADD COLUMN receptionist_id INT NULL AFTER entry_method";
    
    $conn->exec($alterSql);
    
    // Try to add foreign key constraint
    $fkAdded = false;
    try {
        $fkSql = "ALTER TABLE attendance_log 
                  ADD CONSTRAINT fk_attendance_receptionist 
                  FOREIGN KEY (receptionist_id) REFERENCES employees(employee_id) 
                  ON DELETE SET NULL ON UPDATE CASCADE";
        $conn->exec($fkSql);
        $fkAdded = true;
    } catch (Exception $fkError) {
        // FK might fail if it already exists, that's okay
    }
    
    // Populate existing records with receptionist_id = 1 (default admin) if needed
    $updateSql = "UPDATE attendance_log 
                  SET receptionist_id = 1 
                  WHERE receptionist_id IS NULL AND guest_name IS NOT NULL AND log_date = CURDATE()";
    
    $conn->exec($updateSql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Column receptionist_id added successfully to attendance_log table',
        'foreign_key_added' => $fkAdded
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
