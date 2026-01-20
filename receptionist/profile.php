<?php
session_start();

// Check if user is logged in and has receptionist role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Receptionist') {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

$employeeName = $_SESSION['employee_name'] ?? 'Receptionist';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
$employeeId = $_SESSION['employee_id'];

// Fetch employee profile information
$profileQuery = "SELECT * FROM employees WHERE employee_id = ?";
$stmt = $conn->prepare($profileQuery);
$stmt->execute([$employeeId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    try {
        $updateQuery = "UPDATE employees SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE employee_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->execute([$firstName, $lastName, $email, $phone, $employeeId]);
        
        $_SESSION['employee_name'] = "$firstName $lastName";
        $profile['first_name'] = $firstName;
        $profile['last_name'] = $lastName;
        $profile['email'] = $email;
        $profile['phone'] = $phone;
        
        $message = 'Profile updated successfully!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Error updating profile: ' . $e->getMessage();
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Empire Fitness</title>
    <link rel="stylesheet" href="css/receptionist-dashboard.css">
    <link rel="stylesheet" href="css/profile.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="../css/realtime-notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-user-id="<?php echo htmlspecialchars($_SESSION['employee_id']); ?>"
      data-user-role="<?php echo htmlspecialchars($_SESSION['employee_role']); ?>"
      data-user-name="<?php echo htmlspecialchars($_SESSION['employee_name']); ?>">
    <!-- Notifications Container -->
    <div id="notifications"></div>
    <!-- Sidebar Toggle Button -->
    <button id="sidebar-toggle" class="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-circle">
                    <i class="fas fa-dumbbell"></i>
                </div>
                <div class="logo-text">
                    <h2>EMPIRE FITNESS</h2>
                    <p>Reception Desk</p>
                </div>
            </div>
        </div>

        <div class="profile-section" onclick="window.location.href='profile.php'" style="cursor: pointer;">
            <div class="profile-avatar"><?php echo $employeeInitial; ?></div>
            <div class="profile-info">
                <div class="profile-name"><?php echo htmlspecialchars($employeeName); ?></div>
                <div class="profile-role"><?php echo htmlspecialchars($_SESSION['employee_role']); ?></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="receptionistDashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'receptionistDashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-divider">OPERATIONS</div>
            
            <a href="pos.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'active' : ''; ?>">
                <i class="fas fa-cash-register"></i>
                <span>Point of Sale</span>
            </a>
            
            <div class="nav-divider">MEMBERS</div>
            
            <a href="members_list.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'members_list.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Members</span>
            </a>
            
            <div class="nav-divider">PAYMENTS</div>
            
            <a href="payment_history.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'payment_history.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Payment History</span>
            </a>
            
            <div class="nav-divider">SCHEDULE & REPORTS</div>
            
            <a href="schedule_classes.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'schedule_classes.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Class Schedule</span>
            </a>
            <a href="daily_report.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'daily_report.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Daily Report</span>
            </a>
            
            <div class="nav-divider">ACCOUNT</div>
            
            <a href="profile.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <h1>My Profile</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Account / My Profile
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

            <div class="profile-container">
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar-large"><?php echo $employeeInitial; ?></div>
                        <div class="profile-basic-info">
                            <h2><?php echo htmlspecialchars($employeeName); ?></h2>
                            <p class="profile-role-badge"><?php echo htmlspecialchars($_SESSION['employee_role']); ?></p>
                            <p class="profile-id">Employee Code: <?php echo htmlspecialchars($profile['employee_code'] ?? 'N/A'); ?></p>
                        </div>
                    </div>

                    <form method="POST" class="profile-form">
                        <div class="form-section">
                            <h3>Personal Information</h3>
                            
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="role">Role</label>
                                <input type="text" id="role" name="role" value="<?php echo htmlspecialchars($_SESSION['employee_role']); ?>" disabled>
                                <small class="form-text">Role cannot be changed. Contact administrator if needed.</small>
                            </div>

                            <div class="form-group">
                                <label for="join_date">Join Date</label>
                                <input type="text" id="join_date" name="join_date" value="<?php echo htmlspecialchars($profile['hire_date'] ?? 'N/A'); ?>" disabled>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="receptionistDashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <div class="toast-content">
            <i class="toast-icon fas fa-check-circle"></i>
            <span class="toast-message" id="toast-message">Action completed</span>
        </div>
    </div>

    <script src="js/receptionist-dashboard.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>