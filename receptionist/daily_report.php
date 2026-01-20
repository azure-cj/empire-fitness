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
    <title>Daily Report - Empire Fitness</title>
    <link rel="stylesheet" href="css/receptionist-dashboard.css">
    <link rel="stylesheet" href="css/daily-report.css">
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
                <h1>Daily Report</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Reports / Daily Report
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
            <!-- Date Selector and Filters -->
            <div class="report-header">
                <div class="date-picker-group">
                    <label for="report-date">Select Date:</label>
                    <input type="date" id="report-date" class="date-input">
                </div>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="generateReport()">
                        <i class="fas fa-refresh"></i> Generate Report
                    </button>
                    <button class="btn btn-secondary" onclick="exportReport()">
                        <i class="fas fa-download"></i> Export PDF
                    </button>
                </div>
            </div>

            <!-- Report Tabs -->
            <div class="report-tabs">
                <button class="tab-btn active" onclick="switchTab('summary')">
                    <i class="fas fa-chart-pie"></i> Summary
                </button>
                <button class="tab-btn" onclick="switchTab('attendance')">
                    <i class="fas fa-user-check"></i> Attendance
                </button>
                <button class="tab-btn" onclick="switchTab('payments')">
                    <i class="fas fa-credit-card"></i> Payments
                </button>
                <button class="tab-btn" onclick="switchTab('pos')">
                    <i class="fas fa-cash-register"></i> POS Report
                </button>
                <button class="tab-btn" onclick="switchTab('classes')">
                    <i class="fas fa-dumbbell"></i> Classes
                </button>
            </div>

            <!-- Summary Tab -->
            <div id="summary-tab" class="tab-content active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Total Entries</h3>
                            <p class="stat-number"><span id="total-entries">0</span></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Total Revenue</h3>
                            <p class="stat-number"><span id="total-revenue">₱0.00</span></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-dumbbell"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Classes Held</h3>
                            <p class="stat-number"><span id="classes-held">0</span></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Members Attended</h3>
                            <p class="stat-number"><span id="members-attended">0</span></p>
                        </div>
                    </div>
                </div>

                <div class="summary-charts">
                    <div class="chart-container">
                        <h3>Revenue by Payment Method</h3>
                        <canvas id="payment-method-chart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h3>Entries by Type</h3>
                        <canvas id="entry-type-chart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Attendance Tab -->
            <div id="attendance-tab" class="tab-content">
                <div class="table-container">
                    <div class="table-header">
                        <h3>Daily Attendance</h3>
                        <input type="text" id="attendance-search" placeholder="Search members..." class="search-input">
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Time In</th>
                                <th>Member Name</th>
                                <th>Type</th>
                                <th>Discount</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="attendance-body">
                            <tr><td colspan="6" class="text-center text-muted">Loading attendance data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payments Tab -->
            <div id="payments-tab" class="tab-content">
                <div class="table-container">
                    <div class="table-header">
                        <h3>Daily Payments</h3>
                        <input type="text" id="payment-search" placeholder="Search payments..." class="search-input">
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Member/Guest</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="payments-body">
                            <tr><td colspan="6" class="text-center text-muted">Loading payment data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- POS Report Tab -->
            <div id="pos-tab" class="tab-content">
                <div class="table-container">
                    <div class="table-header">
                        <h3>POS Summary</h3>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div style="font-size: 12px; color: #6c757d; margin-bottom: 5px;">Total Sales</div>
                            <div style="font-size: 24px; font-weight: bold; color: #2c3e50;">₱<span id="pos-total-sales">0.00</span></div>
                        </div>
                        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div style="font-size: 12px; color: #6c757d; margin-bottom: 5px;">Cash</div>
                            <div style="font-size: 24px; font-weight: bold; color: #27ae60;">₱<span id="pos-cash-total">0.00</span></div>
                        </div>
                        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div style="font-size: 12px; color: #6c757d; margin-bottom: 5px;">Transactions</div>
                            <div style="font-size: 24px; font-weight: bold; color: #9b59b6;"><span id="pos-transaction-count">0</span></div>
                        </div>
                        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div style="font-size: 12px; color: #6c757d; margin-bottom: 5px;">Sessions</div>
                            <div style="font-size: 24px; font-weight: bold; color: #3498db;"><span id="pos-session-count">0</span></div>
                        </div>
                    </div>
                </div>
                <div class="table-container" style="margin-top: 20px;">
                    <div class="table-header">
                        <h3>POS Transactions</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Receipt</th>
                                <th>Type</th>
                                <th>Client</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Receptionist</th>
                            </tr>
                        </thead>
                        <tbody id="pos-transactions-body">
                            <tr><td colspan="7" class="text-center text-muted">Loading POS data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Classes Tab -->
            <div id="classes-tab" class="tab-content">
                <div class="table-container">
                    <div class="table-header">
                        <h3>Classes Conducted Today</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Class Name</th>
                                <th>Coach</th>
                                <th>Attendees</th>
                                <th>Capacity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="classes-body">
                            <tr><td colspan="6" class="text-center text-muted">Loading class data...</td></tr>
                        </tbody>
                    </table>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="js/receptionist-dashboard.js"></script>
    <script src="js/daily-report.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>
