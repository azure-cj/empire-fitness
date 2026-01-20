<?php
/**
 * Email Verification Handler
 * This script is called when a user verifies their email
 * It creates the member account and sends login credentials
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../config/connection.php';
    require_once __DIR__ . '/../../includes/email_functions.php';
    
    $conn = getDBConnection();
    
    // Get the verification token from the request
    $token = $_POST['token'] ?? $_GET['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Verification token required']);
        exit;
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Get the verification record
        $stmt = $conn->prepare("
            SELECT ev.*, pr.* 
            FROM email_verifications ev
            JOIN pending_registrations pr ON ev.registration_id = pr.registration_id
            WHERE ev.verification_token = ? AND ev.is_verified = TRUE AND ev.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$verification) {
            throw new Exception('Invalid or expired verification token');
        }
        
        // Check if account was already created
        if ($verification['client_id']) {
            echo json_encode([
                'success' => true,
                'message' => 'Account already created',
                'client_id' => $verification['client_id']
            ]);
            exit;
        }
        
        // Generate username from email
        $emailParts = explode('@', $verification['email']);
        $baseUsername = strtolower($emailParts[0]);
        
        $username = $baseUsername;
        $counter = 1;
        while (true) {
            $stmt = $conn->prepare("SELECT client_id FROM clients WHERE username = ?");
            $stmt->execute([$username]);
            if (!$stmt->fetch()) break;
            $username = $baseUsername . $counter++;
        }
        
        // Generate temporary password
        $defaultPassword = '123';
        $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
        
        // Create client account
        $stmt = $conn->prepare("
            INSERT INTO clients (username, first_name, middle_name, last_name, email, phone, referral_source, password_hash, client_type, status, account_status, join_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Member', 'Active', 'Active', CURDATE())
        ");
        
        $stmt->execute([
            $username,
            $verification['first_name'],
            $verification['middle_name'],
            $verification['last_name'],
            $verification['email'],
            $verification['contact_number'],
            $verification['referral_source'],
            $passwordHash
        ]);
        
        $clientId = $conn->lastInsertId();
        if (!$clientId) {
            throw new Exception('Failed to create client account');
        }
        
        // Create profile details
        $stmt = $conn->prepare("INSERT INTO profile_details (client_id, birthdate, gender, street_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $clientId,
            $verification['birthdate'],
            strtolower($verification['gender']),
            $verification['address']
        ]);
        
        // Set membership dates
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+1 month'));
        
        // Determine membership ID
        if ($verification['base_membership'] && $verification['monthly_plan'] === 'none') {
            $membershipId = 0;
        } elseif ($verification['monthly_plan'] === 'student') {
            $membershipId = 1;
        } elseif ($verification['monthly_plan'] === 'regular') {
            $membershipId = 2;
        } else {
            $membershipId = 0;
        }
        
        // Create membership record
        $stmt = $conn->prepare("INSERT INTO client_memberships (client_id, membership_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'Active')");
        $stmt->execute([$clientId, $membershipId, $startDate, $endDate]);
        
        $membershipRecordId = $conn->lastInsertId();
        if (!$membershipRecordId) {
            throw new Exception('Failed to create membership record');
        }
        
        // Update client with current membership
        $stmt = $conn->prepare("UPDATE clients SET current_membership_id = ? WHERE client_id = ?");
        $stmt->execute([$membershipRecordId, $clientId]);
        
        // Update registration status to "Completed" and store client ID
        $stmt = $conn->prepare("UPDATE pending_registrations SET status = 'Completed', client_id = ? WHERE registration_id = ?");
        $stmt->execute([$clientId, $verification['registration_id']]);
        
        // Commit transaction
        $conn->commit();
        
        // Send credentials email
        $memberName = $verification['first_name'] . ' ' . $verification['last_name'];
        $emailResult = sendCredentialsEmailAfterVerification(
            $verification['email'],
            $memberName,
            $username,
            $defaultPassword
        );
        
        // Save account creation record
        saveAccountCreationRecord($verification, $username, $defaultPassword, $clientId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully. Login credentials sent to email.',
            'client_id' => $clientId,
            'username' => $username,
            'email_sent' => $emailResult['success']
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log('Email verification handler error: ' . $e->getMessage());
}

/**
 * Save account creation record to file
 */
function saveAccountCreationRecord($verification, $username, $password, $clientId) {
    $credentialsDir = __DIR__ . '/../credentials';
    if (!file_exists($credentialsDir)) {
        mkdir($credentialsDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_His');
    $filename = $credentialsDir . '/ACCOUNT_CREATED_' . $clientId . '_' . $timestamp . '.txt';
    
    $textContent = "=================================================\n";
    $textContent .= "EMPIRE FITNESS - ACCOUNT CREATED\n";
    $textContent .= "=================================================\n\n";
    $textContent .= "Date: " . date('F d, Y - h:i:s A') . "\n";
    $textContent .= "Registration ID: {$verification['registration_id']}\n";
    $textContent .= "Client ID: {$clientId}\n";
    $textContent .= "Member Name: {$verification['first_name']} {$verification['last_name']}\n";
    $textContent .= "Email: {$verification['email']}\n";
    $textContent .= "Phone: {$verification['contact_number']}\n\n";
    
    $textContent .= "--- ACCOUNT DETAILS ---\n";
    $textContent .= "Username: {$username}\n";
    $textContent .= "Temporary Password: {$password}\n";
    $textContent .= "Account Status: Active\n";
    $textContent .= "Client Type: Member\n\n";
    
    $textContent .= "--- VERIFICATION PROCESS ---\n";
    $textContent .= "1. Registration submitted\n";
    $textContent .= "2. Admin approved application\n";
    $textContent .= "3. Verification email sent\n";
    $textContent .= "4. User clicked verification link\n";
    $textContent .= "5. Email verified ✓\n";
    $textContent .= "6. Account created ✓\n";
    $textContent .= "7. Credentials sent via email ✓\n\n";
    
    $textContent .= "--- MEMBERSHIP DETAILS ---\n";
    $textContent .= "Membership Plan: ";
    if ($verification['base_membership']) {
        $textContent .= "Base Membership";
    }
    if ($verification['monthly_plan'] !== 'none') {
        $textContent .= ($verification['base_membership'] ? " + " : "") . ucfirst($verification['monthly_plan']) . " Monthly";
    }
    if (!$verification['base_membership'] && $verification['monthly_plan'] === 'none') {
        $textContent .= "Walk-in Only";
    }
    
    $textContent .= "\nStart Date: " . date('Y-m-d') . "\n";
    $textContent .= "Initial End Date: " . date('Y-m-d', strtotime('+1 month')) . "\n";
    $textContent .= "Total Amount Paid: ₱" . number_format($verification['total_amount'], 2) . "\n";
    $textContent .= "Payment Method: " . ($verification['payment_method'] ?? 'N/A') . "\n";
    $textContent .= "Reference Number: " . ($verification['reference_number'] ?? 'N/A') . "\n\n";
    
    $textContent .= "=================================================\n";
    $textContent .= "Credentials sent to: {$verification['email']}\n";
    $textContent .= "=================================================\n";
    
    file_put_contents($filename, $textContent);
}

?>
