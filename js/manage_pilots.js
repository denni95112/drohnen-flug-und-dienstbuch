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

// Fetch pilots from API
async function fetchPilots() {
    const tbody = document.getElementById('pilots-tbody');
    const loading = document.getElementById('loading-indicator');
    
    if (loading) loading.style.display = 'block';
    
    try {
        const response = await fetch('api/pilots.php?action=list');
        const data = await response.json();
        
        if (data.success && tbody) {
            tbody.innerHTML = '';
            
            data.data.pilots.forEach(pilot => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${escapeHtml(pilot.id)}</td>
                    <td>${escapeHtml(pilot.name)}</td>
                    <td>
                        <form class="update-minutes-form" data-pilot-id="${pilot.id}">
                            <label for="minutes_of_flights_needed_${pilot.id}">Flugminuten pro 3 Monate</label>
                            <input type="number" 
                                   id="minutes_of_flights_needed_${pilot.id}" 
                                   value="${pilot.minutes_of_flights_needed ?? 60}" 
                                   min="1" 
                                   required>
                            <button type="submit">Aktualisieren</button>
                        </form>
                    </td>
                    <td>
                        <button type="button" 
                                class="button-full delete-pilot-btn" 
                                data-pilot-id="${pilot.id}">
                            Löschen
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            // Attach event listeners
            attachEventListeners();
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
async function addPilot(name) {
    const requestId = generateRequestId();
    
    try {
        const response = await fetch('api/pilots.php?action=create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: name,
                request_id: requestId,
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Pilot erfolgreich hinzugefügt', 'success');
            document.getElementById('name').value = '';
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
        const response = await fetch(`api/pilots.php?id=${pilotId}`, {
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
        const response = await fetch(`api/pilots.php?id=${pilotId}&action=minutes`, {
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

// Attach event listeners
function attachEventListeners() {
    // Delete buttons
    document.querySelectorAll('.delete-pilot-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const pilotId = this.getAttribute('data-pilot-id');
            deletePilot(pilotId);
        });
    });
    
    // Update minutes forms
    document.querySelectorAll('.update-minutes-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const pilotId = this.getAttribute('data-pilot-id');
            const minutes = parseInt(this.querySelector('input[type="number"]').value);
            updatePilotMinutes(pilotId, minutes);
        });
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Load pilots
    fetchPilots();
    
    // Add pilot form
    const addForm = document.getElementById('add-pilot-form');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const name = document.getElementById('name').value.trim();
            if (name) {
                addPilot(name);
            } else {
                showMessage('Bitte geben Sie einen Namen ein', 'error');
            }
        });
    }
});
