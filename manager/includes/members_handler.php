<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has manager role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Manager'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../../config/connection.php';
require_once '../../includes/email_functions.php';
$conn = getDBConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_all':
            getAllMembers($conn);
            break;
            
        case 'get_one':
            getOneMember($conn);
            break;
            
        case 'add':
            addMember($conn);
            break;
            
        case 'edit':
            editMember($conn);
            break;
            
        case 'delete':
            deleteMember($conn);
            break;
            
        case 'verify':
            verifyMember($conn);
            break;
            
        case 'get_pending_payments':
            getPendingPaymentVerifications($conn);
            break;
            
        case 'approve_payment':
            approvePaymentVerification($conn);
            break;
            
        case 'reject_payment':
            rejectPaymentVerification($conn);
            break;
            
        case 'export':
            exportMembers($conn);
            break;
            
        case 'bulk_status':
            bulkUpdateStatus($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Get all members
 */
function getAllMembers($conn) {
    $sql = "SELECT 
                c.client_id,
                c.username,
                c.first_name,
                c.middle_name,
                c.last_name,
                c.email,
                c.phone,
                c.client_type,
                c.status,
                c.account_status,
                c.is_verified,
                c.join_date,
                c.last_login,
                c.current_membership_id,
                c.assigned_coach_id,
                cm.id as membership_record_id,
                cm.membership_id,
                cm.start_date as membership_start,
                cm.end_date as membership_end,
                cm.is_renewal,
                cm.renewal_count,
                cm.last_renewal_date,
                m.plan_name,
                m.duration_days,
                m.monthly_fee
            FROM clients c
            LEFT JOIN client_memberships cm ON c.current_membership_id = cm.id
            LEFT JOIN memberships m ON cm.membership_id = m.membership_id
            ORDER BY c.join_date DESC";
    
    $stmt = $conn->query($sql);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'members' => $members]);
}

/**
 * Get one member
 */
function getOneMember($conn) {
    $clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? 0;
    
    $sql = "SELECT * FROM clients WHERE client_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$clientId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($member) {
        // Get profile details
        $profileSql = "SELECT * FROM profile_details WHERE client_id = ?";
        $profileStmt = $conn->prepare($profileSql);
        $profileStmt->execute([$clientId]);
        $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
        
        // Merge profile data with member data
        if ($profile) {
            $member['birthdate'] = $profile['birthdate'];
            $member['gender'] = $profile['gender'];
            $member['address'] = $profile['street_address'];
            $member['fitness_goals'] = $profile['fitness_goals'];
        }
        
        // Get current membership details with renewal information
        $membershipSql = "SELECT 
                            cm.id as membership_record_id,
                            cm.membership_id,
                            cm.start_date,
                            cm.end_date,
                            cm.status,
                            cm.is_renewal,
                            cm.renewal_count,
                            cm.last_renewal_date,
                            m.plan_name,
                            m.duration_days,
                            m.monthly_fee,
                            m.is_base_membership,
                            DATEDIFF(cm.end_date, CURDATE()) as days_remaining
                        FROM client_memberships cm
                        LEFT JOIN memberships m ON cm.membership_id = m.membership_id
                        WHERE cm.client_id = ?
                        ORDER BY cm.start_date DESC
                        LIMIT 10";
        $membershipStmt = $conn->prepare($membershipSql);
        $membershipStmt->execute([$clientId]);
        $memberships = $membershipStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent activities
        $activitySql = "SELECT * FROM client_activity WHERE client_id = ? ORDER BY created_at DESC LIMIT 10";
        $activityStmt = $conn->prepare($activitySql);
        $activityStmt->execute([$clientId]);
        $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'member' => $member,
            'memberships' => $memberships,
            'activities' => $activities
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
    }
}

/**
 * Add new member
 */
