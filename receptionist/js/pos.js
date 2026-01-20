/**
 * POS System JavaScript
 * Handles all client-side POS operations
 */

let currentSession = null;
let selectedPaymentMethod = 'Cash';
let allTransactions = [];
let clientSearchTimeout;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCurrentSession();
    setupEventListeners();
    loadTransactions();
    loadLastSessions();
    setInterval(loadTransactions, 5000); // Refresh transactions every 5 seconds
    setInterval(loadLastSessions, 60000); // Refresh last sessions every 60 seconds
    loadMembershipPlans();
});

// Setup event listeners
function setupEventListeners() {
    // Client search
    document.getElementById('clientSearch')?.addEventListener('input', function() {
        clearTimeout(clientSearchTimeout);
        if (this.value.length >= 2) {
            clientSearchTimeout = setTimeout(() => searchClients(this.value), 300);
        } else {
            document.getElementById('clientResults').classList.remove('show');
        }
    });

    // Amount input formatting
    document.getElementById('amount')?.addEventListener('change', function() {
        if (this.value) {
            this.value = parseFloat(this.value).toFixed(2);
        }
    });

    // Show membership plan selector when relevant transaction type selected
    document.getElementById('transactionType')?.addEventListener('change', function() {
        const val = this.value;
        const group = document.getElementById('membershipPlanGroup');
        if (val === 'Membership' || val === 'Membership Renewal') {
            if (group) group.style.display = 'block';
        } else {
            if (group) group.style.display = 'none';
        }
    });

    // Update amount/info when membership plan selected
    document.getElementById('membershipPlan')?.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const info = document.getElementById('membershipPlanInfo');
        if (opt && opt.dataset && opt.dataset.price) {
            document.getElementById('amount').value = parseFloat(opt.dataset.price).toFixed(2);
            info.textContent = `Duration: ${opt.dataset.duration || 'N/A'} days • Base: ${opt.dataset.base === '1' ? 'Yes' : 'No'}`;
            info.style.display = 'block';
        } else {
            info.style.display = 'none';
        }
    });

    // Sidebar toggle
    document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('sidebar')?.classList.toggle('hidden');
    });
}

// Load current active session
async function loadCurrentSession() {
    try {
        const response = await fetch('includes/pos_handler.php?action=get_current_session');
        const data = await response.json();

        if (data.success && data.session) {
            currentSession = data.session;
            // Store session ID in data attribute
            document.documentElement.dataset.sessionId = data.session.session_id;
            updateSessionUI(true);
            loadSummary();
        } else {
            document.documentElement.dataset.sessionId = '';
            updateSessionUI(false);
        }
    } catch (error) {
        console.error('Error loading session:', error);
        showAlert('Error loading session', 'danger');
    }
}

