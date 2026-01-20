<?php
session_start();

// Check if user is logged in and has manager role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Manager') {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

// Fetch membership plans for the form selector
$membershipPlans = [];
$basePlan = null;
$monthlyRegularPlan = null;
$monthlyStudentPlan = null;

try {
    $plansStmt = $conn->prepare("SELECT membership_id, plan_name, monthly_fee, is_base_membership FROM memberships WHERE status = 'Active' ORDER BY is_base_membership DESC, plan_name ASC");
    $plansStmt->execute();
    $allPlans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize plans
    foreach ($allPlans as $plan) {
        if ($plan['is_base_membership']) {
            $basePlan = $plan;
        } elseif (stripos($plan['plan_name'], 'student') !== false) {
            $monthlyStudentPlan = $plan;
        } elseif (stripos($plan['plan_name'], 'regular') !== false) {
            $monthlyRegularPlan = $plan;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching membership plans: " . $e->getMessage());
}

$employeeName = $_SESSION['employee_name'] ?? 'Manager';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members Management - Empire Fitness</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/manager-components.css">
    <link rel="stylesheet" href="css/members.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="../css/realtime-notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <button class="filter-btn" onclick="filterMembers('pending_payment')">
                <i class="fas fa-credit-card"></i> <span id="pending-count-badge" style="background: #ff6b6b; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; margin-left: 5px;">0</span> Pending Payment Verification
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
                        <th>Membership</th>
                        <th>Renewal Status</th>
                        <th>Payment Status</th>
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
                        <td colspan="14" class="no-data">
                            <i class="fas fa-spinner fa-spin" style="font-size: 48px; opacity: 0.3;"></i>
                            <p>Loading members...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
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
            <div class="modal-header modern-header">
                <div class="header-left">
                    <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add Member</h3>
                    <p class="header-subtitle">Fill in the member's information below</p>
                </div>
                <button class="modal-close" onclick="closeMemberModal()">&times;</button>
            </div>
            <div class="modal-body modern-form-body">
                <form id="memberForm">
                    <input type="hidden" id="memberId" name="client_id">
                    <input type="hidden" id="formAction" name="action" value="add">
                    
                    <!-- Step 1: Personal Information -->
                    <div class="form-section modern-section">
                        <div class="section-header">
                            <div class="section-icon"><i class="fas fa-user-circle"></i></div>
                            <div class="section-info">
                                <h4>Personal Information</h4>
                                <p>Basic details about the member</p>
                            </div>
                        </div>
                        <div class="form-content">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name">First Name <span class="required">*</span></label>
                                    <input type="text" id="first_name" name="first_name" placeholder="Enter first name" required>
                                </div>
                                <div class="form-group">
                                    <label for="middle_name">Middle Name</label>
                                    <input type="text" id="middle_name" name="middle_name" placeholder="Optional">
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Last Name <span class="required">*</span></label>
                                    <input type="text" id="last_name" name="last_name" placeholder="Enter last name" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" placeholder="member@example.com">
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" placeholder="+63 9XX XXXX XXX" maxlength="20">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="birthdate">Birthdate</label>
                                    <input type="date" id="birthdate" name="birthdate">
                                    <small class="form-hint">Must be 18 years or older</small>
                                </div>
                                <div class="form-group">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                        <option value="prefer-not">Prefer Not to Say</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="address">Address</label>
                                    <textarea id="address" name="address" placeholder="Enter street address" rows="2"></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label for="referral_source">How did you hear about us?</label>
                                    <input type="text" id="referral_source" name="referral_source" placeholder="e.g., Friend, Social Media, Google, etc.">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Membership Plan -->
                    <div class="form-section modern-section">
                        <div class="section-header">
                            <div class="section-icon"><i class="fas fa-credit-card"></i></div>
                            <div class="section-info">
                                <h4>Membership Plan</h4>
                                <p>Choose the membership type for this member</p>
                            </div>
                        </div>
                        <div class="form-content">
                            <div class="membership-plans-grid">
                                <label class="plan-option">
                                    <input type="radio" name="membership_plan" value="none" checked style="display: none;">
                                    <div class="plan-card">
                                        <div class="plan-icon"><i class="fas fa-dumbbell"></i></div>
                                        <h5>Base Membership</h5>
                                        <p>Standard gym access</p>
                                        <span class="plan-price">Included</span>
                                    </div>
                                </label>
                                
                                <label class="plan-option">
                                    <input type="radio" name="membership_plan" value="regular" style="display: none;">
                                    <div class="plan-card">
                                        <div class="plan-icon"><i class="fas fa-star"></i></div>
                                        <h5>Regular Monthly</h5>
                                        <p>Access + Monthly</p>
                                        <span class="plan-price">₱1,000</span>
                                    </div>
                                </label>
                                
                                <label class="plan-option">
                                    <input type="radio" name="membership_plan" value="student" style="display: none;">
                                    <div class="plan-card">
                                        <div class="plan-icon"><i class="fas fa-graduation-cap"></i></div>
                                        <h5>Student Monthly</h5>
                                        <p>Access + Monthly</p>
                                        <span class="plan-price">₱800</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Health Information -->
                    <div class="form-section modern-section">
                        <div class="section-header">
                            <div class="section-icon"><i class="fas fa-heartbeat"></i></div>
                            <div class="section-info">
                                <h4>Health Information</h4>
                                <p>Important for personalized fitness guidance</p>
                            </div>
                        </div>
                        <div class="form-content">
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="medical_conditions">Medical Conditions</label>
                                    <textarea id="medical_conditions" name="medical_conditions" placeholder="List any medical conditions, injuries, or allergies..." rows="3"></textarea>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="fitness_goals">Fitness Goals</label>
                                    <textarea id="fitness_goals" name="fitness_goals" placeholder="What are the member's fitness goals?" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Assessment Scheduling -->
                    <div class="form-section modern-section">
                        <div class="section-header">
                            <div class="section-icon"><i class="fas fa-calendar-check"></i></div>
                            <div class="section-info">
                                <h4>Initial Assessment</h4>
                                <p>Schedule a fitness assessment</p>
                            </div>
                        </div>
                        <div class="form-content">
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="schedule_assessment" name="schedule_assessment" value="1">
                                        <span class="checkmark"></span>
                                        <span class="checkbox-text">Schedule Initial Assessment for this member</span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-row" id="assessment_date_group" style="display: none;">
                                <div class="form-group">
                                    <label for="assessment_date">Preferred Assessment Date</label>
                                    <input type="date" id="assessment_date" name="assessment_date">
                                </div>
                                <div class="form-group">
                                    <label for="preferred_schedule">Preferred Time</label>
                                    <input type="text" id="preferred_schedule" name="preferred_schedule" placeholder="e.g., Morning, Evening, Weekends">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer modern-footer">
                        <button type="button" onclick="closeMemberModal()" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" onclick="showPreviewModal()" class="btn-primary">
                            <i class="fas fa-eye"></i> Review & Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview/Confirmation Modal -->
    <div id="previewModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle"></i> Review Member Information</h3>
                <button class="modal-close" onclick="closePreviewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="preview-content">
                    <div class="preview-section">
                        <h4><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="preview-grid">
                            <div class="preview-item">
                                <span class="preview-label">First Name</span>
                                <span class="preview-value" id="preview_first_name">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Middle Name</span>
                                <span class="preview-value" id="preview_middle_name">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Last Name</span>
                                <span class="preview-value" id="preview_last_name">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Email</span>
                                <span class="preview-value" id="preview_email">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Phone</span>
                                <span class="preview-value" id="preview_phone">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Birthdate</span>
                                <span class="preview-value" id="preview_birthdate">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Gender</span>
                                <span class="preview-value" id="preview_gender">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Referral Source</span>
                                <span class="preview-value" id="preview_referral_source">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="preview-section">
                        <h4><i class="fas fa-credit-card"></i> Membership Plan</h4>
                        <div class="preview-grid">
                            <div class="preview-item">
                                <span class="preview-label">Membership Plan</span>
                                <span class="preview-value" id="preview_membership_plan">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="preview-section">
                        <h4><i class="fas fa-heartbeat"></i> Health Information</h4>
                        <div class="preview-grid">
                            <div class="preview-item">
                                <span class="preview-label">Medical Conditions</span>
                                <span class="preview-value" id="preview_medical_conditions">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Fitness Goals</span>
                                <span class="preview-value" id="preview_fitness_goals">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="preview-section" id="preview_assessment_section" style="display: none;">
                        <h4><i class="fas fa-calendar-check"></i> Assessment Information</h4>
                        <div class="preview-grid">
                            <div class="preview-item">
                                <span class="preview-label">Preferred Assessment Date</span>
                                <span class="preview-value" id="preview_assessment_date">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Preferred Schedule</span>
                                <span class="preview-value" id="preview_preferred_schedule">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="preview-notice">
                        <i class="fas fa-info-circle"></i>
                        <p><strong>Note:</strong> A temporary password will be generated and sent to the member's email address. The member can change it after first login.</p>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" onclick="closePreviewModal()" class="btn-secondary">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button type="button" onclick="confirmAddMember()" class="btn-primary">
                        <i class="fas fa-check"></i> Confirm & Send Credentials
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content modal-medium" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); max-width: 450px;">
            <div class="modal-header" style="border-bottom: none;">
                <h3 style="color: white; margin: 0;"><i class="fas fa-exclamation-triangle"></i> Delete Member</h3>
                <button class="modal-close" onclick="closeDeleteModal()" style="color: white; background: transparent; border: none; font-size: 28px; cursor: pointer; padding: 0;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 30px; text-align: center; background: inherit;">
                <div style="font-size: 48px; color: #ff6b6b; margin-bottom: 20px;">
                    <i class="fas fa-user-slash"></i>
                </div>
                <h4 style="color: white; margin: 0 0 10px 0;">Delete <span id="deleteMemberName" style="font-weight: bold;"></span>?</h4>
                <p style="color: rgba(255,255,255,0.9); margin: 15px 0;">
                    This member will be permanently removed from the system. This action cannot be undone.
                </p>
                <div style="background: rgba(255,107,107,0.1); border-left: 4px solid #ff6b6b; padding: 15px; border-radius: 4px; margin: 20px 0; text-align: left;">
                    <p style="color: rgba(255,255,255,0.9); margin: 0; font-size: 13px;">
                        <strong>Warning:</strong> All associated data (memberships, bookings, assessments) will also be deleted.
                    </p>
                </div>
            </div>
            <div class="modal-footer" style="background: rgba(0,0,0,0.1); border-top: 1px solid rgba(255,255,255,0.1); padding: 15px 20px; display: flex; gap: 10px; justify-content: center;">
                <button type="button" onclick="closeDeleteModal()" class="btn-secondary" style="background: rgba(255,255,255,0.2); color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" onclick="confirmDeleteMember()" class="btn-danger" style="background: #ff6b6b; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-trash"></i> Delete Member
                </button>
            </div>
        </div>
    </div>

    <!-- Membership Payment Verification Modal -->
    <div id="paymentVerificationModal" class="modal">
        <div class="modal-content modal-large" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-credit-card"></i> Membership Payment Verification</h3>
                <button class="modal-close" onclick="closePaymentVerificationModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="background: #f0f8ff; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #2196F3;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <small style="color: #666; font-weight: 600;">MEMBER</small>
                            <p style="margin: 5px 0 0 0; font-size: 16px; color: #000; font-weight: 500;" id="verify-member-name">-</p>
                        </div>
                        <div>
                            <small style="color: #666; font-weight: 600;">MEMBERSHIP PLAN</small>
                            <p style="margin: 5px 0 0 0; font-size: 16px; color: #000; font-weight: 500;" id="verify-plan-name">-</p>
                        </div>
                    </div>
                </div>

                <div style="background: #fff3cd; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div>
                            <small style="color: #666; font-weight: 600;">PAYMENT DATE</small>
                            <p style="margin: 5px 0 0 0; font-size: 16px; color: #000;" id="verify-payment-date">-</p>
                        </div>
                        <div>
                            <small style="color: #666; font-weight: 600;">AMOUNT</small>
                            <p style="margin: 5px 0 0 0; font-size: 16px; color: #000; font-weight: 700; color: #27ae60;" id="verify-amount">₱0.00</p>
                        </div>
                        <div>
                            <small style="color: #666; font-weight: 600;">PAYMENT METHOD</small>
                            <p style="margin: 5px 0 0 0; font-size: 16px; color: #000;" id="verify-payment-method">-</p>
                        </div>
                    </div>
                </div>

                <div style="background: #f5f5f5; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                    <div style="margin-bottom: 10px;">
                        <small style="color: #666; font-weight: 600;">MEMBERSHIP PERIOD</small>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #000;" id="verify-membership-period">-</p>
                    </div>
                    <div>
                        <small style="color: #666; font-weight: 600;">REFERENCE ID</small>
                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #666; font-family: monospace;" id="verify-reference-id">-</p>
                    </div>
                </div>

                <form id="paymentVerificationForm" onsubmit="submitPaymentVerification(event)">
                    <div class="form-group">
                        <label for="verify-remarks" style="font-weight: 600;">Verification Remarks (Required for rejection)</label>
                        <textarea id="verify-remarks" class="form-input" placeholder="Enter remarks or reason for rejection..." style="min-height: 80px;"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closePaymentVerificationModal()" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" onclick="rejectMembershipPayment()" class="btn-danger">
                    <i class="fas fa-times-circle"></i> Reject Payment
                </button>
                <button type="button" onclick="approveMembershipPayment()" class="btn-success">
                    <i class="fas fa-check-circle"></i> Approve Payment
                </button>
            </div>
        </div>
    </div>

    <script src="js/sidebar.js"></script>
    <script src="js/manager-dashboard.js"></script>
    <script src="js/members.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>
