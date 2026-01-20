<?php
session_start();
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/includes/email_functions.php';

$conn = getDBConnection();
$message = '';
$messageType = ''; // 'success' or 'error'
$emailSent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $message = "Please enter your email address.";
        $messageType = 'error';
    } else {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $messageType = 'error';
        } else {
            try {
                // Check if email exists in employees table
                $stmt = $conn->prepare("SELECT employee_id, first_name, last_name, email FROM employees WHERE email = :email AND status = 'Active'");
                $stmt->execute(['email' => $email]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($employee) {
                    // Generate a unique reset token
                    $resetToken = bin2hex(random_bytes(32));
                    $tokenExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Store the reset token in the database
                    $updateStmt = $conn->prepare("UPDATE employees SET reset_token = :token, reset_token_expires = :expires WHERE employee_id = :id");
                    $updateResult = $updateStmt->execute([
                        'token' => $resetToken,
                        'expires' => $tokenExpiry,
                        'id' => $employee['employee_id']
                    ]);

                    if (!$updateResult) {
                        throw new Exception("Failed to store reset token in database");
                    }

                    // Build the reset link
                    $resetLink = 'http://' . $_SERVER['HTTP_HOST'] . '/empirefitness/reset-password.php?token=' . $resetToken . '&email=' . urlencode($email);

                    // Prepare email body
                    $subject = "Password Reset Request - Empire Fitness";
                    $body = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                            .button-container { text-align: center; margin: 30px 0; }
                            .reset-button { display: inline-block; background: #dc2626; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; }
                            .reset-button:hover { background: #991b1b; }
                            .expiry-warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0; color: #856404; }
                            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; border-top: 1px solid #ddd; padding-top: 20px; }
                            code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; word-break: break-all; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>🔐 Password Reset Request</h1>
                            </div>
                            <div class='content'>
                                <h2>Hello {$employee['first_name']} {$employee['last_name']},</h2>
                                <p>We received a request to reset your password for your Empire Fitness staff account.</p>
                                
                                <div class='button-container'>
                                    <a href='$resetLink' class='reset-button'>Reset Your Password</a>
                                </div>
                                
                                <p>Or copy and paste this link in your browser:</p>
                                <p><code>$resetLink</code></p>
                                
                                <div class='expiry-warning'>
                                    <strong>⏰ Important:</strong> This password reset link will expire in <strong>1 hour</strong>. If you don't use it within this time, you'll need to request a new password reset.
                                </div>
                                
                                <p><strong>Didn't request a password reset?</strong></p>
                                <p>If you didn't request this password reset, please ignore this email or contact your administrator if you have concerns about your account security.</p>
                                
                                <div class='footer'>
                                    <p>This is an automated message from Empire Fitness.</p>
                                    <p>Please do not reply to this email.</p>
                                </div>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";

                    // Send the email
                    $emailResult = sendEmail($email, $subject, $body);

                    if ($emailResult['success']) {
                        $message = "Password reset link has been sent to your email address. Please check your inbox and click the link to reset your password.";
                        $messageType = 'success';
                        $emailSent = true;
                    } else {
                        $message = "Failed to send reset email. Please try again later.";
                        $messageType = 'error';
                    }
                } else {
                    // For security, don't reveal whether email exists or not
                    $message = "If an account with this email exists, you will receive a password reset link shortly.";
                    $messageType = 'success';
                    $emailSent = true;
                }
            } catch (Exception $e) {
                $message = "An error occurred. Please try again later.";
                $messageType = 'error';
                // Log the actual error for debugging
                error_log("Password reset error: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Empire Fitness</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index-style.css">
</head>
<body>
<div class="container">
    <div class="left-panel">
        <div class="tech-background"></div>
        <div class="isometric-shapes"></div>
        <div class="logo">
            <h1>EMPIRE FITNESS</h1>
            <div class="subtitle-logo">XTREME GYM</div>
        </div>
    </div>

    <div class="right-panel">
        <form class="login-form" method="POST" action="forgot-password.php">
            <h2 class="login-title">🔐 Forgot Password?</h2>
            <p class="subtitle">Enter your email address<br>We'll send you a link to reset your password</p>

            <?php if ($emailSent && $messageType === 'success'): ?>
                <div class="success-message" id="login-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php elseif (!empty($message)): ?>
                <div class="error-message" id="login-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-input" name="email" placeholder="your.email@empirefitness.com" required autofocus>
            </div>

            <button type="submit" class="login-btn">
                <span>SEND RESET LINK</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
            </button>

            <div class="login-info">
                <p>Remember your password? <a href="index.php" style="color: #d41c1c; text-decoration: none; font-weight: 500;">Back to Login</a></p>
            </div>
        </form>
    </div>
</div>

<script>
    // Show/hide messages
    const successMsg = document.querySelector('.success-message');
    if (successMsg) {
        setTimeout(() => {
            successMsg.style.opacity = '0.8';
        }, 3000);
    }
</script>
</body>
</html>
