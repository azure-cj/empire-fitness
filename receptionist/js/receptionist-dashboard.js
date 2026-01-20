// Sidebar Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');

    // Toggle sidebar on button click
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            
            // Close sidebar when clicking outside on mobile
            if (sidebar.classList.contains('active')) {
                document.addEventListener('click', closeSidebarOnClickOutside);
            }
        });
    }

    // Close sidebar when clicking outside
    function closeSidebarOnClickOutside(event) {
        if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
            sidebar.classList.remove('active');
            document.removeEventListener('click', closeSidebarOnClickOutside);
        }
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            document.removeEventListener('click', closeSidebarOnClickOutside);
        }
    });

    // Search functionality
    const searchInput = document.querySelector('.search-box input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            // Add your search logic here
            console.log('Searching for:', searchTerm);
        });
    }

    // Add smooth scroll behavior
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

    // Add animation to stat cards on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '0';
                entry.target.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    entry.target.style.transition = 'all 0.5s ease';
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, 100);
                
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe stat cards and module cards
    document.querySelectorAll('.stat-card, .module-card').forEach(card => {
        observer.observe(card);
    });

    // Notification button functionality
    const notificationBtn = document.querySelector('.notification-btn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function() {
            // Add your notification logic here
            alert('Notifications feature coming soon!');
        });
    }

    // Auto-update time display
    function updateTime() {
        const dateElement = document.querySelector('.quick-date span');
        if (dateElement) {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateElement.textContent = now.toLocaleDateString('en-US', options);
        }
    }

    // Update time every minute
    setInterval(updateTime, 60000);

    // Add loading animation to module cards
    document.querySelectorAll('.module-card').forEach(card => {
        card.addEventListener('click', function() {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });

    // Handle profile section click
    const profileSection = document.querySelector('.profile-section');
    if (profileSection) {
        profileSection.addEventListener('click', function() {
            console.log('Profile clicked');
        });
    }

    // User profile dropdown (if needed in future)
    const userProfile = document.querySelector('.user-profile');
    if (userProfile) {
        userProfile.addEventListener('click', function(e) {
            e.stopPropagation();
            // Add dropdown menu logic here if needed
            console.log('User profile clicked');
        });
    }

    // Add active class to current page in navigation
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && href.includes(currentPage)) {
            item.classList.add('active');
        }
    });

    // Real-time clock for dashboard (optional)
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
        });
        
        // You can add a clock element to display this
        console.log('Current time:', timeString);
    }

    setInterval(updateClock, 1000);

    // Prevent default behavior on logout
    const logoutLink = document.querySelector('.nav-item.logout');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            const confirmed = confirm('Are you sure you want to logout?');
            if (!confirmed) {
                e.preventDefault();
            }
        });
    }

    // Add tooltips to icons (optional enhancement)
    const icons = document.querySelectorAll('.stat-icon, .module-icon, .activity-icon');
    icons.forEach(icon => {
        icon.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1) rotate(5deg)';
        });
        
        icon.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });

    // Print functionality (if needed)
    function printReport() {
        window.print();
    }

    // Export data functionality placeholder
    function exportData(format) {
        console.log('Exporting data in format:', format);
        alert('Export functionality coming soon!');
    }

    // Make functions globally accessible if needed
    window.printReport = printReport;
    window.exportData = exportData;

    console.log('Receptionist Dashboard loaded successfully');
});