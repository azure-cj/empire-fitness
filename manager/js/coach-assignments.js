// Coach Assignments Management System (patched debug version)
// ==========================================================

// Global variables
let allAssignments = [];
let allCoaches = [];
let allMembers = [];
let unassignedMembers = [];
let selectedMembers = [];

// Global error handlers for easier debugging
window.addEventListener('error', (e) => {
    console.error('Global error captured:', e.message, 'at', e.filename + ':' + e.lineno + ':' + e.colno);
    const alertBox = document.getElementById('alertBox');
    if (alertBox) {
        alertBox.className = 'alert alert-error';
        alertBox.textContent = 'A JavaScript error occurred. Check console for details.';
        alertBox.style.display = 'block';
        setTimeout(() => alertBox.style.display = 'none', 6000);
    }
});
window.addEventListener('unhandledrejection', (e) => {
    console.error('Unhandled promise rejection:', e.reason);
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('coach-assignments.js loaded');
    loadInitialData();
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    // Search and filter inputs
    const searchAssignments = document.getElementById('searchAssignments');
    const searchUnassigned = document.getElementById('searchUnassigned');
    
    if (searchAssignments) {
        searchAssignments.addEventListener('input', debounce(filterAssignments, 300));
    } else {
        console.log('searchAssignments input not found on page');
    }
    
    if (searchUnassigned) {
        searchUnassigned.addEventListener('input', debounce(filterUnassigned, 300));
    } else {
        console.log('searchUnassigned input not found on page');
    }
}

// Load all initial data
function loadInitialData() {
    // Focus the page on assignment workflows:
    loadQuickAssignData();
    loadAssignments();
    loadCoachFilters();
    loadUnassignedMembers();
    loadCoachStatistics();
    loadRecentAssessments();
}

// Tab switching
function switchTab(tabName) {
    // Hide all tabs
    const tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    // Remove active class from all buttons
    const buttons = document.querySelectorAll('.tab-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab
    const selectedTab = document.getElementById(tabName + 'Tab');
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Add active class to clicked button
    const selectedBtn = document.querySelector(`[data-tab="${tabName}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    // Load data for specific tabs
    if (tabName === 'assignments') {
        loadAssignments();
    } else if (tabName === 'statistics') {
        loadCoachStatistics();
        loadRecentAssessments();
    } else if (tabName === 'unassigned') {
        loadUnassignedMembers();
    } else if (tabName === 'overview') {
        // On overview, refresh quick-assign data
        loadQuickAssignData();
    }
}

/**
 * Quick Assign: load coaches and unassigned members for the overview quick-assign panel
 */
function loadQuickAssignData() {
    console.log('Loading quick assign data...');
    // Load unassigned members into quickAssignMemberId
    fetch('includes/coach_assignment_handler.php?action=get_unassigned_members')
        .then(response => response.json())
        .then(data => {
            const memberSelect = document.getElementById('quickAssignMemberId');
            if (!memberSelect) return;
            if (data.success) {
                unassignedMembers = data.members;
                let optionsHtml = '<option value="">Choose a member...</option>';
                data.members.forEach(member => {
                    optionsHtml += `<option value="${member.client_id}">${escapeHtml(member.name)} (${escapeHtml(member.email || 'N/A')})</option>`;
                });
                memberSelect.innerHTML = optionsHtml;
            } else {
                memberSelect.innerHTML = `<option value="">${escapeHtml(data.message || 'Failed to load members')}</option>`;
            }
        })
        .catch(error => {
            console.error('Error loading quick assign members:', error);
            const memberSelect = document.getElementById('quickAssignMemberId');
            if (memberSelect) memberSelect.innerHTML = '<option value="">Failed to load members</option>';
        });

    // Load coaches into quickAssignCoachId
    fetch('includes/coach_assignment_handler.php?action=get_coaches')
        .then(response => response.json())
        .then(data => {
            const coachSelect = document.getElementById('quickAssignCoachId');
            if (!coachSelect) return;
            if (data.success) {
                allCoaches = data.coaches;
                let optionsHtml = '<option value="">Choose a coach...</option>';
                data.coaches.forEach(coach => {
                    optionsHtml += `<option value="${coach.coach_id}">${escapeHtml(coach.name)} (${coach.client_count} clients)</option>`;
                });
                coachSelect.innerHTML = optionsHtml;
            } else {
                coachSelect.innerHTML = `<option value="">${escapeHtml(data.message || 'Failed to load coaches')}</option>`;
            }
        })
        .catch(error => {
            console.error('Error loading quick assign coaches:', error);
            const coachSelect = document.getElementById('quickAssignCoachId');
            if (coachSelect) coachSelect.innerHTML = '<option value="">Failed to load coaches</option>';
        });
}

// Quick assign save (overview)
function quickAssignSave() {
    console.log('quickAssignSave called');
    const clientSel = document.getElementById('quickAssignMemberId');
    const coachSel = document.getElementById('quickAssignCoachId');
    if (!clientSel || !coachSel) {
        showAlert('Quick assign controls not found', 'error');
        return;
    }
    const clientId = clientSel.value;
    const coachId = coachSel.value;
    const notesEl = document.getElementById('quickAssignNotes');
    const notes = notesEl ? notesEl.value : '';

    if (!clientId || !coachId) {
        showAlert('Please select both a member and a coach', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'assign_coach');
    formData.append('client_id', clientId);
    formData.append('coach_id', coachId);
    formData.append('notes', notes);

    fetch('includes/coach_assignment_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Send notifications to coach and member
            sendAssignmentNotifications(clientId, coachId, notes);
            showAlert('Assignment saved successfully and notifications sent', 'success');
            // refresh UI areas
            loadAssignments();
            loadUnassignedMembers();
            loadQuickAssignData();
            loadCoachFilters();
            // Clear form
            if (clientSel) clientSel.value = '';
            if (coachSel) coachSel.value = '';
            if (notesEl) notesEl.value = '';
        } else {
            console.error('Quick assign failed:', data);
            showAlert(data.message || 'Error saving assignment', 'error');
        }
    })
    .catch(error => {
        console.error('Error saving quick assignment:', error);
        showAlert('Error saving assignment', 'error');
    });
}

// Load assignments table
function loadAssignments() {
    const tableBody = document.getElementById('assignmentsTableBody');
    if (!tableBody) {
        console.log('assignmentsTableBody element not found');
        return;
    }
    tableBody.innerHTML = '<tr><td colspan="8" class="no-data"><i class="fas fa-spinner fa-spin"></i><p>Loading assignments...</p></td></tr>';
    
    fetch('includes/coach_assignment_handler.php?action=get_assignments')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allAssignments = data.assignments;
                displayAssignments(data.assignments);
            } else {
                tableBody.innerHTML = `<tr><td colspan="8" class="no-data"><i class="fas fa-exclamation-circle"></i><p>${data.message}</p></td></tr>`;
            }
        })
        .catch(error => {
            console.error('Error loading assignments:', error);
            tableBody.innerHTML = '<tr><td colspan="8" class="no-data"><i class="fas fa-exclamation-circle"></i><p>Failed to load assignments</p></td></tr>';
        });
}

// Display assignments in table
function displayAssignments(assignments) {
    const tableBody = document.getElementById('assignmentsTableBody');
    if (!tableBody) return;
    
    if (assignments.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="8" class="no-data"><i class="fas fa-inbox"></i><p>No assignments found</p></td></tr>';
        return;
    }
    
    let html = '';
    assignments.forEach(assignment => {
        const statusClass = assignment.client_status === 'Active' ? 'active' : 'inactive';
        const coachName = assignment.coach_name && assignment.coach_name.trim() !== '' ? assignment.coach_name : 'Unassigned';
        
        html += `
            <tr>
                <td>
                    <div class="member-cell">
                        <div class="member-avatar">${assignment.first_name.charAt(0)}</div>
                        <div class="member-info">
                            <strong>${escapeHtml(assignment.first_name + ' ' + assignment.last_name)}</strong>
                            <span>${assignment.client_type}</span>
                        </div>
                    </div>
                </td>
                <td>${escapeHtml(assignment.email || 'N/A')}</td>
                <td>${escapeHtml(assignment.phone || 'N/A')}</td>
                <td>
                    <div class="coach-cell">
                        ${coachName !== 'Unassigned' ?  `
                            <strong>${escapeHtml(coachName)}</strong>
                            <span class="coach-status ${assignment.coach_status.toLowerCase()}">${assignment.coach_status}</span>
                        ` : '<span class="unassigned">Unassigned</span>'}
                    </div>
                </td>
                <td>${escapeHtml(assignment.specialization || 'N/A')}</td>
                <td>${formatDate(assignment.join_date)}</td>
                <td>
                    <span class="status-badge ${statusClass}">
                        <i class="fas fa-circle"></i> ${assignment.client_status}
                    </span>
                </td>
                <td class="actions-cell">
                    <button onclick="editAssignment(${assignment.client_id})" class="btn-icon" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="viewMemberDetails(${assignment.client_id})" class="btn-icon" title="View">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="removeAssignment(${assignment.client_id})" class="btn-icon danger" title="Remove">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
}

// Filter assignments
function filterAssignments() {
    const searchEl = document.getElementById('searchAssignments');
    const coachEl = document.getElementById('coachFilter');
    const statusEl = document.getElementById('statusFilter');
    const searchTerm = searchEl ? searchEl.value.toLowerCase() : '';
    const coachFilter = coachEl ? coachEl.value : 'all';
    const statusFilter = statusEl ? statusEl.value : 'all';
    
    let filtered = allAssignments.filter(assignment => {
        const matchesSearch = !searchTerm || 
            (assignment.first_name && assignment.first_name.toLowerCase().includes(searchTerm)) ||
            (assignment.last_name && assignment.last_name.toLowerCase().includes(searchTerm)) ||
            (assignment.email && assignment.email.toLowerCase().includes(searchTerm));
        
        const matchesCoach = coachFilter === 'all' || assignment.coach_id == coachFilter;
        const matchesStatus = statusFilter === 'all' || assignment.client_status === statusFilter;
        
        return matchesSearch && matchesCoach && matchesStatus;
    });
    
    displayAssignments(filtered);
}

// Load coach filters
function loadCoachFilters() {
    fetch('includes/coach_assignment_handler.php?action=get_coaches')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('coachFilter');
                const bulkSelect = document.getElementById('bulkCoachId');
                const assignSelect = document.getElementById('assignCoachId');
                const performanceSelect = document.getElementById('performanceCoachFilter');
                
                let optionsHtml = '';
                data.coaches.forEach(coach => {
                    optionsHtml += `<option value="${coach.coach_id}">${escapeHtml(coach.name)} (${coach.client_count} clients)</option>`;
                });
                
                if (select) select.innerHTML = '<option value="all">All Coaches</option>' + optionsHtml;
                if (bulkSelect) bulkSelect.innerHTML = '<option value="">Choose a coach... </option>' + optionsHtml;
                if (assignSelect) assignSelect.innerHTML = '<option value="">Choose a coach...</option>' + optionsHtml;
                if (performanceSelect) performanceSelect.innerHTML = '<option value="all">All Coaches</option>' + optionsHtml;
                
                allCoaches = data.coaches;
            }
        })
        .catch(error => console.error('Error loading coaches:', error));
}

