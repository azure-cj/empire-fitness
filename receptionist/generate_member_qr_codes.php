<?php
session_start();

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['employee_role'], ['Receptionist', 'Admin', 'Manager'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';

$conn = getDBConnection();
$employee_name = $_SESSION['employee_name'] ?? 'Employee';
$employeeInitial = strtoupper(substr($employee_name, 0, 1));

// Fetch members with active monthly memberships
$membersQuery = "
    SELECT 
        c.client_id,
        c.first_name,
        c.last_name,
        c.email,
        c.phone,
        c.status,
        m.plan_name,
        cm.start_date,
        cm.end_date,
        cm.status as membership_status,
        mq.qr_code_hash,
        mq.created_at as qr_created_at,
        mq.last_used,
        mq.is_active
    FROM clients c
    INNER JOIN client_memberships cm ON c.client_id = cm.client_id
    INNER JOIN memberships m ON cm.membership_id = m.membership_id
    LEFT JOIN member_qr_codes mq ON c.client_id = mq.client_id AND mq.is_active = 1
    WHERE cm.status = 'Active'
    AND cm.end_date >= CURDATE()
    AND c.status = 'Active'
    AND m.monthly_fee > 0
    ORDER BY c.first_name ASC
";

try {
    $stmt = $conn->query($membersQuery);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $members = [];
    $error = "Error fetching members: " . $e->getMessage();
}

$total_members = count($members);
$members_with_qr = count(array_filter($members, function($m) { return !empty($m['qr_code_hash']); }));
$members_without_qr = $total_members - $members_with_qr;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Member QR Codes - Empire Fitness</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/receptionist-dashboard.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <style>
        :root {
            --primary-blue: #667eea;
            --primary-red: #d41c1c;
            --dark-gray: #2c3e50;
            --medium-gray: #7f8c8d;
            --light-gray: #ecf0f1;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: white;
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: var(--dark-gray);
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h1 i {
            color: var(--primary-blue);
            font-size: 32px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 13px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .stat-card i {
            font-size: 40px;
            color: var(--primary-blue);
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 10px 0;
        }

        .stat-label {
            color: var(--medium-gray);
            font-size: 14px;
        }

        .members-list {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .list-header {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .list-header h2 {
            color: var(--dark-gray);
            font-size: 18px;
        }

        .search-box {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-box input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            min-width: 250px;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .member-item {
            padding: 20px 30px;
            border-bottom: 1px solid #e0e0e0;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 200px 150px;
            gap: 20px;
            align-items: center;
            transition: background 0.2s;
        }

        .member-item:hover {
            background: #f8f9fa;
        }

        .member-item:last-child {
            border-bottom: none;
        }

        .member-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .member-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-red));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
        }

        .member-details {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .member-name {
            color: var(--dark-gray);
            font-weight: 600;
            font-size: 15px;
        }

        .member-email {
            color: var(--medium-gray);
            font-size: 13px;
        }

        .member-contact {
            color: var(--medium-gray);
            font-size: 13px;
        }

        .membership-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .membership-plan {
            color: var(--dark-gray);
            font-weight: 600;
            font-size: 14px;
        }

        .membership-dates {
            color: var(--medium-gray);
            font-size: 12px;
        }

        .qr-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .qr-status.has-qr {
            color: var(--success);
        }

        .qr-status.no-qr {
            color: var(--warning);
        }

        .qr-status i {
            font-size: 16px;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .action-btn-generate {
            background: var(--primary-blue);
            color: white;
        }

        .action-btn-generate:hover {
            background: #5568d3;
        }

        .action-btn-view {
            background: var(--success);
            color: white;
        }

        .action-btn-view:hover {
            background: #229954;
        }

        .action-btn-regenerate {
            background: var(--warning);
            color: white;
        }

        .action-btn-regenerate:hover {
            background: #e67e22;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: var(--dark-gray);
            margin-bottom: 10px;
        }

        .modal-header p {
            color: var(--medium-gray);
            font-size: 14px;
        }

        .qr-display {
            margin: 30px 0;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px solid var(--primary-blue);
        }

        #qrcode {
            display: inline-block;
        }

        .qr-hash {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #999;
            margin-top: 15px;
            word-break: break-all;
            padding: 10px;
            background: white;
            border-radius: 6px;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-modal {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-modal-primary {
            background: var(--primary-blue);
            color: white;
        }

        .btn-modal-primary:hover {
            background: #5568d3;
        }

        .btn-modal-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-modal-secondary:hover {
            background: #d0d0d0;
        }

        .alert {
            padding: 15px;
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

        .alert i {
            font-size: 16px;
        }

        .empty-state {
            padding: 60px 30px;
            text-align: center;
            color: var(--medium-gray);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--light-gray);
            margin-bottom: 15px;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: var(--medium-gray);
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 1200px) {
            .member-item {
                grid-template-columns: 1fr 1fr 150px;
            }

            .membership-info,
            .qr-status {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .member-item {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .actions {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }

            .modal-content {
                padding: 20px;
            }
        }

        @media print {
            body {
                background: white;
            }

            .page-header,
            .stats-grid,
            .list-header,
            .search-box,
            .actions,
            .modal {
                display: none;
            }

            .member-item {
                page-break-inside: avoid;
                border: 1px solid #ddd;
                margin-bottom: 10px;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>
                    <i class="fas fa-qrcode"></i>
                    Generate Member QR Codes
                </h1>
                <p style="color: var(--medium-gray); margin-top: 5px;">Manage QR codes for members with active monthly memberships</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="generateAllQRCodes()">
                    <i class="fas fa-layer-group"></i>
                    Generate All Missing
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-number"><?php echo $total_members; ?></div>
                <div class="stat-label">Total Members</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                <div class="stat-number"><?php echo $members_with_qr; ?></div>
                <div class="stat-label">With QR Code</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-exclamation-circle" style="color: var(--warning);"></i>
                <div class="stat-number"><?php echo $members_without_qr; ?></div>
                <div class="stat-label">Missing QR Code</div>
            </div>
        </div>

        <!-- Members List -->
        <div class="members-list">
            <div class="list-header">
                <h2>Members with Monthly Memberships</h2>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by name or email..." onkeyup="filterMembers()">
                </div>
            </div>

            <?php if (!empty($members)): ?>
                <div id="membersList">
                    <?php foreach ($members as $member): ?>
                        <div class="member-item" data-member-id="<?php echo $member['client_id']; ?>" data-member-name="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>">
                            <div class="member-info">
                                <div class="member-avatar">
                                    <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                </div>
                                <div class="member-details">
                                    <div class="member-name"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                    <div class="member-email"><?php echo htmlspecialchars($member['email']); ?></div>
                                </div>
                            </div>

                            <div class="membership-info">
                                <div class="membership-plan"><?php echo htmlspecialchars($member['plan_name']); ?></div>
                                <div class="membership-dates">
                                    <?php 
                                    $startDate = new DateTime($member['start_date']);
                                    $endDate = new DateTime($member['end_date']);
                                    echo $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y');
                                    ?>
                                </div>
                            </div>

                            <div class="member-contact">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?>
                            </div>

                            <div class="qr-status <?php echo !empty($member['qr_code_hash']) ? 'has-qr' : 'no-qr'; ?>">
                                <?php if (!empty($member['qr_code_hash'])): ?>
                                    <i class="fas fa-check-circle"></i>
                                    <span>QR Code Active</span>
                                <?php else: ?>
                                    <i class="fas fa-times-circle"></i>
                                    <span>No QR Code</span>
                                <?php endif; ?>
                            </div>

                            <div class="actions">
                                <?php if (!empty($member['qr_code_hash'])): ?>
                                    <button class="action-btn action-btn-view" onclick="viewQRCode('<?php echo htmlspecialchars($member['client_id']); ?>', '<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>', '<?php echo htmlspecialchars($member['qr_code_hash']); ?>')">
                                        <i class="fas fa-eye"></i>
                                        View
                                    </button>
                                    <button class="action-btn action-btn-regenerate" onclick="regenerateQRCode('<?php echo htmlspecialchars($member['client_id']); ?>')">
                                        <i class="fas fa-sync"></i>
                                        Regenerate
                                    </button>
                                <?php else: ?>
                                    <button class="action-btn action-btn-generate" onclick="generateQRCode('<?php echo htmlspecialchars($member['client_id']); ?>')">
                                        <i class="fas fa-plus"></i>
                                        Generate
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Members Found</h3>
                    <p>There are no members with active monthly memberships at this time.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="memberNameDisplay"></h2>
                <p id="qrCodeStatus"></p>
            </div>

            <div class="qr-display">
                <div id="qrcodeContainer"></div>
                <div class="qr-hash" id="qrHashDisplay"></div>
            </div>

            <div class="modal-footer">
                <button class="btn-modal btn-modal-primary" onclick="downloadQRCode()">
                    <i class="fas fa-download"></i>
                    Download
                </button>
                <button class="btn-modal btn-modal-primary" onclick="printQRCode()">
                    <i class="fas fa-print"></i>
                    Print
                </button>
                <button class="btn-modal btn-modal-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="modal">
        <div class="modal-content" style="text-align: center;">
            <div class="spinner"></div>
            <p id="loadingText">Generating QR code...</p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        let currentQRCode = null;
        let currentQRHash = null;

        async function generateQRCode(clientId) {
            showLoading('Generating QR code...');
            
            try {
                const formData = new FormData();
                formData.append('client_id', clientId);
                
                const response = await fetch('includes/generate_member_qr.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                hideLoading();
                
                if (data.success) {
                    showAlert('QR code generated successfully!', 'success');
                    location.reload();
                } else {
                    showAlert(data.message || 'Error generating QR code', 'error');
                }
            } catch (error) {
                hideLoading();
                console.error('Error:', error);
                showAlert('Error generating QR code', 'error');
            }
        }

        async function regenerateQRCode(clientId) {
            if (!confirm('Are you sure you want to regenerate the QR code? The old code will no longer work.')) {
                return;
            }
            
            showLoading('Regenerating QR code...');
            
            try {
                const formData = new FormData();
                formData.append('action', 'deactivate_qr');
                formData.append('client_id', clientId);
                
                const response = await fetch('includes/entry_exit_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Now generate new QR code
                    await generateQRCode(clientId);
                } else {
                    hideLoading();
                    showAlert('Error deactivating old QR code', 'error');
                }
            } catch (error) {
                hideLoading();
                console.error('Error:', error);
                showAlert('Error regenerating QR code', 'error');
            }
        }

        async function generateAllQRCodes() {
            const count = document.querySelectorAll('.member-item').length;
            const withoutQR = document.querySelectorAll('.qr-status.no-qr').length;
            
            if (withoutQR === 0) {
                showAlert('All members already have QR codes!', 'success');
                return;
            }
            
            if (!confirm(`This will generate ${withoutQR} QR codes. Continue?`)) {
                return;
            }
            
            showLoading(`Generating ${withoutQR} QR codes...`);
            
            const memberIds = Array.from(document.querySelectorAll('.qr-status.no-qr'))
                .map(el => el.closest('.member-item').dataset.memberId);
            
            let generated = 0;
            
            for (const clientId of memberIds) {
                try {
                    const formData = new FormData();
                    formData.append('client_id', clientId);
                    
                    const response = await fetch('includes/generate_member_qr.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        generated++;
                        document.getElementById('loadingText').textContent = 
                            `Generated ${generated} of ${withoutQR} QR codes...`;
                    }
                } catch (error) {
                    console.error('Error generating QR for', clientId, error);
                }
            }
            
            hideLoading();
            showAlert(`Successfully generated ${generated} QR codes!`, 'success');
            setTimeout(() => location.reload(), 1500);
        }

        function viewQRCode(clientId, memberName, qrCodeHash) {
            currentQRHash = qrCodeHash;
            
            document.getElementById('memberNameDisplay').textContent = memberName;
            document.getElementById('qrCodeStatus').textContent = 'Scan this code for check-in/check-out';
            document.getElementById('qrHashDisplay').textContent = qrCodeHash;
            
            // Clear previous QR code
            const container = document.getElementById('qrcodeContainer');
            container.innerHTML = '';
            
            // Generate new QR code
            currentQRCode = new QRCode(container, {
                text: qrCodeHash,
                width: 256,
                height: 256,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });
            
            document.getElementById('qrModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('qrModal').classList.remove('active');
        }

        function downloadQRCode() {
            const canvas = document.querySelector('#qrcodeContainer canvas');
            if (!canvas) {
                showAlert('QR Code not found', 'error');
                return;
            }
            
            const link = document.createElement('a');
            link.download = `gym-qr-code.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
        }

        function printQRCode() {
            const canvas = document.querySelector('#qrcodeContainer canvas');
            if (!canvas) {
                showAlert('QR Code not found', 'error');
                return;
            }
            
            const printWindow = window.open('', '', 'height=400,width=400');
            printWindow.document.write('<html><head><title>Print QR Code</title></head><body>');
            printWindow.document.write('<h2>' + document.getElementById('memberNameDisplay').textContent + '</h2>');
            printWindow.document.write('<img src="' + canvas.toDataURL('image/png') + '" style="max-width: 100%; height: auto;">');
            printWindow.document.write('<p>' + currentQRHash + '</p>');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }

        function showLoading(text = 'Loading...') {
            document.getElementById('loadingText').textContent = text;
            document.getElementById('loadingModal').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingModal').classList.remove('active');
        }

        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            
            const container = document.querySelector('.container');
            container.insertBefore(alertDiv, container.firstChild);
            
            setTimeout(() => alertDiv.remove(), 5000);
        }

        function filterMembers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const memberItems = document.querySelectorAll('.member-item');
            
            memberItems.forEach(item => {
                const memberName = item.dataset.memberName.toLowerCase();
                const memberEmail = item.querySelector('.member-email').textContent.toLowerCase();
                
                if (memberName.includes(searchTerm) || memberEmail.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Close modal when clicking outside
        document.getElementById('qrModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
