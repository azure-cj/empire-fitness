<?php
/**
 * POS System Setup Script
 * Run this once to create all necessary database tables
 */

require_once __DIR__ . '/config/connection.php';

try {
    $conn = getDBConnection();
    
    // Read the schema file
    $schema = file_get_contents('pos-schema.sql');
    
    // Split by semicolons and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...<br>";
            $conn->exec($statement);
        }
    }
    
    echo "<h2 style='color: green;'>✓ POS System setup completed successfully!</h2>";
    echo "<p>All tables have been created.</p>";
    echo "<p><a href='receptionist/pos.php'>Go to POS System</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ Error setting up POS system</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
