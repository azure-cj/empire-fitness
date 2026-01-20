// Employee Management JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initSearchFunctionality();
    initFormSubmit();
    initPhoneValidation();
    initRolePositionSync();
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

// Apply Filter
function applyFilter(filter) {
    const searchTerm = document.getElementById('searchInput').value;
    let url = '?filter=' + filter;
    if (searchTerm) {
        url += '&search=' + encodeURIComponent(searchTerm);
    }
    window.location.href = url;
}

// Search Functionality
function initSearchFunctionality() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const filter = new URLSearchParams(window.location.search).get('filter') || 'all';
                const searchTerm = this.value;
                let url = '?filter=' + filter;
                if (searchTerm) {
                    url += '&search=' + encodeURIComponent(searchTerm);
                }
                window.location.href = url;
            }, 500);
        });
    }
}

// Initialize Phone Number Validation
function initPhoneValidation() {
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
        });
    }

    const emergencyPhoneInput = document.getElementById('emergency_phone');
    if (emergencyPhoneInput) {
        emergencyPhoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
        });
    }
}

// Open Add Modal
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add Employee';
    document.getElementById('formAction').value = 'add';
    document.getElementById('employeeForm').reset();
    document.getElementById('employeeId').value = '';
    
    const roleSelect = document.getElementById('role');
    if (roleSelect) {
        roleSelect.value = 'Receptionist';
        const roleChangeEvent = new Event('change', { bubbles: true });
        roleSelect.dispatchEvent(roleChangeEvent);
    }
    
    document.getElementById('employeeModal').classList.add('active');
}

// Open Edit Modal
async function openEditModal(employeeId) {
    try {
        const formData = new FormData();
        formData.append('action', 'get');
        formData.append('employee_id', employeeId);
        
        const response = await fetch('includes/employee_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            const emp = data.employee;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Employee';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('employeeId').value = emp.employee_id;
            document.getElementById('first_name').value = emp.first_name;
            document.getElementById('middle_name').value = emp.middle_name || '';
            document.getElementById('last_name').value = emp.last_name;
            document.getElementById('email').value = emp.email;
            document.getElementById('phone').value = emp.phone;
            document.getElementById('address').value = emp.address || '';
            document.getElementById('position').value = emp.position;
            document.getElementById('role').value = emp.role;
            document.getElementById('status').value = emp.status;
            document.getElementById('hire_date').value = emp.hire_date || '';
            document.getElementById('emergency_contact').value = emp.emergency_contact || '';
            document.getElementById('emergency_phone').value = emp.emergency_phone || '';
            
            // Trigger role change to sync position
            const roleSelect = document.getElementById('role');
            if (roleSelect) {
                const roleChangeEvent = new Event('change', { bubbles: true });
                roleSelect.dispatchEvent(roleChangeEvent);
            }
            
            document.getElementById('employeeModal').classList.add('active');
        } else {
            showAlert(data.message || 'Error loading employee data', 'error');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'error');
    }
}

// Initialize role to position sync
function initRolePositionSync() {
    const roleSelect = document.getElementById('role');
    const positionField = document.getElementById('position');
    
    if (roleSelect && positionField) {
        roleSelect.addEventListener('change', function() {
            const selectedRole = this.value;
            
            const roleToPositionMap = {
                'Manager': 'Manager',
                'Receptionist': 'Receptionist',
                'Admin': 'Manager',
                'Super Admin': 'Manager',
                'Coach': 'Trainer'
            };
            
            const position = roleToPositionMap[selectedRole] || 'Trainer';
            positionField.value = position;
        });
    }
}

// Close Modal
function closeModal() {
    document.getElementById('employeeModal').classList.remove('active');
}

// Close View Modal
function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
}

