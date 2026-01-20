<?php
session_start();
require_once '../../config/connection.php';

// Include email functions
if (file_exists('../../includes/email_functions.php')) {
    require_once '../../includes/email_functions.php';
}

// Check if user is logged in and has manager role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$conn = getDBConnection();
$response = ['success' => false, 'message' => ''];

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'add_coach':
            $response = addCoach($conn);
            break;
        
        case 'assign_client':
            $response = assignClient($conn);
            break;
        
        case 'change_status':
            $response = changeStatus($conn);
            break;
        
        case 'get_unassigned_clients':
            $response = getUnassignedClients($conn);
            break;
        
        default:
            $response['message'] = 'Invalid action';
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);

// Function to add a new coach
function addCoach($conn) {
    try {
        // Validate required fields
        $required = ['first_name', 'last_name', 'email', 'hire_date', 'status'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                return ['success' => false, 'message' => "Field '$field' is required"];
            }
        }
        
        // Validate phone if provided (must be exactly 11 digits)
        if (!empty($_POST['phone']) && !preg_match('/^[0-9]{11}$/', $_POST['phone'])) {
            return ['success' => false, 'message' => 'Contact number must be exactly 11 digits'];
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT coach_id FROM coach WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        // Generate random temporary password (8 characters)
        $tempPassword = generateRandomPassword(8);
        
        // Handle profile image upload
        $profileImage = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            // Local upload directory (empirefitness)
            $localUploadDir = '../../uploads/coaches/';
            if (!is_dir($localUploadDir)) {
                mkdir($localUploadDir, 0755, true);
            }
            
            // External upload directory (pbl_project)
            $externalUploadDir = 'C:\\xampp\\htdocs\\pbl_project\\assets\\images\\profile_photos\\';
            if (!is_dir($externalUploadDir)) {
                mkdir($externalUploadDir, 0755, true);
            }
            
            // Generate filename
            $fileExtension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $fileName = 'coach_' . time() . '_' . uniqid() . '.' . $fileExtension;
            
            // Save to local directory
            $localFilePath = $localUploadDir . $fileName;
            $externalFilePath = $externalUploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $localFilePath)) {
                $profileImage = 'uploads/coaches/' . $fileName;
                
                // Also copy to external directory
                if (file_exists($localFilePath)) {
                    copy($localFilePath, $externalFilePath);
                }
            }
        }
        
        // Generate coach_id (max + 1)
        $maxId = $conn->query("SELECT COALESCE(MAX(coach_id), 0) + 1 FROM coach")->fetchColumn();
        
        // Generate admin_id (max + 1 from employees)
        $adminId = $conn->query("SELECT COALESCE(MAX(employee_id), 0) + 1 FROM employees")->fetchColumn();
        
        // Hash password
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Insert coach
        $stmt = $conn->prepare("
            INSERT INTO coach (
                coach_id, admin_id, first_name, middle_name, last_name, email, phone,
                specialization, certification, experience_years, hourly_rate,
                hire_date, status, bio, profile_image, password,
                created_by, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, NOW(), NOW()
            )
        ");
        
        $stmt->execute([
            $maxId,
            $adminId,
            $_POST['first_name'],
            $_POST['middle_name'] ?? null,
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'] ?? null,
            $_POST['specialization'] ?? null,
            $_POST['certification'] ?? null,
            $_POST['experience_years'] ?? null,
            $_POST['hourly_rate'] ?? null,
            $_POST['hire_date'],
            $_POST['status'],
            $_POST['bio'] ?? null,
            $profileImage,
            $passwordHash,
            $_SESSION['employee_id']
        ]);
        
        // Also insert into employees table for unified access control (optional)
        $stmt = $conn->prepare("
            INSERT INTO employees (
                employee_id, first_name, middle_name, last_name, email, phone,
                position, hire_date, password_hash, role, status, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                'Trainer', ?, ?, 'Coach', ?, NOW(), NOW()
            )
        ");
        
        $stmt->execute([
            $adminId,
            $_POST['first_name'],
            $_POST['middle_name'] ?? null,
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'] ?? null,
            $_POST['hire_date'],
            $passwordHash,
            $_POST['status']
        ]);
        
        $conn->commit();
        
        // Save credentials to text file
        $credentialsDir = '../credentials/';
        if (!is_dir($credentialsDir)) {
            mkdir($credentialsDir, 0755, true);
        }
        
        $filename = $credentialsDir . 'coach_' . $maxId . '_' . date('Y-m-d_His') . '.txt';
        $credentialsContent = generateCredentialsFile($_POST, $tempPassword, $maxId);
        file_put_contents($filename, $credentialsContent);
        
        // Send email to coach
        $emailSent = false;
        $emailMessage = '';
        
        if (function_exists('sendEmail')) {
            $coachName = $_POST['first_name'] . ' ' . $_POST['last_name'];
            $emailBody = "
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
                        <h1>🎓 Welcome to Empire Fitness Coaching Team!</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello " . htmlspecialchars($_POST['first_name']) . ",</h2>
                        <p>You have been added as a coach at Empire Fitness. Your coaching account has been created and is ready to use.</p>
                        
                        <div class='credentials'>
                            <h3>Your Login Credentials:</h3>
                            <p><strong>Email:</strong> <code>" . htmlspecialchars($_POST['email']) . "</code></p>
                            <p><strong>Temporary Password:</strong> <code>" . htmlspecialchars($tempPassword) . "</code></p>
                        </div>
                        
                        <p><strong>⚠️ Important:</strong> Please change your password after your first login for security purposes.</p>
                        
                        <h3>Your Coach Information:</h3>
                        <p><strong>Specialization:</strong> " . htmlspecialchars($_POST['specialization'] ?? 'Not specified') . "</p>
                        <p><strong>Hire Date:</strong> " . htmlspecialchars($_POST['hire_date'] ?? 'N/A') . "</p>
                        <p><strong>Status:</strong> " . htmlspecialchars($_POST['status'] ?? 'Active') . "</p>
                        
                        <p>You can now log in and start managing your client assignments.</p>
                        <p>Login at: <a href='http://localhost/empirefitness'>http://localhost/empirefitness</a></p>
                        
                        <div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'>
                            <p><strong>Empire Fitness</strong> | Coaching Excellence</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $result = sendEmail(
                $_POST['email'],
                'Your Empire Fitness Coach Account is Ready',
                $emailBody
            );
            
            if ($result['success']) {
                $emailSent = true;
                $emailMessage = ' Email sent to coach.';
            }
        }
        
        return [
            'success' => true, 
            'message' => 'Coach added successfully!' . $emailMessage . ' Credentials saved to: ' . basename($filename),
            'coach_id' => $maxId,
            'credentials_file' => basename($filename),
            'email_sent' => $emailSent
        ];
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return ['success' => false, 'message' => 'Error adding coach: ' . $e->getMessage()];
    }
}

