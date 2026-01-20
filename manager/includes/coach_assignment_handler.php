<?php
session_start();

// Check if user is logged in and has manager role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Manager') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../../config/connection.php';

// Include email functions
if (file_exists('../../includes/email_functions.php')) {
    require_once '../../includes/email_functions.php';
}

$conn = getDBConnection();

// Get the action from request
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// Debug logging
error_log("Coach Assignment Handler - Action: $action");

try {
    switch ($action) {
        case 'get_coach_overview':
            getCoachOverview();
            break;
        case 'get_assignments':
            getAssignments();
            break;
        case 'get_coaches':
            getCoaches();
            break;
        case 'get_unassigned_members':
            getUnassignedMembers();
            break;
        case 'get_member_info':
            getMemberInfo();
            break;
        case 'get_coach_info':
            getCoachInfo();
            break;
        case 'get_statistics':
            getStatistics();
            break;
        case 'get_recent_assessments':
            getRecentAssessments();
            break;
        case 'get_assignment':
            getAssignment();
            break;
        case 'get_member_details':
            getMemberDetails();
            break;
        case 'get_coach_details':
            getCoachDetails();
            break;
        case 'get_assessment_details':
            getAssessmentDetails();
            break;
        case 'get_coach_clients':
            getCoachClients();
            break;
        case 'assign_coach':
            assignCoach();
            break;
        case 'remove_assignment':
            removeAssignment();
            break;
        case 'bulk_assign':
            bulkAssign();
            break;
        default:
            respondError('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    error_log("Exception in Coach Assignment Handler: " . $e->getMessage());
    respondError('An error occurred: ' . $e->getMessage());
}

/**
 * Get coach overview with client counts and status
 */
function getCoachOverview() {
    global $conn;
    
    try {
        $query = "
            SELECT 
                c.coach_id,
                CONCAT(c.first_name, ' ', c.last_name) as name,
                c.specialization,
                c.certification,
                c.experience_years,
                c.hourly_rate,
                c. status,
                c.profile_image,
                COUNT(DISTINCT cl.client_id) as client_count
            FROM coach c
            LEFT JOIN clients cl ON c.coach_id = cl.assigned_coach_id AND cl.status = 'Active'
            WHERE c.status = 'Active'
            GROUP BY c.coach_id
            ORDER BY c.first_name ASC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Coach Overview: " . count($coaches) . " coaches fetched");
        respondSuccess(['coaches' => $coaches]);
    } catch (Exception $e) {
        error_log("Error in getCoachOverview: " .  $e->getMessage());
        respondError('Error fetching coach overview: ' . $e->getMessage());
    }
}

/**
 * Get all assignments with member and coach details
 */
function getAssignments() {
    global $conn;
    
    try {
        $query = "
            SELECT 
                cl.client_id,
                cl.first_name,
                cl.last_name,
                cl.email,
                cl.phone,
                cl.client_type,
                cl.status as client_status,
                cl.join_date,
                cl.assigned_coach_id,
                c.coach_id,
                CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c. last_name, '')) as coach_name,
                COALESCE(c.specialization, 'N/A') as specialization,
                COALESCE(c. status, 'N/A') as coach_status
            FROM clients cl
            LEFT JOIN coach c ON cl.assigned_coach_id = c.coach_id
            WHERE cl.client_type = 'Member' AND cl.status = 'Active'
            ORDER BY cl.first_name ASC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Assignments: " . count($assignments) . " assignments fetched");
        respondSuccess(['assignments' => $assignments]);
    } catch (Exception $e) {
        error_log("Error in getAssignments: " . $e->getMessage());
        respondError('Error fetching assignments: ' . $e->getMessage());
    }
}

/**
 * Get all active coaches
 */
