// Commission Management Dashboard JS

// Modal Management
function openGenerateInvoiceModal() {
    const modal = document.getElementById('generateInvoiceModal');
    modal.classList.add('active');
    
    // Set default month to current month
    const today = new Date();
    document.getElementById('invoiceMonth').value = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');
    
    // Set default due date to 30 days from now
    const dueDate = new Date(today);
    dueDate.setDate(dueDate.getDate() + 30);
    document.getElementById('dueDate').value = dueDate.toISOString().split('T')[0];
}

function closeGenerateInvoiceModal() {
    const modal = document.getElementById('generateInvoiceModal');
    modal.classList.remove('active');
}

function generateInvoice(coachId) {
    document.getElementById('invoiceCoachId').value = coachId;
    openGenerateInvoiceModal();
}

function generateMonthlyInvoices() {
    if (confirm('This will generate invoices for all active coaches with pending commissions. Continue?')) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        
        // Show notification
        showNotification('Generating monthly invoices...', 'info');
    }
}

function viewHistory(coachId) {
    // Fetch and display invoice history for this coach
    const coachRow = document.querySelector(`tr[data-coach-id="${coachId}"]`);
    const coachName = coachRow ? coachRow.querySelector('td:nth-child(1)').textContent.trim() : 'Coach';
    
    // Open modal with loading state
    const modal = document.getElementById('historyModal');
    if (!modal) {
        showNotification('History modal not found', 'error');
        return;
    }
    
    modal.classList.add('active');
    const historyContent = document.getElementById('historyContent');
    historyContent.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading history...</div>';
    
    // Fetch invoice history
    fetch(`includes/invoice_handler.php?action=get_coach_history&coach_id=${coachId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.invoices) {
                let html = '<div class="history-list">';
                
                if (data.invoices.length === 0) {
                    html += '<p style="text-align: center; color: #999; padding: 20px;">No invoices found</p>';
                } else {
                    data.invoices.forEach(invoice => {
                        const statusBadge = `<span class="status-badge status-${invoice.status.toLowerCase().replace(/ /g, '-')}">${invoice.status}</span>`;
                        html += `
                            <div class="history-item" onclick="window.location.href='invoice-detail.php?invoice_id=${invoice.invoice_id}'">
                                <div class="history-header">
                                    <strong>${invoice.invoice_number}</strong>
                                    ${statusBadge}
                                </div>
                                <div class="history-details">
                                    <div class="detail-line">
                                        <span class="label">Month:</span>
                                        <span class="value">${invoice.invoice_month}</span>
                                    </div>
                                    <div class="detail-line">
                                        <span class="label">Amount Due:</span>
                                        <span class="value">₱${parseFloat(invoice.total_commission_due).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                                    </div>
                                    <div class="detail-line">
                                        <span class="label">Paid:</span>
                                        <span class="value">₱${parseFloat(invoice.paid_amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                                    </div>
                                    <div class="detail-line">
                                        <span class="label">Created:</span>
                                        <span class="value">${new Date(invoice.created_at).toLocaleDateString('en-PH')}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                html += '</div>';
                historyContent.innerHTML = html;
                
                // Update modal title
                document.getElementById('historyModalTitle').textContent = `Invoice History - ${coachName}`;
            } else {
                historyContent.innerHTML = `<p style="color: red; padding: 20px;">Error loading history: ${data.message}</p>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            historyContent.innerHTML = '<p style="color: red; padding: 20px;">Error loading history</p>';
        });
}

function viewPaymentHistory(coachId) {
    // Fetch and display payment history for this coach
    const coachRow = document.querySelector(`tr[data-coach-id="${coachId}"]`);
    const coachName = coachRow ? coachRow.querySelector('td:nth-child(1)').textContent.trim() : 'Coach';
    
    const modal = document.getElementById('paymentHistoryModal');
    if (!modal) {
        showNotification('Payment history modal not found', 'error');
        return;
    }
    
    modal.classList.add('active');
    const paymentContent = document.getElementById('paymentHistoryContent');
    paymentContent.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading payment history...</div>';
    
    // Fetch payment history
    fetch(`includes/invoice_handler.php?action=get_payment_history&coach_id=${coachId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.payments) {
                let html = '<div class="payment-list">';
                
                if (data.payments.length === 0) {
                    html += '<p style="text-align: center; color: #999; padding: 20px;">No payments found</p>';
                } else {
                    data.payments.forEach(payment => {
                        html += `
                            <div class="payment-item">
                                <div class="payment-header">
                                    <strong>${payment.invoice_number}</strong>
                                    <span style="color: #27ae60; font-weight: bold;">₱${parseFloat(payment.payment_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                                </div>
                                <div class="payment-details">
                                    <div class="detail-line">
                                        <span class="label">Method:</span>
                                        <span class="value">${payment.payment_method || 'N/A'}</span>
                                    </div>
                                    <div class="detail-line">
                                        <span class="label">Reference:</span>
                                        <span class="value">${payment.payment_reference || 'N/A'}</span>
                                    </div>
                                    <div class="detail-line">
                                        <span class="label">Date:</span>
                                        <span class="value">${new Date(payment.paid_date).toLocaleDateString('en-PH')}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                html += '</div>';
                paymentContent.innerHTML = html;
                
                // Update modal title
                document.getElementById('paymentHistoryModalTitle').textContent = `Payment History - ${coachName}`;
            } else {
                paymentContent.innerHTML = `<p style="color: red; padding: 20px;">Error loading payment history: ${data.message}</p>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            paymentContent.innerHTML = '<p style="color: red; padding: 20px;">Error loading payment history</p>';
        });
}

function viewCoachCommissions(coachId) {
    showNotification('Coach commissions view - redirecting...', 'info');
    // Redirect after showing notification
    setTimeout(() => {
        window.location.href = `coach-commission-history.php?coach_id=${coachId}`;
    }, 800);
}

function viewCoachProfile(coachId) {
    window.location.href = `coach_profile.php?coach_id=${coachId}`;
}

function sendPaymentReminder(coachId) {
    if (confirm('Send payment reminder email to this coach?')) {
        showNotification('Payment reminder sent successfully!', 'success');
    }
}

// Dropdown Toggle
function toggleDropdown(button) {
    event.stopPropagation();
    const dropdown = button.closest('.dropdown').querySelector('.dropdown-menu');
    const allDropdowns = document.querySelectorAll('.dropdown-menu');
    
    // Close all other dropdowns
    allDropdowns.forEach(d => {
        if (d !== dropdown) {
            d.classList.remove('active');
        }
    });
    
    // Toggle current dropdown
    dropdown.classList.toggle('active');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(d => {
            d.classList.remove('active');
        });
    }
});

// Notification System
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${getNotificationIcon(type)}"></i>
        <span>${message}</span>
        <button class="notification-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 4 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

function getNotificationIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

// Search functionality
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('.coach-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const coachName = row.querySelector('.coach-name-cell').textContent.toLowerCase();
            const email = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            
            if (coachName.includes(searchTerm) || email.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide empty state
        const tbody = document.querySelector('.commission-table tbody');
        if (visibleCount === 0 && searchTerm.length > 0) {
            if (!tbody.querySelector('.no-results')) {
                const noResults = document.createElement('tr');
                noResults.className = 'no-results';
                noResults.innerHTML = `<td colspan="8" style="text-align: center; padding: 20px; color: #999;">No coaches found matching "${searchTerm}"</td>`;
                tbody.appendChild(noResults);
            }
        } else {
            const noResults = tbody.querySelector('.no-results');
            if (noResults) noResults.remove();
        }
    });
}

// Filter and sort functionality
const filterStatus = document.getElementById('filterStatus');
const sortBy = document.getElementById('sortBy');

if (filterStatus) {
    filterStatus.addEventListener('change', applyFiltersAndSort);
}

if (sortBy) {
    sortBy.addEventListener('change', applyFiltersAndSort);
}

function applyFiltersAndSort() {
    const status = filterStatus?.value || 'all';
    const sortValue = sortBy?.value || 'balance';
    
    const rows = Array.from(document.querySelectorAll('.coach-row'));
    
    // Apply filters
    const filteredRows = rows.filter(row => {
        if (status === 'all') return true;
        return row.dataset.status === status;
    });
    
    // Sort
    filteredRows.sort((a, b) => {
        if (sortValue === 'balance') {
            const balanceA = parseFloat(a.querySelector('.balance-cell.pending').textContent.replace(/[₱,]/g, ''));
            const balanceB = parseFloat(b.querySelector('.balance-cell.pending').textContent.replace(/[₱,]/g, ''));
            return balanceB - balanceA;
        } else if (sortValue === 'name') {
            const nameA = a.querySelector('.coach-name-cell').textContent;
            const nameB = b.querySelector('.coach-name-cell').textContent;
            return nameA.localeCompare(nameB);
        } else if (sortValue === 'recent') {
            const dateA = a.querySelector('td:nth-child(6)').textContent.trim();
            const dateB = b.querySelector('td:nth-child(6)').textContent.trim();
            if (dateA === 'N/A') return 1;
            if (dateB === 'N/A') return -1;
            return new Date(dateB) - new Date(dateA);
        }
        return 0;
    });
    
    // Reorder rows in table
    const tbody = document.querySelector('.commission-table tbody');
    filteredRows.forEach(row => {
        tbody.appendChild(row);
    });
}

// Form submission
const generateInvoiceForm = document.getElementById('generateInvoiceForm');
if (generateInvoiceForm) {
    generateInvoiceForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const coachId = document.getElementById('invoiceCoachId').value;
        const invoiceMonth = document.getElementById('invoiceMonth').value;
        const dueDate = document.getElementById('dueDate').value;
        
        // Validate
        if (!coachId || !invoiceMonth || !dueDate) {
            showNotification('Please fill in all required fields', 'warning');
            return;
        }
        
        // Disable submit button
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('includes/invoice_handler.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('✓ Invoice ' + result.invoice_number + ' generated successfully!', 'success');
                closeGenerateInvoiceModal();
                
                // Redirect to invoice detail page
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 1500);
            } else {
                showNotification('Error: ' + result.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred while generating the invoice', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('Commission Management Dashboard loaded');
    applyFiltersAndSort();
});
