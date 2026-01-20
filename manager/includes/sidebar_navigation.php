<?php
/**
 * Sidebar Navigation Include for Manager Portal
 * This file provides a consistent navigation sidebar for all manager pages
 * 
 * Usage: Include this file after session_start() and authentication checks
 * <?php include '../includes/sidebar_navigation.php'; ?>
 */

// Get current page filename for active state
$currentPage = basename($_SERVER['PHP_SELF']);
$employeeName = $_SESSION['employee_name'] ?? 'Manager';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>

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
                <p>Manager Portal</p>
            </div>
        </div>
    </div>

    <div class="profile-section" onclick="window.location.href='profile.php'" style="cursor: pointer;">
        <div class="profile-avatar"><?php echo $employeeInitial; ?></div>
        <div class="profile-info">
            <div class="profile-name"><?php echo htmlspecialchars($employeeName); ?></div>
            <div class="profile-role"><?php echo htmlspecialchars($_SESSION['employee_role'] ?? 'Manager'); ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="managerDashboard.php" class="nav-item <?php echo $currentPage == 'managerDashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <div class="nav-divider">STAFF MANAGEMENT</div>
        
        <a href="employees.php" class="nav-item <?php echo $currentPage == 'employees.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>All Employees</span>
        </a>
        <a href="coaches.php" class="nav-item <?php echo $currentPage == 'coaches.php' || $currentPage == 'coach_profile.php' || $currentPage == 'edit_coach.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i>
            <span>Coaches</span>
        </a>
        
        <div class="nav-divider">FINANCIAL</div>
        
        <a href="commission-management.php" class="nav-item <?php echo $currentPage == 'commission-management.php' || $currentPage == 'invoice-detail.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Commission Management</span>
        </a>
        <a href="pos_reports.php" class="nav-item <?php echo $currentPage == 'pos_reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>POS Reports</span>
        </a>
        
        <div class="nav-divider">SCHEDULING</div>
        
        <a href="coach_schedules.php" class="nav-item <?php echo $currentPage == 'coach_schedules.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Coach Schedules</span>
        </a>
       
        <a href="assessments.php" class="nav-item <?php echo $currentPage == 'assessments.php' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-check"></i>
            <span>Assessments</span>
        </a>
        
        <div class="nav-divider">MEMBERS</div>
        
        <a href="members.php" class="nav-item <?php echo $currentPage == 'members.php' || $currentPage == 'member_applications.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Members</span>
        </a>
        <a href="member_applications.php" class="nav-item <?php echo $currentPage == 'member_applications.php' ? 'active' : ''; ?>">
            <i class="fas fa-crown"></i>
            <span>Member Applications</span>
        </a>
        
        <div class="nav-divider">ACCOUNT</div>
        
        <a href="profile.php" class="nav-item <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i>
            <span>My Profile</span>
        </a>
        <a href="settings.php" class="nav-item <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>">
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
