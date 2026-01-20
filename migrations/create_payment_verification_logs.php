<?php
/**
 * Migration: Create payment_verification_logs table
 * This table tracks membership payment verifications by managers
 */

require_once '../config/connection.php';

try {
    $conn = getDBConnection();
    
    // Check if table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'payment_verification_logs'")->rowCount() > 0;
    
    if ($tableExists) {
        echo "Table 'payment_verification_logs' already exists.\n";
    } else {
        // Create the table
        $sql = "CREATE TABLE IF NOT EXISTS `payment_verification_logs` (
            `log_id` INT AUTO_INCREMENT PRIMARY KEY,
            `payment_id` INT NOT NULL,
            `verified_by` INT NOT NULL,
            `verification_status` ENUM('Approved', 'Rejected') NOT NULL,
            `remarks` TEXT,
            `verified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`payment_id`) REFERENCES `unified_payments`(`payment_id`) ON DELETE CASCADE,
            FOREIGN KEY (`verified_by`) REFERENCES `employees`(`employee_id`) ON DELETE RESTRICT,
            INDEX `idx_payment` (`payment_id`),
            INDEX `idx_verified_by` (`verified_by`),
            INDEX `idx_verified_at` (`verified_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $conn->exec($sql);
        echo "✓ Table 'payment_verification_logs' created successfully!\n";
        
        // Verify table creation
        $checkTable = $conn->query("SHOW TABLES LIKE 'payment_verification_logs'")->rowCount();
        if ($checkTable > 0) {
            echo "✓ Table verification successful!\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
