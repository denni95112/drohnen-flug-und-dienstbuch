// Extracted JavaScript from add_flight.php
// Wait for the document to load
document.addEventListener("DOMContentLoaded", function () {
    const flightDateInput = document.getElementById("flight_date");
    const locationSelect = document.getElementById("location_id");

    // Function to fetch locations based on selected flight date
    function fetchLocationsByDate(flightDate) {
        if (flightDate) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'fetch_locations.php?flight_date=' + flightDate, true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    locationSelect.innerHTML = xhr.responseText;
                }
            };
            xhr.send();
        }
    }

    // Listen for changes in the flight date input field
    flightDateInput.addEventListener('change', function () {
        fetchLocationsByDate(flightDateInput.value);
    });
});

