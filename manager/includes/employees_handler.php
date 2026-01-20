<?php
ob_start();
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

try {
    $connectionFile = '../../config/connection.php';
    if (!file_exists($connectionFile)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Connection file not found']);
        exit;
    }
    require_once $connectionFile;
    $pdo = getDBConnection();
    
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

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'fetch':
        fetchEmployees($pdo);
        break;
    case 'stats':
        getEmployeeStats($pdo);
        break;
    case 'create':
        createEmployee($pdo);
        break;
    case 'update':
        updateEmployee($pdo);
        break;
    case 'delete':
        deleteEmployee($pdo);
        break;
    default:
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function fetchEmployees($pdo) {
    try {
        // Manager can view: Receptionists, Managers, and Admins
        $stmt = $pdo->query("
            SELECT 
                employee_id,
                employee_code,
                first_name,
                last_name,
                email,
                phone,
                role,
                hire_date,
                status,
                address
            FROM employees
            WHERE role IN ('Receptionist', 'Manager', 'Admin')
            ORDER BY 
                CASE role
                    WHEN 'Manager' THEN 1
                    WHEN 'Receptionist' THEN 2
                    WHEN 'Admin' THEN 3
                END,
                first_name ASC
        ");
        
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formattedEmployees = array_map(function($emp) {
            return [
                'id' => (int)$emp['employee_id'],
                'employee_code' => $emp['employee_code'],
                'firstName' => $emp['first_name'],
                'middleName' => $emp['middle_name'],
                'lastName' => $emp['last_name'],
                'first_name' => $emp['first_name'],
                'middle_name' => $emp['middle_name'],
                'last_name' => $emp['last_name'],
                'email' => $emp['email'],
                'phone' => $emp['phone'],
                'role' => $emp['role'],
                'status' => $emp['status'],
                'address' => $emp['address']
            ];
        }, $employees);
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'employees' => $formattedEmployees
        ]);
        
    } catch(PDOException $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error fetching employees: ' . $e->getMessage()]);
    }
}