// Load members for assignment (used by modal)
function loadMembers() {
    fetch('includes/coach_assignment_handler.php?action=get_unassigned_members')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('assignMemberId');
                if (!select) return;
                allMembers = data.members;
                
                let optionsHtml = '<option value="">Choose a member...</option>';
                data.members.forEach(member => {
                    optionsHtml += `<option value="${member.client_id}" data-email="${escapeHtml(member.email)}" data-phone="${escapeHtml(member.phone)}" data-name="${escapeHtml(member.name)}">${escapeHtml(member.name)}</option>`;
                });
                
                select.innerHTML = optionsHtml;
            }
        })
        .catch(error => console.error('Error loading members:', error));
}

// Load member info
function loadMemberInfo() {
    const selectElement = document.getElementById('assignMemberId');
    if (!selectElement) return;
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    
    if (selectElement.value) {
        const memberInfoCard = document.getElementById('memberInfoCard');
        if (memberInfoCard) memberInfoCard.style.display = 'block';
        const nameEl = document.getElementById('memberInfoName');
        const emailEl = document.getElementById('memberInfoEmail');
        const phoneEl = document.getElementById('memberInfoPhone');
        if (nameEl) nameEl.textContent = selectedOption.dataset.name || '-';
        if (emailEl) emailEl.textContent = selectedOption.dataset.email || '-';
        if (phoneEl) phoneEl.textContent = selectedOption.dataset.phone || '-';
        
        // Get current coach info
        fetch(`includes/coach_assignment_handler.php?action=get_member_info&client_id=${selectElement.value}`)
            .then(response => response.json())
            .then(data => {
                const coachEl = document.getElementById('memberInfoCoach');
                if (data.success && coachEl) {
                    coachEl.textContent = data.current_coach || 'None';
                }
            });
    } else {
        const memberInfoCard = document.getElementById('memberInfoCard');
        if (memberInfoCard) memberInfoCard.style.display = 'none';
    }
}