function getCoaches() {
    global $conn;
    
    try {
        $query = "
            SELECT 
                c.coach_id,
                CONCAT(c.first_name, ' ', c.last_name) as name,
                c.specialization,
                c. email,
                c.status,
                COUNT(DISTINCT cl.client_id) as client_count
            FROM coach c
            LEFT JOIN clients cl ON c.coach_id = cl.assigned_coach_id AND cl.status = 'Active'
            WHERE c.status = 'Active'
            GROUP BY c. coach_id
            ORDER BY c.first_name ASC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Coaches: " .  count($coaches) . " coaches fetched");
        respondSuccess(['coaches' => $coaches]);
    } catch (Exception $e) {
        error_log("Error in getCoaches: " . $e->getMessage());
        respondError('Error fetching coaches: ' . $e->getMessage());
    }
}

/**
 * Get unassigned members
 */
function getUnassignedMembers() {
    global $conn;
    
    try {
        $query = "
            SELECT 
                cl.client_id,
                CONCAT(cl.first_name, ' ', cl.last_name) as name,
                cl.email,
                cl.phone,
                cl.client_type,
                cl.status,
                cl.join_date,
                COALESCE(m.plan_name, 'None') as membership
            FROM clients cl
            LEFT JOIN client_memberships cm ON cl.client_id = cm.client_id AND cm.status = 'Active'
            LEFT JOIN memberships m ON cm.membership_id = m.membership_id
            WHERE cl.assigned_coach_id IS NULL 
                AND cl.client_type = 'Member' 
                AND cl.status = 'Active'
            ORDER BY cl.join_date DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Unassigned Members: " . count($members) . " members fetched");
        respondSuccess(['members' => $members]);
    } catch (Exception $e) {
        error_log("Error in getUnassignedMembers: " . $e->getMessage());
        respondError('Error fetching unassigned members: ' . $e->getMessage());
    }
}

/**
 * Get member information
 */
function getMemberInfo() {
    global $conn;
    
    $clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
    
    if (! $clientId) {
        respondError('Client ID is required');
        return;
    }
    
    try {
        $query = "
            SELECT 
                cl.client_id,
                CONCAT(cl.first_name, ' ', cl.last_name) as name,
                cl.email,
                cl.phone,
                CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as current_coach
            FROM clients cl
            LEFT JOIN coach c ON cl.assigned_coach_id = c.coach_id
            WHERE cl.client_id = ?  
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$clientId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($member) {
            $currentCoach = trim($member['current_coach']);
            if (empty($currentCoach)) {
                $currentCoach = 'None';
            }
            respondSuccess(['current_coach' => $currentCoach]);
        } else {
            respondError('Member not found');
        }
    } catch (Exception $e) {
        error_log("Error in getMemberInfo: " . $e->getMessage());
        respondError('Error fetching member info: ' . $e->getMessage());
    }
}

/**
 * Get coach information
 */
function getCoachInfo() {
    global $conn;
    
    $coachId = isset($_GET['coach_id']) ? intval($_GET['coach_id']) : 0;
    
    if (!$coachId) {
        respondError('Coach ID is required');
        return;
    }
    
    try {
        $query = "
            SELECT 
                c.coach_id,
                CONCAT(c.first_name, ' ', c.last_name) as name,
                COALESCE(c.specialization, 'General Fitness') as specialization,
                COALESCE(c.experience_years, 0) as experience_years,
                COUNT(DISTINCT cl.client_id) as client_count
            FROM coach c
            LEFT JOIN clients cl ON c.coach_id = cl.assigned_coach_id AND cl.status = 'Active'
            WHERE c.coach_id = ? 
            GROUP BY c.coach_id
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$coachId]);
        $coach = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coach) {
            respondSuccess($coach);
        } else {
            respondError('Coach not found');
        }
    } catch (Exception $e) {
        error_log("Error in getCoachInfo: " . $e->getMessage());
        respondError('Error fetching coach info: ' . $e->getMessage());
    }
}

/**
 * Get coach statistics
 */
