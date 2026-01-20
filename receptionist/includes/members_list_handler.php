<?php
session_start();

// Check if user is logged in and has receptionist role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Receptionist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../../config/connection.php';
$conn = getDBConnection();

header('Content-Type: application/json');

// Get action from request
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_stats':
            getStatistics($conn);
            break;
        
        case 'get_members':
            getMembers($conn);
            break;
        
        case 'get_member_details':
            getMemberDetails($conn);
            break;
        
        case 'export_members':
            exportMembers($conn);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Get statistics for dashboard cards
function getStatistics($conn) {
    try {
        // Total members
        $totalQuery = "SELECT COUNT(*) as total FROM clients";
        $stmt = $conn->query($totalQuery);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Active members
        $activeQuery = "SELECT COUNT(*) as active FROM clients WHERE status = 'Active'";
        $stmt = $conn->query($activeQuery);
        $active = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
        
        // Expiring memberships (within 7 days)
        $expiringQuery = "
            SELECT COUNT(DISTINCT c.client_id) as expiring
            FROM clients c
            INNER JOIN client_memberships cm ON c.client_id = cm.client_id
            WHERE cm.status = 'Active'
            AND DATEDIFF(cm.end_date, CURDATE()) BETWEEN 0 AND 7
        ";
        $stmt = $conn->query($expiringQuery);
        $expiring = $stmt->fetch(PDO::FETCH_ASSOC)['expiring'];
        
        // Inactive members
        $inactiveQuery = "SELECT COUNT(*) as inactive FROM clients WHERE status = 'Inactive'";
        $stmt = $conn->query($inactiveQuery);
        $inactive = $stmt->fetch(PDO::FETCH_ASSOC)['inactive'];
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total' => (int)$total,
                'active' => (int)$active,
                'expiring' => (int)$expiring,
                'inactive' => (int)$inactive
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching statistics: ' . $e->getMessage()]);
    }
}

