<?php
require_once __DIR__ . '/../includes/error_reporting.php';
require_once __DIR__ . '/../includes/security_headers.php';
require __DIR__ . '/../includes/auth.php';
requireAuth();

// Set timezone from config
$config = include __DIR__ . '/../config/config.php';
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/version.php';

// Note: POST handling has been moved to api/pilots.php
// This page now only renders HTML. Data is fetched via API in manage_pilots.js
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Piloten verwalten</title>
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="../css/manage_pilots.css?v=<?php echo APP_VERSION; ?>">
    <script src="../js/manage_pilots.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <h1>Piloten verwalten</h1>

        <!-- Message containers -->
        <div id="message-container"></div>
        <div id="error-container"></div>

        <!-- Add Pilot Form -->
        <form id="add-pilot-form" class="compact-form">
            <?php require_once __DIR__ . '/../includes/csrf.php'; csrf_field(); ?>
            <h2>Neuer Pilot</h2>
            
            <div class="form-row">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" placeholder="Name eingeben" required>
                <label for="minutes_of_flights_needed">Min. Flugminuten pro 3 Monate</label>
                <input type="number" id="minutes_of_flights_needed" name="minutes_of_flights_needed" placeholder="45" min="1" value="45" class="small-input">
            </div>
            
            <div class="license-section">
                <div class="license-group">
                    <label class="license-label">A1/A3 Fernpilotenschein (optional)</label>
                    <div class="license-fields">
                        <input type="text" id="a1_a3_license_id" name="a1_a3_license_id" placeholder="ID">
                        <input type="date" id="a1_a3_license_valid_until" name="a1_a3_license_valid_until" placeholder="Gültig bis">
                    </div>
                </div>
                
                <div class="license-group">
                    <label class="license-label">A2 Fernpilotenschein (optional)</label>
                    <div class="license-fields">
                        <input type="text" id="a2_license_id" name="a2_license_id" placeholder="ID">
                        <input type="date" id="a2_license_valid_until" name="a2_license_valid_until" placeholder="Gültig bis">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" id="lock_on_invalid_license" name="lock_on_invalid_license" value="1">
                    Sperren wenn Fernpilotenschein ungültig
                </label>
            </div>
            
            <button type="submit" class="button-full">Hinzufügen</button>
        </form>

        <!-- Pilots List -->
        <h2>Bestehende Piloten</h2>
        <div style="margin-bottom: 1rem;">
            <label for="sort-pilots">Sortieren nach: </label>
            <select id="sort-pilots" style="padding: 0.5rem; border-radius: 5px; border: 1px solid #ccc;">
                <option value="name">Name</option>
                <option value="id">ID</option>
                <option value="a1_a3_expiry">A1/A3 Ablaufdatum</option>
                <option value="a2_expiry">A2 Ablaufdatum</option>
            </select>
        </div>
        <div id="loading-indicator" style="display: none;">Lade Daten...</div>
        <table id="pilots-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Benötigte Flugminuten</th>
                    <th>A1/A3 Fernpilotenschein</th>
                    <th>A2 Fernpilotenschein</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="pilots-tbody">
                <!-- Will be populated by JavaScript -->
            </tbody>
        </table>

        <!-- Edit Pilot Modal -->
        <div id="editPilotModal" class="modal">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <h2>Pilot bearbeiten</h2>
                <form id="editPilotForm">
                    <?php require_once __DIR__ . '/../includes/csrf.php'; csrf_field(); ?>
                    <input type="hidden" name="edit_pilot_id" id="edit_pilot_id">
                    
                    <div class="form-group">
                        <label for="edit_pilot_name">Name *</label>
                        <input type="text" id="edit_pilot_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_pilot_minutes">Min. Flugminuten pro 3 Monate</label>
                        <input type="number" id="edit_pilot_minutes" name="minutes_of_flights_needed" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="license-label">A1/A3 Fernpilotenschein (optional)</label>
                        <div class="license-fields">
                            <input type="text" id="edit_a1_a3_license_id" name="a1_a3_license_id" placeholder="ID">
                            <input type="date" id="edit_a1_a3_license_valid_until" name="a1_a3_license_valid_until" placeholder="Gültig bis">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="license-label">A2 Fernpilotenschein (optional)</label>
                        <div class="license-fields">
                            <input type="text" id="edit_a2_license_id" name="a2_license_id" placeholder="ID">
                            <input type="date" id="edit_a2_license_valid_until" name="a2_license_valid_until" placeholder="Gültig bis">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="edit_lock_on_invalid_license" name="lock_on_invalid_license" value="1">
                            Sperren wenn Fernpilotenschein ungültig
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">Speichern</button>
                        <button type="button" class="btn-cancel" onclick="closeEditPilotModal()">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
