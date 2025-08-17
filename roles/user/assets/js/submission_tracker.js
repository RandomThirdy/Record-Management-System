// ODCI/roles/user/assets/js/submission_tracker.js

document.addEventListener('DOMContentLoaded', function() {
    initializeSubmissionTracker();
});

function initializeSubmissionTracker() {
    setupFilterTabs();
    setupSearch();
    setupFilters();
    updateCategoryStats();
    animateStats();
}

// Filter tab functionality
function setupFilterTabs() {
    const tabs = document.querySelectorAll('.filter-tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const target = this.getAttribute('onclick')?.match(/'([^']+)'/)?.[1];
            if (target) {
                showSection(target);
            }
        });
    });
}

function showSection(sectionName) {
    // Remove active class from all tabs and sections
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Add active class to clicked tab
    event.target.closest('.filter-tab').classList.add('active');
    
    // Show corresponding section
    const targetSection = document.getElementById(`${sectionName}-section`);
    if (targetSection) {
        targetSection.classList.add('active');
        
        // Add entrance animation
        targetSection.style.opacity = '0';
        targetSection.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            targetSection.style.transition = 'all 0.3s ease';
            targetSection.style.opacity = '1';
            targetSection.style.transform = 'translateY(0)';
        }, 50);
    }
    
    // Update stats based on section
    updateSectionStats(sectionName);
}

// Search functionality
function setupSearch() {
    const searchInput = document.getElementById('submissionSearch');
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 300));
    }
}

function handleSearch() {
    const searchTerm = document.getElementById('submissionSearch').value.toLowerCase();
    const activeSection = document.querySelector('.content-section.active');
    
    if (!activeSection) return;
    
    // Search in different sections
    if (activeSection.id === 'overview-section') {
        searchInOverview(searchTerm);
    } else if (activeSection.id === 'pending-section') {
        searchInPending(searchTerm);
    } else if (activeSection.id === 'completed-section') {
        searchInCompleted(searchTerm);
    } else if (activeSection.id === 'recent-section') {
        searchInRecent(searchTerm);
    }
}

function searchInOverview(searchTerm) {
    const cards = document.querySelectorAll('.category-overview-card');
    
    cards.forEach(card => {
        const categoryName = card.querySelector('.category-info h4').textContent.toLowerCase();
        const shouldShow = categoryName.includes(searchTerm) || searchTerm === '';
        
        card.style.display = shouldShow ? 'block' : 'none';
        
        if (shouldShow && searchTerm !== '') {
            highlightText(card.querySelector('.category-info h4'), searchTerm);
        }
    });
}

function searchInPending(searchTerm) {
    const cards = document.querySelectorAll('.pending-card');
    
    cards.forEach(card => {
        const title = card.querySelector('.pending-info h4').textContent.toLowerCase();
        const shouldShow = title.includes(searchTerm) || searchTerm === '';
        
        card.style.display = shouldShow ? 'block' : 'none';
        
        if (shouldShow && searchTerm !== '') {
            highlightText(card.querySelector('.pending-info h4'), searchTerm);
        }
    });
}

function searchInCompleted(searchTerm) {
    const cards = document.querySelectorAll('.completed-card');
    
    cards.forEach(card => {
        const title = card.querySelector('.completed-info h4').textContent.toLowerCase();
        const shouldShow = title.includes(searchTerm) || searchTerm === '';
        
        card.style.display = shouldShow ? 'block' : 'none';
        
        if (shouldShow && searchTerm !== '') {
            highlightText(card.querySelector('.completed-info h4'), searchTerm);
        }
    });
}

function searchInRecent(searchTerm) {
    const items = document.querySelectorAll('.activity-item');
    
    items.forEach(item => {
        const fileName = item.querySelector('.activity-title').textContent.toLowerCase();
        const shouldShow = fileName.includes(searchTerm) || searchTerm === '';
        
        item.style.display = shouldShow ? 'flex' : 'none';
        
        if (shouldShow && searchTerm !== '') {
            highlightText(item.querySelector('.activity-title'), searchTerm);
        }
    });
}

// Filter functionality
function setupFilters() {
    const categoryFilter = document.getElementById('categoryFilter');
    const semesterFilter = document.getElementById('semesterFilter');
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', applyFilters);
    }
    
    if (semesterFilter) {
        semesterFilter.addEventListener('change', applyFilters);
    }
}

function applyFilters() {
    const categoryFilter = document.getElementById('categoryFilter').value;
    const semesterFilter = document.getElementById('semesterFilter').value;
    const activeSection = document.querySelector('.content-section.active');
    
    if (!activeSection) return;
    
    if (activeSection.id === 'overview-section') {
        filterOverview(categoryFilter);
    } else if (activeSection.id === 'completed-section') {
        filterCompleted(categoryFilter, semesterFilter);
    } else if (activeSection.id === 'recent-section') {
        filterRecent(categoryFilter);
    }
}