function getStatistics() {
    global $conn;
    
    $coachFilter = isset($_GET['coach_id']) ? $_GET['coach_id'] : 'all';
    
    try {
        $where = '';
        $params = [];
        
        if ($coachFilter !== 'all') {
            $where = 'WHERE c.coach_id = ?';
            $params[] = intval($coachFilter);
        }
        
        $query = "
            SELECT 
                c.coach_id,
                CONCAT(c.first_name, ' ', c.last_name) as coach_name,
                COUNT(DISTINCT cl.client_id) as client_count,
                COUNT(DISTINCT CASE WHEN a.assessment_date IS NOT NULL THEN a.assessment_id END) as active_programs,
                ROUND(COALESCE(DATEDIFF(NOW(), MAX(cl.join_date)) / 30, 0), 1) as avg_duration,
                ROUND(
                    (COUNT(DISTINCT a.assessment_id) / NULLIF(COUNT(DISTINCT cl.client_id), 0)) * 100, 
                    1
                ) as assessment_rate
            FROM coach c
            LEFT JOIN clients cl ON c.coach_id = cl.assigned_coach_id AND cl.status = 'Active'
            LEFT JOIN assessment a ON cl.client_id = a.client_id AND a.next_assessment_date >= CURDATE()
            $where
            GROUP BY c.coach_id
            ORDER BY c.first_name ASC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Statistics: " . count($statistics) . " statistics fetched");
        respondSuccess(['statistics' => $statistics]);
    } catch (Exception $e) {
        error_log("Error in getStatistics: " . $e->getMessage());
        respondError('Error fetching statistics: ' . $e->getMessage());
    }
}

/**
 * Get recent assessments
 */
function getRecentAssessments() {
    global $conn;
    
    try {
        $query = "
            SELECT 
                a.assessment_id,
                a.assessment_date,
                a.weight,
                a.height,
                a.body_fat_percentage,
                a.muscle_mass,
                a.next_assessment_date,
                CONCAT(cl.first_name, ' ', cl.last_name) as client_name,
                CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c. last_name, '')) as coach_name
            FROM assessment a
            JOIN clients cl ON a.client_id = cl.client_id
            LEFT JOIN coach c ON a.coach_id = c.coach_id
            WHERE a.assessment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY a.assessment_date DESC
            LIMIT 50
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Recent Assessments: " . count($assessments) . " assessments fetched");
        respondSuccess(['assessments' => $assessments]);
    } catch (Exception $e) {
        error_log("Error in getRecentAssessments: " . $e->getMessage());
        respondError('Error fetching recent assessments: ' . $e->getMessage());
    }
}

/**
 * Get specific assignment
 */
function getAssignment() {
    global $conn;
    
    $clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
    
    if (!$clientId) {
        respondError('Client ID is required');
        return;
    }
    
    try {
        $query = "
            SELECT 
                cl.client_id,
                cl.assigned_coach_id as coach_id
            FROM clients cl
            WHERE cl.client_id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$clientId]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assignment) {
            respondSuccess($assignment);
        } else {
            respondError('Assignment not found');
        }
    } catch (Exception $e) {
        error_log("Error in getAssignment: " . $e->getMessage());
        respondError('Error fetching assignment: ' .  $e->getMessage());
    }
}

/**
 * Get member details
 */
function getMemberDetails() {
    global $conn;
    
    $clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
    
    if (!$clientId) {
        respondError('Client ID is required');
        return;
    }
    
    try {
        $query = "
            SELECT 
                cl. client_id,
                CONCAT(cl.first_name, ' ', cl.last_name) as name,
                cl.email,
                cl.phone,
                cl.status,
                cl.join_date,
                cl.client_type,
                CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c. last_name, '')) as coach_name,
                COALESCE(c.specialization, 'N/A') as coach_specialization,
                COALESCE(m.plan_name, 'None') as membership_type,
                MAX(CASE WHEN a.assessment_id IS NOT NULL THEN a.assessment_date END) as last_assessment,
                MAX(CASE WHEN a.assessment_id IS NOT NULL THEN a.next_assessment_date END) as next_assessment
            FROM clients cl
            LEFT JOIN coach c ON cl. assigned_coach_id = c. coach_id
            LEFT JOIN client_memberships cm ON cl.client_id = cm.client_id AND cm.status = 'Active'
            LEFT JOIN memberships m ON cm.membership_id = m.membership_id
            LEFT JOIN assessment a ON cl.client_id = a.client_id
            WHERE cl.client_id = ?
            GROUP BY cl.client_id
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$clientId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($member) {
            respondSuccess(['member' => $member]);
        } else {
            respondError('Member not found');
        }
    } catch (Exception $e) {
        error_log("Error in getMemberDetails: " . $e->getMessage());
        respondError('Error fetching member details: ' . $e->getMessage());
    }
}

