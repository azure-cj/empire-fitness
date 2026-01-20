// Daily Report Management
let currentDate = new Date();
let reportData = {};
let paymentMethodChart = null;
let entryTypeChart = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('📊 Daily Report page loaded');
    
    // Set date input to today
    const dateInput = document.getElementById('report-date');
    dateInput.valueAsDate = new Date();
    dateInput.addEventListener('change', () => {
        const selectedDate = new Date(dateInput.value);
        currentDate = selectedDate;
        generateReport();
    });
    
    // Load initial report
    generateReport();
});

// Generate Report
function generateReport() {
    console.log('📄 Generating report for:', formatDate(currentDate));
    const dateStr = formatDateForAPI(currentDate);
    
    loadSummary(dateStr);
    loadAttendance(dateStr);
    loadPayments(dateStr);
    loadPOSReport(dateStr);
    loadClasses(dateStr);
}

// Load Summary Data
function loadSummary(dateStr) {
    console.log('📈 Loading summary...');
    fetch(`includes/daily_report_handler.php?action=get_summary&date=${dateStr}`)
        .then(response => response.json())
        .then(data => {
            console.log('✅ Summary Response:', data);
            if (data.success) {
                displaySummary(data.summary, data.payment_methods, data.entry_types);
            } else {
                console.error('❌ Summary Error:', data.message);
                showToast('Failed to load summary: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('❌ Summary fetch error:', error);
            showToast('Error loading summary', 'error');
        });
}

// Display Summary
function displaySummary(summary, paymentMethods, entryTypes) {
    console.log('📊 Displaying summary:', summary);
    
    document.getElementById('total-entries').textContent = summary.total_entries;
    document.getElementById('total-revenue').textContent = '₱' + formatCurrency(summary.total_revenue);
    document.getElementById('classes-held').textContent = summary.classes_held;
    document.getElementById('members-attended').textContent = summary.members_attended;
    
    // Update charts
    updatePaymentMethodChart(paymentMethods);
    updateEntryTypeChart(entryTypes);
}

// Update Payment Method Chart
function updatePaymentMethodChart(data) {
    const ctx = document.getElementById('payment-method-chart');
    if (!ctx) return;
    
    const labels = data.map(d => d.method);
    const counts = data.map(d => d.count);
    const colors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#00f2fe', '#43e97b'];
    
    if (paymentMethodChart) {
        paymentMethodChart.destroy();
    }
    
    paymentMethodChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: colors.slice(0, labels.length),
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { size: 12 } }
                }
            }
        }
    });
}

// Update Entry Type Chart
function updateEntryTypeChart(data) {
    const ctx = document.getElementById('entry-type-chart');
    if (!ctx) return;
    
    const labels = data.map(d => d.attendance_type);
    const counts = data.map(d => d.count);
    const colors = ['#10b981', '#f59e0b', '#ef4444'];
    
    if (entryTypeChart) {
        entryTypeChart.destroy();
    }
    
    entryTypeChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: colors.slice(0, labels.length),
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { size: 12 } }
                }
            }
        }
    });
}

// Load Attendance Data
function loadAttendance(dateStr) {
    console.log('👥 Loading attendance...');
    fetch(`includes/daily_report_handler.php?action=get_attendance&date=${dateStr}`)
        .then(response => response.json())
        .then(data => {
            console.log('✅ Attendance Response:', data);
            if (data.success) {
                displayAttendance(data.attendance);
            } else {
                console.error('❌ Attendance Error:', data.message);
                displayAttendance([]); // Show empty state
            }
        })
        .catch(error => {
            console.error('❌ Attendance fetch error:', error);
            displayAttendance([]); // Show empty state
        });
}

// Display Attendance
function displayAttendance(attendance) {
    const tbody = document.getElementById('attendance-body');
    
    if (!attendance || attendance.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No attendance records for this date</td></tr>';
        return;
    }
    
    tbody.innerHTML = attendance.map(att => `
        <tr>
            <td>${formatTime(att.time_in)}</td>
            <td>${att.name}</td>
            <td><span class="badge badge-${att.attendance_type.toLowerCase().replace(/\s+/g, '-')}">${att.attendance_type}</span></td>
            <td>${att.discount_type || 'Regular'}</td>
            <td>${att.duration}</td>
            <td><span class="status-badge status-${att.status.toLowerCase()}">${att.status}</span></td>
        </tr>
    `).join('');
}

// Load Payments Data
function loadPayments(dateStr) {
    console.log('💰 Loading payments...');
    fetch(`includes/daily_report_handler.php?action=get_payments&date=${dateStr}`)
        .then(response => response.json())
        .then(data => {
            console.log('✅ Payments Response:', data);
            if (data.success) {
                displayPayments(data.payments);
            } else {
                console.error('❌ Payments Error:', data.message);
                displayPayments([]); // Show empty state
            }
        })
        .catch(error => {
            console.error('❌ Payments fetch error:', error);
            displayPayments([]); // Show empty state
        });
}

// Display Payments
function displayPayments(payments) {
    const tbody = document.getElementById('payments-body');
    
    if (!payments || payments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No payment records for this date</td></tr>';
        return;
    }
    
    tbody.innerHTML = payments.map(pmt => `
        <tr>
            <td>${formatDateTime(pmt.time_in)}</td>
            <td>${pmt.name}</td>
            <td>₱${formatCurrency(pmt.amount)}</td>
            <td>${pmt.payment_method || 'Cash'}</td>
            <td>${pmt.discount_type || 'Daily'}</td>
            <td><span class="status-badge status-${pmt.payment_status.toLowerCase()}">${pmt.payment_status}</span></td>
        </tr>
    `).join('');
}

