// Manage Pilots JavaScript with API integration

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

// Sort pilots based on selected sort option
function sortPilots(pilots, sortBy) {
    const sortedPilots = [...pilots];
    
    switch(sortBy) {
        case 'id':
            sortedPilots.sort((a, b) => (a.id || 0) - (b.id || 0));
            break;
        case 'name':
            sortedPilots.sort((a, b) => {
                const nameA = (a.name || '').toLowerCase();
                const nameB = (b.name || '').toLowerCase();
                return nameA.localeCompare(nameB);
            });
            break;
        case 'a1_a3_expiry':
            sortedPilots.sort((a, b) => {
                const dateA = a.a1_a3_license_valid_until || '';
                const dateB = b.a1_a3_license_valid_until || '';
                if (!dateA && !dateB) return 0;
                if (!dateA) return 1;
                if (!dateB) return -1;
                return dateA.localeCompare(dateB);
            });
            break;
        case 'a2_expiry':
            sortedPilots.sort((a, b) => {
                const dateA = a.a2_license_valid_until || '';
                const dateB = b.a2_license_valid_until || '';
                if (!dateA && !dateB) return 0;
                if (!dateA) return 1;
                if (!dateB) return -1;
                return dateA.localeCompare(dateB);
            });
            break;
        default:
            // Default to name sorting
            sortedPilots.sort((a, b) => {
                const nameA = (a.name || '').toLowerCase();
                const nameB = (b.name || '').toLowerCase();
                return nameA.localeCompare(nameB);
            });
    }
    
    return sortedPilots;
}

// Render pilots table
function renderPilotsTable(pilots) {
    const tbody = document.getElementById('pilots-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    pilots.forEach(pilot => {
                // Format license information
                const formatLicenseInfo = (licenseId, validUntil) => {
                    if (!licenseId && !validUntil) {
                        return '<span style="color: #999;">-</span>';
                    }
                    let info = '';
                    if (licenseId) {
                        info += `ID: ${escapeHtml(licenseId)}`;
                    }
                    if (validUntil) {
                        if (info) info += '<br>';
                        info += `Gültig bis: ${escapeHtml(validUntil)}`;
                    }
                    return info || '<span style="color: #999;">-</span>';
                };
                
                const a1A3Info = formatLicenseInfo(pilot.a1_a3_license_id, pilot.a1_a3_license_valid_until);
                const a2Info = formatLicenseInfo(pilot.a2_license_id, pilot.a2_license_valid_until);
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td data-label="ID">${escapeHtml(pilot.id)}</td>
                    <td data-label="Name">${escapeHtml(pilot.name)}</td>
                    <td data-label="Flugminuten">${escapeHtml(pilot.minutes_of_flights_needed ?? 45)}</td>
                    <td data-label="A1/A3 Fernpilotenschein">${a1A3Info}</td>
                    <td data-label="A2 Fernpilotenschein">${a2Info}</td>
                    <td class="actions-cell">
                        <button type="button" 
                                class="btn-edit edit-pilot-btn" 
                                data-pilot-id="${pilot.id}"
                                data-pilot-data='${JSON.stringify(pilot)}'>
                            Bearbeiten
                        </button>
                        <button type="button" 
                                class="btn-delete delete-pilot-btn" 
                                data-pilot-id="${pilot.id}">
                            Löschen
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
    });
    
    // Attach event listeners
    attachEventListeners();
}

// Store pilots data for sorting without re-fetching
let pilotsData = [];

