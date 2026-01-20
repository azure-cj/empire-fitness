<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Super Admin', 'Admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../../config/connection.php';
$conn = getDBConnection();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch ($action) {
        case 'get_dashboard_stats':
            getDashboardStats();
            break;
        
        case 'get_recent_activity':
            getRecentActivity();
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// Get main dashboard statistics
function getDashboardStats() {
    global $conn;
    
    try {
        // Total Active Members
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM clients WHERE client_type = 'Member' AND status = 'Active'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalMembers = $result['count'] ?? 0;
        
        // Monthly Revenue (from attendance_log with amount field)
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM attendance_log 
            WHERE status = 'Completed' 
            AND amount > 0
            AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $monthlyRevenue = $result['total'] ?? 0;
        
        // Walk-in Today
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM attendance_log 
            WHERE attendance_type = 'Walk-in' 
            AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $walkInsToday = $result['count'] ?? 0;
        
        // Active Employees
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE status = 'Active'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $activeEmployees = $result['count'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_members' => (int)$totalMembers,
                'monthly_revenue' => (float)$monthlyRevenue,
                'walk_ins_today' => (int)$walkInsToday,
                'active_employees' => (int)$activeEmployees
            ]
        ]);
    } catch (Exception $e) {
        throw new Exception("Failed to fetch dashboard stats: " . $e->getMessage());
    }
}

// Get recent activity
function getRecentActivity() {
    global $conn;
    
    try {
        $activities = [];
        
        // Recent check-ins today
        $stmt = $conn->prepare("
            SELECT 
                'check_in' as type,
                CASE 
                    WHEN client_id IS NOT NULL THEN 'Client checked in'
                    ELSE CONCAT(COALESCE(guest_name, 'Guest'), ' checked in')
                END as title,
                created_at,
                'door-open' as icon
            FROM attendance_log
            WHERE DATE(created_at) = CURDATE()
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent payments (last 7 days)
        $stmt = $conn->prepare("
            SELECT 
                'payment_received' as type,
                CONCAT('Payment - ₱', FORMAT(COALESCE(amount, 0), 2)) as title,
                created_at,
                'money-bill-wave' as icon
            FROM attendance_log
            WHERE status = 'Completed'
            AND amount > 0
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $activities = array_merge($activities, $payments);
        
        // Sort by created_at descending and limit to 10 most recent
        usort($activities, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        $activities = array_slice($activities, 0, 10);
        
        echo json_encode([
            'success' => true,
            'data' => $activities
        ]);
    } catch (Exception $e) {
        throw new Exception("Failed to fetch recent activity: " . $e->getMessage());
    }
}
?>
