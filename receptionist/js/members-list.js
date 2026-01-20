// Members List Management
let currentPage = 1;
let itemsPerPage = 10;
let totalMembers = 0;
let allMembers = [];
let filteredMembers = [];
let currentMemberId = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadMembersStats();
    loadMembers();
    
    // Search functionality
    document.getElementById('search-members').addEventListener('input', debounce(filterMembers, 300));
    
    // Filter functionality
    document.getElementById('filter-status').addEventListener('change', filterMembers);
    document.getElementById('filter-type').addEventListener('change', filterMembers);
});

// Load members statistics
function loadMembersStats() {
    fetch('includes/members_list_handler.php?action=get_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('total-members').textContent = data.stats.total;
                document.getElementById('active-members').textContent = data.stats.active;
                document.getElementById('expiring-members').textContent = data.stats.expiring;
                document.getElementById('inactive-members').textContent = data.stats.inactive;
            }
        })
        .catch(error => console.error('Error loading stats:', error));
}

// Load all members
function loadMembers() {
    showLoadingState();
    
    fetch('includes/members_list_handler.php?action=get_members')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allMembers = data.members;
                filteredMembers = [...allMembers];
                totalMembers = filteredMembers.length;
                displayMembers();
            } else {
                showEmptyState('Error loading members');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showEmptyState('Failed to load members');
        });
}

// Display members in table
function displayMembers() {
    const tbody = document.getElementById('members-tbody');
    
    if (filteredMembers.length === 0) {
        showEmptyState('No members found');
        return;
    }
    
    // Calculate pagination
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, filteredMembers.length);
    const pageMembers = filteredMembers.slice(startIndex, endIndex);
    
    // Build table rows
    let html = '';
    pageMembers.forEach(member => {
        const statusClass = member.status.toLowerCase();
        const typeClass = member.client_type.toLowerCase().replace('-', '');
        
        html += `
            <tr>
                <td><span class="member-id">#${String(member.client_id).padStart(4, '0')}</span></td>
                <td><span class="member-name">${escapeHtml(member.full_name)}</span></td>
                <td>${member.email || '<span style="color: #9ca3af;">N/A</span>'}</td>
                <td>${member.phone || '<span style="color: #9ca3af;">N/A</span>'}</td>
                <td>
                    <span class="member-type-badge ${typeClass}">
                        ${member.client_type}
                    </span>
                </td>
                <td>${member.membership_plan || '<span style="color: #9ca3af;">None</span>'}</td>
                <td>
                    <span class="status-badge ${statusClass}">
                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                        ${member.status}
                    </span>
                </td>
                <td>${formatDate(member.join_date)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn view" onclick="viewMember(${member.client_id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn edit" onclick="editMember(${member.client_id})" title="Edit Member">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    updatePagination();
}

// Filter members based on search and filters
function filterMembers() {
    const searchTerm = document.getElementById('search-members').value.toLowerCase();
    const statusFilter = document.getElementById('filter-status').value;
    const typeFilter = document.getElementById('filter-type').value;
    
    filteredMembers = allMembers.filter(member => {
        const matchesSearch = !searchTerm || 
            member.full_name.toLowerCase().includes(searchTerm) ||
            (member.email && member.email.toLowerCase().includes(searchTerm)) ||
            (member.phone && member.phone.includes(searchTerm)) ||
            String(member.client_id).includes(searchTerm);
        
        const matchesStatus = !statusFilter || member.status === statusFilter;
        const matchesType = !typeFilter || member.client_type === typeFilter;
        
        return matchesSearch && matchesStatus && matchesType;
    });
    
    totalMembers = filteredMembers.length;
    currentPage = 1;
    displayMembers();
}

// Update pagination controls
function updatePagination() {
    const totalPages = Math.ceil(totalMembers / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage + 1;
    const endIndex = Math.min(startIndex + itemsPerPage - 1, totalMembers);
    
    // Update info text
    document.getElementById('showing-start').textContent = totalMembers > 0 ? startIndex : 0;
    document.getElementById('showing-end').textContent = endIndex;
    document.getElementById('total-records').textContent = totalMembers;
    
    // Update pagination buttons
    document.getElementById('prev-page').disabled = currentPage === 1;
    document.getElementById('next-page').disabled = currentPage === totalPages || totalPages === 0;
    
    // Generate page numbers
    const pageNumbersContainer = document.getElementById('page-numbers');
    let pageNumbersHtml = '';
    
    // Show max 5 page numbers
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        pageNumbersHtml += `
            <button class="page-number ${i === currentPage ? 'active' : ''}" 
                    onclick="goToPage(${i})">
                ${i}
            </button>
        `;
    }
    
    pageNumbersContainer.innerHTML = pageNumbersHtml;
}

// Change page
function changePage(direction) {
    const totalPages = Math.ceil(totalMembers / itemsPerPage);
    
    if (direction === 'prev' && currentPage > 1) {
        currentPage--;
    } else if (direction === 'next' && currentPage < totalPages) {
        currentPage++;
    }
    
    displayMembers();
}

// Go to specific page
function goToPage(page) {
    currentPage = page;
    displayMembers();
}

// View member details
function viewMember(memberId) {
    currentMemberId = memberId;
    
    fetch(`includes/members_list_handler.php?action=get_member_details&member_id=${memberId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateMemberModal(data.member);
                openMemberModal();
            } else {
                showToast('Failed to load member details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load member details', 'error');
        });
}

