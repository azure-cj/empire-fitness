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

// Get coach ID from URL
$coachId = $_GET['id'] ?? null;

if (!$coachId) {
    header("Location: coaches.php");
    exit;
}

// Fetch coach details with statistics
try {
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            COUNT(DISTINCT cl.client_id) as total_clients,
            COUNT(DISTINCT a.assessment_id) as total_assessments,
            COUNT(DISTINCT br.request_id) as total_pt_sessions,
            e.first_name as creator_first_name,
            e.last_name as creator_last_name
        FROM coach c
        LEFT JOIN clients cl ON c.coach_id = cl.assigned_coach_id
        LEFT JOIN assessment a ON c.coach_id = a.coach_id
        LEFT JOIN booking_requests br ON c.coach_id = br.coach_id AND br.booking_type = 'PT Session'
        LEFT JOIN employees e ON c.created_by = e.employee_id
        WHERE c.coach_id = ?
        GROUP BY c.coach_id
    ");
    $stmt->execute([$coachId]);
    $coach = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coach) {
        header("Location: coaches.php");
        exit;
    }
    
    // Fetch assigned clients
    $stmt = $conn->prepare("
        SELECT 
            client_id, first_name, last_name, email, phone,
            join_date, status
        FROM clients
        WHERE assigned_coach_id = ?
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$coachId]);
    $assignedClients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch recent assessments
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            c.first_name, c.last_name
        FROM assessment a
        JOIN clients c ON a.client_id = c.client_id
        WHERE a.coach_id = ?
        ORDER BY a.assessment_date DESC
        LIMIT 5
    ");
    $stmt->execute([$coachId]);
    $recentAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Profile - <?php echo htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']); ?></title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/coaches.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="../css/realtime-notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #b7b7b7 0%, #8e8e8e 100%);
            color: white;
            padding: 3rem 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .profile-avatar-xl {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            color: #667eea;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }
        
        .profile-avatar-xl img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info-main h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .profile-badges {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            backdrop-filter: blur(10px);
        }
        
        .badge-status-active {
            background: rgba(76, 175, 80, 0.9) !important;
        }
        
        .badge-status-inactive {
            background: rgba(244, 67, 54, 0.9) !important;
        }
        
        .badge-status-on-leave {
            background: rgba(255, 152, 0, 0.9) !important;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        .info-card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .info-card h3 {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #4CAF50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            color: #333;
            text-align: right;
        }
        
        .clients-list {
            list-style: none;
            padding: 0;
        }
        
        .client-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .client-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .client-avatar-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .assessment-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .assessment-item:hover {
            background: #e9ecef;
        }
        
        .assessment-date {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.5rem;
        }
        
        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-badges {
                justify-content: center;
            }
        }
    </style>
</head>
<body data-user-id="<?php echo htmlspecialchars($_SESSION['employee_id']); ?>"
      data-user-role="<?php echo htmlspecialchars($_SESSION['employee_role']); ?>"
      data-user-name="<?php echo htmlspecialchars($_SESSION['employee_name']); ?>">
    <!-- Notifications Container -->
    <div id="notifications"></div>
    <?php include 'includes/sidebar_navigation.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <h1>Coach Profile</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Coaches / 
                    <a href="coaches.php">All Coaches</a> / Profile
                </p>
            </div>
            <div class="topbar-right">
                <button class="btn-secondary" onclick="window.location.href='coaches.php'">
                    <i class="fas fa-arrow-left"></i> Back to Coaches
                </button>
            </div>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar-xl">
                <?php 
                    $profileImageUrl = null;
                    if (!empty($coach['profile_image'])) {
                        $profileImage = ltrim($coach['profile_image'], '/\\');
                        
                        // Check 1: Local uploads/coaches folder
                        $localPath = __DIR__ . '/../uploads/coaches/' . basename($profileImage);
                        if (file_exists($localPath)) {
                            $profileImageUrl = '../uploads/coaches/' . basename($profileImage);
                        }
                        // Check 2: External pbl_project coach_photos folder
                        elseif (!$profileImageUrl) {
                            $filename = basename($profileImage);
                            $externalPath = 'C:\\xampp\\htdocs\\pbl_project\\uploads\\coach_photos\\' . $filename;
                            if (file_exists($externalPath)) {
                                $profileImageUrl = '../../pbl_project/uploads/coach_photos/' . $filename;
                            }
                        }
                    }
                    
                    if ($profileImageUrl):
                ?>
                    <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Coach">
                <?php else: ?>
                    <?php echo strtoupper(substr($coach['first_name'], 0, 1) . substr($coach['last_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="profile-info-main">
                <h1><?php echo htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']); ?></h1>
                <p style="font-size: 1.2rem; opacity: 0.9;">
                    <?php echo htmlspecialchars($coach['specialization'] ?? 'Fitness Coach'); ?>
                </p>
                <div class="profile-badges">
                    <span class="badge">
                        <i class="fas fa-calendar"></i>
                        Joined <?php echo date('M Y', strtotime($coach['hire_date'])); ?>
                    </span>
                    <span class="badge">
                        <i class="fas fa-users"></i>
                        <?php echo $coach['total_clients']; ?> Active Clients
                    </span>
                    <span class="badge badge-status-<?php echo strtolower(str_replace(' ', '-', $coach['status'])); ?>">
                        <?php echo $coach['status']; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Assigned Clients</h3>
                    <p class="stat-number"><?php echo $coach['total_clients']; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-content">
                    <h3>Assessments</h3>
                    <p class="stat-number"><?php echo $coach['total_assessments']; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-dumbbell"></i>
                </div>
                <div class="stat-content">
                    <h3>PT Sessions</h3>
                    <p class="stat-number"><?php echo $coach['total_pt_sessions']; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-award"></i>
                </div>
                <div class="stat-content">
                    <h3>Experience</h3>
                    <p class="stat-number"><?php echo $coach['experience_years'] ?? 0; ?> Years</p>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Left Column - Details -->
            <div>
                <div class="info-card">
                    <h3><i class="fas fa-info-circle"></i> Coach Information</h3>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($coach['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars($coach['phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Certification</span>
                        <span class="info-value"><?php echo htmlspecialchars($coach['certification'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Hourly Rate</span>
                        <span class="info-value">₱<?php echo number_format($coach['hourly_rate'] ?? 0, 2); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Hire Date</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($coach['hire_date'])); ?></span>
                    </div>
                </div>

                <?php if ($coach['bio']): ?>
                <div class="info-card" style="margin-top: 1.5rem;">
                    <h3><i class="fas fa-user"></i> Biography</h3>
                    <p style="line-height: 1.6; color: #555;">
                        <?php echo nl2br(htmlspecialchars($coach['bio'])); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column - Clients & Assessments -->
            <div>
                <div class="info-card">
                    <h3><i class="fas fa-users"></i> Assigned Clients (<?php echo count($assignedClients); ?>)</h3>
                    <?php if (!empty($assignedClients)): ?>
                        <ul class="clients-list">
                            <?php foreach ($assignedClients as $client): ?>
                            <li class="client-item">
                                <div class="client-avatar-sm">
                                    <?php echo strtoupper(substr($client['first_name'], 0, 1)); ?>
                                </div>
                                <div style="flex: 1;">
                                    <strong><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></strong>
                                    <br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($client['email']); ?></small>
                                </div>
                                <span class="coach-status <?php echo strtolower($client['status']); ?>" style="font-size: 0.75rem;">
                                    <?php echo $client['status']; ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color: #999; text-align: center; padding: 2rem;">No clients assigned yet</p>
                    <?php endif; ?>
                </div>

                <div class="info-card" style="margin-top: 1.5rem;">
                    <h3><i class="fas fa-clipboard-list"></i> Recent Assessments</h3>
                    <?php if (!empty($recentAssessments)): ?>
                        <?php foreach ($recentAssessments as $assessment): ?>
                        <div class="assessment-item">
                            <strong><?php echo htmlspecialchars($assessment['first_name'] . ' ' . $assessment['last_name']); ?></strong>
                            <p class="assessment-date">
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('M d, Y', strtotime($assessment['assessment_date'])); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #999; text-align: center; padding: 2rem;">No assessments recorded</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/sidebar.js"></script>
    <script src="js/manager-dashboard.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>