<?php
session_start();
require_once '../../config/connection.php';

// Check if user is logged in and has manager role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

$conn = getDBConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_coaches':
            getCoaches($conn);
            break;
        case 'get_classes':
            getClasses($conn);
            break;
        case 'get_class':
            getClass($conn);
            break;
        case 'save_class':
            saveClass($conn);
            break;
        case 'delete_class':
            deleteClass($conn);
            break;
        case 'approve_class':
            approveClass($conn);
            break;
        case 'reject_class':
            rejectClass($conn);
            break;
        case 'get_schedules':
        case 'get_schedules':
            getSchedules($conn);
            break;
        case 'get_schedule':
            getSchedule($conn);
            break;
        case 'save_schedule':
            saveSchedule($conn);
            break;
        case 'delete_schedule':
            deleteSchedule($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function getCoaches($conn) {
    // Query from the coach table instead of employees table
    $sql = "SELECT coach_id as employee_id, 
            CONCAT(first_name, ' ', last_name) as name, 
            email, 
            phone, 
            specialization as position, 
            status
            FROM coach 
            WHERE status = 'Active'
            ORDER BY first_name, last_name";
    
    $stmt = $conn->query($sql);
    $coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'coaches' => $coaches]);
}

function getClasses($conn) {
    // Updated to use coach table instead of employees
    $sql = "SELECT c.*, 
            CONCAT(coach.first_name, ' ', coach.last_name) as coach_name,
            coach.email as coach_email
            FROM classes c
            LEFT JOIN coach ON c.coach_id = coach.coach_id
            ORDER BY c.created_at DESC";
    
    $stmt = $conn->query($sql);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'classes' => $classes]);
}

function getClass($conn) {
    $classId = $_GET['class_id'] ?? 0;
    
    $sql = "SELECT * FROM classes WHERE class_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$classId]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($class) {
        echo json_encode(['success' => true, 'class' => $class]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
    }
}

function saveClass($conn) {
    $classId = $_POST['class_id'] ?? null;
    $className = $_POST['class_name'] ?? '';
    $classType = $_POST['class_type'] ?? '';
    $coachId = $_POST['coach_id'] ?? null;
    $duration = $_POST['duration'] ?? 60;
    $maxCapacity = $_POST['max_capacity'] ?? 10;
    $singleSessionPrice = $_POST['single_session_price'] ?? 0;
    $description = $_POST['description'] ?? null;
    $difficultyLevel = $_POST['difficulty_level'] ?? 'All Levels';
    $equipmentRequired = $_POST['equipment_required'] ?? null;
    $status = $_POST['status'] ?? 'Active';
    
    if (empty($className) || empty($classType)) {
        echo json_encode(['success' => false, 'message' => 'Class name and type are required']);
        return;
    }
    
    if ($classId) {
        // Update existing class
        $sql = "UPDATE classes SET 
                class_name = ?, 
                class_type = ?, 
                coach_id = ?, 
                duration = ?, 
                max_capacity = ?,
                default_capacity = ?,
                single_session_price = ?,
                price = ?,
                description = ?, 
                difficulty_level = ?, 
                equipment_required = ?, 
                status = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE class_id = ?";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            $className, 
            $classType, 
            $coachId, 
            $duration, 
            $maxCapacity,
            $maxCapacity,
            $singleSessionPrice,
            $singleSessionPrice,
            $description, 
            $difficultyLevel, 
            $equipmentRequired, 
            $status,
            $classId
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Class updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update class']);
        }
    } else {
        // Insert new class
        $sql = "INSERT INTO classes 
                (class_name, class_type, coach_id, duration, max_capacity, default_capacity,
                 single_session_price, price, description, difficulty_level, 
                 equipment_required, status, is_bookable) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            $className, 
            $classType, 
            $coachId, 
            $duration, 
            $maxCapacity,
            $maxCapacity,
            $singleSessionPrice,
            $singleSessionPrice,
            $description, 
            $difficultyLevel, 
            $equipmentRequired, 
            $status
        ]);
        
        if ($result) {
            $newClassId = $conn->lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Class created successfully', 'class_id' => $newClassId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create class']);
        }
    }
}
function approveClass($conn) {
    $classId = $_POST['class_id'] ?? 0;
    
    if (! $classId) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        return;
    }
    
    $sql = "UPDATE classes SET 
            status = 'Active', 
            is_bookable = 1,
            updated_at = CURRENT_TIMESTAMP
            WHERE class_id = ? ";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$classId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Class approved and is now Active & Bookable']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve class']);
    }
}

