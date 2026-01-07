// Dashboard JavaScript with API integration and auto-refresh

// Service Worker registration
if ('serviceWorker' in navigator) {
    const swPath = './service-worker.js';
    navigator.serviceWorker.register(swPath)
        .then((registration) => {
            console.log('ServiceWorker registered:', registration);
        })
        .catch((error) => {
            if (!error.message.includes('redirect')) {
                console.warn('ServiceWorker registration failed:', error.message);
            }
        });
}

// Global state
let dashboardData = null;
let refreshInterval = null;
let isRefreshing = false;
let lastRefreshTime = null;

// Get CSRF token from page (if available)
function getCsrfToken() {
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    return csrfInput ? csrfInput.value : '';
}

// Generate unique request ID
function generateRequestId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

// Show error message
function showError(message) {
    const container = document.getElementById('error-message-container');
    if (container) {
        container.textContent = message;
        container.style.display = 'block';
        setTimeout(() => {
            container.style.display = 'none';
        }, 5000);
    }
}

// Show success message
function showSuccess(message) {
    const container = document.getElementById('success-message-container');
    if (container) {
        container.textContent = message;
        container.style.display = 'block';
        setTimeout(() => {
            container.style.display = 'none';
        }, 3000);
    }
}

// Show loading indicator
function showLoading(show) {
    const indicator = document.getElementById('loading-indicator');
    if (indicator) {
        indicator.style.display = show ? 'block' : 'none';
    }
}

// Fetch dashboard data from API
async function fetchDashboardData(force = false) {
    if (isRefreshing && !force) {
        return;
    }
    
    isRefreshing = true;
    
    try {
        // Add cache-busting timestamp to ensure fresh data
        const timestamp = new Date().getTime();
        const response = await fetch(`api/flights.php?action=dashboard&_t=${timestamp}`);
        const data = await response.json();
        
        if (data.success) {
            dashboardData = data.data;
            renderDashboard(dashboardData);
            lastRefreshTime = new Date();
        } else {
            showError(data.error || 'Fehler beim Laden der Daten');
        }
    } catch (error) {
        console.error('Error fetching dashboard data:', error);
        showError('Fehler beim Laden der Daten: ' + error.message);
    } finally {
        isRefreshing = false;
    }
}

// Render dashboard with data
function renderDashboard(data) {
    const pilotsContainer = document.getElementById('pilots-container');
    const welcomeSection = document.getElementById('welcome-section');
    
    if (!pilotsContainer) {
        return;
    }
    
    // Show/hide welcome section
    if (data.pilots && data.pilots.length > 0) {
        if (welcomeSection) {
            welcomeSection.style.display = 'none';
        }
        pilotsContainer.innerHTML = '';
        
        // Render each pilot
        data.pilots.forEach(pilot => {
            const pilotElement = createPilotElement(pilot, data.locations || [], data.drones || []);
            pilotsContainer.appendChild(pilotElement);
        });
    } else {
        if (welcomeSection) {
            welcomeSection.style.display = 'block';
        }
        pilotsContainer.innerHTML = '';
    }
}

// Create pilot element
function createPilotElement(pilot, locations, drones) {
    const hasEnoughMinutes = pilot.flight_count >= pilot.required_minutes;
    const colorClass = hasEnoughMinutes ? 'bg-green' : 'bg-red';
    const ongoingClass = pilot.ongoing_flight ? 'bg-orange' : '';
    
    const div = document.createElement('div');
    div.className = `pilot-container ${colorClass} ${ongoingClass}`;
    div.setAttribute('data-pilot-id', pilot.id);
    
    let html = `<h3>${escapeHtml(pilot.name)}</h3>`;
    
    if (!pilot.ongoing_flight) {
        html += `<p>Flugminuten der letzten 3 Monate: ${pilot.flight_count}</p>`;
        html += `<p>Benötigte Flugminuten: ${pilot.required_minutes}</p>`;
        
        if (hasEnoughMinutes && pilot.next_flight_due) {
            // Check if next flight due is in the future
            const nextDueDate = new Date(pilot.next_flight_due + 'T00:00:00');
            const now = new Date();
            if (nextDueDate >= now) {
                html += `<p>Nächster Flug fällig: ${escapeHtml(pilot.next_flight_due)}</p>`;
            }
        }
    } else {
        // Format the flight date for display
        const flightDate = new Date(pilot.ongoing_flight.flight_date);
        const formattedDate = flightDate.toLocaleString('de-DE');
        html += `<p>Flug gestartet um: ${escapeHtml(formattedDate)}</p>`;
        html += `<br><br>`;
    }
    
    // Form
    if (pilot.ongoing_flight) {
        html += `
            <button type="button" onclick="endFlight(${pilot.ongoing_flight.id}, ${pilot.id})" class="end-flight-btn">
                Flug beenden
            </button>
        `;
    } else {
        html += `
            <div>
                <label for="location_id_${pilot.id}">Standort</label>
                <select id="location_id_${pilot.id}" required>
                    <option value="">Bitte wählen</option>
                    ${locations.map(loc => `<option value="${loc.id}">${escapeHtml(loc.location_name)}</option>`).join('')}
                </select>
            </div>
            <div>
                <label for="drone_id_${pilot.id}">Drohne</label>
                <select id="drone_id_${pilot.id}" required>
                    <option value="">Bitte wählen</option>
                    ${drones.map(drone => `<option value="${drone.id}">${escapeHtml(drone.drone_name)}</option>`).join('')}
                </select>
            </div>
            <div>
                <label for="battery_number_${pilot.id}">Batterienummer</label>
                <input type="number" id="battery_number_${pilot.id}" min="1" required>
            </div>
            <button type="button" onclick="startFlight(${pilot.id})" class="start-flight-btn">
                Flug beginnen
            </button>
        `;
    }
    
    div.innerHTML = html;
    return div;
}

