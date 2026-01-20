<?php
/**
 * Sidebar Navigation Include for Receptionist Portal
 * This file provides a consistent navigation sidebar for all receptionist pages
 * 
 * Usage: Include this file after session_start() and authentication checks
 * <?php include 'includes/sidebar_navigation.php'; ?>
 */

// Get current page filename for active state
$currentPage = basename($_SERVER['PHP_SELF']);
$employeeName = $_SESSION['employee_name'] ?? 'Receptionist';
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
                <p>Receptionist</p>
            </div>
        </div>
    </div>

    <div class="profile-section" onclick="window.location.href='profile.php'" style="cursor: pointer;">
        <div class="profile-avatar"><?php echo $employeeInitial; ?></div>
        <div class="profile-info">
            <div class="profile-name"><?php echo htmlspecialchars($employeeName); ?></div>
            <div class="profile-role"><?php echo htmlspecialchars($_SESSION['employee_role'] ?? 'Receptionist'); ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="receptionistDashboard.php" class="nav-item <?php echo $currentPage == 'receptionistDashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <div class="nav-divider">SALES</div>
        
        <a href="pos.php" class="nav-item <?php echo $currentPage == 'pos.php' ? 'active' : ''; ?>">
            <i class="fas fa-cash-register"></i>
            <span>Point of Sale</span>
        </a>
        
        <a href="pos_reports.php" class="nav-item <?php echo $currentPage == 'pos_reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>POS Reports</span>
        </a>
        
        <div class="nav-divider">PAYMENTS</div>
        
        <a href="payment_history.php" class="nav-item <?php echo $currentPage == 'payment_history.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>Payment History</span>
        </a>

        <div class="nav-divider">MEMBER MANAGEMENT</div>
        
        <a href="entry_exit.php" class="nav-item <?php echo $currentPage == 'entry_exit.php' ? 'active' : ''; ?>">
            <i class="fas fa-sign-in-alt"></i>
            <span>Entry/Exit</span>
        </a>
        <a href="members_list.php" class="nav-item <?php echo $currentPage == 'members_list.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Members List</span>
        </a>
        
        <div class="nav-divider">ACCOUNT</div>
        
        <a href="profile.php" class="nav-item <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
        </a>

        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>
</div>
