<?php
/**
 * Empire Fitness - Database Connection Configuration
 * 
 * This file handles the database connection for the Empire Fitness application.
 * It uses PDO (PHP Data Objects) for secure database operations.
 */

// Database configuration constants
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'empire_fitness');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get database connection
 * 
 * @return PDO Database connection object
 * @throws PDOException if connection fails
 */
function getDBConnection() {
    static $conn = null;
    
    // Return existing connection if already established
    if ($conn !== null) {
        return $conn;
    }
    
    try {
        // Create DSN (Data Source Name)
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        
        // PDO options for better security and error handling
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5
        );
        
        // Create new PDO connection
        $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        return $conn;
        
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
        $errorCode = $e->getCode();
        
        // Display error page
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Error</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .error-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 {
            color: #d41c1c;
            margin: 10px 0 20px;
        }
        .error-details {
            background: #ffebee;
            border-left: 4px solid #d41c1c;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        .debug-info {
            background: #fff3cd;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            text-align: left;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>Database Connection Failed</h1>
        <p>Unable to connect to the database.</p>
        <div class="error-details">
            <strong>Error:</strong> ' . htmlspecialchars($errorMsg) . '<br>
            <strong>Code:</strong> ' . htmlspecialchars($errorCode) . '
        </div>
        <div class="debug-info">
            <p><strong>Host:</strong> ' . htmlspecialchars(DB_HOST) . '</p>
            <p><strong>Port:</strong> ' . htmlspecialchars(DB_PORT) . '</p>
            <p><strong>Database:</strong> ' . htmlspecialchars(DB_NAME) . '</p>
            <p><strong>User:</strong> ' . htmlspecialchars(DB_USER) . '</p>
            <p><strong>PHP Version:</strong> ' . phpversion() . '</p>
        </div>
    </div>
</body>
</html>';
        exit;
    }
}

/**
 * Test database connection
 */
function testConnection() {
    try {
        $conn = getDBConnection();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

?>
