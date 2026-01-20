<?php
/**
 * Coach Assignment Notification Handler
 * Handles sending notifications to coaches and members when assignments are made
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/connection.php';

$action = $_GET['action'] ?? $_POST['action'] ?? null;

error_log("Notification Handler - Action: $action");

try {
    $conn = getDBConnection();

    switch ($action) {
        case 'notify_assignment':
            notifyAssignment();
            break;

        case 'notify_removal':
            notifyRemoval();
            break;

        case 'get_notifications':
            getNotifications();
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
} catch (Exception $e) {
    error_log("Notification Handler Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Notify coach and member about assignment
 */
function notifyAssignment() {
    global $conn;

    $clientId = $_POST['client_id'] ?? null;
    $coachId = $_POST['coach_id'] ?? null;
    $notes = $_POST['notes'] ?? '';

    error_log("Notifying assignment: Client=$clientId, Coach=$coachId");

    if (!$clientId || !$coachId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        return;
    }

    try {
        // Get client and coach info
        $clientStmt = $conn->prepare("
            SELECT client_id, first_name, last_name, email, phone 
            FROM clients 
            WHERE client_id = ?
        ");
        $clientStmt->execute([$clientId]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);

        $coachStmt = $conn->prepare("
            SELECT coach_id, first_name, last_name, email, phone, specialization 
            FROM coach 
            WHERE coach_id = ?
        ");
        $coachStmt->execute([$coachId]);
        $coach = $coachStmt->fetch(PDO::FETCH_ASSOC);

        if (!$client || !$coach) {
            error_log("Client or Coach not found - Client: " . var_export($client, true) . ", Coach: " . var_export($coach, true));
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Client or coach not found']);
            return;
        }

        // Create notifications in database
        $notificationStmt = $conn->prepare("
            INSERT INTO notifications (user_id, user_type, type, title, message, is_read)
            VALUES (?, ?, ?, ?, ?, 0)
        ");

        // Notify Coach
        $coachMessage = sprintf(
            "You have been assigned a new client: %s %s. Contact: %s",
            $client['first_name'],
            $client['last_name'],
            $client['email'] ?? $client['phone'] ?? 'N/A'
        );

        if ($notes) {
            $coachMessage .= "\n\nNotes: " . $notes;
        }

        $coachNotifResult = $notificationStmt->execute([
            $coach['coach_id'],
            'employee',
            'success',
            'New Client Assignment',
            $coachMessage
        ]);

        error_log("Coach notification insert result: " . ($coachNotifResult ? 'true' : 'false'));

        // Notify Member
        $memberMessage = sprintf(
            "You have been assigned a coach: %s %s (%s). They will be contacting you soon.",
            $coach['first_name'],
            $coach['last_name'],
            $coach['specialization'] ?? 'Coach'
        );

        $memberNotifResult = $notificationStmt->execute([
            $clientId,
            'client',
            'info',
            'Coach Assignment',
            $memberMessage
        ]);

        error_log("Member notification insert result: " . ($memberNotifResult ? 'true' : 'false'));

        // Send Email Notifications
        sendEmailNotifications($client, $coach, $notes);

        echo json_encode([
            'success' => true,
            'message' => 'Assignment notifications sent successfully',
            'notifications' => [
                'coach' => $coachMessage,
                'member' => $memberMessage
            ]
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Error in notifyAssignment: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error creating notifications: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * Notify coach and member about assignment removal
 */
function notifyRemoval() {
    global $conn;

    $clientId = $_POST['client_id'] ?? null;
    $coachId = $_POST['coach_id'] ?? null;
    $reason = $_POST['reason'] ?? 'Unknown';
    $notes = $_POST['notes'] ?? '';
    $includeReason = $_POST['include_reason'] ?? '0';

    error_log("Notifying removal: Client=$clientId, Coach=$coachId, Reason=$reason");

    if (!$clientId || !$coachId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        return;
    }

    try {
        // Get client and coach info
        $clientStmt = $conn->prepare("
            SELECT client_id, first_name, last_name, email, phone 
            FROM clients 
            WHERE client_id = ?
        ");
        $clientStmt->execute([$clientId]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);

        $coachStmt = $conn->prepare("
            SELECT coach_id, first_name, last_name, email, phone, specialization 
            FROM coach 
            WHERE coach_id = ?
        ");
        $coachStmt->execute([$coachId]);
        $coach = $coachStmt->fetch(PDO::FETCH_ASSOC);

        if (!$client || !$coach) {
            error_log("Client or Coach not found");
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Client or coach not found']);
            return;
        }

        // Create notifications in database
        $notificationStmt = $conn->prepare("
            INSERT INTO notifications (user_id, user_type, type, title, message, is_read)
            VALUES (?, ?, ?, ?, ?, 0)
        ");

        // Notify Coach about removal
        $coachMessage = sprintf(
            "Your assignment with client %s %s has been removed.",
            $client['first_name'],
            $client['last_name']
        );

        if ($includeReason === '1') {
            $coachMessage .= "\n\nReason: " . $reason;
        }

        if ($notes) {
            $coachMessage .= "\n\nNotes: " . $notes;
        }

        $coachNotifResult = $notificationStmt->execute([
            $coach['coach_id'],
            'employee',
            'warning',
            'Assignment Removed',
            $coachMessage
        ]);

        error_log("Coach removal notification insert result: " . ($coachNotifResult ? 'true' : 'false'));

        // Notify Member about removal
        $memberMessage = sprintf(
            "Your coach assignment with %s %s has been removed.",
            $coach['first_name'],
            $coach['last_name']
        );

        if ($includeReason === '1') {
            $memberMessage .= "\n\nReason: " . $reason;
        }

        if ($notes) {
            $memberMessage .= "\n\nAdditional Information: " . $notes;
        }

        $memberNotifResult = $notificationStmt->execute([
            $clientId,
            'client',
            'warning',
            'Coach Assignment Removed',
            $memberMessage
        ]);

        error_log("Member removal notification insert result: " . ($memberNotifResult ? 'true' : 'false'));

        // Send Email Notifications
        sendRemovalEmailNotifications($client, $coach, $reason, $notes, $includeReason);

        echo json_encode([
            'success' => true,
            'message' => 'Removal notifications sent successfully',
            'notifications' => [
                'coach' => $coachMessage,
                'member' => $memberMessage
            ]
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Error in notifyRemoval: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error creating notifications: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * Send removal email notifications
 */
function sendRemovalEmailNotifications($client, $coach, $reason, $notes, $includeReason) {
    // Email to Coach
    $coachSubject = "Assignment Removal - " . $client['first_name'] . " " . $client['last_name'];
    $coachBody = sprintf(
        "Hello %s,\n\n" .
        "Your assignment with client %s %s has been removed.\n\n",
        $coach['first_name'],
        $client['first_name'],
        $client['last_name']
    );

    if ($includeReason === '1' || $includeReason === true) {
        $coachBody .= "Reason: " . $reason . "\n\n";
    }

    if ($notes) {
        $coachBody .= "Notes: " . $notes . "\n\n";
    }

    $coachBody .= "If you have any questions, please contact the management team.\n\n" .
        "Best regards,\n" .
        "Empire Fitness Management Team";

    sendEmail($coach['email'], $coachSubject, $coachBody);

    // Email to Member
    $memberSubject = "Your Coach Assignment Has Been Removed";
    $memberBody = sprintf(
        "Hello %s,\n\n" .
        "Your coach assignment with %s %s has been removed.\n\n",
        $client['first_name'],
        $coach['first_name'],
        $coach['last_name']
    );

    if ($includeReason === '1' || $includeReason === true) {
        $memberBody .= "Reason: " . $reason . "\n\n";
    }

    if ($notes) {
        $memberBody .= "Additional Information: " . $notes . "\n\n";
    }

    $memberBody .= "You may be assigned a new coach shortly. Please contact us if you have any concerns.\n\n" .
        "Best regards,\n" .
        "Empire Fitness Management Team";

    sendEmail($client['email'], $memberSubject, $memberBody);
}

/**
 * Send email notifications to coach and member
 */
function sendEmailNotifications($client, $coach, $notes = '') {
    // Email to Coach
    $coachSubject = "New Client Assignment - " . $client['first_name'] . " " . $client['last_name'];
    $coachBody = sprintf(
        "Hello %s,\n\n" .
        "You have been assigned a new client:\n\n" .
        "Name: %s %s\n" .
        "Email: %s\n" .
        "Phone: %s\n" .
        "\n" .
        "Please reach out to the client to schedule your first session.\n" .
        "%s" .
        "\n\n" .
        "Best regards,\n" .
        "Empire Fitness Management Team",
        $coach['first_name'],
        $client['first_name'],
        $client['last_name'],
        $client['email'] ?? 'N/A',
        $client['phone'] ?? 'N/A',
        $notes ? "Notes: " . $notes . "\n" : ""
    );

    sendEmail($coach['email'], $coachSubject, $coachBody);

    // Email to Member
    $memberSubject = "Your Coach Assignment - " . $coach['first_name'] . " " . $coach['last_name'];
    $memberBody = sprintf(
        "Hello %s,\n\n" .
        "Great news! We have assigned you a dedicated coach who will help you achieve your fitness goals.\n\n" .
        "Your Coach: %s %s\n" .
        "Specialization: %s\n" .
        "Contact: %s\n" .
        "\n" .
        "Your coach will be contacting you soon to schedule your first session.\n\n" .
        "Best regards,\n" .
        "Empire Fitness Management Team",
        $client['first_name'],
        $coach['first_name'],
        $coach['last_name'],
        $coach['specialization'] ?? 'Personal Training',
        $coach['email'] ?? $coach['phone'] ?? 'N/A'
    );

    sendEmail($client['email'], $memberSubject, $memberBody);
}

/**
 * Send email function (basic implementation)
 */
function sendEmail($to, $subject, $message) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $headers = "From: noreply@empirefitness.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return mail($to, $subject, $message, $headers);
}

/**
 * Get notifications for a user
 */
function getNotifications() {
    global $conn;

    $userId = $_GET['user_id'] ?? null;
    $userType = $_GET['user_type'] ?? null;
    $limit = $_GET['limit'] ?? 10;

    if (!$userId || !$userType) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }

    try {
        $stmt = $conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND user_type = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $userType, $limit]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'count' => count($notifications)
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching notifications: ' . $e->getMessage()]);
        exit;
    }
}
?>
