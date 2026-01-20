<?php
session_start();

require_once '../../config/connection.php';
$conn = getDBConnection();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'get_otc_bookings') {
        // Get all OTC bookings from booking_requests
        $sql = "SELECT 
                    br.request_id,
                    br.client_id,
                    CONCAT(c.first_name, ' ', c.last_name) as client_name,
                    br.booking_type,
                    COALESCE(cl.class_name, 'N/A') as class_name,
                    br.scheduled_date,
                    br.scheduled_time,
                    br.service_rate as amount,
                    br.status,
                    br.payment_date,
                    br.payment_proof,
                    br.reference_number,
                    br.notes,
                    br.payment_method
                FROM booking_requests br
                LEFT JOIN clients c ON br.client_id = c.client_id
                LEFT JOIN classes cl ON br.class_id = cl.class_id
                WHERE br.payment_method = 'Over the Counter'
                AND br.status IN ('Pending Payment', 'Payment Submitted', 'Rejected')
                ORDER BY br.updated_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $bookings
        ]);
        
    } elseif ($action === 'get_otc_booking') {
        $requestId = $_POST['request_id'] ?? 0;
        
        $sql = "SELECT 
                    br.request_id,
                    br.client_id,
                    CONCAT(c.first_name, ' ', c.last_name) as client_name,
                    c.email,
                    c.phone,
                    br.booking_type,
                    br.class_id,
                    COALESCE(cl.class_name, 'N/A') as class_name,
                    br.scheduled_date,
                    br.scheduled_time,
                    br.scheduled_end_time,
                    br.service_rate as amount,
                    br.status,
                    br.payment_date,
                    br.payment_proof,
                    br.reference_number,
                    br.notes,
                    br.payment_method
                FROM booking_requests br
                LEFT JOIN clients c ON br.client_id = c.client_id
                LEFT JOIN classes cl ON br.class_id = cl.class_id
                WHERE br.request_id = :request_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':request_id' => $requestId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            echo json_encode([
                'success' => true,
                'data' => $booking
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Booking not found'
            ]);
        }
        
    } elseif ($action === 'approve_otc_booking') {
        $requestId = $_POST['request_id'] ?? 0;
        
        // Update booking request status to "Payment Verified"
        $sql = "UPDATE booking_requests 
                SET status = 'Payment Verified',
                    verified_by = :verified_by,
                    verified_at = NOW()
                WHERE request_id = :request_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':verified_by' => $_SESSION['employee_id'],
            ':request_id' => $requestId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'OTC booking approved successfully'
        ]);
        
    } elseif ($action === 'reject_otc_booking') {
        $requestId = $_POST['request_id'] ?? 0;
        $reason = $_POST['reason'] ?? '';
        
        // Update booking request status to "Rejected"
        $sql = "UPDATE booking_requests 
                SET status = 'Rejected',
                    rejection_reason = :reason,
                    verified_by = :verified_by,
                    verified_at = NOW()
                WHERE request_id = :request_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':reason' => $reason,
            ':verified_by' => $_SESSION['employee_id'],
            ':request_id' => $requestId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'OTC booking rejected successfully'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
