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

$employeeName = $_SESSION['employee_name'] ?? 'Admin';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Empire Fitness</title>
    <link rel="stylesheet" href="css/admin-dashboard.css">
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
                    <p>Admin Portal</p>
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
            <a href="adminDashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'adminDashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>Dashboard</span>
    </a>
    <a href="employee_management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'employee_management.php' ? 'active' : ''; ?>">
        <i class="fas fa-users-cog"></i>
        <span>Employee Management</span>
    </a>
    
    <div class="nav-divider">RATES & PRICING</div>
    
    <a href="membership_plans.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'membership_plans.php' ? 'active' : ''; ?>">
        <i class="fas fa-crown"></i>
        <span>Membership Plans</span>
    </a>
    <a href="rates.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'rates.php' ? 'active' : ''; ?>">
        <i class="fas fa-money-bill-wave"></i>
        <span>Rates & Fees</span>
    </a>
    
    <div class="nav-divider">REPORTS</div>
    
    <a href="sales.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i>
        <span>Sales Reports</span>
    </a>
    <a href="total_members.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'total_members.php' ? 'active' : ''; ?>">
        <i class="fas fa-user-friends"></i>
        <span>Member Statistics</span>
    </a>
    <a href="gym_activity.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'gym_activity.php' ? 'active' : ''; ?>">
        <i class="fas fa-door-open"></i>
        <span>Recent User Activity</span>
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
                    <i class="fas fa-home"></i> Home / My Profile
                </p>
            </div>
        </div>

        <!-- Alert Box -->
        <div id="alertBox" class="alert-box"></div>

        <!-- Profile Header -->
        <div class="profile-header" style="background: linear-gradient(135deg, #d41c1c 0%, #8b0000 100%); padding: 40px; border-radius: 10px; margin-bottom: 30px; color: white;">
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
                <div>
                    <button onclick="openEditProfileModal()" class="btn-primary" style="padding: 12px 24px;">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                </div>
            </div>
        </div>

        <!-- Profile Information Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px;">
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
                            <i class="fas fa-phone" style="color: #d41c1c;"></i> <?php echo htmlspecialchars($employee['phone']); ?>
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

            <!-- Emergency Contact Card -->
            <div class="info-card" style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="color: #d41c1c; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-phone-alt"></i> Emergency Contact
                </h3>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Contact Name</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <?php echo htmlspecialchars($employee['emergency_contact'] ?: 'Not provided'); ?>
                        </p>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Contact Number</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <i class="fas fa-phone" style="color: #d41c1c;"></i> 
                            <?php echo htmlspecialchars($employee['emergency_phone'] ?: 'Not provided'); ?>
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
                            <button onclick="openChangePasswordModal()" class="btn-primary" style="padding: 8px 16px; font-size: 14px;">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </p>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #666; font-size: 13px;">Account Created</label>
                        <p style="margin: 5px 0 0 0; font-size: 15px;">
                            <?php echo date('F d, Y', strtotime($employee['created_at'])); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Profile</h3>
                <button class="modal-close" onclick="closeEditProfileModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-section">
                        <h4><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_first_name">First Name *</label>
                                <input type="text" id="edit_first_name" name="first_name" value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_middle_name">Middle Name</label>
                                <input type="text" id="edit_middle_name" name="middle_name" value="<?php echo htmlspecialchars($employee['middle_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="edit_last_name">Last Name *</label>
                                <input type="text" id="edit_last_name" name="last_name" value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_phone">Contact Number *</label>
                                <input type="text" id="edit_phone" name="phone" maxlength="11" pattern="[0-9]{11}" value="<?php echo htmlspecialchars($employee['phone']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_email">Email Address *</label>
                                <input type="email" id="edit_email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_address">Home Address</label>
                            <textarea id="edit_address" name="address" rows="2"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h4><i class="fas fa-phone-alt"></i> Emergency Contact</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_emergency_contact">Contact Name</label>
                                <input type="text" id="edit_emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($employee['emergency_contact'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="edit_emergency_phone">Contact Number</label>
                                <input type="text" id="edit_emergency_phone" name="emergency_phone" maxlength="11" pattern="[0-9]{11}" value="<?php echo htmlspecialchars($employee['emergency_phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" onclick="closeEditProfileModal()" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Change Password</h3>
                <button class="modal-close" onclick="closeChangePasswordModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password *</label>
                        <input type="password" id="new_password" name="new_password" minlength="8" required>
                        <small style="color: #666; font-size: 12px;">Minimum 8 characters</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <div class="modal-footer">
                        <button type="button" onclick="closeChangePasswordModal()" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/admin-dashboard.js"></script>
    <script src="js/profile.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>