// Load Classes Data
function loadClasses(dateStr) {
    console.log('🏋️ Loading classes...');
    fetch(`includes/daily_report_handler.php?action=get_classes&date=${dateStr}`)
        .then(response => response.json())
        .then(data => {
            console.log('✅ Classes Response:', data);
            if (data.success) {
                displayClasses(data.classes);
            } else {
                console.error('❌ Classes Error:', data.message);
                displayClasses([]); // Show empty state
            }
        })
        .catch(error => {
            console.error('❌ Classes fetch error:', error);
            displayClasses([]); // Show empty state
        });
}

// Display Classes
function displayClasses(classes) {
    const tbody = document.getElementById('classes-body');
    
    if (!classes || classes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No classes scheduled for this date</td></tr>';
        return;
    }
    
    tbody.innerHTML = classes.map(cls => `
        <tr>
            <td>${formatTime(cls.start_time)}</td>
            <td><strong>${cls.class_name}</strong></td>
            <td>${cls.coach_name}</td>
            <td>${cls.current_bookings || 0}</td>
            <td>${cls.max_capacity}</td>
            <td><span class="status-badge status-${cls.status.toLowerCase()}">${cls.status}</span></td>
        </tr>
    `).join('');
}

// Switch Tab
function switchTab(tabName) {
    console.log('🔄 Switching to tab:', tabName);
    
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(`${tabName}-tab`).classList.add('active');
    event.target.classList.add('active');
}

// Export Report
function exportReport() {
    console.log('📥 Exporting report...');
    showToast('PDF export feature coming soon', 'info');
}

// Utility Functions
function formatDate(date) {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

function formatDateForAPI(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatTime(timeStr) {
    if (!timeStr) return 'N/A';
    
    // Handle full datetime strings
    if (timeStr.includes(' ')) {
        timeStr = timeStr.split(' ')[1];
    }
    
    const parts = timeStr.split(':');
    if (parts.length < 2) return timeStr;
    
    const hours = parseInt(parts[0]);
    const minutes = parts[1];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const displayHour = hours % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function formatDateTime(dateTimeStr) {
    if (!dateTimeStr) return 'N/A';
    
    // If it's a full datetime, extract time
    if (dateTimeStr.includes(' ')) {
        return formatTime(dateTimeStr);
    }
    
    // If it's just a date, return date formatted
    const date = new Date(dateTimeStr);
    if (!isNaN(date.getTime())) {
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
    
    return dateTimeStr;
}

function formatCurrency(amount) {
    const num = parseFloat(amount);
    return isNaN(num) ? '0.00' : num.toFixed(2);
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    const toastIcon = toast.querySelector('.toast-icon');
    
    toastMessage.textContent = message;
    
    toastIcon.className = 'toast-icon fas';
    if (type === 'success') {
        toastIcon.classList.add('fa-check-circle');
    } else if (type === 'error') {
        toastIcon.classList.add('fa-times-circle');
    } else if (type === 'info') {
        toastIcon.classList.add('fa-info-circle');
    }
    
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Load POS Report
function loadPOSReport(dateStr) {
    console.log('💰 Loading POS report...');
    
    // Load POS transactions for the selected date
    fetch(`receptionist/includes/pos_handler.php?action=get_transactions&date=${dateStr}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.transactions) {
                displayPOSReport(data.transactions);
            } else {
                displayPOSReport([]);
            }
        })
        .catch(error => {
            console.error('❌ POS report error:', error);
            displayPOSReport([]);
        });
}

// Display POS Report
function displayPOSReport(transactions) {
    console.log('📊 Displaying POS report with', transactions.length, 'transactions');
    
    // Calculate summary stats
    let totalSales = 0;
    let cashTotal = 0;
    let sessionCount = new Set();
    
    transactions.forEach(trans => {
        totalSales += parseFloat(trans.amount || 0);
        if (trans.payment_method === 'Cash') {
            cashTotal += parseFloat(trans.amount || 0);
        }
        if (trans.session_id) {
            sessionCount.add(trans.session_id);
        }
    });
    
    // Update summary cards
    document.getElementById('pos-total-sales').textContent = totalSales.toFixed(2);
    document.getElementById('pos-cash-total').textContent = cashTotal.toFixed(2);
    document.getElementById('pos-transaction-count').textContent = transactions.length;
    document.getElementById('pos-session-count').textContent = sessionCount.size;
    
    // Populate transactions table
    const tbody = document.getElementById('pos-transactions-body');
    tbody.innerHTML = '';
    
    if (transactions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted">
                    <i class="fas fa-inbox"></i> No POS transactions found
                </td>
            </tr>
        `;
        return;
    }
    
    transactions.forEach(trans => {
        const createdAt = new Date(trans.created_at);
        const timeStr = createdAt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${timeStr}</td>
            <td>${trans.receipt_number || '-'}</td>
            <td>${trans.transaction_type || '-'}</td>
            <td>${trans.client_name || 'Guest'}</td>
            <td>₱${parseFloat(trans.amount || 0).toFixed(2)}</td>
            <td>${trans.payment_method || '-'}</td>
            <td>${trans.employee_name || '-'}</td>
        `;
        tbody.appendChild(row);
    });
}