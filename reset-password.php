<?php
session_start();
require_once __DIR__ . '/config/connection.php';

$conn = getDBConnection();
$message = '';
$messageType = '';
$validToken = false;
$email = '';
$tokenExpired = false;

// Check if token and email are provided
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($token) || empty($email)) {
    $message = "Invalid reset link. Please request a new password reset.";
    $messageType = 'error';
} else {
    try {
        // Verify the token - use backticks if needed
        $stmt = $conn->prepare("SELECT employee_id, first_name, reset_token, reset_token_expires FROM employees WHERE email = :email AND reset_token = :token");
        $stmt->execute(['email' => $email, 'token' => $token]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            $message = "Invalid or expired reset link. Please request a new password reset.";
            $messageType = 'error';
        } elseif (strtotime($employee['reset_token_expires']) < time()) {
            $message = "This password reset link has expired. Please request a new password reset.";
            $messageType = 'error';
            $tokenExpired = true;
        } else {
            $validToken = true;
        }
    } catch (Exception $e) {
        $message = "An error occurred. Please try again later.";
        $messageType = 'error';
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || empty($confirmPassword)) {
        $message = "Please fill in all password fields.";
        $messageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = "Password must be at least 8 characters long.";
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = 'error';
    } else {
        try {
            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            // Update the password and clear the reset token
            $stmt = $conn->prepare("UPDATE employees SET password_hash = :password, reset_token = NULL, reset_token_expires = NULL WHERE employee_id = :id");
            $updateResult = $stmt->execute([
                'password' => $hashedPassword,
                'id' => $employee['employee_id']
            ]);

            if ($updateResult) {
                $message = "Password reset successfully! Redirecting to login page...";
                $messageType = 'success';
                header("refresh:3;url=index.php");
            } else {
                $message = "Failed to update password. Please try again.";
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = "An error occurred while resetting your password. Please try again.";
            $messageType = 'error';
            error_log("Password update error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Empire Fitness</title>
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
        <form class="login-form" method="POST" action="">
            <h2 class="login-title">🔑 Reset Your Password</h2>
            <p class="subtitle">Create a new password<br>for your account</p>

            <?php if (!empty($message)): ?>
                <div class="<?php echo $messageType === 'success' ? 'error-message' : 'error-message'; ?>" id="login-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($validToken): ?>
                <div class="login-info" style="background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; margin-bottom: 20px;">
                    <p><strong>⏰ Password Requirements:</strong><br>• Minimum 8 characters<br>• Mix of uppercase and lowercase letters<br>• Recommended: Include numbers and special characters</p>
                </div>

                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-input" id="password" name="password" placeholder="Enter new password" required minlength="8">
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" class="form-input" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required minlength="8">
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" class="checkbox" id="showPassword">
                    <label for="showPassword" class="checkbox-label">Show password</label>
                </div>

                <button type="submit" class="login-btn">
                    <span>RESET PASSWORD</span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </button>
            <?php elseif ($tokenExpired): ?>
                <div class="login-info">
                    <p style="color: #666; margin-bottom: 15px;">Your reset link has expired.</p>
                    <a href="forgot-password.php" class="login-btn" style="text-decoration: none; color: white; margin-bottom: 0;">
                        <span>REQUEST NEW RESET</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="login-info">
                    <p style="color: #666; margin-bottom: 15px;">Invalid reset link.</p>
                    <a href="index.php" class="login-btn" style="text-decoration: none; color: white; margin-bottom: 0;">
                        <span>BACK TO LOGIN</span>
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
    const showPasswordCheckbox = document.getElementById('showPassword');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    if (showPasswordCheckbox) {
        showPasswordCheckbox.addEventListener('change', function() {
            const type = this.checked ? 'text' : 'password';
            passwordInput.type = type;
            confirmPasswordInput.type = type;
        });
    }
</script>
</body>
</html>