function addMember($conn) {
    try {
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $birthdate = trim($_POST['birthdate'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $referralSource = trim($_POST['referral_source'] ?? '');
        $membershipPlanType = trim($_POST['membership_plan'] ?? 'none'); // Values: 'none', 'regular', 'student'
        $medicalConditions = trim($_POST['medical_conditions'] ?? '');
        $fitnessGoals = trim($_POST['fitness_goals'] ?? '');
        $scheduleAssessment = isset($_POST['schedule_assessment']) ? 1 : 0;
        $assessmentDate = trim($_POST['assessment_date'] ?? '');
        $preferredSchedule = trim($_POST['preferred_schedule'] ?? '');
        
        // Validate required fields
        if (empty($firstName) || empty($lastName)) {
            echo json_encode(['success' => false, 'message' => 'First name and last name are required']);
            return;
        }
        
        // Validate email
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            return;
        }
        
        // Validate age - member must be at least 18 years old
        if (!empty($birthdate)) {
            try {
                $birthdateObj = new DateTime($birthdate);
                $today = new DateTime();
                $age = $today->diff($birthdateObj)->y;
                
                // More precise age calculation (account for whether birthday has occurred this year)
                $birthdateFormatted = $birthdateObj->format('m-d');
                $todayFormatted = $today->format('m-d');
                if ($todayFormatted < $birthdateFormatted) {
                    $age--;
                }
                
                if ($age < 18) {
                    echo json_encode(['success' => false, 'message' => 'Members must be at least 18 years old. This applicant is only ' . $age . ' years old.']);
                    return;
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Invalid birthdate format']);
                return;
            }
        }
        
        // Check if email already exists
        if (!empty($email)) {
            $checkEmail = $conn->prepare("SELECT client_id FROM clients WHERE email = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                return;
            }
        }
        
        // Generate username from name if not provided
        $username = strtolower(substr($firstName, 0, 1) . $lastName);
        $counter = 1;
        $baseUsername = $username;
        while (true) {
            $checkUsername = $conn->prepare("SELECT client_id FROM clients WHERE username = ?");
            $checkUsername->execute([$username]);
            if (!$checkUsername->fetch()) {
                break;
            }
            $username = $baseUsername . $counter++;
        }
        
        // Generate temporary password
        $tempPassword = bin2hex(random_bytes(6)); // e.g., a1b2c3d4e5f6
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        
        // Determine which membership plans to assign based on selection
        $baseMembershipId = null;
        $monthlyMembershipId = null;
        
        // Get plan IDs from database
        $plansStmt = $conn->prepare("SELECT membership_id, is_base_membership, plan_name FROM memberships WHERE status = 'Active'");
        $plansStmt->execute();
        $allPlans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($allPlans as $plan) {
            if ($plan['is_base_membership']) {
                $baseMembershipId = $plan['membership_id'];
            } elseif ($membershipPlanType === 'student' && stripos($plan['plan_name'], 'student') !== false) {
                $monthlyMembershipId = $plan['membership_id'];
            } elseif ($membershipPlanType === 'regular' && stripos($plan['plan_name'], 'regular') !== false) {
                $monthlyMembershipId = $plan['membership_id'];
            }
        }
        
        // For current_membership_id, use the monthly plan if selected, otherwise use base
        $currentMembershipId = $monthlyMembershipId ?? $baseMembershipId;
        
        // Insert member into clients table (without current_membership_id first)
        $sql = "INSERT INTO clients (
            first_name, middle_name, last_name, phone, email, username, 
            password_hash, client_type, status, account_status, is_verified, join_date, referral_source
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            $firstName, $middleName, $lastName, $phone, $email, $username,
            $passwordHash, 'Member', 'Active', 'Active', 0, !empty($referralSource) ? $referralSource : null
        ]);
        
        if ($result) {
            $newClientId = $conn->lastInsertId();
            
            // Insert profile details into profile_details table
            $profileSql = "INSERT INTO profile_details (
                client_id, birthdate, gender, street_address, fitness_goals
            ) VALUES (?, ?, ?, ?, ?)";
            
            $profileStmt = $conn->prepare($profileSql);
            $profileStmt->execute([
                $newClientId,
                !empty($birthdate) ? $birthdate : null,
                !empty($gender) ? $gender : null,
                !empty($address) ? $address : null,
                !empty($fitnessGoals) ? $fitnessGoals : null
            ]);
            
            // Create membership record for the selected plan
            $membershipRecordId = null;
            
            if ($membershipPlanType !== 'none' && $monthlyMembershipId) {
                // Create record for monthly plan (30 days)
                $monthlyEndDate = date('Y-m-d', strtotime('+30 days'));
                
                $membershipSql = "INSERT INTO client_memberships (
                    client_id, membership_id, start_date, end_date, status
                ) VALUES (?, ?, ?, ?, 'Active')";
                
                $membershipStmt = $conn->prepare($membershipSql);
                $membershipStmt->execute([$newClientId, $monthlyMembershipId, date('Y-m-d'), $monthlyEndDate]);
                
                $membershipRecordId = $conn->lastInsertId();
            } elseif ($baseMembershipId) {
                // Create record for base membership (365 days)
                $baseEndDate = date('Y-m-d', strtotime('+365 days'));
                
                $membershipSql = "INSERT INTO client_memberships (
                    client_id, membership_id, start_date, end_date, status
                ) VALUES (?, ?, ?, ?, 'Active')";
                
                $membershipStmt = $conn->prepare($membershipSql);
                $membershipStmt->execute([$newClientId, $baseMembershipId, date('Y-m-d'), $baseEndDate]);
                
                $membershipRecordId = $conn->lastInsertId();
            }
            
            // Now update clients table to reference the membership record
            if ($membershipRecordId) {
                $updateClientSql = "UPDATE clients SET current_membership_id = ? WHERE client_id = ?";
                $updateStmt = $conn->prepare($updateClientSql);
                $updateStmt->execute([$membershipRecordId, $newClientId]);
            }
            
            
            // Log activity
            logActivity($conn, $newClientId, 'Member Registration', "New member registered by manager: $firstName $lastName");
            
            // Send welcome email with credentials if email provided
            if (!empty($email)) {
                $emailResult = sendMemberWelcomeEmail($email, $firstName, $lastName, $username, $tempPassword);
                
                if (!$emailResult['success']) {
                    error_log("Warning: Email sending failed for member $newClientId: " . $emailResult['message']);
                }
            }
            
            // Create assessment inquiry if requested
            if ($scheduleAssessment) {
                try {
                    $assessmentSql = "INSERT INTO assessment_inquiries (
                        first_name, middle_name, last_name, email, phone, birthdate, gender, address,
                        referral_source, medical_conditions, fitness_goals, preferred_schedule, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
                    
                    $assessmentStmt = $conn->prepare($assessmentSql);
                    $assessmentStmt->execute([
                        $firstName, $middleName, $lastName, $email, $phone, 
                        !empty($birthdate) ? $birthdate : null,
                        !empty($gender) ? $gender : null,
                        !empty($address) ? $address : null,
                        !empty($referralSource) ? $referralSource : null,
                        !empty($medicalConditions) ? $medicalConditions : null,
                        !empty($fitnessGoals) ? $fitnessGoals : null,
                        !empty($preferredSchedule) ? $preferredSchedule : null
                    ]);
                } catch (Exception $e) {
                    error_log("Warning: Failed to create assessment inquiry: " . $e->getMessage());
                }
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Member added successfully' . ($scheduleAssessment ? ' and assessment scheduled' : ''),
                'member' => [
                    'client_id' => $newClientId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'username' => $username
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to insert member into database']);
        }
    } catch (Exception $e) {
        error_log("Error in addMember: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error adding member: ' . $e->getMessage()]);
    }
}