function filterOverview(categoryFilter) {
    const cards = document.querySelectorAll('.category-overview-card');
    
    cards.forEach(card => {
        const cardCategory = card.dataset.category;
        const shouldShow = !categoryFilter || cardCategory === categoryFilter;
        
        card.style.display = shouldShow ? 'block' : 'none';
        
        // Add filter animation
        if (shouldShow) {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.9)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '1';
                card.style.transform = 'scale(1)';
            }, Math.random() * 100);
        }
    });
}

function filterCompleted(categoryFilter, semesterFilter) {
    const cards = document.querySelectorAll('.completed-card');
    
    cards.forEach(card => {
        const cardTitle = card.querySelector('.completed-info h4').textContent;
        let shouldShow = true;
        
        // Filter by category if window.fileCategories exists
        if (categoryFilter && window.fileCategories) {
            const categoryName = window.fileCategories[categoryFilter]?.name || '';
            shouldShow = shouldShow && cardTitle.includes(categoryName);
        }
        
        // Filter by semester
        if (semesterFilter) {
            const semesterText = semesterFilter === 'first' ? 'First Semester' : 'Second Semester';
            const semesterItems = card.querySelectorAll('.semester-item .semester-info span');
            let hasSemester = false;
            
            semesterItems.forEach(item => {
                if (item.textContent.includes(semesterText)) {
                    hasSemester = true;
                }
            });
            
            shouldShow = shouldShow && hasSemester;
        }
        
        card.style.display = shouldShow ? 'block' : 'none';
    });
}

function filterRecent(categoryFilter) {
    const items = document.querySelectorAll('.activity-item');
    
    items.forEach(item => {
        const categoryTag = item.querySelector('.category-tag');
        let shouldShow = true;
        
        if (categoryFilter && categoryTag && window.fileCategories) {
            const categoryName = window.fileCategories[categoryFilter]?.name || '';
            shouldShow = categoryTag.textContent.trim() === categoryName;
        }
        
        item.style.display = shouldShow ? 'flex' : 'none';
    });
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function highlightText(element, searchTerm) {
    if (!element || !searchTerm) return;
    
    const originalText = element.textContent;
    const regex = new RegExp(`(${searchTerm})`, 'gi');
    const highlightedText = originalText.replace(regex, '<mark style="background: #fef3c7; padding: 2px 4px; border-radius: 3px;">$1</mark>');
    
    element.innerHTML = highlightedText;
}

// Update category statistics
function updateCategoryStats() {
    const categoryCards = document.querySelectorAll('.category-overview-card');
    
    categoryCards.forEach(card => {
        const categoryKey = card.dataset.category;
        
        // Add click handlers for category cards
        card.addEventListener('click', function() {
            showCategoryDetails(categoryKey);
        });
        
        // Add hover effects
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
}

function showCategoryDetails(categoryKey) {
    // Switch to completed section and filter by category
    showSection('completed');
    
    setTimeout(() => {
        document.getElementById('categoryFilter').value = categoryKey;
        applyFilters();
    }, 100);
}

// Animate statistics on load
function animateStats() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    statNumbers.forEach((stat, index) => {
        const finalValue = parseInt(stat.textContent);
        let currentValue = 0;
        const increment = Math.ceil(finalValue / 30);
        
        setTimeout(() => {
            const counter = setInterval(() => {
                currentValue += increment;
                
                if (currentValue >= finalValue) {
                    stat.textContent = finalValue;
                    clearInterval(counter);
                } else {
                    stat.textContent = currentValue;
                }
            }, 50);
        }, index * 200);
    });
    
    // Animate progress bars if any
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach((bar, index) => {
        const percentage = bar.dataset.percentage || 0;
        
        setTimeout(() => {
            bar.style.width = percentage + '%';
        }, index * 300);
    });
}

// Update section statistics based on active section
function updateSectionStats(sectionName) {
    // You can add specific statistics updates for each section here
    console.log(`Viewing ${sectionName} section`);
}

// Upload functionality
function uploadForCategory(category, semester) {
    // Redirect to folders page with pre-selected category and semester
    const url = new URL('folders.php', window.location.href);
    url.searchParams.set('category', category);
    url.searchParams.set('semester', semester);
    url.searchParams.set('action', 'upload');
    
    window.location.href = url.toString();
}

// Export functions for global access
window.showSection = showSection;
window.uploadForCategory = uploadForCategory;

// Add smooth scrolling for better UX
function smoothScrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Notification system for user feedback
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class='bx ${type === 'success' ? 'bx-check-circle' : 'bx-error-circle'}'></i>
        <span>${message}</span>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        font-family: 'Poppins', sans-serif;
        font-size: 14px;
        font-weight: 500;
        transform: translateX(400px);
        transition: transform 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Refresh data functionality
function refreshSubmissionData() {
    showNotification('Refreshing submission data...', 'info');
    
    // Simulate data refresh
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

// Export refresh function
window.refreshSubmissionData = refreshSubmissionData;