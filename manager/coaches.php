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
    // Total Active Coaches
    $totalCoaches = $conn->query("SELECT COUNT(*) FROM coach WHERE status = 'Active'")->fetchColumn();
    
    // Total Clients Assigned
    $totalAssignments = $conn->query("SELECT COUNT(DISTINCT client_id) FROM clients WHERE assigned_coach_id IS NOT NULL")->fetchColumn();
    
    // Average Clients per Coach
    $avgClientsPerCoach = $totalCoaches > 0 ? round($totalAssignments / $totalCoaches, 1) : 0;
    
    // Coaches on Leave
    $coachesOnLeave = $conn->query("SELECT COUNT(*) FROM coach WHERE status = 'On Leave'")->fetchColumn();
    
} catch (Exception $e) {
    $totalCoaches = 0;
    $totalAssignments = 0;
    $avgClientsPerCoach = 0;
    $coachesOnLeave = 0;
}

// Fetch all coaches with their details
try {
    $stmt = $conn->query("
        SELECT 
            c.*,
            COUNT(DISTINCT cl.client_id) as total_clients,
            COUNT(DISTINCT a.assessment_id) as total_assessments,
            e.first_name as creator_first_name,
            e.last_name as creator_last_name
        FROM coach c
        LEFT JOIN clients cl ON c.coach_id = cl.assigned_coach_id
        LEFT JOIN assessment a ON c.coach_id = a.coach_id
        LEFT JOIN employees e ON c.created_by = e.employee_id
        GROUP BY c.coach_id
        ORDER BY c.created_at DESC
    ");
    $coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $coaches = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coaches Management - Empire Fitness</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/manager-components.css">
    <link rel="stylesheet" href="css/coaches.css">
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
                <h1>Coaches Management</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Staff Management / Coaches
                </p>
            </div>
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search coaches...">
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
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-content">
                    <h3>Active Coaches</h3>
                    <p class="stat-number"><?php echo number_format($totalCoaches); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-check-circle"></i> Currently working
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Assignments</h3>
                    <p class="stat-number"><?php echo number_format($totalAssignments); ?></p>
                    <span class="stat-change positive">
                        <i class="fas fa-link"></i> Coach-Client pairs
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3>Avg Clients/Coach</h3>
                    <p class="stat-number"><?php echo number_format($avgClientsPerCoach, 1); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-balance-scale"></i> Workload balance
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-content">
                    <h3>On Leave</h3>
                    <p class="stat-number"><?php echo number_format($coachesOnLeave); ?></p>
                    <span class="stat-change neutral">
                        <i class="fas fa-umbrella-beach"></i> Currently away
                    </span>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="action-left">
                <button class="btn-primary" onclick="openAddCoachModal()">
                    <i class="fas fa-plus"></i> Add New Coach
                </button>
                <button class="btn-secondary" onclick="openBulkAssignModal()">
                    <i class="fas fa-users-cog"></i> Bulk Assign
                </button>
            </div>
            <div class="action-right">
                <select id="filterStatus" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="On Leave">On Leave</option>
                </select>
                <select id="sortBy" class="filter-select">
                    <option value="name">Sort by Name</option>
                    <option value="clients">Sort by Clients</option>
                    <option value="experience">Sort by Experience</option>
                    <option value="recent">Most Recent</option>
                </select>
            </div>
        </div>

        <!-- Coaches Grid -->
        <div class="coaches-grid" id="coachesGrid">
            <?php foreach ($coaches as $coach): ?>
            <div class="coach-card" data-status="<?php echo $coach['status']; ?>" data-coach-id="<?php echo $coach['coach_id']; ?>">
                <div class="coach-card-header">
                    <div class="coach-avatar-large">
                        <?php 
                            $imagePath = null;
                            $imageUrl = null;
                            
                            if (!empty($coach['profile_image'])) {
                                $profileImage = $coach['profile_image'];
                                
                                // Clean up the path - remove leading slashes
                                $profileImage = ltrim($profileImage, '/\\');
                                
                                // Check 1: Local uploads/coaches folder (empirefitness)
                                $localPath = __DIR__ . '/../uploads/coaches/' . basename($profileImage);
                                if (file_exists($localPath)) {
                                    $imageUrl = '../uploads/coaches/' . basename($profileImage);
                                    $mtime = filemtime($localPath);
                                    $imageUrl .= '?t=' . $mtime;
                                }
                                // Check 2: External pbl_project assets/images/profile_photos folder (NEW)
                                elseif (!$imageUrl) {
                                    $filename = basename($profileImage);
                                    $externalPath = 'C:\\xampp\\htdocs\\pbl_project\\assets\\images\\profile_photos\\' . $filename;
                                    if (file_exists($externalPath)) {
                                        $imageUrl = '../../pbl_project/assets/images/profile_photos/' . $filename;
                                        $mtime = filemtime($externalPath);
                                        $imageUrl .= '?t=' . $mtime;
                                    }
                                }
                                // Check 3: Fallback to old external coach_photos folder
                                elseif (!$imageUrl) {
                                    $filename = basename($profileImage);
                                    $externalPath = 'C:\\xampp\\htdocs\\pbl_project\\uploads\\coach_photos\\' . $filename;
                                    if (file_exists($externalPath)) {
                                        $imageUrl = '../../pbl_project/uploads/coach_photos/' . $filename;
                                        $mtime = filemtime($externalPath);
                                        $imageUrl .= '?t=' . $mtime;
                                    }
                                }
                            }
                            
                            if (!empty($imageUrl)): 
                        ?>
                            <img src="<?php echo $imageUrl; ?>" alt="Coach" onerror="this.parentElement.innerHTML='<?php echo strtoupper(substr($coach['first_name'], 0, 1) . substr($coach['last_name'], 0, 1)); ?>'">
                        <?php else: ?>
                            <?php echo strtoupper(substr($coach['first_name'], 0, 1) . substr($coach['last_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <span class="coach-status <?php echo strtolower(str_replace(' ', '-', $coach['status'])); ?>">
                        <?php echo $coach['status']; ?>
                    </span>
                </div>

                <div class="coach-card-body">
                    <h3 class="coach-name"><?php echo htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']); ?></h3>
                    <p class="coach-specialization">
                        <i class="fas fa-dumbbell"></i> <?php echo htmlspecialchars($coach['specialization'] ?? 'General Fitness'); ?>
                    </p>

                    <div class="coach-stats-mini">
                        <div class="stat-mini">
                            <i class="fas fa-users"></i>
                            <span><?php echo $coach['total_clients']; ?> Clients</span>
                        </div>
                        <div class="stat-mini">
                            <i class="fas fa-clipboard-check"></i>
                            <span><?php echo $coach['total_assessments']; ?> Assessments</span>
                        </div>
                        <div class="stat-mini">
                            <i class="fas fa-award"></i>
                            <span><?php echo $coach['experience_years'] ?? 0; ?> Yrs Exp</span>
                        </div>
                    </div>

                    <div class="coach-info">
                        <div class="info-row">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($coach['email']); ?></span>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($coach['phone'] ?? 'N/A'); ?></span>
                        </div>
                        <?php if ($coach['certification']): ?>
                        <div class="info-row">
                            <i class="fas fa-certificate"></i>
                            <span><?php echo htmlspecialchars($coach['certification']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="coach-card-footer">
                    <button class="btn-action btn-view" onclick="viewCoachProfile(<?php echo $coach['coach_id']; ?>)">
                        <i class="fas fa-eye"></i> View Profile
                    </button>
                    <button class="btn-action btn-assign" onclick="openAssignModal(<?php echo $coach['coach_id']; ?>)">
                        <i class="fas fa-user-plus"></i> Assign Client
                    </button>
                    <div class="dropdown">
                        <button class="btn-action btn-more" onclick="toggleDropdown(this)">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="#" onclick="editCoach(<?php echo $coach['coach_id']; ?>)">
                                <i class="fas fa-edit"></i> Edit Details
                            </a>
                            <a href="#" onclick="viewSchedule(<?php echo $coach['coach_id']; ?>)">
                                <i class="fas fa-calendar"></i> View Schedule
                            </a>
                            <a href="#" onclick="viewPerformance(<?php echo $coach['coach_id']; ?>)">
                                <i class="fas fa-chart-line"></i> Performance
                            </a>
                            <a href="#" onclick="changeStatus(<?php echo $coach['coach_id']; ?>, '<?php echo $coach['status']; ?>')">
                                <i class="fas fa-toggle-on"></i> Change Status
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($coaches)): ?>
        <div class="empty-state">
            <i class="fas fa-user-tie"></i>
            <h3>No Coaches Found</h3>
            <p>Start by adding your first coach to the system.</p>
            <button class="btn-primary" onclick="openAddCoachModal()">
                <i class="fas fa-plus"></i> Add First Coach
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Coach Modal -->
    <div id="addCoachModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Add New Coach</h2>
                <button class="modal-close" onclick="closeAddCoachModal()">&times;</button>
            </div>
            <form id="addCoachForm" method="POST" action="includes/coaches_handler.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_coach">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-section">
                            <h3>Personal Information</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name *</label>
                                    <input type="text" name="first_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Middle Name</label>
                                    <input type="text" name="middle_name" placeholder="Optional">
                                </div>
                                <div class="form-group">
                                    <label>Last Name *</label>
                                    <input type="text" name="last_name" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label>Phone (11 digits)</label>
                                    <input type="tel" name="phone" pattern="[0-9]{11}" placeholder="09XXXXXXXXX" maxlength="11" title="Contact number must be exactly 11 digits">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Profile Image</label>
                                <input type="file" name="profile_image" accept="image/*">
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Professional Details</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Specialization</label>
                                    <input type="text" name="specialization" placeholder="e.g., Strength Training, Yoga">
                                </div>
                                <div class="form-group">
                                    <label>Certification</label>
                                    <input type="text" name="certification" placeholder="e.g., ACE, NASM">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Years of Experience</label>
                                    <input type="number" name="experience_years" min="0" max="50">
                                </div>
                                <div class="form-group">
                                    <label>Hourly Rate</label>
                                    <input type="number" name="hourly_rate" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Hire Date *</label>
                                    <input type="date" name="hire_date" required>
                                </div>
                                <div class="form-group">
                                    <label>Status *</label>
                                    <select name="status" required>
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                        <option value="On Leave">On Leave</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-section full-width">
                            <h3>Account Setup</h3>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <span>A temporary password will be automatically generated and saved to the credentials folder. The coach will receive their login details via email (feature pending).</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeAddCoachModal()">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Add Coach
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Client Modal -->
    <div id="assignClientModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Assign Client to Coach</h2>
                <button class="modal-close" onclick="closeAssignModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="assignClientForm" method="POST" action="includes/coaches_handler.php">
                    <input type="hidden" name="action" value="assign_client">
                    <input type="hidden" name="coach_id" id="assignCoachId">
                    <input type="hidden" name="client_id" id="selectedClientId">
                    
                    <!-- Search Box -->
                    <div class="client-search-container">
                        <div class="search-input-wrapper">
                            
                            <input 
                                type="text" 
                                id="clientSearchInput" 
                                class="client-search-input"
                                placeholder="Search by name, email or phone..."
                                autocomplete="off"
                            >
                            <button type="button" class="search-clear-btn" onclick="clearClientSearch()" style="display: none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="search-info" id="searchInfo">
                            <span id="clientCountDisplay">Loading clients...</span>
                        </div>
                    </div>

                    <!-- Clients Grid -->
                    <div class="clients-grid" id="clientsGrid">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading clients...</p>
                        </div>
                    </div>

                    <!-- Assignment Notes -->
                    <div class="form-group" style="margin-top: 20px;">
                        <label for="assignmentNotes"><i class="fas fa-sticky-note"></i> Assignment Notes</label>
                        <textarea 
                            id="assignmentNotes"
                            name="notes" 
                            rows="3" 
                            placeholder="Optional notes about this assignment..."
                            style="width: 100%; padding: 10px; border: 2px solid #dee2e6; border-radius: 6px; font-family: inherit;"
                        ></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAssignModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" form="assignClientForm" class="btn-primary" id="assignClientBtn">
                    <i class="fas fa-check"></i> Assign Client
                </button>
            </div>
        </div>
    </div>

    <script src="js/sidebar.js"></script>
    <script src="js/manager-dashboard.js"></script>
    <script src="js/coaches.js"></script>
</body>
</html>