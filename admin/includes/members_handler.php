<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has admin role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Super Admin', 'Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../../config/connection.php';
$conn = getDBConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_all':
            getAllMembers($conn);
            break;

        case 'get_all_for_filter':
            getAllMembersForFilter($conn);
            break;
            
        case 'get_one':
            getOneMember($conn);
            break;
            
        case 'add':
            addMember($conn);
            break;
            
        case 'edit':
            editMember($conn);
            break;
            
        case 'delete':
            deleteMember($conn);
            break;
            
        case 'verify':
            verifyMember($conn);
            break;
            
        case 'export':
            exportMembers($conn);
            break;
            
        case 'bulk_status':
            bulkUpdateStatus($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Get all members with pagination
 */
function getAllMembers($conn) {
    require_once 'pagination_helper.php';
    
    $page = isset($_POST['page']) ? (int)$_POST['page'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $itemsPerPage = 20;
    
    // Get total count
    $countStmt = $conn->query("SELECT COUNT(*) FROM clients");
    $totalMembers = (int)$countStmt->fetchColumn();
    
    // Create pagination object
    $pagination = new Pagination($totalMembers, $itemsPerPage, $page);
    
    // Get paginated members
    $sql = "SELECT * FROM clients ORDER BY join_date DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$pagination->getItemsPerPage(), $pagination->getOffset()]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'members' => $members,
        'pagination' => [
            'currentPage' => $pagination->getCurrentPage(),
            'totalPages' => $pagination->getTotalPages(),
            'totalItems' => $pagination->getTotalItems(),
            'itemsPerPage' => $pagination->getItemsPerPage(),
            'hasNextPage' => $pagination->hasNextPage(),
            'hasPreviousPage' => $pagination->hasPreviousPage(),
            'info' => $pagination->getInfo()
        ]
    ]);
}

/**
 * Get all members without pagination (for filtering/searching)
 */
function getAllMembersForFilter($conn) {
    $sql = "SELECT * FROM clients ORDER BY join_date DESC";
    $stmt = $conn->query($sql);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'members' => $members]);
}

/**
 * Get one member
 */
function getOneMember($conn) {
    $clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? 0;
    
    $sql = "SELECT * FROM clients WHERE client_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$clientId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($member) {
        // Get recent activities
        $activitySql = "SELECT * FROM client_activity WHERE client_id = ? ORDER BY created_at DESC LIMIT 10";
        $activityStmt = $conn->prepare($activitySql);
        $activityStmt->execute([$clientId]);
        $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'member' => $member,
            'activities' => $activities
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
    }
}

/**
 * Add new member
 */
function addMember($conn) {
    $firstName = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name']);
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'];
    $clientType = $_POST['client_type'];
    $status = $_POST['status'];
    $accountStatus = $_POST['account_status'];
    $isVerified = $_POST['is_verified'] ?? 0;
    
    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        return;
    }
    
    // Check if email already exists
    if (!empty($email)) {
        $checkEmail = $conn->prepare("SELECT client_id FROM clients WHERE email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
    }
    
    // Check if username already exists
    if (!empty($username)) {
        $checkUsername = $conn->prepare("SELECT client_id FROM clients WHERE username = ?");
        $checkUsername->execute([$username]);
        if ($checkUsername->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            return;
        }
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert member
    $sql = "INSERT INTO clients (
        first_name, middle_name, last_name, phone, email, username, 
        password_hash, client_type, status, account_status, is_verified, join_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        $firstName, $middleName, $lastName, $phone, $email, $username,
        $passwordHash, $clientType, $status, $accountStatus, $isVerified
    ]);
    
    if ($result) {
        $newClientId = $conn->lastInsertId();
        
        // Log activity
        logActivity($conn, $newClientId, 'Member Registration', "New member registered by admin: $firstName $lastName");
        
        echo json_encode(['success' => true, 'message' => 'Member added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add member']);
    }
}

/**
 * Edit member
 */
