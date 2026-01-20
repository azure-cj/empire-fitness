// Entry/Exit Management JavaScript - Enhanced

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadCurrentlyInside();
    loadTodayLog();
    setupEventListeners();
    initializeDateFilters();
    updateCurrentTime();
    
    // Auto-refresh every 30 seconds
    setInterval(() => {
        loadStats();
        loadCurrentlyInside();
        loadTodayLog();
    }, 30000);
    
    // Update time every second
    setInterval(updateCurrentTime, 1000);
});

// Initialize date filters with default values
function initializeDateFilters() {
    const startInput = document.getElementById('log-start-date');
    const endInput = document.getElementById('log-end-date');
    
    if (startInput && endInput) {
        const today = new Date();
        const sevenDaysAgo = new Date(today.getTime() - (7 * 24 * 60 * 60 * 1000));
        
        // Format dates as YYYY-MM-DD
        const formatDate = (date) => date.toISOString().split('T')[0];
        
        startInput.value = formatDate(sevenDaysAgo);
        endInput.value = formatDate(today);
    }
}

// Update current time display
function updateCurrentTime() {
    const timeElement = document.getElementById('current-time');
    if (timeElement) {
        const now = new Date();
        const options = { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: true 
        };
        timeElement.textContent = now.toLocaleTimeString('en-US', options);
    }
}

// Setup event listeners
function setupEventListeners() {
    // Check-in on Enter key
    const checkinInput = document.getElementById('checkin-id');
    if (checkinInput) {
        checkinInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                processCheckIn();
            }
        });
        
        // Auto-focus on check-in input
        checkinInput.focus();
    }
    
    // Check-out on Enter key
    const checkoutInput = document.getElementById('checkout-id');
    if (checkoutInput) {
        checkoutInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                processCheckOut();
            }
        });
    }
    
    // Update payment amount when discount type changes
    const discountSelect = document.getElementById('discount-type');
    if (discountSelect) {
        discountSelect.addEventListener('change', updatePaymentAmount);
        updatePaymentAmount(); // Initial load
    }
    
    // Phone number validation - only allow digits and max 11
    const phoneInput = document.getElementById('guest-phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            // Remove non-numeric characters
            this.value = this.value.replace(/[^\d]/g, '');
            // Limit to 11 digits
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
        });
    }
    
    // Close modal on outside click
    const modal = document.getElementById('walkin-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeWalkInModal();
            }
        });
    }
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeWalkInModal();
        }
    });
}

// Load statistics with animation
async function loadStats() {
    try {
        const response = await fetch('includes/entry_exit_handler.php?action=get_stats');
        const data = await response.json();
        
        if (data.success) {
            animateValue('currently-inside', 0, data.stats.currently_inside || 0, 800);
            animateValue('today-checkins', 0, data.stats.today_checkins || 0, 800);
            animateValue('members-count', 0, data.stats.members_today || 0, 800);
            animateValue('walkins-count', 0, data.stats.walkins_today || 0, 800);
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Animate number counting
function animateValue(id, start, end, duration) {
    const element = document.getElementById(id);
    if (!element) return;
    
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            element.textContent = Math.round(end);
            clearInterval(timer);
        } else {
            element.textContent = Math.round(current);
        }
    }, 16);
}

