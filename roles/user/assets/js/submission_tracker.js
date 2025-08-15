// Sidebar menu functionality
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

// Search documents in table
const searchInput = document.querySelector('#content nav form .form-input input');
const tableRows = document.querySelectorAll('.submission-table tbody tr');

searchInput.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    
    tableRows.forEach(row => {
        const docType = row.cells[0].textContent.toLowerCase();
        const description = row.cells[1].textContent.toLowerCase();
        
        if (docType.includes(searchTerm) || description.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Responsive sidebar behavior
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

// Auto-refresh page every 5 minutes to update latest uploads
setTimeout(function() {
    location.reload();
}, 300000); // 5 minutes

// Add click event to refresh button
const refreshBtn = document.querySelector('.head i.bx-refresh');
if (refreshBtn) {
    refreshBtn.addEventListener('click', function() {
        location.reload();
    });
}

// Filter functionality
const filterBtn = document.querySelector('.head i.bx-filter');
if (filterBtn) {
    filterBtn.addEventListener('click', function() {
        const filterOptions = ['All', 'Uploaded', 'Not Uploaded'];
        const selectedFilter = prompt('Filter documents by:\n\n1. All\n2. Uploaded\n3. Not Uploaded\n\nEnter option number (1-3):');
        
        if (selectedFilter && selectedFilter >= 1 && selectedFilter <= 3) {
            filterDocuments(filterOptions[selectedFilter - 1]);
        }
    });
}

// Filter documents function
function filterDocuments(filter) {
    tableRows.forEach(row => {
        const statusCell = row.cells[2];
        const isUploaded = statusCell.textContent.includes('Uploaded') && !statusCell.textContent.includes('Not Uploaded');
        
        switch(filter) {
            case 'All':
                row.style.display = '';
                break;
            case 'Uploaded':
                row.style.display = isUploaded ? '' : 'none';
                break;
            case 'Not Uploaded':
                row.style.display = !isUploaded ? '' : 'none';
                break;
        }
    });
}