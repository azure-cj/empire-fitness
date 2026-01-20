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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members List - Empire Fitness</title>
    <link rel="stylesheet" href="css/receptionist-dashboard.css">
    <link rel="stylesheet" href="css/members-list.css">
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
            <a href="receptionistDashboard.php" class="nav-item">
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
                <h1>Members List</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Member Management / Members List
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
            <!-- Stats Cards -->
            <div class="members-stats">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Members</h3>
                        <p class="stat-number"><span id="total-members">0</span></p>
                        <span class="stat-change neutral">
                            <i class="fas fa-user"></i> All Time
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Active Members</h3>
                        <p class="stat-number"><span id="active-members">0</span></p>
                        <span class="stat-change positive">
                            <i class="fas fa-check-circle"></i> Current
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Expiring Soon</h3>
                        <p class="stat-number"><span id="expiring-members">0</span></p>
                        <span class="stat-change warning">
                            <i class="fas fa-exclamation-triangle"></i> Within 7 Days
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Inactive Members</h3>
                        <p class="stat-number"><span id="inactive-members">0</span></p>
                        <span class="stat-change negative">
                            <i class="fas fa-ban"></i> Not Active
                        </span>
                    </div>
                </div>
            </div>

            <!-- Members Table Section -->
            <div class="members-section">
                <div class="section-header">
                    <div class="section-title">
                        <h3>All Members</h3>
                        <p>View and manage gym members</p>
                    </div>
                    <div class="section-actions">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="search-members" placeholder="Search members...">
                        </div>
                        <select id="filter-status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                        <select id="filter-type" class="filter-select">
                            <option value="">All Types</option>
                            <option value="Member">Member</option>
                            <option value="Walk-in">Walk-in</option>
                        </select>
                        <button class="btn btn-primary" onclick="exportMembers()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>

                <div class="members-table">
                    <table id="members-table">
                        <thead>
                            <tr>
                                <th>Member ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Type</th>
                                <th>Membership</th>
                                <th>Status</th>
                                <th>Join Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="members-tbody">
                            <tr class="empty-row">
                                <td colspan="9" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>Loading members...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="table-pagination">
                    <div class="pagination-info">
                        Showing <span id="showing-start">0</span> to <span id="showing-end">0</span> of <span id="total-records">0</span> members
                    </div>
                    <div class="pagination-controls">
                        <button class="btn-view btn-sm" id="prev-page" onclick="changePage('prev')">
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <div class="page-numbers" id="page-numbers"></div>
                        <button class="btn-view btn-sm" id="next-page" onclick="changePage('next')">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Member Details Modal -->
    <div class="modal" id="member-modal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-title">
                        <i class="fas fa-user"></i>
                        Member Details
                    </h3>
                    <button class="modal-close" onclick="closeMemberModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="member-details-grid">
                        <!-- Personal Information -->
                        <div class="detail-section">
                            <h4><i class="fas fa-user-circle"></i> Personal Information</h4>
                            <div class="detail-row">
                                <span class="detail-label">Full Name:</span>
                                <span class="detail-value" id="member-name">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value" id="member-email">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone:</span>
                                <span class="detail-value" id="member-phone">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Referral Source:</span>
                                <span class="detail-value" id="member-referral">-</span>
                            </div>
                        </div>

                        <!-- Membership Information -->
                        <div class="detail-section">
                            <h4><i class="fas fa-id-card"></i> Membership Information</h4>
                            <div class="detail-row">
                                <span class="detail-label">Member Type:</span>
                                <span class="detail-value" id="member-type">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Current Plan:</span>
                                <span class="detail-value" id="member-plan">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Start Date:</span>
                                <span class="detail-value" id="member-start">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">End Date:</span>
                                <span class="detail-value" id="member-end">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Days Remaining:</span>
                                <span class="detail-value" id="member-days">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Base Plan:</span>
                                <span class="detail-value" id="member-base-plan">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Base Plan Days Remaining:</span>
                                <span class="detail-value" id="member-base-days">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Monthly Plan:</span>
                                <span class="detail-value" id="member-monthly-plan">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Monthly Plan Days Remaining:</span>
                                <span class="detail-value" id="member-monthly-days">-</span>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="detail-section">
                            <h4><i class="fas fa-cog"></i> Account Status</h4>
                            <div class="detail-row">
                                <span class="detail-label">Status:</span>
                                <span class="detail-value" id="member-status">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Join Date:</span>
                                <span class="detail-value" id="member-join">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Last Login:</span>
                                <span class="detail-value" id="member-login">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Verified:</span>
                                <span class="detail-value" id="member-verified">-</span>
                            </div>
                        </div>

                        <!-- Coach Assignment -->
                        <div class="detail-section">
                            <h4><i class="fas fa-user-tie"></i> Coach Assignment</h4>
                            <div class="detail-row">
                                <span class="detail-label">Assigned Coach:</span>
                                <span class="detail-value" id="member-coach">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeMemberModal()">Close</button>
                    <button class="btn btn-primary" onclick="viewMemberHistory()">
                        <i class="fas fa-history"></i> View History
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <div class="toast-content">
            <i class="toast-icon fas fa-check-circle"></i>
            <span class="toast-message" id="toast-message">Action completed successfully</span>
        </div>
    </div>

    <script src="js/receptionist-dashboard.js"></script>
    <script src="js/members-list.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>