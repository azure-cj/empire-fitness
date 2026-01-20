// Admin Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all features
    initSidebarToggle();
    initModuleCards();
    initActivityItems();
    updateDateTime();
    
    // Load dynamic dashboard data
    loadDashboardStats();
    loadRecentActivity();
    
    // Update date/time every minute
    setInterval(updateDateTime, 60000);
    
    // Auto-refresh stats every 5 minutes
    setInterval(loadDashboardStats, 300000);
    
    // Auto-refresh activity every 10 minutes
    setInterval(loadRecentActivity, 600000);
});

// Sidebar Toggle Functionality
function initSidebarToggle() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    const mainContent = document.getElementById('main-content');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            
            // Close sidebar when clicking outside on mobile
            if (sidebar.classList.contains('active')) {
                document.addEventListener('click', closeSidebarOnClickOutside);
            }
        });
    }
    
    function closeSidebarOnClickOutside(e) {
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
            sidebar.classList.remove('active');
            document.removeEventListener('click', closeSidebarOnClickOutside);
        }
    }
}

// Module Cards Hover Effect
function initModuleCards() {
    const moduleCards = document.querySelectorAll('.module-card');
    
    moduleCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

// Activity Items Animation
function initActivityItems() {
    const activityItems = document.querySelectorAll('.activity-item');
    
    activityItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            item.style.transition = 'all 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, index * 100);
    });
}

// Update Date and Time
function updateDateTime() {
    const dateElements = document.querySelectorAll('.quick-date span');
    const now = new Date();
    
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    
    const formattedDate = now.toLocaleDateString('en-US', options);
    
    dateElements.forEach(element => {
        element.textContent = formattedDate;
    });
}

// Stat Cards Animation
function animateStatCards() {
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// Animate numbers in stat cards
function animateStatNumbers() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    statNumbers.forEach(stat => {
        const text = stat.textContent;
        const hasComma = text.includes(',');
        const hasPeso = text.includes('₱');
        const hasPercent = text.includes('%');
        
        // Extract just the number
        let targetNumber = parseInt(text.replace(/[^0-9]/g, ''));
        
        if (isNaN(targetNumber)) return;
        
        let currentNumber = 0;
        const increment = targetNumber / 50;
        const duration = 1500;
        const stepTime = duration / 50;
        
        const counter = setInterval(() => {
            currentNumber += increment;
            
            if (currentNumber >= targetNumber) {
                currentNumber = targetNumber;
                clearInterval(counter);
            }
            
            let displayValue = Math.floor(currentNumber);
            
            // Format with commas if original had commas
            if (hasComma) {
                displayValue = displayValue.toLocaleString();
            }
            
            // Add peso sign if original had it
            if (hasPeso) {
                displayValue = '₱' + displayValue;
                if (text.includes('.')) {
                    displayValue += '.00';
                }
            }
            
            // Add percent sign if original had it
            if (hasPercent) {
                displayValue += '%';
            }
            
            stat.textContent = displayValue;
        }, stepTime);
    });
}

// Call animations on page load
window.addEventListener('load', function() {
    animateStatCards();
    setTimeout(animateStatNumbers, 200);
});

// Search Functionality
const searchInput = document.querySelector('.search-box input');
if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        console.log('Searching for:', searchTerm);
        // Add your search logic here
    });
}

// Notification Button
const notificationBtn = document.querySelector('.notification-btn');
if (notificationBtn) {
    notificationBtn.addEventListener('click', function() {
        alert('Notifications feature coming soon!');
    });
}

// User Profile Dropdown (if needed)
const userProfile = document.querySelector('.user-profile');
if (userProfile) {
    userProfile.addEventListener('click', function() {
        console.log('User profile clicked');
        // Add dropdown menu logic here
    });
}

// Smooth Scroll for Navigation
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Add active class to current nav item based on URL
function setActiveNavItem() {
    const currentPage = window.location.pathname.split('/').pop();
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && href.includes(currentPage)) {
            item.classList.add('active');
        } else if (item.classList.contains('active') && !href.includes(currentPage)) {
            item.classList.remove('active');
        }
    });
}

setActiveNavItem();

