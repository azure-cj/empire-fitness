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
            addRate($conn);
            break;
            
        case 'edit':
            editRate($conn);
            break;
            
        case 'delete':
            deleteRate($conn);
            break;
            
        case 'toggle_status':
            toggleRateStatus($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function addRate($conn) {
    $rate_name = trim($_POST['rate_name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $applies_to = $_POST['applies_to'] ?? '';
    $is_discounted = intval($_POST['is_discounted'] ?? 0);
    $base_rate_id = !empty($_POST['base_rate_id']) ? intval($_POST['base_rate_id']) : null;
    $discount_type = trim($_POST['discount_type'] ?? '');
    $is_active = intval($_POST['is_active'] ?? 1);
    
    // Validation
    if (empty($rate_name)) {
        echo json_encode(['success' => false, 'message' => 'Rate name is required']);
        return;
    }
    
    if ($price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Price must be greater than 0']);
        return;
    }
    
    if (!in_array($applies_to, ['Guest', 'Member'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid "Applies To" value']);
        return;
    }
    
    // Check for duplicate rate names
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM rates WHERE rate_name = :rate_name");
    $checkStmt->execute([':rate_name' => $rate_name]);
    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'A rate with this name already exists']);
        return;
    }
    
    // If discounted, validate against base rate
    if ($is_discounted && $base_rate_id) {
        $baseStmt = $conn->prepare("SELECT price FROM rates WHERE rate_id = :rate_id");
        $baseStmt->execute([':rate_id' => $base_rate_id]);
        $basePrice = $baseStmt->fetchColumn();
        
        if ($basePrice && $price >= $basePrice) {
            echo json_encode(['success' => false, 'message' => 'Discounted price must be less than the base rate price']);
            return;
        }
    }
    
    // Insert new rate
    $sql = "INSERT INTO rates (
                rate_name, price, description, applies_to, 
                is_discounted, base_rate_id, discount_type, is_active
            ) VALUES (
                :rate_name, :price, :description, :applies_to,
                :is_discounted, :base_rate_id, :discount_type, :is_active
            )";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':rate_name' => $rate_name,
        ':price' => $price,
        ':description' => $description,
        ':applies_to' => $applies_to,
        ':is_discounted' => $is_discounted,
        ':base_rate_id' => $base_rate_id,
        ':discount_type' => $discount_type,
        ':is_active' => $is_active
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Rate added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add rate']);
    }
}

function editRate($conn) {
    $rate_id = intval($_POST['rate_id'] ?? 0);
    $rate_name = trim($_POST['rate_name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $applies_to = $_POST['applies_to'] ?? '';
    $is_discounted = intval($_POST['is_discounted'] ?? 0);
    $base_rate_id = !empty($_POST['base_rate_id']) ? intval($_POST['base_rate_id']) : null;
    $discount_type = trim($_POST['discount_type'] ?? '');
    $is_active = intval($_POST['is_active'] ?? 1);
    
    // Validation
    if ($rate_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid rate ID']);
        return;
    }
    
    if (empty($rate_name)) {
        echo json_encode(['success' => false, 'message' => 'Rate name is required']);
        return;
    }
    
    if ($price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Price must be greater than 0']);
        return;
    }
    
    if (!in_array($applies_to, ['Guest', 'Member'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid "Applies To" value']);
        return;
    }
    
    // Check for duplicate rate names (excluding current rate)
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM rates WHERE rate_name = :rate_name AND rate_id != :rate_id");
    $checkStmt->execute([':rate_name' => $rate_name, ':rate_id' => $rate_id]);
    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'A rate with this name already exists']);
        return;
    }
    
    // Prevent self-reference in base_rate_id
    if ($base_rate_id && $base_rate_id == $rate_id) {
        echo json_encode(['success' => false, 'message' => 'A rate cannot be its own base rate']);
        return;
    }
    
    // If discounted, validate against base rate
    if ($is_discounted && $base_rate_id) {
        $baseStmt = $conn->prepare("SELECT price FROM rates WHERE rate_id = :rate_id");
        $baseStmt->execute([':rate_id' => $base_rate_id]);
        $basePrice = $baseStmt->fetchColumn();
        
        if ($basePrice && $price >= $basePrice) {
            echo json_encode(['success' => false, 'message' => 'Discounted price must be less than the base rate price']);
            return;
        }
    }
    
    // Update rate
    $sql = "UPDATE rates SET
                rate_name = :rate_name,
                price = :price,
                description = :description,
                applies_to = :applies_to,
                is_discounted = :is_discounted,
                base_rate_id = :base_rate_id,
                discount_type = :discount_type,
                is_active = :is_active
            WHERE rate_id = :rate_id";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':rate_id' => $rate_id,
        ':rate_name' => $rate_name,
        ':price' => $price,
        ':description' => $description,
        ':applies_to' => $applies_to,
        ':is_discounted' => $is_discounted,
        ':base_rate_id' => $base_rate_id,
        ':discount_type' => $discount_type,
        ':is_active' => $is_active
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Rate updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update rate']);
    }
}

function deleteRate($conn) {
    $rate_id = intval($_POST['rate_id'] ?? 0);
    
    if ($rate_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid rate ID']);
        return;
    }
    
    // Check if rate is being used as a base rate for other rates
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM rates WHERE base_rate_id = :rate_id");
    $checkStmt->execute([':rate_id' => $rate_id]);
    $dependentRates = $checkStmt->fetchColumn();
    
    if ($dependentRates > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Cannot delete rate: $dependentRates other rate(s) are using this as a base rate. Please update them first."
        ]);
        return;
    }
    
    // Check if rate is being used in any transactions/payments (if you have such tables)
    // Add similar checks here if needed
    
    // Delete rate
    $stmt = $conn->prepare("DELETE FROM rates WHERE rate_id = :rate_id");
    $result = $stmt->execute([':rate_id' => $rate_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Rate deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete rate']);
    }
}

function toggleRateStatus($conn) {
    $rate_id = intval($_POST['rate_id'] ?? 0);
    $is_active = intval($_POST['is_active'] ?? 1);
    
    if ($rate_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid rate ID']);
        return;
    }
    
    if (!in_array($is_active, [0, 1])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE rates SET is_active = :is_active WHERE rate_id = :rate_id");
    $result = $stmt->execute([':is_active' => $is_active, ':rate_id' => $rate_id]);
    
    if ($result) {
        $status = $is_active ? 'activated' : 'deactivated';
        echo json_encode(['success' => true, 'message' => "Rate $status successfully"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
}
?>