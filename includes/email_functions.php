<?php
// Email Functions for Empire Fitness
// This file contains all email-related functions using PHPMailer

require_once __DIR__ . '/../PHPMailer-7.0.1/src/Exception.php';
require_once __DIR__ . '/../PHPMailer-7.0.1/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer-7.0.1/src/SMTP.php';
require_once __DIR__ . '/../config/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email using PHPMailer
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $altBody Plain text body (optional)
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmail($to, $subject, $body, $altBody = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        if (SMTP_DEBUG) {
            $mail->SMTPDebug = 2;
        }
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);
        
        // Send
        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Email sent successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"
        ];
    }
}

/**
 * Send welcome email to new member
 * @param string $memberEmail Member email
 * @param string $memberName Member name
 * @param string $memberCode Member code
 * @param string $password Initial password
 * @return array ['success' => bool, 'message' => string]
 */
function sendWelcomeEmail($memberEmail, $memberName, $memberCode, $password) {
    $subject = "Welcome to Empire Fitness!";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .credentials { background: white; padding: 20px; border-left: 4px solid #dc2626; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            .button { display: inline-block; background: #dc2626; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏋️ Welcome to Empire Fitness!</h1>
            </div>
            <div class='content'>
                <h2>Hello $memberName,</h2>
                <p>We're excited to have you join the Empire Fitness family! Your membership account has been created.</p>
                
                <div class='credentials'>
                    <h3>Your Login Credentials:</h3>
                    <p><strong>Member Code:</strong> <code>$memberCode</code></p>
                    <p><strong>Password:</strong> <code>$password</code></p>
                </div>
                
                <p><strong>⚠️ Important:</strong> Please change your password after your first login for security.</p>
                
                <a href='http://localhost/empirefitness/index.php' class='button'>Login to Your Account</a>
                
                <h3>What's Next?</h3>
                <ul>
                    <li>Visit our gym and check in at the reception</li>
                    <li>Book your first class</li>
                    <li>Meet our coaches</li>
                    <li>Start your fitness journey!</li>
                </ul>
                
                <p>If you have any questions, feel free to contact us.</p>
                
                <div class='footer'>
                    <p><strong>Empire Fitness</strong> | Your Partner in Fitness</p>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($memberEmail, $subject, $body);
}

/**
 * Send employee credentials email
 * @param string $email Employee email
 * @param string $name Employee name
 * @param string $employeeCode Employee code
 * @param string $password Initial password
 * @param string $role Employee role
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmployeeCredentialsEmail($email, $name, $employeeCode, $password, $role) {
    $subject = 'Welcome to Empire Fitness - Your Account Credentials';
    
    $htmlBody = "
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
            }
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background: #ffffff;
            }
            .header {
                background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
                color: white;
                padding: 40px 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
            }
            .content {
                padding: 40px 30px;
                background: #f9fafb;
            }
            .welcome-text {
                font-size: 18px;
                margin-bottom: 20px;
            }
            .credentials-box {
                background: white;
                border-left: 4px solid #dc2626;
                padding: 25px;
                margin: 25px 0;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .credentials-box h2 {
                margin: 0 0 20px 0;
                color: #dc2626;
                font-size: 20px;
            }
            .credential-item {
                margin: 15px 0;
                padding: 12px;
                background: #f3f4f6;
                border-radius: 6px;
            }
            .credential-label {
                font-weight: 600;
                color: #4b5563;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 5px;
            }
            .credential-value {
                font-size: 16px;
                font-family: 'Courier New', monospace;
                color: #1f2937;
                font-weight: 700;
                word-break: break-all;
            }
            .warning-box {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 15px;
                margin: 20px 0;
                border-radius: 6px;
            }
            .warning-box strong {
                color: #92400e;
            }
            .footer {
                background: #1f2937;
                color: #9ca3af;
                padding: 30px;
                text-align: center;
                font-size: 13px;
            }
            .footer a {
                color: #dc2626;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>Welcome to Empire Fitness</h1>
                <p style='margin: 10px 0 0 0; opacity: 0.9;'>Your Employee Account is Ready</p>
            </div>
            
            <div class='content'>
                <p class='welcome-text'>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                
                <p>Welcome to the Empire Fitness team! We're excited to have you join us as a <strong>" . htmlspecialchars($role) . "</strong>.</p>
                
                <p>Your employee account has been created and is ready to use. Below are your login credentials for accessing the Empire Fitness management system:</p>
                
                <div class='credentials-box'>
                    <h2>Your Login Credentials</h2>
                    
                    <div class='credential-item'>
                        <div class='credential-label'>Email Address</div>
                        <div class='credential-value'>" . htmlspecialchars($email) . "</div>
                    </div>
                    
                    <div class='credential-item'>
                        <div class='credential-label'>Employee Code</div>
                        <div class='credential-value'>" . htmlspecialchars($employeeCode) . "</div>
                    </div>
                    
                    <div class='credential-item'>
                        <div class='credential-label'>Temporary Password</div>
                        <div class='credential-value'>" . htmlspecialchars($password) . "</div>
                    </div>
                </div>
                
                <div class='warning-box'>
                    <strong>Important Security Notice:</strong> This is a temporary password. For your security, please change this password immediately after your first login.
                </div>
                
                <p><strong>How to Log In:</strong></p>
                <ol style='line-height: 2;'>
                    <li>Visit the Empire Fitness portal at: <a href='http://localhost/empirefitness' style='color: #dc2626;'>http://localhost/empirefitness</a></li>
                    <li>Enter your email address or employee code</li>
                    <li>Enter your temporary password</li>
                    <li>Follow the prompts to change your password</li>
                </ol>
                
                <p>If you have any questions or need assistance, please don't hesitate to contact your supervisor or the IT department.</p>
                
                <p style='margin-top: 30px;'>Best regards,<br>
                <strong>Empire Fitness Management Team</strong></p>
            </div>
            
            <div class='footer'>
                <p><strong>Empire Fitness</strong></p>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p style='margin-top: 15px;'>For support, contact: <a href='mailto:support@empirefitness.com'>support@empirefitness.com</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $htmlBody);
}

/**
 * Send class booking confirmation email
 * @param string $email Member email
 * @param string $memberName Member name
 * @param string $className Class name
 * @param string $classDate Class date (formatted)
 * @param string $classTime Class time
 * @param string $coachName Coach name
 * @return array ['success' => bool, 'message' => string]
 */
function sendClassBookingEmail($email, $memberName, $className, $classDate, $classTime, $coachName) {
    $subject = "Class Booking Confirmation - Empire Fitness";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .booking-details { background: white; padding: 20px; border-left: 4px solid #dc2626; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Booking Confirmed!</h1>
            </div>
            <div class='content'>
                <h2>Hello $memberName,</h2>
                <p>Your class booking has been confirmed!</p>
                
                <div class='booking-details'>
                    <h3>Booking Details:</h3>
                    <p><strong>Class:</strong> $className</p>
                    <p><strong>Date:</strong> $classDate</p>
                    <p><strong>Time:</strong> $classTime</p>
                    <p><strong>Coach:</strong> $coachName</p>
                </div>
                
                <p>We look forward to seeing you in class! If you need to cancel or reschedule, please log in to your account.</p>
                
                <div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'>
                    <p><strong>Empire Fitness</strong> | Your Partner in Fitness</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send payment receipt email
 * @param string $email Member/Guest email
 * @param string $name Member/Guest name
 * @param string $amount Payment amount
 * @param string $paymentMethod Payment method
 * @param string $description Payment description
 * @param string $transactionId Transaction ID (optional)
 * @return array ['success' => bool, 'message' => string]
 */
function sendPaymentReceiptEmail($email, $name, $amount, $paymentMethod, $description, $transactionId = '') {
    $subject = "Payment Receipt - Empire Fitness";
    
    $transactionInfo = $transactionId ? "<p><strong>Transaction ID:</strong> $transactionId</p>" : '';
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .receipt { background: white; padding: 20px; border: 1px solid #ddd; margin: 20px 0; }
            .amount { font-size: 28px; font-weight: bold; color: #27ae60; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>💰 Payment Receipt</h1>
            </div>
            <div class='content'>
                <h2>Hello $name,</h2>
                <p>Thank you for your payment!</p>
                
                <div class='receipt'>
                    <p><strong>Description:</strong> $description</p>
                    <p><strong>Payment Method:</strong> $paymentMethod</p>
                    $transactionInfo
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 15px 0;'>
                    <p style='text-align: right;'><strong>Amount Paid:</strong> <span class='amount'>₱" . number_format($amount, 2) . "</span></p>
                </div>
                
                <p>Keep this receipt for your records.</p>
                
                <div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'>
                    <p><strong>Empire Fitness</strong> | Receipt #" . date('YmdHis') . "</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send member welcome email with credentials
 * @param string $email Member email
 * @param string $firstName Member first name
 * @param string $lastName Member last name
 * @param string $username Member username
 * @param string $tempPassword Member temporary password
 * @return array ['success' => bool, 'message' => string]
 */
function sendMemberWelcomeEmail($email, $firstName, $lastName, $username, $tempPassword) {
    $name = "$firstName $lastName";
    $subject = "Welcome to Empire Fitness - Your Account Credentials";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .credentials { background: white; padding: 20px; border: 2px solid #dc2626; margin: 20px 0; border-radius: 8px; }
            .credential-item { padding: 12px 0; border-bottom: 1px solid #eee; }
            .credential-item:last-child { border-bottom: none; }
            .label { font-weight: bold; color: #666; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
            .value { font-size: 18px; color: #dc2626; font-family: 'Courier New', monospace; margin-top: 5px; }
            .note { background: #fffbea; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px; }
            .button { display: inline-block; background: #dc2626; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Welcome to Empire Fitness</h1>
            </div>
            <div class='content'>
                <h2>Hello $firstName,</h2>
                <p>Your membership account has been successfully created! We're excited to have you join our fitness community.</p>
                
                <h3 style='color: #dc2626; margin-top: 25px;'>Your Login Credentials</h3>
                
                <div class='credentials'>
                    <div class='credential-item'>
                        <div class='label'>Username</div>
                        <div class='value'>$username</div>
                    </div>
                    <div class='credential-item'>
                        <div class='label'>Temporary Password</div>
                        <div class='value'>$tempPassword</div>
                    </div>
                </div>
                
                <div class='note'>
                    <strong>⚠️ Important:</strong> This is a temporary password for security purposes. Please log in and change your password immediately after your first login.
                </div>
                
                <h3 style='color: #333; margin-top: 25px;'>Next Steps</h3>
                <ol>
                    <li>Visit our website and log in with your credentials above</li>
                    <li>Update your password in your profile settings</li>
                    <li>Complete your fitness profile and goals</li>
                    <li>Connect with our coaches and start your fitness journey!</li>
                </ol>
                
                <div style='text-align: center;'>
                    <a href='" . (defined('SITE_URL') ? SITE_URL : 'http://localhost/empirefitness') . "' class='button'>Go to Empire Fitness</a>
                </div>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;'>
                    <p>If you have any questions or need assistance, please contact our team at support@empirefitness.com or call us during business hours.</p>
                    <p style='margin-top: 10px;'><strong>Empire Fitness</strong> | Your Fitness Journey Starts Here</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send email verification email to new member
 * @param string $memberEmail Member email
 * @param string $memberName Member name
 * @param string $verificationToken Unique verification token
 * @param string $registrationId Registration ID for reference
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmailVerificationEmail($memberEmail, $memberName, $verificationToken, $registrationId) {
    $subject = "Verify Your Email - Empire Fitness";
    
    // Create verification link (adjust the domain as needed)
    $verificationLink = "http://localhost/empirefitness/verify-email.php?token=" . urlencode($verificationToken);
    
    $htmlBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .verification-box { background: white; padding: 25px; border-left: 4px solid #dc2626; margin: 20px 0; border-radius: 8px; }
            .verification-button { display: inline-block; background: #dc2626; color: white; padding: 14px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            .security-note { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 Email Verification Required</h1>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($memberName) . ",</h2>
                <p>Thank you for registering with Empire Fitness! Your membership application has been approved.</p>
                
                <div class='verification-box'>
                    <h3>Verify Your Email Address</h3>
                    <p>To secure your account and receive your login credentials, please verify your email address by clicking the button below:</p>
                    
                    <center>
                        <a href='" . htmlspecialchars($verificationLink) . "' class='verification-button'>
                            ✓ Verify Email Address
                        </a>
                    </center>
                    
                    <p style='font-size: 12px; color: #666; margin-top: 20px;'>
                        If the button above doesn't work, you can also copy and paste this link in your browser:<br>
                        <code style='background: #f0f0f0; padding: 8px; display: block; word-break: break-all; margin: 10px 0;'>" . htmlspecialchars($verificationLink) . "</code>
                    </p>
                </div>
                
                <div class='security-note'>
                    <strong>🔒 Security Notice:</strong>
                    <p style='margin: 10px 0 0 0;'>This link will expire in 24 hours for security reasons. If you don't verify within this time, you'll need to request a new verification email.</p>
                </div>
                
                <h3>What's Next?</h3>
                <ul>
                    <li>Click the verification button above</li>
                    <li>You'll receive your login credentials via email</li>
                    <li>Log in and change your password</li>
                    <li>Start your fitness journey!</li>
                </ul>
                
                <p>If you didn't register for Empire Fitness, please ignore this email.</p>
                
                <div class='footer'>
                    <p><strong>Empire Fitness</strong> | Your Partner in Fitness</p>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $altBody = "
Email Verification Required

Hello " . $memberName . ",

Thank you for registering with Empire Fitness! Your membership application has been approved.

To secure your account and receive your login credentials, please verify your email address by visiting the link below:

" . $verificationLink . "

This link will expire in 24 hours for security reasons.

What's Next?
1. Click the verification link above
2. You'll receive your login credentials via email
3. Log in and change your password
4. Start your fitness journey!

If you didn't register for Empire Fitness, please ignore this email.

---
Empire Fitness | Your Partner in Fitness
This is an automated message, please do not reply.
    ";
    
    return sendEmail($memberEmail, $subject, $htmlBody, $altBody);
}

/**
 * Send credentials email after verification
 * @param string $memberEmail Member email
 * @param string $memberName Member name
 * @param string $memberCode Member code/Username
 * @param string $password Temporary password
 * @return array ['success' => bool, 'message' => string]
 */
function sendCredentialsEmailAfterVerification($memberEmail, $memberName, $memberCode, $password) {
    $subject = "Your Empire Fitness Login Credentials";
    
    $htmlBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #28a745 0%, #1e8449 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .credentials { background: white; padding: 20px; border-left: 4px solid #28a745; margin: 20px 0; border-radius: 8px; }
            .credentials h3 { color: #28a745; margin-top: 0; }
            .credential-item { margin: 15px 0; }
            .credential-label { font-weight: 600; color: #666; font-size: 12px; text-transform: uppercase; }
            .credential-value { font-size: 16px; font-family: 'Courier New', monospace; background: #f0f0f0; padding: 10px; border-radius: 4px; }
            .login-button { display: inline-block; background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Email Verified!</h1>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($memberName) . ",</h2>
                <p>Congratulations! Your email has been verified. Your account is now ready to use.</p>
                
                <div class='credentials'>
                    <h3>Your Login Credentials:</h3>
                    
                    <div class='credential-item'>
                        <div class='credential-label'>Member Code / Username:</div>
                        <div class='credential-value'>" . htmlspecialchars($memberCode) . "</div>
                    </div>
                    
                    <div class='credential-item'>
                        <div class='credential-label'>Temporary Password:</div>
                        <div class='credential-value'>" . htmlspecialchars($password) . "</div>
                    </div>
                    
                    <p style='color: #dc2626; font-weight: bold; margin-top: 20px;'>
                        ⚠️ Important: Please change your password after your first login for security.
                    </p>
                </div>
                
                <center>
                    <a href='http://localhost/empirefitness/index.php' class='login-button'>
                        Login to Your Account →
                    </a>
                </center>
                
                <h3>What's Next?</h3>
                <ul>
                    <li>Visit our gym and check in at the reception</li>
                    <li>Book your first class</li>
                    <li>Meet our coaches</li>
                    <li>Start your fitness journey!</li>
                </ul>
                
                <p>If you have any questions, feel free to contact us.</p>
                
                <div class='footer'>
                    <p><strong>Empire Fitness</strong> | Your Partner in Fitness</p>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $altBody = "
Your Empire Fitness Login Credentials

Hello " . $memberName . ",

Congratulations! Your email has been verified. Your account is now ready to use.

Your Login Credentials:
Username: " . $memberCode . "
Temporary Password: " . $password . "

⚠️ Important: Please change your password after your first login for security.

Visit http://localhost/empirefitness/index.php to login.

What's Next?
- Visit our gym and check in at the reception
- Book your first class
- Meet our coaches
- Start your fitness journey!

If you have any questions, feel free to contact us.

---
Empire Fitness | Your Partner in Fitness
This is an automated message, please do not reply.
    ";
    
    return sendEmail($memberEmail, $subject, $htmlBody, $altBody);
}

/**
 * Send approval email to member with verification link
 * @param string $memberEmail Member email
 * @param string $memberName Member name
 * @param string $verificationUrl Verification URL with registration ID
 * @param int $registrationId Registration ID
 * @return array ['success' => bool, 'message' => string]
 */
function sendApprovedApplicationEmail($memberEmail, $memberName, $verificationUrl, $registrationId) {
    $subject = "Your Empire Fitness Application has been Approved!";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #667eea; color: white; padding: 14px 40px; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: bold; }
            .button:hover { background: #5568d3; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 4px; color: #155724; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✓ Application Approved!</h1>
            </div>
            <div class='content'>
                <h2>Hello $memberName,</h2>
                
                <div class='success-box'>
                    <h3>Great news!</h3>
                    <p>Your membership application has been approved by our management team. You're one step away from completing your registration.</p>
                </div>
                
                <p>To complete your account setup and get started with your Empire Fitness membership, please click the button below:</p>
                
                <center>
                    <a href='$verificationUrl' class='button'>
                        Complete Your Registration →
                    </a>
                </center>
                
                <p><strong>What happens next?</strong></p>
                <ol>
                    <li>Click the button above to verify your email</li>
                    <li>Create your member account with a secure password</li>
                    <li>Receive your QR code for gym access (if applicable)</li>
                    <li>Start your fitness journey at Empire Fitness!</li>
                </ol>
                
                <p><strong>Need help?</strong> If you have any questions, feel free to contact our reception team.</p>
                
                <div class='footer'>
                    <p><strong>Empire Fitness</strong> | Your Partner in Fitness</p>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $altBody = "
Your Empire Fitness Application - Complete Registration

Hello " . $memberName . ",

Great news! Your membership application has been approved by our management team.

To complete your account setup, please visit this link:
" . $verificationUrl . "

What happens next?
1. Verify your email address
2. Create your member account with a secure password
3. Receive your QR code for gym access (if applicable)
4. Start your fitness journey at Empire Fitness!

If you have any questions, feel free to contact our reception team.

---
Empire Fitness | Your Partner in Fitness
This is an automated message, please do not reply.
    ";
    
    return sendEmail($memberEmail, $subject, $body, $altBody);
}

?>
