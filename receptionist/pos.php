<?php
session_start();

if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Receptionist') {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

$employeeName = $_SESSION['employee_name'] ?? 'Receptionist';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));

// Get guest rates for entry/exit walk-in section
$guest_rates = [];
$member_rates = [];
try {
    $sql = "SELECT rate_id, rate_name, price, discount_type, description, applies_to, is_discounted
            FROM rates 
            WHERE applies_to = 'Guest' AND is_active = 1 
            ORDER BY discount_type ASC";
    $stmt = $conn->query($sql);
    $guest_rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale - Empire Fitness</title>
    <link rel="stylesheet" href="css/receptionist-dashboard.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .pos-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 20px;
        }

        .pos-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .pos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .pos-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 24px;
        }

        .session-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: #e8f5e9;
            border-radius: 20px;
            font-size: 14px;
            color: #2e7d32;
            font-weight: 600;
        }

        .session-status.inactive {
            background: #ffebee;
            color: #c62828;
        }

        .session-status .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #d41c1c;
            box-shadow: 0 0 0 3px rgba(212, 28, 28, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }

        .btn-primary {
            background: #d41c1c;
            color: white;
        }

        .btn-primary:hover {
            background: #b81515;
            box-shadow: 0 4px 12px rgba(212, 28, 28, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .transactions-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 6px;
        }

        .transaction-item {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .transaction-item:hover {
            background: #f8f9fa;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-info {
            flex: 1;
        }

        .transaction-type {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .transaction-meta {
            font-size: 12px;
            color: #6c757d;
        }

        .transaction-amount {
            text-align: right;
            font-weight: 700;
            color: #d41c1c;
            font-size: 16px;
        }

        .last-sessions {
            border: 1px solid #e9ecef;
            border-radius: 6px;
            overflow: hidden;
        }

        .sessions-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .sessions-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }

        .sessions-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        .sessions-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
        }

        .sessions-table tbody tr:hover {
            background: #f8f9fa;
        }

        .sessions-table tbody tr:last-child td {
            border-bottom: none;
        }

        .session-amount {
            font-weight: 700;
            color: #2c3e50;
        }

        .session-amount.positive {
            color: #27ae60;
        }

        .session-amount.negative {
            color: #e74c3c;
        }

        .session-time {
            font-size: 12px;
            color: #6c757d;
        }

        .summary-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #d41c1c;
        }

        .summary-label {
            font-size: 12px;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .summary-value {
            font-size: 24px;
            font-weight: 800;
            color: #2c3e50;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .payment-btn {
            padding: 12px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            color: #2c3e50;
            transition: all 0.3s;
            text-align: center;
        }

        .payment-btn:hover {
            border-color: #d41c1c;
            background: #fff5f5;
        }

        .payment-btn.active {
            background: #d41c1c;
            color: white;
            border-color: #d41c1c;
        }

        .session-controls {
            display: flex;
            gap: 10px;
        }

        .session-controls button {
            flex: 1;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-dialog {
            max-width: 500px;
            width: 90%;
        }

        .modal-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .modal-footer .btn {
            margin: 0;
        }

        .form-step {
            display: none;
        }

        .form-step.active {
            display: block;
        }

        .badge-discount {
            background: #ffc107;
            color: #000;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
        }

        .checkbox-group input[type="checkbox"] {
            margin: 0;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-primary {
            background: #d41c1c;
            color: white;
        }

        .badge-secondary {
            background: #e9ecef;
            color: #2c3e50;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #6c757d;
        }

        @media (max-width: 1200px) {
            .pos-container {
                grid-template-columns: 1fr;
            }
        }

        .client-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 6px 6px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 100;
            display: none;
        }

        .client-search-results.show {
            display: block;
        }

        .client-search-item {
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: background 0.2s;
        }

        .client-search-item:hover {
            background: #f8f9fa;
        }

        .client-search-item:last-child {
            border-bottom: none;
        }

        .client-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .client-meta {
            font-size: 12px;
            color: #6c757d;
        }

        .form-group {
            position: relative;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            font-weight: 600;
            color: #6c757d;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
        }

        .tab-btn.active {
            color: #d41c1c;
            border-bottom-color: #d41c1c;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }

        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }

        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
            border-left: 4px solid #2196f3;
        }

        /* Entry/Exit Styles */
        .stats-grid-small {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #d41c1c 0%, #b81515 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
        }

        .stat-content {
            flex: 1;
        }

        .stat-content h3 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
        }

        .stat-number {
            margin: 5px 0 0 0;
            font-size: 28px;
            font-weight: 800;
            color: #2c3e50;
        }

        .stat-change {
            display: inline-block;
            margin-top: 8px;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .stat-change.positive {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .stat-change.neutral {
            background: #f5f5f5;
            color: #666;
        }

        .entry-exit-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .entry-section,
        .exit-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .section-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            font-size: 24px;
            color: #d41c1c;
        }

        .section-title h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 20px;
        }

        .section-content {
            padding: 20px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .input-group label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }

        .input-group .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }

        .input-group .form-input:focus {
            outline: none;
            border-color: #d41c1c;
            box-shadow: 0 0 0 3px rgba(212, 28, 28, 0.1);
        }

        .divider {
            text-align: center;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #6c757d;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e9ecef;
        }

        .walkin-section {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn-checkin,
        .btn-checkout,
        .btn-walkin,
        .btn-member-walkin {
            width: 100%;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .card-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 18px;
        }

        .header-filters,
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }

        .form-input-sm {
            padding: 6px 10px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            font-size: 12px;
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 12px;
        }

        .card-body {
            padding: 20px;
        }

        .currently-inside-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .person-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #d41c1c;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .person-info h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .person-info p {
            margin: 3px 0;
            font-size: 12px;
            color: #6c757d;
        }

        .person-actions {
            display: flex;
            gap: 5px;
        }

        .btn-checkout-inline {
            padding: 6px 12px;
            background: #d41c1c;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-checkout-inline:hover {
            background: #b81515;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead {
            background: #f8f9fa;
        }

        .table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
            font-size: 14px;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
            color: #2c3e50;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .text-center {
            text-align: center;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 1024px) {
            .entry-exit-container {
                grid-template-columns: 1fr;
            }

            .stats-grid-small {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid-small {
                grid-template-columns: 1fr;
            }

            .header-filters,
            .header-actions {
                flex-direction: column;
                width: 100%;
            }

            .filter-group,
            .btn-sm {
                width: 100%;
            }

            .currently-inside-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body data-user-id="<?php echo htmlspecialchars($_SESSION['employee_id']); ?>"
      data-user-role="<?php echo htmlspecialchars($_SESSION['employee_role']); ?>"
      data-user-name="<?php echo htmlspecialchars($_SESSION['employee_name']); ?>"
      data-session-id="">

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
            <a href="receptionistDashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-divider">OPERATIONS</div>
            
            <a href="pos.php" class="nav-item active">
                <i class="fas fa-cash-register"></i>
                <span>Point of Sale</span>
            </a>

            <a href="entry_exit.php" class="nav-item">
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
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="header-title">
                <h1><i class="fas fa-cash-register"></i> Point of Sale</h1>
            </div>
            <div class="header-actions">
                <div class="session-status" id="sessionStatus">
                    <span class="status-dot"></span>
                    <span id="sessionStatusText">No Active Session</span>
                </div>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- POS TAB -->
        <div id="pos-tab" class="tab-content active">
        <div class="pos-container">
            <!-- LEFT SECTION: TRANSACTION INPUT -->
            <div class="pos-section">
                <div class="pos-header">
                    <h2>New Transaction</h2>
                </div>

                <!-- Session Management -->
                <div id="sessionControls">
                    <div class="form-group">
                        <label>Opening Balance (₱)</label>
                        <input type="number" id="openingBalance" placeholder="Enter opening balance" step="0.01">
                    </div>
                    <div class="session-controls">
                        <button class="btn btn-success" onclick="startSession()">
                            <i class="fas fa-play"></i> Start Session
                        </button>
                    </div>
                </div>

                <!-- Transaction Form (Hidden until session starts) -->
                <div id="transactionForm" style="display: none;">
                    <div class="form-group">
                        <label>Client *</label>
                        <input type="text" id="clientSearch" placeholder="Search client">
                        <input type="hidden" id="clientId">
                        <div class="client-search-results" id="clientResults"></div>
                    </div>

                    <div class="form-group">
                        <label>Transaction Type *</label>
                        <select id="transactionType" required>
                            <option value="">Select Transaction Type</option>
                            <option value="Membership">Membership</option>
                            <option value="Membership Renewal">Membership Renewal</option>
                        </select>
                    </div>

                    <div class="form-group" id="membershipPlanGroup" style="display:none;">
                        <label>Membership Plan</label>
                        <select id="membershipPlan" class="form-input">
                            <option value="">Select plan</option>
                        </select>
                        <small id="membershipPlanInfo" style="color:#666; display:none;"></small>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" id="description" placeholder="e.g., Daily Pass, 1 Month Membership">
                    </div>

                    <div class="form-group">
                        <label>Amount (₱) *</label>
                        <input type="number" id="amount" placeholder="0.00" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label>Payment Method *</label>
                        <div class="payment-methods">
                            <button type="button" class="payment-btn active" data-method="Cash" onclick="selectPaymentMethod('Cash')">
                                <i class="fas fa-money-bill"></i> Cash
                            </button>
                            <button type="button" class="payment-btn" data-method="GCash" onclick="selectPaymentMethod('GCash')">
                                <i class="fas fa-mobile-alt"></i> GCash
                            </button>
                        </div>
                        <input type="hidden" id="paymentMethod" value="Cash" required>
                    </div>

                    <div class="button-group">
                        <button class="btn btn-primary" onclick="addTransaction()">
                            <i class="fas fa-plus"></i> Confirm Transaction
                        </button>
                        <button class="btn btn-danger" onclick="endSessionModal()">
                            <i class="fas fa-stop"></i> End Session
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT SECTION: TRANSACTIONS & SUMMARY -->
            <div class="pos-section">
                <div class="pos-header">
                    <h2>Session Summary</h2>
                </div>

                <!-- Summary Cards -->
                <div id="summaryCards">
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Start a POS session to begin processing transactions</p>
                    </div>
                </div>

                <!-- Transactions List -->
                <h3 style="margin-top: 20px; margin-bottom: 10px; color: #2c3e50;">Today's Transactions</h3>
                <div class="transactions-list" id="transactionsList">
                    <div class="empty-state" style="padding: 20px;">
                        <i class="fas fa-receipt"></i>
                        <p>No transactions yet</p>
                    </div>
                </div>

                <!-- Last Sessions -->
                <h3 style="margin-top: 30px; margin-bottom: 10px; color: #2c3e50;">Last Sessions</h3>
                <div class="last-sessions" id="lastSessions">
                    <div class="empty-state" style="padding: 20px;">
                        <i class="fas fa-history"></i>
                        <p>No previous sessions</p>
                    </div>
                </div>
            </div>
        </div>
        </div><!-- Close POS TAB -->
        </div>
        </div>
    
    <div class="modal" id="endSessionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>End Session</h3>
                <button class="modal-close" onclick="closeEndSessionModal()">&times;</button>
            </div>
            <div style="margin-bottom: 20px;">
                <p style="color: #6c757d; margin-bottom: 15px;">Please verify the closing balance before ending your session.</p>
                
                <div class="form-group">
                    <label>Closing Balance (₱)</label>
                    <input type="number" id="closingBalance" placeholder="Enter closing balance" step="0.01">
                </div>

                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea id="sessionNotes" placeholder="Add any notes about this session..." rows="3"></textarea>
                </div>

                <!-- Session Verification Section -->
                <div id="sessionVerification" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 15px;"></div>

                <!-- Earnings Breakdown Section -->
                <div id="earningsBreakdown" style="background: #e8f5e9; padding: 15px; border-radius: 6px; margin-bottom: 15px; display: none;">
                    <div style="font-weight: 700; color: #2e7d32; margin-bottom: 12px; font-size: 14px;">
                        <i class="fas fa-check-circle"></i> Earnings Breakdown
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 14px;">
                        <div style="border-right: 1px solid #c8e6c9; padding-right: 15px;">
                            <div style="color: #6c757d; margin-bottom: 4px;">Opening Balance (To Retain)</div>
                            <div style="font-size: 18px; font-weight: 700; color: #2e7d32;">₱<span id="breakdownOpeningBalance">0.00</span></div>
                        </div>
                        <div>
                            <div style="color: #6c757d; margin-bottom: 4px;">Earnings (To Submit)</div>
                            <div style="font-size: 18px; font-weight: 700; color: #d41c1c;">₱<span id="breakdownEarnings">0.00</span></div>
                        </div>
                    </div>
                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #c8e6c9;">
                        <div style="color: #6c757d; margin-bottom: 4px;">Total in Register</div>
                        <div style="font-size: 20px; font-weight: 700; color: #1b5e20;">₱<span id="breakdownTotal">0.00</span></div>
                    </div>
                </div>

                <div class="button-group">
                    <button class="btn btn-secondary" onclick="closeEndSessionModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn btn-primary" onclick="confirmEndSession()">
                        <i class="fas fa-check"></i> Confirm & Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Unified Walk-in Guest Check-in Modal -->
    <div class="modal" id="walkin-modal">
        <div class="modal-dialog" style="max-width: 700px;">
            <div class="modal-content">
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
                            autofocus>
                    </div>

                    <div style="text-align: center; background: #f5f5f5; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <p style="margin: 0; font-size: 12px; color: #666; font-weight: 600;">WALK-IN RATE SELECTION</p>
                    </div>

                    <div class="form-group">
                        <label for="member-discount-type" class="required">Rate Type</label>
                        <select id="member-discount-type" class="form-input" required>
                            <option value="">Select rate type</option>
                            <?php foreach ($member_rates as $rate): ?>
                                <option value="<?php echo htmlspecialchars($rate['discount_type']); ?>" 
                                        data-rate-id="<?php echo $rate['rate_id']; ?>"
                                        data-price="<?php echo $rate['price']; ?>">
                                    <?php echo htmlspecialchars($rate['rate_name']); ?> (₱<?php echo number_format($rate['price'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" onclick="closeMemberIDModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn btn-primary" type="button" onclick="submitMemberID()">
                        <i class="fas fa-check"></i> Proceed
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Detail Modal -->
    <div class="modal" id="transactionDetailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Transaction Details</h3>
                <button class="modal-close" onclick="document.getElementById('transactionDetailModal').classList.remove('show')">&times;</button>
            </div>
            <div style="padding:15px;">
                <p><strong>Type:</strong> <span id="td-transaction-type">-</span></p>
                <p><strong>Client:</strong> <span id="td-client-name">-</span></p>
                <p><strong>Amount:</strong> <span id="td-amount">-</span></p>
                <p><strong>Payment Method:</strong> <span id="td-payment-method">-</span></p>
                <p><strong>Description:</strong> <span id="td-description">-</span></p>
                <p><strong>Receipt:</strong> <span id="td-receipt">-</span></p>
                <div id="td-linked" style="margin-top:10px; background:#f8f9fa; padding:10px; border-radius:6px;">Linked records will appear here</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="document.getElementById('transactionDetailModal').classList.remove('show')">Close</button>
            </div>
        </div>
    </div>

    <!-- Renewal Confirmation Modal (convert to base purchase) -->
    <div class="modal" id="renewalConfirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Renewal - No Base Membership Found</h3>
                <button class="modal-close" onclick="document.getElementById('renewalConfirmModal').classList.remove('show')">&times;</button>
            </div>
            <div style="padding:15px;">
                <p id="renewalConfirmMessage" style="color:#333;"></p>
                <input type="hidden" id="renewalClientId">
                <input type="hidden" id="renewalMembershipId">
                <input type="hidden" id="renewalAmount">
                <input type="hidden" id="renewalPaymentMethod">
                <p style="margin-top:10px;">You can convert this renewal into a base membership purchase for the client. Proceed?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="document.getElementById('renewalConfirmModal').classList.remove('show')">Cancel</button>
                <button class="btn btn-primary" onclick="confirmConvertToBase()">Convert to Base Purchase</button>
            </div>
        </div>
    </div>

    <script>
        // Tab switching function
        function switchTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all tab buttons
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            const selectedTab = document.getElementById(tabName);
            if (selectedTab) {
                selectedTab.classList.add('active');
            }

            // Add active class to clicked button
            event.target.classList.add('active');

            // Reset form focus if switching to entry/exit
            if (tabName === 'entry-exit-tab') {
                setTimeout(() => {
                    const checkInInput = document.getElementById('checkin-id');
                    if (checkInInput) checkInInput.focus();
                }, 100);
            }
        }

        // Modal functions
        function showWalkInModal() {
            document.getElementById('walkin-modal').classList.add('show');
            document.getElementById('guest-name').focus();
        }

        function closeWalkInModal() {
            document.getElementById('walkin-modal').classList.remove('show');
            document.getElementById('walkin-form').reset();
            document.getElementById('step-1').style.display = 'block';
            document.getElementById('step-2').style.display = 'none';
            document.getElementById('step-3').style.display = 'none';
        }

        function showMemberIDModal() {
            document.getElementById('member-id-modal').classList.add('show');
            document.getElementById('member-lookup-id').focus();
        }

        function closeMemberIDModal() {
            document.getElementById('member-id-modal').classList.remove('show');
            document.getElementById('member-lookup-id').value = '';
            document.getElementById('member-discount-type').value = '';
        }

        function submitMemberID() {
            const memberId = document.getElementById('member-lookup-id').value.trim();
            const rateType = document.getElementById('member-discount-type').value;
            const rateSelect = document.getElementById('member-discount-type');
            const selectedOption = rateSelect.options[rateSelect.selectedIndex];

            if (!memberId || !rateType) {
                showAlert('Please enter member ID and select a rate type', 'danger');
                return;
            }

            // Get the rate price from the selected option
            const amount = parseFloat(selectedOption.dataset.price) || 0;
            const rateName = selectedOption.textContent;

            // Check if there's an active POS session
            const currentSessionId = document.querySelector('[data-session-id]')?.dataset.sessionId;
            
            if (!currentSessionId) {
                showAlert('No active POS session. Please start a POS session first.', 'danger');
                return;
            }

            // Add walk-in member entry to POS
            fetch('includes/pos_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=add_transaction&session_id=' + encodeURIComponent(currentSessionId) + 
                      '&transaction_type=Walk-in Fee&amount=' + encodeURIComponent(amount) + 
                      '&payment_method=Cash&client_id=' + encodeURIComponent(memberId) + 
                      '&description=Walk-in Member - ' + encodeURIComponent(rateName)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Also add entry/exit record
                    return fetch('includes/entry_exit_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=add_member_walkin&member_id=' + encodeURIComponent(memberId) + '&rate_type=' + encodeURIComponent(rateType)
                    });
                } else {
                    throw new Error(data.message || 'Error creating POS transaction');
                }
            })
            .then(r => r.json())
            .then(data => {
                showAlert('✓ Walk-in member checked in and payment recorded in POS!', 'success');
                closeMemberIDModal();
                loadTransactions();
                loadSummary();
                refreshCurrentlyInside();
                refreshLog();
            })
            .catch(err => {
                console.error('Error:', err);
                showAlert('Error: ' + err.message, 'danger');
            });
        }

        // Walk-in Modal Functions
        let currentStep = 1;

        function nextStep() {
            if (currentStep === 1) {
                const guestName = document.getElementById('guest-name').value.trim();
                const discountType = document.getElementById('discount-type').value;

                if (!guestName || !discountType) {
                    showAlert('Please fill in all required fields', 'danger');
                    return;
                }

                // Move to step 2
                document.getElementById('step-1').style.display = 'none';
                document.getElementById('step-2').style.display = 'block';
                document.getElementById('btn-prev').style.display = 'inline-block';
                document.getElementById('btn-next').style.display = 'inline-block';
                document.getElementById('btn-complete').style.display = 'none';

                // Update summary
                const rateSelect = document.getElementById('discount-type');
                const selectedOption = rateSelect.options[rateSelect.selectedIndex];
                document.getElementById('summary-guest-name').textContent = guestName;
                document.getElementById('summary-rate-type').textContent = selectedOption.textContent;

                updatePaymentAmount();
                currentStep = 2;
            } else if (currentStep === 2) {
                const paymentMethod = document.getElementById('guest-payment-method').value;

                if (!paymentMethod) {
                    showAlert('Please select a payment method', 'danger');
                    return;
                }

                // Move to step 3
                document.getElementById('step-2').style.display = 'none';
                document.getElementById('step-3').style.display = 'block';
                document.getElementById('btn-prev').style.display = 'inline-block';
                document.getElementById('btn-next').style.display = 'none';
                document.getElementById('btn-complete').style.display = 'inline-block';

                // Update review
                const guestName = document.getElementById('guest-name').value.trim();
                const guestPhone = document.getElementById('guest-phone').value.trim() || 'Not provided';
                const rateSelect = document.getElementById('discount-type');
                const selectedOption = rateSelect.options[rateSelect.selectedIndex];
                const amount = parseFloat(selectedOption.dataset.price) || 0;

                document.getElementById('review-name').textContent = guestName;
                document.getElementById('review-phone').textContent = guestPhone || 'No phone provided';
                document.getElementById('review-type').textContent = selectedOption.textContent;
                document.getElementById('review-amount').textContent = '₱' + amount.toFixed(2);
                document.getElementById('review-payment-method').textContent = paymentMethod;

                currentStep = 3;
            }
        }

        function previousStep() {
            if (currentStep === 2) {
                document.getElementById('step-2').style.display = 'none';
                document.getElementById('step-1').style.display = 'block';
                document.getElementById('btn-prev').style.display = 'none';
                document.getElementById('btn-next').style.display = 'inline-block';
                document.getElementById('btn-complete').style.display = 'none';
                currentStep = 1;
            } else if (currentStep === 3) {
                document.getElementById('step-3').style.display = 'none';
                document.getElementById('step-2').style.display = 'block';
                document.getElementById('btn-prev').style.display = 'inline-block';
                document.getElementById('btn-next').style.display = 'inline-block';
                document.getElementById('btn-complete').style.display = 'none';
                currentStep = 2;
            }
        }

        function updatePaymentAmount() {
            const rateSelect = document.getElementById('discount-type');
            const selectedOption = rateSelect.options[rateSelect.selectedIndex];
            const amount = parseFloat(selectedOption.dataset.price) || 0;
            const description = selectedOption.dataset.description || '';

            document.getElementById('step-2-amount').textContent = '₱' + amount.toFixed(2);
            
            if (description) {
                const descElement = document.getElementById('rate-description');
                descElement.textContent = description;
                descElement.style.display = 'block';
            }
        }

        function processGuestPayment() {
            const confirm = document.getElementById('guest-payment-confirm').checked;
            if (!confirm) {
                showAlert('Please confirm the payment information', 'danger');
                return;
            }

            // Get all values
            const guestName = document.getElementById('guest-name').value.trim();
            const guestPhone = document.getElementById('guest-phone').value.trim();
            const rateSelect = document.getElementById('discount-type');
            const selectedOption = rateSelect.options[rateSelect.selectedIndex];
            const rateType = rateSelect.value;
            const amount = parseFloat(selectedOption.dataset.price) || 0;
            const paymentMethod = document.getElementById('guest-payment-method').value;
            const rateName = selectedOption.textContent;

            // Check if there's an active POS session
            const currentSessionId = document.querySelector('[data-session-id]')?.dataset.sessionId;
            
            if (!currentSessionId) {
                showAlert('No active POS session. Please start a POS session first.', 'danger');
                return;
            }

            // Create POS transaction for walk-in guest payment
            fetch('includes/pos_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=add_transaction&session_id=' + encodeURIComponent(currentSessionId) + 
                      '&transaction_type=Walk-in Fee&amount=' + encodeURIComponent(amount) + 
                      '&payment_method=' + encodeURIComponent(paymentMethod) + 
                      '&client_name=' + encodeURIComponent(guestName) + 
                      '&description=Walk-in Guest - ' + encodeURIComponent(rateName) + 
                      (guestPhone ? '&notes=Phone: ' + encodeURIComponent(guestPhone) : '')
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Also add entry/exit record
                    return fetch('includes/entry_exit_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=add_guest_walkin&guest_name=' + encodeURIComponent(guestName) + 
                              '&guest_phone=' + encodeURIComponent(guestPhone) + 
                              '&rate_type=' + encodeURIComponent(rateType) + 
                              '&amount=' + encodeURIComponent(amount)
                    });
                } else {
                    throw new Error(data.message || 'Error creating POS transaction');
                }
            })
            .then(r => r.json())
            .then(data => {
                showAlert('✓ Guest checked in and payment ₱' + amount.toFixed(2) + ' added to POS!', 'success');
                closeWalkInModal();
                loadTransactions();
                loadSummary();
                refreshCurrentlyInside();
                refreshLog();
            })
            .catch(err => {
                console.error('Error:', err);
                showAlert('Error: ' + err.message, 'danger');
            });
        }

        // Placeholder functions for entry/exit operations
        function processCheckIn() {
            const id = document.getElementById('checkin-id').value.trim();
            if (!id) {
                showAlert('Please scan or enter a member ID', 'danger');
                return;
            }

            fetch('includes/entry_exit_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=check_in&identifier=' + encodeURIComponent(id)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('✓ ' + data.member_name + ' checked in successfully!', 'success');
                    document.getElementById('checkin-id').value = '';
                    refreshCurrentlyInside();
                    refreshLog();
                } else {
                    showAlert(data.message || 'Error during check-in', 'danger');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                showAlert('Error processing check-in', 'danger');
            });
        }

        function processCheckOut() {
            const id = document.getElementById('checkout-id').value.trim();
            if (!id) {
                showAlert('Please scan or enter an ID', 'danger');
                return;
            }

            fetch('includes/entry_exit_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=check_out&identifier=' + encodeURIComponent(id)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('✓ ' + data.member_name + ' checked out successfully!', 'success');
                    document.getElementById('checkout-id').value = '';
                    refreshCurrentlyInside();
                    refreshLog();
                } else {
                    showAlert(data.message || 'Error during check-out', 'danger');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                showAlert('Error processing check-out', 'danger');
            });
        }

        function refreshCurrentlyInside() {
            // Check if elements exist before trying to access them
            const container = document.getElementById('currently-inside-grid');
            if (!container) {
                // Entry/Exit tab elements don't exist, skip refresh
                return;
            }
            
            fetch('includes/entry_exit_handler.php?action=get_currently_inside')
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    if (!data.success) {
                        container.innerHTML = '<div class="empty-state" style="grid-column: 1 / -1;"><p>' + escapeHtml(data.message || 'Error loading data') + '</p></div>';
                        return;
                    }
                    
                    // Update the "Currently Inside" stat card
                    const peopleCount = data.people ? data.people.length : 0;
                    const insideBtn = document.getElementById('currently-inside');
                    if (insideBtn) insideBtn.textContent = peopleCount;

                    if (data.people && data.people.length > 0) {
                        container.innerHTML = data.people.map(m => `
                            <div class="person-card">
                                <div class="person-info">
                                    <h4>${escapeHtml(m.name || 'Guest')}</h4>
                                    ${m.client_id ? `<p><strong>ID:</strong> ${m.client_id}</p>` : ''}
                                    <p><strong>Type:</strong> ${m.client_type || 'Walk-in'}</p>
                                    <p><strong>In:</strong> ${m.time_in}</p>
                                    <p><strong>Duration:</strong> ${m.duration}</p>
                                </div>
                                <div class="person-actions">
                                    <button class="btn-checkout-inline" onclick="quickCheckOut(${m.attendance_id})" title="Check out this person">
                                        <i class="fas fa-sign-out-alt"></i> Check Out
                                    </button>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<div class="empty-state" style="grid-column: 1 / -1;"><i class="fas fa-inbox"></i><p>No one is currently inside</p></div>';
                    }
                })
                .catch(err => {
                    console.error('Error loading currently inside:', err);
                    const container = document.getElementById('currently-inside-grid');
                    if (container) container.innerHTML = '<div class="empty-state" style="grid-column: 1 / -1;"><p>Error loading data</p></div>';
                });
        }

        function quickCheckOut(memberId) {
            fetch('includes/entry_exit_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=check_out&identifier=' + encodeURIComponent(memberId)
            })
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    showAlert('✓ Checked out successfully!', 'success');
                    refreshCurrentlyInside();
                    refreshLog();
                } else {
                    showAlert(data.message || 'Error checking out', 'danger');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                showAlert('Error checking out: ' + err.message, 'danger');
            });
        }

        function refreshLog() {
            // Check if elements exist before trying to access them
            const startDateEl = document.getElementById('log-start-date');
            const endDateEl = document.getElementById('log-end-date');
            const tbodyEl = document.getElementById('log-table-body');
            
            if (!startDateEl || !endDateEl || !tbodyEl) {
                // Entry/Exit tab elements don't exist, skip refresh
                return;
            }
            
            const startDate = startDateEl.value || new Date().toISOString().split('T')[0];
            const endDate = endDateEl.value || new Date().toISOString().split('T')[0];

            fetch(`includes/entry_exit_handler.php?action=get_log_by_date_range&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`)
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    const tbody = document.getElementById('log-table-body');
                    
                    if (!data.success) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center"><p>' + escapeHtml(data.message || 'Error loading data') + '</p></td></tr>';
                        return;
                    }
                    
                    // Update stats from today's log
                    const todayStr = new Date().toISOString().split('T')[0];
                    if (startDate === todayStr && endDate === todayStr) {
                        fetch('includes/entry_exit_handler.php?action=get_stats')
                            .then(r => {
                                if (!r.ok) throw new Error('HTTP ' + r.status);
                                return r.json();
                            })
                            .then(stats => {
                                if (stats.success && stats.stats) {
                                    const el1 = document.getElementById('today-checkins');
                                    const el2 = document.getElementById('members-count');
                                    const el3 = document.getElementById('walkins-count');
                                    if (el1) el1.textContent = stats.stats.today_checkins || 0;
                                    if (el2) el2.textContent = stats.stats.members_today || 0;
                                    if (el3) el3.textContent = Math.max(0, stats.stats.walkins_today || 0);
                                }
                            })
                            .catch(err => console.error('Error loading stats:', err));
                    }

                    if (data.logs && data.logs.length > 0) {
                        tbody.innerHTML = data.logs.map(e => `
                            <tr>
                                <td>${escapeHtml(e.client_id || e.guest_name || 'Guest')}</td>
                                <td>${escapeHtml(e.name || 'Unknown')}</td>
                                <td>${e.client_type || 'Guest'}</td>
                                <td>${e.time_in}</td>
                                <td>${e.time_out || '-'}</td>
                                <td>${e.duration || '-'}</td>
                                <td><span class="badge badge-${e.status === 'Completed' ? 'secondary' : 'primary'}">${e.status || 'Inside'}</span></td>
                                <td><button class="btn-sm" onclick="showCheckOutModal(${e.attendance_id})">Check Out</button></td>
                            </tr>
                        `).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="empty-state"><i class="fas fa-clipboard"></i><p>No entries for selected dates</p></div></td></tr>';
                    }
                })
                .catch(err => {
                    console.error('Error loading log:', err);
                    const tbody = document.getElementById('log-table-body');
                    if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center"><p>Error loading data</p></td></tr>';
                });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showCheckOutModal(attendanceId) {
            if (!confirm('Are you sure you want to check out this person?')) {
                return;
            }
            quickCheckOut(attendanceId);
        }

        function filterLogByDate() {
            refreshLog();
        }

        function resetLogFilter() {
            document.getElementById('log-start-date').value = '';
            document.getElementById('log-end-date').value = '';
            refreshLog();
        }

        function exportLog() {
            showAlert('Export functionality coming soon', 'info');
        }

        // Initialize entry/exit on tab switch and auto-refresh every 30 seconds
        function initEntryExitTab() {
            refreshCurrentlyInside();
            refreshLog();
        }

        // Auto-refresh entry/exit data when on that tab
        let entryExitRefreshInterval = null;
        function startEntryExitRefresh() {
            if (!entryExitRefreshInterval) {
                initEntryExitTab();
                entryExitRefreshInterval = setInterval(initEntryExitTab, 30000); // Refresh every 30 seconds
            }
        }

        function stopEntryExitRefresh() {
            if (entryExitRefreshInterval) {
                clearInterval(entryExitRefreshInterval);
                entryExitRefreshInterval = null;
            }
        }
        
        // Auto-load entry/exit when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initEntryExitTab();
        });

        // POS Report Functions
        function loadPOSReportSessions() {
            const container = document.getElementById('pos-report-container');
            container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading sessions...</p></div>';

            fetch('includes/pos_handler.php?action=get_recent_sessions')
                .then(r => {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status + ': ' + r.statusText);
                    }
                    return r.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success && data.sessions && data.sessions.length > 0) {
                            displayPOSReportSessions(data.sessions);
                        } else {
                            container.innerHTML = '<div class="empty-state"><i class="fas fa-history"></i><p>No recent POS sessions found</p></div>';
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e, 'Response:', text);
                        container.innerHTML = '<div class="empty-state" style="color: #d41c1c;"><i class="fas fa-exclamation-triangle"></i><p>Error parsing response</p></div>';
                    }
                })
                .catch(err => {
                    console.error('Error loading POS report:', err);
                    container.innerHTML = '<div class="empty-state" style="color: #d41c1c;"><i class="fas fa-exclamation-triangle"></i><p>Error: ' + err.message + '</p></div>';
                });
        }

        function displayPOSReportSessions(sessions) {
            const container = document.getElementById('pos-report-container');
            
            if (!sessions || sessions.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-history"></i><p>No POS sessions found</p></div>';
                return;
            }

            let html = '';
            
            // Calculate totals
            let totalSales = 0;
            let totalCash = 0;
            
            sessions.forEach(s => {
                totalSales += parseFloat(s.total_sales || 0);
                totalCash += parseFloat(s.cash_total || 0);
            });

            // Summary cards
            html += `<div class="stats-grid-small" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #d41c1c 0%, #b81515 100%);"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-content">
                        <h3>Total Sales</h3>
                        <p class="stat-number">₱${totalSales.toFixed(2)}</p>
                        <span class="stat-change positive"><i class="fas fa-arrow-up"></i> ${sessions.length} session(s)</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);"><i class="fas fa-money-bill"></i></div>
                    <div class="stat-content">
                        <h3>Cash Payment</h3>
                        <p class="stat-number">₱${totalCash.toFixed(2)}</p>
                        <span class="stat-change neutral">${((totalCash/totalSales)*100 || 0).toFixed(1)}% of total</span>
                    </div>
                </div>
            </div>`;

            // Sessions table
            html += `<div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Opening</th>
                            <th>Closing</th>
                            <th>Total Sales</th>
                            <th>Cash</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>`;
            
            sessions.forEach(session => {
                const startTime = new Date(session.start_time).toLocaleString();
                const endTime = session.end_time ? new Date(session.end_time).toLocaleString() : '-';
                const statusClass = session.status === 'Closed' ? 'badge-success' : 'badge-warning';
                
                html += `<tr>
                    <td>${session.employee_name}</td>
                    <td style="font-size: 12px;">${startTime}</td>
                    <td style="font-size: 12px;">${endTime}</td>
                    <td>₱${parseFloat(session.opening_balance || 0).toFixed(2)}</td>
                    <td>₱${parseFloat(session.closing_balance || 0).toFixed(2)}</td>
                    <td style="font-weight: 600; color: #d41c1c;">₱${parseFloat(session.total_sales || 0).toFixed(2)}</td>
                    <td>₱${parseFloat(session.cash_total || 0).toFixed(2)}</td>
                    <td><span class="badge ${statusClass}">${session.status}</span></td>
                </tr>`;
            });
            
            html += `</tbody>
                </table>
            </div>`;
            
            container.innerHTML = html;
        }

        function viewSessionDetails(sessionId) {
            alert('Session #' + sessionId + ' details view coming soon!');
        }

        // Load POS report when tab is clicked
        document.addEventListener('DOMContentLoaded', function() {
            const posReportTab = document.querySelector('[onclick*="pos-report-tab"]');
            if (posReportTab) {
                posReportTab.addEventListener('click', loadPOSReportSessions);
            }
        });
    </script>

    <script src="js/pos.js"></script>
</body>
</html>

