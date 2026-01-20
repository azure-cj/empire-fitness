// Manage Payments JavaScript Handler

let currentPayment = {
    type: null,
    referenceId: null,
    amount: 0,
    description: ''
};

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Manage Payments page loaded');
    initializeEventListeners();
    loadPaymentData();
    setInterval(loadPaymentData, 30000); // Refresh every 30 seconds
});

// Event Listeners
function initializeEventListeners() {
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            switchTab(this.getAttribute('data-tab'));
        });
    });

    // Search inputs
    const searchWalkin = document.getElementById('search-walkin');
    const searchWalkinMembers = document.getElementById('search-walkin-members');
    const searchClass = document.getElementById('search-class');
    const searchOTCBookings = document.getElementById('search-otc-bookings');
    const searchRenewal = document.getElementById('search-renewal');
    
    if (searchWalkin) {
        searchWalkin.addEventListener('keyup', function() {
            filterTable('walkin-table', this.value);
        });
    }

    if (searchWalkinMembers) {
        searchWalkinMembers.addEventListener('keyup', function() {
            filterTable('walkin-members-table', this.value);
        });
    }
    
    if (searchClass) {
        searchClass.addEventListener('keyup', function() {
            filterTable('class-table', this.value);
        });
    }

    if (searchOTCBookings) {
        searchOTCBookings.addEventListener('keyup', function() {
            filterOTCBookingsTable(this.value);
        });
    }
    
    if (searchRenewal) {
        searchRenewal.addEventListener('keyup', function() {
            filterTable('renewal-table', this.value);
        });
    }

    // Modal close on background click
    const paymentModal = document.getElementById('payment-modal');
    if (paymentModal) {
        paymentModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });
    }
}

// Switch Tabs
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });

    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Show selected tab
    const contentEl = document.getElementById(tabName + '-content');
    if (contentEl) {
        contentEl.classList.add('active');
    }

    // Add active class to clicked button (find by data-tab)
    document.querySelectorAll('[data-tab="' + tabName + '"]').forEach(btn => {
        btn.classList.add('active');
    });
}

// Load Payment Data
function loadPaymentData() {
    console.log('📊 Loading payment data from database...');
    loadWalkinPayments();
    loadWalkinMemberPayments();
    loadClassPayments();
    loadOTCClassBookings();
    loadMembershipRenewals();
}

