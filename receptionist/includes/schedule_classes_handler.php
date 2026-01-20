<?php
// receptionist/includes/schedule_classes_handler.php

header('Content-Type: application/json; charset=utf-8');
session_start();

// Optional: ensure receptionist is logged-in (same as your page)
if (!isset($_SESSION['employee_id'])) {
    // not fatal: you might want to allow read-only access; uncomment to block
    // echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

require_once __DIR__ . '/../../config/connection.php'; // Fixed path: go up 2 levels to root

try {
    $pdo = getDBConnection(); // expects PDO
    $action = $_GET['action'] ?? '';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed', 'error' => $e->getMessage()]);
    exit;
}

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
    if ($action === 'get_classes') {
        // Return all schedules with class and coach info
        $sql = "
            SELECT
                cs.schedule_id,
                cs.class_id,
                cs.schedule_date,
                DATE_FORMAT(cs.schedule_date, '%Y-%m-%d') AS schedule_date_iso,
                TIME_FORMAT(cs.start_time, '%H:%i') AS start_time,
                TIME_FORMAT(cs.end_time, '%H:%i') AS end_time,
                cs.max_capacity,
                COALESCE(cs.current_bookings, 0) AS current_bookings,
                cs.room_location,
                cs.status,
                c.class_name,
                c.class_type,
                c.description,
                COALESCE(CONCAT(e.first_name, ' ', e.last_name), 'TBA') AS coach_name
            FROM class_schedules cs
            LEFT JOIN classes c ON cs.class_id = c.class_id
            LEFT JOIN employees e ON c.coach_id = e.employee_id
            ORDER BY cs.schedule_date ASC, cs.start_time ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ensure JS-friendly formats (strings)
        foreach ($rows as &$r) {
            // Ensure integers
            $r['max_capacity'] = (int)($r['max_capacity'] ?? 0);
            $r['current_bookings'] = (int)($r['current_bookings'] ?? 0);
            $r['schedule_id'] = (int)($r['schedule_id'] ?? 0);
            $r['class_id'] = (int)($r['class_id'] ?? 0);
        }

        json_ok(['classes' => $rows]);

    } elseif ($action === 'get_stats') {
        // Today's classes
        $todaySql = "SELECT COUNT(*) FROM class_schedules WHERE schedule_date = CURDATE()";
        $todayCount = (int)$pdo->query($todaySql)->fetchColumn();

        // Total bookings (count of class_bookings rows with statuses that indicate an active booking)
        // We use statuses 'Booked' and 'Attended' as bookings; adjust if your app uses other statuses.
        $bookingsSql = "SELECT COUNT(*) FROM class_bookings WHERE status IN ('Booked','Attended')";
        $bookingsCount = (int)$pdo->query($bookingsSql)->fetchColumn();

        // Upcoming this week (next 7 days including today)
        $weekSql = "SELECT COUNT(*) FROM class_schedules WHERE schedule_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)";
        $weekCount = (int)$pdo->query($weekSql)->fetchColumn();

        json_ok(['stats' => [
            'today' => $todayCount,
            'bookings' => $bookingsCount,
            'week' => $weekCount
        ]]);

    } elseif ($action === 'get_class_details') {
        $schedule_id = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;
        if (!$schedule_id) json_err('Missing schedule_id');

        $sql = "
            SELECT
                cs.schedule_id,
                cs.class_id,
                cs.schedule_date,
                TIME_FORMAT(cs.start_time, '%H:%i') AS start_time,
                TIME_FORMAT(cs.end_time, '%H:%i') AS end_time,
                cs.max_capacity,
                cs.room_location,
                cs.status,
                cs.current_bookings,
                c.class_name,
                c.class_type,
                c.description,
                c.default_capacity,
                COALESCE(CONCAT(e.first_name, ' ', e.last_name), 'TBA') AS coach_name
            FROM class_schedules cs
            LEFT JOIN classes c ON cs.class_id = c.class_id
            LEFT JOIN employees e ON c.coach_id = e.employee_id
            WHERE cs.schedule_id = :schedule_id
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':schedule_id' => $schedule_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            json_err('Schedule not found');
        }

        // normalize types
        $row['max_capacity'] = (int)($row['max_capacity'] ?? 0);
        $row['current_bookings'] = (int)($row['current_bookings'] ?? 0);
        $row['schedule_id'] = (int)($row['schedule_id'] ?? 0);
        $row['class_id'] = (int)($row['class_id'] ?? 0);
        $row['default_capacity'] = (int)($row['default_capacity'] ?? 0);

        json_ok(['class' => $row]);

    } elseif ($action === 'get_participants') {
        $schedule_id = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;
        if (!$schedule_id) json_err('Missing schedule_id');

        // Get participants from class_bookings -> clients
        $sql = "
            SELECT
                cb.booking_id,
                cb.member_id,
                COALESCE(CONCAT(cl.first_name, ' ', cl.last_name), '') AS full_name,
                cl.profile_image,
                cb.status,
                DATE_FORMAT(cb.booking_date, '%Y-%m-%d %H:%i:%s') AS booking_date
            FROM class_bookings cb
            LEFT JOIN clients cl ON cb.member_id = cl.client_id
            WHERE cb.schedule_id = :schedule_id
            ORDER BY cb.booking_date ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':schedule_id' => $schedule_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format minimal participant objects used by UI
        $participants = [];
        foreach ($rows as $r) {
            $participants[] = [
                'booking_id' => (int)$r['booking_id'],
                'member_id' => (int)$r['member_id'],
                'full_name' => $r['full_name'] ?: 'Unknown',
                'profile_image' => $r['profile_image'] ?? null,
                'status' => $r['status'],
                'booking_date' => $r['booking_date']
            ];
        }

        json_ok(['participants' => $participants]);

    } else {
        json_err('Invalid action');
    }
} catch (PDOException $ex) {
    json_err('Database error', ['error' => $ex->getMessage()]);
} catch (Exception $e) {
    json_err('Server error', ['error' => $e->getMessage()]);
}