// Load coach info
function loadCoachInfo() {
    const selectElement = document.getElementById('assignCoachId');
    if (!selectElement) return;
    const coachId = selectElement.value;
    
    if (coachId) {
        const coachInfoCard = document.getElementById('coachInfoCard');
        if (coachInfoCard) coachInfoCard.style.display = 'block';
        
        fetch(`includes/coach_assignment_handler.php?action=get_coach_info&coach_id=${coachId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const nameEl = document.getElementById('coachInfoName');
                    const specEl = document.getElementById('coachInfoSpec');
                    const clientsEl = document.getElementById('coachInfoClients');
                    const expEl = document.getElementById('coachInfoExp');
                    if (nameEl) nameEl.textContent = escapeHtml(data.name);
                    if (specEl) specEl.textContent = escapeHtml(data.specialization || 'General Fitness');
                    if (clientsEl) clientsEl.textContent = data.client_count;
                    if (expEl) expEl.textContent = (data.experience_years || 0) + ' years';
                }
            });
    } else {
        const coachInfoCard = document.getElementById('coachInfoCard');
        if (coachInfoCard) coachInfoCard.style.display = 'none';
    }
}

// Load unassigned members
function loadUnassignedMembers() {
    const tableBody = document.getElementById('unassignedTableBody');
    if (!tableBody) {
        console.log('unassignedTableBody element not found');
        return;
    }
    tableBody.innerHTML = '<tr><td colspan="8" class="no-data"><i class="fas fa-spinner fa-spin"></i><p>Loading unassigned members... </p></td></tr>';
    
    fetch('includes/coach_assignment_handler.php?action=get_unassigned_members')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                unassignedMembers = data.members;
                displayUnassignedMembers(data.members);
            } else {
                tableBody.innerHTML = `<tr><td colspan="8" class="no-data"><p>${escapeHtml(data.message || 'No data')}</p></td></tr>`;
            }
        })
        .catch(error => {
            console.error('Error loading unassigned members:', error);
            tableBody.innerHTML = '<tr><td colspan="8" class="no-data"><i class="fas fa-exclamation-circle"></i><p>Failed to load members</p></td></tr>';
        });
}

// Display unassigned members
function displayUnassignedMembers(members) {
    const tableBody = document.getElementById('unassignedTableBody');
    if (!tableBody) return;
    
    if (members.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="8" class="no-data"><i class="fas fa-check-circle"></i><p>All members are assigned! </p></td></tr>';
        return;
    }
    
    let html = '';
    members.forEach(member => {
        html += `
            <tr>
                <td>
                    <input type="checkbox" class="member-checkbox" value="${member.client_id}" onchange="updateSelectedCount()">
                </td>
                <td>
                    <div class="member-cell">
                        <div class="member-avatar">${member.name.charAt(0)}</div>
                        <div class="member-info">
                            <strong>${escapeHtml(member.name)}</strong>
                            <span>${member.client_type}</span>
                        </div>
                    </div>
                </td>
                <td>${escapeHtml(member.email || 'N/A')}</td>
                <td>${escapeHtml(member.phone || 'N/A')}</td>
                <td>${formatDate(member.join_date)}</td>
                <td>${escapeHtml(member.membership || 'None')}</td>
                <td>
                    <span class="status-badge active">
                        <i class="fas fa-circle"></i> Active
                    </span>
                </td>
                <td class="actions-cell">
                    <button onclick="quickAssignMember(${member.client_id}, '${escapeHtml(member.name)}')" class="btn-small btn-primary">
                        <i class="fas fa-user-plus"></i> Assign
                    </button>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
}

// Filter unassigned members
function filterUnassigned() {
    const searchTermEl = document.getElementById('searchUnassigned');
    const searchTerm = searchTermEl ? searchTermEl.value.toLowerCase() : '';
    
    let filtered = unassignedMembers.filter(member => {
        return !searchTerm || 
            (member.name && member.name.toLowerCase().includes(searchTerm)) ||
            (member.email && member.email.toLowerCase().includes(searchTerm)) ||
            (member.phone && member.phone.includes(searchTerm));
    });
    
    displayUnassignedMembers(filtered);
}

// Toggle select all
function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.member-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    
    if (!selectAllCheckbox) return;
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateSelectedCount();
}

// Update selected count
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.member-checkbox:checked');
    selectedMembers = Array.from(checkboxes).map(cb => cb.value);
    const selectedCountEl = document.getElementById('selectedCount');
    if (selectedCountEl) selectedCountEl.textContent = selectedMembers.length;
}

