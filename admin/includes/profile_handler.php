<?php
ob_start();
session_start();

if (!isset($_SESSION['employee_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

require_once '../../config/connection.php';

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
            case 'update_profile':
                // Validate required fields
                if (empty($_POST['first_name']) || empty($_POST['last_name']) || 
                    empty($_POST['email']) || empty($_POST['phone'])) {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
                    exit;
                }
                
                // Validate phone number
                if (!preg_match('/^[0-9]{11}$/', $_POST['phone'])) {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'Contact number must be exactly 11 digits']);
                    exit;
                }
                
                // Validate emergency phone if provided
                if (!empty($_POST['emergency_phone']) && !preg_match('/^[0-9]{11}$/', $_POST['emergency_phone'])) {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'Emergency contact number must be exactly 11 digits']);
                    exit;
                }
                
                $stmt = $conn->prepare("
                    UPDATE employees SET 
                        first_name = ?,
                        middle_name = ?,
                        last_name = ?,
                        phone = ?,
                        email = ?,
                        address = ?,
                        emergency_contact = ?,
                        emergency_phone = ?
                    WHERE employee_id = ?
                ");
                
                $result = $stmt->execute([
                    $_POST['first_name'],
                    $_POST['middle_name'] ?: null,
                    $_POST['last_name'],
                    $_POST['phone'],
                    $_POST['email'],
                    $_POST['address'] ?: null,
                    $_POST['emergency_contact'] ?: null,
                    $_POST['emergency_phone'] ?: null,
                    $_SESSION['employee_id']
                ]);
                
                if ($result) {
                    // Update session name
                    $_SESSION['employee_name'] = $_POST['first_name'] . ' ' . $_POST['last_name'];
                    
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Profile updated successfully!'
                    ]);
                } else {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'Error updating profile']);
                }
                exit;
                
            case 'change_password':
                if (empty($_POST['current_password']) || empty($_POST['new_password'])) {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'All fields are required']);
                    exit;
                }
                
                // Get current password hash
                $stmt = $conn->prepare("SELECT password_hash FROM employees WHERE employee_id = ?");
                $stmt->execute([$_SESSION['employee_id']]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$employee) {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'Employee not found']);
                    exit;
                }
                
                // Verify current password
                if (!password_verify($_POST['current_password'], $employee['password_hash'])) {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                    exit;
                }
                
                // Hash new password
                $newPasswordHash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                
                // Update password
                $stmt = $conn->prepare("UPDATE employees SET password_hash = ? WHERE employee_id = ?");
                $result = $stmt->execute([$newPasswordHash, $_SESSION['employee_id']]);
                
                if ($result) {
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Password changed successfully!'
                    ]);
                } else {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'Error changing password']);
                }
                exit;
                
            default:
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
        
    } catch (PDOException $e) {
        ob_end_clean();
        if ($e->getCode() == 23000) {
            if (strpos($e->getMessage(), 'email') !== false) {
                echo json_encode(['success' => false, 'message' => 'Email already exists!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Duplicate entry found!']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
?>