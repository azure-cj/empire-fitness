<?php
/**
 * Password Reset Database Setup
 * This file adds the necessary columns to support password reset functionality
 * Run this once to update your database schema
 */

require_once __DIR__ . '/config/connection.php';

$conn = getDBConnection();

try {
    // Check if reset_token column exists
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'employees' AND COLUMN_NAME = 'reset_token'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo "Adding reset_token column...<br>";
        $conn->exec("ALTER TABLE employees ADD COLUMN reset_token VARCHAR(64) NULL DEFAULT NULL AFTER password_hash");
        echo "✓ reset_token column added<br>";
    } else {
        echo "✓ reset_token column already exists<br>";
    }
    
    // Check if reset_token_expires column exists
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'employees' AND COLUMN_NAME = 'reset_token_expires'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo "Adding reset_token_expires column...<br>";
        $conn->exec("ALTER TABLE employees ADD COLUMN reset_token_expires DATETIME NULL DEFAULT NULL AFTER reset_token");
        echo "✓ reset_token_expires column added<br>";
    } else {
        echo "✓ reset_token_expires column already exists<br>";
    }
    
    echo "<hr>";
    echo "<p style='color: green; font-weight: bold;'>✓ Database setup complete! The password reset functionality is ready to use.</p>";
    echo "<p><a href='index.php'>Go to Login Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #d41c1c;
        }
        a {
            color: #d41c1c;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Password Reset Setup</h1>
        <p>Database configuration in progress...</p>
    </div>
</body>
</html>
