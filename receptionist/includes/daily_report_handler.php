<?php
// receptionist/includes/daily_report_handler.php

header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['employee_id'])) {
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
    $report_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    if ($action === 'get_summary') {
        try {
            // Total Entries
            $entriesSql = "SELECT COUNT(*) FROM attendance_log WHERE log_date = :date";
            $stmt = $pdo->prepare($entriesSql);
            $stmt->execute([':date' => $report_date]);
            $totalEntries = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception('Error loading entries: ' . $e->getMessage());
        }
        
        try {
            // Total Revenue - Sum actual payment amounts from unified_payments
            $revenueSql = "
                SELECT COALESCE(SUM(up.amount), 0) as total
                FROM unified_payments up
                WHERE DATE(up.payment_date) = :date 
                AND up.payment_status = 'Paid'
            ";
            $stmt = $pdo->prepare($revenueSql);
            if (!$stmt) {
                throw new Exception('Failed to prepare revenue query');
            }
            $stmt->execute([':date' => $report_date]);
            $totalRevenue = (float)$stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {
            throw new Exception('Error loading revenue: ' . $e->getMessage());
        }
        
        try {
            // Classes Held
            $classesSql = "SELECT COUNT(*) FROM class_schedules WHERE schedule_date = :date AND status IN ('Scheduled', 'Completed')";
            $stmt = $pdo->prepare($classesSql);
            $stmt->execute([':date' => $report_date]);
            $classesHeld = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception('Error loading classes: ' . $e->getMessage());
        }
        
        try {
            // Members Attended
            $membersSql = "SELECT COUNT(DISTINCT client_id) FROM attendance_log WHERE log_date = :date AND client_id IS NOT NULL";
            $stmt = $pdo->prepare($membersSql);
            $stmt->execute([':date' => $report_date]);
            $membersAttended = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception('Error loading members: ' . $e->getMessage());
        }
        
        try {
            // Revenue by Payment Method
            $paymentMethodSql = "
                SELECT 
                    COALESCE(up.payment_method, 'Cash') as method, 
                    COUNT(*) as count,
                    SUM(up.amount) as total_amount
                FROM unified_payments up
                WHERE DATE(up.payment_date) = :date 
                AND up.payment_status = 'Paid'
                GROUP BY up.payment_method
            ";
            $stmt = $pdo->prepare($paymentMethodSql);
            $stmt->execute([':date' => $report_date]);
            $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If no payments, add a default entry
            if (empty($paymentMethods)) {
                $paymentMethods = [
                    ['method' => 'No Payments', 'count' => 0, 'total_amount' => 0]
                ];
            }
        } catch (Exception $e) {
            throw new Exception('Error loading payment methods: ' . $e->getMessage());
        }
        
        try {
            // Entries by Type
            $entryTypeSql = "
                SELECT 
                    COALESCE(attendance_type, 'Walk-in') as attendance_type, 
                    COUNT(*) as count
                FROM attendance_log
                WHERE log_date = :date
                GROUP BY attendance_type
            ";
            $stmt = $pdo->prepare($entryTypeSql);
            $stmt->execute([':date' => $report_date]);
            $entryTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($entryTypes)) {
                $entryTypes = [['attendance_type' => 'No Data', 'count' => 0]];
            }
        } catch (Exception $e) {
            throw new Exception('Error loading entry types: ' . $e->getMessage());
        }
        
        json_ok([
            'summary' => [
                'total_entries' => $totalEntries,
                'total_revenue' => $totalRevenue,
                'classes_held' => $classesHeld,
                'members_attended' => $membersAttended
            ],
            'payment_methods' => $paymentMethods,
            'entry_types' => $entryTypes
        ]);
        
    } elseif ($action === 'get_attendance') {
        try {
            $sql = "
                SELECT
                    al.attendance_id,
                    al.time_in,
                    COALESCE(CONCAT(c.first_name, ' ', c.last_name), al.guest_name, 'Walk-in Guest') as name,
                    COALESCE(al.attendance_type, 'Walk-in') as attendance_type,
                    COALESCE(al.discount_type, 'Regular') as discount_type,
                    CASE 
                        WHEN al.time_out IS NOT NULL THEN TIMEDIFF(al.time_out, al.time_in)
                        ELSE 'Still Inside'
                    END as duration,
                    COALESCE(al.status, 'Completed') as status
                FROM attendance_log al
                LEFT JOIN clients c ON al.client_id = c.client_id
                WHERE al.log_date = :date
                ORDER BY al.time_in DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':date' => $report_date]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_ok(['attendance' => $rows]);
        } catch (Exception $e) {
            json_err('Error loading attendance: ' . $e->getMessage());
        }
        
    } elseif ($action === 'get_payments') {
        try {
            $sql = "
                SELECT
                    up.payment_id,
                    up.payment_date as time_in,
                    COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Walk-in') as name,
                    COALESCE(up.payment_method, 'Cash') as payment_method,
                    COALESCE(up.amount, 0) as amount,
                    up.payment_type as discount_type,
                    COALESCE(up.payment_status, 'Pending') as payment_status
                FROM unified_payments up
                LEFT JOIN clients c ON up.client_id = c.client_id
                WHERE DATE(up.payment_date) = :date
                ORDER BY up.created_at DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':date' => $report_date]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_ok(['payments' => $rows]);
        } catch (Exception $e) {
            json_err('Error loading payments: ' . $e->getMessage());
        }
        
    } elseif ($action === 'get_classes') {
        try {
            $sql = "
                SELECT
                    cs.schedule_id,
                    cs.start_time,
                    COALESCE(c.class_name, 'Unnamed Class') as class_name,
                    COALESCE(CONCAT(e.first_name, ' ', e.last_name), 'TBA') as coach_name,
                    COALESCE(cs.current_bookings, 0) as current_bookings,
                    cs.max_capacity,
                    COALESCE(cs.status, 'Scheduled') as status
                FROM class_schedules cs
                LEFT JOIN classes c ON cs.class_id = c.class_id
                LEFT JOIN employees e ON c.coach_id = e.employee_id
                WHERE cs.schedule_date = :date
                ORDER BY cs.start_time ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':date' => $report_date]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_ok(['classes' => $rows]);
        } catch (Exception $e) {
            json_err('Error loading classes: ' . $e->getMessage());
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