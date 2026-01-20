<?php
require_once __DIR__ . '/../includes/gym_activity_handler.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Recent User Activity - Empire Fitness</title>
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/gym-activity.css">
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
    <button id="sidebar-toggle" class="sidebar-toggle"><i class="fas fa-bars"></i></button>

    <!-- Sidebar (copied from your admin dashboard) -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-circle"><i class="fas fa-dumbbell"></i></div>
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
            <a href="adminDashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'adminDashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <a href="employee_management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'employee_management.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i><span>Employee Management</span>
            </a>

            <div class="nav-divider">RATES &amp; PRICING</div>

            <a href="membership_plans.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'membership_plans.php' ? 'active' : ''; ?>">
                <i class="fas fa-crown"></i><span>Membership Plans</span>
            </a>
            <a href="rates.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'rates.php' ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave"></i><span>Rates & Fees</span>
            </a>

            <div class="nav-divider">REPORTS</div>

            <a href="sales.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'sales.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i><span>Sales Reports</span>
            </a>
            <a href="total_members.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'total_members.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-friends"></i><span>Member Statistics</span>
            </a>
            <a href="gym_activity.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'gym_activity.php' ? 'active' : ''; ?>">
                <i class="fas fa-door-open"></i><span>Recent User Activity</span>
            </a>

            <div class="nav-divider">ACCOUNT</div>

            <a href="profile.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i><span>My Profile</span>
            </a>
            <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i><span>Settings</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>

    <!-- Main Content -->
     <div class="main-content" id="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1>Recent User Activity</h1>
                <p class="breadcrumb"><i class="fas fa-home"></i> Home / Activity Logs</p>
            </div>

            <div class="topbar-right">
                <!-- Filters form -->
                <form id="activityFilters" method="get" class="filter-form">
                    <div class="filter-row">
                        <select name="source" id="source" class="filter-input">
                            <option value="all" <?php if ($filter_source === 'all') echo 'selected'; ?>>All</option>
                            <option value="member" <?php if ($filter_source === 'member') echo 'selected'; ?>>Members</option>
                            <option value="coach" <?php if ($filter_source === 'coach') echo 'selected'; ?>>Coaches</option>
                        </select>

                        <select name="activity_type" id="activity_type" class="filter-input">
                            <option value="">All activity types</option>
                            <?php foreach ($activity_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type, ENT_QUOTES); ?>" <?php if ($filter_activity_type === $type) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($type, ENT_QUOTES); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input type="date" name="from" id="from" class="filter-input" value="<?php echo htmlspecialchars($filter_from); ?>" placeholder="From">
                        <input type="date" name="to" id="to" class="filter-input" value="<?php echo htmlspecialchars($filter_to); ?>" placeholder="To">

                        <input type="text" name="q" id="q" class="filter-input search-field" placeholder="Search description, IP, name..." value="<?php echo htmlspecialchars($filter_search, ENT_QUOTES); ?>">

                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a href="gym_activity.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Stats -->
        <div class="activity-stats">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-history"></i></div>
                <div class="stat-content">
                    <h3>Total Activities</h3>
                    <p class="stat-number"><?php echo (int)$totalActivities; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-content">
                    <h3>Today's Activities</h3>
                    <p class="stat-number"><?php echo (int)$activitiestoday; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-content">
                    <h3>Member Activities</h3>
                    <p class="stat-number"><?php echo (int)$memberCount; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stat-content">
                    <h3>Coach Activities</h3>
                    <p class="stat-number"><?php echo (int)$coachCount; ?></p>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="activity-container">
            <div class="activity-section">
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> Activity Results</h2>
                    <span class="activity-count"><?php echo (int)$totalActivities; ?> records</span>
                </div>

                <div class="activity-list" id="activityList">
                    <?php if (!empty($activities)): ?>
                        <?php foreach ($activities as $a): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $a['source'] === 'coach' ? 'coach' : 'member'; ?>">
                                    <i class="<?php echo $a['source'] === 'coach' ? 'fas fa-user-tie' : 'fas fa-user'; ?>"></i>
                                </div>
                                <div class="activity-body">
                                    <div class="activity-header">
                                        <span class="activity-user"><strong><?php echo htmlspecialchars($a['actor_name'], ENT_QUOTES); ?></strong></span>
                                        <span class="activity-badge"><?php echo htmlspecialchars($a['activity_type'], ENT_QUOTES); ?></span>
                                    </div>

                                    <?php if (!empty($a['description'])): ?>
                                        <p class="activity-description"><?php echo htmlspecialchars($a['description'], ENT_QUOTES); ?></p>
                                    <?php endif; ?>

                                    <div class="activity-meta">
                                        <span class="meta-item"><i class="far fa-clock"></i> <?php echo htmlspecialchars(date('M d, Y - H:i:s', strtotime($a['created_at'])), ENT_QUOTES); ?></span>
                                        <?php if (!empty($a['ip_address'])): ?>
                                            <span class="meta-item"><i class="fas fa-globe"></i> <?php echo htmlspecialchars($a['ip_address'], ENT_QUOTES); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($a['user_agent'])): ?>
                                            <span class="meta-item"><i class="fas fa-mobile-alt"></i> <?php echo htmlspecialchars(strlen($a['user_agent']) > 60 ? substr($a['user_agent'],0,60).'...' : $a['user_agent'], ENT_QUOTES); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="activity-empty">
                            <i class="fas fa-inbox"></i>
                            <p>No activity matches your filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <script src="js/admin-dashboard.js"></script>
    <script src="js/gym-activity.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>