// Load coach statistics
function loadCoachStatistics() {
    const container = document.getElementById('coachStatsContainer');
    if (!container) return;
    container.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading statistics...</p></div>';
    
    const coachFilterElement = document.getElementById('performanceCoachFilter');
    const coachFilter = coachFilterElement ? coachFilterElement.value : 'all';
    
    fetch(`includes/coach_assignment_handler.php?action=get_statistics&coach_id=${encodeURIComponent(coachFilter)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCoachStatistics(data.statistics);
            } else {
                container.innerHTML = `<p class="no-data">${escapeHtml(data.message || 'No stats')}</p>`;
            }
        })
        .catch(error => {
            console.error('Error loading statistics:', error);
            container.innerHTML = '<div class="error-state"><p>Failed to load statistics</p></div>';
        });
}

// Display coach statistics
function displayCoachStatistics(stats) {
    const container = document.getElementById('coachStatsContainer');
    if (!container) return;
    
    let html = '';
    stats.forEach(stat => {
        html += `
            <div class="stat-item">
                <div class="stat-header">
                    <h5>${escapeHtml(stat.coach_name)}</h5>
                    <span class="stat-badge">${stat.client_count} clients</span>
                </div>
                <div class="stat-details">
                    <div class="detail-row">
                        <span>Active Programs:</span>
                        <strong>${stat.active_programs}</strong>
                    </div>
                    <div class="detail-row">
                        <span>Avg. Client Duration:</span>
                        <strong>${stat.avg_duration || 0} months</strong>
                    </div>
                    <div class="detail-row">
                        <span>Assessment Rate:</span>
                        <strong>${stat.assessment_rate || 0}%</strong>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html || '<p class="no-data">No statistics available</p>';
}

// Load recent assessments
function loadRecentAssessments() {
    const tableBody = document.getElementById('assessmentsTableBody');
    if (!tableBody) return;
    tableBody.innerHTML = '<tr><td colspan="7" class="no-data"><i class="fas fa-spinner fa-spin"></i><p>Loading assessments...</p></td></tr>';
    
    fetch('includes/coach_assignment_handler.php?action=get_recent_assessments')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRecentAssessments(data.assessments);
            } else {
                tableBody.innerHTML = `<tr><td colspan="7" class="no-data"><p>${escapeHtml(data.message || 'No data')}</p></td></tr>`;
            }
        })
        .catch(error => {
            console.error('Error loading assessments:', error);
            tableBody.innerHTML = '<tr><td colspan="7" class="no-data"><i class="fas fa-exclamation-circle"></i><p>Failed to load assessments</p></td></tr>';
        });
}

// Display recent assessments
function displayRecentAssessments(assessments) {
    const tableBody = document.getElementById('assessmentsTableBody');
    if (!tableBody) return;
    
    if (assessments.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="7" class="no-data"><i class="fas fa-inbox"></i><p>No recent assessments</p></td></tr>';
        return;
    }
    
    let html = '';
    assessments.forEach(assessment => {
        html += `
            <tr>
                <td>${formatDate(assessment.assessment_date)}</td>
                <td>${escapeHtml(assessment.client_name)}</td>
                <td>${escapeHtml(assessment.coach_name || 'N/A')}</td>
                <td>${assessment.weight ? assessment.weight + ' kg' : 'N/A'}</td>
                <td>${assessment.body_fat_percentage ? assessment.body_fat_percentage + '%' : 'N/A'}</td>
                <td>${formatDate(assessment.next_assessment_date)}</td>
                <td class="actions-cell">
                    <button onclick="viewAssessmentDetails(${assessment.assessment_id})" class="btn-icon">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
}

// Open assignment modal
function openAssignModal(coachId = null) {
    console.log('openAssignModal called, coachId=', coachId);
    const modal = document.getElementById('assignmentModal');
    const form = document.getElementById('assignmentForm');
    if (!modal || !form) {
        console.log('Assignment modal or form not found');
        return;
    }
    
    form.reset();
    const memberInfoCard = document.getElementById('memberInfoCard');
    const coachInfoCard = document.getElementById('coachInfoCard');
    if (memberInfoCard) memberInfoCard.style.display = 'none';
    if (coachInfoCard) coachInfoCard.style.display = 'none';
    
    loadMembers();
    loadCoachFilters();
    
    if (coachId) {
        const assignCoachSelect = document.getElementById('assignCoachId');
        if (assignCoachSelect) {
            assignCoachSelect.value = coachId;
            loadCoachInfo();
        }
    }
    
    modal.style.display = 'block';
}

// Close assignment modal
function closeAssignmentModal() {
    const modal = document.getElementById('assignmentModal');
    if (modal) modal.style.display = 'none';
}

// Save assignment (modal)
function saveAssignment() {
    console.log('saveAssignment called');
    const clientIdEl = document.getElementById('assignMemberId');
    const coachIdEl = document.getElementById('assignCoachId');
    if (!clientIdEl || !coachIdEl) {
        showAlert('Assign form controls not found', 'error');
        return;
    }
    const clientId = clientIdEl.value;
    const coachId = coachIdEl.value;
    const notes = (document.getElementById('assignmentNotes') || {}).value || '';
    
    if (!clientId || !coachId) {
        showAlert('Please select both a member and a coach', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'assign_coach');
    formData.append('client_id', clientId);
    formData.append('coach_id', coachId);
    formData.append('notes', notes);
    
    fetch('includes/coach_assignment_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Send notifications to coach and member
            sendAssignmentNotifications(clientId, coachId, notes);
            showAlert('Assignment saved successfully and notifications sent', 'success');
            closeAssignmentModal();
            loadAssignments();
            loadUnassignedMembers();
            loadQuickAssignData();
            loadCoachFilters();
        } else {
            console.error('Save assignment failed:', data);
            showAlert(data.message || 'Error saving assignment', 'error');
        }
    })
    .catch(error => {
        console.error('Error saving assignment:', error);
        showAlert('Error saving assignment', 'error');
    });
}

// Send assignment notifications
function sendAssignmentNotifications(clientId, coachId, notes = '') {
    console.log('sendAssignmentNotifications called with:', { clientId, coachId, notes });
    
    const formData = new FormData();
    formData.append('action', 'notify_assignment');
    formData.append('client_id', clientId);
    formData.append('coach_id', coachId);
    formData.append('notes', notes);
    
    console.log('Sending notifications fetch request to: includes/coach_assignment_notifications.php');
    
    fetch('includes/coach_assignment_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Notification response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Notification response data:', data);
        if (data.success) {
            console.log('✓ Notifications sent successfully', data.notifications);
        } else {
            console.error('✗ Error sending notifications:', data.message);
        }
    })
    .catch(error => {
        console.error('✗ Error sending notifications (fetch error):', error);
    });
}

// Edit assignment
function editAssignment(clientId) {
    fetch(`includes/coach_assignment_handler.php?action=get_assignment&client_id=${clientId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const assignMemberSelect = document.getElementById('assignMemberId');
                const assignCoachSelect = document.getElementById('assignCoachId');
                if (assignMemberSelect) assignMemberSelect.value = clientId;
                if (assignCoachSelect) assignCoachSelect.value = data.coach_id || '';
                const notesEl = document.getElementById('assignmentNotes');
                if (notesEl) notesEl.value = data.notes || '';
                
                loadMemberInfo();
                loadCoachInfo();
                openAssignModal();
            } else {
                showAlert(data.message || 'Could not load assignment', 'error');
            }
        })
        .catch(error => console.error('Error:', error));
}