// Function to generate random password
function generateRandomPassword($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    $charactersLength = strlen($characters);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $password;
}

// Function to generate credentials file content
function generateCredentialsFile($data, $password, $coachId) {
    $content = "==========================================================\n";
    $content .= "         EMPIRE FITNESS - COACH LOGIN CREDENTIALS         \n";
    $content .= "==========================================================\n\n";
    $content .= "Generated on: " . date('F d, Y h:i:s A') . "\n";
    $content .= "Generated by: " . ($_SESSION['employee_name'] ?? 'Manager') . "\n\n";
    $content .= "----------------------------------------------------------\n";
    $content .= "COACH INFORMATION\n";
    $content .= "----------------------------------------------------------\n";
    $content .= "Coach ID:       " . $coachId . "\n";
    $content .= "Name:           " . $data['first_name'] . " " . $data['last_name'] . "\n";
    $content .= "Email:          " . $data['email'] . "\n";
    $content .= "Phone:          " . ($data['phone'] ?? 'N/A') . "\n";
    $content .= "Specialization: " . ($data['specialization'] ?? 'N/A') . "\n";
    $content .= "Hire Date:      " . date('F d, Y', strtotime($data['hire_date'])) . "\n";
    $content .= "Status:         " . $data['status'] . "\n\n";
    $content .= "----------------------------------------------------------\n";
    $content .= "LOGIN CREDENTIALS\n";
    $content .= "----------------------------------------------------------\n";
    $content .= "Username/Email: " . $data['email'] . "\n";
    $content .= "Password:       " . $password . "\n\n";
    $content .= "----------------------------------------------------------\n";
    $content .= "IMPORTANT NOTES\n";
    $content .= "----------------------------------------------------------\n";
    $content .= "1. Please change your password after first login\n";
    $content .= "2. Keep these credentials secure and confidential\n";
    $content .= "3. Login portal: http://localhost/pbl_project/login/log_in_page.html\n";
    $content .= "4. For assistance, contact the gym manager\n\n";
    $content .= "==========================================================\n";
    $content .= "         Welcome to the Empire Fitness Team!              \n";
    $content .= "==========================================================\n\n";
    $content .= "NOTE: This is a temporary password. You will be prompted\n";
    $content .= "to change it upon first login for security purposes.\n\n";
    $content .= "If you did not request this account or have any concerns,\n";
    $content .= "please contact management immediately.\n";
    
    return $content;
}

