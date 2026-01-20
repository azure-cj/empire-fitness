// Membership Plans JavaScript

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initializeSearch();
    initializeFormValidation();
});

// Search Functionality
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
}

// Filter Functions
function applyFilters() {
    const statusFilter = document.getElementById('statusFilter').value;
    const typeFilter = document.getElementById('typeFilter').value;
    
    const url = new URL(window.location.href);
    url.searchParams.set('status', statusFilter);
    url.searchParams.set('type', typeFilter);
    
    window.location.href = url.toString();
}

function resetFilters() {
    window.location.href = 'membership_plans.php';
}

// Modal Functions
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Membership Plan';
    document.getElementById('formAction').value = 'add';
    document.getElementById('planForm').reset();
    document.getElementById('membershipId').value = '';
    document.getElementById('planModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('planModal').style.display = 'none';
    document.getElementById('planForm').reset();
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const planModal = document.getElementById('planModal');
    const viewModal = document.getElementById('viewModal');
    const confirmModal = document.getElementById('confirmModal');
    
    if (event.target == planModal) {
        closeModal();
    }
    if (event.target == viewModal) {
        closeViewModal();
    }
    if (event.target == confirmModal) {
        closeConfirmModal();
    }
}

// View Plan Details
function viewPlan(plan) {
    const benefits = plan.benefits ? plan.benefits.split('\n').filter(b => b.trim()) : [];
    
    const content = `
        <div class="plan-details">
            <div class="detail-row">
                <div class="detail-label">Plan ID:</div>
                <div class="detail-value">${plan.membership_id}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Plan Name:</div>
                <div class="detail-value"><strong>${escapeHtml(plan.plan_name)}</strong></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Monthly Fee:</div>
                <div class="detail-value"><strong style="color: #d41c1c; font-size: 18px;">₱${parseFloat(plan.monthly_fee).toFixed(2)}</strong></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Renewal Fee:</div>
                <div class="detail-value">${plan.renewal_fee ? '₱' + parseFloat(plan.renewal_fee).toFixed(2) : 'Not Set'}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Renewal Discount:</div>
                <div class="detail-value">${plan.renewal_discount_percent > 0 ? parseFloat(plan.renewal_discount_percent).toFixed(0) + '% OFF' : 'No Discount'}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Duration:</div>
                <div class="detail-value">${plan.duration_days} days (${(plan.duration_days / 30).toFixed(1)} months)</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Plan Type:</div>
                <div class="detail-value">
                    <span class="badge ${plan.is_base_membership == 1 ? 'badge-info' : 'badge-secondary'}">
                        ${plan.is_base_membership == 1 ? 'Base Membership' : 'Add-on Plan'}
                    </span>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Status:</div>
                <div class="detail-value">
                    <span class="status-badge status-${plan.status.toLowerCase()}">
                        ${plan.status}
                    </span>
                </div>
            </div>
            ${plan.description ? `
            <div class="detail-row">
                <div class="detail-label">Description:</div>
                <div class="detail-value">${escapeHtml(plan.description)}</div>
            </div>
            ` : ''}
            ${benefits.length > 0 ? `
            <div class="detail-row">
                <div class="detail-label">Benefits:</div>
                <div class="detail-value">
                    <ul class="benefits-list">
                        ${benefits.map(b => `<li>${escapeHtml(b)}</li>`).join('')}
                    </ul>
                </div>
            </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('viewContent').innerHTML = content;
    document.getElementById('viewModal').style.display = 'block';
}

// Edit Plan
function editPlan(plan) {
    document.getElementById('modalTitle').textContent = 'Edit Membership Plan';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('membershipId').value = plan.membership_id;
    document.getElementById('plan_name').value = plan.plan_name;
    document.getElementById('monthly_fee').value = plan.monthly_fee;
    document.getElementById('renewal_fee').value = plan.renewal_fee || '';
    document.getElementById('renewal_discount_percent').value = plan.renewal_discount_percent || 0;
    document.getElementById('duration_days').value = plan.duration_days;
    document.getElementById('status').value = plan.status;
    document.getElementById('is_base_membership').value = plan.is_base_membership;
    document.getElementById('description').value = plan.description || '';
    document.getElementById('benefits').value = plan.benefits || '';
    
    document.getElementById('planModal').style.display = 'block';
}

