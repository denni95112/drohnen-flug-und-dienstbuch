// Extracted JavaScript from manage_locations.php
function setLocation() {
    const spinner = document.getElementById('location-spinner');
    const buttonText = document.getElementById('set-location-text');
    const setLocationBtn = document.getElementById('set-location-btn');
    
    // Store original text
    const originalText = buttonText ? buttonText.textContent : 'Position automatisch setzen';
    
    // Show spinner and update button text
    if (spinner) spinner.style.display = 'inline-block';
    if (buttonText) buttonText.textContent = 'GPS wird gesucht...';
    if (setLocationBtn) setLocationBtn.disabled = true;
    
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            // Set latitude and longitude values
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            
            // Hide spinner and restore button text
            if (spinner) spinner.style.display = 'none';
            if (buttonText) buttonText.textContent = originalText;
            if (setLocationBtn) setLocationBtn.disabled = false;
        }, function(error) {
            alert('Fehler beim Abrufen der GPS-Daten: ' + error.message);
            
            // Hide spinner and restore button text on error
            if (spinner) spinner.style.display = 'none';
            if (buttonText) buttonText.textContent = originalText;
            if (setLocationBtn) setLocationBtn.disabled = false;
        });
    } else {
        alert('Geolocation wird von diesem Browser nicht unterstützt.');
        
        // Hide spinner and restore button text
        if (spinner) spinner.style.display = 'none';
        if (buttonText) buttonText.textContent = originalText;
        if (setLocationBtn) setLocationBtn.disabled = false;
    }
}

// Attach event listener to set location button
document.addEventListener('DOMContentLoaded', function() {
    const setLocationBtn = document.getElementById('set-location-btn');
    if (setLocationBtn) {
        setLocationBtn.addEventListener('click', setLocation);
    }
});

