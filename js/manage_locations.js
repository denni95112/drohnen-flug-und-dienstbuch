// Manage Locations JavaScript with API integration

// Get CSRF token
function getCsrfToken() {
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    return csrfInput ? csrfInput.value : '';
}

// Generate unique request ID
function generateRequestId() {
    return Date.now().toString(36) + Math.random().toString(36).substring(2);
}

// Show message
function showMessage(message, type = 'success') {
    const container = document.getElementById(type === 'success' ? 'message-container' : 'error-container');
    if (container) {
        container.innerHTML = `<p class="${type}">${escapeHtml(message)}</p>`;
        setTimeout(() => {
            container.innerHTML = '';
        }, 5000);
    }
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Format date for display
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('de-DE');
}

// Fetch locations from API
async function fetchLocations(filterTraining = false) {
    const tbody = document.getElementById('locations-tbody');
    const loading = document.getElementById('loading-indicator');
    
    if (loading) loading.style.display = 'block';
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/locations.php?action=list`);
        const data = await response.json();
        
        if (data.success && tbody) {
            tbody.innerHTML = '';
            
            let locations = data.data.locations;
            
            // Apply filter if needed
            if (filterTraining) {
                locations = locations.filter(loc => !loc.training);
            }
            
            if (locations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10">Keine Standorte gefunden</td></tr>';
            } else {
                locations.forEach(location => {
                    const row = document.createElement('tr');
                    if (!location.training) {
                        row.className = 'training-false';
                    }
                    
                    row.innerHTML = `
                        <td data-label="ID">${escapeHtml(location.id)}</td>
                        <td data-label="Standortname">${escapeHtml(location.location_name)}</td>
                        <td data-label="Breitengrad">${escapeHtml(location.latitude)}</td>
                        <td data-label="Längengrad">${escapeHtml(location.longitude)}</td>
                        <td data-label="Beschreibung">${escapeHtml(location.description || '')}</td>
                        <td data-label="Erstellt am">${formatDate(location.created_at)}</td>
                        <td data-label="Einsatz">${location.training ? 'Nein' : 'Ja'}</td>
                        <td data-label="Datei hochladen">
                            ${!location.file_path ? `
                                <form class="upload-file-form" data-location-id="${location.id}" enctype="multipart/form-data">
                                    <input type="file" name="location_file" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx" required>
                                    <br><br>
                                    <button type="submit" class="button-full">Hochladen</button>
                                </form>
                            ` : '<p>Datei bereits hochgeladen</p>'}
                        </td>
                        <td data-label="Datei herunterladen">
                            ${location.file_path ? `
                                <a href="${window.basePath || ''}pages/manage_locations.php?download_file=true&location_id=${location.id}">
                                    <button type="button" class="button-full">Herunterladen</button>
                                </a>
                            ` : '<button type="button" disabled>Keine Datei</button>'}
                        </td>
                        <td data-label="Aktionen">
                            <button type="button" class="button-full delete-location-btn" data-location-id="${location.id}">
                                Löschen
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
                
                // Attach event listeners
                attachEventListeners();
            }
        } else {
            showMessage(data.error || 'Fehler beim Laden der Standorte', 'error');
        }
    } catch (error) {
        console.error('Error fetching locations:', error);
        showMessage('Fehler beim Laden der Standorte: ' + error.message, 'error');
    } finally {
        if (loading) loading.style.display = 'none';
    }
}