/**
 * Get coach details with assigned clients
 */
function getCoachDetails() {
    global $conn;
    
    $coachId = isset($_GET['coach_id']) ? intval($_GET['coach_id']) : 0;
    
    if (!$coachId) {
        respondError('Coach ID is required');
        return;
    }
    
    try {
        // Get coach info
        $coachQuery = "
            SELECT 
                c.coach_id,
                CONCAT(c.first_name, ' ', c.last_name) as name,
                c.email,
                c.phone,
                c.specialization,
                c.certification,
                c.experience_years,
                c.hourly_rate,
                c. status
            FROM coach c
            WHERE c.coach_id = ?
        ";
        
        $stmt = $conn->prepare($coachQuery);
        $stmt->execute([$coachId]);
        $coach = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (! $coach) {
            respondError('Coach not found');
            return;
        }
        
        // Get assigned clients
        $clientsQuery = "
            SELECT 
                cl.client_id,
                CONCAT(cl.first_name, ' ', cl.last_name) as name,
                cl.status
            FROM clients cl
            WHERE cl.assigned_coach_id = ?  AND cl.status = 'Active'
            ORDER BY cl.first_name ASC
        ";
        
        $stmt = $conn->prepare($clientsQuery);
        $stmt->execute([$coachId]);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        respondSuccess(['coach' => $coach, 'clients' => $clients]);
    } catch (Exception $e) {
        error_log("Error in getCoachDetails: " . $e->getMessage());
        respondError('Error fetching coach details: ' . $e->getMessage());
    }
}

/**
 * Get all clients assigned to a specific coach
 */
function getCoachClients() {
    global $conn;
    
    $coachId = isset($_GET['coach_id']) ? intval($_GET['coach_id']) : 0;
    
    if (!$coachId) {
        respondError('Coach ID is required');
        return;
    }
    
    try {
        // Get coach info
        $coachQuery = "
            SELECT 
                CONCAT(first_name, ' ', last_name) as name
            FROM coach
            WHERE coach_id = ?
        ";
        $stmt = $conn->prepare($coachQuery);
        $stmt->execute([$coachId]);
        $coach = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$coach) {
            respondError('Coach not found');
            return;
        }
        
        // Get all assigned clients with full details
        $clientsQuery = "
            SELECT 
                client_id,
                first_name,
                last_name,
                email,
                phone,
                status,
                join_date
            FROM clients
            WHERE assigned_coach_id = ?
            ORDER BY first_name ASC
        ";
        
        $stmt = $conn->prepare($clientsQuery);
        $stmt->execute([$coachId]);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        respondSuccess([
            'coach' => $coach,
            'clients' => $clients,
            'count' => count($clients)
        ]);
    } catch (Exception $e) {
        error_log("Error in getCoachClients: " . $e->getMessage());
        respondError('Error fetching coach clients: ' . $e->getMessage());
    }
}

/**
 * Get assessment details
 */
function getAssessmentDetails() {
    global $conn;
    
    $assessmentId = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
    
    if (! $assessmentId) {
        respondError('Assessment ID is required');
        return;
    }
    
    try {
        $query = "
            SELECT 
                a.assessment_id,
                a.assessment_date,
                a.weight,
                a.height,
                a.body_fat_percentage,
                a.muscle_mass,
                a.blood_pressure,
                a.resting_heart_rate,
                a.fitness_goals,
                a.medical_conditions,
                a.notes,
                a.next_assessment_date,
                CONCAT(cl.first_name, ' ', cl.last_name) as client_name,
                CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as coach_name
            FROM assessment a
            JOIN clients cl ON a.client_id = cl.client_id
            LEFT JOIN coach c ON a.coach_id = c.coach_id
            WHERE a.assessment_id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$assessmentId]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assessment) {
            respondSuccess(['assessment' => $assessment]);
        } else {
            respondError('Assessment not found');
        }
    } catch (Exception $e) {
        error_log("Error in getAssessmentDetails: " . $e->getMessage());
        respondError('Error fetching assessment details: ' . $e->getMessage());
    }
}

