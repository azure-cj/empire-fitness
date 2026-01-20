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

// Include dashboard data file
require_once __DIR__ . '/includes/receptionist_dashboard_data.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard - Empire Fitness</title>
    <link rel="stylesheet" href="css/receptionist-dashboard.css">
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
            
            <a href="entry_exit.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'entry_exit.php' ? 'active' : ''; ?>">
                <i class="fas fa-door-open"></i>
                <span>Entry/Exit</span>
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
                <h1>Reception Desk</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Dashboard
                </p>
            </div>
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" placeholder="Search members...">
                </div>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <?php if ($pendingPayments > 0): ?>
                    <span class="notification-badge"><?php echo $pendingPayments; ?></span>
                    <?php endif; ?>
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
                <h2>Welcome, <?php echo htmlspecialchars(explode(' ', $employeeName)[0]); ?>! 👋</h2>
                <p>Manage member check-ins, payments, and front desk operations.</p>
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
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="stat-content">
                    <h3>Today's Check-ins</h3>
                    <p class="stat-number"><?php echo number_format($todayCheckins); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-user-check"></i> Members entered
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>Today's Revenue</h3>
                    <p class="stat-number">₱<?php echo number_format($todayRevenue, 2); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Collected today
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-content">
                    <h3>Pending Payments</h3>
                    <p class="stat-number"><?php echo number_format($pendingPayments); ?></p>
                    <span class="stat-change <?php echo $pendingPayments > 0 ? 'negative' : 'neutral'; ?>">
                        <i class="fas fa-exclamation-circle"></i> Needs attention
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Currently Inside</h3>
                    <p class="stat-number"><?php echo number_format($currentlyInside); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-door-open"></i> Active now
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3>Today's Classes</h3>
                    <p class="stat-number"><?php echo number_format($todayClasses); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-dumbbell"></i> Scheduled
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon teal">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Members</h3>
                    <p class="stat-number"><?php echo number_format($totalMembers); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-check-circle"></i> Active members
                    </span>
                </div>
            </div>
        </div>

        <!-- Quick Access Modules -->
        <div class="modules-section">
            <h2 class="section-title">Quick Access</h2>
            <div class="module-grid">
                <div class="module-card" onclick="window.location.href='manage_entry_exit.php'">
                    <div class="module-icon green">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                    <div class="module-content">
                        <h3>Member Entry/Exit</h3>
                        <p>Scan member IDs and manage gym access</p>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>

                <div class="module-card" onclick="window.location.href='schedule_classes.php'">
                    <div class="module-icon purple">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="module-content">
                        <h3>Class Schedule</h3>
                        <p>View and manage fitness class schedules</p>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>

                <div class="module-card" onclick="window.location.href='members_list.php'">
                    <div class="module-icon orange">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="module-content">
                        <h3>Members List</h3>
                        <p>View and search member information</p>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>

                <div class="module-card" onclick="window.location.href='daily_report.php'">
                    <div class="module-icon teal">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="module-content">
                        <h3>Daily Report</h3>
                        <p>View daily statistics and summaries</p>
                    </div>
                    <i class="fas fa-arrow-right module-arrow"></i>
                </div>
            </div>
        </div>

        <!-- Recent Activity & Today's Classes -->
        <div class="dashboard-grid">
            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="section-header">
                    <h2>Recent Activity</h2>
                    <a href="manage_entry_exit.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="activity-list">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach (array_slice($recentActivities, 0, 4) as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $activity['time_out'] ? 'orange' : 'green'; ?>">
                                <i class="fas <?php echo getActivityIcon($activity['attendance_type'], $activity['time_out']); ?>"></i>
                            </div>
                            <div class="activity-content">
                                <p class="activity-title">
                                    <?php if ($activity['time_out']): ?>
                                        Member checked out - <?php echo htmlspecialchars($activity['client_name'] ?? $activity['guest_name']); ?>
                                    <?php else: ?>
                                        Member checked in - <?php echo htmlspecialchars($activity['client_name'] ?? $activity['guest_name']); ?>
                                    <?php endif; ?>
                                </p>
                                <p class="activity-time"><?php echo timeAgo($activity['check_in_timestamp']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="activity-item">
                            <div class="activity-content">
                                <p class="activity-title">No recent activity</p>
                                <p class="activity-time">Check back later</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Today's Classes -->
            <div class="pending-approvals">
                <div class="section-header">
                    <h2>Today's Classes</h2>
                    <a href="schedule_classes.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="approval-list">
                    <?php if (!empty($todaysClasses)): ?>
                        <?php foreach (array_slice($todaysClasses, 0, 3) as $class): ?>
                        <div class="approval-item">
                            <div class="approval-icon blue">
                                <i class="fas <?php echo getClassIcon($class['class_type']); ?>"></i>
                            </div>
                            <div class="approval-content">
                                <p class="approval-title"><?php echo htmlspecialchars($class['class_name']); ?></p>
                                <p class="approval-details">
                                    <?php echo date('g:i A', strtotime($class['start_time'])); ?> - 
                                    <?php echo htmlspecialchars($class['coach_first_name'] . ' ' . $class['coach_last_name']); ?>
                                </p>
                            </div>
                            <div class="class-status">
                                <span class="status-badge upcoming">
                                    <?php echo $class['current_bookings'] ?? 0; ?>/<?php echo $class['max_capacity']; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="approval-item">
                            <div class="approval-content">
                                <p class="approval-title">No classes scheduled</p>
                                <p class="approval-details">No classes for today</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/receptionist-dashboard.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>