// Close Confirmation Modal
function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Show Confirmation Modal
function showConfirmationModal(options) {
    const modal = document.getElementById('confirmModal');
    const content = document.getElementById('confirmContent');
    
    const iconClass = options.type || 'info';
    const iconMap = {
        'success': 'fas fa-check-circle',
        'warning': 'fas fa-exclamation-circle',
        'danger': 'fas fa-exclamation-triangle',
        'info': 'fas fa-info-circle'
    };
    const icon = iconMap[iconClass] || 'fas fa-info-circle';
    
    const employeeHtml = options.employeeName 
        ? `<p class="employee-highlight">"${escapeHtml(options.employeeName)}"</p>` 
        : '';
    
    const infoBoxHtml = options.infoBox ? options.infoBox : '';
    
    const textClass = options.type === 'danger' ? 'danger-text' : options.type === 'warning' ? 'warning-text' : '';
    const descriptionHtml = options.description && textClass
        ? `<p class="${textClass}">${options.description}</p>`
        : options.description
        ? `<p>${options.description}</p>`
        : '';
    
    content.innerHTML = `
        <div class="confirmation-icon ${iconClass}">
            <i class="${icon}"></i>
        </div>
        <div class="confirmation-content">
            <h3>${options.title}</h3>
            <p>${options.message}</p>
            ${employeeHtml}
            ${infoBoxHtml}
            ${descriptionHtml}
        </div>
        <div class="confirmation-actions">
            <button class="btn-cancel" onclick="closeConfirmModal()">
                ${options.cancelText || 'Close'}
            </button>
            ${options.onConfirm ? `
            <button class="btn-confirm ${options.confirmClass || ''}" onclick="confirmAction()">
                ${options.confirmText || 'Confirm'}
            </button>
            ` : ''}
        </div>
    `;
    
    window.confirmCallback = options.onConfirm || null;
    modal.classList.add('active');
}

function confirmAction() {
    if (window.confirmCallback) {
        window.confirmCallback();
        window.confirmCallback = null;
    }
    closeConfirmModal();
}

