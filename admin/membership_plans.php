<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Super Admin', 'Admin'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

// Fetch membership plans with filters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : 'all';

try {
    $sql = "SELECT * FROM memberships WHERE 1=1";
    $params = [];
    
    if ($statusFilter !== 'all') {
        $sql .= " AND status = :status";
        $params[':status'] = $statusFilter;
    }
    
    if ($typeFilter === 'base') {
        $sql .= " AND is_base_membership = 1";
    } elseif ($typeFilter === 'addon') {
        $sql .= " AND is_base_membership = 0";
    }
    
    $sql .= " ORDER BY is_base_membership DESC, monthly_fee ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $totalPlans = $conn->query("SELECT COUNT(*) FROM memberships")->fetchColumn();
    $activePlans = $conn->query("SELECT COUNT(*) FROM memberships WHERE status = 'Active'")->fetchColumn();
    $avgMonthlyFee = $conn->query("SELECT AVG(monthly_fee) FROM memberships WHERE status = 'Active'")->fetchColumn();
    
} catch (Exception $e) {
    $memberships = [];
    $totalPlans = 0;
    $activePlans = 0;
    $avgMonthlyFee = 0;
}

$employeeName = $_SESSION['employee_name'] ?? 'Admin';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Plans - Empire Fitness</title>
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/membership-plans.css">
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
                <h1>Membership Plans</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Rates & Pricing / Membership Plans
                </p>
            </div>
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search plans...">
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

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Plans</h3>
                    <p class="stat-number"><?php echo number_format($totalPlans); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-list"></i> All membership plans
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Active Plans</h3>
                    <p class="stat-number"><?php echo number_format($activePlans); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Currently available
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>Avg. Monthly Fee</h3>
                    <p class="stat-number">₱<?php echo number_format($avgMonthlyFee, 2); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-calculator"></i> Average price
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <h3>Quick Actions</h3>
                    <p class="stat-number">
                        <button class="action-btn" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Plan
                        </button>
                    </p>
                    <span class="stat-change neutral">
                        <i class="fas fa-plus-circle"></i> Create new plan
                    </span>
                </div>
            </div>
        </div>

        <!-- Filters and Actions -->
        <div class="content-section">
            <div class="section-header">
                <h2>Manage Membership Plans</h2>
                <button class="btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Plan
                </button>
            </div>

            <div class="filters-container">
                <div class="filter-group">
                    <label for="statusFilter">Status:</label>
                    <select id="statusFilter" onchange="applyFilters()">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $statusFilter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="typeFilter">Plan Type:</label>
                    <select id="typeFilter" onchange="applyFilters()">
                        <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="base" <?php echo $typeFilter === 'base' ? 'selected' : ''; ?>>Base Membership</option>
                        <option value="addon" <?php echo $typeFilter === 'addon' ? 'selected' : ''; ?>>Add-on Plans</option>
                    </select>
                </div>

                <button class="btn-secondary" onclick="resetFilters()">
                    <i class="fas fa-redo"></i> Reset Filters
                </button>
            </div>

            <!-- Alert Box -->
            <div id="alertBox" class="alert" style="display: none;"></div>

            <!-- Plans Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Plan Name</th>
                            <th>Monthly Fee</th>
                            <th>Renewal Fee</th>
                            <th>Discount</th>
                            <th>Duration</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($memberships) > 0): ?>
                            <?php foreach ($memberships as $plan): ?>
                                <tr>
                                    <td><?php echo $plan['membership_id']; ?></td>
                                    <td>
                                        <div class="plan-name-cell">
                                            <strong><?php echo htmlspecialchars($plan['plan_name']); ?></strong>
                                            <?php if ($plan['is_base_membership']): ?>
                                                <span class="badge badge-primary">Base</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="price-cell">₱<?php echo number_format($plan['monthly_fee'], 2); ?></td>
                                    <td class="price-cell">
                                        <?php echo $plan['renewal_fee'] ? '₱' . number_format($plan['renewal_fee'], 2) : '—'; ?>
                                    </td>
                                    <td>
                                        <?php if ($plan['renewal_discount_percent'] > 0): ?>
                                            <span class="discount-badge"><?php echo number_format($plan['renewal_discount_percent'], 0); ?>% OFF</span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $months = round($plan['duration_days'] / 30, 1);
                                        echo $plan['duration_days'] . ' days';
                                        echo '<br><small>(' . $months . ' months)</small>';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $plan['is_base_membership'] ? 'badge-info' : 'badge-secondary'; ?>">
                                            <?php echo $plan['is_base_membership'] ? 'Base Plan' : 'Add-on'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($plan['status']); ?>">
                                            <?php echo htmlspecialchars($plan['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" onclick='viewPlan(<?php echo json_encode($plan); ?>)' title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-edit" onclick='editPlan(<?php echo json_encode($plan); ?>)' title="Edit Plan">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-action btn-toggle" onclick="toggleStatus(<?php echo $plan['membership_id']; ?>, '<?php echo $plan['status']; ?>')" title="Toggle Status">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                            <button class="btn-action btn-delete" onclick="deletePlan(<?php echo $plan['membership_id']; ?>, '<?php echo htmlspecialchars($plan['plan_name'], ENT_QUOTES); ?>')" title="Delete Plan">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No membership plans found</p>
                                    <button class="btn-primary" onclick="openAddModal()">Create Your First Plan</button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="planModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Membership Plan</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="planForm">
                    <input type="hidden" id="membershipId" name="membership_id">
                    <input type="hidden" id="formAction" name="action" value="add">

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="plan_name">Plan Name *</label>
                            <input type="text" id="plan_name" name="plan_name" required placeholder="e.g., Premium Annual Membership">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="monthly_fee">Monthly Fee (₱) *</label>
                            <input type="number" id="monthly_fee" name="monthly_fee" step="0.01" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="duration_days">Duration (Days) *</label>
                            <input type="number" id="duration_days" name="duration_days" required placeholder="30">
                            <small>Common: 30 (1 month), 90 (3 months), 365 (1 year)</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="renewal_fee">Renewal Fee (₱)</label>
                            <input type="number" id="renewal_fee" name="renewal_fee" step="0.01" placeholder="Optional">
                        </div>
                        <div class="form-group">
                            <label for="renewal_discount_percent">Renewal Discount (%)</label>
                            <input type="number" id="renewal_discount_percent" name="renewal_discount_percent" step="0.01" min="0" max="100" placeholder="0">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="is_base_membership">Plan Type *</label>
                            <select id="is_base_membership" name="is_base_membership" required>
                                <option value="1">Base Membership</option>
                                <option value="0">Add-on Plan</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" placeholder="Brief description of the plan..."></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="benefits">Benefits (one per line)</label>
                            <textarea id="benefits" name="benefits" rows="4" placeholder="Access to all gym equipment&#10;Free group classes&#10;Personal trainer consultation&#10;Locker access"></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Save Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Plan Details</h3>
                <button class="close-btn" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <script src="../js/admin-dashboard.js"></script>
    <script src="js/membership-plans.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>