/**
 * Edit member
 */
function editMember($conn) {
    try {
        $clientId = $_POST['client_id'];
        $firstName = trim($_POST['first_name']);
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name']);
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $birthdate = trim($_POST['birthdate'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $referralSource = trim($_POST['referral_source'] ?? '');
        $membershipPlanType = trim($_POST['membership_plan'] ?? 'none'); // Values: 'none', 'regular', 'student'
        $medicalConditions = trim($_POST['medical_conditions'] ?? '');
        $fitnessGoals = trim($_POST['fitness_goals'] ?? '');
        
        // Validate required fields
        if (empty($firstName) || empty($lastName)) {
            echo json_encode(['success' => false, 'message' => 'First name and last name are required']);
            return;
        }
        
        // Validate email
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            return;
        }
        
        // Validate age - member must be at least 18 years old
        if (!empty($birthdate)) {
            try {
                $birthdateObj = new DateTime($birthdate);
                $today = new DateTime();
                $age = $today->diff($birthdateObj)->y;
                
                // More precise age calculation (account for whether birthday has occurred this year)
                $birthdateFormatted = $birthdateObj->format('m-d');
                $todayFormatted = $today->format('m-d');
                if ($todayFormatted < $birthdateFormatted) {
                    $age--;
                }
                
                if ($age < 18) {
                    echo json_encode(['success' => false, 'message' => 'Members must be at least 18 years old. This applicant is only ' . $age . ' years old.']);
                    return;
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Invalid birthdate format']);
                return;
            }
        }
        
        // Check if email already exists (excluding current member)
        if (!empty($email)) {
            $checkEmail = $conn->prepare("SELECT client_id FROM clients WHERE email = ? AND client_id != ?");
            $checkEmail->execute([$email, $clientId]);
            if ($checkEmail->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                return;
            }
        }
        
        // Determine which membership plans to assign based on selection
        $baseMembershipId = null;
        $monthlyMembershipId = null;
        
        // Get plan IDs from database
        $plansStmt = $conn->prepare("SELECT membership_id, is_base_membership, plan_name FROM memberships WHERE status = 'Active'");
        $plansStmt->execute();
        $allPlans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($allPlans as $plan) {
            if ($plan['is_base_membership']) {
                $baseMembershipId = $plan['membership_id'];
            } elseif ($membershipPlanType === 'student' && stripos($plan['plan_name'], 'student') !== false) {
                $monthlyMembershipId = $plan['membership_id'];
            } elseif ($membershipPlanType === 'regular' && stripos($plan['plan_name'], 'regular') !== false) {
                $monthlyMembershipId = $plan['membership_id'];
            }
        }
        
        // For current_membership_id, use the monthly plan if selected, otherwise use base
        $currentMembershipId = $monthlyMembershipId ?? $baseMembershipId;
        
        // Update clients table with basic info (without current_membership_id yet)
        $sql = "UPDATE clients SET 
            first_name = ?, middle_name = ?, last_name = ?, phone = ?, email = ?,
            referral_source = ?
            WHERE client_id = ?";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            $firstName, $middleName, $lastName, $phone, $email,
            !empty($referralSource) ? $referralSource : null,
            $clientId
        ]);
        
        if (!$result) {
            throw new Exception("Failed to update client information");
        }
        
        // Handle membership plan update
        $membershipRecordId = null;
        
        if ($membershipPlanType !== 'none' && $monthlyMembershipId) {
            // Create new record for monthly plan (30 days)
            $monthlyEndDate = date('Y-m-d', strtotime('+30 days'));
            
            $membershipSql = "INSERT INTO client_memberships (
                client_id, membership_id, start_date, end_date, status
            ) VALUES (?, ?, ?, ?, 'Active')";
            
            $membershipStmt = $conn->prepare($membershipSql);
            $membershipStmt->execute([$clientId, $monthlyMembershipId, date('Y-m-d'), $monthlyEndDate]);
            
            $membershipRecordId = $conn->lastInsertId();
        } elseif ($baseMembershipId) {
            // Create new record for base membership (365 days)
            $baseEndDate = date('Y-m-d', strtotime('+365 days'));
            
            $membershipSql = "INSERT INTO client_memberships (
                client_id, membership_id, start_date, end_date, status
            ) VALUES (?, ?, ?, ?, 'Active')";
            
            $membershipStmt = $conn->prepare($membershipSql);
            $membershipStmt->execute([$clientId, $baseMembershipId, date('Y-m-d'), $baseEndDate]);
            
            $membershipRecordId = $conn->lastInsertId();
        }
        
        // Update clients table to reference the membership record
        if ($membershipRecordId) {
            $updateClientSql = "UPDATE clients SET current_membership_id = ? WHERE client_id = ?";
            $updateStmt = $conn->prepare($updateClientSql);
            $updateStmt->execute([$membershipRecordId, $clientId]);
        }
        
        // Update or insert profile_details
        $profileCheckSql = "SELECT profile_id FROM profile_details WHERE client_id = ?";
        $profileCheckStmt = $conn->prepare($profileCheckSql);
        $profileCheckStmt->execute([$clientId]);
        $profileExists = $profileCheckStmt->fetch();
        
        if ($profileExists) {
            // Update existing profile
            $profileSql = "UPDATE profile_details SET 
                birthdate = ?, gender = ?, street_address = ?, fitness_goals = ?
                WHERE client_id = ?";
            
            $profileStmt = $conn->prepare($profileSql);
            $profileStmt->execute([
                !empty($birthdate) ? $birthdate : null,
                !empty($gender) ? $gender : null,
                !empty($address) ? $address : null,
                !empty($fitnessGoals) ? $fitnessGoals : null,
                $clientId
            ]);
        } else {
            // Insert new profile
            $profileSql = "INSERT INTO profile_details (
                client_id, birthdate, gender, street_address, fitness_goals
            ) VALUES (?, ?, ?, ?, ?)";
            
            $profileStmt = $conn->prepare($profileSql);
            $profileStmt->execute([
                $clientId,
                !empty($birthdate) ? $birthdate : null,
                !empty($gender) ? $gender : null,
                !empty($address) ? $address : null,
                !empty($fitnessGoals) ? $fitnessGoals : null
            ]);
        }
        
        // Log activity
        logActivity($conn, $clientId, 'Profile Update', "Member profile updated by manager");
        
        echo json_encode(['success' => true, 'message' => 'Member updated successfully']);
    } catch (Exception $e) {
        error_log("Error in editMember: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Delete member
 */
function deleteMember($conn) {
    $clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? 0;
    
    if (!$clientId) {
        echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
        return;
    }
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // First, deactivate all active memberships for this member
        $deactivateSql = "UPDATE client_memberships 
                         SET status = 'Cancelled'
                         WHERE client_id = ? AND status = 'Active'";
        $deactivateStmt = $conn->prepare($deactivateSql);
        $deactivateStmt->execute([$clientId]);
        
        // Delete member assessments
        $deleteAssessmentsSql = "DELETE FROM assessment WHERE client_id = ?";
        $deleteAssessmentsStmt = $conn->prepare($deleteAssessmentsSql);
        $deleteAssessmentsStmt->execute([$clientId]);
        
        // Delete member booking requests
        $deleteBookingRequestsSql = "DELETE FROM booking_requests WHERE client_id = ?";
        $deleteBookingRequestsStmt = $conn->prepare($deleteBookingRequestsSql);
        $deleteBookingRequestsStmt->execute([$clientId]);
        
        // Delete member class bookings
        $deleteClassBookingsSql = "DELETE FROM class_bookings WHERE member_id = ?";
        $deleteClassBookingsStmt = $conn->prepare($deleteClassBookingsSql);
        $deleteClassBookingsStmt->execute([$clientId]);
        
        // Delete member calendar events
        $deleteEventsSql = "DELETE FROM calendar_events WHERE client_id = ?";
        $deleteEventsStmt = $conn->prepare($deleteEventsSql);
        $deleteEventsStmt->execute([$clientId]);
        
        // Delete member achievements
        $deleteAchievementsSql = "DELETE FROM client_achievements WHERE client_id = ?";
        $deleteAchievementsStmt = $conn->prepare($deleteAchievementsSql);
        $deleteAchievementsStmt->execute([$clientId]);
        
        // Delete member activity logs
        $deleteActivitySql = "DELETE FROM client_activity WHERE client_id = ?";
        $deleteActivityStmt = $conn->prepare($deleteActivitySql);
        $deleteActivityStmt->execute([$clientId]);
        
        // Delete member class packages
        $deleteClassPackagesSql = "DELETE FROM client_class_packages WHERE client_id = ?";
        $deleteClassPackagesStmt = $conn->prepare($deleteClassPackagesSql);
        $deleteClassPackagesStmt->execute([$clientId]);
        
        // Delete member
        $deleteSql = "DELETE FROM clients WHERE client_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->execute([$clientId]);
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Member deleted successfully.']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        error_log("Error deleting member: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error deleting member: ' . $e->getMessage()]);
    }
}

