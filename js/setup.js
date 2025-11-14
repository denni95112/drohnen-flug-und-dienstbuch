// Extracted JavaScript from setup.php
document.addEventListener('DOMContentLoaded', function() {
    const dropdown = document.getElementById('database_path_dropdown');
    const customInput = document.getElementById('database_path_custom');
    
    function toggleCustomInput() {
        if (dropdown.value === 'custom') {
            customInput.style.display = 'block';
            customInput.required = true;
        } else {
            customInput.style.display = 'none';
            customInput.required = false;
            customInput.value = '';
        }
    }
    
    dropdown.addEventListener('change', toggleCustomInput);
    toggleCustomInput(); // Initialize on page load
});

