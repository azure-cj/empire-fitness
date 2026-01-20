<?php
session_start();

// Debug: Check if we're in the right location
// File is at: empirefitness/admin/includes/gym_activity_handler.php
// Target is: empirefitness/config/connection.php
// So we go up 2 levels: __DIR__/../../config/connection.php

// Robust path to config/connection.php
$connectionPath = __DIR__ . '/../../config/connection.php';

// Debug (remove after testing)
if (!file_exists($connectionPath)) {
    die("Error: Cannot find connection.php at: " . realpath(__DIR__ . '/../..') . '/config/connection.php');
}

require_once $connectionPath;

// Get database connection
try {
    $conn = getDBConnection();
    if (!$conn) {
        die("Error: Database connection failed");
    }
} catch (Exception $e) {
    die("Error connecting to database: " . $e->getMessage());
}

// Fetch employee data for sidebar (FIX: This was missing!)
$employeeName = 'Admin User';
$employeeInitial = 'A';

if (isset($_SESSION['employee_id'])) {
    try {
        $stmt = $conn->prepare("SELECT first_name, last_name FROM employees WHERE employee_id = :id");
        $stmt->execute([':id' => $_SESSION['employee_id']]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            $employeeName = trim($employee['first_name'] . ' ' . $employee['last_name']);
            $employeeInitial = strtoupper(substr($employee['first_name'], 0, 1));
        }
    } catch (Exception $e) {
        // Keep default values
    }
}

// Default filter values (from GET)
$filter_source = $_GET['source'] ?? 'all'; // all | member | coach
$filter_activity_type = $_GET['activity_type'] ?? '';
$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';
$filter_search = trim($_GET['q'] ?? '');

// Helper to normalize date inputs (if provided)
$from_datetime = null;
$to_datetime = null;
if (!empty($filter_from)) {
    $d = DateTime::createFromFormat('Y-m-d', $filter_from);
    if ($d) { $from_datetime = $d->format('Y-m-d') . ' 00:00:00'; }
}
if (!empty($filter_to)) {
    $d = DateTime::createFromFormat('Y-m-d', $filter_to);
    if ($d) { $to_datetime = $d->format('Y-m-d') . ' 23:59:59'; }
}

// Build list of activity types (combined from both tables) for filter dropdown
$activity_types = [];
try {
    $stmt = $conn->query("
        SELECT DISTINCT activity_type FROM client_activity
        UNION
        SELECT DISTINCT activity_type FROM coach_activity_logs
        ORDER BY activity_type
    ");
    $activity_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $activity_types = [];
}

// Build dynamic query with filters
$queries = [];
$params = [];

// Member activities query (only if source is all or member)
if ($filter_source === 'all' || $filter_source === 'member') {
    $where = ['1=1'];
    if ($filter_activity_type !== '') {
        $where[] = 'ca.activity_type = :m_activity_type';
        $params[':m_activity_type'] = $filter_activity_type;
    }
    if ($from_datetime) {
        $where[] = 'ca.created_at >= :m_from';
        $params[':m_from'] = $from_datetime;
    }
    if ($to_datetime) {
        $where[] = 'ca.created_at <= :m_to';
        $params[':m_to'] = $to_datetime;
    }
    if ($filter_search !== '') {
        $where[] = '(ca.description LIKE :m_search OR ca.ip_address LIKE :m_search OR ca.user_agent LIKE :m_search OR c.first_name LIKE :m_search OR c.last_name LIKE :m_search)';
        $params[':m_search'] = '%' . $filter_search . '%';
    }

    $memberQuery = "
        SELECT
            ca.activity_id AS id,
            ca.client_id AS actor_id,
            'member' AS source,
            COALESCE(CONCAT(c.first_name, ' ', c.last_name), CONCAT('Member #', ca.client_id)) AS actor_name,
            ca.activity_type,
            ca.description,
            ca.ip_address,
            ca.user_agent,
            ca.created_at
        FROM client_activity ca
        LEFT JOIN clients c ON ca.client_id = c.client_id
        WHERE " . implode(' AND ', $where);
    $queries[] = $memberQuery;
}

// Coach activities query (only if source is all or coach)
if ($filter_source === 'all' || $filter_source === 'coach') {
    $where = ['1=1'];
    if ($filter_activity_type !== '') {
        $where[] = 'cl.activity_type = :c_activity_type';
        $params[':c_activity_type'] = $filter_activity_type;
    }
    if ($from_datetime) {
        $where[] = 'cl.created_at >= :c_from';
        $params[':c_from'] = $from_datetime;
    }
    if ($to_datetime) {
        $where[] = 'cl.created_at <= :c_to';
        $params[':c_to'] = $to_datetime;
    }
    if ($filter_search !== '') {
        $where[] = '(cl.description LIKE :c_search OR cl.ip_address LIKE :c_search OR cl.user_agent LIKE :c_search OR e.first_name LIKE :c_search OR e.last_name LIKE :c_search)';
        $params[':c_search'] = '%' . $filter_search . '%';
    }

    $coachQuery = "
        SELECT
            cl.log_id AS id,
            cl.coach_id AS actor_id,
            'coach' AS source,
            COALESCE(CONCAT(e.first_name, ' ', e.last_name), CONCAT('Coach #', cl.coach_id)) AS actor_name,
            cl.activity_type,
            cl.description,
            cl.ip_address,
            cl.user_agent,
            cl.created_at
        FROM coach_activity_logs cl
        LEFT JOIN employees e ON cl.coach_id = e.employee_id
        WHERE " . implode(' AND ', $where);
    $queries[] = $coachQuery;
}

// If no queries (filtered out), set empty results
$activities = [];
if (!empty($queries)) {
    // Combine queries with UNION ALL then order by created_at desc
    $sql = implode(" UNION ALL ", $queries) . " ORDER BY created_at DESC LIMIT 500";
    
    // Debug: Show the query (remove after testing)
    // echo "<pre>SQL: " . $sql . "</pre>";
    // echo "<pre>Params: " . print_r($params, true) . "</pre>";
    
    try {
        $stmt = $conn->prepare($sql);
        // Bind params (both member and coach placeholders may be set)
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Show results count
        // echo "<pre>Activities found: " . count($activities) . "</pre>";
        
    } catch (Exception $e) {
        // On error, keep $activities empty
        // For debugging: 
        error_log("Activity query error: " . $e->getMessage());
        die("Database query error: " . $e->getMessage() . "<br>SQL: " . $sql);
    }
} else {
    $activities = [];
}

// Summary stats for display (counts)
$totalActivities = count($activities);
$memberCount = 0;
$coachCount = 0;
foreach ($activities as $a) {
    if (isset($a['source']) && $a['source'] === 'member') $memberCount++;
    if (isset($a['source']) && $a['source'] === 'coach') $coachCount++;
}

// Count today's activities
$activitiestoday = 0;
try {
    $todayStmt = $conn->query("
        SELECT (
            (SELECT COUNT(*) FROM client_activity WHERE DATE(created_at) = CURDATE()) +
            (SELECT COUNT(*) FROM coach_activity_logs WHERE DATE(created_at) = CURDATE())
        ) as total
    ");
    $result = $todayStmt->fetch(PDO::FETCH_ASSOC);
    $activitiestoday = (int)$result['total'];
} catch (Exception $e) {
    $activitiestoday = 0;
}
?>