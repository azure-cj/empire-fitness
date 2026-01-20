// Settings Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeSettingsPage();
});

function initializeSettingsPage() {
    setupSettingsNavigation();
    setupPasswordStrengthMeter();
    setupFormValidation();
}

/**
 * Setup settings menu navigation
 */
function setupSettingsNavigation() {
    const menuItems = document.querySelectorAll('.settings-menu-item');
    const sections = document.querySelectorAll('.settings-section');
    
    menuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all items and sections
            menuItems.forEach(m => m.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));
            
            // Add active class to clicked item
            this.classList.add('active');
            
            // Show corresponding section
            const sectionId = this.getAttribute('data-section');
            const targetSection = document.getElementById(`${sectionId}-settings`);
            
            if (targetSection) {
                targetSection.classList.add('active');
            }
        });
    });
    
    // Set first item as active
    if (menuItems.length > 0) {
        menuItems[0].classList.add('active');
    }
}

/**
 * Password strength meter
 */
function setupPasswordStrengthMeter() {
    const newPasswordInput = document.getElementById('new_password');
    const strengthFill = document.getElementById('strength-fill');
    const strengthText = document.getElementById('strength-text');
    
    if (newPasswordInput && strengthFill) {
        newPasswordInput.addEventListener('input', function() {
            const strength = calculatePasswordStrength(this.value);
            updateStrengthMeter(strength, strengthFill, strengthText);
        });
    }
}

/**
 * Calculate password strength
 */
function calculatePasswordStrength(password) {
    let strength = 0;
    
    // Length check
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    
    // Character variety checks
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    return Math.min(Math.ceil(strength / 2), 3);
}

/**
 * Update strength meter display
 */
function updateStrengthMeter(strength, fillElement, textElement) {
    const strengthLevels = {
        0: { class: 'weak', text: 'Weak', color: '#dc3545' },
        1: { class: 'weak', text: 'Weak', color: '#dc3545' },
        2: { class: 'medium', text: 'Medium', color: '#ffc107' },
        3: { class: 'strong', text: 'Strong', color: '#28a745' }
    };
    
    const level = strengthLevels[strength];
    
    fillElement.className = 'strength-fill ' + level.class;
    if (textElement) {
        textElement.textContent = 'Password strength: ' + level.text;
        textElement.style.color = level.color;
    }
}

/**
 * Setup form validation
 */
function setupFormValidation() {
    const forms = document.querySelectorAll('.settings-form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Validate form
 */
function validateForm(form) {
    const action = form.querySelector('input[name="action"]').value;
    
    switch (action) {
        case 'update_password':
            return validatePasswordForm(form);
        case 'update_contact':
            return validateContactForm(form);
        default:
            return true;
    }
}

/**
 * Validate password form
 */
function validatePasswordForm(form) {
    const currentPassword = form.querySelector('#current_password');
    const newPassword = form.querySelector('#new_password');
    const confirmPassword = form.querySelector('#confirm_password');
    
    // Check if passwords match
    if (newPassword.value !== confirmPassword.value) {
        showFormError('Passwords do not match');
        return false;
    }
    
    // Check minimum length
    if (newPassword.value.length < 8) {
        showFormError('Password must be at least 8 characters');
        return false;
    }
    
    return true;
}

/**
 * Validate contact form
 */
function validateContactForm(form) {
    const email = form.querySelector('#email');
    
    if (!validateEmail(email.value)) {
        showFormError('Please enter a valid email address');
        return false;
    }
    
    return true;
}

/**
 * Validate email
 */
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Show form error
 */
function showFormError(message) {
    // Check if error alert already exists
    const existingAlert = document.querySelector('.alert-danger');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger';
    alert.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
    
    const wrapper = document.querySelector('.content-wrapper');
    if (wrapper) {
        wrapper.insertBefore(alert, wrapper.firstChild);
        
        // Remove alert after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
}

/**
 * Clear form alerts
 */
function clearFormAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.animation = 'slideUp 0.3s ease forwards';
        setTimeout(() => alert.remove(), 300);
    });
}

/**
 * Setup toggle switches
 */
function setupToggleSwitches() {
    const toggles = document.querySelectorAll('.toggle-switch input');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const label = this.closest('.notification-item');
            if (label) {
                label.classList.toggle('enabled', this.checked);
            }
        });
    });
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideUp {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-10px);
        }
    }
    
    .notification-item.enabled {
        background-color: rgba(76, 175, 80, 0.05);
    }
`;
document.head.appendChild(style);

// Initialize on load
document.addEventListener('DOMContentLoaded', setupToggleSwitches);