// Start a new POS session
async function startSession() {
    const openingBalance = document.getElementById('openingBalance').value || 0;

    if (!openingBalance || openingBalance < 0) {
        showAlert('Please enter a valid opening balance', 'danger');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'start_session');
        formData.append('opening_balance', openingBalance);

        const response = await fetch('includes/pos_handler.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showAlert('POS Session started successfully', 'success');
            document.getElementById('openingBalance').value = '';
            // Store session ID in data attribute
            if (data.session_id) {
                document.documentElement.dataset.sessionId = data.session_id;
            }
            loadCurrentSession();
            setTimeout(() => {
                document.getElementById('sessionControls').style.display = 'none';
                document.getElementById('transactionForm').style.display = 'block';
            }, 500);
        } else {
            showAlert(data.message || 'Error starting session', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error starting session', 'danger');
    }
}

// Update UI based on session status
function updateSessionUI(isActive) {
    const statusEl = document.getElementById('sessionStatus');
    const statusText = document.getElementById('sessionStatusText');

    if (isActive && currentSession) {
        statusEl.classList.remove('inactive');
        statusText.textContent = `Session Active - ${new Date(currentSession.start_time).toLocaleTimeString()}`;
        document.getElementById('sessionControls').style.display = 'none';
        document.getElementById('transactionForm').style.display = 'block';
    } else {
        statusEl.classList.add('inactive');
        statusText.textContent = 'No Active Session';
        document.getElementById('sessionControls').style.display = 'block';
        document.getElementById('transactionForm').style.display = 'none';
    }
}

// Select payment method
function selectPaymentMethod(method) {
    selectedPaymentMethod = method;
    document.getElementById('paymentMethod').value = method;

    document.querySelectorAll('.payment-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.closest('.payment-btn').classList.add('active');
}

// Add a new transaction
async function addTransaction() {
    const transactionType = document.getElementById('transactionType').value;
    const amount = parseFloat(document.getElementById('amount').value);
    const description = document.getElementById('description').value;
    const clientId = document.getElementById('clientId').value || null;
    const clientName = document.getElementById('clientSearch').value;

    // Validation
    if (!transactionType) {
        showAlert('Please select a transaction type', 'danger');
        return;
    }

    if (!amount || amount <= 0) {
        showAlert('Please enter a valid amount', 'danger');
        return;
    }

    if (!selectedPaymentMethod) {
        showAlert('Please select a payment method', 'danger');
        return;
    }

    try {
        // If this is a membership-related transaction and a client is selected, use membership flow
        if ((transactionType === 'Membership' || transactionType === 'Membership Renewal') && clientId) {
            await processMembership({
                transactionType,
                clientId,
                clientName,
                amount: amount.toFixed(2),
                paymentMethod: selectedPaymentMethod,
                description
            });
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add_transaction');
        formData.append('transaction_type', transactionType);
        formData.append('amount', amount.toFixed(2));
        formData.append('payment_method', selectedPaymentMethod);
        formData.append('description', description);
        if (clientId) formData.append('client_id', clientId);
        if (clientName) formData.append('client_name', clientName);

        const response = await fetch('includes/pos_handler.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showAlert(`Transaction added - Receipt: ${data.receipt_number}`, 'success');
            
            // Reset form
            document.getElementById('transactionType').value = '';
            document.getElementById('description').value = '';
            document.getElementById('amount').value = '';
            document.getElementById('clientSearch').value = '';
            document.getElementById('clientId').value = '';
            selectedPaymentMethod = 'Cash';
            updatePaymentMethodUI();

            // Reload data
            loadTransactions();
            loadSummary();
        } else {
            showAlert(data.message || 'Error adding transaction', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error adding transaction', 'danger');
    }
}

// Process membership purchase/renewal
async function processMembership(payload) {
    try {
        const formData = new FormData();
        formData.append('action', 'process_membership');
        formData.append('client_id', payload.clientId);
        // prefer explicit membership plan selection
        const selectedPlan = document.getElementById('membershipPlan')?.value;
        formData.append('membership_plan', selectedPlan || payload.description || 'monthly');
        formData.append('amount', payload.amount);
        formData.append('payment_method', payload.paymentMethod);
        formData.append('transaction_type', payload.transactionType);

        const response = await fetch('includes/pos_handler.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showAlert(data.message || 'Membership processed successfully', 'success');
            // Reset form
            document.getElementById('transactionType').value = '';
            document.getElementById('description').value = '';
            document.getElementById('amount').value = '';
            document.getElementById('clientSearch').value = '';
            document.getElementById('clientId').value = '';
            selectedPaymentMethod = 'Cash';
            updatePaymentMethodUI();

            loadTransactions();
            loadSummary();
            refreshCurrentlyInside();
            refreshLog();
        } else {
            // If base membership missing, show conversion modal
            if (data.message && data.message.toLowerCase().includes('no base membership')) {
                // Populate modal with details
                const modal = document.getElementById('renewalConfirmModal');
                document.getElementById('renewalConfirmMessage').textContent = data.message;
                document.getElementById('renewalClientId').value = payload.clientId;
                document.getElementById('renewalMembershipId').value = document.getElementById('membershipPlan')?.value || '';
                document.getElementById('renewalAmount').value = payload.amount;
                document.getElementById('renewalPaymentMethod').value = payload.paymentMethod;
                modal.classList.add('show');
            } else {
                showAlert(data.message || 'Error processing membership', 'danger');
            }
        }
    } catch (err) {
        console.error('Error processing membership:', err);
        showAlert('Error processing membership', 'danger');
    }
}

// Load transactions for current session
async function loadTransactions() {
    try {
        const response = await fetch('includes/pos_handler.php?action=get_transactions');
        const data = await response.json();

        if (data.success) {
            allTransactions = data.transactions || [];
            renderTransactions();
        }
    } catch (error) {
        console.error('Error loading transactions:', error);
    }
}

// Render transactions list
function renderTransactions() {
    const container = document.getElementById('transactionsList');

    if (allTransactions.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="padding: 20px;">
                <i class="fas fa-receipt"></i>
                <p>No transactions yet</p>
            </div>
        `;
        return;
    }

    const html = allTransactions.map(trans => `
        <div class="transaction-item" onclick="showTransactionDetails(${trans.transaction_id})" style="cursor:pointer;" data-transaction-id="${trans.transaction_id}">
            <div class="transaction-info">
                <div class="transaction-type">${escapeHtml(trans.transaction_type)}</div>
                <div class="transaction-meta">
                    ${trans.client_name ? `${escapeHtml(trans.client_name)} - ` : ''}
                    ${escapeHtml(trans.description || 'N/A')}
                </div>
                <div class="transaction-meta">
                    <i class="fas fa-user-circle"></i> ${escapeHtml(trans.employee_name || 'N/A')} • ${escapeHtml(trans.payment_method)} • ${formatTime(trans.created_at)}
                </div>
            </div>
            <div class="transaction-amount">₱${parseFloat(trans.amount).toFixed(2)}</div>
        </div>
    `).join('');

    container.innerHTML = html;
}

// Load last sessions
async function loadLastSessions() {
    try {
        const response = await fetch('includes/pos_handler.php?action=get_last_sessions&limit=5');
        const data = await response.json();

        if (data.success) {
            renderLastSessions(data.sessions || []);
        }
    } catch (error) {
        console.error('Error loading last sessions:', error);
    }
}

// Render last sessions table
function renderLastSessions(sessions) {
    const container = document.getElementById('lastSessions');

    if (!sessions || sessions.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="padding: 20px;">
                <i class="fas fa-history"></i>
                <p>No previous sessions</p>
            </div>
        `;
        return;
    }

    const html = `
        <table class="sessions-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Receptionist</th>
                    <th>Transactions</th>
                    <th>Total Sales</th>
                    <th>Cash</th>
                    <th>Digital</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                ${sessions.map(session => {
                    const endTime = session.end_time ? new Date(session.end_time) : null;
                    const sessionDate = endTime ? endTime.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-';
                    const sessionTime = endTime ? endTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '-';
                    
                    const totalSales = parseFloat(session.total_sales || 0);
                    const totalCash = parseFloat(session.total_cash || 0);
                    const totalDigital = parseFloat(session.total_digital || 0);
                    const balanceDiff = parseFloat(session.balance_diff || 0);
                    const balanceClass = balanceDiff >= 0 ? 'positive' : 'negative';
                    
                    return `
                        <tr>
                            <td>
                                <div>${sessionDate}</div>
                                <div class="session-time">${sessionTime}</div>
                            </td>
                            <td>${escapeHtml(session.employee_name)}</td>
                            <td>${session.transaction_count || 0}</td>
                            <td class="session-amount">₱${totalSales.toFixed(2)}</td>
                            <td style="color: #27ae60; font-weight: 600;">₱${totalCash.toFixed(2)}</td>
                            <td style="color: #3498db; font-weight: 600;">₱${totalDigital.toFixed(2)}</td>
                            <td class="session-amount ${balanceClass}">₱${balanceDiff.toFixed(2)}</td>
                        </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
    `;

    container.innerHTML = html;
}

// Load session summary
async function loadSummary() {
    if (!currentSession) return;

    try {
        const response = await fetch('includes/pos_handler.php?action=get_session_summary');
        const data = await response.json();

        if (data.success && data.summary) {
            renderSummary(data.summary);
        }
    } catch (error) {
        console.error('Error loading summary:', error);
    }
}

// Render summary cards
function renderSummary(summary) {
    const container = document.getElementById('summaryCards');

    const totalSales = parseFloat(summary.total_sales || 0);
    const cashTotal = parseFloat(summary.cash_total || 0);
    const transactionCount = parseInt(summary.transaction_count || 0);

    container.innerHTML = `
        <div class="summary-card">
            <div class="summary-label">Earnings for Today</div>
            <div class="summary-value">₱${totalSales.toFixed(2)}</div>
        </div>

        <div class="summary-card">
            <div class="summary-label">Cash</div>
            <div class="summary-value" style="color: #27ae60;">₱${cashTotal.toFixed(2)}</div>
        </div>

        <div class="summary-card">
            <div class="summary-label">Transactions</div>
            <div class="summary-value" style="color: #9b59b6;">${transactionCount}</div>
        </div>

        <div class="summary-card" style="border-left-color: #27ae60;">
            <div class="summary-label">Session Time</div>
            <div class="summary-value" style="font-size: 16px; color: #2c3e50;">
                ${calculateSessionTime(summary.start_time)}
            </div>
        </div>
    `;
}

// Load membership plans for selector
async function loadMembershipPlans() {
    try {
        const res = await fetch('includes/pos_handler.php?action=get_memberships');
        const data = await res.json();
        if (data.success && data.memberships) {
            const select = document.getElementById('membershipPlan');
            data.memberships.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.membership_id;
                opt.textContent = `${m.plan_name} (₱${parseFloat(m.monthly_fee||m.renewal_fee||0).toFixed(2)})`;
                opt.dataset.price = m.monthly_fee || m.renewal_fee || 0;
                opt.dataset.duration = m.duration_days || '';
                opt.dataset.base = m.is_base_membership ? '1' : '0';
                select.appendChild(opt);
            });
        }
    } catch (err) {
        console.error('Error loading memberships:', err);
    }
}

// Show transaction details modal
async function showTransactionDetails(transactionId) {
    try {
        const res = await fetch(`includes/pos_handler.php?action=get_transaction&transaction_id=${transactionId}`);
        const data = await res.json();
        if (!data.success) {
            showAlert(data.message || 'Transaction not found', 'danger');
            return;
        }

        const t = data.detail.transaction;
        const modal = document.getElementById('transactionDetailModal');
        document.getElementById('td-transaction-type').textContent = t.transaction_type;
        document.getElementById('td-client-name').textContent = t.client_name || 'Guest';
        document.getElementById('td-amount').textContent = '₱' + parseFloat(t.amount).toFixed(2);
        document.getElementById('td-payment-method').textContent = t.payment_method || '-';
        document.getElementById('td-description').textContent = t.description || '-';
        document.getElementById('td-receipt').textContent = t.receipt_number || '-';

        const linked = document.getElementById('td-linked');
        linked.innerHTML = '';
        if (data.detail.payment) {
            linked.innerHTML += `<div><strong>Payment ID:</strong> ${data.detail.payment.payment_id} • ₱${parseFloat(data.detail.payment.amount).toFixed(2)}</div>`;
        }
        if (data.detail.client_membership) {
            linked.innerHTML += `<div><strong>Membership Record ID:</strong> ${data.detail.client_membership.id} • Expires: ${data.detail.client_membership.end_date}</div>`;
        }

        modal.classList.add('show');
    } catch (err) {
        console.error('Error fetching transaction details:', err);
        showAlert('Error fetching transaction details', 'danger');
    }
}

// Convert renewal attempt to a base membership purchase
function confirmConvertToBase() {
    const clientId = document.getElementById('renewalClientId').value;
    const membershipId = document.getElementById('renewalMembershipId').value;
    const amount = document.getElementById('renewalAmount').value;
    const paymentMethod = document.getElementById('renewalPaymentMethod').value || 'Cash';

    document.getElementById('renewalConfirmModal').classList.remove('show');

    processMembership({
        transactionType: 'Membership',
        clientId: clientId,
        clientName: '',
        amount: amount,
        paymentMethod: paymentMethod,
        description: membershipId
    });
}

// Open end session modal
function endSessionModal() {
    loadSessionVerification();
    document.getElementById('endSessionModal').classList.add('show');
}

// Close end session modal
function closeEndSessionModal() {
    document.getElementById('endSessionModal').classList.remove('show');
}

// Load session verification details
async function loadSessionVerification() {
    if (!currentSession) return;

    try {
        const response = await fetch('includes/pos_handler.php?action=get_session_summary');
        const data = await response.json();

        if (data.success && data.summary) {
            const summary = data.summary;
            const totalSales = parseFloat(summary.total_sales || 0);
            const cashTotal = parseFloat(summary.cash_total || 0);
            const transactionCount = parseInt(summary.transaction_count || 0);

            const html = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Total Sales</div>
                        <div style="font-size: 18px; font-weight: 700; color: #2c3e50;">₱${totalSales.toFixed(2)}</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Cash Expected</div>
                        <div style="font-size: 18px; font-weight: 700; color: #27ae60;">₱${(parseFloat(currentSession.opening_balance) + cashTotal).toFixed(2)}</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Transactions</div>
                        <div style="font-size: 18px; font-weight: 700; color: #2c3e50;">${transactionCount}</div>
                    </div>
                </div>
            `;
            document.getElementById('sessionVerification').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading verification:', error);
    }
}

// Confirm end session
async function confirmEndSession() {
    const closingBalance = document.getElementById('closingBalance').value;
    const notes = document.getElementById('sessionNotes').value;

    if (!closingBalance || closingBalance < 0) {
        showAlert('Please enter a valid closing balance', 'danger');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'end_session');
        formData.append('closing_balance', closingBalance);
        formData.append('notes', notes);

        const response = await fetch('includes/pos_handler.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            // Save report
            await saveSessionReport(data.session_id);
            
            showAlert('POS session ended successfully', 'success');
            closeEndSessionModal();
            currentSession = null;
            updateSessionUI(false);
            
            // Reset form
            document.getElementById('closingBalance').value = '';
            document.getElementById('sessionNotes').value = '';
            
            // Auto-refresh after 2 seconds
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showAlert(data.message || 'Error ending session', 'danger');
            // Still reload after delay to refresh state, even on error
            setTimeout(() => {
                window.location.reload();
            }, 3000);
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error ending session', 'danger');
        // Still reload after delay to refresh state
        setTimeout(() => {
            window.location.reload();
        }, 3000);
    }
}

// Save session report
async function saveSessionReport(sessionId) {
    try {
        const formData = new FormData();
        formData.append('action', 'save_report');
        formData.append('session_id', sessionId);

        const response = await fetch('includes/pos_handler.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            console.log('Report saved:', data.report);
            // Show report option
            setTimeout(() => {
                showAlert('📊 Report ready! Open your reports to view details.', 'success');
            }, 1000);
        }
    } catch (error) {
        console.error('Error saving report:', error);
    }
}

// Search clients
async function searchClients(query) {
    if (query.length < 2) return;

    try {
        const response = await fetch(`includes/pos_handler.php?action=search_client&q=${encodeURIComponent(query)}`);
        const data = await response.json();

        if (data.success && data.clients.length > 0) {
            renderClientResults(data.clients);
        } else {
            document.getElementById('clientResults').innerHTML = `
                <div style="padding: 10px; text-align: center; color: #6c757d;">
                    No clients found
                </div>
            `;
            document.getElementById('clientResults').classList.add('show');
        }
    } catch (error) {
        console.error('Error searching clients:', error);
    }
}

// Render client search results
function renderClientResults(clients) {
    const container = document.getElementById('clientResults');
    
    const html = clients.map(client => `
        <div class="client-search-item" onclick="selectClient(${client.client_id}, '${escapeHtml(client.name)}')">
            <div class="client-name">${escapeHtml(client.name)}</div>
            <div class="client-meta">${escapeHtml(client.email || '')} • ${escapeHtml(client.phone || '')}</div>
        </div>
    `).join('');

    container.innerHTML = html;
    container.classList.add('show');
}

// Select a client
function selectClient(clientId, clientName) {
    document.getElementById('clientId').value = clientId;
    document.getElementById('clientSearch').value = clientName;
    document.getElementById('clientResults').classList.remove('show');
}

// Update payment method UI
function updatePaymentMethodUI() {
    document.querySelectorAll('.payment-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeBtn = document.querySelector(`.payment-btn[data-method="${selectedPaymentMethod}"]`);
    if (activeBtn) activeBtn.classList.add('active');
}

// Show alert message
function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    const alertId = 'alert-' + Date.now();
    
    const alertHtml = `
        <div class="alert alert-${type}" id="${alertId}" style="margin: 20px 20px 0;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'}"></i>
            ${message}
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', alertHtml);
    
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) alert.remove();
    }, 5000);
}

// Utility functions
function formatTime(datetime) {
    if (!datetime) return '';
    const date = new Date(datetime);
    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function calculateSessionTime(startTime) {
    if (!startTime) return '0m';
    const start = new Date(startTime);
    const now = new Date();
    const diff = Math.floor((now - start) / 60000); // minutes
    
    if (diff < 60) {
        return `${diff}m`;
    } else {
        const hours = Math.floor(diff / 60);
        const minutes = diff % 60;
        return `${hours}h ${minutes}m`;
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Switch between tabs
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    const selectedTab = document.getElementById(tabName);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Add active to corresponding button
    document.querySelector(`.tab-btn[onclick="switchTab('${tabName}')"]`)?.classList.add('active');
    
    // Load entry-exit stats if switching to entry-exit tab
    if (tabName === 'entry-exit-tab') {
        // Initialize entry/exit tab (if function exists from pos.php)
        if (typeof initEntryExitTab === 'function') {
            initEntryExitTab();
        }
        // Start auto-refresh for entry/exit (if function exists from pos.php)
        if (typeof startEntryExitRefresh === 'function') {
            startEntryExitRefresh();
        }
    } else {
        // Stop entry/exit refresh when switching away from that tab
        if (typeof stopEntryExitRefresh === 'function') {
            stopEntryExitRefresh();
        }
    }
}

// Entry/exit functions are now handled in pos.php inline script
// to avoid conflicts with DOM elements and auto-refresh logic



// Animate value changes in cards
function animateValue(id, start, end, duration) {
    const element = document.getElementById(id);
    if (!element) return;
    
    const range = end - start;
    const increment = end > start ? 1 : -1;
    const stepTime = duration / Math.abs(range) || 30;
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        element.textContent = current;
        
        if (current === end) {
            clearInterval(timer);
        }
    }, stepTime);
}