// Add location
async function addLocation(locationData) {
    const requestId = generateRequestId();
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/locations.php?action=create`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                location_name: locationData.name,
                latitude: parseFloat(locationData.latitude),
                longitude: parseFloat(locationData.longitude),
                description: locationData.description || null,
                training: locationData.training,
                request_id: requestId,
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Standort erfolgreich hinzugefügt', 'success');
            document.getElementById('add-location-form').reset();
            await fetchLocations();
        } else {
            showMessage(data.error || 'Fehler beim Hinzufügen des Standorts', 'error');
        }
    } catch (error) {
        console.error('Error adding location:', error);
        showMessage('Fehler beim Hinzufügen des Standorts: ' + error.message, 'error');
    }
}

// Upload file
async function uploadFile(locationId, fileInput) {
    const file = fileInput.files[0];
    if (!file) {
        showMessage('Bitte wählen Sie eine Datei aus', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('location_file', file);
    formData.append('location_id', locationId);
    formData.append('csrf_token', getCsrfToken());
    
    const submitBtn = fileInput.closest('form').querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Wird hochgeladen...';
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/locations.php?action=upload`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Datei erfolgreich hochgeladen und verschlüsselt', 'success');
            await fetchLocations();
        } else {
            showMessage(data.error || 'Fehler beim Hochladen der Datei', 'error');
        }
    } catch (error) {
        console.error('Error uploading file:', error);
        showMessage('Fehler beim Hochladen der Datei: ' + error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

// Delete location
async function deleteLocation(locationId) {
    if (!confirm('Standort wirklich löschen?')) {
        return;
    }
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/locations.php?id=${locationId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Standort erfolgreich gelöscht', 'success');
            await fetchLocations();
        } else {
            showMessage(data.error || 'Fehler beim Löschen des Standorts', 'error');
        }
    } catch (error) {
        console.error('Error deleting location:', error);
        showMessage('Fehler beim Löschen des Standorts: ' + error.message, 'error');
    }
}

// Attach event listeners
function attachEventListeners() {
    // Delete buttons
    document.querySelectorAll('.delete-location-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const locationId = this.getAttribute('data-location-id');
            deleteLocation(locationId);
        });
    });
    
    // File upload forms
    document.querySelectorAll('.upload-file-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const locationId = this.getAttribute('data-location-id');
            const fileInput = this.querySelector('input[type="file"]');
            uploadFile(locationId, fileInput);
        });
    });
}

// Set location from GPS
function setLocation() {
    const spinner = document.getElementById('location-spinner');
    const buttonText = document.getElementById('set-location-text');
    const setLocationBtn = document.getElementById('set-location-btn');
    
    const originalText = buttonText ? buttonText.textContent : 'Position automatisch setzen';
    
    if (spinner) spinner.style.display = 'inline-block';
    if (buttonText) buttonText.textContent = 'GPS wird gesucht...';
    if (setLocationBtn) setLocationBtn.disabled = true;
    
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            
            if (spinner) spinner.style.display = 'none';
            if (buttonText) buttonText.textContent = originalText;
            if (setLocationBtn) setLocationBtn.disabled = false;
        }, function(error) {
            alert('Fehler beim Abrufen der GPS-Daten: ' + error.message);
            
            if (spinner) spinner.style.display = 'none';
            if (buttonText) buttonText.textContent = originalText;
            if (setLocationBtn) setLocationBtn.disabled = false;
        });
    } else {
        alert('Geolocation wird von diesem Browser nicht unterstützt.');
        
        if (spinner) spinner.style.display = 'none';
        if (buttonText) buttonText.textContent = originalText;
        if (setLocationBtn) setLocationBtn.disabled = false;
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Load locations
    fetchLocations();
    
    // Add location form
    const addForm = document.getElementById('add-location-form');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const name = document.getElementById('location_name').value.trim();
            const latitude = document.getElementById('latitude').value;
            const longitude = document.getElementById('longitude').value;
            const description = document.getElementById('description').value.trim();
            const trainingCheckbox = document.getElementById('training');
            const training = trainingCheckbox ? trainingCheckbox.checked : true;
            
            if (!name || !latitude || !longitude) {
                showMessage('Bitte füllen Sie alle erforderlichen Felder aus.', 'error');
                return;
            }
            
            addLocation({ name, latitude, longitude, description, training });
        });
    }
    
    // GPS button
    const setLocationBtn = document.getElementById('set-location-btn');
    if (setLocationBtn) {
        setLocationBtn.addEventListener('click', setLocation);
    }
    
    // Filter checkbox
    const filterCheckbox = document.getElementById('filter_training');
    const applyFilterBtn = document.getElementById('apply-filter-btn');
    if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', function() {
            const filterTraining = filterCheckbox ? filterCheckbox.checked : false;
            fetchLocations(filterTraining);
        });
    }
});
