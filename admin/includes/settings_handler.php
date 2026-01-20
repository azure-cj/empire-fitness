<?php
// Settings Handler - Backend Processing
// Start output buffering to catch any unwanted output
ob_start();

session_start();

// Error handling - don't output errors directly
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON header first
header('Content-Type: application/json');

// Try to include connection file
try {
    $connectionFile = '../../config/connection.php';
    if (!file_exists($connectionFile)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Connection file not found']);
        exit;
    }
    require_once $connectionFile;
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error loading connection']);
    exit;
}

// Check if user is logged in and has admin role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Super Admin', 'Admin'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        $conn = getDBConnection();
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    try {
        switch ($action) {
            case 'change_password':
                // Validate required fields
                if (empty($_POST['current_password']) || empty($_POST['new_password']) || empty($_POST['confirm_password'])) {
                    echo json_encode(['success' => false, 'message' => 'All password fields are required']);
                    exit;
                }
                
                $currentPassword = $_POST['current_password'];
                $newPassword = $_POST['new_password'];
                $confirmPassword = $_POST['confirm_password'];
                $employeeId = $_SESSION['employee_id'];
                
                // Validate password format
                if (strlen($newPassword) < 6) {
                    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
                    exit;
                }
                
                // Check if passwords match
                if ($newPassword !== $confirmPassword) {
                    echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
                    exit;
                }
                
                // Verify current password
                $stmt = $conn->prepare("SELECT password_hash FROM employees WHERE employee_id = ?");
                $stmt->execute([$employeeId]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$employee) {
                    echo json_encode(['success' => false, 'message' => 'Employee not found']);
                    exit;
                }
                
                if (!password_verify($currentPassword, $employee['password_hash'])) {
                    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                    exit;
                }
                
                // Prevent using same password as current
                if (password_verify($newPassword, $employee['password_hash'])) {
                    echo json_encode(['success' => false, 'message' => 'New password cannot be the same as current password']);
                    exit;
                }
                
                // Hash new password
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update password
                $updateStmt = $conn->prepare("UPDATE employees SET password_hash = ?, updated_at = NOW() WHERE employee_id = ?");
                $result = $updateStmt->execute([$newHash, $employeeId]);
                
                if ($result) {
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Password changed successfully! Please use your new password on your next login.'
                    ]);
                } else {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'Error updating password']);
                }
                exit;
            
            default:
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
    } catch (PDOException $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?>