function editMember($conn) {
    $clientId = $_POST['client_id'];
    $firstName = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name']);
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $clientType = $_POST['client_type'];
    $status = $_POST['status'];
    $accountStatus = $_POST['account_status'];
    $isVerified = $_POST['is_verified'] ?? 0;
    
    // Validate required fields
    if (empty($firstName) || empty($lastName)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        return;
    }
    
    // Check if email already exists (excluding current member)
    if (!empty($email)) {
        $checkEmail = $conn->prepare("SELECT client_id FROM clients WHERE email = ? AND client_id != ?");
        $checkEmail->execute([$email, $clientId]);
        if ($checkEmail->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
    }
    
    // Check if username already exists (excluding current member)
    if (!empty($username)) {
        $checkUsername = $conn->prepare("SELECT client_id FROM clients WHERE username = ? AND client_id != ?");
        $checkUsername->execute([$username, $clientId]);
        if ($checkUsername->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            return;
        }
    }
    
    // Build SQL query
    if (!empty($password)) {
        // Update with password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE clients SET 
            first_name = ?, middle_name = ?, last_name = ?, phone = ?, email = ?, 
            username = ?, password_hash = ?, client_type = ?, status = ?, 
            account_status = ?, is_verified = ?
            WHERE client_id = ?";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            $firstName, $middleName, $lastName, $phone, $email, $username,
            $passwordHash, $clientType, $status, $accountStatus, $isVerified, $clientId
        ]);
    } else {
        // Update without password
        $sql = "UPDATE clients SET 
            first_name = ?, middle_name = ?, last_name = ?, phone = ?, email = ?, 
            username = ?, client_type = ?, status = ?, account_status = ?, is_verified = ?
            WHERE client_id = ?";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            $firstName, $middleName, $lastName, $phone, $email, $username,
            $clientType, $status, $accountStatus, $isVerified, $clientId
        ]);
    }
    
    if ($result) {
        // Log activity
        logActivity($conn, $clientId, 'Profile Update', "Member profile updated by admin");
        
        echo json_encode(['success' => true, 'message' => 'Member updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update member']);
    }
}

/**
 * Delete member
 */
function deleteMember($conn) {
    $clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? 0;
    
    // Check if member has active memberships or transactions
    $checkSql = "SELECT COUNT(*) as count FROM client_memberships WHERE client_id = ? AND status = 'Active'";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$clientId]);
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete member with active memberships. Please deactivate first.']);
        return;
    }
    
    // Delete member
    $sql = "DELETE FROM clients WHERE client_id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$clientId]);
    
    if ($result) {
        // Log activity before deletion (if you want to keep activity logs)
        // Note: This will fail if there's a foreign key constraint
        // logActivity($conn, $clientId, 'Account Deletion', 'Account deleted by admin');
        
        echo json_encode(['success' => true, 'message' => 'Member deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete member']);
    }
}

/**
 * Verify member
 */
function verifyMember($conn) {
    $clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? 0;
    
    $sql = "UPDATE clients SET is_verified = 1, verification_token = NULL WHERE client_id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$clientId]);
    
    if ($result) {
        // Log activity
        logActivity($conn, $clientId, 'Email Verification', 'Email verified by admin');
        
        echo json_encode(['success' => true, 'message' => 'Member verified successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to verify member']);
    }
}

/**
 * Export members
 */
function exportMembers($conn) {
    $sql = "SELECT * FROM clients ORDER BY join_date DESC";
    $stmt = $conn->query($sql);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'members' => $members]);
}

/**
 * Bulk update status
 */
function bulkUpdateStatus($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $memberIds = $data['members'] ?? [];
    $status = $data['status'] ?? '';
    
    if (empty($memberIds) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        return;
    }
    
    $placeholders = str_repeat('?,', count($memberIds) - 1) . '?';
    $sql = "UPDATE clients SET status = ? WHERE client_id IN ($placeholders)";
    
    $params = array_merge([$status], $memberIds);
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        // Log activity for each member
        foreach ($memberIds as $memberId) {
            logActivity($conn, $memberId, 'Status Update', "Status changed to $status by admin");
        }
        
        echo json_encode(['success' => true, 'message' => count($memberIds) . ' member(s) updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update members']);
    }
}

/**
 * Log client activity
 */
function logActivity($conn, $clientId, $activityType, $description) {
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $sql = "INSERT INTO client_activity (client_id, activity_type, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$clientId, $activityType, $description, $ipAddress, $userAgent]);
    } catch (Exception $e) {
        // Silently fail if activity logging fails
        error_log('Activity logging failed: ' . $e->getMessage());
    }
}
?>