// Rates & Fees JavaScript

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
    const appliesFilter = document.getElementById('appliesFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    const discountFilter = document.getElementById('discountFilter').value;
    
    const url = new URL(window.location.href);
    url.searchParams.set('applies_to', appliesFilter);
    url.searchParams.set('status', statusFilter);
    url.searchParams.set('discount', discountFilter);
    
    window.location.href = url.toString();
}

function resetFilters() {
    window.location.href = 'rates.php';
}

// Toggle Discount Fields
function toggleDiscountFields() {
    const isDiscounted = document.getElementById('is_discounted').value;
    const discountFields = document.getElementById('discountFields');
    
    if (isDiscounted === '1') {
        discountFields.style.display = 'block';
    } else {
        discountFields.style.display = 'none';
        // Clear discount fields
        document.getElementById('base_rate_id').value = '';
        document.getElementById('discount_type').value = '';
    }
}

// Modal Functions
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Rate';
    document.getElementById('formAction').value = 'add';
    document.getElementById('rateForm').reset();
    document.getElementById('rateId').value = '';
    toggleDiscountFields();
    document.getElementById('rateModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('rateModal').style.display = 'none';
    document.getElementById('rateForm').reset();
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
    const rateModal = document.getElementById('rateModal');
    const viewModal = document.getElementById('viewModal');
    const confirmModal = document.getElementById('confirmModal');
    
    if (event.target == rateModal) {
        closeModal();
    }
    if (event.target == viewModal) {
        closeViewModal();
    }
    if (event.target == confirmModal) {
        closeConfirmModal();
    }
}

// View Rate Details
function viewRate(rate) {
    let discountInfo = '';
    
    if (rate.is_discounted == 1) {
        const savings = rate.base_price ? parseFloat(rate.base_price) - parseFloat(rate.price) : 0;
        const savingsPercent = rate.base_price ? ((savings / parseFloat(rate.base_price)) * 100).toFixed(1) : 0;
        
        discountInfo = `
            <div class="detail-row">
                <div class="detail-label">Base Rate:</div>
                <div class="detail-value">${rate.base_rate_name || 'N/A'} - ₱${rate.base_price ? parseFloat(rate.base_price).toFixed(2) : '0.00'}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Discount Type:</div>
                <div class="detail-value">${escapeHtml(rate.discount_type) || 'N/A'}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Savings:</div>
                <div class="detail-value">
                    <strong style="color: #28a745;">₱${savings.toFixed(2)} (${savingsPercent}% off)</strong>
                </div>
            </div>
        `;
    }
    
    const content = `
        <div class="rate-details">
            <div class="detail-row">
                <div class="detail-label">Rate ID:</div>
                <div class="detail-value">${rate.rate_id}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Rate Name:</div>
                <div class="detail-value"><strong>${escapeHtml(rate.rate_name)}</strong></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Price:</div>
                <div class="detail-value">
                    <strong style="color: #d41c1c; font-size: 20px;">₱${parseFloat(rate.price).toFixed(2)}</strong>
                    ${rate.is_discounted == 1 && rate.base_price ? `
                        <br><small style="color: #999;"><s>₱${parseFloat(rate.base_price).toFixed(2)}</s></small>
                    ` : ''}
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Applies To:</div>
                <div class="detail-value">
                    <span class="badge badge-${rate.applies_to.toLowerCase()}">
                        <i class="fas fa-${rate.applies_to === 'Guest' ? 'user' : 'user-check'}"></i>
                        ${rate.applies_to}
                    </span>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Rate Type:</div>
                <div class="detail-value">
                    <span class="type-badge ${rate.is_discounted == 1 ? 'discounted' : 'regular'}">
                        ${rate.is_discounted == 1 ? '<i class="fas fa-tag"></i> Discounted' : '<i class="fas fa-dollar-sign"></i> Regular'}
                    </span>
                </div>
            </div>
            ${discountInfo}
            <div class="detail-row">
                <div class="detail-label">Status:</div>
                <div class="detail-value">
                    <span class="status-badge status-${rate.is_active == 1 ? 'active' : 'inactive'}">
                        ${rate.is_active == 1 ? 'Active' : 'Inactive'}
                    </span>
                </div>
            </div>
            ${rate.description ? `
            <div class="detail-row">
                <div class="detail-label">Description:</div>
                <div class="detail-value">${escapeHtml(rate.description)}</div>
            </div>
            ` : ''}
            <div class="detail-row">
                <div class="detail-label">Created:</div>
                <div class="detail-value">${formatDateTime(rate.created_at)}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Last Updated:</div>
                <div class="detail-value">${formatDateTime(rate.updated_at)}</div>
            </div>
        </div>
    `;
    
    document.getElementById('viewContent').innerHTML = content;
    document.getElementById('viewModal').style.display = 'block';
}

