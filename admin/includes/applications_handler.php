<?php
ob_start();
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

try {
    $connectionFile = '../../config/connection.php';
    if (!file_exists($connectionFile)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Connection file not found']);
        exit;
    }
    require_once $connectionFile;
    $pdo = getDBConnection();
    
    // Include email functions
    $emailFile = '../../includes/email_functions.php';
    if (file_exists($emailFile)) {
        require_once $emailFile;
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error loading connection: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$employeeId = $_SESSION['employee_id'] ?? 1;

switch($action) {
    case 'fetch':
        fetchApplications($pdo);
        break;
    case 'approve':
        approveApplication($pdo, $employeeId);
        break;
    case 'reject':
        rejectApplication($pdo, $employeeId);
        break;
    case 'view':
        viewApplication($pdo);
        break;
    case 'stats':
        getStats($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function fetchApplications($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                pr.registration_id,
                pr.first_name,
                pr.middle_name,
                pr.last_name,
                pr.email,
                pr.contact_number as phone,
                pr.birthdate,
                pr.gender,
                pr.address,
                pr.referral_source,
                pr.base_membership,
                pr.monthly_plan,
                pr.total_amount,
                pr.reference_number,
                pr.payment_date,
                pr.payment_method,
                pr.payment_proof,
                pr.payment_notes,
                pr.status,
                pr.rejection_reason,
                pr.client_id,
                pr.submitted_at,
                pr.verified_at,
                CONCAT(e.first_name, ' ', e.last_name) as verified_by_name
            FROM pending_registrations pr
            LEFT JOIN employees e ON pr.verified_by = e.employee_id
            ORDER BY 
                CASE pr.status
                    WHEN 'Pending' THEN 1
                    WHEN 'Verified' THEN 2
                    WHEN 'Completed' THEN 3
                    WHEN 'Rejected' THEN 4
                END,
                pr.submitted_at DESC
        ");
        
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // DEBUG: Log first record
        if (count($applications) > 0) {
            error_log("First application - birthdate: " . $applications[0]['birthdate'] . ", gender: " . $applications[0]['gender'] . ", address: " . substr($applications[0]['address'], 0, 50));
        }
        
        $formattedApplications = array_map(function($app) {
            $membershipPlan = '';
            if ($app['base_membership']) {
                $membershipPlan = 'Base Membership';
            }
            if ($app['monthly_plan'] !== 'none') {
                $planType = ucfirst($app['monthly_plan']);
                $membershipPlan .= ($membershipPlan ? ' + ' : '') . "$planType Monthly";
            }
            if (empty($membershipPlan)) {
                $membershipPlan = 'Walk-in Only';
            }
            
            $paymentProofPath = $app['payment_proof'];
            if ($paymentProofPath) {
                if (strpos($paymentProofPath, 'database/') === 0) {
                    $paymentProofPath = '/pbl_project/' . $paymentProofPath;
                } else {
                    $paymentProofPath = '/pbl_project/database/uploads/payment_proofs/' . basename($paymentProofPath);
                }
            }
            
            return [
                'id' => (int)$app['registration_id'],
                'firstName' => $app['first_name'],
                'middleName' => $app['middle_name'],
                'lastName' => $app['last_name'],
                'email' => $app['email'],
                'phone' => $app['phone'],
                'birthdate' => $app['birthdate'],
                'gender' => $app['gender'],
                'address' => $app['address'],
                'referralSource' => $app['referral_source'],
                'membershipPlan' => $membershipPlan,
                'appliedDate' => $app['submitted_at'],
                'status' => strtolower($app['status']),
                'totalAmount' => $app['total_amount'],
                'referenceNumber' => $app['reference_number'],
                'paymentDate' => $app['payment_date'],
                'paymentMethod' => $app['payment_method'],
                'paymentProof' => $paymentProofPath,
                'paymentNotes' => $app['payment_notes'],
                'baseMembership' => $app['base_membership'],
                'monthlyPlan' => $app['monthly_plan'],
                'rejectionReason' => $app['rejection_reason'],
                'clientId' => $app['client_id'],
                'verifiedByName' => $app['verified_by_name'],
                'verifiedAt' => $app['verified_at']
            ];
        }, $applications);
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'applications' => $formattedApplications
        ]);
        
    } catch(PDOException $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error fetching applications: ' . $e->getMessage()]);
    }
}

function getStats($pdo) {
    try {
        $pendingStmt = $pdo->query("SELECT COUNT(*) as count FROM pending_registrations WHERE status = 'Pending'");
        $pendingCount = $pendingStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $approvedStmt = $pdo->query("SELECT COUNT(*) as count FROM pending_registrations WHERE status = 'Completed' AND MONTH(verified_at) = MONTH(CURRENT_DATE()) AND YEAR(verified_at) = YEAR(CURRENT_DATE())");
        $approvedCount = $approvedStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $rejectedStmt = $pdo->query("SELECT COUNT(*) as count FROM pending_registrations WHERE status = 'Rejected'");
        $rejectedCount = $rejectedStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'stats' => [
                'pending' => $pendingCount,
                'approved_this_month' => $approvedCount,
                'rejected' => $rejectedCount
            ]
        ]);
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error fetching statistics: ' . $e->getMessage()]);
    }
}

