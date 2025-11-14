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
});

