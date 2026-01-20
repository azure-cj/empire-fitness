// Show/Hide Password Toggle
document.addEventListener('DOMContentLoaded', function() {
    const showPasswordCheckbox = document.getElementById('showPassword');
    const passwordInput = document.getElementById('password');
    
    if (showPasswordCheckbox && passwordInput) {
        showPasswordCheckbox.addEventListener('change', function() {
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    }
    
    // Form validation
    const loginForm = document.getElementById('loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = loginForm.querySelector('input[name="email"]').value.trim();
            const password = loginForm.querySelector('input[name="password"]').value;
            
            if (!email || !password) {
                e.preventDefault();
                showError('Please fill in all fields');
                return false;
            }
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                showError('Please enter a valid email address');
                return false;
            }
            
            // Show loading state
            const submitBtn = loginForm.querySelector('.login-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>LOGGING IN...</span>';
        });
    }
    
    // Auto-hide error message after 5 seconds
    const errorMessage = document.getElementById('login-error');
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.opacity = '0';
            setTimeout(() => {
                errorMessage.style.display = 'none';
            }, 300);
        }, 5000);
    }
    
    // Add keyboard shortcut for login (Ctrl+Enter or Cmd+Enter)
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            loginForm.submit();
        }
    });
});

// Function to show custom error messages
function showError(message) {
    // Remove existing error if any
    const existingError = document.getElementById('login-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Create new error message
    const errorDiv = document.createElement('div');
    errorDiv.id = 'login-error';
    errorDiv.className = 'error-message';
    errorDiv.innerHTML = `
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span>${message}</span>
    `;
    
    // Insert after login button
    const loginBtn = document.querySelector('.login-btn');
    loginBtn.parentNode.insertBefore(errorDiv, loginBtn.nextSibling);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        errorDiv.style.opacity = '0';
        setTimeout(() => {
            errorDiv.remove();
        }, 300);
    }, 5000);
}

// Add smooth animations on page load
window.addEventListener('load', function() {
    document.body.style.opacity = '0';
    setTimeout(() => {
        document.body.style.transition = 'opacity 0.5s ease';
        document.body.style.opacity = '1';
    }, 100);
});

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}