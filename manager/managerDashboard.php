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
?>
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Empire Fitness</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/button-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar_navigation.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <h1>Manager Dashboard</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Dashboard
                </p>
            </div>
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" placeholder="Search employees...">
                </div>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notification-badge" style="display: none;"></span>
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
                <h2>Welcome back, <span id="first-name"><?php echo htmlspecialchars(explode(' ', $employeeName)[0]); ?></span>! 👋</h2>
                <p>Manage your team and oversee daily operations.</p>
            </div>
            <div class="quick-date">
                <i class="fas fa-calendar-alt"></i>
                <span id="current-date"><?php echo date('l, F d, Y'); ?></span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-content">
                    <h3>Active Coaches</h3>
                    <p class="stat-number" id="coaches-count">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-check-circle"></i> On duty
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Staff Members</h3>
                    <p class="stat-number" id="staff-count">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Active staff
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-content">
                    <h3>Pending Requests</h3>
                    <p class="stat-number" id="requests-count">0</p>
                    <span class="stat-change" id="requests-status">
                        <i class="fas fa-hourglass-half"></i> Awaiting review
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="stat-content">
                    <h3>Active Assignments</h3>
                    <p class="stat-number" id="assignments-count">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-link"></i> Coach-Client
                    </span>
                </div>
            </div>
        </div>

        <!-- Quick Access Modules -->
        <div class="modules-section">
            <h2 class="section-title">Quick Access</h2>
            <div class="module-grid">
                <div class="module-card" onclick="window.location.href='employees.php'">
                    <div class="module-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="module-content">
                        <h3>View All Employees</h3>
                        <p>Manage coaches, receptionists, and staff</p>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>

                <div class="module-card" onclick="window.location.href='coach_schedules.php'">
                    <div class="module-icon green">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="module-content">
                        <h3>Coach Schedules</h3>
                        <p>Create and update coach schedules</p>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>

                <div class="module-card" onclick="window.location.href='time_off_requests.php'">
                    <div class="module-icon orange">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="module-content">
                        <h3>Time-off Requests</h3>
                        <p>Review and approve/reject requests</p>
                        <span class="module-badge" id="module-badge" style="display: none;"></span>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>

                <div class="module-card" onclick="window.location.href='coach_performance.php'">
                    <div class="module-icon red">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="module-content">
                        <h3>Performance Metrics</h3>
                        <p>Monitor coach performance and attendance</p>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>

                <div class="module-card" onclick="window.location.href='personal_training.php'">
                    <div class="module-icon teal">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="module-content">
                        <h3>Personal Training</h3>
                        <p>Manage PT sessions and consultations</p>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>
            </div>
        </div>

        <!-- Recent Activity & Pending Approvals -->
        <div class="dashboard-grid">
            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="section-header">
                    <h2>Recent Activity</h2>
                    <a href="#" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="activity-list" id="activity-list">
                    <div class="loading-placeholder">Loading activity...</div>
                </div>
            </div>

            <!-- Pending Approvals -->
            <div class="pending-approvals">
                <div class="section-header">
                    <h2>Pending Approvals</h2>
                    <a href="time_off_requests.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="approval-list" id="approval-list">
                    <div class="loading-placeholder">Loading approvals...</div>
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

    <script src="js/sidebar.js"></script>
    <script src="js/manager-dashboard.js"></script>
</body>
</html>