// Members Management JavaScript

let allMembers = [];
let filteredMembers = [];
let currentFilter = 'all';
let selectedMembers = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadMembers();
    setupEventListeners();
});

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Form submission - prevent default form submission since we use buttons
    const form = document.getElementById('memberForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            return false;
        });
    }
    
    // Assessment checkbox toggle
    const assessmentCheckbox = document.getElementById('schedule_assessment');
    if (assessmentCheckbox) {
        assessmentCheckbox.addEventListener('change', function() {
            const dateGroup = document.getElementById('assessment_date_group');
            if (this.checked) {
                dateGroup.style.display = 'grid';
            } else {
                dateGroup.style.display = 'none';
            }
        });
    }
    
    // Membership plan selection - highlight base membership when monthly is selected
    const planInputs = document.querySelectorAll('input[name="membership_plan"]');
    planInputs.forEach(input => {
        input.addEventListener('change', function() {
            // If regular or student monthly is selected, also highlight base membership
            if (this.value === 'regular' || this.value === 'student') {
                // Don't actually check the base membership, just highlight it visually
                // The base membership is automatically included with monthly plans
                const baseMembershipCard = document.querySelector('input[name="membership_plan"][value="none"]').closest('.plan-option');
                baseMembershipCard.classList.add('base-required');
            } else {
                // Remove the highlight if going back to base only
                const baseMembershipCard = document.querySelector('input[name="membership_plan"][value="none"]').closest('.plan-option');
                baseMembershipCard.classList.remove('base-required');
            }
        });
    });
    
    // Search input with debounce
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(searchMembers, 300);
        });
    }
}

/**
 * Load all members
 */
async function loadMembers() {
    try {
        console.log('Loading members...');
        const response = await fetch('includes/members_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_all'
        });
        
        console.log('Response status:', response.status);
        const text = await response.text();
        console.log('Raw response:', text);
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response text:', text);
            showAlert('Error: Invalid response from server', 'error');
            return;
        }
        
        console.log('Parsed data:', data);
        
        if (data.success) {
            allMembers = data.members;
            filteredMembers = allMembers;
            console.log('Members loaded:', allMembers.length);
            updateStats();
            renderMembersTable(filteredMembers);
            // Load pending payment count
            loadPendingPayments();
        } else {
            console.error('Server error:', data.message);
            showAlert(data.message, 'error');
        }
    } catch (error) {
        console.error('Error loading members:', error);
        showAlert('Error loading members data: ' + error.message, 'error');
    }
}

/**
 * Update statistics
 */
function updateStats() {
    const total = allMembers.length;
    const active = allMembers.filter(m => m.status === 'Active').length;
    const inactive = allMembers.filter(m => m.status === 'Inactive').length;
    const suspended = allMembers.filter(m => m.status === 'Suspended').length;
    const verified = allMembers.filter(m => m.is_verified == 1).length;
    
    // Calculate new members this month
    const now = new Date();
    const firstDayOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    const newThisMonth = allMembers.filter(m => {
        const joinDate = new Date(m.join_date);
        return joinDate >= firstDayOfMonth;
    }).length;
    
    document.getElementById('totalMembers').textContent = total;
    document.getElementById('activeMembers').textContent = active;
    document.getElementById('inactiveMembers').textContent = inactive;
    document.getElementById('suspendedMembers').textContent = suspended;
    document.getElementById('verifiedMembers').textContent = verified;
    document.getElementById('newMembers').textContent = newThisMonth;
}

/**
 * Render members table
 */
