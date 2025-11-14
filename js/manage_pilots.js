// Extracted JavaScript from manage_pilots.php
// Handle delete confirmation
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('button.delete-pilot-btn, form[action="manage_pilots.php"] button[type="submit"]');
    deleteButtons.forEach(button => {
        // Only attach to delete buttons (those in forms with action="delete")
        const form = button.closest('form');
        if (form && form.querySelector('input[name="action"][value="delete"]')) {
            button.addEventListener('click', function(e) {
                if (!confirm('Pilot wirklich löschen?')) {
                    e.preventDefault();
                }
            });
        }
    });
});