// Load currently inside members
async function loadCurrentlyInside() {
    try {
        const response = await fetch('includes/entry_exit_handler.php?action=get_currently_inside');
        const data = await response.json();
        
        const container = document.getElementById('currently-inside-grid');
        
        if (data.success && data.people.length > 0) {
            container.innerHTML = data.people.map(person => `
                <div class="person-card" style="animation: slideInUp 0.5s ease">
                    <div class="person-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="person-info">
                        <div class="person-name">${escapeHtml(person.name)}</div>
                        <div class="person-type">
                            <i class="fas ${person.client_type === 'Member' ? 'fa-id-card' : 'fa-walking'}"></i>
                            ${person.client_type}
                        </div>
                        <div class="person-time">
                            <i class="fas fa-clock"></i>
                            ${person.time_in} (${person.duration})
                        </div>
                    </div>
                    <div class="person-actions">
                        <button class="btn-quick-checkout" onclick="quickCheckOut('${person.client_id || person.attendance_id}')">
                            <i class="fas fa-sign-out-alt"></i> Check Out
                        </button>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="fas fa-inbox"></i>
                    <p>No one is currently inside</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading currently inside:', error);
    }
}

// Load today's log
async function loadTodayLog() {
    try {
        const response = await fetch('includes/entry_exit_handler.php?action=get_today_log');
        const data = await response.json();
        
        const tbody = document.getElementById('log-table-body');
        
        if (data.success && data.logs.length > 0) {
            tbody.innerHTML = data.logs.map(log => `
                <tr>
                    <td><strong>${log.client_id || log.attendance_id}</strong></td>
                    <td>
                        <strong>${escapeHtml(log.name)}</strong>
                        ${log.guest_name ? `<br><small style="color: #6c757d;">Guest: ${escapeHtml(log.guest_name)}</small>` : ''}
                    </td>
                    <td>
                        <span class="badge ${log.client_type === 'Member' ? 'badge-info' : 'badge-secondary'}">
                            ${log.client_type}
                        </span>
                    </td>
                    <td>${log.time_in}</td>
                    <td>${log.time_out || '<span class="badge badge-warning">Inside</span>'}</td>
                    <td>${log.duration || '-'}</td>
                    <td>
                        <span class="badge ${log.time_out ? 'badge-success' : 'badge-warning'}">
                            ${log.time_out ? 'Completed' : 'In Progress'}
                        </span>
                    </td>
                    <td>
                        ${!log.time_out ? `
                            <button class="btn btn-sm btn-danger" onclick="quickCheckOut('${log.client_id || log.attendance_id}')">
                                <i class="fas fa-sign-out-alt"></i> Check Out
                            </button>
                        ` : '-'}
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="empty-state">
                            <i class="fas fa-clipboard"></i>
                            <p>No entries yet today</p>
                        </div>
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Error loading today log:', error);
    }
}

// Process check-in with loading state
async function processCheckIn() {
    const input = document.getElementById('checkin-id');
    const button = document.querySelector('.btn-checkin');
    const identifier = input.value.trim();
    
    if (!identifier) {
        showToast('Please enter a member ID or scan QR code', 'warning');
        input.focus();
        return;
    }
    
    if (!button) {
        showToast('Button element not found. Please refresh the page.', 'error');
        return;
    }
    
    // Show loading state
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="loading"></span> Processing...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'check_in');
        formData.append('identifier', identifier);
        
        const response = await fetch('includes/entry_exit_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            input.value = '';
            input.focus();
            
            // Refresh data
            loadStats();
            loadCurrentlyInside();
            loadTodayLog();
        } else {
            showToast(data.message, 'error');
            
            // Handle special cases
            if (data.error_type === 'walk_in') {
                setTimeout(() => {
                    if (confirm('Walk-in guest detected. Process daily payment now?')) {
                        window.location.href = `process_payment.php?client_id=${data.member.client_id}&type=daily`;
                    }
                }, 500);
            }
        }
    } catch (error) {
        console.error('Error processing check-in:', error);
        showToast('System error. Please try again.', 'error');
    } finally {
        button.disabled = false;
        button.innerHTML = originalHTML;
    }
}

// Process check-out with loading state
async function processCheckOut() {
    const input = document.getElementById('checkout-id');
    const button = document.querySelector('.btn-checkout');
    const identifier = input.value.trim();
    
    if (!identifier) {
        showToast('Please enter an ID or scan QR code', 'warning');
        input.focus();
        return;
    }
    
    if (!button) {
        showToast('Button element not found. Please refresh the page.', 'error');
        return;
    }
    
    // Show loading state
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="loading"></span> Processing...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'check_out');
        formData.append('identifier', identifier);
        
        const response = await fetch('includes/entry_exit_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            input.value = '';
            
            // Refresh data
            loadStats();
            loadCurrentlyInside();
            loadTodayLog();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error processing check-out:', error);
        showToast('System error. Please try again.', 'error');
    } finally {
        button.disabled = false;
        button.innerHTML = originalHTML;
    }
}

