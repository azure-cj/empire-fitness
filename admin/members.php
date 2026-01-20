<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Super Admin', 'Admin'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

$employeeName = $_SESSION['employee_name'] ?? 'Admin';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members Management - Empire Fitness</title>
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/employee-management.css">
    <link rel="stylesheet" href="css/members.css">
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
    <a href="members.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'members.php' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span>Members</span>
    </a>
    <a href="member_applications.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'member_applications.php' ? 'active' : ''; ?>">
        <i class="fas fa-crown"></i>
        <span>Member Applications</span>
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
                <h1>Members Management</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Members
                </p>
            </div>
        </div>

        <!-- Alert Box -->
        <div id="alertBox" class="alert-box"></div>

        <!-- Stats Summary -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Members</h3>
                    <p class="stat-number" id="totalMembers">0</p>
                    <span class="stat-change neutral">
                        <i class="fas fa-users"></i> All registered
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3>Active Members</h3>
                    <p class="stat-number" id="activeMembers">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Currently active
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>Inactive Members</h3>
                    <p class="stat-number" id="inactiveMembers">0</p>
                    <span class="stat-change neutral">
                        <i class="fas fa-pause-circle"></i> Not active
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-content">
                    <h3>Suspended</h3>
                    <p class="stat-number" id="suspendedMembers">0</p>
                    <span class="stat-change negative">
                        <i class="fas fa-ban"></i> Suspended accounts
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-content">
                    <h3>New This Month</h3>
                    <p class="stat-number" id="newMembers">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-calendar-check"></i> Recent joins
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon teal">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-content">
                    <h3>Verified Accounts</h3>
                    <p class="stat-number" id="verifiedMembers">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-check-circle"></i> Email verified
                    </span>
                </div>
            </div>
        </div>

        <!-- Controls Section -->
        <div class="controls-section">
            <div class="controls-left">
                <button onclick="openAddModal()" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Add Member
                </button>
                <button onclick="exportMembers('csv')" class="btn-export">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button onclick="bulkAction()" class="btn-secondary">
                    <i class="fas fa-tasks"></i> Bulk Actions
                </button>
            </div>
            <div class="controls-right">
                <div class="search-box-inline">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search members..." onkeyup="searchMembers()">
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-btn active" onclick="filterMembers('all')">
                <i class="fas fa-users"></i> All Members
            </button>
            <button class="filter-btn" onclick="filterMembers('active')">
                <i class="fas fa-check-circle"></i> Active
            </button>
            <button class="filter-btn" onclick="filterMembers('inactive')">
                <i class="fas fa-pause-circle"></i> Inactive
            </button>
            <button class="filter-btn" onclick="filterMembers('suspended')">
                <i class="fas fa-ban"></i> Suspended
            </button>
            <button class="filter-btn" onclick="filterMembers('verified')">
                <i class="fas fa-user-shield"></i> Verified
            </button>
            <button class="filter-btn" onclick="filterMembers('unverified')">
                <i class="fas fa-user-clock"></i> Unverified
            </button>
        </div>

        <!-- Members Table -->
        <div class="table-container">
            <table class="employees-table">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        </th>
                        <th>Member ID</th>
                        <th>Member Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Account Status</th>
                        <th>Verified</th>
                        <th>Join Date</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="membersTableBody">
                    <tr>
                        <td colspan="12" class="no-data">
                            <i class="fas fa-spinner fa-spin" style="font-size: 48px; opacity: 0.3;"></i>
                            <p>Loading members...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div id="membersPaginationContainer"></div>
    </div>

    <!-- View Member Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> Member Details</h3>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Add/Edit Member Modal -->
    <div id="memberModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add Member</h3>
                <button class="modal-close" onclick="closeMemberModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="memberForm">
                    <input type="hidden" id="memberId" name="client_id">
                    <input type="hidden" id="formAction" name="action" value="add">
                    
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h4><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label for="middle_name">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" maxlength="20">
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" onclick="closeMemberModal()" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Save Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/admin-dashboard.js"></script>
    <script src="js/members.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>