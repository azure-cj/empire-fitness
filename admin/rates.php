<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Super Admin', 'Admin'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

// Fetch rates with filters
$appliesFilter = isset($_GET['applies_to']) ? $_GET['applies_to'] : 'all';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'active';
$discountFilter = isset($_GET['discount']) ? $_GET['discount'] : 'all';

try {
    $sql = "SELECT r.*, br.rate_name as base_rate_name, br.price as base_price 
            FROM rates r 
            LEFT JOIN rates br ON r.base_rate_id = br.rate_id 
            WHERE 1=1";
    $params = [];
    
    if ($appliesFilter !== 'all') {
        $sql .= " AND r.applies_to = :applies_to";
        $params[':applies_to'] = $appliesFilter;
    }
    
    if ($statusFilter === 'active') {
        $sql .= " AND r.is_active = 1";
    } elseif ($statusFilter === 'inactive') {
        $sql .= " AND r.is_active = 0";
    }
    
    if ($discountFilter === 'discounted') {
        $sql .= " AND r.is_discounted = 1";
    } elseif ($discountFilter === 'regular') {
        $sql .= " AND r.is_discounted = 0";
    }
    
    $sql .= " ORDER BY r.applies_to, r.is_discounted, r.price ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $totalRates = $conn->query("SELECT COUNT(*) FROM rates")->fetchColumn();
    $activeRates = $conn->query("SELECT COUNT(*) FROM rates WHERE is_active = 1")->fetchColumn();
    $discountedRates = $conn->query("SELECT COUNT(*) FROM rates WHERE is_discounted = 1")->fetchColumn();
    $avgPrice = $conn->query("SELECT AVG(price) FROM rates WHERE is_active = 1")->fetchColumn();
    
    // Get base rates for dropdown
    $baseRatesStmt = $conn->query("SELECT rate_id, rate_name, price FROM rates WHERE is_discounted = 0 ORDER BY rate_name");
    $baseRates = $baseRatesStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $rates = [];
    $baseRates = [];
    $totalRates = 0;
    $activeRates = 0;
    $discountedRates = 0;
    $avgPrice = 0;
}

