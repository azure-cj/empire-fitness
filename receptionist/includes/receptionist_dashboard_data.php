<?php
/**
 * Receptionist Dashboard Data Handler
 * This file fetches all necessary data for the receptionist dashboard
 */

// Ensure this file is included from receptionistDashboard.php
if (!isset($conn)) {
    die("Database connection not found");
}

// Initialize all variables with default values
$todayCheckins = 0;
$todayRevenue = 0;
$pendingPayments = 0;
$todayClasses = 0;
$currentlyInside = 0;
$totalMembers = 0;
$recentActivities = [];
$todaysClasses = [];

try {
    // 1. Today's Check-ins (from attendance_log)
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM attendance_log 
        WHERE DATE(log_date) = CURDATE()
    ");
    $stmt->execute();
    $todayCheckins = $stmt->fetchColumn();

    // 2. Today's Revenue from Daily Entrance (from unified_payments)
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) 
        FROM unified_payments 
        WHERE DATE(payment_date) = CURDATE() 
        AND payment_type = 'Daily'
        AND payment_status = 'Paid'
    ");
    $stmt->execute();
    $todayRevenue = $stmt->fetchColumn();

    // 3. Pending Payments (from unified_payments)
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM unified_payments 
        WHERE payment_status = 'Pending'
    ");
    $stmt->execute();
    $pendingPayments = $stmt->fetchColumn();

    // 4. Today's Scheduled Classes (from class_schedules)
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM class_schedules 
        WHERE DATE(schedule_date) = CURDATE()
        AND status = 'Scheduled'
    ");
    $stmt->execute();
    $todayClasses = $stmt->fetchColumn();

    // 5. Currently Checked In Members (those who haven't checked out today)
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM attendance_log 
        WHERE DATE(log_date) = CURDATE() 
        AND time_out IS NULL
    ");
    $stmt->execute();
    $currentlyInside = $stmt->fetchColumn();

    // 6. Total Active Members (from clients)
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM clients 
        WHERE status = 'Active' 
        AND client_type = 'Member'
    ");
    $stmt->execute();
    $totalMembers = $stmt->fetchColumn();

    // 7. Recent Activities (Last 10 activities)
    $stmt = $conn->prepare("
        SELECT 
            al.attendance_id,
            al.client_id,
            CONCAT(c.first_name, ' ', c.last_name) as client_name,
            al.guest_name,
            al.attendance_type,
            al.time_in,
            al.time_out,
            al.log_date,
            al.check_in_timestamp,
            al.check_out_timestamp,
            al.payment_id
        FROM attendance_log al
        LEFT JOIN clients c ON al.client_id = c.client_id
        WHERE DATE(al.log_date) = CURDATE()
        ORDER BY al.check_in_timestamp DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Today's Class Schedule with Details
    $stmt = $conn->prepare("
        SELECT 
            cs.schedule_id,
            cs.schedule_date,
            cs.start_time,
            cs.end_time,
            cs.max_capacity,
            cs.current_bookings,
            cs.room_location,
            cs.status,
            c.class_id,
            c.class_name,
            c.class_type,
            c.duration,
            c.coach_id,
            e.first_name as coach_first_name,
            e.last_name as coach_last_name
        FROM class_schedules cs
        INNER JOIN classes c ON cs.class_id = c.class_id
        LEFT JOIN employees e ON c.coach_id = e.employee_id
        WHERE DATE(cs.schedule_date) = CURDATE()
        AND cs.status = 'Scheduled'
        ORDER BY cs.start_time ASC
    ");
    $stmt->execute();
    $todaysClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 9. Pending Payment Details (for display)
    $stmt = $conn->prepare("
        SELECT 
            up.payment_id,
            up.client_id,
            CONCAT(c.first_name, ' ', c.last_name) as client_name,
            up.payment_type,
            up.amount,
            up.payment_date,
            up.payment_method,
            up.payment_status,
            up.remarks
        FROM unified_payments up
        INNER JOIN clients c ON up.client_id = c.client_id
        WHERE up.payment_status = 'Pending'
        ORDER BY up.payment_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $pendingPaymentDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 10. Today's Revenue by Payment Type (for detailed stats)
    $stmt = $conn->prepare("
        SELECT 
            payment_type,
            COUNT(*) as transaction_count,
            SUM(amount) as total_amount
        FROM unified_payments 
        WHERE DATE(payment_date) = CURDATE()
        AND payment_status = 'Paid'
        GROUP BY payment_type
    ");
    $stmt->execute();
    $revenueByType = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 11. Member vs Walk-in Check-ins Today
    $stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN c.client_type = 'Member' THEN 1 END) as members_checked_in,
            COUNT(CASE WHEN c.client_type = 'Walk-in' THEN 1 END) as walkins_checked_in,
            COUNT(CASE WHEN al.client_id IS NULL THEN 1 END) as guest_checked_in
        FROM attendance_log al
        LEFT JOIN clients c ON al.client_id = c.client_id
        WHERE DATE(al.log_date) = CURDATE()
    ");
    $stmt->execute();
    $checkinBreakdown = $stmt->fetch(PDO::FETCH_ASSOC);

    // 12. Active Class Bookings for Today
    $stmt = $conn->prepare("
        SELECT 
            cb.booking_id,
            cb.member_id,
            CONCAT(c.first_name, ' ', c.last_name) as member_name,
            cl.class_name,
            cs.start_time,
            cs.end_time,
            cb.status,
            cb.booking_type
        FROM class_bookings cb
        INNER JOIN clients c ON cb.member_id = c.client_id
        INNER JOIN class_schedules cs ON cb.schedule_id = cs.schedule_id
        INNER JOIN classes cl ON cs.class_id = cl.class_id
        WHERE DATE(cs.schedule_date) = CURDATE()
        AND cb.status IN ('Booked', 'Attended')
        ORDER BY cs.start_time ASC
    ");
    $stmt->execute();
    $todaysBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 13. Expiring Memberships This Week (for alerts)
    $stmt = $conn->prepare("
        SELECT 
            cm.id,
            cm.client_id,
            CONCAT(c.first_name, ' ', c.last_name) as client_name,
            c.phone,
            c.email,
            m.plan_name,
            cm.end_date,
            DATEDIFF(cm.end_date, CURDATE()) as days_remaining
        FROM client_memberships cm
        INNER JOIN clients c ON cm.client_id = c.client_id
        INNER JOIN memberships m ON cm.membership_id = m.membership_id
        WHERE cm.status = 'Active'
        AND cm.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY cm.end_date ASC
        LIMIT 5
    ");
    $stmt->execute();
    $expiringMemberships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 14. Today's Payment Methods Breakdown
    $stmt = $conn->prepare("
        SELECT 
            payment_method,
            COUNT(*) as count,
            SUM(amount) as total
        FROM unified_payments
        WHERE DATE(payment_date) = CURDATE()
        AND payment_status = 'Paid'
        GROUP BY payment_method
    ");
    $stmt->execute();
    $paymentMethodBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 15. Recent Inquiries (if receptionist handles them)
    $stmt = $conn->prepare("
        SELECT 
            inquiry_id,
            CONCAT(first_name, ' ', last_name) as inquirer_name,
            email,
            phone,
            subject,
            status,
            submitted_at
        FROM inquiries
        WHERE status = 'new'
        ORDER BY submitted_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentInquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log error but don't expose details to user
    error_log("Dashboard data fetch error: " . $e->getMessage());
    
    // Set all values to defaults if there's an error
    $todayCheckins = 0;
    $todayRevenue = 0;
    $pendingPayments = 0;
    $todayClasses = 0;
    $currentlyInside = 0;
    $totalMembers = 0;
    $recentActivities = [];
    $todaysClasses = [];
    $pendingPaymentDetails = [];
    $revenueByType = [];
    $checkinBreakdown = ['members_checked_in' => 0, 'walkins_checked_in' => 0, 'guest_checked_in' => 0];
    $todaysBookings = [];
    $expiringMemberships = [];
    $paymentMethodBreakdown = [];
    $recentInquiries = [];
}

// Helper function to format time ago
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return $diff . ' second' . ($diff != 1 ? 's' : '') . ' ago';
    }
    
    $diff = round($diff / 60);
    if ($diff < 60) {
        return $diff . ' minute' . ($diff != 1 ? 's' : '') . ' ago';
    }
    
    $diff = round($diff / 60);
    if ($diff < 24) {
        return $diff . ' hour' . ($diff != 1 ? 's' : '') . ' ago';
    }
    
    $diff = round($diff / 24);
    return $diff . ' day' . ($diff != 1 ? 's' : '') . ' ago';
}

// Helper function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Helper function to get class icon based on type
function getClassIcon($classType) {
    $icons = [
        'Boxing' => 'fa-boxing-glove',
        'Kickboxing' => 'fa-hand-fist',
        'Muay Thai' => 'fa-hand-back-fist',
        'Personal Training' => 'fa-user-tie',
        'Zumba' => 'fa-music',
        'HIIT' => 'fa-fire',
        'Other' => 'fa-dumbbell'
    ];
    
    return $icons[$classType] ?? 'fa-dumbbell';
}

// Helper function to get activity icon
function getActivityIcon($activityType, $hasTimeOut = false) {
    if ($hasTimeOut) {
        return 'fa-sign-out-alt';
    }
    
    return 'fa-sign-in-alt';
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    $classes = [
        'Active' => 'success',
        'Pending' => 'warning',
        'Paid' => 'success',
        'Completed' => 'success',
        'Cancelled' => 'danger',
        'Expired' => 'danger',
        'Scheduled' => 'info',
        'Booked' => 'primary'
    ];
    
    return $classes[$status] ?? 'secondary';
}
?>