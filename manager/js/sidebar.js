/**
 * Sidebar Navigation JavaScript
 * Handles sidebar toggle, navigation, and responsive behavior
 */

document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const overlay = createOverlay();

    // Create overlay for mobile
    function createOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        document.body.appendChild(overlay);
        return overlay;
    }

    // Toggle sidebar on button click
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (sidebar.classList.contains('active')) {
                overlay.style.display = 'block';
                setTimeout(() => overlay.style.opacity = '1', 10);
            } else {
                overlay.style.opacity = '0';
                setTimeout(() => overlay.style.display = 'none', 300);
            }
        });
    }

    // Close sidebar when clicking overlay
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('active');
        overlay.style.opacity = '0';
        setTimeout(() => overlay.style.display = 'none', 300);
    });

    // Close sidebar when clicking on a navigation item (mobile)
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            // Only close on mobile
            if (window.innerWidth < 768) {
                sidebar.classList.remove('active');
                overlay.style.opacity = '0';
                setTimeout(() => overlay.style.display = 'none', 300);
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('active');
            overlay.style.display = 'none';
            overlay.style.opacity = '0';
        }
    });

    // Highlight active navigation item based on current page
    const currentPage = window.location.pathname.split('/').pop() || 'managerDashboard.php';
    const navLinks = document.querySelectorAll('.sidebar-nav .nav-item');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || href === (currentPage === '' ? 'managerDashboard.php' : currentPage)) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    // Add smooth animations
    navItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        });
    });

    // Escape key closes sidebar on mobile
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && window.innerWidth < 768) {
            sidebar.classList.remove('active');
            overlay.style.opacity = '0';
            setTimeout(() => overlay.style.display = 'none', 300);
        }
    });
});
