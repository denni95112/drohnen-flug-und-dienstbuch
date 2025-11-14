// Extracted JavaScript from manage_locations.php
function setLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            // Set latitude and longitude values
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
        }, function(error) {
            alert('Fehler beim Abrufen der GPS-Daten: ' + error.message);
        });
    } else {
        alert('Geolocation wird von diesem Browser nicht unterstützt.');
    }
}

// Attach event listener to set location button
document.addEventListener('DOMContentLoaded', function() {
    const setLocationBtn = document.getElementById('set-location-btn');
    if (setLocationBtn) {
        setLocationBtn.addEventListener('click', setLocation);
    }
});