/**
 * Assign coach to member
 */
function assignCoach() {
    global $conn;
    
    $clientId = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    $coachId = isset($_POST['coach_id']) ? intval($_POST['coach_id']) : 0;
    $notes = isset($_POST['notes']) ?  trim($_POST['notes']) : '';
    
    if (!$clientId || !$coachId) {
        respondError('Client ID and Coach ID are required');
        return;
    }
    
    try {
        // Check if client exists and get their details
        $clientCheck = $conn->prepare("SELECT client_id, first_name, last_name, email FROM clients WHERE client_id = ?");
        $clientCheck->execute([$clientId]);
        $clientData = $clientCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$clientData) {
            respondError('Client not found');
            return;
        }
        
        // Check if coach exists and get their details
        $coachCheck = $conn->prepare("SELECT coach_id, first_name, last_name, email FROM coach WHERE coach_id = ?");
        $coachCheck->execute([$coachId]);
        $coachData = $coachCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$coachData) {
            respondError('Coach not found');
            return;
        }
        
        // Update assignment
        $query = "UPDATE clients SET assigned_coach_id = ?  WHERE client_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$coachId, $clientId]);
        
        // Log the activity
        logActivity($coachId, 'assignment_made', "Assigned client ID $clientId");
        
        // Send notification emails
        $emailsSent = 0;
        
        // Send email to member about their coach assignment
        if (function_exists('sendEmail') && !empty($clientData['email'])) {
            $memberEmailBody = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .coach-info { background: white; padding: 20px; border-left: 4px solid #dc2626; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🏋️ Your Coach Assignment</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello " . htmlspecialchars($clientData['first_name']) . ",</h2>
                        <p>Great news! You have been assigned a dedicated coach to support your fitness journey at Empire Fitness.</p>
                        
                        <div class='coach-info'>
                            <h3>Your Coach:</h3>
                            <p><strong>Name:</strong> " . htmlspecialchars($coachData['first_name'] . ' ' . $coachData['last_name']) . "</p>
                            <p><strong>Email:</strong> " . htmlspecialchars($coachData['email']) . "</p>
                        </div>
                        
                        <h3>What's Next?</h3>
                        <ul>
                            <li>Log in to your member account</li>
                            <li>Review your coach's profile and specializations</li>
                            <li>Schedule your first training session</li>
                            <li>Set your fitness goals</li>
                        </ul>
                        
                        <p>Your coach will help you achieve your fitness goals. Feel free to reach out to them directly if you have any questions.</p>
                        
                        <div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'>
                            <p><strong>Empire Fitness</strong> | Your Partner in Fitness</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $memberResult = sendEmail(
                $clientData['email'],
                'Your Coach Assignment at Empire Fitness',
                $memberEmailBody
            );
            
            if ($memberResult['success']) {
                $emailsSent++;
            }
        }
        
        // Send email to coach about new member assignment
        if (function_exists('sendEmail') && !empty($coachData['email'])) {
            $coachEmailBody = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .member-info { background: white; padding: 20px; border-left: 4px solid #667eea; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>👤 New Member Assignment</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello Coach " . htmlspecialchars($coachData['first_name']) . ",</h2>
                        <p>A new member has been assigned to you at Empire Fitness.</p>
                        
                        <div class='member-info'>
                            <h3>New Member:</h3>
                            <p><strong>Name:</strong> " . htmlspecialchars($clientData['first_name'] . ' ' . $clientData['last_name']) . "</p>
                            <p><strong>Email:</strong> " . htmlspecialchars($clientData['email']) . "</p>
                        </div>
                        
                        <p>Please reach out to the member soon to introduce yourself and schedule their first training session.</p>
                        <p>Log in to your coach account to view more details about your clients and manage their progress.</p>
                        
                        <div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'>
                            <p><strong>Empire Fitness</strong> | Coaching Excellence</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $coachResult = sendEmail(
                $coachData['email'],
                'New Member Assignment - Empire Fitness',
                $coachEmailBody
            );
            
            if ($coachResult['success']) {
                $emailsSent++;
            }
        }
        
        error_log("Coach assigned: Coach ID $coachId assigned to Client ID $clientId");
        respondSuccess(['message' => 'Coach assigned successfully' . ($emailsSent > 0 ? ". $emailsSent notification(s) sent." : '')]);
    } catch (Exception $e) {
        error_log("Error in assignCoach: " .  $e->getMessage());
        respondError('Error assigning coach: ' . $e->getMessage());
    }
}

