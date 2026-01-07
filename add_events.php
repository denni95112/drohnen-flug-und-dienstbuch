<?php
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/security_headers.php';
require 'auth.php';
requireAuth();

$config = include __DIR__ . '/config/config.php';
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/version.php';

// Note: POST handling has been moved to api/events.php
// This page now only renders HTML. Data is fetched via API in add_events.js
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dienste hinzufügen</title>
    <link rel="stylesheet" href="css/add_events.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
    <script src="js/add_events.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main>
        <h1>Dienst hinzufügen</h1>
        <!-- Message containers -->
        <div id="error-container" class="error-message" style="display: none;"></div>
        <div id="success-container" class="success-message" style="display: none;"></div>

        <form id="add-event-form">
            <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
            <div class="form-group">
                <label for="event_start_date">Startdatum und -zeit</label>
                <input type="datetime-local" id="event_start_date" name="event_start_date" required>
            </div>
            <div class="form-group">
                <label for="event_end_date">Enddatum und -zeit</label>
                <input type="datetime-local" id="event_end_date" name="event_end_date" required>
            </div>
            <div class="form-group">
                <label for="type_id">Typ</label>
                <select name="type_id" id="type_id" required>
                    <option value="">Bitte wählen</option>
                    <option value="1">Dienst</option>
                    <option value="2">Einsatz</option>
                    <option value="3">Verwaltung</option>
                </select>
            </div>
            <div class="form-group">
                <label for="notes">Notizen</label>
                <textarea id="notes" name="notes" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label class="section-label">Anwesenheitsliste</label>
                <div id="loading-pilots" style="display: none;">Lade Piloten...</div>
                <div class="checkbox-container" id="pilots-checkbox-container">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
            <button type="submit" class="btn-submit">Dienst hinzufügen</button>
        </form>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
