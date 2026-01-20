// Coaches Management JavaScript

// Search functionality
document.getElementById('searchInput')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const coachCards = document.querySelectorAll('.coach-card');
    
    coachCards.forEach(card => {
        const coachName = card.querySelector('.coach-name').textContent.toLowerCase();
        const specialization = card.querySelector('.coach-specialization').textContent.toLowerCase();
        const email = card.querySelector('.info-row span').textContent.toLowerCase();
        
        if (coachName.includes(searchTerm) || 
            specialization.includes(searchTerm) || 
            email.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

// Filter by status
document.getElementById('filterStatus')?.addEventListener('change', function(e) {
    const status = e.target.value;
    const coachCards = document.querySelectorAll('.coach-card');
    
    coachCards.forEach(card => {
        if (status === 'all' || card.dataset.status === status) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

// Sort coaches
document.getElementById('sortBy')?.addEventListener('change', function(e) {
    const sortType = e.target.value;
    const grid = document.getElementById('coachesGrid');
    const cards = Array.from(document.querySelectorAll('.coach-card'));
    
    cards.sort((a, b) => {
        switch(sortType) {
            case 'name':
                const nameA = a.querySelector('.coach-name').textContent;
                const nameB = b.querySelector('.coach-name').textContent;
                return nameA.localeCompare(nameB);
            
            case 'clients':
                const clientsA = parseInt(a.querySelector('.stat-mini span').textContent);
                const clientsB = parseInt(b.querySelector('.stat-mini span').textContent);
                return clientsB - clientsA;
            
            case 'experience':
                const expA = parseInt(a.querySelector('.stat-mini:last-child span').textContent);
                const expB = parseInt(b.querySelector('.stat-mini:last-child span').textContent);
                return expB - expA;
            
            case 'recent':
                return 0; // Already sorted by created_at DESC in PHP
            
            default:
                return 0;
        }
    });
    
    cards.forEach(card => grid.appendChild(card));
});

// Modal Functions
function openAddCoachModal() {
    const modal = document.getElementById('addCoachModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeAddCoachModal() {
    const modal = document.getElementById('addCoachModal');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
    document.getElementById('addCoachForm').reset();
}

function openAssignModal(coachId) {
    document.getElementById('assignCoachId').value = coachId;
    loadUnassignedClients();
    const modal = document.getElementById('assignClientModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Focus on search input
    setTimeout(() => {
        document.getElementById('clientSearchInput').focus();
    }, 100);
}

function closeAssignModal() {
    const modal = document.getElementById('assignClientModal');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
    document.getElementById('assignClientForm').reset();
    document.getElementById('selectedClientId').value = '';
    document.getElementById('clientSearchInput').value = '';
    document.querySelector('.search-clear-btn').style.display = 'none';
}

function openBulkAssignModal() {
    // TODO: Implement bulk assignment modal
    alert('Bulk assignment feature coming soon!');
}

// Store all clients for filtering
let allClients = [];
let selectedClient = null;

// Load unassigned clients for assignment modal
async function loadUnassignedClients() {
    try {
        const response = await fetch('includes/coaches_handler.php?action=get_unassigned_clients');
        const data = await response.json();
        
        allClients = data.clients || [];
        renderClientCards(allClients);
        updateClientCount(allClients.length);
        
        // Setup search input listener
        const searchInput = document.getElementById('clientSearchInput');
        searchInput.addEventListener('input', filterClients);
        
    } catch (error) {
        console.error('Error loading clients:', error);
        document.getElementById('clientsGrid').innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #dc3545;">
                <i class="fas fa-exclamation-circle" style="font-size: 32px; margin-bottom: 10px;"></i>
                <p>Error loading clients. Please try again.</p>
            </div>
        `;
    }
}

// Filter clients based on search input
function filterClients() {
    const searchTerm = document.getElementById('clientSearchInput').value.toLowerCase().trim();
    const clearBtn = document.querySelector('.search-clear-btn');
    
    if (searchTerm) {
        clearBtn.style.display = 'flex';
    } else {
        clearBtn.style.display = 'none';
    }
    
    let filtered = allClients;
    if (searchTerm) {
        filtered = allClients.filter(client => {
            const fullName = `${client.first_name} ${client.middle_name || ''} ${client.last_name}`.toLowerCase();
            const email = (client.email || '').toLowerCase();
            const phone = (client.phone || '').toLowerCase();
            
            return fullName.includes(searchTerm) || 
                   email.includes(searchTerm) || 
                   phone.includes(searchTerm);
        });
    }
    
    renderClientCards(filtered);
    updateClientCount(filtered.length);
}

// Clear search input
function clearClientSearch() {
    document.getElementById('clientSearchInput').value = '';
    document.querySelector('.search-clear-btn').style.display = 'none';
    renderClientCards(allClients);
    updateClientCount(allClients.length);
}

// Render client cards
function renderClientCards(clients) {
    const grid = document.getElementById('clientsGrid');
    
    if (clients.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #6c757d;">
                <i class="fas fa-search" style="font-size: 48px; opacity: 0.3; margin-bottom: 10px;"></i>
                <p style="margin: 10px 0; font-size: 16px;">No clients found</p>
                <p style="font-size: 14px;">Try adjusting your search criteria</p>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = clients.map(client => {
        const initials = (client.first_name.charAt(0) + client.last_name.charAt(0)).toUpperCase();
        const fullName = `${client.first_name} ${client.middle_name ? client.middle_name + ' ' : ''}${client.last_name}`;
        const isSelected = selectedClient && selectedClient.client_id === client.client_id;
        
        return `
            <div class="client-card ${isSelected ? 'selected' : ''}" data-client-id="${client.client_id}">
                <div class="client-card-header">
                    <div class="client-avatar">${initials}</div>
                    <button type="button" class="select-client-btn" onclick="selectClient(${client.client_id}, '${fullName.replace(/'/g, "\\'")}')" title="Select this client">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
                <div class="client-card-body">
                    <h4 class="client-name">${fullName}</h4>
                    <div class="client-detail">
                        <i class="fas fa-envelope"></i>
                        <span>${client.email || 'No email'}</span>
                    </div>
                    <div class="client-detail">
                        <i class="fas fa-phone"></i>
                        <span>${client.phone || 'No phone'}</span>
                    </div>
                    ${client.join_date ? `
                    <div class="client-detail">
                        <i class="fas fa-calendar"></i>
                        <span>Joined ${formatDate(client.join_date)}</span>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    }).join('');
}

// Select a client
function selectClient(clientId, clientName) {
    selectedClient = { client_id: clientId, name: clientName };
    document.getElementById('selectedClientId').value = clientId;
    
    // Update UI
    document.querySelectorAll('.client-card').forEach(card => {
        card.classList.remove('selected');
    });
    document.querySelector(`[data-client-id="${clientId}"]`).classList.add('selected');
    
    // Update button state
    document.getElementById('assignClientBtn').disabled = false;
}

// Update client count display
function updateClientCount(count) {
    const display = document.getElementById('clientCountDisplay');
    if (count === 0) {
        display.textContent = 'No clients found';
    } else if (count === 1) {
        display.textContent = '1 client available';
    } else {
        display.textContent = `${count} clients available`;
    }
}

// View coach profile
function viewCoachProfile(coachId) {
    window.location.href = `coach_profile.php?id=${coachId}`;
}

// Edit coach
function editCoach(coachId) {
    window.location.href = `edit_coach.php?id=${coachId}`;
}

// View schedule
function viewSchedule(coachId) {
    window.location.href = `coach_schedules.php?coach_id=${coachId}`;
}

// View performance
function viewPerformance(coachId) {
    window.location.href = `coach_performance.php?coach_id=${coachId}`;
}

// Change coach status
async function changeStatus(coachId, currentStatus) {
    const statuses = ['Active', 'Inactive', 'On Leave'];
    const newStatus = prompt(`Change status for this coach\nCurrent: ${currentStatus}\n\nEnter new status (Active/Inactive/On Leave):`, currentStatus);
    
    if (newStatus && statuses.includes(newStatus) && newStatus !== currentStatus) {
        try {
            const formData = new FormData();
            formData.append('action', 'change_status');
            formData.append('coach_id', coachId);
            formData.append('status', newStatus);
            
            const response = await fetch('includes/coaches_handler.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showAlert('success', 'Status updated successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('error', result.message || 'Failed to update status');
            }
        } catch (error) {
            showAlert('error', 'An error occurred while updating status');
            console.error('Error:', error);
        }
    }
}

// Toggle dropdown menu
function toggleDropdown(button) {
    const dropdown = button.closest('.dropdown');
    const menu = dropdown.querySelector('.dropdown-menu');
    
    // Close all other dropdowns
    document.querySelectorAll('.dropdown-menu.show').forEach(m => {
        if (m !== menu) m.classList.remove('show');
    });
    
    menu.classList.toggle('show');
    
    // Close dropdown when clicking outside
    setTimeout(() => {
        document.addEventListener('click', function closeDropdown(e) {
            if (!dropdown.contains(e.target)) {
                menu.classList.remove('show');
                document.removeEventListener('click', closeDropdown);
            }
        });
    }, 0);
}

// Form validation
document.getElementById('addCoachForm')?.addEventListener('submit', function(e) {
    // No password validation needed anymore since it's auto-generated
    return true;
});

// Handle form submissions
document.getElementById('addCoachForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitButton = this.querySelector('[type="submit"]');
    const originalText = submitButton.innerHTML;
    submitButton.innerHTML = '<span class="loading-spinner"></span> Adding...';
    submitButton.disabled = true;
    
    try {
        const formData = new FormData(this);
        const response = await fetch('includes/coaches_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            const fileName = result.credentials_file || 'credentials file';
            showAlert('success', `Coach added successfully! Credentials saved to: ${fileName}`);
            setTimeout(() => location.reload(), 2000);
        } else {
            showAlert('error', result.message || 'Failed to add coach');
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    } catch (error) {
        showAlert('error', 'An error occurred while adding the coach');
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
        console.error('Error:', error);
    }
});

document.getElementById('assignClientForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitButton = this.querySelector('[type="submit"]');
    const originalText = submitButton.innerHTML;
    submitButton.innerHTML = '<span class="loading-spinner"></span> Assigning...';
    submitButton.disabled = true;
    
    try {
        const formData = new FormData(this);
        const response = await fetch('includes/coaches_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', 'Client assigned successfully!');
            closeAssignModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('error', result.message || 'Failed to assign client');
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    } catch (error) {
        showAlert('error', 'An error occurred while assigning the client');
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
        console.error('Error:', error);
    }
});

// Show alert messages
function showAlert(type, message) {
    // Remove existing alerts
    document.querySelectorAll('.alert').forEach(alert => alert.remove());
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    const mainContent = document.querySelector('.main-content');
    const topbar = document.querySelector('.topbar');
    mainContent.insertBefore(alert, topbar.nextSibling);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    });
});

// Handle escape key to close modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show').forEach(modal => {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        });
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('Coaches management loaded');
    
    // Check for success/error messages in URL
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const error = urlParams.get('error');
    
    if (success) {
        showAlert('success', decodeURIComponent(success));
        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    if (error) {
        showAlert('error', decodeURIComponent(error));
        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

/**
 * Format date to readable format
 */
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}