// Remove assignment
function removeAssignment(clientId) {
    if (!confirm('Are you sure you want to remove this assignment?')) {
        return;
    }
    console.log('removeAssignment called for', clientId);
    
    const formData = new FormData();
    formData.append('action', 'remove_assignment');
    formData.append('client_id', clientId);
    
    fetch('includes/coach_assignment_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Assignment removed successfully', 'success');
            loadAssignments();
            loadUnassignedMembers();
            loadQuickAssignData();
            loadCoachFilters();
        } else {
            console.error('Remove assignment failed:', data);
            showAlert(data.message || 'Error removing assignment', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error removing assignment', 'error');
    });
}

// Quick assign member (from Unassigned table -> prefill quick assign panel)
function quickAssignMember(clientId, memberName) {
    console.log('quickAssignMember called', clientId, memberName);
    const memberSelect = document.getElementById('quickAssignMemberId');
    if (!memberSelect) return;
    memberSelect.value = clientId;
    // open overview tab to show quick assign panel
    switchTab('overview');
}

// Bulk assign selected
function bulkAssignSelected() {
    if (selectedMembers.length === 0) {
        showAlert('Please select at least one member', 'warning');
        return;
    }
    
    const modal = document.getElementById('bulkAssignModal');
    if (!modal) return;
    document.getElementById('selectedCount').textContent = selectedMembers.length;
    modal.style.display = 'block';
}

// Close bulk assign modal
function closeBulkAssignModal() {
    const modal = document.getElementById('bulkAssignModal');
    if (modal) modal.style.display = 'none';
}

// Confirm bulk assign
function confirmBulkAssign() {
    const coachIdEl = document.getElementById('bulkCoachId');
    if (!coachIdEl) {
        showAlert('Bulk assign coach select not found', 'error');
        return;
    }
    const coachId = coachIdEl.value;
    const notes = (document.getElementById('bulkNotes') || {}).value || '';
    
    if (!coachId) {
        showAlert('Please select a coach', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'bulk_assign');
    formData.append('coach_id', coachId);
    formData.append('client_ids', JSON.stringify(selectedMembers));
    formData.append('notes', notes);
    
    fetch('includes/coach_assignment_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Send notifications for each assignment
            selectedMembers.forEach(clientId => {
                sendAssignmentNotifications(clientId, coachId, notes);
            });
            showAlert(`${selectedMembers.length} members assigned successfully and notifications sent`, 'success');
            closeBulkAssignModal();
            selectedMembers = [];
            const selectAll = document.getElementById('selectAll');
            if (selectAll) selectAll.checked = false;
            loadUnassignedMembers();
            loadAssignments();
            loadQuickAssignData();
            loadCoachFilters();
        } else {
            console.error('Bulk assign failed:', data);
            showAlert(data.message || 'Error assigning members', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error assigning members', 'error');
    });
}

// View member details
function viewMemberDetails(clientId) {
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('memberDetailsContent');
    if (!content || !modal) return;
    
    content.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading details...</p></div>';
    modal.style.display = 'block';
    
    fetch(`includes/coach_assignment_handler.php?action=get_member_details&client_id=${clientId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMemberDetails(data.member);
            } else {
                content.innerHTML = '<div class="error-state"><p>Error loading details</p></div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<div class="error-state"><p>Error loading details</p></div>';
        });
}

// Display member details
function displayMemberDetails(member) {
    const content = document.getElementById('memberDetailsContent');
    if (!content) return;
    
    let html = `
        <div class="details-grid">
            <div class="detail-section">
                <h4><i class="fas fa-user"></i> Personal Information</h4>
                <div class="detail-row">
                    <span class="label">Name:</span>
                    <span class="value">${escapeHtml(member.name)}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Email:</span>
                    <span class="value">${escapeHtml(member.email)}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Phone:</span>
                    <span class="value">${escapeHtml(member.phone)}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Join Date:</span>
                    <span class="value">${formatDate(member.join_date)}</span>
                </div>
            </div>
            
            <div class="detail-section">
                <h4><i class="fas fa-user-tie"></i> Coach Information</h4>
                <div class="detail-row">
                    <span class="label">Assigned Coach:</span>
                    <span class="value">${member.coach_name || 'None'}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Specialization:</span>
                    <span class="value">${escapeHtml(member.coach_specialization || 'N/A')}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Status:</span>
                    <span class="value"><span class="status-badge ${member.status.toLowerCase()}">${member.status}</span></span>
                </div>
            </div>
            
            <div class="detail-section">
                <h4><i class="fas fa-dumbbell"></i> Fitness Information</h4>
                <div class="detail-row">
                    <span class="label">Membership Type:</span>
                    <span class="value">${escapeHtml(member.membership_type || 'None')}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Last Assessment:</span>
                    <span class="value">${formatDate(member.last_assessment) || 'None'}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Next Assessment:</span>
                    <span class="value">${formatDate(member.next_assessment) || 'None'}</span>
                </div>
            </div>
        </div>
    `;
    
    content.innerHTML = html;
}

// Close details modal
function closeDetailsModal() {
    const modal = document.getElementById('detailsModal');
    if (modal) modal.style.display = 'none';
}

// View coach details
function viewCoachDetails(coachId) {
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('memberDetailsContent');
    if (!content || !modal) return;
    
    content.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading details...</p></div>';
    modal.style.display = 'block';
    
    fetch(`includes/coach_assignment_handler.php?action=get_coach_details&coach_id=${coachId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCoachDetailsModal(data.coach, data.clients);
            } else {
                content.innerHTML = '<div class="error-state"><p>Error loading details</p></div>';
            }
        })
        .catch(error => console.error('Error:', error));
}

