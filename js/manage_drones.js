// Extracted JavaScript from manage_drones.php
// Handle delete confirmation
document.addEventListener('DOMContentLoaded', function() {
    const deleteLinks = document.querySelectorAll('a.delete-drone-link, a[href*="delete_id"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Möchten Sie diese Drohne wirklich löschen?')) {
                e.preventDefault();
            }
        });
    });
});

