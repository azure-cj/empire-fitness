<?php
// TEMPORARY: Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/config/connection.php';
$conn = getDBConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Query employees table using email
        $stmt = $conn->prepare("SELECT * FROM employees WHERE email = :email AND status = 'Active'");
        $stmt->execute(['email' => $email]);
        $employee = $stmt->fetch();

        if ($employee && password_verify($password, $employee['password_hash'])) {
            // Set session variables
            $_SESSION['employee_id'] = $employee['employee_id'];
            $_SESSION['employee_email'] = $employee['email'];
            $_SESSION['employee_name'] = $employee['first_name'] . ' ' . $employee['last_name'];
            $_SESSION['employee_role'] = $employee['role'];
            $_SESSION['employee_position'] = $employee['position'];

            // Update last login timestamp
            $conn->prepare("UPDATE employees SET last_login = NOW() WHERE employee_id = :id")
                 ->execute(['id' => $employee['employee_id']]);

            // Redirect based on role
            switch ($employee['role']) {
                case 'Super Admin':
                case 'Admin':
                    header("Location: admin/adminDashboard.php");
                    exit;
                case 'Manager':
                    header("Location: manager/managerDashboard.php");
                    exit;
                case 'Receptionist':
                    header("Location: receptionist/receptionistDashboard.php");
                    exit;
                default:
                    $error = "Your role does not have dashboard access.";
                    session_destroy();
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Empire Fitness - Staff Login</title>
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
        <form class="login-form" id="loginForm" method="POST" action="index.php">
            <h2 class="login-title">STAFF LOGIN</h2>
            <p class="subtitle">Access your dashboard<br>Enter your credentials to continue</p>
            
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-input" name="email" placeholder="your.email@empirefitness.com" required autofocus>
            </div>
            
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" class="form-input" name="password" id="password" placeholder="Enter your password" required>
            </div>
            
            <div class="form-actions">
                <div class="checkbox-group">
                    <input type="checkbox" class="checkbox" id="showPassword">
                    <label for="showPassword" class="checkbox-label">Show password</label>
                </div>
                <a href="forgot-password.php" class="forgot-password-link">Forgot Password?</a>
            </div>
            
            <button type="submit" class="login-btn">
                <span>LOGIN</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M13.8 12H3"/>
                </svg>
            </button>
            
            <?php if (!empty($error)): ?>
                <div class="error-message" id="login-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="login-info">
                <p>🔒 Secure login for authorized staff only</p>
            </div>
        </form>
    </div>
</div>

<script src="js/index-script.js"></script>
</body>
</html>