function renderMembersTable(members) {
    const tbody = document.getElementById('membersTableBody');
    
    if (members.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="14" class="no-data">
                    <i class="fas fa-users" style="font-size: 48px; opacity: 0.3;"></i>
                    <p>No members found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = members.map(member => {
        const fullName = `${member.first_name} ${member.middle_name ? member.middle_name + ' ' : ''}${member.last_name}`;
        const initials = (member.first_name.charAt(0) + member.last_name.charAt(0)).toUpperCase();
        const statusClass = member.status.toLowerCase();
        const accountStatusClass = member.account_status.toLowerCase();
        const verifiedClass = member.is_verified == 1 ? 'yes' : 'no';
        const verifiedText = member.is_verified == 1 ? 'Verified' : 'Not Verified';
        
        // Membership info
        const membershipPlan = member.plan_name || 'No membership';
        const durationDisplay = member.duration_days ? member.duration_days + ' days' : 'Monthly';
        const monthlyLabel = member.monthly_fee ? `₱${parseFloat(member.monthly_fee).toFixed(2)}/mo` : '';
        const isRenewal = member.is_renewal == 1;
        const renewalCount = member.renewal_count || 0;
        const renewalBadge = isRenewal ? `<span style="background: #e7f3ff; color: #0066cc; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><i class="fas fa-sync-alt"></i> Renewal #${renewalCount}</span>` : '<span style="background: #f0f0f0; color: #666; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Original</span>';
        
        return `
            <tr data-member-id="${member.client_id}">
                <td>
                    <input type="checkbox" class="member-checkbox" value="${member.client_id}" 
                           onchange="toggleMemberSelection(${member.client_id})">
                </td>
                <td>
                    <span class="employee-code">#${member.client_id}</span>
                </td>
                <td>
                    <div class="member-info">
                        <div class="member-avatar">${initials}</div>
                        <div>
                            <div class="member-name">${fullName}</div>
                            ${member.username ? '<div class="member-username">@' + member.username + '</div>' : ''}
                        </div>
                    </div>
                </td>
                <td>${member.email || '-'}</td>
                <td>${member.phone || '-'}</td>
                <td>
                    <span class="type-badge type-${member.client_type.toLowerCase().replace('-', '')}">
                        ${member.client_type}
                    </span>
                </td>
                <td>
                    <div style="font-size: 13px; line-height: 1.4;">
                        <div style="font-weight: 600; color: #2c3e50;">${membershipPlan}</div>
                        <div style="color: #6c757d; font-size: 12px;">${durationDisplay}${monthlyLabel ? ' - ' + monthlyLabel : ''}</div>
                    </div>
                </td>
                <td>
                    <div style="text-align: center;">
                        ${renewalBadge}
                    </div>
                </td>
                <td>
                    <span class="status-badge status-${statusClass}">
                        <i class="fas fa-circle"></i>
                        ${member.status}
                    </span>
                </td>
                <td>
                    <span class="status-badge status-${accountStatusClass}">
                        ${member.account_status}
                    </span>
                </td>
                <td>
                    <span class="verified-badge verified-${verifiedClass}">
                        <i class="fas fa-${member.is_verified == 1 ? 'check-circle' : 'clock'}"></i>
                        ${verifiedText}
                    </span>
                </td>
                <td>${member.join_date ? formatDate(member.join_date) : '-'}</td>
                <td>${member.last_login ? formatDateTime(member.last_login) : 'Never'}</td>
                <td>
                    <div class="action-buttons">
                        <button onclick="viewMember(${member.client_id})" class="btn-action btn-view" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="openEditModal(${member.client_id})" class="btn-action btn-edit" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${member.is_verified == 0 ? '<button onclick="verifyMember(' + member.client_id + ')" class="btn-action btn-reset" title="Verify"><i class="fas fa-check"></i></button>' : ''}
                        <button onclick="deleteMember(${member.client_id}, '${fullName}')" class="btn-action btn-delete" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Filter members
 */
function filterMembers(filter) {
    currentFilter = filter;
    
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.closest('.filter-btn').classList.add('active');
    
    // Handle pending payment filter specially
    if (filter === 'pending_payment') {
        loadPendingPayments();
        return;
    }
    
    // Apply filter
    switch(filter) {
        case 'active':
            filteredMembers = allMembers.filter(m => m.status === 'Active');
            break;
        case 'inactive':
            filteredMembers = allMembers.filter(m => m.status === 'Inactive');
            break;
        case 'suspended':
            filteredMembers = allMembers.filter(m => m.status === 'Suspended');
            break;
        case 'verified':
            filteredMembers = allMembers.filter(m => m.is_verified == 1);
            break;
        case 'unverified':
            filteredMembers = allMembers.filter(m => m.is_verified == 0);
            break;
        default:
            filteredMembers = allMembers;
    }
    
    renderMembersTable(filteredMembers);
}

/**
 * Search members
 */
function searchMembers() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
    
    if (!searchTerm) {
        filterMembers(currentFilter);
        return;
    }
    
    filteredMembers = allMembers.filter(member => {
        const fullName = `${member.first_name} ${member.middle_name || ''} ${member.last_name}`.toLowerCase();
        const email = (member.email || '').toLowerCase();
        const phone = (member.phone || '').toLowerCase();
        const username = (member.username || '').toLowerCase();
        
        return fullName.includes(searchTerm) ||
               email.includes(searchTerm) ||
               phone.includes(searchTerm) ||
               username.includes(searchTerm) ||
               member.client_id.toString().includes(searchTerm);
    });
    
    renderMembersTable(filteredMembers);
}

/**
 * View member details
 */
async function viewMember(memberId) {
    try {
        const response = await fetch('includes/members_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_one&client_id=' + memberId
        });
        
        const data = await response.json();
        
        if (data.success) {
            const member = data.member;
            const memberships = data.memberships || [];
            const activities = data.activities || [];
            const fullName = member.first_name + ' ' + (member.middle_name ? member.middle_name + ' ' : '') + member.last_name;
            
            // Build membership history HTML
            let membershipHtml = '';
            if (memberships.length > 0) {
                membershipHtml = '<div class="membership-history">';
                memberships.forEach((m, index) => {
                    const isRenewal = m.is_renewal == 1;
                    const daysRemaining = m.days_remaining;
                    let statusClass = 'status-active';
                    let statusText = 'Active';
                    
                    if (daysRemaining < 0) {
                        statusClass = 'status-expired';
                        statusText = 'Expired';
                    } else if (daysRemaining <= 7) {
                        statusClass = 'status-expiring';
                        statusText = 'Expiring Soon';
                    }
                    
                    const renewalBadge = isRenewal ? '<span class="renewal-badge renewal-yes"><i class="fas fa-sync-alt"></i> Renewal</span>' : '';
                    const monthlyLabel = m.monthly_fee ? ' (₱' + parseFloat(m.monthly_fee).toFixed(2) + '/month)' : '';
                    const durationDisplay = m.duration_days ? m.duration_days + ' days' : 'Unlimited';
                    
                    membershipHtml += `
                        <div class="membership-card ${index === 0 ? 'current' : 'previous'}">
                            <div class="membership-header">
                                <div>
                                    <h5>${m.plan_name || 'N/A'}${monthlyLabel}</h5>
                                    <p class="membership-type">${isRenewal ? 'Renewal #' + (m.renewal_count || 1) : 'Original'}</p>
                                </div>
                                <span class="${statusClass}">${statusText}</span>
                                ${renewalBadge}
                            </div>
                            <div class="membership-details">
                                <div class="detail-row">
                                    <span class="detail-label">Duration:</span>
                                    <span class="detail-value">${durationDisplay}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Start Date:</span>
                                    <span class="detail-value">${formatDate(m.start_date)}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">End Date:</span>
                                    <span class="detail-value">${formatDate(m.end_date)}</span>
                                </div>
                                ${m.days_remaining !== null ? `
                                <div class="detail-row">
                                    <span class="detail-label">Days Remaining:</span>
                                    <span class="detail-value">${m.days_remaining >= 0 ? m.days_remaining + ' days' : 'Expired ' + Math.abs(m.days_remaining) + ' days ago'}</span>
                                </div>
                                ` : ''}
                                ${m.last_renewal_date ? `
                                <div class="detail-row">
                                    <span class="detail-label">Last Renewal:</span>
                                    <span class="detail-value">${formatDate(m.last_renewal_date)}</span>
                                </div>
                                ` : ''}
                                ${m.renewal_count ? `
                                <div class="detail-row">
                                    <span class="detail-label">Total Renewals:</span>
                                    <span class="detail-value">${m.renewal_count}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
                membershipHtml += '</div>';
            } else {
                membershipHtml = '<p style="text-align: center; color: #6c757d; padding: 20px;">No membership records found</p>';
            }
            
            let activityHtml = '';
            if (activities.length > 0) {
                activityHtml = '<div class="member-timeline">';
                activities.forEach(activity => {
                    let iconClass = 'blue';
                    let icon = 'fa-info-circle';
                    
                    if (activity.activity_type.includes('login')) {
                        iconClass = 'green';
                        icon = 'fa-sign-in-alt';
                    } else if (activity.activity_type.includes('logout')) {
                        iconClass = 'orange';
                        icon = 'fa-sign-out-alt';
                    } else if (activity.activity_type.includes('register')) {
                        iconClass = 'blue';
                        icon = 'fa-user-plus';
                    } else if (activity.activity_type.includes('update')) {
                        iconClass = 'orange';
                        icon = 'fa-edit';
                    }
                    
                    activityHtml += '<div class="timeline-item">' +
                        '<div class="timeline-icon ' + iconClass + '"><i class="fas ' + icon + '"></i></div>' +
                        '<div class="timeline-content">' +
                        '<h5>' + activity.activity_type + '</h5>' +
                        '<p>' + (activity.description || 'No description') + '</p>' +
                        '<span class="timeline-date">' + formatDateTime(activity.created_at) + '</span>' +
                        '</div>' +
                        '</div>';
                });
                activityHtml += '</div>';
            } else {
                activityHtml = '<p style="text-align: center; color: #6c757d; padding: 20px;">No activity recorded</p>';
            }
            
            document.getElementById('viewModalBody').innerHTML = 
                '<div class="member-details-grid">' +
                    '<div class="detail-section">' +
                        '<h4><i class="fas fa-user"></i> Personal Information</h4>' +
                        '<div class="detail-row">' +
                            '<span class="detail-label">Full Name:</span>' +
                            '<span class="detail-value">' + fullName + '</span>' +
                        '</div>' +
                        '<div class="detail-row">' +
                            '<span class="detail-label">Email:</span>' +
                            '<span class="detail-value">' + (member.email || 'N/A') + '</span>' +
                        '</div>' +
                        '<div class="detail-row">' +
                            '<span class="detail-label">Phone:</span>' +
                            '<span class="detail-value">' + (member.phone || 'N/A') + '</span>' +
                        '</div>' +
                        '<div class="detail-row">' +
                            '<span class="detail-label">Username:</span>' +
                            '<span class="detail-value">' + (member.username || 'N/A') + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="detail-section">' +
                        '<h4><i class="fas fa-id-card"></i> Account Status</h4>' +
                        '<div class="detail-row">' +
                            '<span class="detail-label">Member Type:</span>' +
                            '<span class="detail-value">' +
                                '<span class="type-badge type-' + member.client_type.toLowerCase().replace('-', '') + '">' + member.client_type + '</span>' +
                            '</span>' +
                        '</div>' +
                        '<div class="detail-row">' +
                            '<span class="detail-label">Status:</span>' +
                            '<span class="detail-value">' +
                                '<span class="status-badge status-' + member.status.toLowerCase() + '">' + member.status + '</span>' +
                            '</span>' +
                        '</div>' +
                        '<div class="detail-row">' +
                            '<span class="detail-label">Account Status:</span>' +
                            '<span class="detail-value">' +
                                '<span class="status-badge status-' + member.account_status.toLowerCase() + '">' + member.account_status + '</span>' +
                            '</span>' +
                        '</div>' +
                        '<div class="detail-row">' +
                            '<span class="detail-label">Verified:</span>' +
                            '<span class="detail-value">' +
                                '<span class="verified-badge verified-' + (member.is_verified == 1 ? 'yes' : 'no') + '">' +
                                    (member.is_verified == 1 ? 'Yes' : 'No') +
                                '</span>' +
                            '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="detail-section">' +
                        '<h4><i class="fas fa-calendar"></i> Important Dates</h4>' +
                        '<div class="detail-row">' +
                            '<span class="detail-label">Join Date:</span>' +
                            '<span class="detail-value">' + (member.join_date ? formatDate(member.join_date) : 'N/A') + '</span>' +
                        '</div>' +
                        '<div class="detail-row">' +
                            '<span class="detail-label">Last Login:</span>' +
                            '<span class="detail-value">' + (member.last_login ? formatDateTime(member.last_login) : 'Never') + '</span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="detail-section" style="grid-column: 1 / -1; margin-top: 20px;">' +
                    '<h4><i class="fas fa-credit-card"></i> Membership History & Renewals</h4>' +
                    membershipHtml +
                '</div>' +
                '<div class="detail-section" style="grid-column: 1 / -1; margin-top: 20px;">' +
                    '<h4><i class="fas fa-history"></i> Recent Activity</h4>' +
                    activityHtml +
                '</div>';
            
            document.getElementById('viewModal').style.display = 'flex';
            document.getElementById('viewModal').classList.add('show');
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        console.error('Error loading member details:', error);
        showAlert('Error loading member details', 'error');
    }
}

/**
 * Open add modal
 */
function openAddModal() {
    console.log('Opening Add Member Modal');
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add Member';
    document.getElementById('formAction').value = 'add';
    document.getElementById('memberForm').reset();
    document.getElementById('memberId').value = '';
    document.getElementById('memberModal').style.display = 'flex';
    document.getElementById('memberModal').classList.add('show');
}

/**
 * Open edit modal
 */
async function openEditModal(memberId) {
    try {
        const response = await fetch('includes/members_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_one&client_id=${memberId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            const member = data.member;
            
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit"></i> Edit Member';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('memberId').value = member.client_id;
            
            // Personal Information
            document.getElementById('first_name').value = member.first_name;
            document.getElementById('middle_name').value = member.middle_name || '';
            document.getElementById('last_name').value = member.last_name;
            document.getElementById('phone').value = member.phone || '';
            document.getElementById('email').value = member.email || '';
            document.getElementById('birthdate').value = member.birthdate || '';
            document.getElementById('gender').value = member.gender || '';
            document.getElementById('address').value = member.address || '';
            document.getElementById('referral_source').value = member.referral_source || '';
            
            // Membership Plan - Set radio button based on membership type
            let planType = 'none';
            if (member.current_membership_id) {
                // Determine if it's student or regular based on the ID
                // We'll use the plan name to determine type
                if (member.current_membership_id === 1) { // Monthly Student (Member)
                    planType = 'student';
                } else if (member.current_membership_id === 2) { // Monthly Regular (Member)
                    planType = 'regular';
                }
            }
            document.querySelector(`input[name="membership_plan"][value="${planType}"]`).checked = true;
            
            // Health Information
            document.getElementById('medical_conditions').value = member.medical_conditions || '';
            document.getElementById('fitness_goals').value = member.fitness_goals || '';
            
            // Note: Assessment fields are not populated from existing member data
            // as these are for new assessments only
            document.getElementById('schedule_assessment').checked = false;
            document.getElementById('assessment_date_group').style.display = 'none';
            
            document.getElementById('memberModal').style.display = 'flex';
            document.getElementById('memberModal').classList.add('show');
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        console.error('Error loading member:', error);
        showAlert('Error loading member data', 'error');
    }
}

/**
 * Handle form submission
 */
async function handleFormSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('includes/members_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            closeMemberModal();
            loadMembers();
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        console.error('Error saving member:', error);
        showAlert('Error saving member data', 'error');
    }
}

/**
 * Verify member
 */
async function verifyMember(memberId) {
    if (!confirm('Are you sure you want to verify this member?')) {
        return;
    }
    
    try {
        const response = await fetch('includes/members_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=verify&client_id=' + memberId
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            loadMembers();
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        console.error('Error verifying member:', error);
        showAlert('Error verifying member', 'error');
    }
}

/**
 * Delete member - Show confirmation modal
 */
let deleteConfirmData = {};

function deleteMember(memberId, memberName) {
    deleteConfirmData = { memberId: memberId, memberName: memberName };
    document.getElementById('deleteMemberName').textContent = memberName;
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('closing');
    modal.classList.add('show');
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.add('closing');
    modal.classList.remove('show');
    
    setTimeout(() => {
        modal.classList.remove('closing');
        deleteConfirmData = {};
    }, 300);
}

/**
 * Confirm and execute member deletion
 */
async function confirmDeleteMember() {
    if (!deleteConfirmData.memberId) return;
    
    closeDeleteModal();
    showAlert('Deleting member...', 'info');
    
    try {
        const response = await fetch('includes/members_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=delete&client_id=' + deleteConfirmData.memberId
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message + ' Member has been permanently removed.', 'success');
            loadMembers();
        } else {
            showAlert(data.message || 'Failed to delete member', 'error');
        }
    } catch (error) {
        console.error('Error deleting member:', error);
        showAlert('Error deleting member: ' + error.message, 'error');
    }
}

/**
 * Export members
 */
async function exportMembers(format) {
    try {
        const response = await fetch('includes/members_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=export&format=' + format
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Create CSV content
            let csv = 'Member ID,First Name,Middle Name,Last Name,Email,Phone,Username,Type,Status,Account Status,Verified,Join Date,Last Login\n';
            
            data.members.forEach(member => {
                csv += member.client_id + ',"' + member.first_name + '","' + (member.middle_name || '') + '","' + member.last_name + '","' + (member.email || '') + '","' + (member.phone || '') + '","' + (member.username || '') + '","' + member.client_type + '","' + member.status + '","' + member.account_status + '","' + (member.is_verified == 1 ? 'Yes' : 'No') + '","' + (member.join_date || '') + '","' + (member.last_login || '') + '"\n';
            });
            
            // Download
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'members_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showAlert('Members exported successfully', 'success');
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        console.error('Error exporting members:', error);
        showAlert('Error exporting members', 'error');
    }
}

/**
 * Bulk actions
 */
function bulkAction() {
    const selected = selectedMembers.length;
    
    if (selected === 0) {
        showAlert('Please select members first', 'warning');
        return;
    }
    
    const action = prompt('Selected ' + selected + ' member(s). Enter action:\n1 - Activate\n2 - Suspend\n3 - Verify\n4 - Delete');
    
    if (!action) return;
    
    switch(action) {
        case '1':
            bulkUpdateStatus('Active');
            break;
        case '2':
            bulkUpdateStatus('Suspended');
            break;
        case '3':
            bulkVerify();
            break;
        case '4':
            bulkDelete();
            break;
        default:
            showAlert('Invalid action', 'error');
    }
}

/**
 * Bulk update status
 */
async function bulkUpdateStatus(status) {
    if (!confirm('Update ' + selectedMembers.length + ' member(s) to ' + status + '?')) {
        return;
    }
    
    try {
        const response = await fetch('includes/members_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'bulk_status',
                members: selectedMembers,
                status: status
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            selectedMembers = [];
            loadMembers();
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        console.error('Error updating members:', error);
        showAlert('Error updating members', 'error');
    }
}

/**
 * Toggle member selection
 */
function toggleMemberSelection(memberId) {
    const index = selectedMembers.indexOf(memberId);
    if (index > -1) {
        selectedMembers.splice(index, 1);
    } else {
        selectedMembers.push(memberId);
    }
}

/**
 * Toggle select all
 */
function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.member-checkbox');
    const selectAll = document.getElementById('selectAll').checked;
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll;
        const memberId = parseInt(checkbox.value);
        if (selectAll && !selectedMembers.includes(memberId)) {
            selectedMembers.push(memberId);
        } else if (!selectAll) {
            selectedMembers = [];
        }
    });
}

/**
 * Close modals with animation
 */
function closeViewModal() {
    const modal = document.getElementById('viewModal');
    modal.classList.add('closing');
    modal.classList.remove('show');
    
    setTimeout(() => {
        modal.style.display = 'none';
        modal.classList.remove('closing');
    }, 300);
}

function closeMemberModal() {
    const modal = document.getElementById('memberModal');
    modal.classList.add('closing');
    modal.classList.remove('show');
    
    setTimeout(() => {
        modal.style.display = 'none';
        modal.classList.remove('closing');
    }, 300);
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        if (event.target.id === 'viewModal') {
            closeViewModal();
        } else if (event.target.id === 'memberModal') {
            closeMemberModal();
        } else if (event.target.id === 'previewModal') {
            closePreviewModal();
        }
    }
}

/**
 * Utility functions
 */
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function showAlert(message, type) {
    const alertBox = document.getElementById('alertBox');
    alertBox.textContent = message;
    alertBox.className = `alert-box alert-${type} show`;
    
    setTimeout(() => {
        alertBox.classList.remove('show');
    }, 3000);
}

/**
 * Show preview modal before saving
 */
function showPreviewModal() {
    console.log('Opening Preview Modal');
    
    // Validate form first
    const form = document.getElementById('memberForm');
    if (!form.checkValidity()) {
        console.warn('Form validation failed');
        form.reportValidity();
        return;
    }
    
    // Validate age - member must be at least 18 years old
    const birthdateInput = document.getElementById('birthdate').value;
    if (birthdateInput) {
        const birthdate = new Date(birthdateInput);
        const today = new Date();
        let age = today.getFullYear() - birthdate.getFullYear();
        const monthDiff = today.getMonth() - birthdate.getMonth();
        
        // Adjust age if birthday hasn't occurred this year
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
            age--;
        }
        
        if (age < 18) {
            showAlert('Members must be at least 18 years old. This applicant is only ' + age + ' years old.', 'error');
            return;
        }
    }
    
    // Populate preview fields - Personal Information
    document.getElementById('preview_first_name').textContent = document.getElementById('first_name').value || '-';
    document.getElementById('preview_middle_name').textContent = document.getElementById('middle_name').value || '-';
    document.getElementById('preview_last_name').textContent = document.getElementById('last_name').value || '-';
    document.getElementById('preview_email').textContent = document.getElementById('email').value || '-';
    document.getElementById('preview_phone').textContent = document.getElementById('phone').value || '-';
    document.getElementById('preview_birthdate').textContent = document.getElementById('birthdate').value || '-';
    
    // Format gender display
    const genderSelect = document.getElementById('gender');
    const genderValue = genderSelect.value;
    const genderText = genderValue ? genderSelect.options[genderSelect.selectedIndex].text : '-';
    document.getElementById('preview_gender').textContent = genderText;
    
    document.getElementById('preview_referral_source').textContent = document.getElementById('referral_source').value || '-';
    
    // Format membership plan display
    const planValue = document.querySelector('input[name="membership_plan"]:checked').value;
    let planText = 'Base Membership Only';
    if (planValue === 'regular') {
        planText = 'Base Membership + Monthly Regular (₱1,000.00/month)';
    } else if (planValue === 'student') {
        planText = 'Base Membership + Monthly Student (₱800.00/month)';
    }
    document.getElementById('preview_membership_plan').textContent = planText;
    
    // Populate Health Information
    document.getElementById('preview_medical_conditions').textContent = document.getElementById('medical_conditions').value || '-';
    document.getElementById('preview_fitness_goals').textContent = document.getElementById('fitness_goals').value || '-';
    
    // Handle Assessment Information
    const scheduleAssessment = document.getElementById('schedule_assessment').checked;
    const assessmentSection = document.getElementById('preview_assessment_section');
    
    if (scheduleAssessment) {
        document.getElementById('preview_assessment_date').textContent = document.getElementById('assessment_date').value || '-';
        document.getElementById('preview_preferred_schedule').textContent = document.getElementById('preferred_schedule').value || '-';
        assessmentSection.style.display = 'block';
    } else {
        assessmentSection.style.display = 'none';
    }
    
    console.log('Preview data populated');
    
    // Show preview modal
    document.getElementById('previewModal').style.display = 'flex';
    document.getElementById('previewModal').classList.add('show');
    
    console.log('Preview modal displayed');
}

/**
 * Close preview modal
 */
function closePreviewModal() {
    const modal = document.getElementById('previewModal');
    modal.classList.add('closing');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
        modal.classList.remove('closing');
    }, 300);
}

/**
 * Confirm and save member from preview modal
 */
async function confirmAddMember() {
    const form = document.getElementById('memberForm');
    const formData = new FormData(form);
    const action = document.getElementById('formAction').value || 'add';
    
    // Ensure action is set
    formData.set('action', action);
    
    try {
        const actionText = action === 'add' ? 'Creating member account...' : 'Updating member...';
        showAlert(actionText, 'info');
        
        console.log('Sending form data with action:', action);
        
        const response = await fetch('includes/members_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        console.log('Response received:', responseText);
        
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response:', responseText);
            showAlert('Server error. Please try again.', 'error');
            return;
        }
        
        if (data.success) {
            const successMsg = action === 'add' 
                ? data.message + ' Credentials have been sent to the email.' 
                : data.message;
            showAlert(successMsg, 'success');
            
            // Close preview modal first, then member modal
            closePreviewModal();
            setTimeout(() => {
                closeMemberModal();
                loadMembers();
            }, 350);
        } else {
            showAlert(data.message || 'An error occurred', 'error');
            console.error('Server returned error:', data);
        }
    } catch (error) {
        console.error('Error saving member:', error);
        showAlert('Error saving member data: ' + error.message, 'error');
    }
}

/**
 * Load pending membership payment verifications
 */
function loadPendingPayments() {
    fetch('includes/members_handler.php?action=get_pending_payments')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const count = data.count || 0;
                document.getElementById('pending-count-badge').textContent = count;
                
                if (currentFilter === 'pending_payment') {
                    displayPendingPayments(data.pending_payments || []);
                }
            }
        })
        .catch(error => console.error('Error loading pending payments:', error));
}

/**
 * Display pending payment verifications in members table
 */
function displayPendingPayments(payments) {
    const tbody = document.getElementById('membersTableBody');
    
    if (!payments || payments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="15" class="no-data"><i class="fas fa-inbox"></i><p>No pending payment verifications</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = payments.map(payment => `
        <tr>
            <td><input type="checkbox" class="member-checkbox" value="${payment.client_id}"></td>
            <td>${payment.client_id}</td>
            <td>${payment.first_name} ${payment.last_name}</td>
            <td>${payment.email}</td>
            <td>${payment.phone}</td>
            <td><span class="badge">${payment.client_type}</span></td>
            <td>${payment.plan_name}</td>
            <td>${payment.is_renewal ? 'Renewal' : 'New'}</td>
            <td>
                <span class="badge warning">
                    <i class="fas fa-hourglass-half"></i> Pending
                </span>
                <small style="display: block; color: #666; margin-top: 5px;">${new Date(payment.payment_date).toLocaleDateString()}</small>
            </td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>
                <button class="action-btn" onclick="openPaymentVerification(${payment.payment_id}, '${payment.first_name} ${payment.last_name}', '${payment.plan_name}', '${new Date(payment.payment_date).toLocaleDateString()}', ${payment.amount}, '${payment.payment_method}', '${payment.membership_start}', '${payment.membership_end}', '${payment.reference_id}')" title="Verify Payment">
                    <i class="fas fa-check-circle"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Open payment verification modal
 */
function openPaymentVerification(paymentId, memberName, planName, paymentDate, amount, method, startDate, endDate, referenceId) {
    document.getElementById('verify-member-name').textContent = memberName;
    document.getElementById('verify-plan-name').textContent = planName;
    document.getElementById('verify-payment-date').textContent = paymentDate;
    document.getElementById('verify-amount').textContent = '₱' + parseFloat(amount).toFixed(2);
    document.getElementById('verify-payment-method').textContent = method;
    document.getElementById('verify-membership-period').textContent = `${startDate} to ${endDate}`;
    document.getElementById('verify-reference-id').textContent = referenceId;
    document.getElementById('verify-remarks').textContent = '';
    
    // Store payment ID for later use
    document.getElementById('paymentVerificationModal').dataset.paymentId = paymentId;
    
    const modal = document.getElementById('paymentVerificationModal');
    modal.style.display = 'block';
}

/**
 * Close payment verification modal
 */
function closePaymentVerificationModal() {
    const modal = document.getElementById('paymentVerificationModal');
    modal.style.display = 'none';
}

/**
 * Approve membership payment
 */
function approveMembershipPayment() {
    const paymentId = document.getElementById('paymentVerificationModal').dataset.paymentId;
    const remarks = document.getElementById('verify-remarks').value;
    
    const formData = new FormData();
    formData.append('action', 'approve_payment');
    formData.append('payment_id', paymentId);
    formData.append('remarks', remarks);
    
    fetch('includes/members_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Payment approved successfully!', 'success');
            closePaymentVerificationModal();
            loadMembers();
            loadPendingPayments();
        } else {
            showAlert(data.message || 'Failed to approve payment', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error approving payment: ' + error.message, 'error');
    });
}

/**
 * Reject membership payment
 */
function rejectMembershipPayment() {
    const remarks = document.getElementById('verify-remarks').value;
    
    if (!remarks.trim()) {
        showAlert('Please provide a reason for rejection', 'warning');
        return;
    }
    
    const paymentId = document.getElementById('paymentVerificationModal').dataset.paymentId;
    
    const formData = new FormData();
    formData.append('action', 'reject_payment');
    formData.append('payment_id', paymentId);
    formData.append('remarks', remarks);
    
    fetch('includes/members_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Payment rejected successfully!', 'success');
            closePaymentVerificationModal();
            loadMembers();
            loadPendingPayments();
        } else {
            showAlert(data.message || 'Failed to reject payment', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error rejecting payment: ' + error.message, 'error');
    });
}