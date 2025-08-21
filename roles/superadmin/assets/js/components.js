// Enhanced Sidebar and Navbar functionality
document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initNavbar();
    initAppUtils();
});

// Sidebar functionality with enhanced features
function initSidebar() {
    const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');
    const sidebar = document.getElementById('sidebar');

    // Active menu state management
    allSideMenu.forEach(item => {
        const li = item.parentElement;

        item.addEventListener('click', function () {
            allSideMenu.forEach(i => {
                i.parentElement.classList.remove('active');
            });
            li.classList.add('active');
        });
    });

    // Toggle sidebar with enhanced animation
    const menuBar = document.querySelector('#content nav .bx.bx-menu');

    if (menuBar && sidebar) {
        menuBar.addEventListener('click', function () {
            sidebar.classList.toggle('hide');
            
            // Add ripple effect to menu button
            createRippleEffect(this);
        });
    }

    // Handle responsive sidebar
    handleResponsiveSidebar();
    
    // Window resize handler
    window.addEventListener('resize', handleResponsiveSidebar);

    // Auto-set active menu based on current page
    setActiveMenuFromURL();
}

// Navbar functionality with enhanced features
function initNavbar() {
    // Search functionality with animations
    const searchButton = document.querySelector('#content nav form .form-input button');
    const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
    const searchForm = document.querySelector('#content nav form');
    const searchInput = document.querySelector('#content nav form .form-input input');

    if (searchButton && searchButtonIcon && searchForm) {
        searchButton.addEventListener('click', function (e) {
            if (window.innerWidth < 576) {
                e.preventDefault();
                searchForm.classList.toggle('show');
                
                if (searchForm.classList.contains('show')) {
                    searchButtonIcon.classList.replace('bx-search', 'bx-x');
                    // Focus on input after animation
                    setTimeout(() => {
                        searchInput.focus();
                    }, 300);
                } else {
                    searchButtonIcon.classList.replace('bx-x', 'bx-search');
                }
            }
        });

        // Add search input enhancement
        if (searchInput) {
            searchInput.addEventListener('focus', function() {
                searchForm.classList.add('focused');
            });
            
            searchInput.addEventListener('blur', function() {
                searchForm.classList.remove('focused');
            });
        }

        // Handle responsive search
        window.addEventListener('resize', function () {
            if (window.innerWidth > 576) {
                searchButtonIcon.classList.replace('bx-x', 'bx-search');
                searchForm.classList.remove('show');
            }
        });
    }

    // Enhanced dark mode toggle
    const switchMode = document.getElementById('switch-mode');

    if (switchMode) {
        // Load saved theme preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            switchMode.checked = true;
            document.body.classList.add('dark');
        }

        switchMode.addEventListener('change', function () {
            if (this.checked) {
                document.body.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                createThemeTransition();
            } else {
                document.body.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                createThemeTransition();
            }
        });
    }

    // Notification interaction
    const notification = document.querySelector('#content nav .notification');
    if (notification) {
        notification.addEventListener('click', function(e) {
            e.preventDefault();
            // Add notification click animation
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    }

    // Profile interaction
    const profile = document.querySelector('#content nav .profile');
    if (profile) {
        profile.addEventListener('click', function(e) {
            e.preventDefault();
            // Add profile click animation
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    }
}

// App utilities for enhanced functionality
function initAppUtils() {
    // Create global AppUtils object
    window.AppUtils = {
        setActiveSidebarItem: function(currentPage) {
            const allSideMenu = document.querySelectorAll('#sidebar .side-menu li');
            allSideMenu.forEach(item => {
                const link = item.querySelector('a');
                if (link && link.getAttribute('href') === currentPage) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        },

        updateNavbarTitle: function(title) {
            const pageTitle = document.getElementById('pageTitle');
            if (pageTitle) {
                pageTitle.textContent = title;
            }
        },

        toggleSidebar: function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('hide');
            }
        },

        toggleDarkMode: function() {
            const switchMode = document.getElementById('switch-mode');
            if (switchMode) {
                switchMode.checked = !switchMode.checked;
                switchMode.dispatchEvent(new Event('change'));
            }
        },

        updateNotificationCount: function(count) {
            const notificationNum = document.getElementById('notificationCount');
            if (notificationNum) {
                notificationNum.textContent = count;
                if (count > 0) {
                    notificationNum.style.display = 'flex';
                } else {
                    notificationNum.style.display = 'none';
                }
            }
        }
    };
}

// Helper functions
function handleResponsiveSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        if (window.innerWidth < 768) {
            sidebar.classList.add('hide');
        } else if (window.innerWidth > 1024) {
            // Auto-show sidebar on large screens
            sidebar.classList.remove('hide');
        }
    }
}

function setActiveMenuFromURL() {
    const currentPage = window.location.pathname.split('/').pop();
    if (currentPage && window.AppUtils) {
        window.AppUtils.setActiveSidebarItem(currentPage);
    }
}

function createRippleEffect(element) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = rect.width / 2 - size / 2;
    const y = rect.height / 2 - size / 2;
    
    ripple.style.cssText = `
        position: absolute;
        border-radius: 50%;
        transform: scale(0);
        animation: ripple 600ms linear;
        background-color: rgba(255, 255, 255, 0.7);
        left: ${x}px;
        top: ${y}px;
        width: ${size}px;
        height: ${size}px;
    `;
    
    element.style.position = 'relative';
    element.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

function createThemeTransition() {
    document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
    setTimeout(() => {
        document.body.style.transition = '';
    }, 300);
}

// Add CSS for ripple animation if not already present
if (!document.querySelector('#ripple-animation-style')) {
    const style = document.createElement('style');
    style.id = 'ripple-animation-style';
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// Export functions for external use
window.sidebarNavbarUtils = {
    toggleSidebar: function() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.toggle('hide');
        }
    },
    toggleDarkMode: function() {
        const switchMode = document.getElementById('switch-mode');
        if (switchMode) {
            switchMode.checked = !switchMode.checked;
            switchMode.dispatchEvent(new Event('change'));
        }
    },
    setActiveMenuItem: function(href) {
        if (window.AppUtils) {
            window.AppUtils.setActiveSidebarItem(href);
        }
    }
};

// Initialize tooltip functionality for enhanced UX
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[title]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const title = this.getAttribute('title');
            if (title) {
                const tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                tooltip.textContent = title;
                tooltip.style.cssText = `
                    position: absolute;
                    background: var(--dark);
                    color: var(--light);
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    pointer-events: none;
                    z-index: 9999;
                    opacity: 0;
                    transform: translateY(-100%);
                    transition: opacity 0.3s ease;
                `;
                
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
                
                setTimeout(() => {
                    tooltip.style.opacity = '1';
                }, 10);
                
                this.tooltipElement = tooltip;
                this.setAttribute('data-original-title', title);
                this.removeAttribute('title');
            }
        });
        
        element.addEventListener('mouseleave', function() {
            if (this.tooltipElement) {
                this.tooltipElement.remove();
                this.tooltipElement = null;
                this.setAttribute('title', this.getAttribute('data-original-title'));
                this.removeAttribute('data-original-title');
            }
        });
    });
}

// Initialize tooltips after DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initTooltips, 100);
});