// Display coach details
function displayCoachDetailsModal(coach, clients) {
    const content = document.getElementById('memberDetailsContent');
    if (!content) return;
    
    let clientsHtml = '';
    if (clients && clients.length > 0) {
        clients.forEach(client => {
            clientsHtml += `
                <div class="client-item">
                    <span>${escapeHtml(client.name)}</span>
                    <span class="status-badge ${client.status.toLowerCase()}">${client.status}</span>
                </div>
            `;
        });
    } else {
        clientsHtml = '<p class="no-data">No assigned clients</p>';
    }
    
    let html = `
        <div class="details-grid">
            <div class="detail-section">
                <h4><i class="fas fa-user-tie"></i> Coach Information</h4>
                <div class="detail-row">
                    <span class="label">Name:</span>
                    <span class="value">${escapeHtml(coach.name)}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Email:</span>
                    <span class="value">${escapeHtml(coach.email)}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Phone:</span>
                    <span class="value">${escapeHtml(coach.phone)}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Status:</span>
                    <span class="value"><span class="status-badge ${coach.status.toLowerCase()}">${coach.status}</span></span>
                </div>
            </div>
            
            <div class="detail-section">
                <h4><i class="fas fa-certificate"></i> Qualifications</h4>
                <div class="detail-row">
                    <span class="label">Specialization:</span>
                    <span class="value">${escapeHtml(coach.specialization)}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Certification:</span>
                    <span class="value">${escapeHtml(coach.certification)}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Experience:</span>
                    <span class="value">${coach.experience_years} years</span>
                </div>
                <div class="detail-row">
                    <span class="label">Hourly Rate:</span>
                    <span class="value">₱${parseFloat(coach.hourly_rate || 0).toFixed(2)}</span>
                </div>
            </div>
            
            <div class="detail-section full-width">
                <h4><i class="fas fa-users"></i> Assigned Clients (${clients ? clients.length : 0})</h4>
                <div class="clients-list">
                    ${clientsHtml}
                </div>
            </div>
        </div>
    `;
    
    content.innerHTML = html;
}

