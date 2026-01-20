<?php
session_start();

// Check if user is logged in and has manager role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Manager') {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

$employeeName = $_SESSION['employee_name'] ?? 'Manager';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));
?>
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - Empire Fitness</title>
    <link rel="stylesheet" href="css/manager-dashboard.css">
    <link rel="stylesheet" href="css/manager-components.css">
    <link rel="stylesheet" href="css/employees.css">
    <link rel="stylesheet" href="css/button-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar_navigation.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <h1>Employee Management</h1>
                <p class="breadcrumb">
                    <i class="fas fa-home"></i> Home / Employee Management
                </p>
            </div>
        </div>

        <!-- Alert Box -->
        <div id="alertBox" class="alert" style="display: none;"></div>

        <!-- Stats Summary -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Employees</h3>
                    <p class="stat-number" id="total-employees">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-check-circle"></i> Active
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-content">
                    <h3>Receptionists</h3>
                    <p class="stat-number" id="total-receptionists">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-headset"></i> Front desk
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div class="stat-content">
                    <h3>Managers</h3>
                    <p class="stat-number" id="total-managers">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-briefcase"></i> Management
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-content">
                    <h3>Other Staff</h3>
                    <p class="stat-number" id="total-other">0</p>
                    <span class="stat-change positive">
                        <i class="fas fa-briefcase"></i> Support staff
                    </span>
                </div>
            </div>
        </div>

        <!-- Controls Section -->
        <div class="controls-section">
            <div class="controls-left">
                <button onclick="openAddModal()" class="btn-primary">
                    <i class="fas fa-plus"></i> Add Employee
                </button>
                <button onclick="loadEmployees()" class="btn-secondary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <div class="controls-right">
                <select id="roleFilter" onchange="filterEmployees()" class="filter-select">
                    <option value="all">All Roles</option>
                    <option value="Receptionist">Receptionists</option>
                    <option value="Manager">Managers</option>
                    <option value="Admin">Admins</option>
                </select>
                <select id="statusFilter" onchange="filterEmployees()" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
                <div class="search-box-inline">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search employees..." onkeyup="searchEmployees()">
                </div>
            </div>
        </div>

        <!-- Employees Table -->
        <div class="table-container">
            <table class="employees-table">
                <thead>
                    <tr>
                        <th>Employee Code</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Contact Number</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="employeesTableBody">
                    <tr>
                        <td colspan="8" class="no-data">
                            <i class="fas fa-spinner fa-spin" style="font-size: 48px; opacity: 0.3;"></i>
                            <p>Loading employees...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add/Edit Employee Modal -->
    <div id="employeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add Employee</h3>
                <button class="modal-close" onclick="closeEmployeeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="employeeForm">
                    <input type="hidden" id="employeeId" name="employee_id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstName"><i class="fas fa-user"></i> First Name *</label>
                            <input type="text" id="firstName" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="middleName"><i class="fas fa-user"></i> Middle Name</label>
                            <input type="text" id="middleName" name="middle_name">
                        </div>
                        
                        <div class="form-group">
                            <label for="lastName"><i class="fas fa-user"></i> Last Name *</label>
                            <input type="text" id="lastName" name="last_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone"><i class="fas fa-phone"></i> Contact Number</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                        
                        <div class="form-group">
                            <label for="role"><i class="fas fa-briefcase"></i> System Role *</label>
                            <select id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="Receptionist">Receptionist</option>
                                <option value="Manager">Manager</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status"><i class="fas fa-toggle-on"></i> Status *</label>
                            <select id="status" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea id="address" name="address" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeEmployeeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-primary" onclick="saveEmployee()">
                    <i class="fas fa-save"></i> Save Employee
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header modal-header-danger">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this employee?</p>
                <p class="warning-text"><strong id="deleteEmployeeName"></strong></p>
                <p class="text-muted">This action cannot be undone.</p>
                <input type="hidden" id="deleteEmployeeId">
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Delete Employee
                </button>
            </div>
        </div>
    </div>

    <script src="js/sidebar.js"></script>
    <script src="js/manager-dashboard.js"></script>
    <script src="js/employees.js"></script>
</body>
</html>