function createEmployee($pdo) {
    try {
        $firstName = $_POST['first_name'] ?? '';
        $middleName = $_POST['middle_name'] ?? null;
        $lastName = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? null;
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        $address = $_POST['address'] ?? null;
        
        // Validate required fields
        if (empty($firstName) || empty($lastName) || empty($email) || empty($role)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'First Name, Last Name, Email, and Role are required']);
            return;
        }
        
        // Validate role - Manager can only create: Receptionist, Manager, Admin
        $allowedRoles = ['Receptionist', 'Manager', 'Admin'];
        if (!in_array($role, $allowedRoles)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid role']);
            return;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
        
        // Generate employee_code
        $year = date('Y');
        $prefix = 'EMP' . $year;
        $stmt = $pdo->prepare("SELECT employee_code FROM employees WHERE employee_code LIKE ? ORDER BY employee_code DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $lastCode = $stmt->fetchColumn();
        
        if ($lastCode) {
            $lastNumber = intval(substr($lastCode, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        $employeeCode = $prefix . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        
        // Generate default password
        $defaultPassword = 'EmpireFit2025!';
        $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
        
        // Insert employee
        $stmt = $pdo->prepare("
            INSERT INTO employees (
                employee_code, first_name, middle_name, last_name, email, phone, 
                role, status, address, password_hash, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $employeeCode,
            $firstName,
            $middleName,
            $lastName,
            $email,
            $phone,
            $role,
            $status,
            $address,
            $passwordHash
        ]);
        
        $newEmployeeId = $pdo->lastInsertId();
        
        // Save credentials to text file in admin/credentials folder
        $credentialsDir = __DIR__ . '/../../admin/credentials';
        if (!is_dir($credentialsDir)) {
            mkdir($credentialsDir, 0755, true);
        }
        
        // Create filename with timestamp
        $timestamp = date('Y-m-d_His');
        $filename = $credentialsDir . '/EMPLOYEE_' . $employeeCode . '_' . $timestamp . '.txt';
        
        // Prepare credentials content
        $credentialsContent = "═══════════════════════════════════════════════════════════════\n";
        $credentialsContent .= "EMPLOYEE LOGIN CREDENTIALS\n";
        $credentialsContent .= "═══════════════════════════════════════════════════════════════\n\n";
        $credentialsContent .= "Employee Code: " . $employeeCode . "\n";
        $credentialsContent .= "Name: " . $firstName . " " . $lastName . "\n";
        $credentialsContent .= "Email: " . $email . "\n";
        $credentialsContent .= "Role: " . $role . "\n";
        $credentialsContent .= "Status: " . $status . "\n";
        $credentialsContent .= "\n";
        $credentialsContent .= "LOGIN DETAILS:\n";
        $credentialsContent .= "─────────────────────────────────────────────────────────────\n";
        $credentialsContent .= "Email (Username): " . $email . "\n";
        $credentialsContent .= "Default Password: " . $defaultPassword . "\n";
        $credentialsContent .= "\n";
        $credentialsContent .= "LOGIN URL: http://localhost/empirefitness/index.php\n";
        $credentialsContent .= "\n";
        $credentialsContent .= "IMPORTANT NOTES:\n";
        $credentialsContent .= "─────────────────────────────────────────────────────────────\n";
        $credentialsContent .= "1. Please change your password after first login\n";
        $credentialsContent .= "2. Keep these credentials secure and confidential\n";
        $credentialsContent .= "3. Do not share your password with anyone\n";
        $credentialsContent .= "4. Contact admin if you forget your password\n";
        $credentialsContent .= "\n";
        $credentialsContent .= "Generated Date: " . date('Y-m-d H:i:s') . "\n";
        $credentialsContent .= "═══════════════════════════════════════════════════════════════\n";
        
        // Write to file
        file_put_contents($filename, $credentialsContent);
        
        // Send email to employee
        $emailSent = false;
        $emailMessage = '';
        
        if (function_exists('sendEmployeeCredentialsEmail')) {
            $result = sendEmployeeCredentialsEmail(
                $email,
                $firstName . ' ' . $lastName,
                $employeeCode,
                $defaultPassword,
                $role
            );
            
            if ($result['success']) {
                $emailSent = true;
                $emailMessage = ' Email sent to employee.';
            }
        }
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Employee created successfully' . $emailMessage,
            'employee_id' => $newEmployeeId,
            'employee_code' => $employeeCode,
            'default_password' => $defaultPassword,
            'credentials_file' => basename($filename),
            'email_sent' => $emailSent
        ]);
        
    } catch(PDOException $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error creating employee: ' . $e->getMessage()]);
    }
}

function updateEmployee($pdo) {
    try {
        $employeeId = $_POST['employee_id'] ?? 0;
        $firstName = $_POST['first_name'] ?? '';
        $middleName = $_POST['middle_name'] ?? null;
        $lastName = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? null;
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        $address = $_POST['address'] ?? null;
        $password = $_POST['password'] ?? '';
        
        // Validate required fields
        if (!$employeeId || empty($firstName) || empty($lastName) || empty($email) || empty($role)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'First Name, Last Name, Email, and Role are required']);
            return;
        }
        
        // Validate role
        $allowedRoles = ['Receptionist', 'Manager', 'Admin'];
        if (!in_array($role, $allowedRoles)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid role']);
            return;
        }
        
        // Check if employee exists and is manageable
        $stmt = $pdo->prepare("SELECT role FROM employees WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            return;
        }
        
        // Manager cannot edit Super Admin or Admin accounts
        if (in_array($employee['role'], ['Super Admin', 'Admin'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this employee']);
            return;
        }
        
        // Check if email is taken by another employee
        $stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE email = ? AND employee_id != ?");
        $stmt->execute([$email, $employeeId]);
        if ($stmt->fetch()) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
        
        // Update employee
        if (!empty($password)) {
            // Update with new password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE employees SET
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    email = ?,
                    phone = ?,
                    role = ?,
                    status = ?,
                    address = ?,
                    password_hash = ?
                WHERE employee_id = ?
            ");
            $stmt->execute([
                $firstName,
                $middleName,
                $lastName,
                $email,
                $phone,
                $role,
                $status,
                $address,
                $passwordHash,
                $employeeId
            ]);
        } else {
            // Update without changing password
            $stmt = $pdo->prepare("
                UPDATE employees SET
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    email = ?,
                    phone = ?,
                    role = ?,
                    status = ?,
                    address = ?
                WHERE employee_id = ?
            ");
            $stmt->execute([
                $firstName,
                $middleName,
                $lastName,
                $email,
                $phone,
                $role,
                $status,
                $address,
                $employeeId
            ]);
        }
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Employee updated successfully'
        ]);
        
    } catch(PDOException $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error updating employee: ' . $e->getMessage()]);
    }
}

function deleteEmployee($pdo) {
    try {
        $employeeId = $_POST['employee_id'] ?? 0;
        
        if (!$employeeId) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Employee ID required']);
            return;
        }
        
        // Check if employee exists and is deletable
        $stmt = $pdo->prepare("SELECT role FROM employees WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            return;
        }
        
        // Manager cannot delete Super Admin or Admin accounts
        if (in_array($employee['role'], ['Super Admin', 'Admin'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this employee']);
            return;
        }
        
        // Prevent deleting yourself
        if ($employeeId == $_SESSION['employee_id']) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
            return;
        }
        
        // Delete employee
        $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Employee deleted successfully'
        ]);
        
    } catch(PDOException $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error deleting employee: ' . $e->getMessage()]);
    }
}

function getEmployeeStats($pdo) {
    try {
        $stats = [
            'total-employees' => 0,
            'total-receptionists' => 0,
            'total-managers' => 0,
            'total-other' => 0
        ];
        
        // Total employees
        $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE role IN ('Receptionist', 'Manager', 'Admin')");
        $stats['total-employees'] = (int)$stmt->fetchColumn();
        
        // Receptionists
        $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE role = 'Receptionist'");
        $stats['total-receptionists'] = (int)$stmt->fetchColumn();
        
        // Managers
        $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE role = 'Manager'");
        $stats['total-managers'] = (int)$stmt->fetchColumn();
        
        // Admins
        $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE role = 'Admin'");
        $stats['total-other'] = (int)$stmt->fetchColumn();
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        
    } catch(PDOException $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error fetching stats: ' . $e->getMessage()]);
    }
}

?>