// Fetch pilots from API
async function fetchPilots(useCache = false) {
    const tbody = document.getElementById('pilots-tbody');
    const loading = document.getElementById('loading-indicator');
    
    if (!useCache && loading) loading.style.display = 'block';
    
    try {
        // Use cached data if available and useCache is true
        if (useCache && pilotsData.length > 0) {
            const sortSelect = document.getElementById('sort-pilots');
            const sortBy = sortSelect ? sortSelect.value : 'name';
            const sortedPilots = sortPilots(pilotsData, sortBy);
            renderPilotsTable(sortedPilots);
            return;
        }
        
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/pilots.php?action=list`);
        const data = await response.json();
        
        if (data.success && tbody) {
            // Store pilots data
            pilotsData = data.data.pilots;
            
            // Get current sort option
            const sortSelect = document.getElementById('sort-pilots');
            const sortBy = sortSelect ? sortSelect.value : 'name';
            
            // Sort pilots
            const sortedPilots = sortPilots(pilotsData, sortBy);
            
            // Render table
            renderPilotsTable(sortedPilots);
        } else {
            showMessage(data.error || 'Fehler beim Laden der Piloten', 'error');
        }
    } catch (error) {
        console.error('Error fetching pilots:', error);
        showMessage('Fehler beim Laden der Piloten: ' + error.message, 'error');
    } finally {
        if (loading) loading.style.display = 'none';
    }
}

// Add pilot
async function addPilot(formData) {
    const requestId = generateRequestId();
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/pilots.php?action=create`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: formData.name,
                minutes_of_flights_needed: formData.minutes_of_flights_needed || 45,
                a1_a3_license_id: formData.a1_a3_license_id || null,
                a1_a3_license_valid_until: formData.a1_a3_license_valid_until || null,
                a2_license_id: formData.a2_license_id || null,
                a2_license_valid_until: formData.a2_license_valid_until || null,
                lock_on_invalid_license: formData.lock_on_invalid_license || '0',
                request_id: requestId,
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Pilot erfolgreich hinzugefügt', 'success');
            // Reset form
            document.getElementById('add-pilot-form').reset();
            await fetchPilots();
        } else {
            showMessage(data.error || 'Fehler beim Hinzufügen des Piloten', 'error');
        }
    } catch (error) {
        console.error('Error adding pilot:', error);
        showMessage('Fehler beim Hinzufügen des Piloten: ' + error.message, 'error');
    }
}

// Delete pilot
async function deletePilot(pilotId) {
    if (!confirm('Pilot wirklich löschen?')) {
        return;
    }
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/pilots.php?id=${pilotId}`, {
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
            showMessage('Pilot erfolgreich gelöscht', 'success');
            await fetchPilots();
        } else {
            showMessage(data.error || 'Fehler beim Löschen des Piloten', 'error');
        }
    } catch (error) {
        console.error('Error deleting pilot:', error);
        showMessage('Fehler beim Löschen des Piloten: ' + error.message, 'error');
    }
}

// Update pilot minutes
async function updatePilotMinutes(pilotId, minutes) {
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/pilots.php?id=${pilotId}&action=minutes`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                minutes_of_flights_needed: minutes,
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Anzahl der benötigten Flugminuten erfolgreich aktualisiert', 'success');
        } else {
            showMessage(data.error || 'Fehler beim Aktualisieren', 'error');
        }
    } catch (error) {
        console.error('Error updating pilot:', error);
        showMessage('Fehler beim Aktualisieren: ' + error.message, 'error');
    }
}

// Open edit pilot modal
function openEditPilotModal(pilotData) {
    const modal = document.getElementById('editPilotModal');
    if (!modal) return;
    
    // Populate form fields
    document.getElementById('edit_pilot_id').value = pilotData.id;
    document.getElementById('edit_pilot_name').value = pilotData.name || '';
    document.getElementById('edit_pilot_minutes').value = pilotData.minutes_of_flights_needed || 45;
    document.getElementById('edit_a1_a3_license_id').value = pilotData.a1_a3_license_id || '';
    document.getElementById('edit_a1_a3_license_valid_until').value = pilotData.a1_a3_license_valid_until || '';
    document.getElementById('edit_a2_license_id').value = pilotData.a2_license_id || '';
    document.getElementById('edit_a2_license_valid_until').value = pilotData.a2_license_valid_until || '';
    document.getElementById('edit_lock_on_invalid_license').checked = pilotData.lock_on_invalid_license == 1 || pilotData.lock_on_invalid_license === true;
    
    modal.style.display = 'block';
}

