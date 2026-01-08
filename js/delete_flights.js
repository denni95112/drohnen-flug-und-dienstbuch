// Delete Flights JavaScript with API integration

// Get CSRF token
function getCsrfToken() {
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    return csrfInput ? csrfInput.value : '';
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

// Fetch flights from API
async function fetchFlights() {
    const tbody = document.getElementById('flights-tbody');
    const loading = document.getElementById('loading-indicator');
    
    if (loading) loading.style.display = 'block';
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/flights.php?action=list`);
        const data = await response.json();
        
        if (data.success && tbody) {
            tbody.innerHTML = '';
            
            if (data.data.flights.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3">Keine Flüge gefunden</td></tr>';
            } else {
                data.data.flights.forEach(flight => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${escapeHtml(flight.pilot_name)}</td>
                        <td>${escapeHtml(flight.flight_date)}</td>
                        <td>
                            <button type="button" 
                                    class="button-full delete-flight-btn" 
                                    data-flight-id="${flight.id}">
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
            showMessage(data.error || 'Fehler beim Laden der Flüge', 'error');
        }
    } catch (error) {
        console.error('Error fetching flights:', error);
        showMessage('Fehler beim Laden der Flüge: ' + error.message, 'error');
    } finally {
        if (loading) loading.style.display = 'none';
    }
}

// Delete flight
async function deleteFlight(flightId) {
    if (!confirm('Flug wirklich löschen?')) {
        return;
    }
    
    try {
        const response = await fetch(`${basePath}api/flights.php?id=${flightId}`, {
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
            showMessage('Flug erfolgreich gelöscht', 'success');
            await fetchFlights();
        } else {
            showMessage(data.error || 'Fehler beim Löschen des Flugs', 'error');
        }
    } catch (error) {
        console.error('Error deleting flight:', error);
        showMessage('Fehler beim Löschen des Flugs: ' + error.message, 'error');
    }
}

// Attach event listeners
function attachEventListeners() {
    document.querySelectorAll('.delete-flight-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const flightId = this.getAttribute('data-flight-id');
            deleteFlight(flightId);
        });
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    fetchFlights();
});