function rejectClass($conn) {
    $classId = $_POST['class_id'] ?? 0;
    $reason = $_POST['rejection_reason'] ?? 'No reason provided';
    
    if (!$classId) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        return;
    }
    
    $sql = "UPDATE classes SET 
            status = 'Rejected', 
            is_bookable = 0,
            rejection_reason = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE class_id = ? ";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$reason, $classId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Class rejected']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject class']);
    }
}

function deleteClass($conn) {
    $classId = $_POST['class_id'] ?? 0;
    
    // Check if class has schedules
    $checkSql = "SELECT COUNT(*) FROM class_schedules WHERE class_id = ? AND status = 'Scheduled'";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$classId]);
    $scheduleCount = $checkStmt->fetchColumn();
    
    if ($scheduleCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete class with scheduled sessions']);
        return;
    }
    
    $sql = "DELETE FROM classes WHERE class_id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$classId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Class deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete class']);
    }
}

function getSchedules($conn) {
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+60 days'));
    
    // Updated to use coach table instead of employees
    $sql = "SELECT cs.*, 
            c.class_name, c.class_type, c.duration as class_duration,
            CONCAT(coach.first_name, ' ', coach.last_name) as coach_name
            FROM class_schedules cs
            INNER JOIN classes c ON cs.class_id = c.class_id
            LEFT JOIN coach ON c.coach_id = coach.coach_id
            WHERE cs.schedule_date BETWEEN ? AND ?
            ORDER BY cs.schedule_date, cs.start_time";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'schedules' => $schedules]);
}

function getSchedule($conn) {
    $scheduleId = $_GET['schedule_id'] ?? 0;
    
    $sql = "SELECT cs.*, c.class_name, c.duration as class_duration
            FROM class_schedules cs
            INNER JOIN classes c ON cs.class_id = c.class_id
            WHERE cs.schedule_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$scheduleId]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($schedule) {
        echo json_encode(['success' => true, 'schedule' => $schedule]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Schedule not found']);
    }
}

