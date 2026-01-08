// Add Flight JavaScript with API integration

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
    const container = document.getElementById(type === 'success' ? 'success-container' : 'error-container');
    if (container) {
        container.textContent = message;
        container.style.display = 'block';
        setTimeout(() => {
            container.style.display = 'none';
        }, 5000);
    }
}

// Fetch locations by date
async function fetchLocationsByDate(flightDate) {
    if (!flightDate) {
        return;
    }
    
    // Extract date part (YYYY-MM-DD)
    const datePart = flightDate.split('T')[0];
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/locations.php?action=list&date=${datePart}`);
        const data = await response.json();
        
        const locationSelect = document.getElementById('location_id');
        if (locationSelect && data.success) {
            locationSelect.innerHTML = '<option value="">Bitte wählen</option>';
            data.data.locations.forEach(location => {
                const option = document.createElement('option');
                option.value = location.id;
                option.textContent = location.location_name;
                locationSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error fetching locations:', error);
    }
}

// Submit flight form
async function submitFlight(e) {
    e.preventDefault();
    
    const pilotId = document.getElementById('pilot_id').value;
    const flightDate = document.getElementById('flight_date').value;
    const flightEndDate = document.getElementById('flight_end_date').value;
    const droneId = document.getElementById('drone_id').value;
    const batteryNumber = document.getElementById('battery_number').value;
    const locationId = document.getElementById('location_id').value;
    
    // Validation
    if (!pilotId || !flightDate || !flightEndDate || !droneId || !batteryNumber) {
        showMessage('Bitte füllen Sie alle erforderlichen Felder aus.', 'error');
        return;
    }
    
    if (parseInt(batteryNumber) <= 0) {
        showMessage('Bitte geben Sie eine gültige Batterienummer ein.', 'error');
        return;
    }
    
    if (new Date(flightEndDate) <= new Date(flightDate)) {
        showMessage('Das Enddatum muss nach dem Startdatum liegen.', 'error');
        return;
    }
    
    const requestId = generateRequestId();
    const submitBtn = document.querySelector('#add-flight-form button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Wird eingetragen...';
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/flights.php?action=create`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                pilot_id: parseInt(pilotId),
                flight_date: flightDate,
                flight_end_date: flightEndDate,
                drone_id: parseInt(droneId),
                location_id: locationId ? parseInt(locationId) : null,
                battery_number: parseInt(batteryNumber),
                request_id: requestId,
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Flug erfolgreich eingetragen!', 'success');
            // Reset form
            document.getElementById('add-flight-form').reset();
            document.getElementById('location_id').innerHTML = '<option value="">Bitte wählen</option>';
        } else {
            showMessage(data.error || 'Fehler beim Eintragen des Flugs.', 'error');
        }
    } catch (error) {
        console.error('Error submitting flight:', error);
        showMessage('Fehler beim Eintragen des Flugs: ' + error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

// Load pilots
async function loadPilots() {
    const pilotSelect = document.getElementById('pilot_id');
    if (!pilotSelect) return;
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/pilots.php?action=list`);
        const data = await response.json();
        
        if (data.success && pilotSelect) {
            pilotSelect.innerHTML = '<option value="">Bitte wählen</option>';
            data.data.pilots.forEach(pilot => {
                const option = document.createElement('option');
                option.value = pilot.id;
                option.textContent = pilot.name;
                pilotSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading pilots:', error);
        pilotSelect.innerHTML = '<option value="">Fehler beim Laden</option>';
    }
}

// Load drones
async function loadDrones() {
    const droneSelect = document.getElementById('drone_id');
    if (!droneSelect) return;
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/drones.php?action=list`);
        const data = await response.json();
        
        if (data.success && droneSelect) {
            droneSelect.innerHTML = '<option value="">Bitte wählen</option>';
            data.data.drones.forEach(drone => {
                const option = document.createElement('option');
                option.value = drone.id;
                option.textContent = drone.drone_name;
                droneSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading drones:', error);
        droneSelect.innerHTML = '<option value="">Fehler beim Laden</option>';
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    const flightDateInput = document.getElementById('flight_date');
    const form = document.getElementById('add-flight-form');
    
    // Load pilots and drones on page load
    loadPilots();
    loadDrones();
    
    // Fetch locations when flight date changes
    if (flightDateInput) {
        flightDateInput.addEventListener('change', function() {
            fetchLocationsByDate(flightDateInput.value);
        });
    }
    
    // Handle form submission
    if (form) {
        form.addEventListener('submit', submitFlight);
    }
});
