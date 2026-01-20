<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Super Admin', 'Admin'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

// Fetch statistics from correct database tables
try {
    // Total Active Members
    $stmt = $conn->prepare("SELECT COUNT(*) FROM clients WHERE client_type = 'Member' AND status = 'Active'");
    $stmt->execute();
    $totalMembers = $stmt->fetchColumn();
    
    // Monthly Revenue (current month)
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(al.amount), 0) 
        FROM attendance_log al 
        WHERE al.status = 'Completed' 
        AND MONTH(al.created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(al.created_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute();
    $monthlySales = $stmt->fetchColumn();
    
    // Walk-in Clients Today
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM attendance_log 
        WHERE attendance_type = 'Walk-in' 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $walkInsToday = $stmt->fetchColumn();
    
    // Active Employees
    $stmt = $conn->prepare("SELECT COUNT(*) FROM employees WHERE status = 'Active'");
    $stmt->execute();
    $activeEmployees = $stmt->fetchColumn();
    
} catch (Exception $e) {
    // Set default values if queries fail
    error_log("Dashboard query error: " . $e->getMessage());
    $totalMembers = 0;
    $monthlySales = 0;
    $walkInsToday = 0;
    $activeEmployees = 0;
}

$employeeName = $_SESSION['employee_name'] ?? 'Admin';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Empire Fitness</title>
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
                <h1>Dashboard Overview</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Dashboard
                </p>
            </div>
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" placeholder="Search...">
                </div>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
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

        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $employeeName)[0]); ?>! 👋</h2>
                <p>Here's what's happening with your gym today.</p>
            </div>
            <div class="quick-date">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo date('l, F d, Y'); ?></span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Members</h3>
                    <p class="stat-number"><?php echo number_format($totalMembers); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Active members
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>Monthly Revenue</h3>
                    <p class="stat-number">₱<?php echo number_format($monthlySales, 2); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> This month
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-walking"></i>
                </div>
                <div class="stat-content">
                    <h3>Walk-ins Today</h3>
                    <p class="stat-number"><?php echo number_format($walkInsToday); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-calendar-day"></i> <?php echo date('M d, Y'); ?>
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-content">
                    <h3>Active Staff</h3>
                    <p class="stat-number"><?php echo number_format($activeEmployees); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-check-circle"></i> Employees
                    </span>
                </div>
            </div>
        </div>

        <!-- Module Grid -->
        <div class="modules-section">
            <h2 class="section-title">Quick Access Modules</h2>
            <div class="module-grid">
                <div class="module-card" onclick="window.location.href='employee_management.php'">
                    <div class="module-icon red">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="module-content">
                        <h3>Employee Management</h3>
                        <p>Manage staff accounts, roles, and permissions</p>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>

                

                <div class="module-card" onclick="window.location.href='membership_plans.php'">
                    <div class="module-icon purple">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="module-content">
                        <h3>Membership Plans</h3>
                        <p>Configure pricing, plans, and promotions</p>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>

                <div class="module-card" onclick="window.location.href='sales.php'">
                    <div class="module-icon green">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="module-content">
                        <h3>Sales Reports</h3>
                        <p>View revenue, payments, and financial analytics</p>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>

               

                <div class="module-card" onclick="window.location.href='account_verification.php'">
                    <div class="module-icon teal">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="module-content">
                        <h3>Account Verification</h3>
                        <p>Review and approve new member registrations</p>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <div class="section-header">
                <h2>Recent Activity</h2>
                <a href="#" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon blue">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="activity-content">
                        <p class="activity-title">New member registered</p>
                        <p class="activity-time">5 minutes ago</p>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon green">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="activity-content">
                        <p class="activity-title">Payment received - Monthly membership</p>
                        <p class="activity-time">15 minutes ago</p>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon orange">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div class="activity-content">
                        <p class="activity-title">Walk-in client checked in</p>
                        <p class="activity-time">30 minutes ago</p>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon purple">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div class="activity-content">
                        <p class="activity-title">Employee profile updated</p>
                        <p class="activity-time">1 hour ago</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/admin-dashboard.js"></script>
</body>
</html>