// Edit Rate
function editRate(rate) {
    document.getElementById('modalTitle').textContent = 'Edit Rate';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('rateId').value = rate.rate_id;
    document.getElementById('rate_name').value = rate.rate_name;
    document.getElementById('price').value = rate.price;
    document.getElementById('applies_to').value = rate.applies_to;
    document.getElementById('is_discounted').value = rate.is_discounted;
    document.getElementById('is_active').value = rate.is_active;
    document.getElementById('description').value = rate.description || '';
    
    // Toggle and populate discount fields
    toggleDiscountFields();
    if (rate.is_discounted == 1) {
        document.getElementById('base_rate_id').value = rate.base_rate_id || '';
        document.getElementById('discount_type').value = rate.discount_type || '';
    }
    
    document.getElementById('rateModal').style.display = 'block';
}

// Toggle Rate Status
function toggleStatus(rateId, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const action = currentStatus == 1 ? 'deactivate' : 'activate';
    
    showConfirmationModal({
        type: 'warning',
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} Rate`,
        message: `Are you sure you want to ${action} this rate?`,
        description: newStatus === 0 
            ? 'This rate will no longer be available for use.' 
            : 'This rate will become available for use.',
        confirmText: action.charAt(0).toUpperCase() + action.slice(1),
        confirmClass: '',
        onConfirm: () => {
            executeToggleStatus(rateId, newStatus);
        }
    });
}

function executeToggleStatus(rateId, newStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('rate_id', rateId);
    formData.append('is_active', newStatus);
    
    fetch('includes/rates_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Rate status updated successfully!', 'success');
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

// Delete Rate
function deleteRate(rateId, rateName) {
    showConfirmationModal({
        type: 'danger',
        title: 'Delete Rate',
        message: `Are you sure you want to delete this rate?`,
        rateName: rateName,
        description: 'This action cannot be undone. All rate data will be permanently removed.',
        confirmText: 'Delete Rate',
        confirmClass: 'danger',
        onConfirm: () => {
            executeDeleteRate(rateId);
        }
    });
}

function executeDeleteRate(rateId) {
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('rate_id', rateId);
    
    fetch('includes/rates_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Rate deleted successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message || 'Failed to delete rate', 'error');
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
    
    const rateNameHtml = options.rateName 
        ? `<p class="rate-highlight">"${escapeHtml(options.rateName)}"</p>` 
        : '';
    
    content.innerHTML = `
        <div class="confirmation-icon ${iconClass}">
            <i class="${icon}"></i>
        </div>
        <div class="confirmation-content">
            <h3>${options.title}</h3>
            <p>${options.message}</p>
            ${rateNameHtml}
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
    const form = document.getElementById('rateForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            
            // Validation
            const rateName = document.getElementById('rate_name').value.trim();
            const price = parseFloat(document.getElementById('price').value);
            const appliesTo = document.getElementById('applies_to').value;
            
            if (!rateName) {
                showAlert('Please enter a rate name', 'error');
                return;
            }
            
            if (price <= 0) {
                showAlert('Price must be greater than 0', 'error');
                return;
            }
            
            if (!appliesTo) {
                showAlert('Please select who this rate applies to', 'error');
                return;
            }
            
            // Validate discount fields if discounted
            const isDiscounted = document.getElementById('is_discounted').value;
            if (isDiscounted === '1') {
                const baseRateId = document.getElementById('base_rate_id').value;
                const discountType = document.getElementById('discount_type').value.trim();
                
                if (baseRateId) {
                    // Check if discounted price is less than base price
                    const baseRate = baseRatesData.find(br => br.rate_id == baseRateId);
                    if (baseRate && price >= parseFloat(baseRate.price)) {
                        showAlert('Discounted price must be less than the base rate price', 'error');
                        return;
                    }
                }
            }
            
            // Submit form
            fetch('includes/rates_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message || 'Rate saved successfully!', 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.message || 'Failed to save rate', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while saving', 'error');
            });
        });
    }
}

// Auto-calculate discount when base rate is selected
document.getElementById('base_rate_id')?.addEventListener('change', function() {
    const baseRateId = this.value;
    if (baseRateId && baseRatesData) {
        const baseRate = baseRatesData.find(br => br.rate_id == baseRateId);
        if (baseRate) {
            const currentPrice = parseFloat(document.getElementById('price').value) || 0;
            if (currentPrice === 0) {
                // Suggest a 20% discount
                const suggestedPrice = parseFloat(baseRate.price) * 0.8;
                document.getElementById('price').value = suggestedPrice.toFixed(2);
            }
        }
    }
});

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
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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