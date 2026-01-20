<?php
session_start();
require_once '../../config/connection.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Super Admin', 'Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            addMembershipPlan($conn);
            break;
            
        case 'edit':
            editMembershipPlan($conn);
            break;
            
        case 'delete':
            deleteMembershipPlan($conn);
            break;
            
        case 'toggle_status':
            togglePlanStatus($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function addMembershipPlan($conn) {
    $plan_name = trim($_POST['plan_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $benefits = trim($_POST['benefits'] ?? '');
    $monthly_fee = floatval($_POST['monthly_fee'] ?? 0);
    $renewal_fee = !empty($_POST['renewal_fee']) ? floatval($_POST['renewal_fee']) : null;
    $renewal_discount_percent = floatval($_POST['renewal_discount_percent'] ?? 0);
    $duration_days = intval($_POST['duration_days'] ?? 0);
    $status = $_POST['status'] ?? 'Active';
    $is_base_membership = intval($_POST['is_base_membership'] ?? 0);
    
    // Validation
    if (empty($plan_name)) {
        echo json_encode(['success' => false, 'message' => 'Plan name is required']);
        return;
    }
    
    if ($monthly_fee <= 0) {
        echo json_encode(['success' => false, 'message' => 'Monthly fee must be greater than 0']);
        return;
    }
    
    if ($duration_days <= 0) {
        echo json_encode(['success' => false, 'message' => 'Duration must be greater than 0 days']);
        return;
    }
    
    // Check for duplicate plan names
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM memberships WHERE plan_name = :plan_name");
    $checkStmt->execute([':plan_name' => $plan_name]);
    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'A plan with this name already exists']);
        return;
    }
    
    // Get next membership_id
    $maxId = $conn->query("SELECT COALESCE(MAX(membership_id), 0) FROM memberships")->fetchColumn();
    $membership_id = $maxId + 1;
    
    // Insert new plan
    $sql = "INSERT INTO memberships (
                membership_id, plan_name, description, benefits, 
                monthly_fee, renewal_fee, renewal_discount_percent, 
                duration_days, status, is_base_membership
            ) VALUES (
                :membership_id, :plan_name, :description, :benefits,
                :monthly_fee, :renewal_fee, :renewal_discount_percent,
                :duration_days, :status, :is_base_membership
            )";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':membership_id' => $membership_id,
        ':plan_name' => $plan_name,
        ':description' => $description,
        ':benefits' => $benefits,
        ':monthly_fee' => $monthly_fee,
        ':renewal_fee' => $renewal_fee,
        ':renewal_discount_percent' => $renewal_discount_percent,
        ':duration_days' => $duration_days,
        ':status' => $status,
        ':is_base_membership' => $is_base_membership
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Membership plan added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add membership plan']);
    }
}

function editMembershipPlan($conn) {
    $membership_id = intval($_POST['membership_id'] ?? 0);
    $plan_name = trim($_POST['plan_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $benefits = trim($_POST['benefits'] ?? '');
    $monthly_fee = floatval($_POST['monthly_fee'] ?? 0);
    $renewal_fee = !empty($_POST['renewal_fee']) ? floatval($_POST['renewal_fee']) : null;
    $renewal_discount_percent = floatval($_POST['renewal_discount_percent'] ?? 0);
    $duration_days = intval($_POST['duration_days'] ?? 0);
    $status = $_POST['status'] ?? 'Active';
    $is_base_membership = intval($_POST['is_base_membership'] ?? 0);
    
    // Validation
    if ($membership_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid membership ID']);
        return;
    }
    
    if (empty($plan_name)) {
        echo json_encode(['success' => false, 'message' => 'Plan name is required']);
        return;
    }
    
    if ($monthly_fee <= 0) {
        echo json_encode(['success' => false, 'message' => 'Monthly fee must be greater than 0']);
        return;
    }
    
    if ($duration_days <= 0) {
        echo json_encode(['success' => false, 'message' => 'Duration must be greater than 0 days']);
        return;
    }
    
    // Check for duplicate plan names (excluding current plan)
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM memberships WHERE plan_name = :plan_name AND membership_id != :membership_id");
    $checkStmt->execute([':plan_name' => $plan_name, ':membership_id' => $membership_id]);
    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'A plan with this name already exists']);
        return;
    }
    
    // Update plan
    $sql = "UPDATE memberships SET
                plan_name = :plan_name,
                description = :description,
                benefits = :benefits,
                monthly_fee = :monthly_fee,
                renewal_fee = :renewal_fee,
                renewal_discount_percent = :renewal_discount_percent,
                duration_days = :duration_days,
                status = :status,
                is_base_membership = :is_base_membership
            WHERE membership_id = :membership_id";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':membership_id' => $membership_id,
        ':plan_name' => $plan_name,
        ':description' => $description,
        ':benefits' => $benefits,
        ':monthly_fee' => $monthly_fee,
        ':renewal_fee' => $renewal_fee,
        ':renewal_discount_percent' => $renewal_discount_percent,
        ':duration_days' => $duration_days,
        ':status' => $status,
        ':is_base_membership' => $is_base_membership
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Membership plan updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update membership plan']);
    }
}

function deleteMembershipPlan($conn) {
    $membership_id = intval($_POST['membership_id'] ?? 0);
    
    if ($membership_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid membership ID']);
        return;
    }
    
    // Check if plan is being used by any members
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM clients WHERE current_membership_id = :membership_id");
    $checkStmt->execute([':membership_id' => $membership_id]);
    $memberCount = $checkStmt->fetchColumn();
    
    if ($memberCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Cannot delete plan: $memberCount member(s) are currently using this plan. Please reassign them first."
        ]);
        return;
    }
    
    // Delete plan
    $stmt = $conn->prepare("DELETE FROM memberships WHERE membership_id = :membership_id");
    $result = $stmt->execute([':membership_id' => $membership_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Membership plan deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete membership plan']);
    }
}

function togglePlanStatus($conn) {
    $membership_id = intval($_POST['membership_id'] ?? 0);
    $status = $_POST['status'] ?? 'Active';
    
    if ($membership_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid membership ID']);
        return;
    }
    
    if (!in_array($status, ['Active', 'Inactive'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE memberships SET status = :status WHERE membership_id = :membership_id");
    $result = $stmt->execute([':status' => $status, ':membership_id' => $membership_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => "Plan status updated to $status"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
}
?>