// View assessment details
function viewAssessmentDetails(assessmentId) {
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('memberDetailsContent');
    if (!content || !modal) return;
    
    content.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading details...</p></div>';
    modal.style.display = 'block';
    
    fetch(`includes/coach_assignment_handler.php?action=get_assessment_details&assessment_id=${assessmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAssessmentDetailsModal(data.assessment);
            } else {
                content.innerHTML = '<div class="error-state"><p>Error loading details</p></div>';
            }
        })
        .catch(error => console.error('Error:', error));
}

// Display assessment details
function displayAssessmentDetailsModal(assessment) {
    const content = document.getElementById('memberDetailsContent');
    if (!content) return;
    
    let html = `
        <div class="details-grid">
            <div class="detail-section">
                <h4><i class="fas fa-user"></i> Member Information</h4>
                <div class="detail-row">
                    <span class="label">Name:</span>
                    <span class="value">${escapeHtml(assessment.client_name)}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Coach:</span>
                    <span class="value">${escapeHtml(assessment.coach_name || 'N/A')}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Assessment Date:</span>
                    <span class="value">${formatDate(assessment.assessment_date)}</span>
                </div>
            </div>
            
            <div class="detail-section">
                <h4><i class="fas fa-heart"></i> Physical Measurements</h4>
                <div class="detail-row">
                    <span class="label">Weight:</span>
                    <span class="value">${assessment.weight ? assessment.weight + ' kg' : 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Height:</span>
                    <span class="value">${assessment.height ? assessment.height + ' cm' : 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Body Fat %:</span>
                    <span class="value">${assessment.body_fat_percentage ? assessment.body_fat_percentage + '%' : 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Muscle Mass:</span>
                    <span class="value">${assessment.muscle_mass ? assessment.muscle_mass + ' kg' : 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Blood Pressure:</span>
                    <span class="value">${escapeHtml(assessment.blood_pressure || 'N/A')}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Resting Heart Rate:</span>
                    <span class="value">${assessment.resting_heart_rate ? assessment.resting_heart_rate + ' bpm' : 'N/A'}</span>
                </div>
            </div>
            
            <div class="detail-section full-width">
                <h4><i class="fas fa-clipboard"></i> Goals & Conditions</h4>
                <div class="detail-row">
                    <span class="label">Fitness Goals:</span>
                    <span class="value">${escapeHtml(assessment.fitness_goals || 'N/A')}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Medical Conditions:</span>
                    <span class="value">${escapeHtml(assessment.medical_conditions || 'None reported')}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Notes:</span>
                    <span class="value">${escapeHtml(assessment.notes || 'N/A')}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Next Assessment:</span>
                    <span class="value">${formatDate(assessment.next_assessment_date)}</span>
                </div>
            </div>
        </div>
    `;
    
    content.innerHTML = html;
}

// Helper function: Format date
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

// Helper function: Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Helper function: Debounce
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

// Helper function: Show alert
function showAlert(message, type = 'info') {
    const alertBox = document.getElementById('alertBox');
    if (!alertBox) {
        // fallback to console
        console.log('ALERT:', type, message);
        return;
    }
    alertBox.className = `alert alert-${type}`;
    alertBox.textContent = message;
    alertBox.style.display = 'block';
    
    setTimeout(() => {
        alertBox.style.display = 'none';
    }, 4000);
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const assignmentModal = document.getElementById('assignmentModal');
    const detailsModal = document.getElementById('detailsModal');
    const bulkAssignModal = document.getElementById('bulkAssignModal');
    const removeAssignmentModal = document.getElementById('removeAssignmentModal');
    
    if (event.target === assignmentModal && assignmentModal) {
        assignmentModal.style.display = 'none';
    }
    if (event.target === detailsModal && detailsModal) {
        detailsModal.style.display = 'none';
    }
    if (event.target === bulkAssignModal && bulkAssignModal) {
        bulkAssignModal.style.display = 'none';
    }
    if (event.target === removeAssignmentModal && removeAssignmentModal) {
        removeAssignmentModal.style.display = 'none';
    }
});

// ===== NEW: ASSIGNMENT VIEW SWITCHING =====
function switchAssignmentView(viewType) {
    const tableContainer = document.getElementById('tableViewContainer');
    const cardsContainer = document.getElementById('cardsViewContainer');
    const toggleButtons = document.querySelectorAll('.view-toggle .toggle-btn');
    
    toggleButtons.forEach(btn => btn.classList.remove('active'));
    
    if (viewType === 'table') {
        tableContainer.style.display = 'block';
        cardsContainer.style.display = 'none';
        toggleButtons[0].classList.add('active');
        loadAssignments();
    } else if (viewType === 'cards') {
        tableContainer.style.display = 'none';
        cardsContainer.style.display = 'block';
        toggleButtons[1].classList.add('active');
        loadCoachesCardsView();
    }
}

// ===== NEW: LOAD COACHES CARDS VIEW =====
function loadCoachesCardsView() {
    fetch('includes/coach_assignment_handler.php?action=get_coach_overview')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCoachCards(data.coaches);
            } else {
                showAlert('Error loading coaches: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error loading coaches view', 'error');
        });
}

// ===== NEW: DISPLAY COACH CARDS WITH CLIENTS =====
function displayCoachCards(coaches) {
    const container = document.getElementById('coachesCardsContent');
    
    if (!coaches || coaches.length === 0) {
        container.innerHTML = '<div class="no-data"><i class="fas fa-info-circle"></i><p>No coaches available</p></div>';
        return;
    }
    
    let html = '';
    coaches.forEach(coach => {
        html += `
            <div class="coach-card">
                <div class="coach-card-header">
                    <div class="coach-avatar">${coach.name.charAt(0).toUpperCase()}</div>
                    <div class="coach-header-info">
                        <h3>${escapeHtml(coach.name)}</h3>
                        <p>${escapeHtml(coach.specialization || 'Coach')}</p>
                    </div>
                </div>
                
                <div class="coach-card-body">
                    <div class="coach-info-row">
                        <span class="coach-info-label"><i class="fas fa-users"></i> Assigned Clients</span>
                        <span class="coach-info-value highlight">${coach.client_count || 0}</span>
                    </div>
                    <div class="coach-info-row">
                        <span class="coach-info-label"><i class="fas fa-graduation-cap"></i> Experience</span>
                        <span class="coach-info-value">${coach.experience_years || 0} years</span>
                    </div>
                    <div class="coach-info-row">
                        <span class="coach-info-label"><i class="fas fa-certificate"></i> Certification</span>
                        <span class="coach-info-value">${escapeHtml(coach.certification || 'Not specified')}</span>
                    </div>
                    <div class="coach-info-row">
                        <span class="coach-info-label"><i class="fas fa-dollar-sign"></i> Hourly Rate</span>
                        <span class="coach-info-value">₱${parseFloat(coach.hourly_rate || 0).toFixed(2)}</span>
                    </div>
                    
                    <div class="clients-list" id="clients-list-${coach.coach_id}">
                        <h4><i class="fas fa-user-friends"></i> Assigned Members (${coach.client_count || 0})</h4>
                        <div class="clients-list-items" id="clients-items-${coach.coach_id}">
                            <div class="loading-state" style="padding: 8px;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="coach-card-footer">
                    <button class="btn-add-client" onclick="openAssignModal(${coach.coach_id})">
                        <i class="fas fa-user-plus"></i> Add Client
                    </button>
                    <button class="btn-view-clients" onclick="loadCoachClients(${coach.coach_id})">
                        <i class="fas fa-eye"></i> View All
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Load clients for each coach
    coaches.forEach(coach => {
        loadCoachClientsForCard(coach.coach_id);
    });
}

// ===== NEW: LOAD COACH CLIENTS FOR CARD =====
function loadCoachClientsForCard(coachId) {
    fetch(`includes/coach_assignment_handler.php?action=get_coach_clients&coach_id=${coachId}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById(`clients-items-${coachId}`);
            if (!container) return;
            
            if (data.success && data.clients && data.clients.length > 0) {
                let html = '';
                data.clients.slice(0, 5).forEach(client => {
                    html += `
                        <div class="client-item">
                            <span class="client-item-name">${escapeHtml(client.first_name)} ${escapeHtml(client.last_name)}</span>
                            <i class="fas fa-times client-item-remove" onclick="initiateRemoveAssignment(${client.client_id}, ${coachId})" title="Remove assignment"></i>
                        </div>
                    `;
                });
                
                if (data.clients.length > 5) {
                    html += `<div class="client-item" style="justify-content: center; background: transparent;"><small>+${data.clients.length - 5} more clients</small></div>`;
                }
                
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="no-clients">No clients assigned</div>';
            }
        })
        .catch(error => {
            console.error('Error loading coach clients:', error);
            const container = document.getElementById(`clients-items-${coachId}`);
            if (container) {
                container.innerHTML = '<div class="no-clients">Error loading clients</div>';
            }
        });
}

// ===== NEW: LOAD ALL CLIENTS OF A COACH =====
function loadCoachClients(coachId) {
    fetch(`includes/coach_assignment_handler.php?action=get_coach_clients&coach_id=${coachId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const coach = data.coach || {};
                let clientsHtml = '';
                
                if (data.clients && data.clients.length > 0) {
                    clientsHtml = '<table style="width: 100%;"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Actions</th></tr></thead><tbody>';
                    data.clients.forEach(client => {
                        clientsHtml += `
                            <tr>
                                <td>${escapeHtml(client.first_name)} ${escapeHtml(client.last_name)}</td>
                                <td>${escapeHtml(client.email || 'N/A')}</td>
                                <td>${escapeHtml(client.phone || 'N/A')}</td>
                                <td>
                                    <button class="action-btn action-btn-remove" onclick="initiateRemoveAssignment(${client.client_id}, ${coachId})">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    clientsHtml += '</tbody></table>';
                } else {
                    clientsHtml = '<p style="text-align: center; color: #999;">No clients assigned to this coach</p>';
                }
                
                const modal = document.getElementById('detailsModal');
                const content = document.getElementById('memberDetailsContent');
                content.innerHTML = `
                    <h3>Coach: ${escapeHtml(coach.name)}</h3>
                    <p>Total Clients: <strong>${data.clients ? data.clients.length : 0}</strong></p>
                    ${clientsHtml}
                `;
                modal.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error loading coach clients', 'error');
        });
}

// ===== NEW: INITIATE REMOVAL WITH NOTIFICATION =====
let removalData = { clientId: null, coachId: null };

function initiateRemoveAssignment(clientId, coachId) {
    removalData = { clientId, coachId };
    
    // Fetch client and coach info
    Promise.all([
        fetch(`includes/coach_assignment_handler.php?action=get_member_details&client_id=${clientId}`).then(r => r.json()),
        fetch(`includes/coach_assignment_handler.php?action=get_coach_details&coach_id=${coachId}`).then(r => r.json())
    ])
    .then(([clientData, coachData]) => {
        if (clientData.success && coachData.success) {
            const client = clientData.member;
            const coach = coachData.coach;
            
            document.getElementById('removeClientName').textContent = `${client.first_name} ${client.last_name}`;
            document.getElementById('removeClientEmail').textContent = client.email || 'N/A';
            document.getElementById('removeCoachName').textContent = `${coach.first_name} ${coach.last_name}`;
            
            document.getElementById('removeAssignmentModal').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error loading details', 'error');
    });
}

function closeRemoveModal() {
    document.getElementById('removeAssignmentModal').style.display = 'none';
    removalData = { clientId: null, coachId: null };
}

// ===== NEW: CONFIRM AND PROCESS REMOVAL WITH NOTIFICATION =====
function confirmRemoveAssignment() {
    const reason = document.getElementById('removalReason').value;
    const notes = document.getElementById('removalNotes').value;
    const notifyBoth = document.getElementById('notifyBoth').checked;
    const includeReason = document.getElementById('includeReason').checked;
    
    if (!reason) {
        showAlert('Please select a reason for removal', 'warning');
        return;
    }
    
    if (!removalData.clientId || !removalData.coachId) {
        showAlert('Invalid client or coach ID', 'error');
        return;
    }
    
    // Step 1: Remove assignment
    const formData = new FormData();
    formData.append('action', 'remove_assignment');
    formData.append('client_id', removalData.clientId);
    formData.append('coach_id', removalData.coachId);
    formData.append('reason', reason);
    formData.append('notes', notes);
    
    fetch('includes/coach_assignment_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Step 2: Send notification if enabled
            if (notifyBoth) {
                sendRemovalNotification(
                    removalData.clientId,
                    removalData.coachId,
                    reason,
                    notes,
                    includeReason
                );
            }
            
            closeRemoveModal();
            showAlert('Assignment removed successfully', 'success');
            
            // Refresh views
            loadAssignments();
            loadCoachesCardsView();
            loadUnassignedMembers();
        } else {
            showAlert('Error removing assignment: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error removing assignment', 'error');
    });
}

// ===== NEW: SEND REMOVAL NOTIFICATION =====
function sendRemovalNotification(clientId, coachId, reason, notes, includeReason) {
    const formData = new FormData();
    formData.append('action', 'notify_removal');
    formData.append('client_id', clientId);
    formData.append('coach_id', coachId);
    formData.append('reason', reason);
    formData.append('notes', notes);
    formData.append('include_reason', includeReason ? '1' : '0');
    
    fetch('includes/coach_assignment_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✓ Removal notifications sent successfully', data);
        } else {
            console.error('✗ Error sending removal notifications:', data.message);
        }
    })
    .catch(error => {
        console.error('✗ Error sending removal notifications:', error);
    });
}