function saveSchedule($conn) {
    $scheduleId = $_POST['schedule_id'] ?? null;
    $classId = $_POST['class_id'] ?? null;
    $scheduleDate = $_POST['schedule_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';
    $maxCapacity = $_POST['max_capacity'] ?? 10;
    $roomLocation = $_POST['room_location'] ?? 'Main Room';
    $status = $_POST['status'] ?? 'Scheduled';
    $notes = $_POST['notes'] ?? null;
    $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
    $recurrencePattern = $_POST['recurrence_pattern'] ?? null;
    $recurrenceEndDate = $_POST['recurrence_end_date'] ?? null;
    
    if (empty($classId) || empty($scheduleDate) || empty($startTime) || empty($endTime)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }
    
    // Check for conflicts
    $conflictSql = "SELECT COUNT(*) FROM class_schedules cs
                    INNER JOIN classes c ON cs.class_id = c.class_id
                    WHERE cs.schedule_date = ? 
                    AND cs.status = 'Scheduled'
                    AND c.coach_id = (SELECT coach_id FROM classes WHERE class_id = ?)
                    AND (
                        (cs.start_time <= ? AND cs.end_time > ?) OR
                        (cs.start_time < ? AND cs.end_time >= ?) OR
                        (cs.start_time >= ? AND cs.end_time <= ?)
                    )";
    
    if ($scheduleId) {
        $conflictSql .= " AND cs.schedule_id != ?";
    }
    
    $conflictStmt = $conn->prepare($conflictSql);
    $params = [$scheduleDate, $classId, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime];
    if ($scheduleId) {
        $params[] = $scheduleId;
    }
    $conflictStmt->execute($params);
    $hasConflict = $conflictStmt->fetchColumn() > 0;
    
    if ($hasConflict) {
        echo json_encode(['success' => false, 'message' => 'Schedule conflict detected. Coach is already scheduled at this time.']);
        return;
    }
    
    if ($scheduleId) {
        // Update existing schedule
        $sql = "UPDATE class_schedules SET 
                class_id = ?,
                schedule_date = ?,
                start_time = ?,
                end_time = ?,
                max_capacity = ?,
                room_location = ?,
                status = ?,
                notes = ?,
                is_recurring = ?,
                recurrence_pattern = ?,
                recurrence_end_date = ?
                WHERE schedule_id = ?";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            $classId, $scheduleDate, $startTime, $endTime, $maxCapacity,
            $roomLocation, $status, $notes, $isRecurring, $recurrencePattern,
            $recurrenceEndDate, $scheduleId
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update schedule']);
        }
    } else {
        // Insert new schedule(s)
        if ($isRecurring && !empty($recurrenceEndDate)) {
            // Create recurring schedules
            $createdCount = createRecurringSchedules(
                $conn, $classId, $scheduleDate, $startTime, $endTime,
                $maxCapacity, $roomLocation, $notes, $recurrencePattern, $recurrenceEndDate
            );
            
            if ($createdCount > 0) {
                echo json_encode(['success' => true, 'message' => "$createdCount recurring schedules created successfully"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create recurring schedules']);
            }
        } else {
            // Create single schedule
            $sql = "INSERT INTO class_schedules 
                    (class_id, schedule_date, start_time, end_time, max_capacity, 
                     room_location, status, notes, is_recurring, recurrence_pattern, 
                     recurrence_end_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                $classId, $scheduleDate, $startTime, $endTime, $maxCapacity,
                $roomLocation, $status, $notes, $isRecurring, $recurrencePattern,
                $recurrenceEndDate
            ]);
            
            if ($result) {
                $newScheduleId = $conn->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Schedule created successfully', 'schedule_id' => $newScheduleId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create schedule']);
            }
        }
    }
}

function createRecurringSchedules($conn, $classId, $startDate, $startTime, $endTime, 
                                  $maxCapacity, $roomLocation, $notes, $pattern, $endDate) {
    $createdCount = 0;
    $currentDate = new DateTime($startDate);
    $endDateTime = new DateTime($endDate);
    
    $sql = "INSERT INTO class_schedules 
            (class_id, schedule_date, start_time, end_time, max_capacity, 
             room_location, status, notes, is_recurring, recurrence_pattern, 
             recurrence_end_date, parent_schedule_id) 
            VALUES (?, ?, ?, ?, ?, ?, 'Scheduled', ?, 1, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $parentScheduleId = null;
    
    while ($currentDate <= $endDateTime) {
        $scheduleDate = $currentDate->format('Y-m-d');
        
        $result = $stmt->execute([
            $classId, $scheduleDate, $startTime, $endTime, $maxCapacity,
            $roomLocation, $notes, $pattern, $endDate, $parentScheduleId
        ]);
        
        if ($result) {
            $createdCount++;
            if ($parentScheduleId === null) {
                $parentScheduleId = $conn->lastInsertId();
            }
        }
        
        // Increment date based on pattern
        switch ($pattern) {
            case 'daily':
                $currentDate->modify('+1 day');
                break;
            case 'weekly':
                $currentDate->modify('+1 week');
                break;
            case 'monthly':
                $currentDate->modify('+1 month');
                break;
        }
    }
    
    return $createdCount;
}

function deleteSchedule($conn) {
    $scheduleId = $_POST['schedule_id'] ?? 0;
    
    // Check if schedule has bookings
    $checkSql = "SELECT COUNT(*) FROM class_bookings WHERE schedule_id = ? AND status != 'Cancelled'";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$scheduleId]);
    $bookingCount = $checkStmt->fetchColumn();
    
    if ($bookingCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete schedule with active bookings. Please cancel the schedule instead.']);
        return;
    }
    
    $sql = "DELETE FROM class_schedules WHERE schedule_id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$scheduleId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete schedule']);
    }
}
?>