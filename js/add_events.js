// Enforce 15-minute intervals for datetime-local inputs
// datetime-local inputs work with local time, so we need to work directly with local time
function roundTo15Minutes(input) {
    if (!input.value) {
        return;
    }
    
    // Parse the datetime-local value (format: YYYY-MM-DDTHH:mm)
    // This is already in local time, so we parse it as local time
    const [datePart, timePart] = input.value.split('T');
    if (!datePart || !timePart) {
        return;
    }
    
    const [year, month, day] = datePart.split('-').map(Number);
    const [hours, minutes] = timePart.split(':').map(Number);
    
    // Create a date object in local time
    const localDate = new Date(year, month - 1, day, hours, minutes, 0, 0);
    
    // Round to nearest 15 minutes
    const roundedMinutes = Math.round(localDate.getMinutes() / 15) * 15;
    localDate.setMinutes(roundedMinutes);
    localDate.setSeconds(0);
    localDate.setMilliseconds(0);
    
    // Format back to datetime-local format (YYYY-MM-DDTHH:mm) in local time
    const formattedYear = localDate.getFullYear();
    const formattedMonth = String(localDate.getMonth() + 1).padStart(2, '0');
    const formattedDay = String(localDate.getDate()).padStart(2, '0');
    const formattedHours = String(localDate.getHours()).padStart(2, '0');
    const formattedMinutes = String(localDate.getMinutes()).padStart(2, '0');
    
    input.value = `${formattedYear}-${formattedMonth}-${formattedDay}T${formattedHours}:${formattedMinutes}`;
}

// Attach event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const eventStartDate = document.getElementById('event_start_date');
    const eventEndDate = document.getElementById('event_end_date');
    
    if (eventStartDate) {
        eventStartDate.addEventListener('change', function() {
            roundTo15Minutes(this);
        });
    }
    
    if (eventEndDate) {
        eventEndDate.addEventListener('change', function() {
            roundTo15Minutes(this);
        });
    }
});

