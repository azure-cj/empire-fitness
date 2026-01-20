session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/config/connection.php';

$verificationStatus = null;
$message = '';
$memberName = '';
$memberEmail = '';
$accountCreated = false;

if (isset($_GET['registration_id']) && !empty($_GET['registration_id'])) {
    try {
        $conn = getDBConnection();
        $registrationId = $_GET['registration_id'];
        
        // Find the pending registration record with Verified status
        $stmt = $conn->prepare("
            SELECT * FROM pending_registrations 
            WHERE registration_id = ? AND status = 'Verified'
        ");
        $stmt->execute([$registrationId]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$registration) {
            $verificationStatus = 'error';
            $message = 'Invalid registration link or application not yet approved by manager.';
        } else {
            $memberName = $registration['first_name'] . ' ' . $registration['last_name'];
            $memberEmail = $registration['email'];
            
            // Check if account was already created
            if ($registration['client_id']) {
                $verificationStatus = 'already_verified';
                $message = 'Your email has already been verified and your account is active!';
                $accountCreated = true;
            } else {
                // First time - create account
                $conn->beginTransaction();
                try {
                    // Call the account creation logic
                    require_once __DIR__ . '/includes/email_functions.php';
                    
                    // Generate username from email
                    $emailParts = explode('@', $registration['email']);
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
                        $registration['first_name'],
                        $registration['middle_name'],
                        $registration['last_name'],
                        $registration['email'],
                        $registration['contact_number'],
                        $registration['referral_source'],
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
                        $registration['birthdate'],
                        strtolower($registration['gender']),
                        $registration['address']
                    ]);
                    
                    // Set membership dates
                    $startDate = date('Y-m-d');
                    $endDate = date('Y-m-d', strtotime('+1 month'));
                    
                    // Determine membership ID
                    if ($registration['base_membership'] && $registration['monthly_plan'] === 'none') {
                        $membershipId = 0;
                    } elseif ($registration['monthly_plan'] === 'student') {
                        $membershipId = 1;
                    } elseif ($registration['monthly_plan'] === 'regular') {
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
                    $stmt->execute([$clientId, $registrationId]);
                    
                    // Generate QR code if they have a monthly plan (monthly_plan !== 'none')
                    if ($registration['monthly_plan'] !== 'none') {
                        // Generate unique QR code hash
                        $qrCodeHash = 'EF-' . str_pad($clientId, 6, '0', STR_PAD_LEFT) . '-' . 
                                      md5($clientId . $registration['email'] . time());
                        
                        // Insert QR code
                        $stmt = $conn->prepare("
                            INSERT INTO member_qr_codes (client_id, qr_code_hash, is_active) 
                            VALUES (?, ?, 1)
                        ");
                        $stmt->execute([$clientId, $qrCodeHash]);
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Send credentials email
                    $sendResult = sendCredentialsEmailAfterVerification($registration['email'], $memberName, $username, $defaultPassword);
                    
                    $verificationStatus = 'success';
                    $message = 'Your email has been successfully verified!';
                    $accountCreated = true;
                    
                } catch (Exception $createError) {
                    $conn->rollBack();
                    $verificationStatus = 'error';
                    $message = 'Error creating account: ' . $createError->getMessage();
                    error_log('Account creation error: ' . $createError->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        $verificationStatus = 'error';
        $message = 'An error occurred during verification. Please try again later.';
        error_log('Email verification error: ' . $e->getMessage());
    }
} else {
    $verificationStatus = 'error';
    $message = 'No registration link provided.';
}
?>
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
                    
                    // Generate QR code if they have a monthly plan (monthly_plan !== 'none')
                    if ($verification['monthly_plan'] !== 'none') {
                        // Generate unique QR code hash
                        $qrCodeHash = 'EF-' . str_pad($clientId, 6, '0', STR_PAD_LEFT) . '-' . 
                                      md5($clientId . $verification['email'] . time());
                        
                        // Insert QR code
                        $stmt = $conn->prepare("
                            INSERT INTO member_qr_codes (client_id, qr_code_hash, is_active) 
                            VALUES (?, ?, 1)
                        ");
                        $stmt->execute([$clientId, $qrCodeHash]);
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Send credentials email
                    $sendResult = sendCredentialsEmailAfterVerification($verification['email'], $memberName, $username, $defaultPassword);
                    
                    $verificationStatus = 'success';
                    $message = 'Your email has been successfully verified!';
                    $accountCreated = true;
                    
                } catch (Exception $createError) {
                    $conn->rollBack();
                    $verificationStatus = 'error';
                    $message = 'Error creating account: ' . $createError->getMessage();
                    error_log('Account creation error: ' . $createError->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        $verificationStatus = 'error';
        $message = 'An error occurred during verification. Please try again later.';
        error_log('Email verification error: ' . $e->getMessage());
    }
} else {
    $verificationStatus = 'error';
    $message = 'No verification token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Empire Fitness</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .verification-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            padding: 50px 40px;
            text-align: center;
        }
        
        .logo {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }
        
        .message {
            margin: 20px 0;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .status-success {
            color: #28a745;
        }
        
        .status-error {
            color: #dc3545;
        }
        
        .status-already-verified {
            color: #ffc107;
        }
        
        .member-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            border-left: 4px solid #28a745;
        }
        
        .member-info p {
            margin: 10px 0;
            font-size: 14px;
            color: #555;
        }
        
        .member-info strong {
            color: #333;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #28a745;
            color: white;
        }
        
        .btn-primary:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .icon {
            font-size: 64px;
            margin: 20px 0;
        }
        
        .steps {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        
        .steps h3 {
            text-align: center;
            margin-bottom: 15px;
            color: #333;
        }
        
        .steps ol {
            margin-left: 20px;
            color: #555;
        }
        
        .steps li {
            margin: 10px 0;
        }
        
        .footer-text {
            color: #999;
            font-size: 12px;
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .error-details {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <?php if ($verificationStatus === 'success' || $accountCreated): ?>
            <div class="icon">✅</div>
            <h1>Email Verified Successfully!</h1>
            
            <div class="member-info">
                <p><strong>Welcome, <?php echo htmlspecialchars($memberName); ?>!</strong></p>
                <p>Email: <?php echo htmlspecialchars($memberEmail); ?></p>
            </div>
            
            <p class="message status-success">
                <strong><?php echo htmlspecialchars($message); ?></strong>
            </p>
            
            <div class="steps">
                <h3>What's Next?</h3>
                <ol>
                    <li>Check your email for your login credentials</li>
                    <li>Log in to your account</li>
                    <li>Change your password for security</li>
                    <li>Start your fitness journey!</li>
                </ol>
            </div>
            
            <div class="action-buttons">
                <a href="index.php" class="btn btn-primary">Go to Login</a>
                <a href="javascript:window.close();" class="btn btn-secondary">Close Window</a>
            </div>
            
        <?php elseif ($verificationStatus === 'already_verified'): ?>
            <div class="icon">ℹ️</div>
            <h1>Already Verified</h1>
            
            <p class="message status-already-verified">
                <strong><?php echo htmlspecialchars($message); ?></strong>
            </p>
            
            <div class="member-info">
                <p><strong><?php echo htmlspecialchars($memberName); ?></strong></p>
                <p>Your credentials should have been sent to: <?php echo htmlspecialchars($memberEmail); ?></p>
            </div>
            
            <div class="steps">
                <h3>Ready to Login?</h3>
                <ol>
                    <li>Check your email for your login credentials</li>
                    <li>Log in to your account</li>
                    <li>Change your password in your profile</li>
                </ol>
            </div>
            
            <div class="action-buttons">
                <a href="index.php" class="btn btn-primary">Go to Login</a>
            </div>
            
        <?php else: ?>
            <div class="icon">❌</div>
            <h1>Verification Failed</h1>
            
            <div class="error-details">
                <strong>Error:</strong><br>
                <?php echo htmlspecialchars($message); ?>
            </div>
            
            <p class="message status-error" style="margin-top: 20px;">
                <strong>Your verification link is invalid or has expired.</strong>
            </p>
            
            <div class="steps">
                <h3>What Can You Do?</h3>
                <ol>
                    <li>Request a new verification email from the admin</li>
                    <li>Contact support if you need assistance</li>
                    <li>Return to the login page</li>
                </ol>
            </div>
            
            <div class="action-buttons">
                <a href="index.php" class="btn btn-secondary">Back to Login</a>
            </div>
        <?php endif; ?>
        
        <div class="footer-text">
            <p>&copy; 2026 Empire Fitness. All rights reserved.</p>
            <p>If you have any questions, please contact our support team.</p>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Empire Fitness</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .verification-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            padding: 50px 40px;
            text-align: center;
        }
        
        .logo {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }
        
        .message {
            margin: 20px 0;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .status-success {
            color: #28a745;
        }
        
        .status-error {
            color: #dc3545;
        }
        
        .status-already-verified {
            color: #ffc107;
        }
        
        .member-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            border-left: 4px solid #28a745;
        }
        
        .member-info p {
            margin: 10px 0;
            font-size: 14px;
            color: #555;
        }
        
        .member-info strong {
            color: #333;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #28a745;
            color: white;
        }
        
        .btn-primary:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .icon {
            font-size: 64px;
            margin: 20px 0;
        }
        
        .steps {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        
        .steps h3 {
            text-align: center;
            margin-bottom: 15px;
            color: #333;
        }
        
        .steps ol {
            margin-left: 20px;
            color: #555;
        }
        
        .steps li {
            margin: 10px 0;
        }
        
        .footer-text {
            color: #999;
            font-size: 12px;
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .error-details {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <?php if ($verificationStatus === 'success'): ?>
            <div class="icon">✅</div>
            <h1>Email Verified Successfully!</h1>
            
            <div class="member-info">
                <p><strong>Welcome, <?php echo htmlspecialchars($memberName); ?>!</strong></p>
                <p>Email: <?php echo htmlspecialchars($memberEmail); ?></p>
            </div>
            
            <p class="message status-success">
                <strong><?php echo htmlspecialchars($message); ?></strong>
            </p>
            
            <div class="steps">
                <h3>What's Next?</h3>
                <ol>
                    <li>Check your email for your login credentials</li>
                    <li>Log in to your account</li>
                    <li>Change your password for security</li>
                    <li>Start your fitness journey!</li>
                </ol>
            </div>
            
            <div class="action-buttons">
                <a href="index.php" class="btn btn-primary">Go to Login</a>
                <a href="javascript:window.close();" class="btn btn-secondary">Close Window</a>
            </div>
            
        <?php elseif ($verificationStatus === 'already_verified'): ?>
            <div class="icon">ℹ️</div>
            <h1>Already Verified</h1>
            
            <p class="message status-already-verified">
                <strong><?php echo htmlspecialchars($message); ?></strong>
            </p>
            
            <div class="member-info">
                <p><strong><?php echo htmlspecialchars($memberName); ?></strong></p>
                <p>Your credentials should have been sent to: <?php echo htmlspecialchars($memberEmail); ?></p>
            </div>
            
            <div class="steps">
                <h3>Ready to Login?</h3>
                <ol>
                    <li>Check your email for your login credentials</li>
                    <li>Log in to your account</li>
                    <li>Change your password in your profile</li>
                </ol>
            </div>
            
            <div class="action-buttons">
                <a href="index.php" class="btn btn-primary">Go to Login</a>
            </div>
            
        <?php else: ?>
            <div class="icon">❌</div>
            <h1>Verification Failed</h1>
            
            <div class="error-details">
                <strong>Error:</strong><br>
                <?php echo htmlspecialchars($message); ?>
            </div>
            
            <p class="message status-error" style="margin-top: 20px;">
                <strong>Your verification link is invalid or has expired.</strong>
            </p>
            
            <div class="steps">
                <h3>What Can You Do?</h3>
                <ol>
                    <li>Request a new verification email from the admin</li>
                    <li>Contact support if you need assistance</li>
                    <li>Return to the login page</li>
                </ol>
            </div>
            
            <div class="action-buttons">
                <a href="index.php" class="btn btn-secondary">Back to Login</a>
            </div>
        <?php endif; ?>
        
        <div class="footer-text">
            <p>&copy; 2026 Empire Fitness. All rights reserved.</p>
            <p>If you have any questions, please contact our support team.</p>
        </div>
    </div>
</body>
</html>
