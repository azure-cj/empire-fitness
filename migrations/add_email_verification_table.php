<?php
/**
 * Migration: Add Email Verification Table
 * 
 * Creates a table to track email verification tokens for new member registrations.
 * This allows for secure email verification before sending login credentials.
 */

require_once __DIR__ . '/../config/connection.php';

function addEmailVerificationTable() {
    try {
        $conn = getDBConnection();
        
        // Check if table already exists
        $stmt = $conn->query("SHOW TABLES LIKE 'email_verifications'");
        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Email verification table already exists'
            ];
        }
        
        // Create email_verifications table
        $sql = "
        CREATE TABLE email_verifications (
            verification_id INT AUTO_INCREMENT PRIMARY KEY,
            registration_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            verification_token VARCHAR(255) NOT NULL UNIQUE,
            is_verified BOOLEAN DEFAULT FALSE,
            verified_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            FOREIGN KEY (registration_id) REFERENCES pending_registrations(registration_id) ON DELETE CASCADE,
            INDEX idx_token (verification_token),
            INDEX idx_registration (registration_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $conn->exec($sql);
        
        return [
            'success' => true,
            'message' => 'Email verification table created successfully'
        ];
        
    } catch(Exception $e) {
        return [
            'success' => false,
            'message' => 'Error creating email verification table: ' . $e->getMessage()
        ];
    }
}

// Run migration if accessed directly
if (php_sapi_name() === 'cli' || (isset($_GET['action']) && $_GET['action'] === 'migrate')) {
    $result = addEmailVerificationTable();
    echo json_encode($result);
    exit;
}
?>
