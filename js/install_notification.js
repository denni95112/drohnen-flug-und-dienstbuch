/**
 * Install Notification Dialog Handler
 * Shows dialog asking user if they want to notify developer about installation
 */

(function() {
    'use strict';
    
    // Check if we should show the notification
    if (typeof window.showInstallNotification === 'undefined' || !window.showInstallNotification) {
        return;
    }
    
    const modal = document.getElementById('install-notification-modal');
    if (!modal) {
        return;
    }
    
    const yesButton = document.getElementById('install-notification-yes');
    const noButton = document.getElementById('install-notification-no');
    const messageContainer = document.getElementById('install-notification-message-container');
    const shareOrgCheckbox = document.getElementById('install-notification-share-org');
    const organizationField = document.getElementById('install-notification-organization');
    
    // Get CSRF token from page (should be in header or form)
    function getCSRFToken() {
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        if (csrfInput) {
            return csrfInput.value;
        }
        // Try to get from meta tag if available
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.getAttribute('content');
        }
        return null;
    }
    
    function showMessage(message, isError = false) {
        if (messageContainer) {
            messageContainer.innerHTML = '<p class="' + (isError ? 'error' : 'success') + '">' + 
                message.replace(/</g, '&lt;').replace(/>/g, '&gt;') + 
                '</p>';
        }
    }
    
    function closeModal() {
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    function disableButtons() {
        if (yesButton) yesButton.disabled = true;
        if (noButton) noButton.disabled = true;
    }
    
    function enableButtons() {
        if (yesButton) yesButton.disabled = false;
        if (noButton) noButton.disabled = false;
    }
    
    // Handle checkbox to enable/disable organization field
    if (shareOrgCheckbox && organizationField) {
        shareOrgCheckbox.addEventListener('change', function() {
            organizationField.disabled = !this.checked;
            if (!this.checked) {
                organizationField.value = '';
            }
        });
    }
    
    async function handleAction(action) {
        const csrfToken = getCSRFToken();
        if (!csrfToken) {
            showMessage('CSRF-Token nicht gefunden. Bitte Seite neu laden.', true);
            return;
        }
        
        disableButtons();
        showMessage('Wird verarbeitet...', false);
        
        try {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('csrf_token', csrfToken);
            
            // Include organization if action is send
            if (action === 'send' && organizationField) {
                const orgName = shareOrgCheckbox && shareOrgCheckbox.checked ? organizationField.value.trim() : '';
                formData.append('organization', orgName);
            }
            
            let response;
            try {
                const basePath = window.basePath || '';
                response = await fetch(`${basePath}api/install_notification.php`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
            } catch (networkError) {
                // Network error (CORS, connection refused, etc.)
                throw new Error('Netzwerkfehler: Die Anfrage konnte nicht gesendet werden. Bitte überprüfen Sie Ihre Internetverbindung.');
            }
            
            const responseText = await response.text();
            
            if (!response.ok) {
                // Try to parse error message from response
                let errorMsg = `HTTP Fehler: ${response.status}`;
                if (response.status === 0) {
                    errorMsg = 'Verbindungsfehler: Die Anfrage konnte nicht abgeschlossen werden. Bitte überprüfen Sie Ihre Internetverbindung.';
                } else if (responseText) {
                    try {
                        const errorData = JSON.parse(responseText);
                        if (errorData.error) {
                            errorMsg = errorData.error;
                        }
                    } catch (e) {
                        // If not JSON, use the text as error
                        if (responseText.length < 200) {
                            errorMsg = responseText;
                        }
                    }
                }
                throw new Error(errorMsg);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server hat HTML statt JSON zurückgegeben.');
            }
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error('Ungültige JSON-Antwort vom Server.');
            }
            
            if (data.success) {
                if (action === 'send') {
                    showMessage('Benachrichtigung erfolgreich gesendet!', false);
                } else if (action === 'dismiss') {
                    showMessage('Einstellung gespeichert.', false);
                }
                
                // Close modal after a short delay
                setTimeout(() => {
                    closeModal();
                    // Reload page to update config state
                    window.location.reload();
                }, 1500);
            } else {
                showMessage(data.error || 'Ein Fehler ist aufgetreten.', true);
                enableButtons();
            }
        } catch (error) {
            showMessage('Ein Fehler ist aufgetreten: ' + (error.message || 'Unbekannter Fehler'), true);
            enableButtons();
            console.error('Install notification error:', error);
        }
    }
    
    // Event listeners
    if (yesButton) {
        yesButton.addEventListener('click', function() {
            handleAction('send');
        });
    }
    
    if (noButton) {
        noButton.addEventListener('click', function() {
            handleAction('dismiss');
        });
    }
    
    // Close modal on outside click
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });
    
    // Show modal when page loads
    document.addEventListener('DOMContentLoaded', function() {
        if (modal) {
            modal.style.display = 'block';
        }
    });
})();