// Get all members with their membership information
function getMembers($conn) {
    try {
        $query = "
            SELECT 
                c.client_id,
                CONCAT(c.first_name, ' ', COALESCE(CONCAT(c.middle_name, ' '), ''), c.last_name) as full_name,
                c.email,
                c.phone,
                c.client_type,
                c.status,
                c.join_date,
                c.last_login,
                c.is_verified,
                m.plan_name as membership_plan,
                cm.start_date as membership_start,
                cm.end_date as membership_end,
                DATEDIFF(cm.end_date, CURDATE()) as days_remaining,
                coach.first_name as coach_first_name,
                coach.last_name as coach_last_name,
                -- Base membership (is_base_membership = 1) days remaining and plan
                (
                    SELECT DATEDIFF(cm2.end_date, CURDATE())
                    FROM client_memberships cm2
                    JOIN memberships m2 ON cm2.membership_id = m2.membership_id
                    WHERE cm2.client_id = c.client_id AND cm2.status = 'Active' AND m2.is_base_membership = 1
                    LIMIT 1
                ) as base_days_remaining,
                (
                    SELECT m2.plan_name
                    FROM client_memberships cm2
                    JOIN memberships m2 ON cm2.membership_id = m2.membership_id
                    WHERE cm2.client_id = c.client_id AND cm2.status = 'Active' AND m2.is_base_membership = 1
                    LIMIT 1
                ) as base_plan_name,
                -- Monthly plan (heuristic: duration ~30 days or name contains 'month')
                (
                    SELECT m3.plan_name
                    FROM client_memberships cm3
                    JOIN memberships m3 ON cm3.membership_id = m3.membership_id
                    WHERE cm3.client_id = c.client_id AND cm3.status = 'Active' AND (m3.duration_days BETWEEN 28 AND 31 OR LOWER(m3.plan_name) LIKE '%month%')
                    LIMIT 1
                ) as monthly_plan_name,
                (
                    SELECT DATEDIFF(cm3.end_date, CURDATE())
                    FROM client_memberships cm3
                    JOIN memberships m3 ON cm3.membership_id = m3.membership_id
                    WHERE cm3.client_id = c.client_id AND cm3.status = 'Active' AND (m3.duration_days BETWEEN 28 AND 31 OR LOWER(m3.plan_name) LIKE '%month%')
                    LIMIT 1
                ) as monthly_days_remaining
            FROM clients c
            LEFT JOIN client_memberships cm ON c.client_id = cm.client_id AND cm.status = 'Active'
            LEFT JOIN memberships m ON cm.membership_id = m.membership_id
            LEFT JOIN coach ON c.assigned_coach_id = coach.coach_id
            ORDER BY c.client_id DESC
        ";
        
        $stmt = $conn->query($query);
        $members = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $members[] = [
                'client_id' => (int)$row['client_id'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'client_type' => $row['client_type'],
                'status' => $row['status'],
                'join_date' => $row['join_date'],
                'last_login' => $row['last_login'],
                'is_verified' => (bool)$row['is_verified'],
                'membership_plan' => $row['membership_plan'],
                'membership_start' => $row['membership_start'],
                'membership_end' => $row['membership_end'],
                'days_remaining' => $row['days_remaining'],
                'coach_name' => $row['coach_first_name'] ? $row['coach_first_name'] . ' ' . $row['coach_last_name'] : null,
                'base_plan_name' => $row['base_plan_name'],
                'base_days_remaining' => $row['base_days_remaining'],
                'monthly_plan_name' => $row['monthly_plan_name'],
                'monthly_days_remaining' => $row['monthly_days_remaining']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'members' => $members
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching members: ' . $e->getMessage()]);
    }
}

// Get detailed information for a specific member
function getMemberDetails($conn) {
    try {
        $memberId = intval($_GET['member_id'] ?? 0);
        
        if ($memberId === 0) {
            throw new Exception('Invalid member ID');
        }
        
        $query = "
            SELECT 
                c.client_id,
                CONCAT(c.first_name, ' ', COALESCE(CONCAT(c.middle_name, ' '), ''), c.last_name) as full_name,
                c.email,
                c.phone,
                c.referral_source,
                c.client_type,
                c.status,
                c.join_date,
                c.last_login,
                c.is_verified,
                m.plan_name as membership_plan,
                cm.start_date as membership_start,
                cm.end_date as membership_end,
                DATEDIFF(cm.end_date, CURDATE()) as days_remaining,
                CONCAT(coach.first_name, ' ', coach.last_name) as coach_name,
                -- Base membership info
                (
                    SELECT DATEDIFF(cm2.end_date, CURDATE())
                    FROM client_memberships cm2
                    JOIN memberships m2 ON cm2.membership_id = m2.membership_id
                    WHERE cm2.client_id = c.client_id AND cm2.status = 'Active' AND m2.is_base_membership = 1
                    LIMIT 1
                ) as base_days_remaining,
                (
                    SELECT m2.plan_name
                    FROM client_memberships cm2
                    JOIN memberships m2 ON cm2.membership_id = m2.membership_id
                    WHERE cm2.client_id = c.client_id AND cm2.status = 'Active' AND m2.is_base_membership = 1
                    LIMIT 1
                ) as base_plan_name,
                -- Monthly plan info
                (
                    SELECT m3.plan_name
                    FROM client_memberships cm3
                    JOIN memberships m3 ON cm3.membership_id = m3.membership_id
                    WHERE cm3.client_id = c.client_id AND cm3.status = 'Active' AND (m3.duration_days BETWEEN 28 AND 31 OR LOWER(m3.plan_name) LIKE '%month%')
                    LIMIT 1
                ) as monthly_plan_name,
                (
                    SELECT DATEDIFF(cm3.end_date, CURDATE())
                    FROM client_memberships cm3
                    JOIN memberships m3 ON cm3.membership_id = m3.membership_id
                    WHERE cm3.client_id = c.client_id AND cm3.status = 'Active' AND (m3.duration_days BETWEEN 28 AND 31 OR LOWER(m3.plan_name) LIKE '%month%')
                    LIMIT 1
                ) as monthly_days_remaining
            FROM clients c
            LEFT JOIN client_memberships cm ON c.client_id = cm.client_id AND cm.status = 'Active'
            LEFT JOIN memberships m ON cm.membership_id = m.membership_id
            LEFT JOIN coach ON c.assigned_coach_id = coach.coach_id
            WHERE c.client_id = :member_id
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':member_id', $memberId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode([
                'success' => true,
                'member' => [
                    'client_id' => (int)$row['client_id'],
                    'full_name' => $row['full_name'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'referral_source' => $row['referral_source'],
                    'client_type' => $row['client_type'],
                    'status' => $row['status'],
                    'join_date' => $row['join_date'],
                    'last_login' => $row['last_login'],
                    'is_verified' => (bool)$row['is_verified'],
                    'membership_plan' => $row['membership_plan'],
                    'membership_start' => $row['membership_start'],
                    'membership_end' => $row['membership_end'],
                    'days_remaining' => $row['days_remaining'],
                    'coach_name' => $row['coach_name'],
                    'base_plan_name' => $row['base_plan_name'],
                    'base_days_remaining' => $row['base_days_remaining'],
                    'monthly_plan_name' => $row['monthly_plan_name'],
                    'monthly_days_remaining' => $row['monthly_days_remaining']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Member not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching member details: ' . $e->getMessage()]);
    }
}

// Export members to CSV
function exportMembers($conn) {
    try {
        $query = "
            SELECT 
                c.client_id,
                CONCAT(c.first_name, ' ', COALESCE(CONCAT(c.middle_name, ' '), ''), c.last_name) as full_name,
                c.email,
                c.phone,
                c.client_type,
                c.status,
                c.join_date,
                c.referral_source,
                m.plan_name as membership_plan,
                cm.start_date as membership_start,
                cm.end_date as membership_end,
                DATEDIFF(cm.end_date, CURDATE()) as days_remaining
            FROM clients c
            LEFT JOIN client_memberships cm ON c.client_id = cm.client_id AND cm.status = 'Active'
            LEFT JOIN memberships m ON cm.membership_id = m.membership_id
            ORDER BY c.client_id DESC
        ";
        
        $stmt = $conn->query($query);
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="members_export_' . date('Y-m-d') . '.csv"');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'Member ID',
            'Full Name',
            'Email',
            'Phone',
            'Type',
            'Status',
            'Join Date',
            'Referral Source',
            'Membership Plan',
            'Membership Start',
            'Membership End',
            'Days Remaining'
        ]);
        
        // Add data rows
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['client_id'],
                $row['full_name'],
                $row['email'] ?? 'N/A',
                $row['phone'] ?? 'N/A',
                $row['client_type'],
                $row['status'],
                $row['join_date'],
                $row['referral_source'] ?? 'N/A',
                $row['membership_plan'] ?? 'None',
                $row['membership_start'] ?? 'N/A',
                $row['membership_end'] ?? 'N/A',
                $row['days_remaining'] ?? 'N/A'
            ]);
        }
        
        fclose($output);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error exporting members: ' . $e->getMessage()]);
    }
}

// Connection is automatically closed when script ends with PDO
?>