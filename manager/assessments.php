```php
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
    $pendingRequests = $conn->query("SELECT COUNT(*) FROM assessment_inquiries WHERE status = 'pending'")->fetchColumn();
    $totalAssessments = $conn->query("SELECT COUNT(*) FROM assessment")->fetchColumn();
    $monthAssessments = $conn->query("
        SELECT COUNT(*) FROM assessment 
        WHERE MONTH(assessment_date) = MONTH(CURDATE()) 
        AND YEAR(assessment_date) = YEAR(CURDATE())
    ")->fetchColumn();
} catch (Exception $e) {
    $pendingRequests = $totalAssessments = $monthAssessments = 0;
}

// Fetch pending assessment inquiries
try {
    $stmt = $conn->query("
        SELECT *
        FROM assessment_inquiries
        WHERE status = 'pending'
        ORDER BY created_at DESC
    ");
    $pendingInquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pendingInquiries = [];
}

// Fetch all completed assessments with client info
try {
    $stmt = $conn->query("
        SELECT 
            a.*,
            c.first_name,
            c.middle_name,
            c.last_name,
            c.email,
            c.phone,
            co.first_name as coach_first_name,
            co.last_name as coach_last_name
        FROM assessment a
        JOIN clients c ON a.client_id = c.client_id
        LEFT JOIN coach co ON a.coach_id = co.coach_id
        ORDER BY a.assessment_date DESC
    ");
    $assessmentsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $assessmentsList = [];
}

// Fetch available coaches for assignment
try {
    $stmt = $conn->query("
        SELECT 
            coach_id,
            CONCAT(first_name, ' ', last_name) as coach_name,
            specialization
        FROM coach
        WHERE status = 'Active'
        ORDER BY first_name ASC
    ");
    $availableCoaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $availableCoaches = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Requests - Empire Fitness</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/manager-components.css">
    <link rel="stylesheet" href="css/assessments.css">
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
                <h1>Assessment Management</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Assessments
                </p>
            </div>
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search assessments...">
                </div>
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

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="stat-content">
                    <h3>Pending Requests</h3>
                    <p class="stat-number"><?php echo number_format($pendingRequests); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-hourglass-half"></i> Awaiting review
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Assessments</h3>
                    <p class="stat-number"><?php echo number_format($totalAssessments); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-clipboard-list"></i> All time
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3>This Month</h3>
                    <p class="stat-number"><?php echo number_format($monthAssessments); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-calendar"></i> Current month
                    </span>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab-btn active" data-tab="pending">
                    <i class="fas fa-inbox"></i> Pending Requests 
                    <span class="badge"><?php echo count($pendingInquiries); ?></span>
                </button>
                <button class="tab-btn" data-tab="assessments">
                    <i class="fas fa-clipboard-list"></i> All Assessments
                </button>
            </div>
        </div>

        <!-- PENDING REQUESTS TAB -->
        <div class="tab-content active" id="pending-tab">
            <div class="action-bar">
                <div class="action-left">
                    <h3><i class="fas fa-inbox"></i> Assessment Requests</h3>
                </div>
                <div class="action-right">
                    <input type="text" id="searchPending" class="search-input" placeholder="Search requests...">
                </div>
            </div>

            <?php if (!empty($pendingInquiries)): ?>
            <div class="inquiries-grid" id="inquiriesGrid">
                <?php foreach ($pendingInquiries as $inquiry): ?>
                <div class="inquiry-card" data-inquiry-id="<?php echo $inquiry['inquiry_id']; ?>">
                    <div class="inquiry-header">
                        <div class="inquiry-info">
                            <h3><?php echo htmlspecialchars($inquiry['first_name'] . ' ' . $inquiry['last_name']); ?></h3>
                            <p class="inquiry-email">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($inquiry['email']); ?>
                            </p>
                            <?php if ($inquiry['phone']): ?>
                            <p class="inquiry-phone">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($inquiry['phone']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <span class="inquiry-date">
                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($inquiry['created_at'])); ?>
                        </span>
                    </div>

                    <div class="inquiry-body">
                        <?php if ($inquiry['fitness_goals']): ?>
                        <div class="info-section">
                            <label>Fitness Goals:</label>
                            <p><?php echo htmlspecialchars($inquiry['fitness_goals']); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if ($inquiry['medical_conditions']): ?>
                        <div class="info-section">
                            <label>Medical Conditions:</label>
                            <p><?php echo htmlspecialchars($inquiry['medical_conditions']); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if ($inquiry['preferred_schedule']): ?>
                        <div class="info-section">
                            <label>Preferred Schedule:</label>
                            <p><?php echo htmlspecialchars($inquiry['preferred_schedule']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="inquiry-actions">
                        <button class="btn-action btn-approve" onclick="openProcessModal(<?php echo $inquiry['inquiry_id']; ?>, '<?php echo htmlspecialchars(addslashes($inquiry['first_name'] . ' ' . $inquiry['last_name'])); ?>', '<?php echo htmlspecialchars($inquiry['email']); ?>')">
                            <i class="fas fa-check"></i> Process & Assign
                        </button>
                        <button class="btn-action btn-reject" onclick="rejectRequest(<?php echo $inquiry['inquiry_id']; ?>)">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Pending Requests</h3>
                <p>All assessment requests have been processed.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ALL ASSESSMENTS TAB -->
        <div class="tab-content" id="assessments-tab">
            <div class="action-bar">
                <div class="action-left">
                    <h3><i class="fas fa-clipboard-list"></i> Assessment Records</h3>
                </div>
                <div class="action-right">
                    <input type="text" id="searchAssessments" class="search-input" placeholder="Search assessments...">
                </div>
            </div>

            <?php if (!empty($assessmentsList)): ?>
            <div class="assessments-table">
                <table>
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>Assessment Date</th>
                            <th>Coach</th>
                            <th>Measurements</th>
                            <th>Goals</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assessmentsList as $assessment): ?>
                        <tr class="assessment-row">
                            <td>
                                <div class="client-info">
                                    <strong><?php echo htmlspecialchars($assessment['first_name'] . ' ' . $assessment['last_name']); ?></strong>
                                    <small><?php echo htmlspecialchars($assessment['email']); ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="date-badge">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($assessment['assessment_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($assessment['coach_id']): ?>
                                    <strong><?php echo htmlspecialchars($assessment['coach_first_name'] . ' ' . $assessment['coach_last_name']); ?></strong>
                                <?php else: ?>
                                    <span class="unassigned">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="metrics">
                                    <?php if ($assessment['weight']): ?>
                                        <span><i class="fas fa-weight"></i> <?php echo $assessment['weight']; ?> kg</span>
                                    <?php endif; ?>
                                    <?php if ($assessment['height']): ?>
                                        <span><i class="fas fa-ruler-vertical"></i> <?php echo $assessment['height']; ?> cm</span>
                                    <?php endif; ?>
                                    <?php if ($assessment['body_fat_percentage']): ?>
                                        <span><i class="fas fa-percentage"></i> <?php echo $assessment['body_fat_percentage']; ?>% BF</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <p class="goals-text"><?php echo htmlspecialchars(substr($assessment['fitness_goals'] ?? 'N/A', 0, 60)); ?></p>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-sm btn-view" onclick="viewDetails(<?php echo $assessment['assessment_id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn-sm btn-edit" onclick="editAssessment(<?php echo $assessment['assessment_id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn-sm btn-delete" onclick="deleteAssessment(<?php echo $assessment['assessment_id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h3>No Assessment Records</h3>
                <p>Assessment data will appear here once requests are processed.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // Include modals (they need $availableCoaches)
    include __DIR__ . '/includes/modals.php';
    ?>

    <script src="js/sidebar.js"></script>
    <script src="js/manager-dashboard.js"></script>
    <script src="js/assessments.js"></script>
</body>
</html>
```