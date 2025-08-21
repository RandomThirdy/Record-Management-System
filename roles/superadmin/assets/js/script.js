// ======================================
// MAIN SCRIPT.JS - ORGANIZED VERSION
// ======================================

document.addEventListener('DOMContentLoaded', function() {
    // ======================================
    // FORM SPECIFIC FUNCTIONALITY
    // (Keep for compatibility with other pages)
    // ======================================
    
    // Handle fund type selection
    const fundTypeSelect = document.getElementById('fund_type');
    const bankGroup = document.getElementById('bank_group');
    const officeGroup = document.getElementById('office_group');
    
    if (fundTypeSelect) {
        fundTypeSelect.addEventListener('change', function() {
            const fundType = this.value;
            
            if (fundType === 'General Fund') {
                bankGroup.style.display = 'block';
                officeGroup.style.display = 'block';
            } else {
                bankGroup.style.display = 'block';
                officeGroup.style.display = 'none';
            }
        });
    }
    
    // Handle office selection for sub-offices
    const officeSelect = document.getElementById('office_id');
    const subOfficeGroup = document.getElementById('sub_office_group');
    
    if (officeSelect) {
        officeSelect.addEventListener('change', function() {
            const officeId = this.value;
            
            if (officeId) {
                fetch(`includes/get_sub_offices.php?office_id=${officeId}`)
                    .then(response => response.json())
                    .then(data => {
                        const subOfficeSelect = document.getElementById('sub_office_id');
                        subOfficeSelect.innerHTML = '<option value="">Select Sub Office (Optional)</option>';
                        
                        if (data.length > 0) {
                            data.forEach(office => {
                                const option = document.createElement('option');
                                option.value = office.id;
                                option.textContent = office.name;
                                subOfficeSelect.appendChild(option);
                            });
                            subOfficeGroup.style.display = 'block';
                        } else {
                            subOfficeGroup.style.display = 'none';
                        }
                    });
            } else {
                subOfficeGroup.style.display = 'none';
            }
        });
    }
    
    // Handle expense type selection
    const expenseTypeSelect = document.getElementById('expense_type');
    const taxGroup = document.getElementById('tax_group');
    const totalGroup = document.getElementById('total_group');
    
    if (expenseTypeSelect) {
        expenseTypeSelect.addEventListener('change', function() {
            const expenseType = this.value;
            
            if (expenseType === 'Cash Advance') {
                taxGroup.style.display = 'none';
                totalGroup.style.display = 'none';
                document.getElementById('tax').value = '0';
                updateTotal();
            } else {
                taxGroup.style.display = 'block';
                totalGroup.style.display = 'block';
            }
        });
    }
    
    // Calculate total automatically
    const amountInput = document.getElementById('amount');
    const taxInput = document.getElementById('tax');
    
    if (amountInput && taxInput) {
        amountInput.addEventListener('input', updateTotal);
        taxInput.addEventListener('input', updateTotal);
    }
    
    function updateTotal() {
        const amount = parseFloat(amountInput.value) || 0;
        const tax = parseFloat(taxInput.value) || 0;
        const total = amount + tax;
        
        const totalInput = document.getElementById('total');
        if (totalInput) {
            totalInput.value = total.toFixed(2);
        }
    }
});

// ======================================
// SIDEBAR FUNCTIONALITY
// ======================================

// Handle sidebar menu active states
const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');

allSideMenu.forEach(item => {
    const li = item.parentElement;

    item.addEventListener('click', function () {
        allSideMenu.forEach(i => {
            i.parentElement.classList.remove('active');
        });
        li.classList.add('active');
    });
});

// Toggle sidebar visibility
const menuBar = document.querySelector('#content nav .bx.bx-menu');
const sidebar = document.getElementById('sidebar');

if (menuBar && sidebar) {
    menuBar.addEventListener('click', function () {
        sidebar.classList.toggle('hide');
    });
}

// ======================================
// NAVBAR FUNCTIONALITY
// ======================================

// Handle navbar search functionality
const searchButton = document.querySelector('#content nav form .form-input button');
const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
const searchForm = document.querySelector('#content nav form');

if (searchButton && searchButtonIcon && searchForm) {
    searchButton.addEventListener('click', function (e) {
        if(window.innerWidth < 576) {
            e.preventDefault();
            searchForm.classList.toggle('show');
            if(searchForm.classList.contains('show')) {
                searchButtonIcon.classList.replace('bx-search', 'bx-x');
            } else {
                searchButtonIcon.classList.replace('bx-x', 'bx-search');
            }
        }
    });
}

// Handle dark mode toggle
const switchModeToggle = document.getElementById('switch-mode');

if (switchModeToggle) {
    switchModeToggle.addEventListener('change', function () {
        if(this.checked) {
            document.body.classList.add('dark');
        } else {
            document.body.classList.remove('dark');
        }
    });
}

// ======================================
// RESPONSIVE FUNCTIONALITY
// ======================================

// Initial responsive setup
function initializeResponsive() {
    if(window.innerWidth < 768 && sidebar) {
        sidebar.classList.add('hide');
    } else if(window.innerWidth > 576 && searchButtonIcon && searchForm) {
        searchButtonIcon.classList.replace('bx-x', 'bx-search');
        searchForm.classList.remove('show');
    }
}

// Handle window resize events
window.addEventListener('resize', function () {
    if(this.innerWidth > 576 && searchButtonIcon && searchForm) {
        searchButtonIcon.classList.replace('bx-x', 'bx-search');
        searchForm.classList.remove('show');
    }
});

// Initialize responsive behavior on load
initializeResponsive();

// ======================================
// UTILITY FUNCTIONS
// ======================================

// Function to set active sidebar menu item based on current page
function setActiveSidebarItem(pageName) {
    const allSideMenuItems = document.querySelectorAll('#sidebar .side-menu.top li');
    
    allSideMenuItems.forEach(item => {
        const link = item.querySelector('a');
        if (link && link.getAttribute('href').includes(pageName)) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

// Function to update page title in navbar
function updateNavbarTitle(title) {
    const pageTitle = document.getElementById('pageTitle');
    if (pageTitle) {
        pageTitle.textContent = title;
    }
}

// Function to update notification count
function updateNotificationCount(count) {
    const notificationCount = document.getElementById('notificationCount');
    if (notificationCount) {
        notificationCount.textContent = count;
        notificationCount.style.display = count > 0 ? 'flex' : 'none';
    }
}

// Function to update user avatar
function updateUserAvatar(avatarUrl) {
    const userAvatar = document.getElementById('userAvatar');
    if (userAvatar && avatarUrl) {
        userAvatar.src = avatarUrl;
    }
}

// ======================================
// EXPORT FUNCTIONS FOR OTHER MODULES
// ======================================

// Make utility functions available globally for other scripts
window.AppUtils = {
    setActiveSidebarItem: setActiveSidebarItem,
    updateNavbarTitle: updateNavbarTitle,
    updateNotificationCount: updateNotificationCount,
    updateUserAvatar: updateUserAvatar
};