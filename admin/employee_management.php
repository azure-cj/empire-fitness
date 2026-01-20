<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Super Admin', 'Admin'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

// Fetch employees based on filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT * FROM employees WHERE 1=1";
$params = [];

// Apply filters
if ($filter === 'receptionist') {
    $sql .= " AND position = 'Receptionist'";
} elseif ($filter === 'manager') {
    $sql .= " AND position = 'Manager'";
} elseif ($filter === 'trainer') {
    $sql .= " AND position = 'Trainer'";
} elseif ($filter === 'active') {
    $sql .= " AND status = 'Active'";
} elseif ($filter === 'inactive') {
    $sql .= " AND status = 'Inactive'";
}

// Apply search
if (!empty($searchTerm)) {
    $sql .= " AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR phone LIKE :search OR employee_code LIKE :search)";
    $params['search'] = "%$searchTerm%";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$employeeName = $_SESSION['employee_name'] ?? 'Admin';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - Empire Fitness</title>
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
    
    <a href="gym_entry.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'gym_entry.php' ? 'active' : ''; ?>">
        <i class="fas fa-door-open"></i>
        <span>Gym Entry Logs</span>
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
                <h1>Employee Management</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Employee Management
                </p>
            </div>
        </div>

        <!-- Alert Box -->
        <div id="alertBox" class="alert-box"></div>

        <!-- Controls Section -->
        <div class="controls-section">
            <div class="controls-left">
                <button onclick="openAddModal()" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Add Employee
                </button>
                <button onclick="exportEmployees('csv')" class="btn-export">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
            </div>
            <div class="controls-right">
                <div class="search-box-inline">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search employees..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" onclick="applyFilter('all')">
                <i class="fas fa-users"></i> All Employees
            </button>
            <button class="filter-btn <?= $filter === 'active' ? 'active' : '' ?>" onclick="applyFilter('active')">
                <i class="fas fa-check-circle"></i> Active
            </button>
            <button class="filter-btn <?= $filter === 'inactive' ? 'active' : '' ?>" onclick="applyFilter('inactive')">
                <i class="fas fa-times-circle"></i> Inactive
            </button>
            <button class="filter-btn <?= $filter === 'manager' ? 'active' : '' ?>" onclick="applyFilter('manager')">
                <i class="fas fa-user-tie"></i> Managers
            </button>
            <button class="filter-btn <?= $filter === 'receptionist' ? 'active' : '' ?>" onclick="applyFilter('receptionist')">
                <i class="fas fa-user-clock"></i> Receptionists
            </button>
        </div>

        <!-- Stats Summary -->
        <div class="stats-summary">
            <div class="stat-item">
                <i class="fas fa-users"></i>
                <div>
                    <span class="stat-number"><?php echo count($employees); ?></span>
                    <span class="stat-label">Total Employees</span>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-user-check"></i>
                <div>
                    <span class="stat-number"><?php echo count(array_filter($employees, fn($e) => $e['status'] === 'Active')); ?></span>
                    <span class="stat-label">Active</span>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-user-times"></i>
                <div>
                    <span class="stat-number"><?php echo count(array_filter($employees, fn($e) => $e['status'] === 'Inactive')); ?></span>
                    <span class="stat-label">Inactive</span>
                </div>
            </div>
        </div>

        <!-- Employees Table -->
        <div class="table-container">
            <table class="employees-table">
                <thead>
                    <tr>
                        <th>Employee Code</th>
                        <th>Employee Name</th>
                        <th>Email</th>
                        <th>Contact Number</th>
                        <th>Position</th>
                        <th>System Role</th>
                        <th>Status< /th>
                        <th>Hire Date</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <span class="employee-code"><?= htmlspecialchars($emp['employee_code'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            <?= strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="employee-name">
                                                <?= htmlspecialchars($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($emp['email']) ?></td>
                                <td><?= htmlspecialchars($emp['phone']) ?></td>
                                <td>
                                    <span class="position-badge position-<?= strtolower($emp['position']) ?>">
                                        <?= htmlspecialchars($emp['position']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="role-badge role-<?= strtolower(str_replace(' ', '-', $emp['role'])) ?>">
                                        <?= htmlspecialchars($emp['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($emp['status']) ?>">
                                        <i class="fas fa-circle"></i>
                                        <?= htmlspecialchars($emp['status']) ?>
                                    </span>
                                </td>
                                <td><?= $emp['hire_date'] ? date('M d, Y', strtotime($emp['hire_date'])) : 'N/A' ?></td>
                                <td><?= $emp['last_login'] ? date('M d, Y h:i A', strtotime($emp['last_login'])) : 'Never' ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="viewEmployee(<?= $emp['employee_id'] ?>)" class="btn-action btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="openEditModal(<?= $emp['employee_id'] ?>)" class="btn-action btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="resetPassword(<?= $emp['employee_id'] ?>)" class="btn-action btn-reset" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <button onclick="deleteEmployee(<?= $emp['employee_id'] ?>, '<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>')" class="btn-action btn-delete" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="no-data">
                                <i class="fas fa-users" style="font-size: 48px; opacity: 0.3;"></i>
                                <p>No employees found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add/Edit Employee Modal -->
    <div id="employeeModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add Employee</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="employeeForm">
                    <input type="hidden" id="employeeId" name="employee_id">
                    <input type="hidden" id="formAction" name="action" value="add">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h4><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" maxlength="50" required>
                            </div>
                            <div class="form-group">
                                <label for="middle_name">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name" maxlength="50">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" maxlength="50" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Contact Number *</label>
                                <input type="text" id="phone" name="phone" maxlength="11" pattern="[0-9]{11}" placeholder="09XXXXXXXXX" required>
                                <small style="color: #666; font-size: 12px;">11-digit number only (e.g., 09123456789)</small>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" maxlength="100" placeholder="employee@empirefitness.com" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="address">Home Address</label>
                            <textarea id="address" name="address" rows="2" placeholder="Complete home address"></textarea>
                        </div>
                    </div>

                    <!-- Employment Details Section -->
                    <div class="form-section">
                        <h4><i class="fas fa-briefcase"></i> Employment Details</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="hire_date">Hire Date</label>
                                <input type="date" id="hire_date" name="hire_date">
                            </div>
                        </div>
                        <!-- Hidden position field - automatically set based on system role -->
                        <input type="hidden" id="position" name="position" value="">
                    </div>

                    <!-- Emergency Contact Section -->
                    <div class="form-section">
                        <h4><i class="fas fa-phone-alt"></i> Emergency Contact</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="emergency_contact">Emergency Contact Name</label>
                                <input type="text" id="emergency_contact" name="emergency_contact" maxlength="100" placeholder="Full name of emergency contact">
                            </div>
                            <div class="form-group">
                                <label for="emergency_phone">Emergency Contact Number</label>
                                <input type="text" id="emergency_phone" name="emergency_phone" maxlength="11" pattern="[0-9]{11}" placeholder="09XXXXXXXXX">
                                <small style="color: #666; font-size: 12px;">11-digit number only</small>
                            </div>
                        </div>
                    </div>

                    <!-- Account Access Section -->
                    <div class="form-section">
                        <h4><i class="fas fa-lock"></i> System Access & Account</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="role">System Role *</label>
                                <select id="role" name="role" required>
                                    <option value="Receptionist">Receptionist</option>
                                    <option value="Manager">Manager</option>
                                    <option value="Admin">Admin</option>
                                    <?php if ($_SESSION['employee_role'] === 'Super Admin'): ?>
                                    <option value="Super Admin">Super Admin</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Account Status *</label>
                                <select id="status" name="status" required>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-note">
                            <i class="fas fa-info-circle"></i>
                            <span>A default password and employee code will be automatically generated upon creation.</span>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" onclick="closeModal()" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Save Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Employee Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> Employee Details</h3>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-body" id="confirmContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <script src="../js/admin-dashboard.js"></script>
    <script src="js/employee-management.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>