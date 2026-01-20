<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

// Fetch current employee data
$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$stmt->execute([$_SESSION['employee_id']]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header("Location: ../logout.php");
    exit;
}

$employeeName = $_SESSION['employee_name'] ?? 'Manager';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Empire Fitness</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/manager-components.css">
    <link rel="stylesheet" href="css/employee-management.css">
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
                <h1>My Profile</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / My Profile
                </p>
            </div>
        </div>

        <!-- Alert Box -->
        <div id="alertBox" class="alert-box"></div>

        <!-- Profile Header -->
        <div class="profile-header" style="background: linear-gradient(135deg, #d41c1c 0%, #8b0000 100%); padding: 40px; border-radius: 10px; margin-bottom: 30px; color: white; margin-left: 20px; margin-right: 20px; margin-top: 20px;">
            <div style="display: flex; align-items: center; gap: 30px;">
                <div style="width: 120px; height: 120px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; font-size: 48px; font-weight: bold; color: #d41c1c; border: 5px solid rgba(255,255,255,0.3);">
                    <?php echo $employeeInitial; ?>
                </div>
                <div style="flex: 1;">
                    <h2 style="margin: 0; font-size: 32px; font-weight: bold;">
                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . ($employee['middle_name'] ? $employee['middle_name'] . ' ' : '') . $employee['last_name']); ?>
                    </h2>
                    <p style="margin: 10px 0 5px 0; font-size: 18px; opacity: 0.9;">
                        <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($employee['position']); ?> • 
                        <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($employee['role']); ?>
                    </p>
                    <p style="margin: 0; font-size: 14px; opacity: 0.8;">
                        <i class="fas fa-id-badge"></i> Employee Code: <?php echo htmlspecialchars($employee['employee_code'] ?? 'N/A'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Profile Information Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px; margin: 0 20px;">
            <!-- Personal Information Card -->
            <div class="info-card" style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="color: #d41c1c; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-user"></i> Personal Information
                </h3>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Full Name</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . ($employee['middle_name'] ? $employee['middle_name'] . ' ' : '') . $employee['last_name']); ?>
                        </p>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Email Address</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <i class="fas fa-envelope" style="color: #d41c1c;"></i> <?php echo htmlspecialchars($employee['email']); ?>
                        </p>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Contact Number</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <i class="fas fa-phone" style="color: #d41c1c;"></i> <?php echo htmlspecialchars($employee['phone'] ?? 'Not provided'); ?>
                        </p>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Home Address</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <i class="fas fa-map-marker-alt" style="color: #d41c1c;"></i> <?php echo htmlspecialchars($employee['address'] ?: 'Not provided'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Employment Details Card -->
            <div class="info-card" style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="color: #d41c1c; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-briefcase"></i> Employment Details
                </h3>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Position</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <span class="position-badge position-<?= strtolower($employee['position']) ?>">
                                <?php echo htmlspecialchars($employee['position']); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">System Role</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <span class="role-badge role-<?= strtolower(str_replace(' ', '-', $employee['role'])) ?>">
                                <?php echo htmlspecialchars($employee['role']); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Account Status</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <span class="status-badge status-<?= strtolower($employee['status']) ?>">
                                <i class="fas fa-circle"></i> <?php echo htmlspecialchars($employee['status']); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Hire Date</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <i class="fas fa-calendar" style="color: #d41c1c;"></i> 
                            <?php echo $employee['hire_date'] ? date('F d, Y', strtotime($employee['hire_date'])) : 'Not specified'; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Account Security Card -->
            <div class="info-card" style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="color: #d41c1c; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-lock"></i> Account Security
                </h3>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Last Login</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <i class="fas fa-clock" style="color: #d41c1c;"></i> 
                            <?php echo $employee['last_login'] ? date('F d, Y h:i A', strtotime($employee['last_login'])) : 'Never'; ?>
                        </p>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Password</label>
                        <p style="margin: 5px 0 0 0;">
                            <a href="settings.php" class="btn-primary" style="padding: 8px 16px; font-size: 14px; text-decoration: none; display: inline-block;">
                                <i class="fas fa-key"></i> Change Password
                            </a>
                        </p>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Employee Code</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <i class="fas fa-id-badge" style="color: #d41c1c;"></i> <?php echo htmlspecialchars($employee['employee_code']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/sidebar.js"></script>
    <script src="js/manager-dashboard.js"></script>
    <script src="js/profile.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>
