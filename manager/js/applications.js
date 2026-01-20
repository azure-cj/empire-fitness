let applicationsData = [];
let filteredData = [];

document.addEventListener('DOMContentLoaded', function() {
    loadApplications();
    loadStats();
});

async function loadApplications() {
    try {
        const response = await fetch('includes/applications_handler.php?action=fetch');
        const responseText = await response.text();
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', responseText);
            showAlert('Server returned invalid JSON. Check console for details.', 'error');
            return;
        }
        
        if (data.success) {
            applicationsData = data.applications;
            filteredData = [...applicationsData];
            
            console.log('✅ Applications loaded:', applicationsData.length);
            if (applicationsData.length > 0) {
                console.log('First application data:', applicationsData[0]);
                console.log('Birthdate:', applicationsData[0].birthdate);
                console.log('Gender:', applicationsData[0].gender);
                console.log('Address:', applicationsData[0].address);
            }
            
            renderApplicationsTable();
        } else {
            console.error('Server error:', data.message);
            showAlert('Error loading applications: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showAlert('Failed to load applications: ' + error.message, 'error');
    }
}

async function loadStats() {
    try {
        const response = await fetch('includes/applications_handler.php?action=stats');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('pendingCount').textContent = data.stats.pending || 0;
            document.getElementById('approvedCount').textContent = data.stats.approved_this_month || 0;
            document.getElementById('rejectedCount').textContent = data.stats.rejected || 0;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

function renderApplicationsTable() {
    const tbody = document.getElementById('applicationsTableBody');
    
    if (filteredData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="no-data">
                    <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3;"></i>
                    <p>No applications found</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = filteredData.map(app => {
        const statusClass = `status-${app.status}`;
        return `
            <tr>
                <td>#${app.id}</td>
                <td>
                    <div style="font-weight: 500;">${escapeHtml(app.firstName)} ${escapeHtml(app.lastName)}</div>
                    ${app.middleName ? `<small style="color: #6c757d;">${escapeHtml(app.middleName)}</small>` : ''}
                </td>
                <td>${escapeHtml(app.email)}</td>
                <td>${escapeHtml(app.phone)}</td>
                <td>${app.membershipPlan}</td>
                <td>₱${parseFloat(app.totalAmount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                <td>${formatDate(app.appliedDate)}</td>
                <td><span class="status-badge ${statusClass}">${capitalizeFirst(app.status)}</span></td>
                <td>
                    <button class="action-btn btn-view" onclick="viewApplication(${app.id})" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${app.status === 'pending' ? `
                        <button class="action-btn btn-approve-small" onclick="quickApprove(${app.id})" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="action-btn btn-reject-small" onclick="quickReject(${app.id})" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                </td>
            </tr>
        `;
    }).join('');
}

function filterApplications() {
    const status = document.getElementById('statusFilter').value;
    filteredData = status === 'all' ? [...applicationsData] : applicationsData.filter(app => app.status === status);
    renderApplicationsTable();
}

function searchApplications() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    
    let data = status === 'all' ? [...applicationsData] : applicationsData.filter(app => app.status === status);
    filteredData = data.filter(app => {
        const fullName = `${app.firstName} ${app.lastName}`.toLowerCase();
        const searchableText = `${fullName} ${app.email} ${app.phone} ${app.referenceNumber}`.toLowerCase();
        return searchableText.includes(searchTerm);
    });
    
    renderApplicationsTable();
}

function viewApplication(id) {
    console.log('📋 Opening application details for ID:', id);
    console.log('All applications data:', applicationsData);
    const app = applicationsData.find(a => a.id === id);
    
    if (!app) {
        console.error('❌ Application not found for ID:', id);
        showAlert('Application not found', 'error');
        return;
    }

    console.log('✅ Found application:', app);
    console.log('🎂 Birthdate value:', app.birthdate, '| Type:', typeof app.birthdate);
    console.log('👤 Gender value:', app.gender, '| Type:', typeof app.gender);
    console.log('🏠 Address value:', app.address, '| Type:', typeof app.address);
    console.log('📧 Email value:', app.email, '| Type:', typeof app.email);

    document.getElementById('currentApplicationId').value = id;
    
    // Personal Information
    const fullName = `${app.firstName} ${app.middleName ? app.middleName + ' ' : ''}${app.lastName}`;
    document.getElementById('detailName').textContent = fullName;
    document.getElementById('detailEmail').textContent = app.email || 'Not provided';
    document.getElementById('detailPhone').textContent = app.phone || 'Not provided';
    document.getElementById('detailBirthdate').textContent = app.birthdate ? formatDate(app.birthdate) : 'Not provided';
    document.getElementById('detailGender').textContent = app.gender ? capitalizeFirst(app.gender) : 'Not provided';
    document.getElementById('detailAddress').textContent = app.address || 'Not provided';
    
    // Referral source
    const referralContainer = document.getElementById('detailReferralContainer');
    if (app.referralSource && app.referralSource.trim()) {
        document.getElementById('detailReferral').textContent = app.referralSource;
        referralContainer.style.display = 'block';
    } else {
        referralContainer.style.display = 'none';
    }
    
    // Membership & Payment
    document.getElementById('detailPlan').textContent = app.membershipPlan || 'Not specified';
    document.getElementById('detailAmount').textContent = `₱${parseFloat(app.totalAmount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
    document.getElementById('detailReference').textContent = app.referenceNumber || 'N/A';
    document.getElementById('detailPaymentDate').textContent = app.paymentDate ? formatDate(app.paymentDate) : 'N/A';
    document.getElementById('detailPaymentMethod').textContent = app.paymentMethod || 'N/A';
    
    // Payment Notes
    const notesContainer = document.getElementById('detailPaymentNotesContainer');
    if (app.paymentNotes && app.paymentNotes.trim()) {
        document.getElementById('detailPaymentNotes').textContent = app.paymentNotes;
        notesContainer.style.display = 'block';
    } else {
        notesContainer.style.display = 'none';
    }
    
    // Application Status
    document.getElementById('detailAppliedDate').textContent = app.appliedDate ? formatDateTime(app.appliedDate) : 'N/A';
    const statusBadge = document.getElementById('detailStatus');
    statusBadge.textContent = capitalizeFirst(app.status);
    statusBadge.className = `status-badge status-${app.status}`;
    
    // Verified information
    const verifiedContainer = document.getElementById('detailVerifiedContainer');
    const verifiedAtContainer = document.getElementById('detailVerifiedAtContainer');
    if (app.verifiedByName && app.verifiedAt) {
        document.getElementById('detailVerifiedBy').textContent = app.verifiedByName;
        document.getElementById('detailVerifiedAt').textContent = formatDateTime(app.verifiedAt);
        verifiedContainer.style.display = 'block';
        verifiedAtContainer.style.display = 'block';
    } else {
        verifiedContainer.style.display = 'none';
        verifiedAtContainer.style.display = 'none';
    }
    
    // Rejection reason
    const rejectionContainer = document.getElementById('detailRejectionContainer');
    if (app.rejectionReason && app.rejectionReason.trim()) {
        document.getElementById('detailRejectionReason').textContent = app.rejectionReason;
        rejectionContainer.style.display = 'block';
    } else {
        rejectionContainer.style.display = 'none';
    }
    
    // Client ID link
    const clientIdContainer = document.getElementById('detailClientIdContainer');
    if (app.clientId) {
        document.getElementById('detailClientId').innerHTML = `<a href="member_profile.php?id=${app.clientId}" target="_blank" style="color: #d41c1c; text-decoration: none;">#${app.clientId}</a>`;
        clientIdContainer.style.display = 'block';
    } else {
        clientIdContainer.style.display = 'none';
    }

    // Payment Proof
    displayPaymentProof(app.paymentProof);

    // Show/hide buttons based on status
    const approveBtn = document.getElementById('btnApprove');
    const rejectBtn = document.getElementById('btnReject');
    if (app.status !== 'pending') {
        approveBtn.style.display = 'none';
        rejectBtn.style.display = 'none';
    } else {
        approveBtn.style.display = 'inline-flex';
        rejectBtn.style.display = 'inline-flex';
    }

    document.getElementById('viewApplicationModal').style.display = 'block';
    console.log('✅ Modal opened');
}

function displayPaymentProof(proofPath) {
    const container = document.getElementById('paymentProofContainer');
    
    if (!proofPath || proofPath.trim() === '') {
        container.innerHTML = '<div class="no-payment-proof">No payment proof uploaded</div>';
        return;
    }
    
    const webPath = proofPath.trim();
    
    // Determine file extension from the path
    const lastDotIndex = webPath.lastIndexOf('.');
    const ext = lastDotIndex > -1 ? webPath.substring(lastDotIndex + 1).toLowerCase() : '';
    
    console.log('Displaying payment proof - Path:', webPath, 'Extension:', ext);
    
    if (ext === 'pdf') {
        // PDF Preview with buttons
        container.innerHTML = `
            <div class="payment-proof-container">
                <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                    <button class="btn-outline-danger" onclick="openPdfModal('${webPath.replace(/'/g, "\\'")}')">
                        <i class="fas fa-search-plus"></i> Preview PDF
                    </button>
                    <a href="${escapeHtml(webPath)}" target="_blank" class="btn-outline-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-external-link-alt"></i> Open in Tab
                    </a>
                </div>
            </div>
        `;
    } else if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(ext)) {
        // Image preview with click to enlarge
        const encodedPath = webPath.replace(/'/g, "\\'");
        container.innerHTML = `
            <div class="payment-proof-container">
                <img src="${escapeHtml(webPath)}" alt="Payment Proof" class="payment-proof-image" onclick="openImageModal('${encodedPath}')" onerror="handleImageError(this)">
                <small style="display: block; margin-top: 10px; color: #666; text-align: center;">Click image to enlarge</small>
            </div>
        `;
    } else {
        // Generic file download
        container.innerHTML = `
            <div class="payment-proof-container">
                <div class="payment-proof-pdf">
                    <i class="fas fa-file"></i>
                    <div>
                        <a href="${escapeHtml(webPath)}" target="_blank" style="color: #d41c1c; text-decoration: none;">
                            <i class="fas fa-external-link-alt"></i> Download Payment Proof
                        </a>
                    </div>
                </div>
            </div>
        `;
    }
}

function handleImageError(imgElement) {
    imgElement.onerror = null; // Prevent loop
    imgElement.parentElement.innerHTML = '<div class="no-payment-proof">Image failed to load - the file may not exist or is not a valid image</div>';
}

function openImageModal(imagePath) {
    console.log('Opening image modal with path:', imagePath);
    const modal = document.getElementById('imageModal');
    const img = document.getElementById('imageModalImg');
    
    // The path should already be correct from the server
    img.src = imagePath;
    
    // Display the modal
    modal.style.display = 'block';
    
    // Log the modal status
    console.log('Image modal displayed with path:', imagePath);
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    modal.style.display = 'none';
    console.log('Image modal closed');
}

function openPdfModal(pdfPath) {
    console.log('Opening PDF modal with path:', pdfPath);
    const modal = document.getElementById('pdfModal');
    const iframe = document.getElementById('pdfModalIframe');
    
    // Add debug parameter to see file search logs
    let urlPath = pdfPath;
    if (pdfPath.includes('file_viewer.php')) {
        urlPath = pdfPath + '&debug=1';
    }
    
    // The path should already be correct from the server
    iframe.src = urlPath;
    
    // Display the modal
    modal.style.display = 'block';
    
    console.log('PDF modal displayed with path:', urlPath);
}

function closePdfModal() {
    const modal = document.getElementById('pdfModal');
    modal.style.display = 'none';
    document.getElementById('pdfModalIframe').src = '';
    console.log('PDF modal closed');
}

function closeApplicationModal() {
    document.getElementById('viewApplicationModal').style.display = 'none';
}

async function quickApprove(id) {
    const app = applicationsData.find(a => a.id === id);
    if (!app) return;
    
    if (!confirm(`Are you sure you want to approve this application and create a member account for ${app.firstName} ${app.lastName}?`)) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'approve');
        formData.append('registration_id', id);
        
        const response = await fetch('includes/applications_handler.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            // Show detailed success notification with credentials
            const credentialsMessage = `✅ MEMBER APPROVED & ACCOUNT CREATED!\n\n` +
                `Member: ${app.firstName} ${app.lastName}\n` +
                `Email: ${app.email}\n\n` +
                `📋 LOGIN CREDENTIALS:\n` +
                `Username: ${data.username}\n` +
                `Password: ${data.temporary_password}\n\n` +
                `💌 Email with credentials has been sent to the member.\n\n` +
                `${data.message}`;
            
            showAlert(credentialsMessage, 'success');
            await loadApplications();
            await loadStats();
        } else {
            showAlert('Error: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Failed to approve application. Please try again.', 'error');
    }
}

async function quickReject(id) {
    const app = applicationsData.find(a => a.id === id);
    if (!app) return;
    
    const reason = prompt('Please enter rejection reason:');
    if (!reason || reason.trim() === '') {
        showAlert('Rejection reason is required', 'warning');
        return;
    }
    
    if (!confirm(`Are you sure you want to reject the application for ${app.firstName} ${app.lastName}?`)) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'reject');
        formData.append('registration_id', id);
        formData.append('reason', reason);
        
        const response = await fetch('includes/applications_handler.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            await loadApplications();
            await loadStats();
        } else {
            showAlert('Error: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Failed to reject application. Please try again.', 'error');
    }
}

async function approveApplication() {
    const id = parseInt(document.getElementById('currentApplicationId').value);
    const app = applicationsData.find(a => a.id === id);
    
    if (!app) {
        showAlert('Application not found', 'error');
        return;
    }

    if (!confirm(`Approve registration for ${app.firstName} ${app.lastName}?`)) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'approve');
        formData.append('registration_id', id);
        
        const response = await fetch('includes/applications_handler.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            closeApplicationModal();
            
            // Show verification email sent notification
            const successMessage = `✅ APPLICATION APPROVED!\n\n` +
                `Member: ${app.firstName} ${app.lastName}\n` +
                `Email: ${app.email}\n\n` +
                `📧 Verification email sent to: ${app.email}\n\n` +
                `The member will receive login credentials after verifying their email address.`;
            
            showAlert(successMessage, 'success');
            await loadApplications();
            await loadStats();
        } else {
            showAlert('Error: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Failed to approve application. Please try again.', 'error');
    }
}

async function rejectApplication() {
    const id = parseInt(document.getElementById('currentApplicationId').value);
    const app = applicationsData.find(a => a.id === id);
    
    if (!app) {
        showAlert('Application not found', 'error');
        return;
    }

    const reason = prompt('Please enter rejection reason:');
    if (!reason || reason.trim() === '') {
        showAlert('Rejection reason is required', 'warning');
        return;
    }
    
    if (!confirm(`Reject application for ${app.firstName} ${app.lastName}?`)) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'reject');
        formData.append('registration_id', id);
        formData.append('reason', reason);
        
        const response = await fetch('includes/applications_handler.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            closeApplicationModal();
            showAlert(data.message, 'success');
            await loadApplications();
            await loadStats();
        } else {
            showAlert('Error: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Failed to reject application. Please try again.', 'error');
    }
}

window.onclick = function(event) {
    const appModal = document.getElementById('viewApplicationModal');
    const imgModal = document.getElementById('imageModal');
    if (event.target === appModal) closeApplicationModal();
    if (event.target === imgModal) closeImageModal();
}

function showAlert(message, type) {
    const alertBox = document.getElementById('alertBox');
    alertBox.textContent = message;
    alertBox.className = `alert alert-${type}`;
    alertBox.style.display = 'block';
    
    const timeout = message.includes('Username:') ? 15000 : 5000;
    setTimeout(() => { alertBox.style.display = 'none'; }, timeout);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateTimeString) {
    if (!dateTimeString) return 'N/A';
    const date = new Date(dateTimeString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function capitalizeFirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}