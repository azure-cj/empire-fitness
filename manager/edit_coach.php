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
$coachId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($coachId === 0) {
    header("Location: coaches.php");
    exit;
}

// Fetch coach details
try {
    $stmt = $conn->prepare("
        SELECT * FROM coach WHERE coach_id = ?
    ");
    $stmt->execute([$coachId]);
    $coach = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coach) {
        header("Location: coaches.php");
        exit;
    }
} catch (Exception $e) {
    header("Location: coaches.php");
    exit;
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug: Log file upload info
        error_log("Form submitted. FILES array: " . print_r($_FILES, true));
        
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $specialization = $_POST['specialization'] ?? '';
        $hourlyRate = $_POST['hourly_rate'] ?? '';
        $experienceYears = $_POST['experience_years'] ?? '';
        $bio = $_POST['bio'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        
        // Validate required fields
        if (empty($firstName) || empty($lastName) || empty($email)) {
            $message = 'First Name, Last Name, and Email are required';
            $messageType = 'error';
        } else {
            // Check for duplicate email (excluding current coach)
            $stmt = $conn->prepare("SELECT coach_id FROM coach WHERE email = ? AND coach_id != ?");
            $stmt->execute([$email, $coachId]);
            if ($stmt->fetch()) {
                $message = 'Email already exists for another coach';
                $messageType = 'error';
            } else {
                $profileImage = $coach['profile_image'];
                
                // Handle photo upload
                if (!empty($_FILES['profile_image']['name'])) {
                    error_log("Uploading file: " . $_FILES['profile_image']['name']);
                    $file = $_FILES['profile_image'];
                    $fileName = basename($file['name']);
                    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    
                    // Validate file type
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (!in_array($fileExt, $allowedExts)) {
                        $message = 'Only image files (JPG, PNG, GIF, WEBP) are allowed';
                        $messageType = 'error';
                        error_log("Invalid file extension: $fileExt");
                    } elseif ($file['size'] > 5242880) { // 5MB
                        $message = 'File size must be less than 5MB';
                        $messageType = 'error';
                        error_log("File too large: " . $file['size'] . " bytes");
                    } else {
                        // Create unique filename
                        $uniqueName = 'coach_' . $coachId . '_' . time() . '.' . $fileExt;
                        error_log("Unique filename: $uniqueName");
                        
                        // Try uploading to local folder first
                        $localUploadDir = __DIR__ . '/../uploads/coaches';
                        error_log("Local upload dir: $localUploadDir");
                        
                        if (!is_dir($localUploadDir)) {
                            if (!@mkdir($localUploadDir, 0755, true)) {
                                error_log("Failed to create local upload directory");
                                $message = 'Failed to create upload directory';
                                $messageType = 'error';
                            }
                        }
                        
                        if ($messageType !== 'error') {
                            $localPath = $localUploadDir . '/' . $uniqueName;
                            error_log("Local path: $localPath");
                            
                            if (move_uploaded_file($file['tmp_name'], $localPath)) {
                                error_log("File moved to: $localPath");
                                
                                // Also copy to external pbl_project folder
                                // Path structure: /xampp/htdocs/manager/edit_coach.php -> need /xampp/htdocs/pbl_project
                                $externalUploadDir = dirname(dirname(__DIR__)) . '/pbl_project/uploads/coach_photos';
                                error_log("External upload dir: $externalUploadDir");
                                
                                if (!is_dir($externalUploadDir)) {
                                    if (!@mkdir($externalUploadDir, 0755, true)) {
                                        error_log("Failed to create external upload directory");
                                    }
                                }
                                
                                $externalPath = $externalUploadDir . '/' . $uniqueName;
                                error_log("External path: $externalPath");
                                
                                if (!copy($localPath, $externalPath)) {
                                    // Log copy failure but don't fail the upload
                                    error_log("Warning: Failed to copy coach photo to external folder: $externalPath");
                                } else {
                                    error_log("Successfully copied to external folder");
                                }
                                
                                $profileImage = 'uploads/coaches/' . $uniqueName;
                                error_log("Profile image set to: $profileImage");
                            } else {
                                $message = 'Failed to upload file: ' . error_get_last()['message'];
                                $messageType = 'error';
                                error_log("move_uploaded_file failed for: " . $file['tmp_name']);
                            }
                        }
                    }
                }
                
                // Only update if no file upload error
                if ($messageType !== 'error') {
                    try {
                        $stmt = $conn->prepare("
                            UPDATE coach SET
                                first_name = ?,
                                last_name = ?,
                                email = ?,
                                phone = ?,
                                specialization = ?,
                                hourly_rate = ?,
                                experience_years = ?,
                                bio = ?,
                                profile_image = ?,
                                status = ?,
                                updated_at = NOW()
                            WHERE coach_id = ?
                        ");
                        
                        error_log("Executing UPDATE with values: first_name=$firstName, last_name=$lastName, email=$email, profileImage=$profileImage, coachId=$coachId");
                        
                        $stmt->execute([
                            $firstName,
                            $lastName,
                            $email,
                            $phone,
                            $specialization,
                            $hourlyRate,
                            $experienceYears,
                            $bio,
                            $profileImage,
                            $status,
                            $coachId
                        ]);
                        
                        error_log("UPDATE successful. Rows affected: " . $stmt->rowCount());
                        
                        $message = 'Coach details updated successfully';
                        $messageType = 'success';
                        
                        // Refresh coach data
                        $stmt = $conn->prepare("SELECT * FROM coach WHERE coach_id = ?");
                        $stmt->execute([$coachId]);
                        $coach = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $pex) {
                        error_log("Database error in UPDATE: " . $pex->getMessage());
                        $message = 'Database error: ' . $pex->getMessage();
                        $messageType = 'error';
                    }
                }
            }
        }
    } catch (Exception $e) {
        $message = 'Error updating coach: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Determine profile image path
$profileImageUrl = null;
if (!empty($coach['profile_image'])) {
    $profileImage = $coach['profile_image'];
    
    // Clean up the path - remove leading slashes
    $profileImage = ltrim($profileImage, '/\\');
    
    // Check 1: Local uploads/coaches folder (empirefitness)
    $localPath = __DIR__ . '/../uploads/coaches/' . basename($profileImage);
    if (file_exists($localPath)) {
        $profileImageUrl = '../uploads/coaches/' . basename($profileImage);
        $mtime = filemtime($localPath);
        $profileImageUrl .= '?t=' . $mtime;
    }
    // Check 2: External pbl_project coach_photos folder
    elseif (!$profileImageUrl) {
        $filename = basename($profileImage);
        $externalPath = 'C:\\xampp\\htdocs\\pbl_project\\uploads\\coach_photos\\' . $filename;
        if (file_exists($externalPath)) {
            $profileImageUrl = '../../pbl_project/uploads/coach_photos/' . $filename;
            $mtime = filemtime($externalPath);
            $profileImageUrl .= '?t=' . $mtime;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Coach - Empire Fitness</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/coaches.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="../css/realtime-notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .edit-coach-container {
            margin: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .edit-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .edit-header h1 {
            margin: 0;
            font-size: 28px;
        }
        
        .edit-header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        
        .edit-body {
            padding: 30px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .photo-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: start;
        }
        
        .photo-preview {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .photo-preview-box {
            width: 200px;
            height: 200px;
            background: #f5f5f5;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 2px solid #ddd;
        }
        
        .photo-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .photo-preview-box.empty {
            font-size: 48px;
            color: #999;
            background: #f9f9f9;
        }
        
        .photo-upload {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .file-input-label:hover {
            transform: translateY(-2px);
        }
        
        .file-input-label i {
            margin-right: 8px;
        }
        
        .file-name {
            color: #666;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #f0f0f0;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            transition: gap 0.3s;
        }
        
        .back-link:hover {
            gap: 12px;
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
                <h1>Edit Coach</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / <a href="coaches.php">Coaches</a> / Edit Coach
                </p>
            </div>
        </div>

        <div class="edit-coach-container">
            <div class="edit-header">
                <i class="fas fa-user-tie" style="font-size: 32px;"></i>
                <div>
                    <h1><?php echo htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']); ?></h1>
                    <p><?php echo htmlspecialchars($coach['specialization']); ?></p>
                </div>
            </div>

            <div class="edit-body">
                <a href="coaches.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Coaches
                </a>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($coach['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($coach['last_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($coach['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($coach['phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-briefcase"></i> Professional Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="specialization">Specialization</label>
                                <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($coach['specialization'] ?? ''); ?>" placeholder="e.g., Strength Training, Cardio">
                            </div>
                            <div class="form-group">
                                <label for="experience_years">Experience (Years)</label>
                                <input type="number" id="experience_years" name="experience_years" value="<?php echo htmlspecialchars($coach['experience_years'] ?? ''); ?>" min="0">
                            </div>
                            <div class="form-group">
                                <label for="hourly_rate">Hourly Rate</label>
                                <input type="number" id="hourly_rate" name="hourly_rate" value="<?php echo htmlspecialchars($coach['hourly_rate'] ?? ''); ?>" min="0" step="0.01">
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="Active" <?php echo $coach['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo $coach['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="On Leave" <?php echo $coach['status'] === 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bio">Bio</label>
                            <textarea id="bio" name="bio" placeholder="Coach biography and achievements"><?php echo htmlspecialchars($coach['bio'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Photo Section -->
                    <div class="form-section">
                        <h3><i class="fas fa-image"></i> Profile Photo</h3>
                        <div class="photo-section">
                            <div class="photo-preview">
                                <p style="margin: 0 0 10px 0; color: #666; font-weight: 600;">Current Photo</p>
                                <div class="photo-preview-box <?php echo empty($profileImageUrl) ? 'empty' : ''; ?>">
                                    <?php if (!empty($profileImageUrl)): ?>
                                        <img src="<?php echo $profileImageUrl; ?>" alt="Coach Photo">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="photo-upload">
                                <p style="margin: 0 0 10px 0; color: #666; font-weight: 600;">Upload New Photo</p>
                                <div class="file-input-wrapper">
                                    <input type="file" id="profile_image" name="profile_image" accept="image/*">
                                    <label for="profile_image" class="file-input-label">
                                        <i class="fas fa-cloud-upload-alt"></i> Choose Photo
                                    </label>
                                </div>
                                <p class="file-name">Supported formats: JPG, PNG, GIF, WEBP (Max 5MB)</p>
                                <p style="margin: 0; color: #999; font-size: 13px;">Photo will be saved to both local and external folders</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="coaches.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/manager-dashboard.js"></script>
    <script>
        // Handle file input change
        const fileInput = document.getElementById('profile_image');
        const fileLabel = document.querySelector('.file-input-label');
        
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const file = this.files[0];
                if (file) {
                    const fileName = file.name;
                    const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
                    const fileType = file.type;
                    
                    // Validate file size (5MB max)
                    if (file.size > 5242880) {
                        alert('File is too large! Maximum size is 5MB. Current size: ' + fileSize + 'MB');
                        this.value = '';
                        return;
                    }
                    
                    // Validate file type
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(fileType)) {
                        alert('Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.');
                        this.value = '';
                        return;
                    }
                    
                    // Show selected file info
                    const fileInfo = document.querySelector('.file-name');
                    if (fileInfo) {
                        fileInfo.textContent = 'Selected: ' + fileName + ' (' + fileSize + 'MB)';
                        fileInfo.style.color = '#28a745';
                    }
                    
                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewBox = document.querySelector('.photo-preview-box');
                        if (previewBox) {
                            previewBox.classList.remove('empty');
                            previewBox.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Handle form submission
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const submitBtn = document.querySelector('.btn-primary');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }
        });
    </script>
    <script src="js/sidebar.js"></script>
    <script src="../js/realtime-notifications.js"></script>
</body>
</html>
