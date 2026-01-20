<?php
// manager/includes/dashboard_data.php

header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/connection.php';

function json_ok($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

function json_err($msg = 'An error occurred', $extra = []) {
    $out = ['success' => false, 'message' => $msg];
    if (!empty($extra)) $out = array_merge($out, $extra);
    echo json_encode($out);
    exit;
}

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        json_err('Database connection failed');
    }
    
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get_stats') {
        try {
            // Total Active Coaches
            $sql = "SELECT COUNT(*) as count FROM employees WHERE role = 'Coach' AND status = 'Active'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $totalCoaches = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            throw new Exception('Error loading coaches: ' . $e->getMessage());
        }
        
        try {
            // Total Active Staff (Receptionists, Admin, etc)
            $sql = "SELECT COUNT(*) as count FROM employees WHERE role != 'Coach' AND status = 'Active'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $totalStaff = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            throw new Exception('Error loading staff: ' . $e->getMessage());
        }
        
        try {
            // Pending Time-off Requests
            $sql = "SELECT COUNT(*) as count FROM booking_requests WHERE status = 'Pending Payment'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $pendingRequests = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            throw new Exception('Error loading requests: ' . $e->getMessage());
        }
        
        try {
            // Active Coach Assignments (clients with assigned coaches)
            $sql = "SELECT COUNT(DISTINCT assigned_coach_id) as count FROM clients WHERE assigned_coach_id IS NOT NULL";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $activeAssignments = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            throw new Exception('Error loading assignments: ' . $e->getMessage());
        }
        
        json_ok([
            'coaches' => $totalCoaches,
            'staff' => $totalStaff,
            'requests' => $pendingRequests,
            'assignments' => $activeAssignments
        ]);
        
    } elseif ($action === 'get_activity') {
        try {
            $sql = "
                SELECT 
                    'assignment' as type,
                    CONCAT('Coach assigned to client') as title,
                    DATE_FORMAT(NOW(), '%i minutes ago') as time,
                    'user-plus' as icon
                FROM clients
                WHERE assigned_coach_id IS NOT NULL
                ORDER BY RAND()
                LIMIT 4
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            json_ok(['activities' => $activities]);
        } catch (Exception $e) {
            json_err('Error loading activities: ' . $e->getMessage());
        }
        
    } elseif ($action === 'get_approvals') {
        try {
            $sql = "
                SELECT 
                    br.request_id,
                    CONCAT(c.first_name, ' ', c.last_name) as client_name,
                    br.booking_type,
                    DATE_FORMAT(br.preferred_date, '%b %d, %Y') as date,
                    br.status
                FROM booking_requests br
                LEFT JOIN clients c ON br.client_id = c.client_id
                WHERE br.status = 'Pending Payment'
                LIMIT 3
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            json_ok(['approvals' => $approvals]);
        } catch (Exception $e) {
            json_err('Error loading approvals: ' . $e->getMessage());
        }
        
    } else {
        json_err('Invalid action');
    }
    
} catch (PDOException $ex) {
    json_err('Database error', ['error' => $ex->getMessage(), 'code' => $ex->getCode()]);
} catch (Exception $e) {
    json_err('Server error', ['error' => $e->getMessage(), 'code' => $e->getCode()]);
}
?>
