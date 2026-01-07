// Extracted JavaScript from view_events.php
// Automatically submit the form when the year is changed
document.addEventListener("DOMContentLoaded", function () {
    const yearSelect = document.getElementById("year");
    if (yearSelect) {
        yearSelect.addEventListener("change", function () {
            this.form.submit();
        });
    }

    // Accordion toggle
    const accordions = document.querySelectorAll(".accordion-header");
    accordions.forEach(header => {
        header.addEventListener("click", function () {
            const body = this.nextElementSibling;
            body.style.display = body.style.display === "block" ? "none" : "block";
        });
    });

    // Modal close handlers
    const modal = document.getElementById("editEventModal");
    if (modal) {
        const closeBtn = modal.querySelector(".modal-close");
        if (closeBtn) {
            closeBtn.addEventListener("click", closeEditModal);
        }
        
        // Close modal when clicking outside of it
        window.addEventListener("click", function(event) {
            if (event.target === modal) {
                closeEditModal();
            }
        });
    }
});

// Open edit modal and populate with event data
function openEditModal(eventData) {
    const modal = document.getElementById("editEventModal");
    if (!modal) return;
    
    // Populate form fields
    document.getElementById("edit_event_id").value = eventData.id;
    document.getElementById("edit_event_start_date").value = eventData.event_start_date;
    document.getElementById("edit_event_end_date").value = eventData.event_end_date;
    document.getElementById("edit_type_id").value = eventData.type_id;
    document.getElementById("edit_notes").value = eventData.notes;
    
    // Clear and set pilot checkboxes
    const checkboxes = document.querySelectorAll("#editEventForm input[type='checkbox'][name='pilot_ids[]']");
    checkboxes.forEach(checkbox => {
        checkbox.checked = eventData.pilot_ids.includes(parseInt(checkbox.value));
    });
    
    modal.style.display = "block";
}

// Close edit modal
function closeEditModal() {
    const modal = document.getElementById("editEventModal");
    if (modal) {
        modal.style.display = "none";
    }
}