/**
 * Remove assignment
 */
function removeAssignment() {
    global $conn;
    
    $clientId = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    
    if (!$clientId) {
        respondError('Client ID is required');
        return;
    }
    
    try {
        // Get current coach before removing
        $getCoachQuery = $conn->prepare("SELECT assigned_coach_id FROM clients WHERE client_id = ?");
        $getCoachQuery->execute([$clientId]);
        $result = $getCoachQuery->fetch(PDO::FETCH_ASSOC);
        $coachId = $result['assigned_coach_id'] ?? null;
        
        // Remove assignment
        $query = "UPDATE clients SET assigned_coach_id = NULL WHERE client_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$clientId]);
        
        // Log the activity
        if ($coachId) {
            logActivity($coachId, 'assignment_removed', "Removed client ID $clientId");
        }
        
        error_log("Assignment removed: Coach ID $coachId removed from Client ID $clientId");
        respondSuccess(['message' => 'Assignment removed successfully']);
    } catch (Exception $e) {
        error_log("Error in removeAssignment: " . $e->getMessage());
        respondError('Error removing assignment: ' . $e->getMessage());
    }
}

/**
 * Bulk assign members to a coach
 */
function bulkAssign() {
    global $conn;
    
    $coachId = isset($_POST['coach_id']) ? intval($_POST['coach_id']) : 0;
    $clientIdsJson = isset($_POST['client_ids']) ? $_POST['client_ids'] : '[]';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    if (!$coachId) {
        respondError('Coach ID is required');
        return;
    }
    
    $clientIds = json_decode($clientIdsJson, true);
    
    if (!is_array($clientIds) || empty($clientIds)) {
        respondError('No clients selected');
        return;
    }
    
    try {
        // Validate coach exists
        $coachCheck = $conn->prepare("SELECT coach_id FROM coach WHERE coach_id = ?");
        $coachCheck->execute([$coachId]);
        if (!$coachCheck->fetch()) {
            respondError('Coach not found');
            return;
        }
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Prepare update statement
        $query = "UPDATE clients SET assigned_coach_id = ? WHERE client_id = ?";
        $stmt = $conn->prepare($query);
        
        $successCount = 0;
        foreach ($clientIds as $clientId) {
            $clientId = intval($clientId);
            if ($clientId > 0) {
                $result = $stmt->execute([$coachId, $clientId]);
                if ($result) {
                    $successCount++;
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Log the activity
        logActivity($coachId, 'bulk_assignment', "Assigned $successCount clients in bulk");
        
        error_log("Bulk assignment: Coach ID $coachId assigned to $successCount clients");
        respondSuccess(['message' => "$successCount members assigned successfully"]);
    } catch (Exception $e) {
        // Rollback on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error in bulkAssign: " . $e->getMessage());
        respondError('Error bulk assigning members: ' . $e->getMessage());
    }
}

/**
 * Log coach activity
 */
function logActivity($coachId, $activityType, $description) {
    global $conn;
    
    try {
        $query = "
            INSERT INTO coach_activity_logs (coach_id, activity_type, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            $coachId,
            $activityType,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '0. 0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        error_log('Activity logging error: ' . $e->getMessage());
    }
}

/**
 * Respond with success
 */
function respondSuccess($data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

/**
 * Respond with error
 */
function respondError($message) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}
?>