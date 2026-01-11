// Manage Drones JavaScript with API integration

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

// Fetch drones from API
async function fetchDrones() {
    const tbody = document.getElementById('drones-tbody');
    const loading = document.getElementById('loading-indicator');
    
    if (loading) loading.style.display = 'block';
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/drones.php?action=list`);
        const data = await response.json();
        
        if (data.success && tbody) {
            tbody.innerHTML = '';
            
            data.data.drones.forEach(drone => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${escapeHtml(drone.id)}</td>
                    <td>${escapeHtml(drone.drone_name)}</td>
                    <td>
                        <button type="button" 
                                class="btn-delete delete-drone-btn" 
                                data-drone-id="${drone.id}">
                            Löschen
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            // Attach event listeners
            attachEventListeners();
        } else {
            showMessage(data.error || 'Fehler beim Laden der Drohnen', 'error');
        }
    } catch (error) {
        console.error('Error fetching drones:', error);
        showMessage('Fehler beim Laden der Drohnen: ' + error.message, 'error');
    } finally {
        if (loading) loading.style.display = 'none';
    }
}

// Add drone
async function addDrone(droneName) {
    const requestId = generateRequestId();
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/drones.php?action=create`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                drone_name: droneName,
                request_id: requestId,
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Drohne erfolgreich hinzugefügt', 'success');
            document.getElementById('drone_name').value = '';
            await fetchDrones();
        } else {
            showMessage(data.error || 'Fehler beim Hinzufügen der Drohne', 'error');
        }
    } catch (error) {
        console.error('Error adding drone:', error);
        showMessage('Fehler beim Hinzufügen der Drohne: ' + error.message, 'error');
    }
}

// Delete drone
async function deleteDrone(droneId) {
    if (!confirm('Drohne wirklich löschen?')) {
        return;
    }
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/drones.php?id=${droneId}`, {
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
            showMessage('Drohne erfolgreich gelöscht', 'success');
            await fetchDrones();
        } else {
            showMessage(data.error || 'Fehler beim Löschen der Drohne', 'error');
        }
    } catch (error) {
        console.error('Error deleting drone:', error);
        showMessage('Fehler beim Löschen der Drohne: ' + error.message, 'error');
    }
}

// Attach event listeners
function attachEventListeners() {
    document.querySelectorAll('.delete-drone-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const droneId = this.getAttribute('data-drone-id');
            deleteDrone(droneId);
        });
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Load drones
    fetchDrones();
    
    // Add drone form
    const addForm = document.getElementById('add-drone-form');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const droneName = document.getElementById('drone_name').value.trim();
            if (droneName) {
                addDrone(droneName);
            } else {
                showMessage('Bitte geben Sie einen Namen für die Drohne ein', 'error');
            }
        });
    }
});
