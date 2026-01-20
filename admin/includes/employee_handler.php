<?php
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
        echo json_encode(['success' => false, 'message' => 'Connection file not found at: ' . $connectionFile]);
        exit;
    }
    require_once $connectionFile;
    
    // Include email functions
    $emailFile = '../../includes/email_functions.php';
    if (file_exists($emailFile)) {
        require_once $emailFile;
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error loading connection: ' . $e->getMessage()]);
    exit;
}

// Check if user is logged in and has admin role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Super Admin', 'Admin'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Function to generate unique employee code
function generateEmployeeCode($conn) {
    $year = date('Y');
    $prefix = 'EMP' . $year;
    
    // Get the last employee code for this year
    $stmt = $conn->prepare("SELECT employee_code FROM employees WHERE employee_code LIKE ? ORDER BY employee_code DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastCode = $stmt->fetchColumn();
    
    if ($lastCode) {
        // Extract the number and increment
        $lastNumber = intval(substr($lastCode, -4));
        $newNumber = $lastNumber + 1;
    } else {
        // First employee of the year
        $newNumber = 1;
    }
    
    // Format: EMP2025-0001
    return $prefix . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        $conn = getDBConnection();
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
    
    try {
        switch ($action) {
            case 'add':
                // Validate required fields
                if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email']) || 
                    empty($_POST['phone']) || empty($_POST['position']) || 
                    empty($_POST['role']) || empty($_POST['status'])) {
                    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
                    exit;
                }
                
                // Validate phone number (11 digits only)
                if (!preg_match('/^[0-9]{11}$/', $_POST['phone'])) {
                    echo json_encode(['success' => false, 'message' => 'Contact number must be exactly 11 digits']);
                    exit;
                }
                
                // Validate emergency phone if provided
                if (!empty($_POST['emergency_phone']) && !preg_match('/^[0-9]{11}$/', $_POST['emergency_phone'])) {
                    echo json_encode(['success' => false, 'message' => 'Emergency contact number must be exactly 11 digits']);
                    exit;
                }
                
                // Generate employee code
                $employeeCode = generateEmployeeCode($conn);
                
                // Generate default password
                $defaultPassword = 'EmpireFit2025!';
                $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("
                    INSERT INTO employees (
                        employee_code, first_name, middle_name, last_name, phone, email, address,
                        position, hire_date, emergency_contact, emergency_phone,
                        password_hash, role, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $employeeCode,
                    $_POST['first_name'],
                    $_POST['middle_name'] ?: null,
                    $_POST['last_name'],
                    $_POST['phone'],
                    $_POST['email'],
                    $_POST['address'] ?: null,
                    $_POST['position'],
                    $_POST['hire_date'] ?: null,
                    $_POST['emergency_contact'] ?: null,
                    $_POST['emergency_phone'] ?: null,
                    $passwordHash,
                    $_POST['role'],
                    $_POST['status']
                ]);
                
                if ($result) {
                    // Send credentials email if email functions are available
                    $emailSent = false;
                    $emailMessage = '';
                    
                    if (function_exists('sendEmployeeCredentialsEmail')) {
                        $emailResult = sendEmployeeCredentialsEmail(
                            $_POST['email'],
                            $_POST['first_name'] . ' ' . $_POST['last_name'],
                            $employeeCode,
                            $defaultPassword,
                            $_POST['position']
                        );
                        
                        if ($emailResult['success']) {
                            $emailSent = true;
                            $emailMessage = ' Employee credentials email sent successfully!';
                        } else {
                            $emailMessage = ' (Note: Email failed to send - ' . $emailResult['message'] . ')';
                        }
                    }
                    
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Employee added successfully!' . $emailMessage,
                        'employee_code' => $employeeCode,
                        'default_password' => $defaultPassword,
                        'email_sent' => $emailSent
                    ]);
                } else {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'Error adding employee']);
                }
                exit;
                
            case 'edit':
                if (!isset($_POST['employee_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
                    exit;
                }
                
                // Validate phone number (11 digits only)
                if (!preg_match('/^[0-9]{11}$/', $_POST['phone'])) {
                    echo json_encode(['success' => false, 'message' => 'Contact number must be exactly 11 digits']);
                    exit;
                }
                
                // Validate emergency phone if provided
                if (!empty($_POST['emergency_phone']) && !preg_match('/^[0-9]{11}$/', $_POST['emergency_phone'])) {
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
                        position = ?, 
                        role = ?,
                        status = ?, 
                        hire_date = ?, 
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
                    $_POST['position'],
                    $_POST['role'],
                    $_POST['status'],
                    $_POST['hire_date'] ?: null,
                    $_POST['emergency_contact'] ?: null,
                    $_POST['emergency_phone'] ?: null,
                    $_POST['employee_id']
                ]);
                
                ob_end_clean();
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Employee updated successfully!' : 'Error updating employee'
                ]);
                exit;
                
            case 'get':
                if (!isset($_POST['employee_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
                    exit;
                }
                
                $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
                $stmt->execute([$_POST['employee_id']]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($employee) {
                    // Don't send password hash to client
                    unset($employee['password_hash']);
                    ob_end_clean();
                    echo json_encode(['success' => true, 'employee' => $employee]);
                } else {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'Employee not found']);
                }
                exit;
                
            case 'delete':
                if (!isset($_POST['employee_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
                    exit;
                }
                
                // Check if trying to delete self
                if ($_POST['employee_id'] == $_SESSION['employee_id']) {
                    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
                    exit;
                }
                
                $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
                $result = $stmt->execute([$_POST['employee_id']]);
                
                ob_end_clean();
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Employee deleted successfully!' : 'Error deleting employee'
                ]);
                exit;
                
            case 'reset_password':
                if (!isset($_POST['employee_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
                    exit;
                }
                
                // Get employee details for email
                $getEmpStmt = $conn->prepare("SELECT first_name, last_name, email, position FROM employees WHERE employee_id = ?");
                $getEmpStmt->execute([$_POST['employee_id']]);
                $employee = $getEmpStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$employee) {
                    echo json_encode(['success' => false, 'message' => 'Employee not found']);
                    exit;
                }
                
                // Generate new default password
                $defaultPassword = 'EmpireFit2025!';
                $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE employees SET password_hash = ? WHERE employee_id = ?");
                $result = $stmt->execute([$passwordHash, $_POST['employee_id']]);
                
                $emailSent = false;
                $emailMessage = '';
                
                // Send password reset email if email functions are available
                if ($result && function_exists('sendEmail')) {
                    $resetEmailBody = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                            .credentials { background: white; padding: 20px; border-left: 4px solid #dc2626; margin: 20px 0; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>Password Reset</h1>
                            </div>
                            <div class='content'>
                                <h2>Hello " . htmlspecialchars($employee['first_name']) . ",</h2>
                                <p>Your password has been reset by an administrator at Empire Fitness.</p>
                                
                                <div class='credentials'>
                                    <h3>Your New Temporary Password:</h3>
                                    <p><code>" . htmlspecialchars($defaultPassword) . "</code></p>
                                </div>
                                
                                <p><strong>⚠️ Important:</strong> Please change this password immediately after your next login for security purposes.</p>
                                <p>Login at: <a href='http://localhost/empirefitness'>http://localhost/empirefitness</a></p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    $emailResult = sendEmail(
                        $employee['email'],
                        'Password Reset - Empire Fitness',
                        $resetEmailBody
                    );
                    
                    if ($emailResult['success']) {
                        $emailSent = true;
                        $emailMessage = ' Password reset email sent to ' . $employee['email'];
                    } else {
                        $emailMessage = ' (Email failed to send - ' . $emailResult['message'] . ')';
                    }
                }
                
                ob_end_clean();
                echo json_encode([
                    'success' => $result,
                    'message' => ($result ? 'Password reset successfully!' : 'Error resetting password') . $emailMessage,
                    'default_password' => $result ? $defaultPassword : null,
                    'email_sent' => $emailSent
                ]);
                exit;
                
            case 'update_status':
                if (!isset($_POST['employee_id']) || !isset($_POST['status'])) {
                    echo json_encode(['success' => false, 'message' => 'Employee ID and status are required']);
                    exit;
                }
                
                $validStatuses = ['Active', 'Inactive'];
                if (!in_array($_POST['status'], $validStatuses)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid status']);
                    exit;
                }
                
                $stmt = $conn->prepare("UPDATE employees SET status = ? WHERE employee_id = ?");
                $result = $stmt->execute([$_POST['status'], $_POST['employee_id']]);
                
                ob_end_clean();
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Status updated successfully!' : 'Error updating status'
                ]);
                exit;
                
            case 'export_csv':
                // Fetch all employees
                $stmt = $conn->query("SELECT * FROM employees ORDER BY employee_id ASC");
                $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Create CSV content
                $csv = "Employee Code,First Name,Middle Name,Last Name,Contact Number,Email,Position,Role,Status,Hire Date,Address,Emergency Contact,Emergency Phone,Created At\n";
                
                foreach ($employees as $emp) {
                    $csv .= sprintf(
                        "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                        $emp['employee_code'] ?? '',
                        $emp['first_name'],
                        $emp['middle_name'] ?? '',
                        $emp['last_name'],
                        $emp['phone'],
                        $emp['email'],
                        $emp['position'],
                        $emp['role'],
                        $emp['status'],
                        $emp['hire_date'] ?? '',
                        str_replace(["\r", "\n", ","], [" ", " ", ";"], $emp['address'] ?? ''),
                        $emp['emergency_contact'] ?? '',
                        $emp['emergency_phone'] ?? '',
                        $emp['created_at']
                    );
                }
                
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'csv' => $csv,
                    'filename' => 'employees_' . date('Y-m-d_His') . '.csv'
                ]);
                exit;
                
            default:
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
        
    } catch (PDOException $e) {
        // Check for duplicate entry error
        if ($e->getCode() == 23000) {
            if (strpos($e->getMessage(), 'email') !== false) {
                echo json_encode(['success' => false, 'message' => 'Email already exists!']);
            } elseif (strpos($e->getMessage(), 'employee_code') !== false) {
                echo json_encode(['success' => false, 'message' => 'Employee code already exists!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Duplicate entry found!']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method or missing action']);
}

// Catch any unexpected output
ob_end_clean();
?>