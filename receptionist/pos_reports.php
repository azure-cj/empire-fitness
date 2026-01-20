<?php
session_start();

if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Receptionist') {
    header("Location: ../index.php");
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

$employeeInitial = strtoupper(substr($_SESSION['employee_name'] ?? 'R', 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Reports - Empire Fitness</title>
    <link rel="stylesheet" href="../manager/css/manager-dashboard.css">
    <link rel="stylesheet" href="../css/button-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-user-id="<?php echo htmlspecialchars($_SESSION['employee_id']); ?>"
      data-user-role="<?php echo htmlspecialchars($_SESSION['employee_role']); ?>"
      data-user-name="<?php echo htmlspecialchars($_SESSION['employee_name']); ?>">

    <?php include 'includes/sidebar_navigation.php'; ?>

    <main class="main-content">
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-info">
                <h1><i class="fas fa-chart-bar"></i> POS Reports</h1>
                <p>Comprehensive point of sale transaction analysis</p>
            </div>
            <div class="header-actions no-print">
                <button class="btn-icon btn-primary" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
                <button class="btn-icon btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <div class="filter-group">
                <label for="filter-date">Date:</label>
                <input type="date" id="filter-date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="filter-group">
                <label for="filter-receptionist">Receptionist:</label>
                <select id="filter-receptionist">
                    <option value="">All Receptionists</option>
                </select>
            </div>
            <button class="btn-sm btn-primary-sm" onclick="loadReports()">
                <i class="fas fa-search"></i> Filter
            </button>
            <button class="btn-sm btn-secondary-sm" onclick="resetFilters()">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Reports Container -->
        <div id="reports-container">
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No reports to display. Start a POS session and end it to generate a report.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="pagination-container"></div>
    </div>

    <script>
        let allReports = [];
        let currentPage = 1;
        const reportsPerPage = 5;

        // Load reports on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadReports();
            loadReceptionistList();
            setupSidebar();
        });

        function setupSidebar() {
            document.getElementById('sidebar-toggle').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('hidden');
            });
        }

        function loadReceptionistList() {
            // Fetch list of receptionists who have made sales
            fetch('includes/pos_handler.php?action=get_receptionists')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.receptionists) {
                        const select = document.getElementById('filter-receptionist');
                        data.receptionists.forEach(rec => {
                            const option = document.createElement('option');
                            option.value = rec.employee_id;
                            option.textContent = rec.employee_name;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(err => console.error('Error loading receptionists:', err));
        }

        function loadReports() {
            const date = document.getElementById('filter-date').value;
            const receptionistId = document.getElementById('filter-receptionist').value;

            fetch(`includes/pos_handler.php?action=get_session_reports&date=${date}${receptionistId ? '&employee_id=' + receptionistId : ''}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.sessions) {
                        allReports = data.sessions;
                        currentPage = 1;
                        renderReports();
                    } else {
                        showNoReports();
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    showNoReports();
                });
        }

        function renderReports() {
            const container = document.getElementById('reports-container');
            
            if (allReports.length === 0) {
                showNoReports();
                return;
            }

            const start = (currentPage - 1) * reportsPerPage;
            const end = start + reportsPerPage;
            const pagedReports = allReports.slice(start, end);

            let html = '';
            pagedReports.forEach(report => {
                const startTime = new Date(report.start_time).toLocaleTimeString();
                const endTime = report.end_time ? new Date(report.end_time).toLocaleTimeString() : 'Active';

                html += `
                    <div class="report-card">
                        <div class="report-header">
                            <div class="report-info">
                                <div class="report-title">Session #${report.session_id}</div>
                                <div class="report-meta">
                                    <span><i class="fas fa-user"></i> ${report.employee_name}</span>
                                    <span><i class="fas fa-clock"></i> ${startTime} - ${endTime}</span>
                                    <span><i class="fas fa-calendar"></i> ${new Date(report.start_time).toLocaleDateString()}</span>
                                </div>
                            </div>
                            <div class="report-actions">
                                <button class="btn-sm btn-primary-sm" onclick="exportReport(${report.session_id})">
                                    <i class="fas fa-download"></i> Export
                                </button>
                                <button class="btn-sm btn-secondary-sm" onclick="printReport(${report.session_id})">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-box">
                                <div class="stat-label">Total Sales</div>
                                <div class="stat-value">₱${parseFloat(report.total_sales).toFixed(2)}</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Cash</div>
                                <div class="stat-value">₱${parseFloat(report.total_cash).toFixed(2)}</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Digital</div>
                                <div class="stat-value">₱${parseFloat(report.total_digital).toFixed(2)}</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Transactions</div>
                                <div class="stat-value">${report.transaction_count}</div>
                            </div>
                        </div>

                        <div class="breakdown-section">
                            <div class="breakdown-title"><i class="fas fa-layer-group"></i> Opening & Closing</div>
                            <div class="breakdown-list">
                                <div class="breakdown-item">
                                    <span class="breakdown-item-label">Opening Balance</span>
                                    <span class="breakdown-item-value">₱${parseFloat(report.opening_balance).toFixed(2)}</span>
                                </div>
                                <div class="breakdown-item">
                                    <span class="breakdown-item-label">Closing Balance</span>
                                    <span class="breakdown-item-value">₱${parseFloat(report.closing_balance).toFixed(2)}</span>
                                </div>
                            </div>
                        </div>

                        ${report.notes ? `<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; color: #856404;"><strong>Notes:</strong> ${report.notes}</div>` : ''}
                    </div>
                `;
            });

            container.innerHTML = html;
            renderPagination();
        }

        function renderPagination() {
            const totalPages = Math.ceil(allReports.length / reportsPerPage);
            const paginationContainer = document.getElementById('pagination-container');

            if (totalPages <= 1) {
                paginationContainer.innerHTML = '';
                return;
            }

            let html = '';
            for (let i = 1; i <= totalPages; i++) {
                html += `<button class="btn-sm ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
            }

            paginationContainer.innerHTML = html;
        }

        function goToPage(page) {
            currentPage = page;
            renderReports();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showNoReports() {
            document.getElementById('reports-container').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No reports found for the selected criteria.</p>
                </div>
            `;
            document.getElementById('pagination-container').innerHTML = '';
        }

        function resetFilters() {
            document.getElementById('filter-date').value = new Date().toISOString().split('T')[0];
            document.getElementById('filter-receptionist').value = '';
            loadReports();
        }

        function exportReport(sessionId) {
            alert('Export feature coming soon for report #' + sessionId);
        }

        function printReport(sessionId) {
            window.print();
        }

        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('sidebar');

            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                });

                // Close sidebar when a link is clicked on mobile
                const navItems = sidebar.querySelectorAll('.nav-item');
                navItems.forEach(item => {
                    item.addEventListener('click', function() {
                        if (window.innerWidth <= 768) {
                            sidebar.classList.remove('open');
                        }
                    });
                });

                // Close sidebar when window is resized to larger screen
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        sidebar.classList.remove('open');
                    }
                });
            }
        });
    </script>
</body>
</html>
