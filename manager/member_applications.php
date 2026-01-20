<?php
session_start();

// Check if user is logged in and has manager role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Manager') {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

$employeeName = $_SESSION['employee_name'] ?? 'Manager';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Applications - Empire Fitness</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/manager-components.css">
    <link rel="stylesheet" href="css/members.css">
    <link rel="stylesheet" href="css/applications.css">
    <link rel="stylesheet" href="css/button-styles.css">
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
        flex-wrap: wrap;
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

    /* Action buttons in table - prevent stacking */
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 35px;
        height: 35px;
        padding: 0;
        margin: 0 3px;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid;
        vertical-align: middle;
    }

    .btn-view {
        color: #d41c1c;
        border-color: #d41c1c;
        background-color: white;
    }

    .btn-view:hover {
        background-color: #d41c1c;
        color: white;
        box-shadow: 0 4px 12px rgba(212, 28, 28, 0.3);
        transform: translateY(-2px);
    }

    .btn-approve-small {
        color: #28a745;
        border-color: #28a745;
        background-color: white;
    }

    .btn-approve-small:hover {
        background-color: #28a745;
        color: white;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        transform: translateY(-2px);
    }

    .btn-reject-small {
        color: #dc3545;
        border-color: #dc3545;
        background-color: white;
    }

    .btn-reject-small:hover {
        background-color: #dc3545;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        transform: translateY(-2px);
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
<body>
    <!-- Notifications Container -->
    <div id="notifications"></div>
    <?php include 'includes/sidebar_navigation.php'; ?>

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
                <button id="btnReject" class="btn-outline-danger" onclick="rejectApplication()" style="display: none;">
                    <i class="fas fa-times-circle"></i> Reject
                </button>
                <button id="btnApprove" class="btn-outline-success" onclick="approveApplication()" style="display: none;">
                    <i class="fas fa-check-circle"></i> Approve & Create Account
                </button>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div id="imageModal" class="modal">
        <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
        <div class="modal-content" style="background: transparent; box-shadow: none; max-width: 100%; max-height: 100vh;">
            <img id="imageModalImg" src="" alt="Payment Proof" style="max-width: 90%; max-height: 90vh; object-fit: contain;">
        </div>
    </div>

    <!-- PDF Preview Modal -->
    <div id="pdfModal" class="modal" style="display: none;">
        <span class="image-modal-close" onclick="closePdfModal()">&times;</span>
        <div class="modal-content" style="background: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 95%; max-height: 95vh; padding: 0;">
            <iframe id="pdfModalIframe" src="" style="width: 100%; height: 85vh; border: none; border-radius: 8px;"></iframe>
        </div>
    </div>

    <script src="js/sidebar.js"></script>
    <script src="js/manager-dashboard.js"></script>
    <script src="js/applications.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>
