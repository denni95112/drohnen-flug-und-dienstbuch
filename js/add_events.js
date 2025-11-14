// Extracted JavaScript from add_events.php
// Note: toLocalTime is a PHP function, so we'll use a JavaScript equivalent
function toLocalTime(isoString) {
    const date = new Date(isoString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// Enforce 15-minute intervals for datetime-local inputs
function roundTo15Minutes(input) {
    const value = new Date(input.value);
    const roundedMinutes = Math.round(value.getMinutes() / 15) * 15;
    value.setMinutes(roundedMinutes);
    value.setSeconds(0);
    input.value = toLocalTime(value.toISOString().slice(0, 16)); // Convert to YYYY-MM-DDTHH:mm format
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

