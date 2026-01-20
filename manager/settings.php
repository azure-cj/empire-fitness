<?php
session_start();

// Check if user is logged in and has manager role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Manager') {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

$employeeName = $_SESSION['employee_name'] ?? 'Manager';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
$employeeId = $_SESSION['employee_id'];

$message = '';
$messageType = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match!';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $message = 'Password must be at least 6 characters long!';
            $messageType = 'error';
        } else {
            try {
                // Fetch current password hash
                $query = "SELECT password_hash FROM employees WHERE employee_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$employeeId]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($employee && password_verify($currentPassword, $employee['password_hash'])) {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateQuery = "UPDATE employees SET password_hash = ? WHERE employee_id = ?";
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->execute([$newHash, $employeeId]);
                    
                    $message = 'Password changed successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Current password is incorrect!';
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = 'Error changing password: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}
?>
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Manager</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/manager-components.css">
    <link rel="stylesheet" href="css/settings.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="../css/realtime-notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-user-id="<?php echo htmlspecialchars($_SESSION['employee_id']); ?>"
      data-user-role="<?php echo htmlspecialchars($_SESSION['employee_role']); ?>"
      data-user-name="<?php echo htmlspecialchars($_SESSION['employee_name']); ?>">
    <!-- Notifications Container -->
    <div id="notifications"></div>
    <?php include 'includes/sidebar_navigation.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <h1>Settings</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Account / Settings
                </p>
            </div>
            <div class="topbar-right">
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                </button>
                <div class="user-profile">
                    <div class="user-avatar"><?php echo $employeeInitial; ?></div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($employeeName); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars($_SESSION['employee_role']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-wrapper">
            <!-- Message Alert -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>" id="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
                <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
            </div>
            <?php endif; ?>

            <div class="settings-container">
                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <button class="tab-btn active" onclick="openTab('account')">
                        <i class="fas fa-lock"></i> Account Security
                    </button>
                    <button class="tab-btn" onclick="openTab('preferences')">
                        <i class="fas fa-sliders-h"></i> Preferences
                    </button>
                    <button class="tab-btn" onclick="openTab('notifications')">
                        <i class="fas fa-bell"></i> Notifications
                    </button>
                </div>

                <!-- Account Security Tab -->
                <div id="account" class="settings-tab active">
                    <div class="settings-card">
                        <div class="card-header">
                            <h3>Change Password</h3>
                            <p>Update your password to keep your account secure</p>
                        </div>
                        <form method="POST" class="settings-form">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label for="current_password">Current Password *</label>
                                <div class="password-input-group">
                                    <input type="password" id="current_password" name="current_password" required>
                                    <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="new_password">New Password *</label>
                                <div class="password-input-group">
                                    <input type="password" id="new_password" name="new_password" required>
                                    <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="form-text">Minimum 6 characters</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <div class="password-input-group">
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Password
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="settings-card">
                        <div class="card-header">
                            <h3>Active Sessions</h3>
                            <p>Manage your active login sessions</p>
                        </div>
                        <div class="sessions-info">
                            <div class="session-item">
                                <div class="session-details">
                                    <h4>Current Session</h4>
                                    <p>
                                        <i class="fas fa-desktop"></i> 
                                        <span id="device-info">Desktop Browser</span>
                                    </p>
                                </div>
                                <div class="session-badge">Active</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preferences Tab -->
                <div id="preferences" class="settings-tab">
                    <div class="settings-card">
                        <div class="card-header">
                            <h3>Display Preferences</h3>
                            <p>Customize your dashboard appearance</p>
                        </div>
                        <form class="settings-form">
                            <div class="form-group">
                                <label for="theme">Theme</label>
                                <select id="theme" name="theme">
                                    <option value="light">Light Mode</option>
                                    <option value="dark">Dark Mode</option>
                                    <option value="auto">Auto (System Default)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="language">Language</label>
                                <select id="language" name="language">
                                    <option value="en">English</option>
                                </select>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Notifications Tab -->
                <div id="notifications" class="settings-tab">
                    <div class="settings-card">
                        <div class="card-header">
                            <h3>Notification Preferences</h3>
                            <p>Choose how you want to be notified</p>
                        </div>
                        <form class="settings-form">
                            <div class="notification-option">
                                <div class="option-info">
                                    <h4>Email Notifications</h4>
                                    <p>Receive updates via email</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="notification-option">
                                <div class="option-info">
                                    <h4>System Notifications</h4>
                                    <p>Receive in-app notifications</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/sidebar.js"></script>
    <script src="js/manager-dashboard.js"></script>
    <script src="js/settings.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>
