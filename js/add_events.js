// Add Events JavaScript with API integration

// Get CSRF token
function getCsrfToken() {
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    return csrfInput ? csrfInput.value : '';
}

// Generate unique request ID
function generateRequestId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
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

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load pilots
async function loadPilots() {
    const container = document.getElementById('pilots-checkbox-container');
    const loading = document.getElementById('loading-pilots');
    
    if (loading) loading.style.display = 'block';
    
    try {
        const response = await fetch('api/pilots.php?action=list');
        const data = await response.json();
        
        if (data.success && container) {
            container.innerHTML = '';
            data.data.pilots.forEach(pilot => {
                const div = document.createElement('div');
                div.className = 'checkbox-group';
                div.innerHTML = `
                    <label for="pilot_${pilot.id}">${escapeHtml(pilot.name)}</label>
                    <input type="checkbox" id="pilot_${pilot.id}" name="pilot_ids[]" value="${pilot.id}">
                `;
                container.appendChild(div);
            });
        }
    } catch (error) {
        console.error('Error loading pilots:', error);
        showMessage('Fehler beim Laden der Piloten', 'error');
    } finally {
        if (loading) loading.style.display = 'none';
    }
}

// Submit event form
async function submitEvent(e) {
    e.preventDefault();
    
    const eventStartDate = document.getElementById('event_start_date').value;
    const eventEndDate = document.getElementById('event_end_date').value;
    const typeId = document.getElementById('type_id').value;
    const notes = document.getElementById('notes').value;
    
    // Get selected pilot IDs
    const pilotCheckboxes = document.querySelectorAll('input[name="pilot_ids[]"]:checked');
    const pilotIds = Array.from(pilotCheckboxes).map(cb => parseInt(cb.value));
    
    // Validation
    if (!eventStartDate || !eventEndDate || !typeId || !notes) {
        showMessage('Bitte füllen Sie alle erforderlichen Felder aus.', 'error');
        return;
    }
    
    if (![1, 2, 3].includes(parseInt(typeId))) {
        showMessage('Ungültiger Ereignistyp.', 'error');
        return;
    }
    
    if (new Date(eventEndDate) <= new Date(eventStartDate)) {
        showMessage('Das Enddatum muss nach dem Startdatum liegen.', 'error');
        return;
    }
    
    if (pilotIds.length === 0) {
        showMessage('Bitte mindestens einen Piloten auswählen.', 'error');
        return;
    }
    
    const requestId = generateRequestId();
    const submitBtn = document.querySelector('#add-event-form button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Wird erstellt...';
    
    try {
        const response = await fetch('api/events.php?action=create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                event_start_date: eventStartDate,
                event_end_date: eventEndDate,
                type_id: parseInt(typeId),
                notes: notes,
                pilot_ids: pilotIds,
                request_id: requestId,
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Das Ereignis wurde erfolgreich hinzugefügt.', 'success');
            // Reset form
            document.getElementById('add-event-form').reset();
            // Uncheck all pilots
            document.querySelectorAll('input[name="pilot_ids[]"]').forEach(cb => cb.checked = false);
        } else {
            showMessage(data.error || 'Fehler beim Erstellen des Ereignisses.', 'error');
        }
    } catch (error) {
        console.error('Error submitting event:', error);
        showMessage('Fehler beim Erstellen des Ereignisses: ' + error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Load pilots
    loadPilots();
    
    // Handle form submission
    const form = document.getElementById('add-event-form');
    if (form) {
        form.addEventListener('submit', submitEvent);
    }
});
