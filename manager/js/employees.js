let employeesData = [];
let filteredData = [];

document.addEventListener('DOMContentLoaded', function() {
    loadEmployees();
    loadStats();
});

async function loadStats() {
    try {
        const response = await fetch('includes/employees_handler.php?action=stats');
        const data = await response.json();
        
        if (data.success && data.stats) {
            Object.keys(data.stats).forEach(key => {
                const element = document.getElementById(key);
                if (element) {
                    element.textContent = data.stats[key];
                }
            });
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadEmployees() {
    try {
        const response = await fetch('includes/employees_handler.php?action=fetch');
        const data = await response.json();
        
        if (data.success) {
            employeesData = data.employees;
            filteredData = [...employeesData];
            console.log('✅ Employees loaded:', employeesData.length);
            renderEmployeesTable();
        } else {
            showAlert('Error loading employees: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showAlert('Failed to load employees: ' + error.message, 'error');
    }
}

function renderEmployeesTable() {
    const tbody = document.getElementById('employeesTableBody');
    
    if (filteredData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="no-data">
                    <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3;"></i>
                    <p>No employees found</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = filteredData.map(emp => {
        const statusClass = `status-${emp.status.toLowerCase().replace(' ', '-')}`;
        const roleClass = `role-${emp.role.toLowerCase()}`;
        
        return `
            <tr>
                <td><strong>${escapeHtml(emp.employee_code)}</strong></td>
                <td>
                    <div style="font-weight: 600;">${escapeHtml(emp.firstName)} ${escapeHtml(emp.lastName)}</div>
                </td>
                <td><span class="role-badge ${roleClass}">${escapeHtml(emp.role)}</span></td>
                <td>${escapeHtml(emp.email)}</td>
                <td>${escapeHtml(emp.phone) || 'N/A'}</td>
                <td><span class="status-badge ${statusClass}">${escapeHtml(emp.status)}</span></td>
                <td>
                    <button class="action-btn btn-edit" onclick="openEditModal(${emp.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn btn-delete" onclick="openDeleteModal(${emp.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function filterEmployees() {
    const roleFilter = document.getElementById('roleFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    filteredData = employeesData.filter(emp => {
        const matchesRole = roleFilter === 'all' || emp.role === roleFilter;
        const matchesStatus = statusFilter === 'all' || emp.status === statusFilter;
        const matchesSearch = searchTerm === '' || 
            emp.firstName.toLowerCase().includes(searchTerm) ||
            emp.lastName.toLowerCase().includes(searchTerm) ||
            emp.email.toLowerCase().includes(searchTerm) ||
            (emp.phone && emp.phone.includes(searchTerm));
        
        return matchesRole && matchesStatus && matchesSearch;
    });
    
    renderEmployeesTable();
}

function searchEmployees() {
    filterEmployees();
}

function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add Employee';
    document.getElementById('employeeForm').reset();
    document.getElementById('employeeId').value = '';
    document.getElementById('employeeModal').style.display = 'block';
}

function openEditModal(id) {
    const emp = employeesData.find(e => e.id === id);
    if (!emp) {
        showAlert('Employee not found', 'error');
        return;
    }
    
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit"></i> Edit Employee';
    document.getElementById('employeeId').value = emp.id;
    document.getElementById('firstName').value = emp.first_name || '';
    document.getElementById('middleName').value = emp.middle_name || '';
    document.getElementById('lastName').value = emp.last_name || '';
    document.getElementById('email').value = emp.email || '';
    document.getElementById('phone').value = emp.phone || '';
    document.getElementById('role').value = emp.role || '';
    document.getElementById('status').value = emp.status || '';
    document.getElementById('address').value = emp.address || '';
    
    document.getElementById('employeeModal').style.display = 'block';
}

function closeEmployeeModal() {
    document.getElementById('employeeModal').style.display = 'none';
}

async function saveEmployee() {
    const form = document.getElementById('employeeForm');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    const employeeId = document.getElementById('employeeId').value;
    formData.append('action', employeeId ? 'update' : 'create');
    
    try {
        const response = await fetch('includes/employees_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // For new employees, show credentials message
            if (!employeeId && data.credentials_file) {
                const message = `✅ Employee Created Successfully!\n\n` +
                    `Employee Code: ${data.employee_code}\n` +
                    `Default Password: ${data.default_password}\n\n` +
                    `Credentials saved to: admin/credentials/${data.credentials_file}\n\n` +
                    `⚠️ Please share credentials securely with the employee.`;
                alert(message);
            }
            showAlert(data.message, 'success');
            closeEmployeeModal();
            await loadEmployees();
        } else {
            showAlert('Error: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Failed to save employee. Please try again.', 'error');
    }
}

function openDeleteModal(id) {
    const emp = employeesData.find(e => e.id === id);
    if (!emp) {
        showAlert('Employee not found', 'error');
        return;
    }
    
    document.getElementById('deleteEmployeeId').value = emp.id;
    document.getElementById('deleteEmployeeName').textContent = `${emp.firstName} ${emp.lastName} (${emp.role})`;
    document.getElementById('deleteModal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

async function confirmDelete() {
    const employeeId = document.getElementById('deleteEmployeeId').value;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('employee_id', employeeId);
        
        const response = await fetch('includes/employees_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            closeDeleteModal();
            await loadEmployees();
        } else {
            showAlert('Error: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Failed to delete employee. Please try again.', 'error');
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const employeeModal = document.getElementById('employeeModal');
    const deleteModal = document.getElementById('deleteModal');
    
    if (event.target === employeeModal) {
        closeEmployeeModal();
    }
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
}

function showAlert(message, type) {
    const alertBox = document.getElementById('alertBox');
    alertBox.textContent = message;
    alertBox.className = `alert alert-${type}`;
    alertBox.style.display = 'block';
    
    setTimeout(() => {
        alertBox.style.display = 'none';
    }, 5000);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

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