// Form Submit
function initFormSubmit() {
    const form = document.getElementById('employeeForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const action = document.getElementById('formAction').value;
            
            try {
                const response = await fetch('includes/employee_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    closeModal();
                    
                    if (action === 'add' && data.default_password && data.employee_code) {
                        // Show credentials modal for new employee
                        const employeeName = document.getElementById('first_name').value + ' ' + document.getElementById('last_name').value;
                        const email = document.getElementById('email').value;
                        
                        showConfirmationModal({
                            type: 'success',
                            title: 'Employee Created Successfully',
                            message: 'The employee account has been created. Please save the following credentials:',
                            infoBox: `
                                <div class="info-box">
                                    <h4>Account Credentials</h4>
                                    <p style="margin: 8px 0;"><strong>Employee Name:</strong> ${escapeHtml(employeeName)}</p>
                                    <p style="margin: 8px 0;"><strong>Email:</strong> ${escapeHtml(email)}</p>
                                    <p style="margin: 8px 0;"><strong>Employee Code:</strong></p>
                                    <div class="credentials-display">${escapeHtml(data.employee_code)}</div>
                                    <p style="margin: 8px 0;"><strong>Default Password:</strong></p>
                                    <div class="credentials-display">${escapeHtml(data.default_password)}</div>
                                    ${data.email_sent ? '<p style="margin-top: 12px; color: #059669;"><i class="fas fa-check-circle"></i> Credentials have been emailed to the employee.</p>' : ''}
                                </div>
                            `,
                            description: data.email_sent ? 'The employee will receive these credentials via email. Make sure to inform them to check their inbox.' : 'Please share these credentials with the employee manually as the email notification could not be sent.',
                            cancelText: 'Close',
                            onConfirm: () => {
                                window.location.reload();
                            },
                            confirmText: 'Done',
                            confirmClass: ''
                        });
                    } else {
                        showAlert(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                } else {
                    showAlert(data.message || 'Error saving employee', 'error');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            }
        });
    }
}

// View Employee
async function viewEmployee(employeeId) {
    try {
        const formData = new FormData();
        formData.append('action', 'get');
        formData.append('employee_id', employeeId);
        
        const response = await fetch('includes/employee_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            const emp = data.employee;
            const viewBody = document.getElementById('viewModalBody');
            
            viewBody.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div>
                        <h4 style="color: #d41c1c; margin-bottom: 5px;">
                            <i class="fas fa-id-card"></i> Employee Information
                        </h4>
                        <p><strong>Employee Code:</strong> ${emp.employee_code || 'N/A'}</p>
                        <p><strong>Name:</strong> ${emp.first_name} ${emp.middle_name || ''} ${emp.last_name}</p>
                        <p><strong>Email:</strong> ${emp.email}</p>
                        <p><strong>Contact Number:</strong> ${emp.phone}</p>
                        <p><strong>Address:</strong> ${emp.address || 'N/A'}</p>
                    </div>
                    <div>
                        <h4 style="color: #d41c1c; margin-bottom: 5px;">
                            <i class="fas fa-briefcase"></i> Employment Details
                        </h4>
                        <p><strong>Position:</strong> ${emp.position}</p>
                        <p><strong>Role:</strong> ${emp.role}</p>
                        <p><strong>Status:</strong> <span class="status-badge status-${emp.status.toLowerCase()}">${emp.status}</span></p>
                        <p><strong>Hire Date:</strong> ${emp.hire_date || 'N/A'}</p>
                    </div>
                    <div>
                        <h4 style="color: #d41c1c; margin-bottom: 5px;">
                            <i class="fas fa-phone-alt"></i> Emergency Contact
                        </h4>
                        <p><strong>Contact Name:</strong> ${emp.emergency_contact || 'N/A'}</p>
                        <p><strong>Contact Number:</strong> ${emp.emergency_phone || 'N/A'}</p>
                        <br>
                        <h4 style="color: #d41c1c; margin-bottom: 5px;">
                            <i class="fas fa-clock"></i> Timestamps
                        </h4>
                        <p><strong>Created:</strong> ${new Date(emp.created_at).toLocaleString()}</p>
                        <p><strong>Last Updated:</strong> ${new Date(emp.updated_at).toLocaleString()}</p>
                        <p><strong>Last Login:</strong> ${emp.last_login ? new Date(emp.last_login).toLocaleString() : 'Never'}</p>
                    </div>
                </div>
            `;
            
            document.getElementById('viewModal').classList.add('active');
        } else {
            showAlert(data.message || 'Error loading employee data', 'error');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'error');
    }
}

// Delete Employee
function deleteEmployee(employeeId, employeeName) {
    showConfirmationModal({
        type: 'danger',
        title: 'Delete Employee',
        message: 'Are you sure you want to delete this employee?',
        employeeName: employeeName,
        description: 'This action cannot be undone. All employee data will be permanently removed from the system.',
        confirmText: 'Delete Employee',
        confirmClass: 'danger',
        onConfirm: () => {
            executeDeleteEmployee(employeeId);
        }
    });
}

async function executeDeleteEmployee(employeeId) {
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('employee_id', employeeId);
        
        const response = await fetch('includes/employee_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.message || 'Error deleting employee', 'error');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'error');
    }
}

// Reset Password
function resetPassword(employeeId) {
    showConfirmationModal({
        type: 'warning',
        title: 'Reset Password',
        message: 'Are you sure you want to reset this employee\'s password?',
        description: 'A new default password will be generated and sent to the employee via email.',
        confirmText: 'Reset Password',
        confirmClass: '',
        onConfirm: () => {
            executeResetPassword(employeeId);
        }
    });
}

async function executeResetPassword(employeeId) {
    try {
        const formData = new FormData();
        formData.append('action', 'reset_password');
        formData.append('employee_id', employeeId);
        
        const response = await fetch('includes/employee_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showConfirmationModal({
                type: 'success',
                title: 'Password Reset Successful',
                message: 'The employee password has been reset successfully.',
                infoBox: `
                    <div class="info-box">
                        <h4>New Password</h4>
                        <div class="credentials-display">${escapeHtml(data.default_password)}</div>
                        ${data.email_sent ? '<p style="margin-top: 12px; color: #059669;"><i class="fas fa-check-circle"></i> Password has been emailed to the employee.</p>' : ''}
                    </div>
                `,
                description: data.email_sent ? 'The employee will receive the new password via email.' : 'Please share this password with the employee manually.',
                cancelText: 'Close'
            });
        } else {
            showAlert(data.message || 'Error resetting password', 'error');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'error');
    }
}

// Export Employees
async function exportEmployees(format) {
    if (format !== 'csv') {
        showAlert('Only CSV export is supported at the moment', 'warning');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'export_csv');
        
        const response = await fetch('includes/employee_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            const blob = new Blob([data.csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = data.filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showAlert('Employees exported successfully!', 'success');
        } else {
            showAlert(data.message || 'Error exporting employees', 'error');
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'error');
    }
}

// Utility function
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('employeeModal');
    const viewModal = document.getElementById('viewModal');
    const confirmModal = document.getElementById('confirmModal');
    
    if (event.target === modal) {
        closeModal();
    }
    if (event.target === viewModal) {
        closeViewModal();
    }
    if (event.target === confirmModal) {
        closeConfirmModal();
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeViewModal();
        closeConfirmModal();
    }
    
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        openAddModal();
    }
});

console.log('Employee Management System Loaded');