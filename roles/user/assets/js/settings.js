// Tab functionality
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.style.display = 'none';
        content.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => {
        btn.classList.remove('active');
        btn.style.background = 'transparent';
        btn.style.color = 'var(--dark)';
        btn.style.boxShadow = 'none';
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabName);
    if (selectedTab) {
        selectedTab.style.display = 'block';
        selectedTab.classList.add('active');
    }
    
    // Add active class to clicked button
    event.target.classList.add('active');
    event.target.style.background = 'linear-gradient(135deg, var(--blue) 0%, #2980b9 100%)';
    event.target.style.color = 'white';
    event.target.style.boxShadow = '0 4px 8px rgba(52, 152, 219, 0.3)';
}

// Profile picture preview
document.getElementById('profilePicture').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        // Check file size (max 2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            this.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
        this.style.borderColor = '#dc3545';
    } else {
        this.setCustomValidity('');
        this.style.borderColor = '#28a745';
    }
});

// Password strength indicator
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strength = getPasswordStrength(password);
    
    // You can add a visual password strength indicator here
    if (password.length < 8) {
        this.style.borderColor = '#dc3545';
    } else if (strength < 3) {
        this.style.borderColor = '#ffc107';
    } else {
        this.style.borderColor = '#28a745';
    }
});

function getPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    return strength;
}

// Modal functions
function showDeletionModal() {
    document.getElementById('deletionModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function hideDeletionModal() {
    document.getElementById('deletionModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('deletionForm').reset();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('deletionModal');
    if (event.target == modal) {
        hideDeletionModal();
    }
}

// Data export request
function requestDataExport() {
    if (confirm('Do you want to export all your account data? You will receive a download link via email within 24 hours.')) {
        // Show loading state
        event.target.innerHTML = '<i class="bx bx-loader-alt" style="animation: spin 1s linear infinite;"></i> Processing...';
        event.target.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
            alert('Data export request submitted successfully! You will receive a download link via email within 24 hours.');
            event.target.innerHTML = '<i class="bx bx-download"></i> Export Data';
            event.target.disabled = false;
        }, 2000);
    }
}

// Form reset function
function resetForm() {
    if (confirm('Are you sure you want to reset all changes?')) {
        location.reload();
    }
}

// Toggle switches functionality
document.addEventListener('DOMContentLoaded', function() {
    const toggles = document.querySelectorAll('input[type="checkbox"]');
    
    toggles.forEach(toggle => {
        if (toggle.id !== 'switch-mode') { // Exclude the main dark mode toggle
            toggle.addEventListener('change', function() {
                const slider = this.nextElementSibling;
                const knob = slider.nextElementSibling;
                
                if (this.checked) {
                    slider.style.background = 'linear-gradient(135deg, var(--blue), #2980b9)';
                    slider.style.boxShadow = '0 4px 12px rgba(52, 152, 219, 0.3)';
                    knob.style.transform = 'translateX(30px)';
                } else {
                    slider.style.background = '#ccc';
                    slider.style.boxShadow = 'none';
                    knob.style.transform = 'translateX(0)';
                }
            });
        }
    });
});

// Sidebar functionality (from original dashboard)
const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');

allSideMenu.forEach(item=> {
    const li = item.parentElement;

    item.addEventListener('click', function () {
        allSideMenu.forEach(i=> {
            i.parentElement.classList.remove('active');
        })
        li.classList.add('active');
    })
});

// Toggle sidebar
const menuBar = document.querySelector('#content nav .bx.bx-menu');
const sidebar = document.getElementById('sidebar');

menuBar.addEventListener('click', function () {
    sidebar.classList.toggle('hide');
})

// Search functionality
const searchButton = document.querySelector('#content nav form .form-input button');
const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
const searchForm = document.querySelector('#content nav form');

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
})

if(window.innerWidth < 768) {
    sidebar.classList.add('hide');
} else if(window.innerWidth > 576) {
    searchButtonIcon.classList.replace('bx-x', 'bx-search');
    searchForm.classList.remove('show');
}

window.addEventListener('resize', function () {
    if(this.innerWidth > 576) {
        searchButtonIcon.classList.replace('bx-x', 'bx-search');
        searchForm.classList.remove('show');
    }
})

// Dark mode toggle
const switchMode = document.getElementById('switch-mode');

switchMode.addEventListener('change', function () {
    if(this.checked) {
        document.body.classList.add('dark');
    } else {
        document.body.classList.remove('dark');
    }
})

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('[style*="animation: slideIn"]');
    alerts.forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);

// Smooth scrolling for form validation errors
function scrollToError(element) {
    element.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    });
    element.focus();
}

// Form validation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#dc3545';
                if (isValid) {
                    scrollToError(field);
                    isValid = false;
                }
            } else {
                field.style.borderColor = '#28a745';
            }
        });
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});

 // Add spin animation for loading states
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);