// Populate member modal with data
function populateMemberModal(member) {
    document.getElementById('member-name').textContent = member.full_name;
    document.getElementById('member-email').textContent = member.email || 'N/A';
    document.getElementById('member-phone').textContent = member.phone || 'N/A';
    document.getElementById('member-referral').textContent = member.referral_source || 'N/A';
    
    document.getElementById('member-type').textContent = member.client_type;
    document.getElementById('member-plan').textContent = member.membership_plan || 'None';
    document.getElementById('member-start').textContent = member.membership_start ? formatDate(member.membership_start) : 'N/A';
    document.getElementById('member-end').textContent = member.membership_end ? formatDate(member.membership_end) : 'N/A';
    document.getElementById('member-days').textContent = member.days_remaining || 'N/A';
    document.getElementById('member-base-plan').textContent = member.base_plan_name || 'None';
    document.getElementById('member-base-days').textContent = member.base_days_remaining || 'N/A';
    document.getElementById('member-monthly-plan').textContent = member.monthly_plan_name || 'None';
    document.getElementById('member-monthly-days').textContent = member.monthly_days_remaining || 'N/A';
    
    const statusHtml = `<span class="status-badge ${member.status.toLowerCase()}">${member.status}</span>`;
    document.getElementById('member-status').innerHTML = statusHtml;
    document.getElementById('member-join').textContent = formatDate(member.join_date);
    document.getElementById('member-login').textContent = member.last_login ? formatDateTime(member.last_login) : 'Never';
    
    const verifiedHtml = member.is_verified ? 
        '<span style="color: #48c774;"><i class="fas fa-check-circle"></i> Verified</span>' : 
        '<span style="color: #f59e0b;"><i class="fas fa-exclamation-circle"></i> Not Verified</span>';
    document.getElementById('member-verified').innerHTML = verifiedHtml;
    
    document.getElementById('member-coach').textContent = member.coach_name || 'Not Assigned';
}

// Open member modal
function openMemberModal() {
    document.getElementById('member-modal').classList.add('active');
}

// Close member modal
function closeMemberModal() {
    document.getElementById('member-modal').classList.remove('active');
    currentMemberId = null;
}

// View member history
function viewMemberHistory() {
    if (currentMemberId) {
        window.location.href = `member_history.php?member_id=${currentMemberId}`;
    }
}

// Edit member (placeholder)
function editMember(memberId) {
    showToast('Edit functionality coming soon', 'info');
}

// Export members
function exportMembers() {
    showToast('Exporting members...', 'info');
    
    fetch('includes/members_list_handler.php?action=export_members')
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `members_list_${formatDateForFilename(new Date())}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            showToast('Members exported successfully', 'success');
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to export members', 'error');
        });
}

// Show loading state
function showLoadingState() {
    const tbody = document.getElementById('members-tbody');
    tbody.innerHTML = `
        <tr class="empty-row">
            <td colspan="9" class="text-center">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading members...</p>
            </td>
        </tr>
    `;
}

// Show empty state
function showEmptyState(message) {
    const tbody = document.getElementById('members-tbody');
    tbody.innerHTML = `
        <tr class="empty-row">
            <td colspan="9" class="text-center">
                <i class="fas fa-inbox"></i>
                <p>${message}</p>
            </td>
        </tr>
    `;
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    const toastIcon = toast.querySelector('.toast-icon');
    
    toastMessage.textContent = message;
    
    // Update icon based on type
    toastIcon.className = 'toast-icon fas';
    if (type === 'success') {
        toastIcon.classList.add('fa-check-circle');
    } else if (type === 'error') {
        toastIcon.classList.add('fa-times-circle');
    } else if (type === 'info') {
        toastIcon.classList.add('fa-info-circle');
    } else if (type === 'warning') {
        toastIcon.classList.add('fa-exclamation-circle');
    }
    
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatDateForFilename(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}${month}${day}`;
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('member-modal');
    if (event.target === modal) {
        closeMemberModal();
    }
});