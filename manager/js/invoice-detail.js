// Invoice Detail JS

function openRecordPaymentModal() {
    const modal = document.getElementById('recordPaymentModal');
    modal.classList.add('active');
}

function closeRecordPaymentModal() {
    const modal = document.getElementById('recordPaymentModal');
    modal.classList.remove('active');
}

function openWaiveModal() {
    const modal = document.getElementById('waiveModal');
    modal.classList.add('active');
}

function closeWaiveModal() {
    const modal = document.getElementById('waiveModal');
    modal.classList.remove('active');
}

function generatePDF() {
    // Get the invoice ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    const invoiceId = urlParams.get('invoice_id');
    
    if (invoiceId) {
        window.open(`generate_invoice_pdf.php?invoice_id=${invoiceId}`, '_blank');
        showNotification('Opening PDF in new window...', 'info');
    }
}

function sendPaymentReminder() {
    const urlParams = new URLSearchParams(window.location.search);
    const invoiceId = urlParams.get('invoice_id');
    
    if (confirm('Send payment reminder email to the coach?')) {
        showNotification('Payment reminder sent successfully!', 'success');
    }
}

function printInvoice() {
    window.print();
}

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

// Form submission - Record Payment
const recordPaymentForm = document.getElementById('recordPaymentForm');
if (recordPaymentForm) {
    recordPaymentForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const paymentAmount = parseFloat(document.querySelector('input[name="payment_amount"]').value);
        
        if (paymentAmount <= 0) {
            showNotification('Please enter a valid payment amount', 'warning');
            return;
        }
        
        // Disable submit button
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recording...';
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('includes/invoice_handler.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('✓ Payment recorded successfully! Status: ' + result.new_status, 'success');
                closeRecordPaymentModal();
                
                // Reload page to update display
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showNotification('Error: ' + result.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred while recording the payment', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

// Form submission - Waive Commission
const waiveForm = document.getElementById('waiveForm');
if (waiveForm) {
    waiveForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to waive this commission? This action cannot be undone.')) {
            return;
        }
        
        // Disable submit button
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('includes/invoice_handler.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('✓ Commission waived successfully!', 'success');
                closeWaiveModal();
                
                // Reload page to update display
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showNotification('Error: ' + result.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred while processing the waiver', 'error');
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

// Initialize date inputs
document.addEventListener('DOMContentLoaded', function() {
    const paymentDateInput = document.querySelector('input[name="payment_date"]');
    if (paymentDateInput) {
        paymentDateInput.value = new Date().toISOString().split('T')[0];
    }
});
