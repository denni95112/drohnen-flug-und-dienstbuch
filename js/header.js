// Modern Navigation JavaScript
function htmlEscape(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function openAdminModal(isLogout = false) {
    const modal = document.getElementById('admin-modal');
    const loginContent = document.getElementById('admin-login-content');
    const logoutContent = document.getElementById('admin-logout-content');
    const modalTitle = document.getElementById('admin-modal-title');
    const messageContainer = document.getElementById('admin-message-container');
    const logoutMessageContainer = document.getElementById('admin-logout-message-container');
    
    // Clear any previous messages
    if (messageContainer) messageContainer.innerHTML = '';
    if (logoutMessageContainer) logoutMessageContainer.innerHTML = '';
    
    if (isLogout) {
        // Show logout content
        if (loginContent) loginContent.style.display = 'none';
        if (logoutContent) logoutContent.style.display = 'block';
        if (modalTitle) modalTitle.textContent = 'Zu normalem Benutzer wechseln';
    } else {
        // Show login content
        if (loginContent) loginContent.style.display = 'block';
        if (logoutContent) logoutContent.style.display = 'none';
        if (modalTitle) modalTitle.textContent = 'Admin Login';
    }
    
    modal.style.display = 'block';
}

function closeAdminModal() {
    const modal = document.getElementById('admin-modal');
    const messageContainer = document.getElementById('admin-message-container');
    const logoutMessageContainer = document.getElementById('admin-logout-message-container');
    
    // Clear messages when closing
    if (messageContainer) messageContainer.innerHTML = '';
    if (logoutMessageContainer) logoutMessageContainer.innerHTML = '';
    
    modal.style.display = 'none';
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
    const fileNameWithoutPath = fileName.replace('pages/', '');
    
    const menuLinks = document.querySelectorAll('.nav-menu a[href]');
    menuLinks.forEach(link => {
        const linkPath = link.getAttribute('href');
        const linkPathWithoutDir = linkPath.replace('pages/', '');
        if (linkPath === fileName || linkPathWithoutDir === fileName || 
            (fileName === '' && (linkPath === 'pages/dashboard.php' || linkPath === 'dashboard.php'))) {
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
    
    // Handle admin icon click (for logout when already admin)
    const adminLogoutIcon = document.getElementById('admin-logout-icon');
    if (adminLogoutIcon) {
        adminLogoutIcon.addEventListener('click', function(e) {
            e.preventDefault();
            openAdminModal(true);
        });
    }
    
    // Handle admin logout form submission via AJAX
    const adminLogoutConfirm = document.getElementById('admin-logout-confirm');
    if (adminLogoutConfirm) {
        adminLogoutConfirm.addEventListener('click', async function(e) {
            e.preventDefault();
            
            const logoutMessageContainer = document.getElementById('admin-logout-message-container');
            const logoutButton = adminLogoutConfirm;
            
            // Clear previous messages
            if (logoutMessageContainer) {
                logoutMessageContainer.innerHTML = '';
            }
            
            // Disable button during request
            if (logoutButton) {
                logoutButton.disabled = true;
                logoutButton.textContent = 'Wird verarbeitet...';
            }
            
            try {
                const formData = new FormData();
                formData.append('admin_logout', '1');
                
                // Get CSRF token from the form (it should be in the admin-login-form)
                const csrfInput = document.querySelector('#admin-login-form input[name="csrf_token"]');
                if (!csrfInput) {
                    throw new Error('CSRF-Token nicht gefunden. Bitte Seite neu laden.');
                }
                formData.append('csrf_token', csrfInput.value);
                
                const basePath = window.basePath || '';
                const response = await fetch(`${basePath}api/admin.php`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                // Get response text first (can only read once)
                const responseText = await response.text();
                
                // Check if response is OK
                if (!response.ok) {
                    throw new Error(responseText || `HTTP Fehler: ${response.status}`);
                }
                
                // Check content type before parsing JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If we got HTML, it means the page output HTML before processing the request
                    throw new Error('Server hat HTML statt JSON zurückgegeben. Möglicherweise wurde die Seite vor der Verarbeitung der Anfrage ausgegeben.');
                }
                
                // Parse JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error('Ungültige JSON-Antwort vom Server: ' + responseText.substring(0, 100));
                }
                
                if (data.success) {
                    // Show success message
                    if (logoutMessageContainer) {
                        logoutMessageContainer.innerHTML = '<p class="admin-success">' + 
                            htmlEscape(data.message || 'Erfolgreich zu normalem Benutzer zurückgewechselt!') + 
                            '</p>';
                    }
                    
                    // Close modal after a short delay to show success message
                    setTimeout(() => {
                        closeAdminModal();
                        // Reload page to update admin status
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    if (logoutMessageContainer) {
                        logoutMessageContainer.innerHTML = '<p class="admin-error">' + 
                            (data.error || 'Ein Fehler ist aufgetreten.') + 
                            '</p>';
                    }
                    
                    // Re-enable button
                    if (logoutButton) {
                        logoutButton.disabled = false;
                        logoutButton.textContent = 'Zu normalem Benutzer wechseln';
                    }
                }
            } catch (error) {
                // Show error message with actual error details
                let errorMessage = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
                if (error.message) {
                    errorMessage = error.message;
                }
                
                if (logoutMessageContainer) {
                    logoutMessageContainer.innerHTML = '<p class="admin-error">' + 
                        htmlEscape(errorMessage) + 
                        '</p>';
                }
                
                // Re-enable button
                if (logoutButton) {
                    logoutButton.disabled = false;
                    logoutButton.textContent = 'Zu normalem Benutzer wechseln';
                }
                
                console.error('Admin logout error:', error);
            }
        });
    }
    
    // Handle admin login form submission via AJAX
    const adminLoginForm = document.getElementById('admin-login-form');
    if (adminLoginForm) {
        adminLoginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Only process if login content is visible (not logout)
            const loginContent = document.getElementById('admin-login-content');
            if (loginContent && loginContent.style.display === 'none') {
                return;
            }
            
            const formData = new FormData(adminLoginForm);
            const messageContainer = document.getElementById('admin-message-container');
            const submitButton = adminLoginForm.querySelector('button[type="submit"]');
            const passwordInput = document.getElementById('admin_password');
            
            // Clear previous messages
            if (messageContainer) {
                messageContainer.innerHTML = '';
            }
            
            // Disable submit button during request
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Wird verarbeitet...';
            }
            
            try {
                const basePath = window.basePath || '';
                const response = await fetch(`${basePath}api/admin.php`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                // Get response text first (can only read once)
                const responseText = await response.text();
                
                // Parse JSON response
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    // If JSON parsing fails, show generic error
                    throw new Error('Ungültige Antwort vom Server: ' + responseText.substring(0, 100));
                }
                
                // Check if response is OK or if data indicates success
                if (!response.ok || !data.success) {
                    // Extract error message from response
                    const errorMessage = data.error || `HTTP Fehler: ${response.status}`;
                    
                    // Show error message
                    if (messageContainer) {
                        messageContainer.innerHTML = '<p class="admin-error">' + 
                            htmlEscape(errorMessage) + 
                            '</p>';
                    }
                    
                    // Re-enable submit button
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = 'Als Admin anmelden';
                    }
                    
                    // Keep modal open - don't close it
                    return;
                }
                
                // Success case
                if (data.success) {
                    // Show success message
                    if (messageContainer) {
                        messageContainer.innerHTML = '<p class="admin-success">' + 
                            htmlEscape(data.message || 'Erfolgreich als Admin angemeldet!') + 
                            '</p>';
                    }
                    
                    // Clear password field
                    if (passwordInput) {
                        passwordInput.value = '';
                    }
                    
                    // Close modal after a short delay to show success message
                    setTimeout(() => {
                        closeAdminModal();
                        // Reload page to update admin status
                        window.location.reload();
                    }, 1500);
                }
            } catch (error) {
                // Show error message with actual error details
                let errorMessage = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
                if (error.message) {
                    errorMessage = error.message;
                }
                
                if (messageContainer) {
                    messageContainer.innerHTML = '<p class="admin-error">' + 
                        htmlEscape(errorMessage) + 
                        '</p>';
                }
                
                // Re-enable submit button
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Als Admin anmelden';
                }
                
                console.error('Admin login error:', error);
            }
        });
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