// Function to assign a client to a coach
function assignClient($conn) {
    try {
        $coachId = $_POST['coach_id'] ?? null;
        $clientId = $_POST['client_id'] ?? null;
        $notes = $_POST['notes'] ?? null;
        
        if (!$coachId || !$clientId) {
            return ['success' => false, 'message' => 'Coach ID and Client ID are required'];
        }
        
        // Check if client is already assigned to another coach
        $stmt = $conn->prepare("SELECT assigned_coach_id FROM clients WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $currentCoach = $stmt->fetchColumn();
        
        if ($currentCoach && $currentCoach != $coachId) {
            return ['success' => false, 'message' => 'Client is already assigned to another coach'];
        }
        
        // Update client's assigned coach
        $stmt = $conn->prepare("UPDATE clients SET assigned_coach_id = ? WHERE client_id = ?");
        $stmt->execute([$coachId, $clientId]);
        
        // Log the assignment (you can create an assignment history table)
        // For now, we'll just update the client record
        
        return ['success' => true, 'message' => 'Client assigned successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error assigning client: ' . $e->getMessage()];
    }
}

// Function to change coach status
function changeStatus($conn) {
    try {
        $coachId = $_POST['coach_id'] ?? null;
        $status = $_POST['status'] ?? null;
        
        if (!$coachId || !$status) {
            return ['success' => false, 'message' => 'Coach ID and Status are required'];
        }
        
        $validStatuses = ['Active', 'Inactive', 'On Leave'];
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }
        
        $stmt = $conn->prepare("UPDATE coach SET status = ?, updated_at = NOW() WHERE coach_id = ?");
        $stmt->execute([$status, $coachId]);
        
        // Also update in employees table
        $stmt = $conn->prepare("
            UPDATE employees 
            SET status = ?, updated_at = NOW() 
            WHERE employee_id = (SELECT admin_id FROM coach WHERE coach_id = ?)
        ");
        $stmt->execute([$status, $coachId]);
        
        return ['success' => true, 'message' => 'Status updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
    }
}

// Function to get unassigned clients
function getUnassignedClients($conn) {
    try {
        $stmt = $conn->query("
            SELECT client_id, first_name, last_name, email
            FROM clients
            WHERE (assigned_coach_id IS NULL OR assigned_coach_id = 0)
            AND status = 'Active'
            ORDER BY first_name, last_name
        ");
        
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'clients' => $clients];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching clients: ' . $e->getMessage()];
    }
}