// Load Walk-in Payments (Recent Paid Payments)
function loadWalkinPayments() {
    console.log('🚶 Loading walk-in guest payments...');
    fetch('includes/payment_handler.php?action=get_walkin_payments')
        .then(response => response.json())
        .then(data => {
            console.log('✅ Walk-in guests response:', data);
            const tbody = document.getElementById('walkin-tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';

            if (data.success && data.payments && data.payments.length > 0) {
                console.log('✅ Found ' + data.payments.length + ' walk-in guests');
                data.payments.forEach(payment => {
                    const row = createWalkinRow(payment);
                    tbody.appendChild(row);
                });
            } else {
                console.log('⚠️ No walk-in guest payments found');
                tbody.innerHTML = `
                    <tr class="empty-row">
                        <td colspan="7" class="text-center">
                            <i class="fas fa-inbox"></i>
                            <p>No walk-in guest payments today</p>
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            console.error('❌ Error loading walk-in payments:', error);
        });
}

// Load Walk-in Member Payments (Members paying walk-in discounted rate)
function loadWalkinMemberPayments() {
    fetch('includes/payment_handler.php?action=get_walkin_member_payments')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('walkin-members-tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';

            if (data.success && data.payments && data.payments.length > 0) {
                data.payments.forEach(payment => {
                    const row = createWalkinMemberRow(payment);
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = `
                    <tr class="empty-row">
                        <td colspan="7" class="text-center">
                            <i class="fas fa-inbox"></i>
                            <p>No walk-in member payments today</p>
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => console.error('Error loading walk-in member payments:', error));
}

// Create Walk-in Member Row
function createWalkinMemberRow(payment) {
    const row = document.createElement('tr');
    
    // Determine status badge
    const isPaid = payment.status === 'paid' || payment.status === 'Paid';
    const statusBadge = isPaid 
        ? '<span class="badge badge-paid"><i class="fas fa-check"></i> Paid</span>'
        : '<span class="badge badge-pending"><i class="fas fa-hourglass"></i> Pending</span>';

    // Format amount
    const amount = parseFloat(payment.amount) || 0;
    const formattedAmount = amount > 0 ? '₱' + amount.toFixed(2) : '₱0.00';

    // Format time
    const timeDisplay = formatDateTime(payment.time_in);

    // Get discount rate
    const discountRate = payment.discount_rate || payment.discount_type || '-';

    row.innerHTML = `
        <td><strong>${escapeHtml(payment.member_name)}</strong></td>
        <td>${escapeHtml(payment.membership_plan || 'N/A')}</td>
        <td>${timeDisplay}</td>
        <td><span class="badge badge-discount">${escapeHtml(discountRate)}</span></td>
        <td><strong>${formattedAmount}</strong></td>
        <td>${statusBadge}</td>
        <td>${escapeHtml(payment.receptionist_name || 'Unassigned')}</td>
    `;
    return row;
}

// Create Walk-in Row (Display Only - Recent Payments)
function createWalkinRow(payment) {
    const row = document.createElement('tr');
    
    // Determine status badge
    const isPaid = payment.status === 'paid' || payment.status === 'Paid';
    const statusBadge = isPaid 
        ? '<span class="badge badge-paid"><i class="fas fa-check"></i> Paid</span>'
        : '<span class="badge badge-pending"><i class="fas fa-hourglass"></i> Pending</span>';

    // Format amount
    const amount = parseFloat(payment.amount) || 0;
    const formattedAmount = amount > 0 ? '₱' + amount.toFixed(2) : '₱0.00';

    // Format time
    const timeDisplay = formatDateTime(payment.time_in);

    row.innerHTML = `
        <td><strong>${escapeHtml(payment.guest_name)}</strong></td>
        <td>${timeDisplay}</td>
        <td>${payment.duration || '-'}</td>
        <td><span class="badge">Walk-in</span></td>
        <td><strong>${formattedAmount}</strong></td>
        <td>${statusBadge}</td>
        <td>${escapeHtml(payment.receptionist_name || 'Unassigned')}</td>
    `;
    return row;
}

// Load Class Payments
function loadClassPayments() {
    fetch('includes/payment_handler.php?action=get_class_payments')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('class-tbody');
            tbody.innerHTML = '';

            if (data.success && data.payments.length > 0) {
                data.payments.forEach(payment => {
                    const row = createClassRow(payment);
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = `
                    <tr class="empty-row">
                        <td colspan="6" class="text-center">
                            <i class="fas fa-inbox"></i>
                            <p>No pending class payments</p>
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => console.error('Error loading class payments:', error));
}

// Create Class Row
function createClassRow(payment) {
    const row = document.createElement('tr');
    const statusBadge = payment.payment_status === 'Pending'
        ? '<span class="badge badge-pending"><i class="fas fa-hourglass"></i> Pending</span>'
        : '<span class="badge badge-paid"><i class="fas fa-check"></i> Paid</span>';

    const scheduleDateTime = formatDate(payment.schedule_date) + ' ' + formatTime(payment.start_time);
    const amount = parseFloat(payment.amount) || 0;

    row.innerHTML = `
        <td><strong>${escapeHtml(payment.member_name)}</strong></td>
        <td>${escapeHtml(payment.class_name)}</td>
        <td>${scheduleDateTime}</td>
        <td><strong>₱${amount.toFixed(2)}</strong></td>
        <td>${statusBadge}</td>
        <td>
            ${payment.payment_status === 'Pending' ? `
                <button class="action-btn btn-process" onclick="openPaymentModal('class', ${payment.request_id}, ${amount}, '${escapeHtml(payment.member_name)} - ${escapeHtml(payment.class_name)}')">
                    <i class="fas fa-check"></i> Process
                </button>
            ` : '-'}
        </td>
    `;
    return row;
}

// Load Membership Renewals
function loadMembershipRenewals() {
    fetch('includes/payment_handler.php?action=get_renewals')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('renewal-tbody');
            tbody.innerHTML = '';

            if (data.success && data.renewals.length > 0) {
                data.renewals.forEach(renewal => {
                    const row = createRenewalRow(renewal);
                    tbody.appendChild(row);
                });
                updateRenewalsCount(data.pending_renewals);
            } else {
                tbody.innerHTML = `
                    <tr class="empty-row">
                        <td colspan="6" class="text-center">
                            <i class="fas fa-inbox"></i>
                            <p>No pending renewals</p>
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => console.error('Error loading renewals:', error));
}

// Create Renewal Row
function createRenewalRow(renewal) {
    const row = document.createElement('tr');
    const statusBadge = isExpiring(renewal.end_date)
        ? '<span class="badge badge-expired">Expiring Soon</span>'
        : '<span class="badge badge-pending">Pending</span>';

    // Calculate estimated renewal amount based on plan
    const estimatedAmount = calculateRenewalCost(renewal);

    row.innerHTML = `
        <td><strong>${escapeHtml(renewal.client_name)}</strong></td>
        <td>${escapeHtml(renewal.plan_name)}</td>
        <td>${formatDate(renewal.end_date)}</td>
        <td><strong>₱${estimatedAmount.toFixed(2)}</strong></td>
        <td>${statusBadge}</td>
        <td>
            <button class="action-btn btn-process" onclick="openPaymentModal('renewal', ${renewal.client_id}, ${estimatedAmount}, 'Renewal - ${escapeHtml(renewal.client_name)}')">
                <i class="fas fa-check"></i> Process
            </button>
        </td>
    `;
    return row;
}

// Calculate renewal cost based on member's last plan
function calculateRenewalCost(renewal) {
    // If there's a price stored in database for this membership plan, use it
    if (renewal.plan_price) {
        return parseFloat(renewal.plan_price);
    }
    
    // Otherwise use renewal_amount if available
    if (renewal.renewal_amount) {
        return parseFloat(renewal.renewal_amount);
    }
    
    // Fallback: try to estimate from plan duration (assuming standard pricing)
    // This is a backup if data isn't available in DB
    return 0;
}

// Open Payment Modal
function openPaymentModal(type, referenceId, amount, description, timeIn, rateType, receptionist) {
    currentPayment = {
        type: type,
        referenceId: referenceId,
        amount: amount,
        description: description
    };

    // Update modal content
    document.getElementById('modal-payee').textContent = description;
    
    // Format amount with proper handling of NaN
    const formattedAmount = isNaN(amount) || amount === 0 ? '₱0.00' : '₱' + parseFloat(amount).toFixed(2);
    document.getElementById('modal-amount').textContent = formattedAmount;

    // Update approval modal specific fields for walk-in
    if (type === 'walkin') {
        document.getElementById('modal-time-in').textContent = timeIn ? formatDateTime(timeIn) : '-';
        document.getElementById('modal-rate-type').textContent = rateType ? rateType : '-';
        document.getElementById('modal-receptionist').textContent = receptionist ? receptionist : '-';
    } else {
        // Hide walk-in specific fields for other payment types
        document.getElementById('modal-time-in').textContent = '-';
        document.getElementById('modal-rate-type').textContent = '-';
        document.getElementById('modal-receptionist').textContent = '-';
    }

    // Reset form
    document.getElementById('payment-form').reset();
    document.getElementById('payment-type').value = type;
    document.getElementById('payment-reference-id').value = referenceId;

    // Show modal
    document.getElementById('payment-modal').classList.add('active');
}

// Format DateTime helper - handles both timestamp and time-only formats
function formatDateTime(dateTimeString) {
    if (!dateTimeString) return '-';
    
    // If it's just a time (HH:MM:SS), return as is (formatted)
    if (dateTimeString.length <= 8) {
        // Format as HH:MM from HH:MM:SS
        const timeParts = dateTimeString.split(':');
        if (timeParts.length >= 2) {
            return timeParts[0] + ':' + timeParts[1];
        }
        return dateTimeString;
    }
    
    // If it's a full timestamp, parse and format
    const date = new Date(dateTimeString);
    if (isNaN(date.getTime())) {
        // If parsing failed, return as-is
        return dateTimeString;
    }
    
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return hours + ':' + minutes;
}

// Close Payment Modal
function closePaymentModal() {
    document.getElementById('payment-modal').classList.remove('active');
    document.getElementById('payment-form').reset();
}

// Submit Payment
function submitPayment() {
    const form = document.getElementById('payment-form');
    const paymentConfirm = document.getElementById('payment-confirm');
    
    if (!form.checkValidity() || !paymentConfirm.checked) {
        showToast('error', 'Please fill in all required fields and confirm payment');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'process_payment');
    formData.append('type', currentPayment.type);
    formData.append('reference_id', currentPayment.referenceId);
    formData.append('amount', currentPayment.amount);
    formData.append('payment_method', document.getElementById('payment-method').value);
    formData.append('remarks', document.getElementById('payment-remarks').value);

    const submitBtn = document.getElementById('submit-payment-btn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    submitBtn.disabled = true;

    fetch('includes/payment_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            closePaymentModal();
            loadPaymentData();
        } else {
            showToast('error', data.message || 'Error processing payment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Error processing payment');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Filter Table
function filterTable(tableId, searchTerm) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = tbody.getElementsByTagName('tr');
    searchTerm = searchTerm.toLowerCase().trim();

    let visibleCount = 0;
    let hasEmptyRow = false;

    Array.from(rows).forEach(row => {
        // Check if this is an empty state row
        if (row.classList.contains('empty-row')) {
            hasEmptyRow = true;
            // Hide empty row if we have search term or other rows to show
            row.style.display = 'none';
            return;
        }

        if (searchTerm === '') {
            // Show all rows if search is empty
            row.style.display = '';
            visibleCount++;
        } else {
            // Filter by search term
            const text = row.textContent.toLowerCase();
            const isVisible = text.includes(searchTerm);
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        }
    });

    // Show empty message if no results and we have an empty row
    if (visibleCount === 0 && hasEmptyRow && searchTerm !== '') {
        const emptyRow = tbody.querySelector('.empty-row');
        if (emptyRow) {
            emptyRow.innerHTML = `
                <td colspan="${emptyRow.cells.length}" class="text-center">
                    <i class="fas fa-search"></i>
                    <p>No results found for "${searchTerm}"</p>
                </td>
            `;
            emptyRow.style.display = '';
        }
    }
}

function filterOTCBookingsTable(searchTerm) {
    searchTerm = searchTerm.toLowerCase().trim();
    
    if (searchTerm === '') {
        renderOTCBookings(allOTCBookings);
    } else {
        const filtered = allOTCBookings.filter(booking => 
            booking.client_name.toLowerCase().includes(searchTerm) ||
            booking.class_name.toLowerCase().includes(searchTerm) ||
            booking.request_id.toString().includes(searchTerm)
        );
        renderOTCBookings(filtered);
    }
}

// Utility Functions
function showToast(type, message) {
    const toast = document.getElementById('toast');
    const messageEl = document.getElementById('toast-message');
    const iconEl = toast.querySelector('.toast-icon');

    toast.className = 'toast show ' + type;
    messageEl.textContent = message;

    if (type === 'success') {
        iconEl.className = 'toast-icon fas fa-check-circle';
    } else {
        iconEl.className = 'toast-icon fas fa-exclamation-circle';
    }

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

function getPaymentDescription(type) {
    const descriptions = {
        'walkin': 'Daily walk-in entrance fee',
        'class': 'Class session payment',
        'renewal': 'Membership renewal payment'
    };
    return descriptions[type] || 'Payment';
}

function formatTime(time) {
    if (!time) return '-';
    const date = new Date('2000-01-01 ' + time);
    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function isExpiring(date) {
    const today = new Date();
    const expiryDate = new Date(date);
    const daysUntilExpiry = Math.floor((expiryDate - today) / (1000 * 60 * 60 * 24));
    return daysUntilExpiry <= 7;
}

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

function updatePendingCount(count) {
    document.getElementById('pending-count').textContent = count || 0;
}

function updateTodayTotal(total) {
    document.getElementById('today-total').textContent = parseFloat(total || 0).toFixed(2);
}

function updateRenewalsCount(count) {
    document.getElementById('renewals-count').textContent = count || 0;
}

// ========================================
// OTC CLASS BOOKINGS MANAGEMENT
// ========================================

let allOTCBookings = [];

function loadOTCClassBookings() {
    fetch('includes/otc_bookings_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_otc_class_bookings'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            allOTCBookings = data.data;
            renderOTCBookings(allOTCBookings);
        } else {
            console.error('Error loading OTC bookings:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function renderOTCBookings(bookings) {
    const tbody = document.getElementById('otc-bookings-tbody');
    
    if (!bookings || bookings.length === 0) {
        tbody.innerHTML = `
            <tr class="empty-row">
                <td colspan="7" class="text-center">
                    <i class="fas fa-inbox"></i>
                    <p>No OTC class bookings pending</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = bookings.map(booking => {
        const statusClass = getOTCStatusClass(booking.status);
        const scheduledDate = new Date(booking.scheduled_date).toLocaleDateString('en-US', { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
        
        return `
            <tr>
                <td>#${booking.request_id}</td>
                <td>${escapeHtml(booking.client_name)}</td>
                <td>${escapeHtml(booking.class_name)}</td>
                <td>${scheduledDate}</td>
                <td><strong>₱${parseFloat(booking.amount).toFixed(2)}</strong></td>
                <td><span class="status-badge ${statusClass}">${booking.status}</span></td>
                <td>
                    <button onclick="viewOTCBooking(${booking.request_id})" class="action-btn" title="View & Approve">
                        <i class="fas fa-eye"></i> View
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function viewOTCBooking(requestId) {
    fetch('includes/otc_bookings_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_otc_booking&request_id=${requestId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const booking = data.data;
            displayOTCBookingModal(booking);
        } else {
            showToast('Error loading booking details', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error loading booking details', 'error');
    });
}

function displayOTCBookingModal(booking) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.id = 'otc-detail-modal';
    modal.innerHTML = `
        <div class="modal-dialog" style="max-width: 600px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><i class="fas fa-credit-card"></i> OTC Class Booking Details</h2>
                    <button class="modal-close" onclick="this.closest('.modal').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <div>
                            <label style="font-weight: 600; font-size: 12px; color: #666;">BOOKING ID</label>
                            <p style="margin: 5px 0 0 0;">#${booking.request_id}</p>
                        </div>
                        <div>
                            <label style="font-weight: 600; font-size: 12px; color: #666;">MEMBER NAME</label>
                            <p style="margin: 5px 0 0 0;">${escapeHtml(booking.client_name)}</p>
                        </div>
                        <div>
                            <label style="font-weight: 600; font-size: 12px; color: #666;">CLASS NAME</label>
                            <p style="margin: 5px 0 0 0;">${escapeHtml(booking.class_name)}</p>
                        </div>
                        <div>
                            <label style="font-weight: 600; font-size: 12px; color: #666;">BOOKING TYPE</label>
                            <p style="margin: 5px 0 0 0;">${booking.booking_type}</p>
                        </div>
                        <div>
                            <label style="font-weight: 600; font-size: 12px; color: #666;">SCHEDULED DATE</label>
                            <p style="margin: 5px 0 0 0;">${new Date(booking.scheduled_date).toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' })}</p>
                        </div>
                        <div>
                            <label style="font-weight: 600; font-size: 12px; color: #666;">SCHEDULED TIME</label>
                            <p style="margin: 5px 0 0 0;">${booking.scheduled_time}</p>
                        </div>
                    </div>

                    <div style="background: #f0f8ff; padding: 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #667eea;">
                        <small style="color: #666; font-weight: 600;">AMOUNT DUE</small>
                        <h3 style="margin: 8px 0 0 0; font-size: 32px; color: #27ae60;">₱${parseFloat(booking.amount).toFixed(2)}</h3>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: 600; font-size: 12px; color: #666;">PAYMENT STATUS</label>
                        <p style="margin: 5px 0 0 0;"><span class="status-badge ${getOTCStatusClass(booking.status)}">${booking.status}</span></p>
                    </div>

                    ${booking.notes ? `
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: 600; font-size: 12px; color: #666;">NOTES</label>
                        <p style="margin: 5px 0 0 0; color: #555;">${escapeHtml(booking.notes)}</p>
                    </div>
                    ` : ''}

                    ${booking.payment_proof && booking.payment_proof !== 'OTC-PENDING' ? `
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: 600; font-size: 12px; color: #666;">PAYMENT PROOF</label>
                        <img src="../uploads/${booking.payment_proof}" alt="Payment proof" style="max-width: 100%; max-height: 300px; border-radius: 6px; margin-top: 10px;">
                    </div>
                    ` : `
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: 600; font-size: 12px; color: #666;">PAYMENT PROOF</label>
                        <p style="margin: 5px 0 0 0; color: #999;">No payment proof available</p>
                    </div>
                    `}
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">
                        <i class="fas fa-times"></i> Close
                    </button>
                    ${(booking.status === 'Pending Payment' || booking.status === 'Payment Submitted') ? `
                        <button class="btn btn-danger" onclick="rejectOTCBooking(${booking.request_id})">
                            <i class="fas fa-ban"></i> Reject
                        </button>
                        <button class="btn btn-success" onclick="approveOTCBooking(${booking.request_id})">
                            <i class="fas fa-check"></i> Approve
                        </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'flex';
    modal.onclick = function(event) {
        if (event.target === modal) modal.remove();
    };
}

function approveOTCBooking(requestId) {
    if (!confirm('Are you sure you want to approve this OTC payment?')) {
        return;
    }
    
    fetch('includes/otc_bookings_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=approve_otc_booking&request_id=${requestId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('OTC booking approved successfully', 'success');
            document.getElementById('otc-detail-modal').remove();
            loadOTCClassBookings();
        } else {
            showToast('Error approving booking: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error approving booking', 'error');
    });
}

function rejectOTCBooking(requestId) {
    const reason = prompt('Enter rejection reason:');
    if (!reason) return;
    
    fetch('includes/otc_bookings_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=reject_otc_booking&request_id=${requestId}&reason=${encodeURIComponent(reason)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('OTC booking rejected successfully', 'success');
            document.getElementById('otc-detail-modal').remove();
            loadOTCClassBookings();
        } else {
            showToast('Error rejecting booking: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error rejecting booking', 'error');
    });
}

function getOTCStatusClass(status) {
    switch(status) {
        case 'Pending Payment':
            return 'status-pending';
        case 'Payment Submitted':
            return 'status-submitted';
        case 'Payment Verified':
            return 'status-verified';
        case 'Rejected':
            return 'status-rejected';
        default:
            return 'status-default';
    }
}

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const messageEl = document.getElementById('toast-message');
    
    messageEl.textContent = message;
    toast.className = `toast toast-${type}`;
    toast.style.display = 'block';
    
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}