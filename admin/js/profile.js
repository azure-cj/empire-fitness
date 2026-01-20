// Profile Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initProfileForm();
    initPasswordForm();
    initPhoneValidation();
});

// Show Alert
function showAlert(message, type = 'success') {
    const alertBox = document.getElementById('alertBox');
    alertBox.className = `alert-box ${type}`;
    alertBox.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'exclamation-triangle'}"></i>
        ${message}
    `;
    alertBox.style.display = 'block';
    
    setTimeout(() => {
        alertBox.style.display = 'none';
    }, 5000);
}

// Initialize Phone Validation
function initPhoneValidation() {
    const phoneInputs = document.querySelectorAll('input[type="text"][maxlength="11"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
        });
    });
}

// Open Edit Profile Modal
function openEditProfileModal() {
    document.getElementById('editProfileModal').classList.add('active');
}

// Close Edit Profile Modal
function closeEditProfileModal() {
    document.getElementById('editProfileModal').classList.remove('active');
}

// Open Change Password Modal
function openChangePasswordModal() {
    document.getElementById('changePasswordModal').classList.add('active');
    document.getElementById('passwordForm').reset();
}

// Close Change Password Modal
function closeChangePasswordModal() {
    document.getElementById('changePasswordModal').classList.remove('active');
}

// Initialize Profile Form
function initProfileForm() {
    const form = document.getElementById('profileForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('includes/profile_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    closeEditProfileModal();
                    showAlert(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert(data.message || 'Error updating profile', 'error');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            }
        });
    }
}

// Initialize Password Form
function initPasswordForm() {
    const form = document.getElementById('passwordForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                showAlert('New passwords do not match!', 'error');
                return;
            }
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('includes/profile_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    closeChangePasswordModal();
                    showAlert(data.message, 'success');
                    form.reset();
                } else {
                    showAlert(data.message || 'Error changing password', 'error');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            }
        });
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const editModal = document.getElementById('editProfileModal');
    const passwordModal = document.getElementById('changePasswordModal');
    
    if (event.target === editModal) {
        closeEditProfileModal();
    }
    if (event.target === passwordModal) {
        closeChangePasswordModal();
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditProfileModal();
        closeChangePasswordModal();
    }
});

console.log('Profile Page Loaded');