/**
 * Verify member
 */
function verifyMember($conn) {
    $clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? 0;
    
    $sql = "UPDATE clients SET is_verified = 1, verification_token = NULL WHERE client_id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$clientId]);
    
    if ($result) {
        // Log activity
        logActivity($conn, $clientId, 'Email Verification', 'Email verified by admin');
        
        echo json_encode(['success' => true, 'message' => 'Member verified successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to verify member']);
    }
}

/**
 * Export members
 */
function exportMembers($conn) {
    $sql = "SELECT * FROM clients ORDER BY join_date DESC";
    $stmt = $conn->query($sql);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'members' => $members]);
}

/**
 * Bulk update status
 */
function bulkUpdateStatus($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $memberIds = $data['members'] ?? [];
    $status = $data['status'] ?? '';
    
    if (empty($memberIds) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        return;
    }
    
    $placeholders = str_repeat('?,', count($memberIds) - 1) . '?';
    $sql = "UPDATE clients SET status = ? WHERE client_id IN ($placeholders)";
    
    $params = array_merge([$status], $memberIds);
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        // Log activity for each member
        foreach ($memberIds as $memberId) {
            logActivity($conn, $memberId, 'Status Update', "Status changed to $status by admin");
        }
        
        echo json_encode(['success' => true, 'message' => count($memberIds) . ' member(s) updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update members']);
    }
}

