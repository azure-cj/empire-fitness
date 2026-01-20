// Members Management JavaScript

let allMembers = [];
let filteredMembers = [];
let currentFilter = 'all';
let selectedMembers = [];
let currentPagination = null;
let currentPage = 1;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadMembers();
    setupEventListeners();
});

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Form submission
    document.getElementById('memberForm').addEventListener('submit', handleFormSubmit);
    
    // Search input with debounce
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            searchMembers();
        }, 300);
    });
}

/**
 * Load all members with pagination
 */
async function loadMembers(page = 1) {
    try {
        console.log('Loading members, page:', page);
        
        // Load full dataset for filtering (only on first load)
        if (allMembers.length === 0) {
            try {
                const filterResponse = await fetch('includes/members_handler.php?action=get_all_for_filter');
                const filterData = await filterResponse.json();
                if (filterData.success) {
                    allMembers = filterData.members;
                    console.log('Full dataset loaded:', allMembers.length);
                }
            } catch (e) {
                console.error('Error loading filter data:', e);
            }
        }
        
        // Load paginated data for display
        const formData = new FormData();
        formData.append('action', 'get_all');
        formData.append('page', page);
        
        const response = await fetch('includes/members_handler.php', {
            method: 'POST',
            body: formData
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
            currentPagination = data.pagination;
            currentPage = page;
            filteredMembers = data.members;
            console.log('Page members loaded:', filteredMembers.length);
            updateStats(allMembers); // Pass full dataset for accurate stats
            renderMembersTable(filteredMembers);
            renderPagination();
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
 * Render pagination controls
 */
function renderPagination() {
    const container = document.getElementById('membersPaginationContainer');
    
    if (!currentPagination || currentPagination.totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<div class="pagination">';
    
    // Previous button
    if (currentPagination.hasPreviousPage) {
        html += `<button class="pagination-btn prev-btn" onclick="loadMembers(${currentPagination.currentPage - 1})">
                    <i class="fas fa-chevron-left"></i> Previous
                 </button>`;
    } else {
        html += `<span class="pagination-btn prev-btn disabled"><i class="fas fa-chevron-left"></i> Previous</span>`;
    }
    
    // Page numbers
    html += '<div class="pagination-pages">';
    
    // First page if not in range
    const pageRange = getPageRange(currentPagination.currentPage, currentPagination.totalPages, 5);
    if (pageRange[0] > 1) {
        html += `<button class="page-num" onclick="loadMembers(1)">1</button>`;
        if (pageRange[0] > 2) {
            html += `<span class="page-ellipsis">...</span>`;
        }
    }
    
    // Page range
    for (let page of pageRange) {
        if (page === currentPagination.currentPage) {
            html += `<span class="page-num active">${page}</span>`;
        } else {
            html += `<button class="page-num" onclick="loadMembers(${page})">${page}</button>`;
        }
    }
    
    // Last page if not in range
    if (pageRange[pageRange.length - 1] < currentPagination.totalPages) {
        if (pageRange[pageRange.length - 1] < currentPagination.totalPages - 1) {
            html += `<span class="page-ellipsis">...</span>`;
        }
        html += `<button class="page-num" onclick="loadMembers(${currentPagination.totalPages})">${currentPagination.totalPages}</button>`;
    }
    
    html += '</div>';
    
    // Next button
    if (currentPagination.hasNextPage) {
        html += `<button class="pagination-btn next-btn" onclick="loadMembers(${currentPagination.currentPage + 1})">
                    Next <i class="fas fa-chevron-right"></i>
                 </button>`;
    } else {
        html += `<span class="pagination-btn next-btn disabled">Next <i class="fas fa-chevron-right"></i></span>`;
    }
    
    html += '</div>';
    
    // Info text
    html += `<div class="pagination-info">${currentPagination.info}</div>`;
    
    container.innerHTML = html;
}

/**
 * Get page range for display
 */
function getPageRange(currentPage, totalPages, range = 5) {
    let start = Math.max(1, currentPage - Math.floor(range / 2));
    let end = Math.min(totalPages, start + range - 1);
    
    if (end - start < range - 1) {
        start = Math.max(1, end - range + 1);
    }
    
    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
}

/**
 * Update statistics (based on full dataset)
 */
function updateStats(fullDataset = null) {
    const dataset = fullDataset || filteredMembers;
    const total = dataset.length;
    const active = dataset.filter(m => m.status === 'Active').length;
    const inactive = dataset.filter(m => m.status === 'Inactive').length;
    const suspended = dataset.filter(m => m.status === 'Suspended').length;
    const verified = dataset.filter(m => m.is_verified == 1).length;
    
    // Calculate new members this month
    const now = new Date();
    const firstDayOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    const newThisMonth = dataset.filter(m => {
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
                <td colspan="12" class="no-data">
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
    currentPage = 1; // Reset to first page
    
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.closest('.filter-btn').classList.add('active');
    
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
    
    // Simulate pagination for filtered results (20 per page)
    const itemsPerPage = 20;
    const totalPages = Math.ceil(filteredMembers.length / itemsPerPage);
    const offset = 0;
    
    const pageMembers = filteredMembers.slice(offset, offset + itemsPerPage);
    
    // Create pagination object for filtered results
    currentPagination = {
        currentPage: 1,
        totalPages: totalPages,
        totalItems: filteredMembers.length,
        itemsPerPage: itemsPerPage,
        hasNextPage: totalPages > 1,
        hasPreviousPage: false,
        info: `Showing 1 to ${Math.min(itemsPerPage, filteredMembers.length)} of ${filteredMembers.length} items`
    };
    
    updateStats(allMembers);
    renderMembersTable(pageMembers);
    renderPagination();
}

/**
 * Search members
 */
function searchMembers() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
    currentPage = 1; // Reset to first page
    
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
    
    // Apply filter to search results
    let filtered = filteredMembers;
    switch(currentFilter) {
        case 'active':
            filtered = filteredMembers.filter(m => m.status === 'Active');
            break;
        case 'inactive':
            filtered = filteredMembers.filter(m => m.status === 'Inactive');
            break;
        case 'suspended':
            filtered = filteredMembers.filter(m => m.status === 'Suspended');
            break;
        case 'verified':
            filtered = filteredMembers.filter(m => m.is_verified == 1);
            break;
        case 'unverified':
            filtered = filteredMembers.filter(m => m.is_verified == 0);
            break;
    }
    
    // Simulate pagination for filtered results
    const itemsPerPage = 20;
    const totalPages = Math.ceil(filtered.length / itemsPerPage);
    const offset = 0;
    
    const pageMembers = filtered.slice(offset, offset + itemsPerPage);
    
    // Create pagination object for search results
    currentPagination = {
        currentPage: 1,
        totalPages: totalPages,
        totalItems: filtered.length,
        itemsPerPage: itemsPerPage,
        hasNextPage: totalPages > 1,
        hasPreviousPage: false,
        info: `Showing 1 to ${Math.min(itemsPerPage, filtered.length)} of ${filtered.length} items`
    };
    
    updateStats(allMembers);
    renderMembersTable(pageMembers);
    renderPagination();
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
            const activities = data.activities || [];
            const fullName = member.first_name + ' ' + (member.middle_name ? member.middle_name + ' ' : '') + member.last_name;
            
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
                    '<div class="detail-section">' +
                        '<h4><i class="fas fa-dumbbell"></i> Membership Info</h4>' +
                        '<div class="detail-row">' +
                            '<span class="detail-label">Current Membership:</span>' +
                            '<span class="detail-value">' + (member.current_membership_id || 'None') + '</span>' +
                        '</div>' +
                        '<div class="detail-row">' +
                            '<span class="detail-label">Assigned Coach:</span>' +
                            '<span class="detail-value">' + (member.assigned_coach_id || 'None') + '</span>' +
                        '</div>' +
                    '</div>' +
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
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add Member';
    document.getElementById('formAction').value = 'add';
    document.getElementById('memberForm').reset();
    document.getElementById('memberId').value = '';
    document.getElementById('password').required = true;
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
            document.getElementById('first_name').value = member.first_name;
            document.getElementById('middle_name').value = member.middle_name || '';
            document.getElementById('last_name').value = member.last_name;
            document.getElementById('phone').value = member.phone || '';
            document.getElementById('email').value = member.email || '';
            document.getElementById('username').value = member.username || '';
            document.getElementById('client_type').value = member.client_type;
            document.getElementById('status').value = member.status;
            document.getElementById('account_status').value = member.account_status;
            document.getElementById('is_verified').value = member.is_verified;
            document.getElementById('password').required = false;
            document.getElementById('password').value = '';
            
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
 * Delete member
 */
async function deleteMember(memberId, memberName) {
    if (!confirm('Are you sure you want to delete ' + memberName + '? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch('includes/members_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=delete&client_id=' + memberId
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            loadMembers();
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        console.error('Error deleting member:', error);
        showAlert('Error deleting member', 'error');
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