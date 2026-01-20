<?php
/**
 * Email Verification Table Migration Script
 * 
 * This script creates the email_verifications table required for the email verification system.
 * Run this once to set up the database table.
 */

require_once __DIR__ . '/config/connection.php';

try {
    $pdo = getDBConnection();
    
    // Read the migration SQL file
    $migrationSQL = file_get_contents(__DIR__ . '/migrations/001_create_email_verifications_table.sql');
    
    if (!$migrationSQL) {
        throw new Exception('Could not read migration file');
    }
    
    // Execute the migration
    $pdo->exec($migrationSQL);
    
    echo "✅ SUCCESS: email_verifications table created successfully!\n\n";
    echo "Table Details:\n";
    echo "- Table Name: email_verifications\n";
    echo "- Columns: 8 (verification_id, registration_id, email, verification_token, expires_at, verified_at, created_at, is_verified)\n";
    echo "- Indexes: 3 (idx_token, idx_email, idx_registration)\n";
    echo "- Foreign Key: registration_id → pending_registrations.registration_id\n\n";
    
    // Verify the table was created
    $stmt = $pdo->query("DESCRIBE email_verifications");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Table Structure:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-20s | %-15s | %-20s | %s\n", "Field", "Type", "Null", "Key");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($columns as $col) {
        printf("%-20s | %-15s | %-20s | %s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null'] === 'YES' ? 'YES' : 'NO',
            $col['Key'] ?? ''
        );
    }
    echo str_repeat("-", 80) . "\n";
    
    echo "\n✅ Email Verification System Ready!\n";
    echo "The system can now send verification emails and store tokens in the database.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nMake sure:\n";
    echo "1. The database connection is configured in config/connection.php\n";
    echo "2. The migration file exists at migrations/001_create_email_verifications_table.sql\n";
    echo "3. You have permission to create tables in the database\n";
    exit(1);
}
?>
