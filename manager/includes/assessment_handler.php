<?php
session_start();
require_once '../../config/connection.php';

// Check if user is logged in and has manager role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$conn = getDBConnection();
$action = $_POST['action'] ?? '';

// PROCESS ASSESSMENT REQUEST
if ($action === 'process_request') {
    try {
        $inquiryId = $_POST['inquiry_id'] ?? null;
        $coachId = $_POST['coach_id'] ?? null;
        $assessmentDate = $_POST['assessment_date'] ?? null;
        $notes = $_POST['notes'] ?? null;

        // Validate required fields
        if (!$inquiryId || !$coachId || !$assessmentDate) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        // Get inquiry details
        $stmt = $conn->prepare("
            SELECT * FROM assessment_inquiries 
            WHERE inquiry_id = ? AND status = 'pending'
        ");
        $stmt->execute([$inquiryId]);
        $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inquiry) {
            echo json_encode(['success' => false, 'message' => 'Inquiry not found or already processed']);
            exit;
        }

        // Check if client exists by email
        $stmt = $conn->prepare("SELECT client_id FROM clients WHERE email = ?");
        $stmt->execute([$inquiry['email']]);
        $existingClient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingClient) {
            // Use existing client
            $clientId = $existingClient['client_id'];
        } else {
            // Create new client from inquiry data
            $stmt = $conn->prepare("
                INSERT INTO clients (
                    first_name, 
                    middle_name, 
                    last_name, 
                    email, 
                    phone, 
                    referral_source,
                    client_type,
                    status,
                    join_date
                ) VALUES (?, ?, ?, ?, ?, ?, 'Walk-in', 'Active', CURDATE())
            ");
            
            $stmt->execute([
                $inquiry['first_name'],
                $inquiry['middle_name'],
                $inquiry['last_name'],
                $inquiry['email'],
                $inquiry['phone'],
                $inquiry['referral_source']
            ]);
            
            $clientId = $conn->lastInsertId();

            // Create profile details if additional data exists
            if ($inquiry['birthdate'] || $inquiry['gender'] || $inquiry['address']) {
                $stmt = $conn->prepare("
                    INSERT INTO profile_details (
                        client_id,
                        birthdate,
                        gender,
                        street_address,
                        fitness_goals
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $clientId,
                    $inquiry['birthdate'],
                    $inquiry['gender'],
                    $inquiry['address'],
                    $inquiry['fitness_goals']
                ]);
            }
        }

        // Create assessment record
        $stmt = $conn->prepare("
            INSERT INTO assessment (
                client_id,
                coach_id,
                admin_id,
                assessment_date,
                fitness_goals,
                medical_conditions,
                notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $clientId,
            $coachId,
            $_SESSION['employee_id'],
            $assessmentDate,
            $inquiry['fitness_goals'],
            $inquiry['medical_conditions'],
            $notes
        ]);

        $assessmentId = $conn->lastInsertId();

        // Update inquiry status to completed
        $stmt = $conn->prepare("
            UPDATE assessment_inquiries 
            SET status = 'completed' 
            WHERE inquiry_id = ?
        ");
        $stmt->execute([$inquiryId]);

        // Create notification for the client (if client has account)
        try {
            $stmt = $conn->prepare("
                INSERT INTO notifications (
                    user_id,
                    user_type,
                    title,
                    message,
                    type
                ) VALUES (?, 'client', 'Assessment Scheduled', ?, 'info')
            ");
            
            $message = "Your assessment has been scheduled for " . date('F j, Y', strtotime($assessmentDate)) . ". Our coach will contact you soon.";
            $stmt->execute([$clientId, $message]);
        } catch (Exception $e) {
            // Notification creation failed, but continue
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Assessment request processed successfully! Client assigned to coach.',
            'assessment_id' => $assessmentId
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// REJECT ASSESSMENT REQUEST
if ($action === 'reject_request') {
    try {
        $inquiryId = $_POST['inquiry_id'] ?? null;

        if (!$inquiryId) {
            echo json_encode(['success' => false, 'message' => 'Missing inquiry ID']);
            exit;
        }

        // Update inquiry status to cancelled
        $stmt = $conn->prepare("
            UPDATE assessment_inquiries 
            SET status = 'cancelled' 
            WHERE inquiry_id = ? AND status = 'pending'
        ");
        $stmt->execute([$inquiryId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Assessment request rejected successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Inquiry not found or already processed'
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// GET ASSESSMENT DETAILS
if ($action === 'get_details') {
    try {
        $assessmentId = $_POST['assessment_id'] ?? null;

        if (!$assessmentId) {
            echo '<p>Invalid assessment ID</p>';
            exit;
        }

        // Fetch assessment with client and coach info
        $stmt = $conn->prepare("
            SELECT 
                a.*,
                c.first_name,
                c.middle_name,
                c.last_name,
                c.email,
                c.phone,
                c.client_type,
                co.first_name as coach_first_name,
                co.last_name as coach_last_name,
                co.email as coach_email,
                co.specialization,
                e.first_name as admin_first_name,
                e.last_name as admin_last_name
            FROM assessment a
            JOIN clients c ON a.client_id = c.client_id
            LEFT JOIN coach co ON a.coach_id = co.coach_id
            LEFT JOIN employees e ON a.admin_id = e.employee_id
            WHERE a.assessment_id = ?
        ");
        $stmt->execute([$assessmentId]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assessment) {
            echo '<p>Assessment not found</p>';
            exit;
        }

        // Calculate BMI if height and weight available
        $bmi = null;
        if ($assessment['weight'] && $assessment['height']) {
            $heightInMeters = $assessment['height'] / 100;
            $bmi = round($assessment['weight'] / ($heightInMeters * $heightInMeters), 1);
        }

        // Output HTML
        ?>
        <div class="details-grid">
            <div class="detail-section">
                <h3><i class="fas fa-user"></i> Client Information</h3>
                <div class="detail-row">
                    <label>Name:</label>
                    <span><?php echo htmlspecialchars($assessment['first_name'] . ' ' . ($assessment['middle_name'] ? $assessment['middle_name'] . ' ' : '') . $assessment['last_name']); ?></span>
                </div>
                <div class="detail-row">
                    <label>Email:</label>
                    <span><?php echo htmlspecialchars($assessment['email']); ?></span>
                </div>
                <div class="detail-row">
                    <label>Phone:</label>
                    <span><?php echo htmlspecialchars($assessment['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <label>Client Type:</label>
                    <span class="badge-<?php echo strtolower($assessment['client_type']); ?>">
                        <?php echo htmlspecialchars($assessment['client_type']); ?>
                    </span>
                </div>
            </div>

            <div class="detail-section">
                <h3><i class="fas fa-user-tie"></i> Assessment Details</h3>
                <div class="detail-row">
                    <label>Assessment Date:</label>
                    <span><?php echo date('F j, Y', strtotime($assessment['assessment_date'])); ?></span>
                </div>
                <div class="detail-row">
                    <label>Assigned Coach:</label>
                    <span><?php echo $assessment['coach_id'] ? htmlspecialchars($assessment['coach_first_name'] . ' ' . $assessment['coach_last_name']) : 'Not assigned'; ?></span>
                </div>
                <?php if ($assessment['coach_email']): ?>
                <div class="detail-row">
                    <label>Coach Email:</label>
                    <span><?php echo htmlspecialchars($assessment['coach_email']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($assessment['specialization']): ?>
                <div class="detail-row">
                    <label>Specialization:</label>
                    <span><?php echo htmlspecialchars($assessment['specialization']); ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <label>Processed By:</label>
                    <span><?php echo htmlspecialchars($assessment['admin_first_name'] . ' ' . $assessment['admin_last_name']); ?></span>
                </div>
            </div>

            <?php if ($assessment['weight'] || $assessment['height'] || $assessment['body_fat_percentage'] || $assessment['muscle_mass']): ?>
            <div class="detail-section">
                <h3><i class="fas fa-weight"></i> Physical Measurements</h3>
                <?php if ($assessment['weight']): ?>
                <div class="detail-row">
                    <label>Weight:</label>
                    <span><?php echo $assessment['weight']; ?> kg</span>
                </div>
                <?php endif; ?>
                <?php if ($assessment['height']): ?>
                <div class="detail-row">
                    <label>Height:</label>
                    <span><?php echo $assessment['height']; ?> cm</span>
                </div>
                <?php endif; ?>
                <?php if ($bmi): ?>
                <div class="detail-row">
                    <label>BMI:</label>
                    <span><?php echo $bmi; ?> 
                        <small>
                            <?php 
                            if ($bmi < 18.5) echo '(Underweight)';
                            elseif ($bmi < 25) echo '(Normal)';
                            elseif ($bmi < 30) echo '(Overweight)';
                            else echo '(Obese)';
                            ?>
                        </small>
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($assessment['body_fat_percentage']): ?>
                <div class="detail-row">
                    <label>Body Fat:</label>
                    <span><?php echo $assessment['body_fat_percentage']; ?>%</span>
                </div>
                <?php endif; ?>
                <?php if ($assessment['muscle_mass']): ?>
                <div class="detail-row">
                    <label>Muscle Mass:</label>
                    <span><?php echo $assessment['muscle_mass']; ?> kg</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($assessment['blood_pressure'] || $assessment['resting_heart_rate']): ?>
            <div class="detail-section">
                <h3><i class="fas fa-heartbeat"></i> Vital Signs</h3>
                <?php if ($assessment['blood_pressure']): ?>
                <div class="detail-row">
                    <label>Blood Pressure:</label>
                    <span><?php echo htmlspecialchars($assessment['blood_pressure']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($assessment['resting_heart_rate']): ?>
                <div class="detail-row">
                    <label>Resting Heart Rate:</label>
                    <span><?php echo $assessment['resting_heart_rate']; ?> bpm</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($assessment['fitness_goals']): ?>
            <div class="detail-section full-width">
                <h3><i class="fas fa-bullseye"></i> Fitness Goals</h3>
                <p><?php echo nl2br(htmlspecialchars($assessment['fitness_goals'])); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($assessment['medical_conditions']): ?>
            <div class="detail-section full-width">
                <h3><i class="fas fa-notes-medical"></i> Medical Conditions</h3>
                <p><?php echo nl2br(htmlspecialchars($assessment['medical_conditions'])); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($assessment['notes']): ?>
            <div class="detail-section full-width">
                <h3><i class="fas fa-sticky-note"></i> Notes</h3>
                <p><?php echo nl2br(htmlspecialchars($assessment['notes'])); ?></p>
            </div>
            <?php endif; ?>

            <div class="detail-section full-width">
                <h3><i class="fas fa-clock"></i> Timeline</h3>
                <div class="detail-row">
                    <label>Created:</label>
                    <span><?php echo date('F j, Y g:i A', strtotime($assessment['created_at'])); ?></span>
                </div>
                <?php if ($assessment['updated_at'] && $assessment['updated_at'] != $assessment['created_at']): ?>
                <div class="detail-row">
                    <label>Last Updated:</label>
                    <span><?php echo date('F j, Y g:i A', strtotime($assessment['updated_at'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($assessment['next_assessment_date']): ?>
                <div class="detail-row">
                    <label>Next Assessment:</label>
                    <span><?php echo date('F j, Y', strtotime($assessment['next_assessment_date'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .detail-section {
            background: #f9f9f9;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .detail-section.full-width {
            grid-column: 1 / -1;
        }
        .detail-section h3 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-section h3 i {
            color: #2563eb;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e8e8e8;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-row label {
            font-weight: 600;
            color: #666;
            font-size: 13px;
        }
        .detail-row span {
            color: #333;
            font-size: 13px;
            text-align: right;
        }
        .detail-section p {
            margin: 0;
            color: #333;
            line-height: 1.6;
            font-size: 13px;
        }
        .badge-member {
            background: #10b981;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-walk-in {
            background: #f59e0b;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        </style>
        <?php

    } catch (Exception $e) {
        echo '<p>Error loading assessment details: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    exit;
}

// GET ASSESSMENT DATA FOR EDITING
if ($action === 'get_assessment_data') {
    try {
        $assessmentId = $_POST['assessment_id'] ?? null;

        if (!$assessmentId) {
            echo json_encode(['success' => false, 'message' => 'Missing assessment ID']);
            exit;
        }

        $stmt = $conn->prepare("SELECT * FROM assessment WHERE assessment_id = ?");
        $stmt->execute([$assessmentId]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($assessment) {
            echo json_encode([
                'success' => true,
                'assessment' => $assessment
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Assessment not found']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// UPDATE ASSESSMENT
if ($action === 'update_assessment') {
    try {
        $assessmentId = $_POST['assessment_id'] ?? null;
        $coachId = $_POST['coach_id'] ?? null;
        $assessmentDate = $_POST['assessment_date'] ?? null;
        $weight = $_POST['weight'] ?? null;
        $height = $_POST['height'] ?? null;
        $bodyFat = $_POST['body_fat_percentage'] ?? null;
        $muscleMass = $_POST['muscle_mass'] ?? null;
        $bloodPressure = $_POST['blood_pressure'] ?? null;
        $heartRate = $_POST['resting_heart_rate'] ?? null;
        $fitnessGoals = $_POST['fitness_goals'] ?? null;
        $medicalConditions = $_POST['medical_conditions'] ?? null;
        $notes = $_POST['notes'] ?? null;
        $nextAssessmentDate = $_POST['next_assessment_date'] ?? null;

        if (!$assessmentId || !$coachId || !$assessmentDate) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $stmt = $conn->prepare("
            UPDATE assessment SET
                coach_id = ?,
                assessment_date = ?,
                weight = ?,
                height = ?,
                body_fat_percentage = ?,
                muscle_mass = ?,
                blood_pressure = ?,
                resting_heart_rate = ?,
                fitness_goals = ?,
                medical_conditions = ?,
                notes = ?,
                next_assessment_date = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE assessment_id = ?
        ");

        $stmt->execute([
            $coachId,
            $assessmentDate,
            $weight ?: null,
            $height ?: null,
            $bodyFat ?: null,
            $muscleMass ?: null,
            $bloodPressure ?: null,
            $heartRate ?: null,
            $fitnessGoals ?: null,
            $medicalConditions ?: null,
            $notes ?: null,
            $nextAssessmentDate ?: null,
            $assessmentId
        ]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Assessment updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No changes made or assessment not found'
            ]);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// DELETE ASSESSMENT
if ($action === 'delete_assessment') {
    try {
        $assessmentId = $_POST['assessment_id'] ?? null;

        if (!$assessmentId) {
            echo json_encode(['success' => false, 'message' => 'Missing assessment ID']);
            exit;
        }

        // Check if assessment exists
        $stmt = $conn->prepare("SELECT assessment_id FROM assessment WHERE assessment_id = ?");
        $stmt->execute([$assessmentId]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Assessment not found']);
            exit;
        }

        // Delete the assessment
        $stmt = $conn->prepare("DELETE FROM assessment WHERE assessment_id = ?");
        $stmt->execute([$assessmentId]);

        echo json_encode([
            'success' => true,
            'message' => 'Assessment deleted successfully'
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
?>