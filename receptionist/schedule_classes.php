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
    <title>Class Schedule - Empire Fitness</title>
    <link rel="stylesheet" href="css/receptionist-dashboard.css">
    <link rel="stylesheet" href="css/schedule-classes.css">
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
                <h1>Class Schedule</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Scheduling / Class Schedule
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
            <!-- Calendar Controls -->
            <div class="calendar-controls">
                <div class="view-controls">
                    <button class="view-btn active" data-view="month" onclick="changeView('month')">
                        <i class="fas fa-calendar"></i> Month
                    </button>
                    <button class="view-btn" data-view="week" onclick="changeView('week')">
                        <i class="fas fa-calendar-week"></i> Week
                    </button>
                    <button class="view-btn" data-view="day" onclick="changeView('day')">
                        <i class="fas fa-calendar-day"></i> Day
                    </button>
                    <button class="view-btn" data-view="list" onclick="changeView('list')">
                        <i class="fas fa-list"></i> List
                    </button>
                </div>

                <div class="date-navigation">
                    <button class="nav-btn" onclick="navigateDate('prev')">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="today-btn" onclick="goToToday()">Today</button>
                    <h2 class="current-date" id="current-date">December 2024</h2>
                    <button class="nav-btn" onclick="navigateDate('next')">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <div class="filter-controls">
                    <select id="class-type-filter" class="filter-select" onchange="filterClasses()">
                        <option value="">All Classes</option>
                        <option value="Boxing">Boxing</option>
                        <option value="Kickboxing">Kickboxing</option>
                        <option value="Muay Thai">Muay Thai</option>
                        <option value="Zumba">Zumba</option>
                        <option value="HIIT">HIIT</option>
                        <option value="Other">Other</option>
                    </select>
                    <button class="btn btn-primary" onclick="openLegendModal()">
                        <i class="fas fa-info-circle"></i> Legend
                    </button>
                </div>
            </div>

            <!-- Calendar View -->
            <div class="calendar-container">
                <!-- Month View -->
                <div class="calendar-view month-view active" id="month-view">
                    <div class="calendar-header">
                        <div class="day-label">Sun</div>
                        <div class="day-label">Mon</div>
                        <div class="day-label">Tue</div>
                        <div class="day-label">Wed</div>
                        <div class="day-label">Thu</div>
                        <div class="day-label">Fri</div>
                        <div class="day-label">Sat</div>
                    </div>
                    <div class="calendar-grid" id="month-grid">
                        <!-- Calendar cells will be generated by JavaScript -->
                    </div>
                </div>

                <!-- Week View -->
                <div class="calendar-view week-view" id="week-view">
                    <div class="week-header">
                        <div class="time-column">Time</div>
                        <div class="day-columns" id="week-day-columns"></div>
                    </div>
                    <div class="week-grid" id="week-grid">
                        <!-- Week schedule will be generated by JavaScript -->
                    </div>
                </div>

                <!-- Day View -->
                <div class="calendar-view day-view" id="day-view">
                    <div class="day-schedule" id="day-schedule">
                        <!-- Day schedule will be generated by JavaScript -->
                    </div>
                </div>

                <!-- List View -->
                <div class="calendar-view list-view active" id="list-view">
                    <div class="list-header">
                        <input type="text" id="search-classes" class="search-input" placeholder="Search classes, coach, type...">
                        <select id="filter-type" class="filter-select" onchange="filterClassesList()">
                            <option value="">All Types</option>
                            <option value="Boxing">Boxing</option>
                            <option value="Kickboxing">Kickboxing</option>
                            <option value="Muay Thai">Muay Thai</option>
                            <option value="Zumba">Zumba</option>
                            <option value="HIIT">HIIT</option>
                            <option value="Other">Other</option>
                        </select>
                        <select id="filter-status" class="filter-select" onchange="filterClassesList()">
                            <option value="">All Status</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="list-container" id="list-container">
                        <!-- List of classes will be generated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="schedule-stats">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Today's Classes</h3>
                        <p class="stat-number"><span id="today-classes">0</span></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Bookings</h3>
                        <p class="stat-number"><span id="total-bookings">0</span></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Upcoming This Week</h3>
                        <p class="stat-number"><span id="week-classes">0</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Class Details Modal -->
    <div class="modal" id="class-modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>
                        <i class="fas fa-dumbbell"></i>
                        <span id="modal-class-name">Class Details</span>
                    </h3>
                    <button class="modal-close" onclick="closeClassModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="class-details-section">
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-tag"></i> Class Type:</span>
                            <span class="detail-value" id="modal-class-type">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-user-tie"></i> Coach:</span>
                            <span class="detail-value" id="modal-coach-name">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-calendar"></i> Date:</span>
                            <span class="detail-value" id="modal-date">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-clock"></i> Time:</span>
                            <span class="detail-value" id="modal-time">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-hourglass-half"></i> Duration:</span>
                            <span class="detail-value" id="modal-duration">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-map-marker-alt"></i> Location:</span>
                            <span class="detail-value" id="modal-location">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-users"></i> Capacity:</span>
                            <span class="detail-value">
                                <span id="modal-bookings">0</span> / <span id="modal-capacity">0</span>
                                <span class="capacity-badge" id="capacity-status"></span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-info-circle"></i> Status:</span>
                            <span class="detail-value" id="modal-status">-</span>
                        </div>
                    </div>

                    <hr class="modal-divider">

                    <div class="description-section">
                        <h4><i class="fas fa-align-left"></i> Description</h4>
                        <p id="modal-description">No description available</p>
                    </div>

                    <div class="participants-section">
                        <h4><i class="fas fa-users"></i> Participants (<span id="participants-count">0</span>)</h4>
                        <div class="participants-list" id="participants-list">
                            <p class="text-muted">Loading participants...</p>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeClassModal()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Legend Modal -->
    <div class="modal" id="legend-modal">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>
                        <i class="fas fa-info-circle"></i>
                        Class Legend
                    </h3>
                    <button class="modal-close" onclick="closeLegendModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="legend-section">
                        <h4>Class Types</h4>
                        <div class="legend-item">
                            <span class="legend-color boxing"></span>
                            <span>Boxing</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color kickboxing"></span>
                            <span>Kickboxing</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color muaythai"></span>
                            <span>Muay Thai</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color zumba"></span>
                            <span>Zumba</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color hiit"></span>
                            <span>HIIT</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color other"></span>
                            <span>Other</span>
                        </div>
                    </div>

                    <hr>

                    <div class="legend-section">
                        <h4>Status Indicators</h4>
                        <div class="legend-item">
                            <span class="status-indicator scheduled"></span>
                            <span>Scheduled</span>
                        </div>
                        <div class="legend-item">
                            <span class="status-indicator completed"></span>
                            <span>Completed</span>
                        </div>
                        <div class="legend-item">
                            <span class="status-indicator cancelled"></span>
                            <span>Cancelled</span>
                        </div>
                    </div>

                    <hr>

                    <div class="legend-section">
                        <h4>Capacity Status</h4>
                        <div class="legend-item">
                            <span class="capacity-badge available">Available</span>
                            <span>Spots Available</span>
                        </div>
                        <div class="legend-item">
                            <span class="capacity-badge filling">Filling Up</span>
                            <span>75%+ Full</span>
                        </div>
                        <div class="legend-item">
                            <span class="capacity-badge full">Full</span>
                            <span>At Capacity</span>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="closeLegendModal()">Got it</button>
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

    <script src="js/receptionist-dashboard.js"></script>
    <script src="js/schedule-classes.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>