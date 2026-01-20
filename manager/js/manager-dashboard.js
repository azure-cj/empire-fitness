// Manager Dashboard JavaScript

// Initialize dashboard on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('📊 Manager Dashboard loaded');
    
    // Load dashboard data
    loadStats();
    loadActivity();
    loadApprovals();
    
    // Refresh data every 30 seconds
    setInterval(() => {
        loadStats();
    }, 30000);
    
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
});

// Load statistics
function loadStats() {
    console.log('📈 Loading statistics...');
    fetch('includes/dashboard_data.php?action=get_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Stats loaded:', data);
                updateStats(data);
            } else {
                console.error('❌ Stats Error:', data.message);
            }
        })
        .catch(error => console.error('❌ Stats fetch error:', error));
}

// Update statistics on page
function updateStats(data) {
    const coachesEl = document.getElementById('coaches-count');
    const staffEl = document.getElementById('staff-count');
    const requestsEl = document.getElementById('requests-count');
    const assignmentsEl = document.getElementById('assignments-count');
    
    if (coachesEl) coachesEl.textContent = data.coaches;
    if (staffEl) staffEl.textContent = data.staff;
    if (requestsEl) requestsEl.textContent = data.requests;
    if (assignmentsEl) assignmentsEl.textContent = data.assignments;
    
    // Update badge visibility
    const badge = document.getElementById('pending-badge');
    const notifBadge = document.getElementById('notification-badge');
    const moduleBadge = document.getElementById('module-badge');
    const requestsStatus = document.getElementById('requests-status');
    
    if (data.requests > 0) {
        if (badge) {
            badge.style.display = 'inline';
            badge.textContent = data.requests;
        }
        if (notifBadge) {
            notifBadge.style.display = 'inline';
            notifBadge.textContent = data.requests;
        }
        if (moduleBadge) {
            moduleBadge.style.display = 'inline';
            moduleBadge.textContent = data.requests + ' pending';
        }
        if (requestsStatus) {
            requestsStatus.classList.add('negative');
            requestsStatus.classList.remove('neutral');
        }
    } else {
        if (badge) badge.style.display = 'none';
        if (notifBadge) notifBadge.style.display = 'none';
        if (moduleBadge) moduleBadge.style.display = 'none';
        if (requestsStatus) {
            requestsStatus.classList.remove('negative');
            requestsStatus.classList.add('neutral');
        }
    }
}

// Load activity
function loadActivity() {
    console.log('📝 Loading activity...');
    fetch('includes/dashboard_data.php?action=get_activity')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Activity loaded:', data);
                displayActivity(data.activities);
            } else {
                console.error('❌ Activity Error:', data.message);
            }
        })
        .catch(error => console.error('❌ Activity fetch error:', error));
}

// Display activity
function displayActivity(activities) {
    const activityList = document.getElementById('activity-list');
    
    if (!activities || activities.length === 0) {
        activityList.innerHTML = '<div class="no-data">No recent activity</div>';
        return;
    }
    
    let html = '';
    const icons = {
        'assignment': 'user-plus blue',
        'schedule': 'calendar-check green',
        'request': 'calendar-times orange',
        'assessment': 'clipboard-check purple'
    };
    
    activities.forEach(activity => {
        const iconClass = icons[activity.type] || 'info-circle gray';
        html += `
            <div class="activity-item">
                <div class="activity-icon ${iconClass}">
                    <i class="fas fa-${activity.icon || 'circle'}"></i>
                </div>
                <div class="activity-content">
                    <p class="activity-title">${activity.title}</p>
                    <p class="activity-time">${activity.time}</p>
                </div>
            </div>
        `;
    });
    
    activityList.innerHTML = html;
}

// Load approvals
function loadApprovals() {
    console.log('✅ Loading approvals...');
    fetch('includes/dashboard_data.php?action=get_approvals')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Approvals loaded:', data);
                displayApprovals(data.approvals);
            } else {
                console.error('❌ Approvals Error:', data.message);
            }
        })
        .catch(error => console.error('❌ Approvals fetch error:', error));
}

// Display approvals
function displayApprovals(approvals) {
    const approvalList = document.getElementById('approval-list');
    
    if (!approvals || approvals.length === 0) {
        approvalList.innerHTML = '<div class="no-data">No pending approvals</div>';
        return;
    }
    
    let html = '';
    const iconMap = {
        'vacation': 'calendar-times orange',
        'sick': 'calendar-times orange',
        'schedule': 'calendar-alt blue'
    };
    
    approvals.forEach(approval => {
        const type = approval.booking_type ? approval.booking_type.toLowerCase() : 'schedule';
        const iconClass = iconMap[type] || 'calendar-alt blue';
        const requestId = approval.request_id || 0;
        const clientName = approval.client_name || 'Unknown';
        const bookingType = approval.booking_type || 'Request';
        const date = approval.date || 'N/A';
        
        html += `
            <div class="approval-item">
                <div class="approval-icon ${iconClass}">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="approval-content">
                    <p class="approval-title">${clientName} - ${bookingType}</p>
                    <p class="approval-details">${date}</p>
                </div>
                <div class="approval-actions">
                    <button class="btn-approve-small" data-request-id="${requestId}" title="Approve">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn-reject-small" data-request-id="${requestId}" title="Reject">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    approvalList.innerHTML = html;
    
    // Add event listeners to buttons
    approvalList.querySelectorAll('.btn-approve-small').forEach(btn => {
        btn.addEventListener('click', function() {
            const requestId = this.getAttribute('data-request-id');
            approveRequest(parseInt(requestId));
        });
    });
    
    approvalList.querySelectorAll('.btn-reject-small').forEach(btn => {
        btn.addEventListener('click', function() {
            const requestId = this.getAttribute('data-request-id');
            rejectRequest(parseInt(requestId));
        });
    });
}

// Approve request
function approveRequest(requestId) {
    console.log('✅ Approving request:', requestId);
    showToast('Request approved!', 'success');
    loadApprovals();
    loadStats();
}

// Reject request
function rejectRequest(requestId) {
    console.log('❌ Rejecting request:', requestId);
    showToast('Request rejected!', 'error');
    loadApprovals();
    loadStats();
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    const toastIcon = toast.querySelector('.toast-icon');
    
    toastMessage.textContent = message;
    toast.classList.remove('success', 'error');
    toast.classList.add(type);
    
    if (type === 'success') {
        toastIcon.className = 'toast-icon fas fa-check-circle';
    } else {
        toastIcon.className = 'toast-icon fas fa-exclamation-circle';
    }
    
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}