/**
 * Get pending payment verifications
 */
function getPendingPaymentVerifications($conn) {
    $sql = "SELECT 
                c.client_id,
                c.username,
                c.first_name,
                c.middle_name,
                c.last_name,
                c.email,
                c.phone,
                c.join_date,
                cm.id as membership_record_id,
                cm.membership_id,
                cm.start_date as membership_start,
                cm.end_date as membership_end,
                m.plan_name,
                m.monthly_fee,
                up.payment_id,
                up.payment_date,
                up.amount,
                up.payment_method,
                up.payment_status,
                up.reference_id,
                up.remarks
            FROM clients c
            INNER JOIN client_memberships cm ON c.client_id = cm.client_id
            INNER JOIN memberships m ON cm.membership_id = m.membership_id
            INNER JOIN unified_payments up ON cm.id = up.reference_id AND up.payment_type = 'Membership'
            WHERE up.payment_status = 'Pending'
            ORDER BY up.payment_date DESC";
    
    $stmt = $conn->query($sql);
    $pendingPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'pending_payments' => $pendingPayments, 'count' => count($pendingPayments)]);
}

/**
 * Approve payment verification
 */
function approvePaymentVerification($conn) {
    $paymentId = $_POST['payment_id'] ?? 0;
    $remarks = $_POST['remarks'] ?? '';
    
    if (!$paymentId) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
        return;
    }
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Update payment status to 'Completed'
        $sql = "UPDATE unified_payments SET payment_status = 'Completed' WHERE payment_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$paymentId]);
        
        // Get payment details to update membership if needed
        $sql = "SELECT reference_id, payment_type FROM unified_payments WHERE payment_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment['payment_type'] === 'Membership') {
            // Update membership status to 'Active' if applicable
            $sql = "UPDATE client_memberships SET status = 'Active' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$payment['reference_id']]);
        }
        
        // Log the action
        $sql = "INSERT INTO payment_verification_logs (payment_id, verified_by, verification_status, remarks, verified_at) 
                VALUES (?, ?, 'Approved', ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$paymentId, $_SESSION['employee_id'], $remarks]);
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Payment approved successfully']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Reject payment verification
 */
function rejectPaymentVerification($conn) {
    $paymentId = $_POST['payment_id'] ?? 0;
    $remarks = $_POST['remarks'] ?? '';
    
    if (!$paymentId) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
        return;
    }
    
    if (!$remarks) {
        echo json_encode(['success' => false, 'message' => 'Rejection remarks are required']);
        return;
    }
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Update payment status to 'Rejected'
        $sql = "UPDATE unified_payments SET payment_status = 'Rejected' WHERE payment_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$paymentId]);
        
        // Log the action
        $sql = "INSERT INTO payment_verification_logs (payment_id, verified_by, verification_status, remarks, verified_at) 
                VALUES (?, ?, 'Rejected', ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$paymentId, $_SESSION['employee_id'], $remarks]);
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Payment rejected successfully']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Log client activity
 */
function logActivity($conn, $clientId, $activityType, $description) {
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $sql = "INSERT INTO client_activity (client_id, activity_type, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$clientId, $activityType, $description, $ipAddress, $userAgent]);
    } catch (Exception $e) {
        // Silently fail if activity logging fails
        error_log('Activity logging failed: ' . $e->getMessage());
    }
}
?>