// Quick check-out from currently inside list
async function quickCheckOut(identifier) {
    const result = await showConfirm('Are you sure you want to check out this person?');
    
    if (!result) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'check_out');
        formData.append('identifier', identifier);
        
        const response = await fetch('includes/entry_exit_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            
            // Refresh data
            loadStats();
            loadCurrentlyInside();
            loadTodayLog();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error processing quick check-out:', error);
        showToast('System error. Please try again.', 'error');
    }
}

// Show walk-in modal with animation
// Page data helpers
function refreshCurrentlyInside() {
    loadCurrentlyInside();
    showToast('Refreshed currently inside', 'info');
}

function refreshLog() {
    loadTodayLog();
    showToast('Refreshed today\'s log', 'info');
}

// Export log (placeholder)
function exportLog() {
    showToast('Export functionality coming soon', 'info');
}

// Enhanced toast notification
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    const icon = toast.querySelector('.toast-icon');
    const messageEl = toast.querySelector('.toast-message');
    
    // Set icon based on type
    const icons = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle'
    };
    
    icon.className = `toast-icon ${icons[type] || icons.info}`;
    messageEl.textContent = message;
    
    // Remove previous type classes
    toast.classList.remove('success', 'error', 'warning', 'info', 'show');
    
    // Force reflow to restart animation
    void toast.offsetWidth;
    
    // Add new type and show
    toast.classList.add(type, 'show');
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        toast.classList.remove('show');
    }, 5000);
}