// Start flight
async function startFlight(pilotId) {
    const locationId = document.getElementById(`location_id_${pilotId}`).value;
    const droneId = document.getElementById(`drone_id_${pilotId}`).value;
    const batteryNumber = document.getElementById(`battery_number_${pilotId}`).value;
    
    if (!locationId || !droneId || !batteryNumber || batteryNumber <= 0) {
        showError('Bitte füllen Sie alle Felder aus.');
        return;
    }
    
    const requestId = generateRequestId();
    const btn = document.querySelector(`[onclick="startFlight(${pilotId})"]`);
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Wird gestartet...';
    
    try {
        const response = await fetch('api/flights.php?action=start', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                pilot_id: pilotId,
                drone_id: parseInt(droneId),
                location_id: parseInt(locationId),
                battery_number: parseInt(batteryNumber),
                request_id: requestId,
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Flug erfolgreich gestartet');
            // Force refresh dashboard to show updated state immediately
            await fetchDashboardData(true);
        } else {
            // If flight was already started by another user, refresh to show current state
            if (data.error_code === 'FLIGHT_ALREADY_STARTED') {
                showError('Pilot hat bereits einen laufenden Flug. Daten werden aktualisiert...');
                // Force refresh dashboard to show current state immediately
                await fetchDashboardData(true);
            } else {
                showError(data.error || 'Fehler beim Starten des Flugs');
            }
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (error) {
        console.error('Error starting flight:', error);
        showError('Fehler beim Starten des Flugs: ' + error.message);
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// End flight
async function endFlight(flightId, pilotId) {
    if (!confirm('Möchten Sie diesen Flug wirklich beenden?')) {
        return;
    }
    
    const requestId = generateRequestId();
    const btn = document.querySelector(`[onclick="endFlight(${flightId}, ${pilotId})"]`);
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Wird beendet...';
    
    try {
        const response = await fetch('api/flights.php?action=end', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                flight_id: flightId,
                request_id: requestId,
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Flug erfolgreich beendet');
            // Force refresh dashboard to show updated state immediately
            await fetchDashboardData(true);
        } else {
            // If flight was already ended by another user, refresh to show current state
            if (data.error_code === 'FLIGHT_ALREADY_ENDED') {
                showError('Flug wurde bereits beendet. Daten werden aktualisiert...');
                // Force refresh dashboard to show current state immediately
                await fetchDashboardData(true);
            } else {
                showError(data.error || 'Fehler beim Beenden des Flugs');
            }
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (error) {
        console.error('Error ending flight:', error);
        showError('Fehler beim Beenden des Flugs: ' + error.message);
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Initial load
    showLoading(true);
    fetchDashboardData().then(() => {
        showLoading(false);
    });
    
    // Auto-refresh every 30 seconds
    refreshInterval = setInterval(() => {
        if (!isRefreshing) {
            fetchDashboardData();
        }
    }, 30000);
    
    // Pause refresh when page is hidden
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        } else {
            // Resume refresh and immediately fetch
            fetchDashboardData();
            refreshInterval = setInterval(() => {
                if (!isRefreshing) {
                    fetchDashboardData();
                }
            }, 30000);
        }
    });
});
