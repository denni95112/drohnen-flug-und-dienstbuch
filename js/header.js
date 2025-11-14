// Extracted JavaScript from includes/header.php
function openAdminModal() {
    document.getElementById('admin-modal').style.display = 'block';
}

function closeAdminModal() {
    document.getElementById('admin-modal').style.display = 'none';
}

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('admin-modal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
};

function toggleMenu() {
    const menu = document.getElementById('nav-menu');
    if (menu.style.display === 'block') {
        menu.style.display = 'none';
    } else {
        menu.style.display = 'block';
    }
}

function toggleDropdown() {
    const dropdown = document.getElementById('nav-dropdown');
    if (dropdown.style.display === 'block') {
        dropdown.style.display = 'none';
    } else {
        dropdown.style.display = 'block';
    }
}

// Attach event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const navToggleBtn = document.getElementById('nav-toggle-btn');
    const navDropdownToggleBtn = document.getElementById('nav-dropdown-toggle-btn');
    const adminModalLink = document.getElementById('admin-modal-link');
    const adminModalClose = document.getElementById('admin-modal-close');
    
    if (navToggleBtn) {
        navToggleBtn.addEventListener('click', toggleMenu);
    }
    
    if (navDropdownToggleBtn) {
        navDropdownToggleBtn.addEventListener('click', toggleDropdown);
    }
    
    if (adminModalLink) {
        adminModalLink.addEventListener('click', function(e) {
            e.preventDefault();
            openAdminModal();
        });
    }
    
    if (adminModalClose) {
        adminModalClose.addEventListener('click', closeAdminModal);
    }
});