function approveApplication($pdo, $employeeId) {
    $registrationId = $_POST['registration_id'] ?? 0;
    
    if (!$registrationId) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Registration ID required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get the registration data
        $stmt = $pdo->prepare("SELECT * FROM pending_registrations WHERE registration_id = ? AND status = 'Pending'");
        $stmt->execute([$registrationId]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$registration) {
            throw new Exception('Application not found or already processed');
        }
        
        // Generate unique verification token
        $verificationToken = bin2hex(random_bytes(32)); // 64 character token
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours')); // Token expires in 24 hours
        
        // Store verification token in database
        $stmt = $pdo->prepare("
            INSERT INTO email_verifications (registration_id, email, verification_token, expires_at) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $registrationId,
            $registration['email'],
            $verificationToken,
            $expiresAt
        ]);
        
        // Update registration status to "Verified" to mark that admin approved it
        $stmt = $pdo->prepare("UPDATE pending_registrations SET status = 'Verified', verified_by = ?, verified_at = NOW() WHERE registration_id = ?");
        $stmt->execute([$employeeId, $registrationId]);
        
        $pdo->commit();
        
        // Send verification email instead of credentials
        $memberName = $registration['first_name'] . ' ' . $registration['last_name'];
        $emailResult = sendEmailVerificationEmail($registration['email'], $memberName, $verificationToken, $registrationId);
        
        // Save approval action to file for records
        saveApprovalActionToFile($registration, 'verification_email_sent', $verificationToken);
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Application approved successfully. Verification email sent to ' . htmlspecialchars($registration['email']),
            'registration_id' => $registrationId,
            'verification_token_sent' => true
        ]);
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function rejectApplication($pdo, $employeeId) {
    $registrationId = $_POST['registration_id'] ?? 0;
    $reason = $_POST['reason'] ?? 'Application rejected by administrator';
    
    if (!$registrationId) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Registration ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM pending_registrations WHERE registration_id = ?");
        $stmt->execute([$registrationId]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("UPDATE pending_registrations SET status = 'Rejected', rejection_reason = ?, verified_by = ?, verified_at = NOW() WHERE registration_id = ? AND status = 'Pending'");
        $result = $stmt->execute([$reason, $employeeId, $registrationId]);
        
        if ($stmt->rowCount() > 0) {
            saveCredentialsToFile($registration, null, null, 'rejected', $reason);
            $emailSent = sendRejectionEmail($registration, $reason);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Application rejected successfully' . ($emailSent ? ' (Email sent)' : ' (Notification saved to file)')
            ]);
        } else {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Application not found or already processed']);
        }
    } catch(PDOException $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error rejecting application: ' . $e->getMessage()]);
    }
}

function viewApplication($pdo) {
    $registrationId = $_GET['registration_id'] ?? 0;
    
    if (!$registrationId) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Registration ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM pending_registrations WHERE registration_id = ?");
        $stmt->execute([$registrationId]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($application) {
            ob_end_clean();
            echo json_encode(['success' => true, 'application' => $application]);
        } else {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Application not found']);
        }
    } catch(PDOException $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error fetching application: ' . $e->getMessage()]);
    }
}

