document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('history-tbody');
    const totalCollectedEl = document.getElementById('total-collected');
    const todayCollectedEl = document.getElementById('today-collected');
    const todayCountEl = document.getElementById('today-count');
    const filterCountEl = document.getElementById('filter-count');

    const dateFromEl = document.getElementById('filter-date-from');
    const dateToEl = document.getElementById('filter-date-to');
    const typeEl = document.getElementById('filter-type');
    const methodEl = document.getElementById('filter-method');
    const statusEl = document.getElementById('filter-status');
    const searchEl = document.getElementById('filter-search');

    const applyBtn = document.getElementById('apply-filters-btn');
    const resetBtn = document.getElementById('reset-filters-btn');

    const toastEl = document.getElementById('toast');
    const toastMsgEl = document.getElementById('toast-message');

    function showToast(message) {
        if (!toastEl) return;
        toastMsgEl.textContent = message;
        toastEl.classList.add('show');
        setTimeout(() => toastEl.classList.remove('show'), 2500);
    }

    function buildQuery() {
        const params = new URLSearchParams();

        if (dateFromEl.value) params.append('date_from', dateFromEl.value);
        if (dateToEl.value) params.append('date_to', dateToEl.value);
        if (typeEl.value) params.append('type', typeEl.value);
        if (methodEl.value) params.append('method', methodEl.value);
        if (statusEl.value) params.append('status', statusEl.value);
        if (searchEl.value.trim() !== '') params.append('search', searchEl.value.trim());

        return params.toString();
    }

    function clearTable() {
        while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
    }

    function formatAmount(amount) {
        const num = parseFloat(amount || 0);
        return num.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function renderRows(rows) {
        clearTable();

        if (!rows || rows.length === 0) {
            const tr = document.createElement('tr');
            tr.classList.add('empty-row');
            tr.innerHTML = `
                <td colspan="9" class="text-center">
                    <i class="fas fa-inbox"></i>
                    <p>No payment records found</p>
                </td>
            `;
            tbody.appendChild(tr);
            filterCountEl.textContent = '0';
            return;
        }

        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.classList.add('history-row');

            const date = row.paymentdate || '';
            const client = row.clientname || 'N/A';
            const item = row.itemname || 'N/A';
            const type = row.paymenttype || 'N/A';
            const method = row.paymentmethod || 'N/A';
            const amount = formatAmount(row.amount);
            const status = row.paymentstatus || 'N/A';
            const ref = row.referenceid || 'N/A';
            const proofUrl = row.proof_url;

            let statusClass = '';
            if (status === 'Paid') statusClass = 'status-paid';
            else if (status === 'Pending') statusClass = 'status-pending';
            else if (status === 'Refunded') statusClass = 'status-refunded';
            else if (status === 'Cancelled') statusClass = 'status-cancelled';

            let proofHtml = '<span class="no-proof">None</span>';
            if (proofUrl) {
                proofHtml = `
                    <a href="${proofUrl}" target="_blank" class="proof-link">
                        <i class="fas fa-file-image"></i> View
                    </a>
                `;
            }

            tr.innerHTML = `
                <td>${date}</td>
                <td>${client}</td>
                <td>${item}</td>
                <td>${type}</td>
                <td>${method}</td>
                <td class="amount-cell">₱${amount}</td>
                <td><span class="status-pill ${statusClass}">${status}</span></td>
                <td>${ref}</td>
                <td>${proofHtml}</td>
            `;

            tbody.appendChild(tr);
        });

        filterCountEl.textContent = rows.length.toString();
    }

    async function loadHistory(showMessage = false) {
        const query = buildQuery();
        const url = `includes/payment_history_data.php${query ? '?' + query : ''}`;

        try {
            const response = await fetch(url, {cache: 'no-store'});
            if (!response.ok) {
                throw new Error('Failed to load payment history');
            }

            const data = await response.json();
            
            // Debug: Log the response
            console.log('API Response:', data);
            
            if (!data.success) {
                throw new Error(data.message || 'Error loading payment history');
            }

            renderRows(data.data || []);

            if (data.total_collected !== undefined) {
                totalCollectedEl.textContent = formatAmount(data.total_collected);
            }
            if (data.today_collected !== undefined) {
                todayCollectedEl.textContent = formatAmount(data.today_collected);
            }
            if (data.today_count !== undefined) {
                todayCountEl.textContent = data.today_count.toString();
            }

            if (showMessage) {
                showToast('Payment history updated');
            }
        } catch (err) {
            console.error('Error loading history:', err);
            showToast('Unable to load payment history');
        }
    }

    applyBtn.addEventListener('click', e => {
        e.preventDefault();
        loadHistory(true);
    });

    resetBtn.addEventListener('click', e => {
        e.preventDefault();
        dateFromEl.value = '';
        dateToEl.value = '';
        typeEl.value = '';
        methodEl.value = '';
        statusEl.value = '';
        searchEl.value = '';
        loadHistory(true);
    });

    // Enter key in search
    searchEl.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadHistory(true);
        }
    });

    // Initial load
    loadHistory(false);
});

// Tab switching for payment history
function switchPaymentTab(tabType) {
    const allPaymentsTable = document.getElementById('all-payments-table');
    const posTransactionsTable = document.getElementById('pos-transactions-table');
    const tabAllBtn = document.getElementById('tab-all-payments');
    const tabPosBtn = document.getElementById('tab-pos-transactions');

    if (tabType === 'all') {
        allPaymentsTable.style.display = 'block';
        posTransactionsTable.style.display = 'none';
        tabAllBtn.style.borderColor = '#d41c1c';
        tabAllBtn.style.color = '#2c3e50';
        tabPosBtn.style.borderColor = 'transparent';
        tabPosBtn.style.color = '#6c757d';
    } else {
        allPaymentsTable.style.display = 'none';
        posTransactionsTable.style.display = 'block';
        tabAllBtn.style.borderColor = 'transparent';
        tabAllBtn.style.color = '#6c757d';
        tabPosBtn.style.borderColor = '#d41c1c';
        tabPosBtn.style.color = '#2c3e50';
        loadPOSTransactions();
    }
}

// Load POS transactions for the day
async function loadPOSTransactions() {
    try {
        const today = new Date().toISOString().split('T')[0];
        const response = await fetch(`includes/pos_handler.php?action=get_transactions&date=${today}`);
        const data = await response.json();

        const tbody = document.getElementById('pos-tbody');
        tbody.innerHTML = '';

        if (!data.success || !data.transactions || data.transactions.length === 0) {
            tbody.innerHTML = `
                <tr class="empty-row">
                    <td colspan="9" class="text-center">
                        <i class="fas fa-inbox"></i>
                        <p>No POS transactions found</p>
                    </td>
                </tr>
            `;
            return;
        }

        data.transactions.forEach(trans => {
            const createdAt = new Date(trans.created_at);
            const dateStr = createdAt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const timeStr = createdAt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${dateStr} ${timeStr}</td>
                <td>${trans.receipt_number || '-'}</td>
                <td>${trans.transaction_type || '-'}</td>
                <td>${trans.client_name || 'Guest'}</td>
                <td>${trans.description || '-'}</td>
                <td>₱${parseFloat(trans.amount || 0).toFixed(2)}</td>
                <td>${trans.payment_method || '-'}</td>
                <td>${trans.employee_name || '-'}</td>
                <td>${trans.session_id || '-'}</td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error loading POS transactions:', error);
        const tbody = document.getElementById('pos-tbody');
        tbody.innerHTML = `
            <tr class="empty-row">
                <td colspan="9" class="text-center">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Error loading POS transactions</p>
                </td>
            </tr>
        `;
    }
}