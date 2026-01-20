// Sales Report JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initCharts();
});

// Initialize all charts
function initCharts() {
    initDailyRevenueChart();
    initPaymentTypeChart();
    initPaymentMethodChart();
}

// Daily Revenue Trend Chart
function initDailyRevenueChart() {
    const ctx = document.getElementById('dailyRevenueChart');
    if (!ctx) return;
    
    const dates = Object.keys(dailyRevenueData);
    const revenues = Object.values(dailyRevenueData);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates.map(date => {
                const d = new Date(date);
                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }),
            datasets: [{
                label: 'Daily Revenue',
                data: revenues,
                borderColor: '#d41c1c',
                backgroundColor: 'rgba(212, 28, 28, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#d41c1c',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: ₱' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Revenue by Payment Type Pie Chart
function initPaymentTypeChart() {
    const ctx = document.getElementById('paymentTypeChart');
    if (!ctx) return;
    
    const types = Object.keys(revenueByTypeData);
    const revenues = Object.values(revenueByTypeData);
    
    const colors = {
        'Membership': '#3b82f6',
        'Monthly': '#8b5cf6',
        'Daily': '#f59e0b',
        'Service': '#10b981',
        'Class': '#f59e0b'
    };
    
    const backgroundColors = types.map(type => colors[type] || '#6b7280');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: types,
            datasets: [{
                data: revenues,
                backgroundColor: backgroundColors,
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return label + ': ₱' + value.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

// Revenue by Payment Method Bar Chart
function initPaymentMethodChart() {
    const ctx = document.getElementById('paymentMethodChart');
    if (!ctx) return;
    
    const methods = Object.keys(revenueByMethodData);
    const revenues = Object.values(revenueByMethodData);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: methods,
            datasets: [{
                label: 'Revenue',
                data: revenues,
                backgroundColor: 'rgba(212, 28, 28, 0.8)',
                borderColor: '#d41c1c',
                borderWidth: 1,
                borderRadius: 6,
                hoverBackgroundColor: '#b91a1a'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: ₱' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Reset Filters
function resetFilters() {
    window.location.href = 'sales.php';
}

// Show Alert
function showAlert(message, type = 'success') {
    const alertBox = document.getElementById('alertBox');
    alertBox.className = `alert-box ${type}`;
    alertBox.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'exclamation-triangle'}"></i>
        ${message}
    `;
    alertBox.style.display = 'block';
    
    setTimeout(() => {
        alertBox.style.display = 'none';
    }, 5000);
}

// View Payment Details
async function viewPayment(paymentId) {
    try {
        const formData = new FormData();
        formData.append('action', 'get');
        formData.append('payment_id', paymentId);
        
        const response = await fetch('includes/sales_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            const payment = data.payment;
            const viewBody = document.getElementById('viewModalBody');
            
            viewBody.innerHTML = `
                <div class="payment-details">
                    <div class="payment-summary">
                        <div class="summary-card">
                            <div class="label">Payment ID</div>
                            <div class="value">#${payment.payment_id}</div>
                        </div>
                        <div class="summary-card">
                            <div class="label">Payment Date</div>
                            <div class="value">${formatDate(payment.payment_date)}</div>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Client Name</div>
                        <div class="detail-value"><strong>${escapeHtml(payment.client_name)}</strong></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Email</div>
                        <div class="detail-value">${escapeHtml(payment.client_email)}</div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Payment Type</div>
                        <div class="detail-value">
                            <span class="payment-type-badge type-${payment.payment_type.toLowerCase()}">
                                ${payment.payment_type}
                            </span>
                        </div>
                    </div>
                    
                    ${payment.reference_id ? `
                        <div class="detail-row">
                            <div class="detail-label">Reference ID</div>
                            <div class="detail-value">#${payment.reference_id}</div>
                        </div>
                    ` : ''}
                    
                    <div class="detail-row">
                        <div class="detail-label">Amount</div>
                        <div class="detail-value amount-large">₱${parseFloat(payment.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Payment Method</div>
                        <div class="detail-value">
                            <span class="payment-method-badge method-${payment.payment_method.toLowerCase().replace(/ /g, '-')}">
                                ${payment.payment_method}
                            </span>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <span class="status-badge status-${payment.payment_status.toLowerCase()}">
                                <i class="fas fa-circle"></i> ${payment.payment_status}
                            </span>
                        </div>
                    </div>
                    
                    ${payment.remarks ? `
                        <div class="detail-row">
                            <div class="detail-label">Remarks</div>
                            <div class="detail-value">
                                <div class="remarks-box">${escapeHtml(payment.remarks)}</div>
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="detail-row">
                        <div class="detail-label">Created By</div>
                        <div class="detail-value">${escapeHtml(payment.created_by_name || 'System')}</div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Created At</div>
                        <div class="detail-value">${formatDateTime(payment.created_at)}</div>
                    </div>
                </div>
            `;
            
            document.getElementById('viewModal').classList.add('active');
        } else {
            showAlert(data.message || 'Error loading payment data', 'error');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'error');
    }
}

// Mark Payment as Paid
async function markAsPaid(paymentId) {
    if (!confirm('Mark this payment as paid?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'mark_as_paid');
        formData.append('payment_id', paymentId);
        
        const response = await fetch('includes/sales_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.message || 'Error updating payment status', 'error');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'error');
    }
}

// Export Report
async function exportReport(format) {
    try {
        const params = new URLSearchParams(window.location.search);
        params.append('action', 'export_' + format);
        
        const formData = new FormData();
        formData.append('action', 'export_' + format);
        formData.append('date_from', params.get('date_from') || document.getElementById('date_from').value);
        formData.append('date_to', params.get('date_to') || document.getElementById('date_to').value);
        formData.append('payment_type', params.get('payment_type') || document.getElementById('payment_type').value);
        formData.append('payment_status', params.get('payment_status') || document.getElementById('payment_status').value);
        formData.append('payment_method', params.get('payment_method') || document.getElementById('payment_method').value);
        
        const response = await fetch('includes/sales_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (format === 'csv') {
                const blob = new Blob([data.csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = data.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            }
            showAlert('Report exported successfully!', 'success');
        } else {
            showAlert(data.message || 'Error exporting report', 'error');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'error');
    }
}

// Close View Modal
function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
}

// Utility Functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const viewModal = document.getElementById('viewModal');
    if (event.target === viewModal) {
        closeViewModal();
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeViewModal();
    }
});

console.log('Sales Report System Loaded');