function saveCredentialsToFile($registration, $username, $password, $status, $reason = null) {
    $credentialsDir = __DIR__ . '/../credentials';
    if (!file_exists($credentialsDir)) {
        mkdir($credentialsDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $filename = $credentialsDir . '/' . date('Y-m-d') . '_notifications.txt';
    
    if ($status === 'approved') {
        $content = "=================================================\n";
        $content .= "MEMBER ACCOUNT APPROVED\n";
        $content .= "=================================================\n";
        $content .= "Date: $timestamp\n";
        $content .= "Registration ID: {$registration['registration_id']}\n";
        $content .= "Name: {$registration['first_name']} {$registration['last_name']}\n";
        $content .= "Email: {$registration['email']}\n";
        $content .= "Phone: {$registration['contact_number']}\n";
        $content .= "\n--- LOGIN CREDENTIALS ---\n";
        $content .= "Username: $username\n";
        $content .= "Temporary Password: $password\n";
        $content .= "\nMembership Plan: ";
        if ($registration['base_membership']) $content .= "Base Membership";
        if ($registration['monthly_plan'] !== 'none') {
            $content .= ($registration['base_membership'] ? " + " : "") . ucfirst($registration['monthly_plan']) . " Monthly";
        }
        $content .= "\nTotal Amount: ₱" . number_format($registration['total_amount'], 2) . "\n";
        $content .= "=================================================\n\n";
    } else {
        $content = "=================================================\n";
        $content .= "APPLICATION REJECTED\n";
        $content .= "=================================================\n";
        $content .= "Date: $timestamp\n";
        $content .= "Registration ID: {$registration['registration_id']}\n";
        $content .= "Name: {$registration['first_name']} {$registration['last_name']}\n";
        $content .= "Email: {$registration['email']}\n";
        $content .= "Phone: {$registration['contact_number']}\n";
        $content .= "\nRejection Reason:\n$reason\n";
        $content .= "=================================================\n\n";
    }
    
    file_put_contents($filename, $content, FILE_APPEND);
}

function sendApprovalEmail($registration, $username, $password) {
    $credentialsDir = __DIR__ . '/../credentials';
    if (!file_exists($credentialsDir)) {
        mkdir($credentialsDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_His');
    $filename = $credentialsDir . '/APPROVED_' . $registration['registration_id'] . '_' . $timestamp . '.txt';
    
    $textContent = "=================================================\n";
    $textContent .= "EMPIRE FITNESS - APPLICATION APPROVED\n";
    $textContent .= "=================================================\n\n";
    $textContent .= "Dear {$registration['first_name']} {$registration['last_name']},\n\n";
    $textContent .= "Congratulations! Your membership application has been approved.\n\n";
    $textContent .= "Your member account has been created with the following credentials:\n\n";
    $textContent .= "--- LOGIN CREDENTIALS ---\n";
    $textContent .= "Username: $username\n";
    $textContent .= "Temporary Password: $password\n\n";
    $textContent .= "IMPORTANT: Please change your password after your first login for security purposes.\n\n";
    $textContent .= "--- MEMBERSHIP DETAILS ---\n";
    $textContent .= "Registration ID: {$registration['registration_id']}\n";
    $textContent .= "Member Name: {$registration['first_name']} {$registration['last_name']}\n";
    $textContent .= "Email: {$registration['email']}\n";
    $textContent .= "Phone: {$registration['contact_number']}\n";
    $textContent .= "Membership Plan: ";
    
    if ($registration['base_membership']) {
        $textContent .= "Base Membership";
    }
    if ($registration['monthly_plan'] !== 'none') {
        $textContent .= ($registration['base_membership'] ? " + " : "") . ucfirst($registration['monthly_plan']) . " Monthly";
    }
    if (!$registration['base_membership'] && $registration['monthly_plan'] === 'none') {
        $textContent .= "Walk-in Only";
    }
    
    $textContent .= "\nTotal Amount Paid: ₱" . number_format($registration['total_amount'], 2) . "\n";
    $textContent .= "Payment Method: " . ($registration['payment_method'] ?? 'N/A') . "\n";
    $textContent .= "Reference Number: " . ($registration['reference_number'] ?? 'N/A') . "\n\n";
    $textContent .= "--- NEXT STEPS ---\n";
    $textContent .= "1. Visit our gym at your convenience\n";
    $textContent .= "2. Log in to your account using the credentials above\n";
    $textContent .= "3. Change your password in your profile settings\n";
    $textContent .= "4. Start your fitness journey with us!\n\n";
    $textContent .= "If you have any questions or concerns, please don't hesitate to contact us.\n\n";
    $textContent .= "Welcome to Empire Fitness!\n\n";
    $textContent .= "Best regards,\n";
    $textContent .= "Empire Fitness Team\n\n";
    $textContent .= "=================================================\n";
    $textContent .= "Date Generated: " . date('F d, Y - h:i:s A') . "\n";
    $textContent .= "=================================================\n";
    
    // Save to file as backup
    file_put_contents($filename, $textContent);
    
    // Send HTML email using PHPMailer
    $membershipPlan = 'Walk-in Only';
    if ($registration['base_membership']) {
        $membershipPlan = 'Base Membership';
    }
    if ($registration['monthly_plan'] !== 'none') {
        $membershipPlan .= ($registration['base_membership'] ? " + " : "") . ucfirst($registration['monthly_plan']) . " Monthly";
    }
    
    $htmlBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .credentials { background: white; padding: 20px; border-left: 4px solid #dc2626; margin: 20px 0; }
            .details { background: white; padding: 15px; border: 1px solid #ddd; margin: 15px 0; border-radius: 5px; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Application Approved!</h1>
            </div>
            <div class='content'>
                <h2>Dear " . htmlspecialchars($registration['first_name']) . " " . htmlspecialchars($registration['last_name']) . ",</h2>
                <p>Congratulations! Your membership application has been approved.</p>
                <p>Your member account has been created and is ready to use.</p>
                
                <div class='credentials'>
                    <h3>Your Login Credentials:</h3>
                    <p><strong>Username:</strong> <code>" . htmlspecialchars($username) . "</code></p>
                    <p><strong>Temporary Password:</strong> <code>" . htmlspecialchars($password) . "</code></p>
                    <p><strong style='color: #dc2626;'>⚠️ Important:</strong> Please change your password after your first login for security.</p>
                </div>
                
                <div class='details'>
                    <h3>Membership Details:</h3>
                    <p><strong>Membership Plan:</strong> " . htmlspecialchars($membershipPlan) . "</p>
                    <p><strong>Total Amount Paid:</strong> ₱" . number_format($registration['total_amount'], 2) . "</p>
                    <p><strong>Payment Method:</strong> " . htmlspecialchars($registration['payment_method'] ?? 'N/A') . "</p>
                    <p><strong>Reference Number:</strong> " . htmlspecialchars($registration['reference_number'] ?? 'N/A') . "</p>
                </div>
                
                <h3>Next Steps:</h3>
                <ol>
                    <li>Visit our gym at your convenience</li>
                    <li>Log in to your member account</li>
                    <li>Update your profile and change your password</li>
                    <li>Start your fitness journey with us!</li>
                </ol>
                
                <p>If you have any questions or concerns, please don't hesitate to contact us.</p>
                
                <div class='footer'>
                    <p><strong>Welcome to Empire Fitness!</strong></p>
                    <p>Your Partner in Fitness</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email if function is available
    if (function_exists('sendEmail')) {
        $result = sendEmail(
            $registration['email'],
            'Welcome to Empire Fitness - Your Account is Ready!',
            $htmlBody,
            $textContent
        );
        return $result['success'];
    }
    
    return false;
}

function sendRejectionEmail($registration, $reason) {
    $credentialsDir = __DIR__ . '/../credentials';
    if (!file_exists($credentialsDir)) {
        mkdir($credentialsDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_His');
    $filename = $credentialsDir . '/REJECTED_' . $registration['registration_id'] . '_' . $timestamp . '.txt';
    
    $textContent = "=================================================\n";
    $textContent .= "EMPIRE FITNESS - APPLICATION STATUS UPDATE\n";
    $textContent .= "=================================================\n\n";
    $textContent .= "Dear {$registration['first_name']} {$registration['last_name']},\n\n";
    $textContent .= "Thank you for your interest in Empire Fitness.\n\n";
    $textContent .= "After careful review, we regret to inform you that we are unable to approve your membership application at this time.\n\n";
    $textContent .= "--- APPLICATION DETAILS ---\n";
    $textContent .= "Registration ID: {$registration['registration_id']}\n";
    $textContent .= "Applicant Name: {$registration['first_name']} {$registration['last_name']}\n";
    $textContent .= "Email: {$registration['email']}\n";
    $textContent .= "Phone: {$registration['contact_number']}\n\n";
    $textContent .= "--- REASON FOR REJECTION ---\n";
    $textContent .= "$reason\n\n";
    $textContent .= "If you believe this decision was made in error or if you would like to discuss this matter further, please feel free to contact us directly.\n\n";
    $textContent .= "We appreciate your understanding and hope to serve you in the future.\n\n";
    $textContent .= "Best regards,\n";
    $textContent .= "Empire Fitness Team\n\n";
    $textContent .= "=================================================\n";
    $textContent .= "Date Generated: " . date('F d, Y - h:i:s A') . "\n";
    $textContent .= "=================================================\n";
    
    // Save to file as backup
    file_put_contents($filename, $textContent);
    
    // Send HTML email using PHPMailer
    $htmlBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .notice { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Application Status Update</h1>
            </div>
            <div class='content'>
                <h2>Dear " . htmlspecialchars($registration['first_name']) . " " . htmlspecialchars($registration['last_name']) . ",</h2>
                <p>Thank you for your interest in Empire Fitness.</p>
                
                <div class='notice'>
                    <p>After careful review of your membership application, we regret to inform you that we are unable to approve it at this time.</p>
                </div>
                
                <h3>Reason for Decision:</h3>
                <p>" . nl2br(htmlspecialchars($reason)) . "</p>
                
                <p>If you believe this decision was made in error or if you would like to discuss this matter further, please feel free to contact us directly. We would be happy to help.</p>
                
                <p>We appreciate your understanding and hope to serve you in the future.</p>
                
                <div class='footer'>
                    <p><strong>Empire Fitness</strong></p>
                    <p>Your Partner in Fitness</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email if function is available
    if (function_exists('sendEmail')) {
        $result = sendEmail(
            $registration['email'],
            'Empire Fitness - Application Status Update',
            $htmlBody,
            $textContent
        );
        return $result['success'];
    }

    return false;
}

function saveApprovalActionToFile($registration, $action, $token = null) {
    $credentialsDir = __DIR__ . '/../credentials';
    if (!file_exists($credentialsDir)) {
        mkdir($credentialsDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_His');
    $filename = $credentialsDir . '/VERIFICATION_' . $registration['registration_id'] . '_' . $timestamp . '.txt';
    
    $textContent = "=================================================\n";
    $textContent .= "EMPIRE FITNESS - APPLICATION APPROVED\n";
    $textContent .= "EMAIL VERIFICATION WORKFLOW\n";
    $textContent .= "=================================================\n\n";
    $textContent .= "Date: " . date('F d, Y - h:i:s A') . "\n";
    $textContent .= "Registration ID: {$registration['registration_id']}\n";
    $textContent .= "Member Name: {$registration['first_name']} {$registration['last_name']}\n";
    $textContent .= "Email: {$registration['email']}\n\n";
    
    $textContent .= "--- ACTION ---\n";
    $textContent .= "Status: Application Approved\n";
    $textContent .= "Next Step: Email verification required\n";
    $textContent .= "Verification Email Sent: YES\n";
    $textContent .= "Verification Token: " . ($token ? substr($token, 0, 20) . '...' : 'N/A') . "\n";
    $textContent .= "Token Expires: " . date('Y-m-d H:i:s', strtotime('+24 hours')) . "\n\n";
    
    $textContent .= "--- FLOW ---\n";
    $textContent .= "1. Verification email sent to {$registration['email']}\n";
    $textContent .= "2. User clicks verification link in email\n";
    $textContent .= "3. Email is verified in system\n";
    $textContent .= "4. User receives login credentials\n\n";
    
    $textContent .= "--- MEMBERSHIP DETAILS ---\n";
    $textContent .= "Membership Plan: ";
    if ($registration['base_membership']) {
        $textContent .= "Base Membership";
    }
    if ($registration['monthly_plan'] !== 'none') {
        $textContent .= ($registration['base_membership'] ? " + " : "") . ucfirst($registration['monthly_plan']) . " Monthly";
    }
    if (!$registration['base_membership'] && $registration['monthly_plan'] === 'none') {
        $textContent .= "Walk-in Only";
    }
    
    $textContent .= "\nTotal Amount Paid: ₱" . number_format($registration['total_amount'], 2) . "\n";
    $textContent .= "Payment Method: " . ($registration['payment_method'] ?? 'N/A') . "\n";
    $textContent .= "Reference Number: " . ($registration['reference_number'] ?? 'N/A') . "\n\n";
    
    $textContent .= "=================================================\n";
    
    file_put_contents($filename, $textContent);
}

?>