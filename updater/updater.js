/**
 * Updater JavaScript
 * Handles update checking and execution
 */

(function() {
    'use strict';
    
    const config = window.updaterConfig || {};
    const apiUrl = config.basePath + 'updater/updater_api.php';
    let isUpdating = false;
    let updateCheckInProgress = false;
    
    // DOM elements
    const checkBtn = document.getElementById('check-updates-btn');
    const updateBtn = document.getElementById('update-now-btn');
    const reloadBtn = document.getElementById('reload-page-btn');
    const errorContainer = document.getElementById('error-message-container');
    const successContainer = document.getElementById('success-message-container');
    const updateAvailableSection = document.getElementById('update-available-section');
    const noUpdateSection = document.getElementById('no-update-section');
    const updateProgressSection = document.getElementById('update-progress-section');
    const updateCompleteSection = document.getElementById('update-complete-section');
    const progressBarFill = document.getElementById('progress-bar-fill');
    const progressText = document.getElementById('progress-text');
    const updateStatus = document.getElementById('update-status');
    const newVersionBadge = document.getElementById('new-version-badge');
    const releaseNotes = document.getElementById('release-notes');
    const releaseUrl = document.getElementById('release-url');
    const updateResults = document.getElementById('update-results');
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we have initial update info
        if (config.updateInfo) {
            handleUpdateCheckResult(config.updateInfo);
        }
        
        // Setup event listeners
        if (checkBtn) {
            checkBtn.addEventListener('click', handleCheckUpdates);
        }
        
        if (updateBtn) {
            updateBtn.addEventListener('click', handleUpdateNow);
        }
        
        if (reloadBtn) {
            reloadBtn.addEventListener('click', function() {
                window.location.reload();
            });
        }
    });
    
    /**
     * Handle check for updates button click
     */
    function handleCheckUpdates() {
        if (updateCheckInProgress) {
            return;
        }
        
        updateCheckInProgress = true;
        setButtonLoading(checkBtn, true);
        hideMessages();
        
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'check',
                csrf_token: config.csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            updateCheckInProgress = false;
            setButtonLoading(checkBtn, false);
            
            if (data.success && data.data) {
                handleUpdateCheckResult(data.data);
            } else {
                showError(data.error || 'Fehler beim Prüfen auf Updates');
            }
        })
        .catch(error => {
            updateCheckInProgress = false;
            setButtonLoading(checkBtn, false);
            showError('Netzwerkfehler: ' + error.message);
        });
    }
    
    /**
     * Handle update check result
     */
    function handleUpdateCheckResult(result) {
        hideAllSections();
        
        // Debug: log the result
        console.log('Update check result:', result);
        
        if (result.error) {
            showError(result.error);
            return;
        }
        
        if (result.available) {
            // Show update available section
            if (updateAvailableSection) {
                updateAvailableSection.style.display = 'block';
            }
            
            // Set new version
            if (newVersionBadge) {
                newVersionBadge.textContent = 'v' + result.latest_version;
            }
            
            // Set release notes
            if (result.release_notes && releaseNotes) {
                releaseNotes.innerHTML = formatReleaseNotes(result.release_notes);
                releaseNotes.style.display = 'block';
            }
            
            // Set release URL
            if (result.release_url && releaseUrl) {
                releaseUrl.href = result.release_url;
                releaseUrl.style.display = 'block';
            }
        } else {
            // Show no update section
            if (noUpdateSection) {
                noUpdateSection.style.display = 'block';
            }
        }
    }
    
    /**
     * Format release notes (convert markdown-like text to HTML)
     */
    function formatReleaseNotes(notes) {
        if (!notes) return '';
        
        // Simple markdown-like formatting
        let html = notes
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/^### (.*$)/gim, '<h3>$1</h3>')
            .replace(/^## (.*$)/gim, '<h2>$1</h2>')
            .replace(/^# (.*$)/gim, '<h1>$1</h1>')
            .replace(/\n/g, '<br>');
        
        return html;
    }
    
    /**
     * Handle update now button click
     */
    function handleUpdateNow() {
        if (isUpdating) {
            return;
        }
        
        const version = newVersionBadge ? newVersionBadge.textContent.replace('v', '') : '';
        if (!version) {
            showError('Version nicht gefunden');
            return;
        }
        
        if (!confirm('Möchten Sie wirklich auf Version ' + version + ' aktualisieren?\n\nDas Update kann einige Minuten dauern. Bitte schließen Sie diese Seite nicht.')) {
            return;
        }
        
        isUpdating = true;
        hideAllSections();
        if (updateProgressSection) {
            updateProgressSection.style.display = 'block';
        }
        
        setProgress(0, 'Update wird gestartet...');
        
        // Perform update
        performUpdate(version);
    }
    
    /**
     * Perform the update
     */
    function performUpdate(version) {
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'update',
                version: version,
                csrf_token: config.csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            isUpdating = false;
            
            if (data.success) {
                setProgress(100, 'Update abgeschlossen!');
                
                // Show completion section
                setTimeout(() => {
                    if (updateProgressSection) {
                        updateProgressSection.style.display = 'none';
                    }
                    if (updateCompleteSection) {
                        updateCompleteSection.style.display = 'block';
                    }
                    
                    // Show results
                    if (updateResults) {
                        let resultsHtml = '<div class="update-success">';
                        resultsHtml += '<p><strong>Update erfolgreich abgeschlossen!</strong></p>';
                        resultsHtml += '<ul>';
                        resultsHtml += '<li>Dateien aktualisiert: ' + (data.files_updated || 0) + '</li>';
                        resultsHtml += '<li>Dateien entfernt: ' + (data.files_removed || 0) + '</li>';
                        if (data.backup_path) {
                            resultsHtml += '<li>Backup erstellt: ' + data.backup_path + '</li>';
                        }
                        resultsHtml += '</ul>';
                        resultsHtml += '<p>Bitte laden Sie die Seite neu, um die neue Version zu verwenden.</p>';
                        resultsHtml += '</div>';
                        updateResults.innerHTML = resultsHtml;
                    }
                }, 1000);
            } else {
                setProgress(0, 'Update fehlgeschlagen');
                
                // Format error message - preserve line breaks for better readability
                let errorMsg = data.error || 'Update fehlgeschlagen';
                if (data.missing_extensions && data.missing_extensions.length > 0) {
                    errorMsg = '<strong>Fehlende PHP-Erweiterungen:</strong> ' + data.missing_extensions.join(', ') + '<br><br>' + errorMsg;
                }
                
                // Show error with better formatting
                const errorContainer = document.getElementById('error-message-container');
                if (errorContainer) {
                    errorContainer.innerHTML = errorMsg.replace(/\n/g, '<br>');
                    errorContainer.style.display = 'block';
                }
                
                if (data.rollback) {
                    showError('Das Update wurde automatisch zurückgesetzt. Bitte versuchen Sie es später erneut.');
                }
                
                // Show update available section again
                setTimeout(() => {
                    if (updateProgressSection) {
                        updateProgressSection.style.display = 'none';
                    }
                    if (updateAvailableSection) {
                        updateAvailableSection.style.display = 'block';
                    }
                }, 5000);
            }
        })
        .catch(error => {
            isUpdating = false;
            setProgress(0, 'Fehler aufgetreten');
            showError('Netzwerkfehler: ' + error.message);
            
            // Show update available section again
            setTimeout(() => {
                if (updateProgressSection) {
                    updateProgressSection.style.display = 'none';
                }
                if (updateAvailableSection) {
                    updateAvailableSection.style.display = 'block';
                }
            }, 3000);
        });
    }
    
    /**
     * Set progress bar and text
     */
    function setProgress(percent, text) {
        if (progressBarFill) {
            progressBarFill.style.width = percent + '%';
        }
        if (progressText) {
            progressText.textContent = text || '';
        }
    }
    
    /**
     * Set button loading state
     */
    function setButtonLoading(button, loading) {
        if (!button) return;
        
        const text = button.querySelector('.btn-text');
        const spinner = button.querySelector('.btn-spinner');
        
        if (loading) {
            button.disabled = true;
            if (text) text.style.display = 'none';
            if (spinner) spinner.style.display = 'inline';
        } else {
            button.disabled = false;
            if (text) text.style.display = 'inline';
            if (spinner) spinner.style.display = 'none';
        }
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        hideMessages();
        if (errorContainer) {
            // Allow HTML in error messages for better formatting
            errorContainer.innerHTML = message.replace(/\n/g, '<br>');
            errorContainer.style.display = 'block';
        }
    }
    
    /**
     * Show success message
     */
    function showSuccess(message) {
        hideMessages();
        if (successContainer) {
            successContainer.textContent = message;
            successContainer.style.display = 'block';
        }
    }
    
    /**
     * Hide all messages
     */
    function hideMessages() {
        if (errorContainer) errorContainer.style.display = 'none';
        if (successContainer) successContainer.style.display = 'none';
    }
    
    /**
     * Hide all sections
     */
    function hideAllSections() {
        if (updateAvailableSection) updateAvailableSection.style.display = 'none';
        if (noUpdateSection) noUpdateSection.style.display = 'none';
        if (updateProgressSection) updateProgressSection.style.display = 'none';
        if (updateCompleteSection) updateCompleteSection.style.display = 'none';
    }
})();