// Toggle Plan Status
function toggleStatus(planId, currentStatus) {
    const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
    const action = currentStatus === 'Active' ? 'deactivate' : 'activate';
    
    showConfirmationModal({
        type: 'warning',
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} Plan`,
        message: `Are you sure you want to ${action} this membership plan?`,
        description: newStatus === 'Inactive' 
            ? 'This plan will no longer be available for new subscriptions.' 
            : 'This plan will become available for new subscriptions.',
        confirmText: action.charAt(0).toUpperCase() + action.slice(1),
        confirmClass: '',
        onConfirm: () => {
            executeToggleStatus(planId, newStatus);
        }
    });
}

function executeToggleStatus(planId, newStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('membership_id', planId);
    formData.append('status', newStatus);
    
    fetch('includes/membership_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Plan status updated successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message || 'Failed to update status', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred', 'error');
    });
}

// Delete Plan
function deletePlan(planId, planName) {
    showConfirmationModal({
        type: 'danger',
        title: 'Delete Membership Plan',
        message: `Are you sure you want to delete this plan?`,
        planName: planName,
        description: 'This action cannot be undone. All plan data will be permanently removed.',
        confirmText: 'Delete Plan',
        confirmClass: 'danger',
        onConfirm: () => {
            executeDeletePlan(planId);
        }
    });
}

function executeDeletePlan(planId) {
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('membership_id', planId);
    
    fetch('includes/membership_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Plan deleted successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message || 'Failed to delete plan', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred', 'error');
    });
}

// Show Confirmation Modal
function showConfirmationModal(options) {
    const modal = document.getElementById('confirmModal');
    const content = document.getElementById('confirmContent');
    
    const iconClass = options.type === 'danger' ? 'danger' : 'warning';
    const icon = options.type === 'danger' ? 'fas fa-exclamation-triangle' : 'fas fa-exclamation-circle';
    const textClass = options.type === 'danger' ? 'danger-text' : 'warning-text';
    
    const planNameHtml = options.planName 
        ? `<p class="plan-highlight">"${escapeHtml(options.planName)}"</p>` 
        : '';
    
    content.innerHTML = `
        <div class="confirmation-icon ${iconClass}">
            <i class="${icon}"></i>
        </div>
        <div class="confirmation-content">
            <h3>${options.title}</h3>
            <p>${options.message}</p>
            ${planNameHtml}
            <p class="${textClass}">${options.description}</p>
        </div>
        <div class="confirmation-actions">
            <button class="btn-cancel" onclick="closeConfirmModal()">Cancel</button>
            <button class="btn-confirm ${options.confirmClass}" onclick="confirmAction()">
                ${options.confirmText}
            </button>
        </div>
    `;
    
    // Store the callback
    window.confirmCallback = options.onConfirm;
    
    modal.style.display = 'block';
}

function confirmAction() {
    if (window.confirmCallback) {
        window.confirmCallback();
        window.confirmCallback = null;
    }
    closeConfirmModal();
}

// Form Submission
function initializeFormValidation() {
    const form = document.getElementById('planForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const action = document.getElementById('formAction').value;
            
            // Validation
            const planName = document.getElementById('plan_name').value.trim();
            const monthlyFee = parseFloat(document.getElementById('monthly_fee').value);
            const durationDays = parseInt(document.getElementById('duration_days').value);
            
            if (!planName) {
                showAlert('Please enter a plan name', 'error');
                return;
            }
            
            if (monthlyFee <= 0) {
                showAlert('Monthly fee must be greater than 0', 'error');
                return;
            }
            
            if (durationDays <= 0) {
                showAlert('Duration must be greater than 0 days', 'error');
                return;
            }
            
            // Submit form
            fetch('includes/membership_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message || 'Plan saved successfully!', 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.message || 'Failed to save plan', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while saving', 'error');
            });
        });
    }
}

// Alert Function
function showAlert(message, type = 'info') {
    const alertBox = document.getElementById('alertBox');
    if (!alertBox) return;
    
    alertBox.className = `alert ${type}`;
    alertBox.textContent = message;
    alertBox.style.display = 'flex';
    
    setTimeout(() => {
        alertBox.style.display = 'none';
    }, 5000);
}

// Utility Functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Calculate renewal fee based on discount
document.getElementById('renewal_discount_percent')?.addEventListener('input', function() {
    const monthlyFee = parseFloat(document.getElementById('monthly_fee').value) || 0;
    const discount = parseFloat(this.value) || 0;
    
    if (monthlyFee > 0 && discount > 0) {
        const renewalFee = monthlyFee * (1 - discount / 100);
        document.getElementById('renewal_fee').value = renewalFee.toFixed(2);
    }
});

// Auto-calculate discount when renewal fee changes
document.getElementById('renewal_fee')?.addEventListener('input', function() {
    const monthlyFee = parseFloat(document.getElementById('monthly_fee').value) || 0;
    const renewalFee = parseFloat(this.value) || 0;
    
    if (monthlyFee > 0 && renewalFee > 0 && renewalFee < monthlyFee) {
        const discount = ((monthlyFee - renewalFee) / monthlyFee) * 100;
        document.getElementById('renewal_discount_percent').value = discount.toFixed(2);
    }
});