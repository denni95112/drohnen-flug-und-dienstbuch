// Modern Navigation JavaScript
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
    const backdrop = document.getElementById('nav-backdrop');
    const toggleBtn = document.getElementById('nav-toggle-btn');
    const isActive = menu.classList.contains('active');
    
    if (isActive) {
        menu.classList.remove('active');
        backdrop.classList.remove('active');
        toggleBtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    } else {
        menu.classList.add('active');
        backdrop.classList.add('active');
        toggleBtn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }
}

function toggleDropdown(event) {
    event.preventDefault();
    event.stopPropagation();
    const dropdown = document.getElementById('nav-dropdown');
    const toggleBtn = document.getElementById('nav-dropdown-toggle-btn');
    const isActive = dropdown.classList.contains('active');
    
    if (isActive) {
        dropdown.classList.remove('active');
        toggleBtn.classList.remove('active');
    } else {
        dropdown.classList.add('active');
        toggleBtn.classList.add('active');
    }
}

// Close menu when clicking backdrop
function closeMenuOnBackdrop(event) {
    if (event.target.id === 'nav-backdrop') {
        toggleMenu();
    }
}

// Set active menu item based on current page
function setActiveMenuItem() {
    const currentPath = window.location.pathname;
    const fileName = currentPath.split('/').pop() || 'dashboard.php';
    
    const menuLinks = document.querySelectorAll('.nav-menu a[href]');
    menuLinks.forEach(link => {
        const linkPath = link.getAttribute('href');
        if (linkPath === fileName || (fileName === '' && linkPath === 'dashboard.php')) {
            link.classList.add('active');
        }
    });
}

// Close menu on window resize if switching to desktop
function handleResize() {
    const menu = document.getElementById('nav-menu');
    const backdrop = document.getElementById('nav-backdrop');
    const toggleBtn = document.getElementById('nav-toggle-btn');
    
    if (window.innerWidth >= 768) {
        menu.classList.remove('active');
        backdrop.classList.remove('active');
        toggleBtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }
}

// Attach event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const navToggleBtn = document.getElementById('nav-toggle-btn');
    const navDropdownToggleBtn = document.getElementById('nav-dropdown-toggle-btn');
    const adminModalLink = document.getElementById('admin-modal-link');
    const adminModalClose = document.getElementById('admin-modal-close');
    const backdrop = document.getElementById('nav-backdrop');
    
    if (navToggleBtn) {
        navToggleBtn.addEventListener('click', toggleMenu);
    }
    
    if (navDropdownToggleBtn) {
        navDropdownToggleBtn.addEventListener('click', toggleDropdown);
    }
    
    if (backdrop) {
        backdrop.addEventListener('click', closeMenuOnBackdrop);
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
    
    // Set active menu item
    setActiveMenuItem();
    
    // Handle window resize
    window.addEventListener('resize', handleResize);
    
    // Close menu when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('nav-menu');
        const toggleBtn = document.getElementById('nav-toggle-btn');
        
        if (window.innerWidth < 768 && 
            menu.classList.contains('active') &&
            !menu.contains(event.target) &&
            !toggleBtn.contains(event.target)) {
            toggleMenu();
        }
    });
});