// Close edit pilot modal
function closeEditPilotModal() {
    const modal = document.getElementById('editPilotModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Update pilot
async function updatePilot(pilotData) {
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/pilots.php?id=${pilotData.id}&action=update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: pilotData.name,
                minutes_of_flights_needed: pilotData.minutes_of_flights_needed,
                a1_a3_license_id: pilotData.a1_a3_license_id || null,
                a1_a3_license_valid_until: pilotData.a1_a3_license_valid_until || null,
                a2_license_id: pilotData.a2_license_id || null,
                a2_license_valid_until: pilotData.a2_license_valid_until || null,
                lock_on_invalid_license: pilotData.lock_on_invalid_license || '0',
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Pilot erfolgreich aktualisiert', 'success');
            closeEditPilotModal();
            await fetchPilots();
        } else {
            showMessage(data.error || 'Fehler beim Aktualisieren des Piloten', 'error');
        }
    } catch (error) {
        console.error('Error updating pilot:', error);
        showMessage('Fehler beim Aktualisieren des Piloten: ' + error.message, 'error');
    }
}

// Attach event listeners
function attachEventListeners() {
    // Delete buttons
    document.querySelectorAll('.delete-pilot-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const pilotId = this.getAttribute('data-pilot-id');
            deletePilot(pilotId);
        });
    });
    
    // Edit buttons
    document.querySelectorAll('.edit-pilot-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const pilotDataStr = this.getAttribute('data-pilot-data');
            if (pilotDataStr) {
                try {
                    const pilotData = JSON.parse(pilotDataStr);
                    openEditPilotModal(pilotData);
                } catch (e) {
                    console.error('Error parsing pilot data:', e);
                }
            }
        });
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Load pilots
    fetchPilots();
    
    // Sort dropdown change handler
    const sortSelect = document.getElementById('sort-pilots');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            // Re-sort and re-render with cached data (no API call needed)
            fetchPilots(true);
        });
    }
    
    // Add pilot form
    const addForm = document.getElementById('add-pilot-form');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const name = document.getElementById('name').value.trim();
            if (name) {
                const formData = {
                    name: name,
                    minutes_of_flights_needed: document.getElementById('minutes_of_flights_needed').value || 45,
                    a1_a3_license_id: document.getElementById('a1_a3_license_id').value.trim() || null,
                    a1_a3_license_valid_until: document.getElementById('a1_a3_license_valid_until').value || null,
                    a2_license_id: document.getElementById('a2_license_id').value.trim() || null,
                    a2_license_valid_until: document.getElementById('a2_license_valid_until').value || null,
                    lock_on_invalid_license: document.getElementById('lock_on_invalid_license').checked ? '1' : '0'
                };
                addPilot(formData);
            } else {
                showMessage('Bitte geben Sie einen Namen ein', 'error');
            }
        });
    }
    
    // Edit pilot form
    const editForm = document.getElementById('editPilotForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const pilotData = {
                id: parseInt(document.getElementById('edit_pilot_id').value),
                name: document.getElementById('edit_pilot_name').value.trim(),
                minutes_of_flights_needed: parseInt(document.getElementById('edit_pilot_minutes').value) || 45,
                a1_a3_license_id: document.getElementById('edit_a1_a3_license_id').value.trim() || null,
                a1_a3_license_valid_until: document.getElementById('edit_a1_a3_license_valid_until').value || null,
                a2_license_id: document.getElementById('edit_a2_license_id').value.trim() || null,
                a2_license_valid_until: document.getElementById('edit_a2_license_valid_until').value || null,
                lock_on_invalid_license: document.getElementById('edit_lock_on_invalid_license').checked ? '1' : '0'
            };
            
            if (!pilotData.name) {
                showMessage('Bitte geben Sie einen Namen ein', 'error');
                return;
            }
            
            updatePilot(pilotData);
        });
    }
    
    // Close modal on close button click
    const modalClose = document.querySelector('#editPilotModal .modal-close');
    if (modalClose) {
        modalClose.addEventListener('click', closeEditPilotModal);
    }
    
    // Close modal on outside click
    const editModal = document.getElementById('editPilotModal');
    if (editModal) {
        editModal.addEventListener('click', function(e) {
            if (e.target === editModal) {
                closeEditPilotModal();
            }
        });
    }
});