// Tooltip functionality (optional)
function initTooltips() {
    const elementsWithTooltip = document.querySelectorAll('[data-tooltip]');
    
    elementsWithTooltip.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltipText = this.getAttribute('data-tooltip');
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = tooltipText;
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
            tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
        });
        
        element.addEventListener('mouseleave', function() {
            const tooltip = document.querySelector('.tooltip');
            if (tooltip) {
                tooltip.remove();
            }
        });
    });
}

// Refresh page data via AJAX
function loadDashboardStats() {
    const url = 'includes/admin_dashboard_handler.php?action=get_dashboard_stats';
    
    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateStatCards(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading dashboard stats:', error);
        });
}

// Load recent activity from backend
function loadRecentActivity() {
    const url = 'includes/admin_dashboard_handler.php?action=get_recent_activity';
    
    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateActivityList(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading recent activity:', error);
        });
}

// Update stat cards with fresh data
function updateStatCards(stats) {
    const cards = document.querySelectorAll('.stat-card');
    
    if (cards.length >= 4) {
        // Update Total Members
        const memberCard = cards[0];
        const memberNumber = memberCard.querySelector('.stat-number');
        if (memberNumber) memberNumber.textContent = formatNumber(stats.total_members);
        
        // Update Monthly Revenue
        const revenueCard = cards[1];
        const revenueNumber = revenueCard.querySelector('.stat-number');
        if (revenueNumber) revenueNumber.textContent = '₱' + formatCurrency(stats.monthly_revenue);
        
        // Update Walk-ins Today
        const walkinsCard = cards[2];
        const walkinsNumber = walkinsCard.querySelector('.stat-number');
        if (walkinsNumber) walkinsNumber.textContent = formatNumber(stats.walk_ins_today);
        
        // Update Active Staff
        const staffCard = cards[3];
        const staffNumber = staffCard.querySelector('.stat-number');
        if (staffNumber) staffNumber.textContent = formatNumber(stats.active_employees);
    }
}

// Update activity list with fresh data
function updateActivityList(activities) {
    const activityList = document.querySelector('.activity-list');
    
    if (!activityList) return;
    
    activityList.innerHTML = '';
    
    if (activities.length === 0) {
        activityList.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">No recent activity</p>';
        return;
    }
    
    activities.forEach((activity, index) => {
        const timeAgo = getTimeAgo(activity.created_at);
        const iconColor = getIconColor(activity.type);
        const icon = activity.icon || 'info-circle';
        
        const activityHTML = `
            <div class="activity-item" style="opacity: 0; transform: translateX(-20px);">
                <div class="activity-icon ${iconColor}">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="activity-content">
                    <p class="activity-title">${escapeHtml(activity.title)}</p>
                    <p class="activity-time">${timeAgo}</p>
                </div>
            </div>
        `;
        
        activityList.insertAdjacentHTML('beforeend', activityHTML);
        
        // Animate in
        setTimeout(() => {
            const newItem = activityList.lastElementChild;
            newItem.style.transition = 'all 0.5s ease';
            newItem.style.opacity = '1';
            newItem.style.transform = 'translateX(0)';
        }, index * 50);
    });
}

// Get time ago string
function getTimeAgo(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
    if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
    
    return date.toLocaleDateString();
}

// Get icon color based on activity type
function getIconColor(type) {
    const colorMap = {
        'member_registered': 'blue',
        'payment_received': 'green',
        'check_in': 'orange',
        'employee_updated': 'purple'
    };
    
    return colorMap[type] || 'blue';
}

// Format number with commas
function formatNumber(num) {
    return Number(num).toLocaleString('en-US');
}

// Format currency
function formatCurrency(num) {
    return Number(num).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Escape HTML special characters
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-box input');
        if (searchInput) {
            searchInput.focus();
        }
    }
    
    // Escape to close sidebar on mobile
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        if (sidebar && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
        }
    }
});

// Print functionality
function printDashboard() {
    window.print();
}

// Export data functionality (placeholder)
function exportData(format) {
    console.log('Exporting data as:', format);
    alert(`Export as ${format} feature coming soon!`);
}

// Console welcome message
console.log('%cEmpire Fitness Admin Dashboard', 'color: #d41c1c; font-size: 20px; font-weight: bold;');
console.log('%cWelcome to the admin panel!', 'color: #718096; font-size: 14px;');