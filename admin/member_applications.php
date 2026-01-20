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
    <title>Member Applications - Empire Fitness</title>
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/employee-management.css">
    <link rel="stylesheet" href="css/members.css">
    <link rel="stylesheet" href="css/applications.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="../css/realtime-notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 2% auto;
        padding: 0;
        border: 1px solid #888;
        width: 90%;
        max-width: 1000px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .modal-header {
        padding: 20px;
        background: linear-gradient(135deg, #d41c1c 0%, #a81616 100%);
        color: white;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 20px;
    }

    .modal-close {
        color: white;
        font-size: 28px;
        font-weight: bold;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        line-height: 30px;
    }

    .modal-close:hover,
    .modal-close:focus {
        color: #ffcccc;
    }

    .modal-body {
        padding: 20px;
        max-height: 70vh;
        overflow-y: auto;
    }

    .modal-footer {
        padding: 15px 20px;
        background-color: #f8f9fa;
        border-radius: 0 0 8px 8px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    /* Application details grid */
    .application-details-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 25px;
        margin-top: 20px;
    }

    .application-detail-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #d41c1c;
    }

    .application-detail-section h4 {
        color: #d41c1c;
        margin: 0 0 15px 0;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 16px;
    }

    .detail-grid {
        display: grid;
        gap: 12px;
    }

    .detail-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #dee2e6;
        gap: 15px;
    }

    .detail-item:last-child {
        border-bottom: none;
    }

    .detail-item label {
        font-weight: 600;
        color: #6c757d;
        min-width: 140px;
    }

    .detail-item span {
        color: #2c3e50;
        font-weight: 500;
        text-align: right;
        flex: 1;
    }

    .payment-proof-container {
        margin-top: 10px;
    }

    .payment-proof-image {
        max-width: 100%;
        border-radius: 5px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: transform 0.2s;
    }

    .payment-proof-image:hover {
        transform: scale(1.02);
    }

    .payment-proof-pdf {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 15px;
        background: white;
        border-radius: 5px;
        border: 1px solid #ddd;
    }

    .payment-proof-pdf i {
        font-size: 32px;
        color: #d32f2f;
    }

    .payment-proof-pdf a {
        color: #d41c1c;
        text-decoration: none;
        font-weight: 500;
    }

    .payment-proof-pdf a:hover {
        text-decoration: underline;
    }

    .no-payment-proof {
        color: #999;
        font-style: italic;
        padding: 20px;
        text-align: center;
    }

    .alert {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        max-width: 400px;
        animation: slideIn 0.3s ease;
        white-space: pre-line;
    }

    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border-left: 4px solid #28a745;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border-left: 4px solid #dc3545;
    }

    .alert-warning {
        background: #fff3cd;
        color: #856404;
        border-left: 4px solid #ffc107;
    }

    @media (max-width: 1200px) {
        .application-details-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .detail-item {
            flex-direction: column;
            gap: 5px;
        }
        
        .detail-item label {
            min-width: auto;
        }
        
        .detail-item span {
            text-align: left;
        }
    }
    </style>
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
                <h1>Member Applications</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Member Applications
                </p>
            </div>
        </div>

        <!-- Alert Box -->
        <div id="alertBox" class="alert-box"></div>

        <!-- Stats Summary -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>Pending Applications</h3>
                    <p class="stat-number" id="pendingCount">0</p>
                    <span class="stat-change neutral">
                        <i class="fas fa-hourglass-half"></i> Awaiting review
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Approved This Month</h3>
                    <p class="stat-number" id="approvedCount">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Accounts created
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Rejected</h3>
                    <p class="stat-number" id="rejectedCount">0</p>
                    <span class="stat-change negative">
                        <i class="fas fa-ban"></i> Declined applications
                    </span>
                </div>
            </div>
        </div>

        <!-- Controls Section -->
        <div class="controls-section">
            <div class="controls-left">
                <button onclick="window.location.href='members.php'" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Members
                </button>
                <button onclick="loadApplications()" class="btn-primary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <div class="controls-right">
                <select id="statusFilter" onchange="filterApplications()" class="filter-select">
                    <option value="all">All Applications</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <div class="search-box-inline">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search applications..." onkeyup="searchApplications()">
                </div>
            </div>
        </div>

        <!-- Applications Table -->
        <div class="table-container">
            <table class="employees-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Membership Plan</th>
                        <th>Amount</th>
                        <th>Applied Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="applicationsTableBody">
                    <tr>
                        <td colspan="9" class="no-data">
                            <i class="fas fa-spinner fa-spin" style="font-size: 48px; opacity: 0.3;"></i>
                            <p>Loading applications...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- View Application Modal -->
    <div id="viewApplicationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> Application Details</h3>
                <button class="modal-close" onclick="closeApplicationModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="currentApplicationId">
                
                <div class="application-details-grid">
                    <!-- Personal Information -->
                    <div class="application-detail-section">
                        <h4><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Full Name:</label>
                                <span id="detailName"></span>
                            </div>
                            <div class="detail-item">
                                <label>Email:</label>
                                <span id="detailEmail"></span>
                            </div>
                            <div class="detail-item">
                                <label>Contact Number:</label>
                                <span id="detailPhone"></span>
                            </div>
                            <div class="detail-item">
                                <label>Birthdate:</label>
                                <span id="detailBirthdate"></span>
                            </div>
                            <div class="detail-item">
                                <label>Gender:</label>
                                <span id="detailGender"></span>
                            </div>
                            <div class="detail-item" style="grid-column: 1 / -1;">
                                <label>Address:</label>
                                <span id="detailAddress"></span>
                            </div>
                            <div class="detail-item" id="detailReferralContainer" style="grid-column: 1 / -1; display: none;">
                                <label>How they found us:</label>
                                <span id="detailReferral"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Membership & Payment -->
                    <div class="application-detail-section">
                        <h4><i class="fas fa-credit-card"></i> Membership & Payment</h4>
                        <div class="detail-grid">
                            <div class="detail-item" style="grid-column: 1 / -1;">
                                <label>Membership Plan:</label>
                                <span id="detailPlan"></span>
                            </div>
                            <div class="detail-item">
                                <label>Total Amount:</label>
                                <span id="detailAmount" style="font-weight: 600; color: #28a745;"></span>
                            </div>
                            <div class="detail-item">
                                <label>Payment Method:</label>
                                <span id="detailPaymentMethod"></span>
                            </div>
                            <div class="detail-item">
                                <label>Reference Number:</label>
                                <span id="detailReference"></span>
                            </div>
                            <div class="detail-item">
                                <label>Payment Date:</label>
                                <span id="detailPaymentDate"></span>
                            </div>
                            <div class="detail-item" id="detailPaymentNotesContainer" style="grid-column: 1 / -1; display: none;">
                                <label>Payment Notes:</label>
                                <span id="detailPaymentNotes"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Application Status -->
                    <div class="application-detail-section">
                        <h4><i class="fas fa-info-circle"></i> Application Status</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Status:</label>
                                <span id="detailStatus" class="status-badge"></span>
                            </div>
                            <div class="detail-item">
                                <label>Submitted:</label>
                                <span id="detailAppliedDate"></span>
                            </div>
                            <div class="detail-item" id="detailVerifiedContainer" style="display: none;">
                                <label>Verified By:</label>
                                <span id="detailVerifiedBy"></span>
                            </div>
                            <div class="detail-item" id="detailVerifiedAtContainer" style="display: none;">
                                <label>Verified At:</label>
                                <span id="detailVerifiedAt"></span>
                            </div>
                            <div class="detail-item" id="detailRejectionContainer" style="grid-column: 1 / -1; display: none;">
                                <label>Rejection Reason:</label>
                                <span id="detailRejectionReason" style="color: #dc3545;"></span>
                            </div>
                            <div class="detail-item" id="detailClientIdContainer" style="display: none;">
                                <label>Client ID:</label>
                                <span id="detailClientId"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Proof -->
                    <div class="application-detail-section">
                        <h4><i class="fas fa-file-image"></i> Payment Proof</h4>
                        <div id="paymentProofContainer">
                            <div class="no-payment-proof">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeApplicationModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button id="btnReject" class="btn-danger" onclick="rejectApplication()">
                    <i class="fas fa-times-circle"></i> Reject
                </button>
                <button id="btnApprove" class="btn-success" onclick="approveApplication()">
                    <i class="fas fa-check-circle"></i> Approve & Create Account
                </button>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div id="imageModal" class="modal">
        <span class="modal-close" onclick="closeImageModal()" style="position: absolute; top: 20px; right: 40px; font-size: 40px; color: white; cursor: pointer; z-index: 10001;">&times;</span>
        <div class="modal-content" style="background: transparent; box-shadow: none; max-width: 90%; max-height: 90vh;">
            <img id="imageModalImg" src="" alt="Payment Proof" style="width: 100%; height: auto; border-radius: 8px;">
        </div>
    </div>

    <script src="js/admin-dashboard.js"></script>
    <script src="js/applications.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>