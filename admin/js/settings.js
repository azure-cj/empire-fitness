// Settings Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeSettings();
});

function initializeSettings() {
    // Tab switching
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.settings-tab');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('onclick').match(/'([^']+)'/)[1];
            openTab(tabName);
        });
    });

    // Password visibility toggle
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const inputId = this.parentElement.querySelector('input').id;
            togglePassword(inputId);
        });
    });

    // Auto-hide alert after 5 seconds
    const alert = document.getElementById('alert');
    if (alert) {
        setTimeout(() => {
            alert.style.display = 'none';
        }, 5000);
    }
}

/**
 * Open a specific settings tab
 * @param {string} tabName - The name of the tab to open
 */
function openTab(tabName) {
    // Hide all tabs
    const tabContents = document.querySelectorAll('.settings-tab');
    tabContents.forEach(tab => {
        tab.classList.remove('active');
    });

    // Remove active class from all buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => {
        btn.classList.remove('active');
    });

    // Show selected tab
    const selectedTab = document.getElementById(tabName);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }

    // Add active class to clicked button
    event.target.closest('.tab-btn').classList.add('active');
}

/**
 * Toggle password visibility
 * @param {string} inputId - The ID of the password input
 */
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = event.target.closest('.toggle-password');
    
    if (!input || !button) return;

    if (input.type === 'password') {
        input.type = 'text';
        button.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        button.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

/**
 * Show notification message
 * @param {string} message - The message to display
 * @param {string} type - The type of message (success, error, warning)
 */
function showNotification(message, type = 'success') {
    const alertBox = document.createElement('div');
    alertBox.className = `alert alert-${type}`;
    alertBox.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'exclamation-triangle'}"></i>
        <span>${escapeHtml(message)}</span>
        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
    `;
    
    const pageWrapper = document.querySelector('.page-wrapper');
    pageWrapper.insertBefore(alertBox, pageWrapper.firstChild);

    setTimeout(() => {
        alertBox.style.display = 'none';
    }, 5000);
}

/**
 * Escape HTML special characters
 * @param {string} text - The text to escape
 * @returns {string} Escaped text
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

/**
 * Validate password form
 * @returns {boolean} True if form is valid
 */
function validatePasswordForm() {
    const currentPassword = document.getElementById('current_password');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');

    if (!currentPassword.value) {
        showNotification('Current password is required', 'error');
        return false;
    }

    if (!newPassword.value) {
        showNotification('New password is required', 'error');
        return false;
    }

    if (newPassword.value.length < 6) {
        showNotification('New password must be at least 6 characters long', 'error');
        return false;
    }

    if (newPassword.value !== confirmPassword.value) {
        showNotification('New passwords do not match', 'error');
        return false;
    }

    return true;
}
