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

// Fetch statistics
try {
    $totalCoaches = $conn->query("SELECT COUNT(*) FROM employees WHERE role = 'Coach' AND status = 'Active'")->fetchColumn();
    $scheduledToday = $conn->query("SELECT COUNT(DISTINCT cs.class_id) FROM class_schedules cs WHERE cs.schedule_date = CURDATE() AND cs.status = 'Scheduled'")->fetchColumn();
    $totalClasses = $conn->query("SELECT COUNT(*) FROM classes WHERE status = 'Active'")->fetchColumn();
    $totalSchedules = $conn->query("SELECT COUNT(*) FROM class_schedules WHERE status = 'Scheduled'")->fetchColumn();
} catch (Exception $e) {
    $totalCoaches = 0;
    $scheduledToday = 0;
    $totalClasses = 0;
    $totalSchedules = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Schedules - Empire Fitness</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/manager-components.css">
    <link rel="stylesheet" href="css/coach-schedules.css">
    <link rel="stylesheet" href="css/button-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar_navigation.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <h1>Class Management & Scheduling</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Scheduling / Coach Schedules
                </p>
            </div>
        </div>

        <!-- Alert Box -->
        <div id="alertBox" class="alert" style="display: none;"></div>

        <!-- Stats Summary -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Coaches</h3>
                    <p class="stat-number"><?php echo $totalCoaches; ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-check-circle"></i> Active
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-dumbbell"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Classes</h3>
                    <p class="stat-number"><?php echo $totalClasses; ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-check-circle"></i> Active
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3>Scheduled Today</h3>
                    <p class="stat-number"><?php echo $scheduledToday; ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-calendar-day"></i> <?php echo date('M d, Y'); ?>
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Schedules</h3>
                    <p class="stat-number"><?php echo $totalSchedules; ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-clock"></i> Upcoming
                    </span>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" data-tab="classes" onclick="switchTab('classes')">
                <i class="fas fa-dumbbell"></i> Manage Classes
            </button>
            <button class="tab-btn" data-tab="schedules" onclick="switchTab('schedules')">
                <i class="fas fa-calendar-alt"></i> Class Schedules
            </button>
            <button class="tab-btn" data-tab="otc-bookings" onclick="switchTab('otc-bookings')">
                <i class="fas fa-credit-card"></i> OTC Bookings
            </button>
        </div>

        <!-- Classes Tab -->
        <div id="classesTab" class="tab-content active">
            <div class="controls-section">
                <div class="controls-left">
                    <button onclick="openAddClassModal()" class="btn-primary">
                        <i class="fas fa-plus"></i> Create New Class
                    </button>
                    <button onclick="loadClasses()" class="btn-secondary">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="controls-right">
                    <select id="classTypeFilter" onchange="filterClasses()" class="filter-select">
                        <option value="all">All Types</option>
                        <option value="Boxing">Boxing</option>
                        <option value="Kickboxing">Kickboxing</option>
                        <option value="Muay Thai">Muay Thai</option>
                        <option value="Zumba">Zumba</option>
                        <option value="HIIT">HIIT</option>
                        <option value="Other">Other</option>
                    </select>
                    <select id="classStatusFilter" onchange="filterClasses()" class="filter-select">
                        <option value="all">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Pending">Pending</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Class Name</th>
                            <th>Type</th>
                            <th>Coach</th>
                            <th>Duration</th>
                            <th>Capacity</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="classesTableBody">
                        <tr>
                            <td colspan="9" class="no-data">
                                <i class="fas fa-spinner fa-spin" style="font-size: 48px; opacity: 0.3;"></i>
                                <p>Loading classes...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Schedules Tab -->
        <div id="schedulesTab" class="tab-content">
            <div class="controls-section">
                <div class="controls-left">
                    <button onclick="openAddScheduleModal()" class="btn-primary">
                        <i class="fas fa-plus"></i> Create Schedule
                    </button>
                    <button onclick="loadSchedules()" class="btn-secondary">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <div class="view-toggle">
                        <button class="view-btn active" data-view="calendar" onclick="switchView('calendar')">
                            <i class="fas fa-calendar"></i> Calendar
                        </button>
                        <button class="view-btn" data-view="list" onclick="switchView('list')">
                            <i class="fas fa-list"></i> List
                        </button>
                    </div>
                </div>
                <div class="controls-right">
                    <select id="scheduleClassFilter" onchange="filterSchedules()" class="filter-select">
                        <option value="all">All Classes</option>
                    </select>
                    <select id="scheduleStatusFilter" onchange="filterSchedules()" class="filter-select">
                        <option value="all">All Status</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <!-- Calendar View -->
            <div id="calendarView" class="calendar-view">
                <div class="calendar-header">
                    <button class="calendar-nav-btn" onclick="previousWeek()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h2 id="calendarTitle">Week of <?php echo date('F d, Y'); ?></h2>
                    <button class="calendar-nav-btn" onclick="nextWeek()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button class="calendar-nav-btn" onclick="goToToday()">
                        <i class="fas fa-calendar-day"></i> Today
                    </button>
                </div>
                <div class="calendar-grid" id="calendarGrid">
                    <!-- Calendar will be rendered here -->
                </div>
            </div>

            <!-- List View -->
            <div id="listView" class="list-view" style="display: none;">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Class</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Location</th>
                                <th>Capacity</th>
                                <th>Bookings</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="schedulesTableBody">
                            <tr>
                                <td colspan="9" class="no-data">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 48px; opacity: 0.3;"></i>
                                    <p>Loading schedules...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- OTC Bookings Tab -->
        <div id="otcBookingsTab" class="tab-content">
            <div class="controls-section">
                <div class="controls-left">
                    <button onclick="loadOTCBookings()" class="btn-secondary">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="controls-right">
                    <select id="otcStatusFilter" onchange="filterOTCBookings()" class="filter-select">
                        <option value="all">All Status</option>
                        <option value="Pending Payment">Pending Payment</option>
                        <option value="Payment Submitted">Payment Submitted</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Member</th>
                            <th>Class</th>
                            <th>Scheduled Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="otcBookingsTableBody">
                        <tr>
                            <td colspan="8" class="no-data">
                                <i class="fas fa-spinner fa-spin" style="font-size: 48px; opacity: 0.3;"></i>
                                <p>Loading OTC bookings...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View OTC Booking Modal -->
    <div id="otcBookingModal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h3 style="margin: 0;"><i class="fas fa-credit-card"></i> Approve Payment</h3>
                <button class="modal-close" onclick="closeOTCModal()">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <!-- Booking Info Card -->
                <div style="background: #f8f9fa; padding: 18px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #e9ecef;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px;">
                        <div>
                            <p style="font-size: 11px; color: #6c757d; font-weight: 700; margin: 0 0 6px 0; text-transform: uppercase; letter-spacing: 0.5px;">Booking ID</p>
                            <p style="font-size: 15px; color: #2c3e50; font-weight: 700; margin: 0;" id="otc-booking-id">#-</p>
                        </div>
                        <div>
                            <p style="font-size: 11px; color: #6c757d; font-weight: 700; margin: 0 0 6px 0; text-transform: uppercase; letter-spacing: 0.5px;">Member</p>
                            <p style="font-size: 15px; color: #2c3e50; font-weight: 700; margin: 0;" id="otc-member-name">-</p>
                        </div>
                    </div>
                    <div style="border-top: 1px solid #dee2e6; padding-top: 12px;">
                        <p style="font-size: 11px; color: #6c757d; font-weight: 700; margin: 0 0 6px 0; text-transform: uppercase; letter-spacing: 0.5px;">Class</p>
                        <p style="font-size: 15px; color: #2c3e50; font-weight: 700; margin: 0;" id="otc-class-name">-</p>
                    </div>
                </div>

                <!-- Schedule & Amount -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                    <div>
                        <p style="font-size: 11px; color: #6c757d; font-weight: 700; margin: 0 0 6px 0; text-transform: uppercase; letter-spacing: 0.5px;">Date</p>
                        <p style="font-size: 14px; color: #2c3e50; margin: 0; font-weight: 600;" id="otc-scheduled-date">-</p>
                    </div>
                    <div>
                        <p style="font-size: 11px; color: #6c757d; font-weight: 700; margin: 0 0 6px 0; text-transform: uppercase; letter-spacing: 0.5px;">Time</p>
                        <p style="font-size: 14px; color: #2c3e50; margin: 0; font-weight: 600;" id="otc-scheduled-time">-</p>
                    </div>
                </div>

                <!-- Amount Due - Highlighted -->
                <div style="background: linear-gradient(135deg, #fff5f5 0%, #ffe0e0 100%); padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid #d41c1c; text-align: center;">
                    <p style="font-size: 11px; color: #6c757d; font-weight: 700; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.5px;">Amount Due</p>
                    <p style="font-size: 32px; color: #d41c1c; margin: 0; font-weight: 800;" id="otc-amount">₱0.00</p>
                </div>

                <!-- Payment Proof -->
                <div style="margin-bottom: 20px;">
                    <p style="font-size: 11px; color: #6c757d; font-weight: 700; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 0.5px;">Payment Proof</p>
                    <img id="otc-payment-proof" src="" alt="Payment proof" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px; border: 1px solid #dee2e6;" class="hidden">
                    <p id="otc-no-proof" style="color: #6c757d; font-size: 13px; text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px; margin: 0; border: 1px dashed #dee2e6;">No payment proof provided</p>
                </div>

                <input type="hidden" id="otc-request-id">
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeOTCModal()" style="flex: 1;">
                    <i class="fas fa-times"></i> Close
                </button>
                <button class="btn-danger" id="otc-reject-btn" onclick="openOTCRejectModal()" style="display: none; flex: 1;">
                    <i class="fas fa-ban"></i> Reject
                </button>
                <button class="btn-success" id="otc-approve-btn" onclick="approveOTCBooking()" style="display: none; flex: 1; background: #27ae60; border-color: #27ae60;">
                    <i class="fas fa-check"></i> Approve
                </button>
            </div>
        </div>
    </div>

    <!-- OTC Reject Modal -->
    <div id="otcRejectModal" class="modal">
        <div class="modal-content modal-medium">
            <div class="modal-header modal-header-warning">
                <h3><i class="fas fa-ban"></i> Reject OTC Payment</h3>
                <button class="modal-close" onclick="closeOTCRejectModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reject this payment?</p>
                <input type="hidden" id="otc-reject-request-id">
                
                <div class="form-group" style="margin-top: 20px;">
                    <label for="otcRejectionReason"><i class="fas fa-comment"></i> Rejection Reason *</label>
                    <textarea id="otcRejectionReason" rows="4" placeholder="Explain why this payment is being rejected..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: Arial, sans-serif; resize: vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeOTCRejectModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-danger" onclick="submitOTCReject()">
                    <i class="fas fa-ban"></i> Reject Payment
                </button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Class Modal -->
    <div id="classModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 id="classModalTitle"><i class="fas fa-dumbbell"></i> Create New Class</h3>
                <button class="modal-close" onclick="closeClassModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="classForm">
                    <input type="hidden" id="classId" name="class_id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="className"><i class="fas fa-tag"></i> Class Name *</label>
                            <input type="text" id="className" name="class_name" required placeholder="e.g., Morning Boxing">
                        </div>
                        
                        <div class="form-group">
                            <label for="classType"><i class="fas fa-list"></i> Class Type *</label>
                            <select id="classType" name="class_type" required>
                                <option value="">Select Type</option>
                                <option value="Boxing">Boxing</option>
                                <option value="Kickboxing">Kickboxing</option>
                                <option value="Muay Thai">Muay Thai</option>
                                <option value="Personal Training">Personal Training</option>
                                <option value="Zumba">Zumba</option>
                                <option value="HIIT">HIIT</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="coachId"><i class="fas fa-user-tie"></i> Assigned Coach *</label>
                            <select id="coachId" name="coach_id" required>
                                <option value="">Select Coach</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration"><i class="fas fa-clock"></i> Duration (minutes) *</label>
                            <input type="number" id="duration" name="duration" required min="15" step="15" value="60">
                        </div>
                        
                        <div class="form-group">
                            <label for="maxCapacity"><i class="fas fa-users"></i> Max Capacity *</label>
                            <input type="number" id="maxCapacity" name="max_capacity" required min="1" value="10">
                        </div>
                        
                        <div class="form-group">
                            <label for="singleSessionPrice"><i class="fas fa-money-bill"></i> Single Session Price *</label>
                            <input type="number" id="singleSessionPrice" name="single_session_price" required min="0" step="0.01" placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label for="difficultyLevel"><i class="fas fa-signal"></i> Difficulty Level</label>
                            <select id="difficultyLevel" name="difficulty_level">
                                <option value="All Levels">All Levels</option>
                                <option value="Beginner">Beginner</option>
                                <option value="Intermediate">Intermediate</option>
                                <option value="Advanced">Advanced</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="classStatus"><i class="fas fa-toggle-on"></i> Status *</label>
                            <select id="classStatus" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description"><i class="fas fa-align-left"></i> Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="Describe the class..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="equipmentRequired"><i class="fas fa-tools"></i> Equipment Required</label>
                        <textarea id="equipmentRequired" name="equipment_required" rows="2" placeholder="List any equipment needed..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeClassModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-primary" onclick="saveClass()">
                    <i class="fas fa-save"></i> Save Class
                </button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Schedule Modal -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="scheduleModalTitle"><i class="fas fa-calendar-plus"></i> Create Schedule</h3>
                <button class="modal-close" onclick="closeScheduleModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm">
                    <input type="hidden" id="scheduleId" name="schedule_id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="scheduleClassId"><i class="fas fa-dumbbell"></i> Select Class *</label>
                            <select id="scheduleClassId" name="class_id" required onchange="updateScheduleClassInfo()">
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="scheduleDate"><i class="fas fa-calendar"></i> Date *</label>
                            <input type="date" id="scheduleDate" name="schedule_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="startTime"><i class="fas fa-clock"></i> Start Time *</label>
                            <input type="time" id="startTime" name="start_time" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="endTime"><i class="fas fa-clock"></i> End Time *</label>
                            <input type="time" id="endTime" name="end_time" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="scheduleMaxCapacity"><i class="fas fa-users"></i> Max Capacity *</label>
                            <input type="number" id="scheduleMaxCapacity" name="max_capacity" required min="1" value="10">
                        </div>
                        
                        <div class="form-group">
                            <label for="roomLocation"><i class="fas fa-map-marker-alt"></i> Location</label>
                            <input type="text" id="roomLocation" name="room_location" value="Main Room">
                        </div>
                        
                        <div class="form-group">
                            <label for="scheduleStatus"><i class="fas fa-toggle-on"></i> Status *</label>
                            <select id="scheduleStatus" name="status" required>
                                <option value="Scheduled">Scheduled</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="scheduleNotes"><i class="fas fa-sticky-note"></i> Notes</label>
                        <textarea id="scheduleNotes" name="notes" rows="3" placeholder="Add any special instructions..."></textarea>
                    </div>

                    <div class="recurring-section">
                        <label class="checkbox-label">
                            <input type="checkbox" id="isRecurring" name="is_recurring" onchange="toggleRecurring()">
                            <span>Recurring Schedule</span>
                        </label>
                        
                        <div id="recurringOptions" style="display: none;">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="recurrencePattern">Repeat Pattern</label>
                                    <select id="recurrencePattern" name="recurrence_pattern">
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="recurrenceEndDate">End Date</label>
                                    <input type="date" id="recurrenceEndDate" name="recurrence_end_date">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeScheduleModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-primary" onclick="saveSchedule()">
                    <i class="fas fa-save"></i> Save Schedule
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header modal-header-danger">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Are you sure you want to delete this item?</p>
                <p class="warning-text"><strong id="deleteInfo"></strong></p>
                <p class="text-muted">This action cannot be undone.</p>
                <input type="hidden" id="deleteId">
                <input type="hidden" id="deleteType">
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
    <!-- View Class Details Modal -->
    <div id="viewClassModal" class="modal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Class Details</h3>
                <button class="modal-close" onclick="closeViewClassModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewClassContent">
                <!-- Details will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeViewClassModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button class="btn-primary" onclick="editClassFromView()">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
        </div>
    </div>

    <!-- View Schedule Details Modal -->
    <div id="viewScheduleModal" class="modal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Schedule Details</h3>
                <button class="modal-close" onclick="closeViewScheduleModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewScheduleContent">
                <!-- Details will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeViewScheduleModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button class="btn-primary" onclick="editScheduleFromView()">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
        </div>
    </div>

    <!-- Reject Class Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content modal-medium">
        <div class="modal-header modal-header-warning">
            <h3><i class="fas fa-ban"></i> Reject Class Proposal</h3>
            <button class="modal-close" onclick="closeRejectModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to reject this class proposal?</p>
            <p class="warning-text"><strong id="rejectClassName"></strong></p>
            <input type="hidden" id="rejectClassId">
            
            <div class="form-group" style="margin-top: 20px;">
                <label for="rejectionReason"><i class="fas fa-comment"></i> Rejection Reason *</label>
                <textarea id="rejectionReason" rows="4" placeholder="Explain why this class is being rejected..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: Arial, sans-serif; resize: vertical;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeRejectModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn-danger" onclick="rejectClass()">
                <i class="fas fa-ban"></i> Reject Class
            </button>
        </div>
    </div>
</div>

    <script src="js/sidebar.js"></script>
    <script src="js/manager-dashboard.js"></script>
    <script src="js/coach-schedules.js"></script>
</body>
</html>