// Category functionality
function toggleCategory(deptId, categoryKey) {
    const content = document.getElementById(`category-content-${deptId}-${categoryKey}`);
    const icon = document.getElementById(`icon-${deptId}-${categoryKey}`);
    const header = content ? content.previousElementSibling : null;
    
    if (!content || !icon) return;
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        icon.classList.remove('rotated');
        if (header) header.classList.remove('active');
    } else {
        content.classList.add('show');
        icon.classList.add('rotated');
        if (header) header.classList.add('active');
        
        // Load files for this category if not already loaded
        loadCategoryFiles(deptId, categoryKey);
    }
}

function showCategorySemester(deptId, categoryKey, semester) {
    // Update tab active state
    const tabs = document.querySelectorAll(`#category-content-${deptId}-${categoryKey} .semester-tab`);
    tabs.forEach(tab => tab.classList.remove('active'));
    if (event && event.target) {
        event.target.classList.add('active');
    }
    
    // Show/hide semester content
    const firstSemester = document.getElementById(`files-${deptId}-${categoryKey}-first`);
    const secondSemester = document.getElementById(`files-${deptId}-${categoryKey}-second`);
    
    if (semester === 'first') {
        if (firstSemester) firstSemester.style.display = 'grid';
        if (secondSemester) secondSemester.style.display = 'none';
    } else {
        if (firstSemester) firstSemester.style.display = 'none';
        if (secondSemester) secondSemester.style.display = 'grid';
    }
}

function loadCategoryFiles(deptId, categoryKey) {
    // Security check: Only load files for user's department
    if (deptId != userDepartmentId) {
        console.error('Access denied: Cannot load files from different department');
        return;
    }
    
    // AJAX call to load files from database for specific category
    fetch('../handlers/category_files.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            department_id: deptId,
            category: categoryKey,
            user_department_id: userDepartmentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderCategoryFiles(deptId, categoryKey, 'first', data.first_semester || []);
            renderCategoryFiles(deptId, categoryKey, 'second', data.second_semester || []);
        } else {
            console.error('Error loading category files:', data.message);
            if (data.message.includes('Access denied')) {
                alert('Access denied: You can only view files from your department.');
            }
        }
    })
    .catch(error => {
        console.error('Error loading category files:', error);
    });
}