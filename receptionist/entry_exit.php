<?php
session_start();

// Check if user is logged in and has receptionist role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Receptionist') {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';

// Get receptionist info
$receptionist_name = $_SESSION['employee_name'] ?? 'Receptionist';
$employeeInitial = strtoupper(substr($receptionist_name, 0, 1));
$today_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-7 days')); // Default to 7 days ago

// Fetch active guest rates from database
$guest_rates = [];
$member_rates = [];
try {
    $conn = getDBConnection();
    
    // Fetch guest rates
    $sql = "SELECT rate_id, rate_name, price, discount_type, description, applies_to, is_discounted
            FROM rates 
            WHERE applies_to = 'Guest' AND is_active = 1 
            ORDER BY discount_type ASC";
    $stmt = $conn->query($sql);
    $guest_rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch member rates
    $sql = "SELECT rate_id, rate_name, price, discount_type, description, applies_to, is_discounted
            FROM rates 
            WHERE applies_to = 'Member' AND is_active = 1 
            ORDER BY discount_type ASC";
    $stmt = $conn->query($sql);
    $member_rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback if database query fails
    $guest_rates = [
        ['rate_id' => 1, 'rate_name' => 'Regular', 'price' => '100.00', 'discount_type' => 'Regular', 'description' => 'Regular guest rate', 'applies_to' => 'Guest', 'is_discounted' => 0],
        ['rate_id' => 2, 'rate_name' => 'Student', 'price' => '80.00', 'discount_type' => 'Student', 'description' => 'Student discount rate', 'applies_to' => 'Guest', 'is_discounted' => 1]
    ];
    
    $member_rates = [
        ['rate_id' => 5, 'rate_name' => 'Member Daily Rate', 'price' => '70.00', 'discount_type' => 'Member', 'description' => 'Member daily rate', 'applies_to' => 'Member', 'is_discounted' => 1],
        ['rate_id' => 6, 'rate_name' => 'Student Member Daily Rate', 'price' => '60.00', 'discount_type' => 'Student Member', 'description' => 'Student member daily rate', 'applies_to' => 'Member', 'is_discounted' => 1]

    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Entry/Exit - Empire Fitness</title>
    <link rel="stylesheet" href="css/receptionist-dashboard.css">
    <link rel="stylesheet" href="css/entry-exit.css">
    <link rel="stylesheet" href="css/modal.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="../css/realtime-notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-user-id="<?php echo htmlspecialchars($_SESSION['employee_id']); ?>"
      data-user-role="<?php echo htmlspecialchars($_SESSION['employee_role']); ?>"
      data-user-name="<?php echo htmlspecialchars($_SESSION['employee_name']); ?>">
    <!-- Notifications Container -->
    <div id="notifications"></div>

    <!-- Sidebar Toggle -->
    <button class="sidebar-toggle" id="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
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
                <div class="profile-name"><?php echo htmlspecialchars($receptionist_name); ?></div>
                <div class="profile-role">Receptionist</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="receptionistDashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-divider">OPERATIONS</div>
            
            <a href="pos.php" class="nav-item">
                <i class="fas fa-cash-register"></i>
                <span>Point of Sale</span>
            </a>
            
            <a href="entry_exit.php" class="nav-item active">
                <i class="fas fa-door-open"></i>
                <span>Entry/Exit</span>
            </a>

            <div class="nav-divider">MEMBERS</div>
            
            <a href="members_list.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Members</span>
            </a>
            
            <div class="nav-divider">PAYMENTS</div>
            
            <a href="payment_history.php" class="nav-item">
                <i class="fas fa-history"></i>
                <span>Payment History</span>
            </a>

            <div class="nav-divider">SCHEDULE & REPORTS</div>
            
            <a href="schedule_classes.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Class Schedule</span>
            </a>
            <a href="daily_report.php" class="nav-item">
                <i class="fas fa-chart-bar"></i>
                <span>Daily Report</span>
            </a>
            
            <div class="nav-divider">ACCOUNT</div>
            
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            <a href="settings.php" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-door-open"></i> Manage Entry/Exit</h1>
                <p class="page-subtitle">Track member and guest check-ins and check-outs in real-time</p>
            </div>
            <div class="current-time" id="current-time">00:00:00 AM</div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid-small">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Currently Inside</h3>
                    <p class="stat-number" id="currently-inside">0</p>
                    <span class="stat-change neutral">
                        <i class="fas fa-door-open"></i> Active now
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="stat-content">
                    <h3>Today's Check-ins</h3>
                    <p class="stat-number" id="today-checkins">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Total entries
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-content">
                    <h3>Members Today</h3>
                    <p class="stat-number" id="members-count">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-user-check"></i> With membership
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-walking"></i>
                </div>
                <div class="stat-content">
                    <h3>Walk-ins Today</h3>
                    <p class="stat-number" id="walkins-count">0</p>
                    <span class="stat-change neutral">
                        <i class="fas fa-ticket-alt"></i> Daily guests
                    </span>
                </div>
            </div>
        </div>


        <!-- Entry and Exit Section -->
        <div class="entry-exit-container">
            <!-- Check In Section -->
<div class="entry-section">
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-sign-in-alt"></i>
            <h2>Member Check In</h2>
        </div>
    </div>
    <div class="section-content">
        <div class="input-group">
            <label for="checkin-id">
                <i class="fas fa-qrcode"></i> Scan QR Code or Enter Member ID
            </label>
            
            <!-- QR Scanner Button and Manual Input Toggle -->
            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                <button 
                    class="btn btn-primary" 
                    id="btn-start-scanner-checkin" 
                    onclick="startQRScanner('checkin')"
                    style="flex: 1;">
                    <i class="fas fa-camera"></i> Start QR Scanner
                </button>
                <button 
                    class="btn btn-secondary" 
                    id="btn-stop-scanner-checkin" 
                    onclick="stopQRScanner('checkin')"
                    style="flex: 1; display: none;">
                    <i class="fas fa-stop"></i> Stop Scanner
                </button>
            </div>
            
            <!-- QR Reader Container -->
            <div id="qr-reader-checkin" style="display: none; margin-bottom: 15px; max-width: 500px;"></div>
            
            <!-- Scanner Status -->
            <div id="qr-status-checkin" style="margin-bottom: 15px; padding: 10px; border-radius: 5px; display: none; text-align: center; font-weight: 600;"></div>
            
            <!-- Manual Entry Input (hidden by default when scanner is active) -->
            <div id="manual-entry-container-checkin">
                <input 
                    type="text" 
                    id="checkin-id" 
                    class="form-input" 
                    placeholder="Type member ID manually..."
                    autocomplete="off"
                    autofocus>
                <button class="btn btn-primary btn-checkin" onclick="processCheckIn()">
                    <i class="fas fa-sign-in-alt"></i> Check In Member
                </button>
            </div>
        </div>

        <div class="divider">
            <span>OR</span>
        </div>

        <div class="walkin-section">
            <button class="btn btn-secondary btn-walkin" onclick="showWalkInModal()">
                <i class="fas fa-walking"></i> Register Walk-in Guest
            </button>
            <button class="btn btn-primary btn-member-walkin" onclick="showMemberIDModal()">
                <i class="fas fa-user-check"></i> Register Walk-in Member
            </button>
        </div>
    </div>
</div>

            <!-- Check Out Section -->
<div class="exit-section">
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-sign-out-alt"></i>
            <h2>Member Check Out</h2>
        </div>
    </div>
    <div class="section-content">
        <div class="input-group">
            <label for="checkout-id">
                <i class="fas fa-qrcode"></i> Scan QR Code or Enter ID
            </label>
            
            <!-- QR Scanner Button and Manual Input Toggle -->
            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                <button 
                    class="btn btn-danger" 
                    id="btn-start-scanner-checkout" 
                    onclick="startQRScanner('checkout')"
                    style="flex: 1;">
                    <i class="fas fa-camera"></i> Start QR Scanner
                </button>
                <button 
                    class="btn btn-secondary" 
                    id="btn-stop-scanner-checkout" 
                    onclick="stopQRScanner('checkout')"
                    style="flex: 1; display: none;">
                    <i class="fas fa-stop"></i> Stop Scanner
                </button>
            </div>
            
            <!-- QR Reader Container -->
            <div id="qr-reader-checkout" style="display: none; margin-bottom: 15px; max-width: 500px;"></div>
            
            <!-- Scanner Status -->
            <div id="qr-status-checkout" style="margin-bottom: 15px; padding: 10px; border-radius: 5px; display: none; text-align: center; font-weight: 600;"></div>
            
            <!-- Manual Entry Input -->
            <div id="manual-entry-container-checkout">
                <input 
                    type="text" 
                    id="checkout-id" 
                    class="form-input" 
                    placeholder="Type ID manually to check out..."
                    autocomplete="off">
                <button class="btn btn-danger btn-checkout" onclick="processCheckOut()">
                    <i class="fas fa-sign-out-alt"></i> Check Out
                </button>
            </div>
        </div>
        
        <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 15px; border-radius: 10px; margin-top: 10px; border-left: 4px solid #ffc107;">
            <div style="display: flex; align-items: center; gap: 10px; color: #856404;">
                <i class="fas fa-info-circle" style="font-size: 20px;"></i>
                <div>
                    <strong style="display: block; margin-bottom: 3px;">Quick Tip</strong>
                    <small>You can also check out from the "Currently Inside" section below</small>
                </div>
            </div>
        </div>
    </div>
</div>
        </div>

        <!-- Currently Inside Section -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Currently Inside the Gym</h3>
                <button class="btn btn-sm btn-secondary" onclick="refreshCurrentlyInside()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <div class="card-body">
                <div class="currently-inside-grid" id="currently-inside-grid">
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <i class="fas fa-inbox"></i>
                        <p>No one is currently inside</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Log -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-list"></i> Entry/Exit Log</h3>
                <div class="header-filters">
                    <div class="filter-group">
                        <label for="log-start-date">From:</label>
                        <input type="date" id="log-start-date" class="form-input-sm">
                    </div>
                    <div class="filter-group">
                        <label for="log-end-date">To:</label>
                        <input type="date" id="log-end-date" class="form-input-sm">
                    </div>
                    <button class="btn btn-sm btn-primary" onclick="filterLogByDate()">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
                <div class="header-actions">
                    <button class="btn btn-sm btn-secondary" onclick="exportLog()">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="resetLogFilter()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="refreshLog()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="log-table-body">
                            <tr>
                                <td colspan="8" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-clipboard"></i>
                                        <p>No entries yet today</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Unified Walk-in Guest Check-in Modal -->
    <div class="modal" id="walkin-modal">
        <div class="modal-dialog" style="max-width: 700px;">
            <div class="modal-content">
                <!-- Progress Steps Header -->
                <div class="progress-header">
                    <div class="progress-container">
                        <!-- Progress line background -->
                        <div class="progress-line"></div>
                        
                        <!-- Step 1: Guest Details -->
                        <div class="step-item">
                            <div class="step-circle" id="step-1-circle">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="step-label">Guest Details</div>
                        </div>

                        <!-- Step 2: Payment -->
                        <div class="step-item">
                            <div class="step-circle" id="step-2-circle">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="step-label">Payment</div>
                        </div>

                        <!-- Step 3: Confirmation -->
                        <div class="step-item">
                            <div class="step-circle" id="step-3-circle">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="step-label">Confirm</div>
                        </div>
                    </div>
                </div>

                <div class="modal-header">
                    <h3 id="step-title"><i class="fas fa-walking"></i> Walk-in Guest Registration</h3>
                    <button class="modal-close" onclick="closeWalkInModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <form id="walkin-form" onsubmit="event.preventDefault();">
                        <!-- STEP 1: Guest Details -->
                        <div id="step-1" class="form-step" style="display: block;">
                            <div class="form-group">
                                <label for="guest-name" class="required">Guest Name</label>
                                <input 
                                    type="text" 
                                    id="guest-name" 
                                    class="form-input" 
                                    placeholder="Enter guest's full name"
                                    required>
                            </div>
                            <div class="form-group">
                                <label for="guest-phone">Phone Number (11 digits)</label>
                                <input 
                                    type="text" 
                                    id="guest-phone" 
                                    class="form-input"
                                    placeholder="e.g., 09171234567"
                                    maxlength="11"
                                    pattern="[0-9]*">
                                <small style="color: #999;">Optional - must be exactly 11 digits if provided</small>
                            </div>
                            <div class="form-group">
                                <label for="discount-type" class="required">Rate Type</label>
                                <select id="discount-type" class="form-input" required onchange="updatePaymentAmount()">
                                    <option value="">Select rate type</option>
                                    <?php foreach ($guest_rates as $rate): ?>
                                        <option value="<?php echo htmlspecialchars($rate['discount_type']); ?>" 
                                                data-rate-id="<?php echo $rate['rate_id']; ?>"
                                                data-price="<?php echo $rate['price']; ?>"
                                                data-rate-name="<?php echo htmlspecialchars($rate['rate_name']); ?>"
                                                data-description="<?php echo htmlspecialchars($rate['description'] ?? ''); ?>"
                                                data-is-discounted="<?php echo $rate['is_discounted'] ?? 0; ?>">
                                            <?php echo htmlspecialchars($rate['rate_name']); ?> (₱<?php echo number_format($rate['price'], 2); ?>)
                                            <?php if ($rate['is_discounted']): ?><span class="badge-discount"> - Discounted</span><?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="rate-description" style="color: #666; margin-top: 5px; display: none;"></small>
                            </div>
                        </div>

                        <!-- STEP 2: Payment Method -->
                        <div id="step-2" class="form-step" style="display: none;">
                            <div style="background: #f5f5f5; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <label style="font-size: 12px; color: #2f2f2f; font-weight: 600;">GUEST NAME</label>
                                        <p style="margin: 5px 0 0 0; font-size: 15px; color: #000;" id="summary-guest-name">-</p>
                                    </div>
                                    <div>
                                        <label style="font-size: 12px; color: #2f2f2f; font-weight: 600;">RATE TYPE</label>
                                        <p style="margin: 5px 0 0 0; font-size: 15px; color: #000;" id="summary-rate-type">-</p>
                                    </div>
                                </div>
                            </div>

                            <div style="text-align: center; background: #f0f8ff; padding: 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #667eea;">
                                <small style="color: #666; font-weight: 600;">AMOUNT DUE</small>
                                <h3 style="margin: 8px 0 0 0; font-size: 32px; color: #27ae60;" id="step-2-amount">₱0.00</h3>
                            </div>

                            <div class="form-group">
                                <label for="guest-payment-method" class="required">Payment Method</label>
                                <select id="guest-payment-method" class="form-input" required>
                                    <option value="">Select payment method</option>
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                    <option value="PayMaya">PayMaya</option>
                                </select>
                            </div>
                        </div>

                        <!-- STEP 3: Review & Confirm -->
                        <div id="step-3" class="form-step" style="display: none;">
                            <div style="background: #f0fdf4; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #27ae60;">
                                <div style="display: flex; align-items: center; gap: 10px; color: #065f46;">
                                    <i class="fas fa-check-circle" style="font-size: 20px;"></i>
                                    <div>
                                        <strong style="display: block; margin-bottom: 3px;">Review Your Information</strong>
                                        <small>Please verify all details are correct before confirming</small>
                                    </div>
                                </div>
                            </div>

                            <div style="background: #f5f5f5; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                                <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">
                                    <small style="color: #999; font-weight: 600;">GUEST INFORMATION</small>
                                    <p style="margin: 8px 0 4px 0; font-size: 16px; font-weight: 500; color: #000;" id="review-name">-</p>
                                    <p style="margin: 0; color: #000; font-size: 14px;" id="review-phone">No phone provided</p>
                                </div>
                                <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">
                                    <small style="color: #999; font-weight: 600;">RATE & AMOUNT</small>
                                    <p style="margin: 8px 0 0 0; font-size: 16px; font-weight: 500; color: #000;">
                                        <span id="review-type">-</span> | 
                                        <span id="review-amount" style="color: #27ae60; font-weight: 700;">₱0.00</span>
                                    </p>
                                </div>
                                <div>
                                    <small style="color: #999; font-weight: 600;">PAYMENT METHOD</small>
                                    <p style="margin: 8px 0 0 0; font-size: 16px; font-weight: 500; color: #000;" id="review-payment-method">-</p>
                                </div>
                            </div>

                            <div class="form-group checkbox-group">
                                <label>
                                    <input type="checkbox" id="guest-payment-confirm" required>
                                    <span>I confirm this payment has been received and the guest information is correct</span>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" onclick="closeWalkInModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn btn-secondary" id="btn-prev" type="button" onclick="previousStep()" style="display: none;">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button class="btn btn-primary" id="btn-next" type="button" onclick="nextStep()">
                        <i class="fas fa-chevron-right"></i> Next
                    </button>
                    <button class="btn btn-success" id="btn-complete" type="button" onclick="processGuestPayment()" style="display: none;">
                        <i class="fas fa-check"></i> Complete Check In
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Member ID Lookup Modal -->
    <div class="modal" id="member-id-modal">
        <div class="modal-dialog" style="max-width: 500px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-user-check"></i> Member Lookup</h3>
                    <button class="modal-close" onclick="closeMemberIDModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div style="background: #e3f2fd; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #2196F3;">
                        <div style="display: flex; align-items: center; gap: 10px; color: #1565c0;">
                            <i class="fas fa-info-circle" style="font-size: 20px;"></i>
                            <div>
                                <strong style="display: block; margin-bottom: 3px;">Enter Member ID</strong>
                                <small>Enter your member/client ID to proceed with walk-in registration</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="member-lookup-id" class="required">Member ID</label>
                        <input 
                            type="text" 
                            id="member-lookup-id" 
                            class="form-input" 
                            placeholder="Enter member ID (e.g., 1001)"
                            autocomplete="off"
                            autofocus>
                        <small style="color: #2f2f2f;">Your member ID is provided during registration</small>
                    </div>

                    <div id="member-lookup-error" style="display: none; background: #ffebee; border: 1px solid #ef5350; padding: 12px; border-radius: 6px; color: #c62828; margin-bottom: 15px;">
                        <i class="fas fa-exclamation-circle"></i> <span id="error-message"></span>
                    </div>

                    <div id="member-lookup-info" style="display: none; background: #f0fdf4; border: 1px solid #4ade80; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                        <div style="font-weight: 600; margin-bottom: 8px;">
                            <i class="fas fa-check-circle" style="color: #22c55e;"></i> Member Found
                        </div>
                        <div style="font-size: 14px;">
                            <div style="margin-bottom: 5px;"><strong>Name:</strong> <span id="member-found-name">-</span></div>
                            <div><strong>Status:</strong> <span id="member-found-status">-</span></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" onclick="closeMemberIDModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn btn-primary" type="button" onclick="validateMemberID()">
                        <i class="fas fa-search"></i> Find Member
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Member Walk-in Registration Modal -->
    <div class="modal" id="member-walkin-modal">
        <div class="modal-dialog" style="max-width: 700px;">
            <div class="modal-content">
                <!-- Progress Steps Header -->
                <div class="progress-header">
                    <div class="progress-container">
                        <!-- Progress line background -->
                        <div class="progress-line"></div>
                        
                        <!-- Step 1: Member Details -->
                        <div class="step-item">
                            <div class="step-circle" id="member-step-1-circle">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="step-label">Member Details</div>
                        </div>

                        <!-- Step 2: Payment -->
                        <div class="step-item">
                            <div class="step-circle" id="member-step-2-circle">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="step-label">Payment</div>
                        </div>

                        <!-- Step 3: Confirmation -->
                        <div class="step-item">
                            <div class="step-circle" id="member-step-3-circle">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="step-label">Confirm</div>
                        </div>
                    </div>
                </div>

                <div class="modal-header">
                    <h3 id="member-step-title"><i class="fas fa-user-check"></i> Member Walk-in Registration</h3>
                    <button class="modal-close" onclick="closeMemberWalkInModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <form id="member-walkin-form" onsubmit="event.preventDefault();">
                        <!-- STEP 1: Member Details -->
                        <div id="member-step-1" class="form-step" style="display: block;">
                            <!-- Member Info Display (injected by JavaScript) -->
                            <div id="member-info-display-container"></div>

                            <div class="form-group">
                                <label for="member-discount-type" class="required">Rate Type</label>
                                <select id="member-discount-type" class="form-input" required onchange="updateMemberPaymentAmount()">
                                    <option value="">Select rate type</option>
                                    <?php foreach ($member_rates as $rate): ?>
                                        <option value="<?php echo $rate['rate_id']; ?>" 
                                                data-rate-id="<?php echo $rate['rate_id']; ?>"
                                                data-price="<?php echo $rate['price']; ?>"
                                                data-rate-name="<?php echo htmlspecialchars($rate['rate_name']); ?>"
                                                data-description="<?php echo htmlspecialchars($rate['description'] ?? ''); ?>"
                                                data-is-discounted="<?php echo $rate['is_discounted'] ?? 0; ?>">
                                            <?php echo htmlspecialchars($rate['rate_name']); ?> (₱<?php echo number_format($rate['price'], 2); ?>)
                                            <?php if ($rate['is_discounted']): ?><span class="badge-discount"> - Discounted</span><?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="member-rate-description" style="color: #666; margin-top: 5px; display: none;"></small>
                            </div>
                        </div>

                        <!-- STEP 2: Payment Method -->
                        <div id="member-step-2" class="form-step" style="display: none;">
                            <div style="background: #f5f5f5; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <label style="font-size: 12px; color: #2f2f2f; font-weight: 600;">MEMBER NAME</label>
                                        <p style="margin: 5px 0 0 0; font-size: 15px; color: #000;" id="member-summary-name">-</p>
                                    </div>
                                    <div>
                                        <label style="font-size: 12px; color: #2f2f2f; font-weight: 600;">RATE TYPE</label>
                                        <p style="margin: 5px 0 0 0; font-size: 15px; color: #000;" id="member-summary-rate-type">-</p>
                                    </div>
                                </div>
                            </div>

                            <div style="text-align: center; background: #f0f8ff; padding: 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #667eea;">
                                <small style="color: #666; font-weight: 600;">AMOUNT DUE</small>
                                <h3 style="margin: 8px 0 0 0; font-size: 32px; color: #27ae60;" id="member-step-2-amount">₱0.00</h3>
                            </div>

                            <div class="form-group">
                                <label for="member-payment-method" class="required">Payment Method</label>
                                <select id="member-payment-method" class="form-input" required>
                                    <option value="">Select payment method</option>
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                    <option value="PayMaya">PayMaya</option>
                                </select>
                            </div>
                        </div>

                        <!-- STEP 3: Review & Confirm -->
                        <div id="member-step-3" class="form-step" style="display: none;">
                            <div style="background: #f0fdf4; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #27ae60;">
                                <div style="display: flex; align-items: center; gap: 10px; color: #065f46;">
                                    <i class="fas fa-check-circle" style="font-size: 20px;"></i>
                                    <div>
                                        <strong style="display: block; margin-bottom: 3px;">Review Your Information</strong>
                                        <small>Please verify all details are correct before confirming</small>
                                    </div>
                                </div>
                            </div>

                            <div style="background: #f5f5f5; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                                <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">
                                    <small style="color: #999; font-weight: 600;">MEMBER INFORMATION</small>
                                    <p style="margin: 8px 0 0 0; font-size: 16px; font-weight: 500; color: #000;" id="member-review-name">-</p>
                                </div>
                                <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">
                                    <small style="color: #999; font-weight: 600;">RATE & AMOUNT</small>
                                    <p style="margin: 8px 0 0 0; font-size: 16px; font-weight: 500; color: #000;">
                                        <span id="member-review-type">-</span> | 
                                        <span id="member-review-amount" style="color: #27ae60; font-weight: 700;">₱0.00</span>
                                    </p>
                                </div>
                                <div>
                                    <small style="color: #999; font-weight: 600;">PAYMENT METHOD</small>
                                    <p style="margin: 8px 0 0 0; font-size: 16px; font-weight: 500; color: #000;" id="member-review-payment-method">-</p>
                                </div>
                            </div>

                            <div class="form-group checkbox-group">
                                <label>
                                    <input type="checkbox" id="member-payment-confirm" required>
                                    <span>I confirm this payment has been received and the member information is correct</span>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" onclick="closeMemberWalkInModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn btn-secondary" id="member-btn-prev" type="button" onclick="memberPreviousStep()" style="display: none;">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button class="btn btn-primary" id="member-btn-next" type="button" onclick="memberNextStep()">
                        <i class="fas fa-chevron-right"></i> Next
                    </button>
                    <button class="btn btn-success" id="member-btn-complete" type="button" onclick="processMemberPayment()" style="display: none;">
                        <i class="fas fa-check"></i> Complete Check In
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Error Modal -->
    <div class="modal" id="error-modal">
        <div class="modal-dialog" style="max-width: 400px;">
            <div class="modal-content">
                <div class="modal-header" style="background: #ffebee; border-bottom: 2px solid #ef5350;">
                    <h3 style="color: #c62828;"><i class="fas fa-exclamation-circle"></i> Error</h3>
                    <button class="modal-close" onclick="closeErrorModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="error-modal-message" style="margin: 0; color: #333; font-size: 16px;">An error occurred</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" type="button" onclick="closeErrorModal()">
                        <i class="fas fa-check"></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal" id="success-modal">
        <div class="modal-dialog" style="max-width: 400px;">
            <div class="modal-content">
                <div class="modal-header" style="background: #f0fdf4; border-bottom: 2px solid #22c55e;">
                    <h3 style="color: #065f46;"><i class="fas fa-check-circle"></i> Success</h3>
                    <button class="modal-close" onclick="closeSuccessModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="success-modal-message" style="margin: 0; color: #333; font-size: 16px;">Operation completed successfully</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" type="button" onclick="closeSuccessModal()">
                        <i class="fas fa-check"></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="toast" id="toast">
        <div class="toast-content">
            <i class="toast-icon"></i>
            <span class="toast-message"></span>
        </div>
    </div>

    <script>
        // Global rates data from PHP
        const allRates = {
            guest: <?php echo json_encode($guest_rates); ?>,
            member: <?php echo json_encode($member_rates); ?>
        };

        // Function to populate rates dropdown based on registration type
        function updateRatesDropdown(isMember = false) {
            const discountSelect = document.getElementById('discount-type');
            const ratesToUse = isMember ? allRates.member : allRates.guest;
            
            // Clear existing options (keep the placeholder)
            discountSelect.innerHTML = '<option value="">Select rate type</option>';
            
            // Add options from the appropriate rate array
            ratesToUse.forEach(rate => {
                const option = document.createElement('option');
                option.value = rate.discount_type;
                option.setAttribute('data-rate-id', rate.rate_id);
                option.setAttribute('data-price', rate.price);
                option.setAttribute('data-rate-name', rate.rate_name);
                option.setAttribute('data-description', rate.description || '');
                option.setAttribute('data-is-discounted', rate.is_discounted || 0);
                
                let optionText = `${rate.rate_name} (₱${parseFloat(rate.price).toFixed(2)})`;
                if (rate.is_discounted) {
                    optionText += ' - Discounted';
                }
                option.textContent = optionText;
                discountSelect.appendChild(option);
            });
            
            // Reset value and trigger change event
            discountSelect.value = '';
            discountSelect.dispatchEvent(new Event('change'));
        }
    </script>

    <!-- Modal: No Active POS Session -->
    <div class="modal" id="no-pos-session-modal">
        <div class="modal-dialog" style="max-width: 500px;">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #ff6b6b; color: white;">
                    <h3>
                        <i class="fas fa-exclamation-circle"></i> Active POS Session Required
                    </h3>
                    <button class="modal-close" onclick="closeNoPOSModal()" style="color: white;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" style="padding: 30px; text-align: center;">
                    <i class="fas fa-cash-register" style="font-size: 48px; color: #ff6b6b; margin-bottom: 20px;"></i>
                    <h4 style="margin-bottom: 15px;">POS Session Not Active</h4>
                    <p style="margin-bottom: 20px; color: #666;">
                        To register walk-in guests, you must have an active Point of Sale session open. 
                        This ensures all transactions are properly recorded.
                    </p>
                    <p style="color: #999; font-size: 14px; margin-bottom: 25px;">
                        Please start a POS session first, then return to register guests.
                    </p>
                </div>
                <div class="modal-footer" style="background-color: #f9f9f9; padding: 15px;">
                    <button class="btn btn-secondary" onclick="closeNoPOSModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn btn-danger" onclick="goToPointOfSale()">
                        <i class="fas fa-arrow-right"></i> Go to Point of Sale
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Scanner Library - html5-qrcode (10x faster than jsQR) -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <script src="js/receptionist-dashboard.js"></script>
    <script src="js/modal.js"></script>
    <script src="js/entry-exit.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>