$employeeName = $_SESSION['employee_name'] ?? 'Admin';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rates & Fees - Empire Fitness</title>
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/rates.css">
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
                <h1>Rates & Fees Management</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Rates & Pricing / Rates & Fees
                </p>
            </div>
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search rates...">
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
                <div class="stat-icon blue">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Rates</h3>
                    <p class="stat-number"><?php echo number_format($totalRates); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-list"></i> All rate types
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Active Rates</h3>
                    <p class="stat-number"><?php echo number_format($activeRates); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Currently available
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <h3>Discounted Rates</h3>
                    <p class="stat-number"><?php echo number_format($discountedRates); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-ticket-alt"></i> Special pricing
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>Average Price</h3>
                    <p class="stat-number">₱<?php echo number_format($avgPrice, 2); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-calculator"></i> Mean rate
                    </span>
                </div>
            </div>
        </div>

        <!-- Filters and Actions -->
        <div class="content-section">
            <div class="section-header">
                <h2>Manage Rates & Fees</h2>
                <button class="btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Rate
                </button>
            </div>

            <div class="filters-container">
                <div class="filter-group">
                    <label for="appliesFilter">Applies To:</label>
                    <select id="appliesFilter" onchange="applyFilters()">
                        <option value="all" <?php echo $appliesFilter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="Guest" <?php echo $appliesFilter === 'Guest' ? 'selected' : ''; ?>>Guests</option>
                        <option value="Member" <?php echo $appliesFilter === 'Member' ? 'selected' : ''; ?>>Members</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="statusFilter">Status:</label>
                    <select id="statusFilter" onchange="applyFilters()">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="discountFilter">Type:</label>
                    <select id="discountFilter" onchange="applyFilters()">
                        <option value="all" <?php echo $discountFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="regular" <?php echo $discountFilter === 'regular' ? 'selected' : ''; ?>>Regular Rates</option>
                        <option value="discounted" <?php echo $discountFilter === 'discounted' ? 'selected' : ''; ?>>Discounted Rates</option>
                    </select>
                </div>

                <button class="btn-secondary" onclick="resetFilters()">
                    <i class="fas fa-redo"></i> Reset Filters
                </button>
            </div>

            <!-- Alert Box -->
            <div id="alertBox" class="alert" style="display: none;"></div>

            <!-- Rates Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Rate Name</th>
                            <th>Price</th>
                            <th>Applies To</th>
                            <th>Type</th>
                            <th>Discount Info</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rates) > 0): ?>
                            <?php foreach ($rates as $rate): ?>
                                <tr>
                                    <td><?php echo $rate['rate_id']; ?></td>
                                    <td>
                                        <div class="rate-name-cell">
                                            <strong><?php echo htmlspecialchars($rate['rate_name']); ?></strong>
                                            <?php if ($rate['is_discounted']): ?>
                                                <span class="badge badge-discount">
                                                    <i class="fas fa-percentage"></i> Discount
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="price-cell">
                                        ₱<?php echo number_format($rate['price'], 2); ?>
                                        <?php if ($rate['is_discounted'] && $rate['base_price']): ?>
                                            <br>
                                            <small class="original-price">
                                                <s>₱<?php echo number_format($rate['base_price'], 2); ?></s>
                                                <span class="savings">
                                                    Save ₱<?php echo number_format($rate['base_price'] - $rate['price'], 2); ?>
                                                </span>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($rate['applies_to']); ?>">
                                            <i class="fas fa-<?php echo $rate['applies_to'] === 'Guest' ? 'user' : 'user-check'; ?>"></i>
                                            <?php echo htmlspecialchars($rate['applies_to']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($rate['is_discounted']): ?>
                                            <span class="type-badge discounted">
                                                <i class="fas fa-tag"></i> Discounted
                                            </span>
                                        <?php else: ?>
                                            <span class="type-badge regular">
                                                <i class="fas fa-dollar-sign"></i> Regular
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($rate['is_discounted']): ?>
                                            <small>
                                                <strong>Type:</strong> <?php echo htmlspecialchars($rate['discount_type'] ?? 'N/A'); ?><br>
                                                <?php if ($rate['base_rate_name']): ?>
                                                    <strong>Base:</strong> <?php echo htmlspecialchars($rate['base_rate_name']); ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <span style="color: #999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $rate['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $rate['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" onclick='viewRate(<?php echo json_encode($rate); ?>)' title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-edit" onclick='editRate(<?php echo json_encode($rate); ?>)' title="Edit Rate">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-action btn-toggle" onclick="toggleStatus(<?php echo $rate['rate_id']; ?>, <?php echo $rate['is_active']; ?>)" title="Toggle Status">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                            <button class="btn-action btn-delete" onclick="deleteRate(<?php echo $rate['rate_id']; ?>, '<?php echo htmlspecialchars($rate['rate_name'], ENT_QUOTES); ?>')" title="Delete Rate">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No rates found</p>
                                    <button class="btn-primary" onclick="openAddModal()">Create Your First Rate</button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="rateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Rate</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="rateForm">
                    <input type="hidden" id="rateId" name="rate_id">
                    <input type="hidden" id="formAction" name="action" value="add">

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="rate_name">Rate Name *</label>
                            <input type="text" id="rate_name" name="rate_name" required placeholder="e.g., Walk-in Day Pass, Student Discount">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Price (₱) *</label>
                            <input type="number" id="price" name="price" step="0.01" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="applies_to">Applies To *</label>
                            <select id="applies_to" name="applies_to" required>
                                <option value="">Select...</option>
                                <option value="Guest">Guest</option>
                                <option value="Member">Member</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="is_discounted">Is Discounted? *</label>
                            <select id="is_discounted" name="is_discounted" required onchange="toggleDiscountFields()">
                                <option value="0">No - Regular Rate</option>
                                <option value="1">Yes - Discounted Rate</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="is_active">Status *</label>
                            <select id="is_active" name="is_active" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- Discount Fields (hidden by default) -->
                    <div id="discountFields" style="display: none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="base_rate_id">Base Rate</label>
                                <select id="base_rate_id" name="base_rate_id">
                                    <option value="">None</option>
                                    <?php foreach ($baseRates as $br): ?>
                                        <option value="<?php echo $br['rate_id']; ?>">
                                            <?php echo htmlspecialchars($br['rate_name']); ?> - ₱<?php echo number_format($br['price'], 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="discount_type">Discount Type</label>
                                <input type="text" id="discount_type" name="discount_type" placeholder="e.g., Student, Senior, Early Bird">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" placeholder="Brief description of this rate..."></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Save Rate
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
                <h3>Rate Details</h3>
                <button class="close-btn" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <script>
        // Pass base rates to JavaScript
        const baseRatesData = <?php echo json_encode($baseRates); ?>;
    </script>
    <script src="../js/admin-dashboard.js"></script>
    <script src="js/rates.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>