// Custom confirm dialog (prettier than default)
async function showConfirm(message) {
    return new Promise((resolve) => {
        const result = confirm(message);
        resolve(result);
    });
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

// Date Filter Functions
function filterLogByDate() {
    const startDate = document.getElementById('log-start-date').value;
    const endDate = document.getElementById('log-end-date').value;
    
    if (!startDate || !endDate) {
        showAlert('Please select both start and end dates', 'warning');
        return;
    }
    
    if (startDate > endDate) {
        showAlert('Start date cannot be after end date', 'warning');
        return;
    }
    
    loadLogByDateRange(startDate, endDate);
}

async function loadLogByDateRange(startDate, endDate) {
    try {
        const response = await fetch(`includes/entry_exit_handler.php?action=get_log_by_date&start_date=${startDate}&end_date=${endDate}`);
        const data = await response.json();
        
        const tbody = document.getElementById('log-table-body');
        
        if (data.success && data.logs.length > 0) {
            tbody.innerHTML = data.logs.map(log => `
                <tr>
                    <td><strong>${log.client_id || log.attendance_id}</strong></td>
                    <td>
                        <strong>${escapeHtml(log.name)}</strong>
                        ${log.guest_name ? `<br><small style="color: #6c757d;">Guest: ${escapeHtml(log.guest_name)}</small>` : ''}
                    </td>
                    <td>
                        <span class="badge ${log.client_type === 'Member' ? 'badge-info' : 'badge-secondary'}">
                            ${log.client_type}
                        </span>
                    </td>
                    <td>${log.time_in}</td>
                    <td>${log.time_out || '<span class="badge badge-warning">Inside</span>'}</td>
                    <td>${log.duration || '-'}</td>
                    <td>
                        <span class="badge ${log.time_out ? 'badge-success' : 'badge-warning'}">
                            ${log.time_out ? 'Completed' : 'In Progress'}
                        </span>
                    </td>
                    <td>
                        ${!log.time_out ? `
                            <button class="btn btn-sm btn-danger" onclick="quickCheckOut('${log.client_id || log.attendance_id}')">
                                <i class="fas fa-sign-out-alt"></i> Check Out
                            </button>
                        ` : '-'}
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="empty-state">
                            <i class="fas fa-clipboard"></i>
                            <p>No entries found for the selected date range</p>
                        </div>
                    </td>
                </tr>
            `;
        }
        showAlert('Log filtered successfully', 'success');
    } catch (error) {
        console.error('Error loading filtered log:', error);
        showAlert('Failed to load filtered log', 'error');
    }
}

function resetLogFilter() {
    document.getElementById('log-start-date').value = '';
    document.getElementById('log-end-date').value = '';
    loadTodayLog();
    showAlert('Filter reset. Showing today\'s entries', 'info');
}

// Check if active POS session exists
async function checkActivePOSSession() {
    try {
        const response = await fetch('../receptionist/includes/pos_handler.php?action=get_current_session');
        const data = await response.json();
        return data.success && data.session && data.session.session_id;
    } catch (error) {
        console.error('Error checking POS session:', error);
        return false;
    }
}

// Handle member check-in with POS session check
async function handleMemberCheckIn() {
    const hasActiveSession = await checkActivePOSSession();
    
    if (!hasActiveSession) {
        // Show POS session required modal
        document.getElementById('no-pos-session-modal').classList.add('active');
        return;
    }
    
    // Proceed with normal check-in if session exists
    processCheckIn();
}

// Show walk-in modal with POS session check
async function showWalkInModal() {
    const hasActiveSession = await checkActivePOSSession();
    
    if (!hasActiveSession) {
        // Show POS session required modal
        document.getElementById('no-pos-session-modal').classList.add('active');
        return;
    }
    
    // Show walk-in modal if session exists
    document.getElementById('walkin-modal').classList.add('active');
}

// Show member walk-in modal with POS session check
async function showMemberIDModal() {
    const hasActiveSession = await checkActivePOSSession();
    
    if (!hasActiveSession) {
        // Show POS session required modal
        document.getElementById('no-pos-session-modal').classList.add('active');
        return;
    }
    
    // Show member walk-in modal if session exists
    document.getElementById('member-walkin-modal').classList.add('active');
}

// Close no POS session modal
function closeNoPOSModal() {
    const modal = document.getElementById('no-pos-session-modal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Redirect to Point of Sale
function goToPointOfSale() {
    window.location.href = 'pos.php';
}

// Add slide up animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);

// QR Scanner State Management
let qrScannerActive = {
    checkin: false,
    checkout: false
};
let qrVideoStreams = {
    checkin: null,
    checkout: null
};
let qrAnimationFrames = {
    checkin: null,
    checkout: null
};

/**
 * QR Scanner instances - Using html5-qrcode (10x faster than jsQR)
 */
let html5QrCodeScanners = {
    checkin: null,
    checkout: null
};

/**
 * Start QR Scanner - Optimized with html5-qrcode
 */
async function startQRScanner(type) {
    const readerId = `qr-reader-${type}`;
    const statusId = `qr-status-${type}`;
    const startBtn = document.getElementById(`btn-start-scanner-${type}`);
    const stopBtn = document.getElementById(`btn-stop-scanner-${type}`);
    
    try {
        // Show scanner container
        document.getElementById(readerId).style.display = 'block';
        showScannerStatus(type, 'Initializing camera...', 'info');
        
        // Initialize scanner if not already created
        if (!html5QrCodeScanners[type]) {
            html5QrCodeScanners[type] = new Html5Qrcode(readerId);
        }
        
        // Configuration for faster scanning
        const config = {
            fps: 30,  // High FPS for fast detection
            qrbox: { width: 250, height: 250 },  // Smaller scan area = faster
            aspectRatio: 1.0,
            formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE]  // Only QR codes
        };
        
        // Start scanning
        await html5QrCodeScanners[type].start(
            { facingMode: "environment" },  // Use back camera on mobile
            config,
            onQRCodeScanned.bind(null, type),  // Success callback
            onScanError.bind(null, type)  // Error callback
        );
        
        // Update UI
        startBtn.style.display = 'none';
        stopBtn.style.display = 'block';
        showScannerStatus(type, '📷 Scanner active - Point at QR code', 'success');
        
    } catch (error) {
        console.error('Error starting scanner:', error);
        showScannerStatus(type, '❌ Camera access denied. Check permissions.', 'error');
        document.getElementById(readerId).style.display = 'none';
    }
}

/**
 * QR Code Successfully Scanned
 */
async function onQRCodeScanned(type, decodedText, decodedResult) {
    console.log(`✅ QR Code detected: ${decodedText}`);
    
    // Show success status
    showScannerStatus(type, '✓ QR Code detected! Processing...', 'success');
    
    // Stop scanner immediately after successful scan
    await stopQRScanner(type);
    
    // Process the QR code
    await verifyAndProcessQR(decodedText, type);
}

/**
 * Scanner Error Handler
 */
function onScanError(type, errorMessage) {
    // Ignore routine scan errors (no QR in frame)
    // Only log actual errors
    if (!errorMessage.includes('No MultiFormat Readers')) {
        console.debug('Scan error:', errorMessage);
    }
}

/**
 * Stop QR Scanner
 */
async function stopQRScanner(type) {
    const readerId = `qr-reader-${type}`;
    const startBtn = document.getElementById(`btn-start-scanner-${type}`);
    const stopBtn = document.getElementById(`btn-stop-scanner-${type}`);
    
    try {
        if (html5QrCodeScanners[type] && html5QrCodeScanners[type].isScanning) {
            await html5QrCodeScanners[type].stop();
        }
        
        // Update UI
        document.getElementById(readerId).style.display = 'none';
        startBtn.style.display = 'block';
        stopBtn.style.display = 'none';
        
        // Hide status
        const statusEl = document.getElementById(`qr-status-${type}`);
        if (statusEl) statusEl.style.display = 'none';
        
    } catch (error) {
        console.error('Error stopping scanner:', error);
    }
}

/**
 * Show Scanner Status Message
 */
function showScannerStatus(type, message, status = 'info') {
    const statusEl = document.getElementById(`qr-status-${type}`);
    if (!statusEl) return;
    
    statusEl.style.display = 'block';
    statusEl.textContent = message;
    
    // Color coding
    const colors = {
        info: '#667eea',
        success: '#27ae60',
        error: '#ef5350',
        warning: '#f39c12'
    };
    
    statusEl.style.background = colors[status] + '20';
    statusEl.style.color = colors[status];
    statusEl.style.border = `1px solid ${colors[status]}`;
}

/**
 * Verify QR Code and Process Check-in/out
 */
async function verifyAndProcessQR(qrCodeHash, type) {
    try {
        showScannerStatus(type, '🔄 Verifying QR code...', 'info');
        
        const formData = new FormData();
        formData.append('action', 'verify_qr_code');
        formData.append('qr_code_hash', qrCodeHash);
        
        const response = await fetch('includes/entry_exit_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && data.client_id) {
            showScannerStatus(type, '✓ Valid member! Processing...', 'success');
            
            // Set the client_id and trigger check-in or check-out
            if (type === 'checkin') {
                document.getElementById('checkin-id').value = data.client_id;
                await processCheckIn();
            } else if (type === 'checkout') {
                document.getElementById('checkout-id').value = data.client_id;
                await processCheckOut();
            }
        } else {
            showScannerStatus(type, '❌ ' + (data.message || 'Invalid QR code'), 'error');
            
            // Auto-restart scanner after 2 seconds
            setTimeout(() => {
                startQRScanner(type);
            }, 2000);
        }
        
    } catch (error) {
        console.error('Error verifying QR code:', error);
        showScannerStatus(type, '❌ Error processing QR code', 'error');
        
        // Auto-restart scanner after 2 seconds
        setTimeout(() => {
            startQRScanner(type);
        }, 2000);
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', async function() {
    await stopQRScanner('checkin');
    await stopQRScanner('checkout');
});