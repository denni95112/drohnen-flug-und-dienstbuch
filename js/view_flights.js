// View Flights JavaScript

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
    } else {
        // Fallback: use alert if container doesn't exist
        alert(message);
    }
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Format date for datetime-local input (Y-m-d\TH:i)
function formatDateForInput(dateString) {
    if (!dateString) return '';
    // Convert from 'Y-m-d H:i:s' (local time) to 'Y-m-d\TH:i' format for datetime-local input
    const date = new Date(dateString.replace(' ', 'T'));
    if (isNaN(date.getTime())) {
        // Fallback: try to parse as-is
        const parts = dateString.split(' ');
        if (parts.length >= 2) {
            return parts[0] + 'T' + parts[1].substring(0, 5);
        }
        return '';
    }
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// Open edit flight modal
function openEditFlightModal(flightId, flightEndDate, batteryNumber) {
    if (!window.isAdmin) return;
    
    const modal = document.getElementById('editFlightModal');
    if (!modal) return;
    
    // Populate form fields
    document.getElementById('edit_flight_id').value = flightId;
    document.getElementById('edit_flight_end_date').value = formatDateForInput(flightEndDate);
    document.getElementById('edit_battery_number').value = batteryNumber || '';
    
    modal.style.display = 'block';
}

// Close edit flight modal
function closeEditFlightModal() {
    const modal = document.getElementById('editFlightModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Update flight
async function updateFlight(flightData) {
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/flights.php?action=update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: parseInt(flightData.id),
                flight_end_date: flightData.flight_end_date,
                battery_number: parseInt(flightData.battery_number),
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Flug erfolgreich aktualisiert', 'success');
            closeEditFlightModal();
            // Reload page to show updated data
            window.location.reload();
        } else {
            showMessage(data.error || 'Fehler beim Aktualisieren des Flugs', 'error');
        }
    } catch (error) {
        console.error('Error updating flight:', error);
        showMessage('Fehler beim Aktualisieren des Flugs: ' + error.message, 'error');
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (!window.isAdmin) return;
    
    // Edit flight buttons
    document.querySelectorAll('.edit-flight-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const flightId = this.getAttribute('data-flight-id');
            const flightEndDate = this.getAttribute('data-flight-end-date') || '';
            const batteryNumber = this.getAttribute('data-battery-number') || '';
            openEditFlightModal(flightId, flightEndDate, batteryNumber);
        });
    });
    
    // Edit flight form
    const editForm = document.getElementById('editFlightForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const flightId = document.getElementById('edit_flight_id').value;
            const flightEndDate = document.getElementById('edit_flight_end_date').value;
            const batteryNumber = document.getElementById('edit_battery_number').value;
            
            if (!flightEndDate || !batteryNumber) {
                showMessage('Bitte füllen Sie alle erforderlichen Felder aus.', 'error');
                return;
            }
            
            if (parseInt(batteryNumber) <= 0) {
                showMessage('Bitte geben Sie eine gültige Batterienummer ein.', 'error');
                return;
            }
            
            updateFlight({ id: flightId, flight_end_date: flightEndDate, battery_number: batteryNumber });
        });
    }
    
    // Close modal on close button click
    const modalClose = document.querySelector('#editFlightModal .modal-close');
    if (modalClose) {
        modalClose.addEventListener('click', closeEditFlightModal);
    }
    
    // Close modal on outside click
    const editModal = document.getElementById('editFlightModal');
    if (editModal) {
        editModal.addEventListener('click', function(e) {
            if (e.target === editModal) {
                closeEditFlightModal();
            }
        });
    }
});
