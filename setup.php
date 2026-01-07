<?php
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/version.php';
// setup.php

// Detect OS
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$projectRoot = __DIR__;

// Generate suggested database paths based on OS
$suggestedPaths = [];
if ($isWindows) {
    // Windows paths
    $suggestedPaths = [
        'C:/data/drone-dashboard-database.sqlite' => 'C:/data/drone-dashboard-database.sqlite - Empfohlen',
        'db/drone-dashboard-database.sqlite' => '(db/drone-dashboard-database.sqlite) - Weniger sicher',
        'D:/apps/drone-dashboard-database.sqlite' => 'D:/apps/drone-dashboard-database.sqlite',
        '../data/drone-dashboard-database.sqlite' => '../data/drone-dashboard-database.sqlite - Relativ',
        'C:/ProgramData/drone-dashboard-database.sqlite' => 'C:/ProgramData/drone-dashboard-database.sqlite',
    ];
} else {
    // Linux/Unix paths
    $user = get_current_user();
    $suggestedPaths = [
        '/var/data/drone-dashboard-database.sqlite' => '/var/data/drone-dashboard-database.sqlite - Empfohlen',
        'db/drone-dashboard-database.sqlite' => '(db/drone-dashboard-database.sqlite) - Weniger sicher',
        '/home/' . $user . '/data/drone-dashboard-database.sqlite' => '/home/' . $user . '/data/drone-dashboard-database.sqlite',
        '../data/drone-dashboard-database.sqlite' => '../data/drone-dashboard-database.sqlite - Relativ',
        '/opt/data/drone-dashboard-database.sqlite' => '/opt/data/drone-dashboard-database.sqlite',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = '';
    $name = trim($_POST['name']);
    $short_name = trim($_POST['short_name']);
    $navigation_title = trim($_POST['navigation_title']);
    $password = $_POST['password'];
    $admin_password = $_POST['admin_password'];
    $external_documentation_url = $_POST['external_documentation_url'];
    
    // Handle database path: use custom path if provided, otherwise use dropdown selection
    $database_path_dropdown = $_POST['database_path_dropdown'] ?? '';
    $database_path_custom = trim($_POST['database_path_custom'] ?? '');
    
    if ($database_path_dropdown === 'custom' && !empty($database_path_custom)) {
        $database_path = $database_path_custom;
    } elseif ($database_path_dropdown === 'custom' && empty($database_path_custom)) {
        $database_path = 'db/drone-dashboard-database.sqlite'; // Default if custom is selected but empty
    } elseif (!empty($database_path_dropdown)) {
        $database_path = $database_path_dropdown;
    } else {
        $database_path = 'db/drone-dashboard-database.sqlite'; // Default
    }
    
    $database_path = trim($database_path);

    if ($name && $short_name && $navigation_title && $password && $admin_password) {
        // Paths
        $manifestPath = __DIR__ . '/manifest.json';
        $configPath = __DIR__ . '/config/config.php';

        // Create config folder if missing
        if (!is_dir(__DIR__ . '/config')) {
            mkdir(__DIR__ . '/config', 0755, true);
        }

        // Handle logo upload (optional)
        $logo_path = '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoDir = __DIR__ . '/uploads/logos';
            
            // Create uploads directory if it doesn't exist
            if (!is_dir(__DIR__ . '/uploads')) {
                if (!@mkdir(__DIR__ . '/uploads', 0755, true)) {
                    $error = "Fehler: Das Verzeichnis 'uploads' konnte nicht erstellt werden.";
                }
            }
            
            // Create logos subdirectory if it doesn't exist
            if (empty($error) && !is_dir($logoDir)) {
                if (!@mkdir($logoDir, 0755, true)) {
                    $error = "Fehler: Das Verzeichnis 'uploads/logos' konnte nicht erstellt werden.";
                }
            }
            
            if (empty($error)) {
                $file = $_FILES['logo'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
                
                // Validate file size
                if ($file['size'] > $maxSize) {
                    $error = "Logo-Datei ist zu groß. Maximale Größe: 2MB.";
                } else {
                    // Validate file type
                    $mimeType = null;
                    if (extension_loaded('fileinfo')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);
                    } else {
                        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $extensionMap = [
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'svg' => 'image/svg+xml',
                            'webp' => 'image/webp'
                        ];
                        $mimeType = $extensionMap[$extension] ?? null;
                    }
                    
                    if (!$mimeType || !in_array($mimeType, $allowedTypes)) {
                        $error = "Logo-Dateityp nicht erlaubt. Erlaubte Typen: JPEG, PNG, GIF, SVG, WebP.";
                    } else {
                        // Generate safe filename
                        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $safeName = 'logo_' . time() . '_' . uniqid() . '.' . $extension;
                        $targetPath = $logoDir . '/' . $safeName;
                        
                        if (@move_uploaded_file($file['tmp_name'], $targetPath)) {
                            $logo_path = 'uploads/logos/' . $safeName;
                        } else {
                            $error = "Fehler beim Speichern des Logos. Bitte überprüfen Sie die Schreibrechte für das Verzeichnis 'uploads/logos'.";
                        }
                    }
                }
            }
        } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Handle other upload errors
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => "Die Datei überschreitet die upload_max_filesize Direktive in php.ini.",
                UPLOAD_ERR_FORM_SIZE => "Die Datei überschreitet die MAX_FILE_SIZE Direktive im HTML-Formular.",
                UPLOAD_ERR_PARTIAL => "Die Datei wurde nur teilweise hochgeladen.",
                UPLOAD_ERR_NO_TMP_DIR => "Fehlender temporärer Ordner.",
                UPLOAD_ERR_CANT_WRITE => "Fehler beim Schreiben der Datei auf die Festplatte.",
                UPLOAD_ERR_EXTENSION => "Eine PHP-Erweiterung hat den Upload gestoppt."
            ];
            $errorCode = $_FILES['logo']['error'];
            $error = $uploadErrors[$errorCode] ?? "Unbekannter Fehler beim Hochladen der Datei (Fehlercode: {$errorCode}).";
        }
        
        // Only proceed if there are no errors
        if (empty($error)) {
            // Generate random IV (16 bytes for AES-256-CBC)
            $iv = bin2hex(random_bytes(8)); // 8 bytes = 16 hex chars (16 bytes total when used)

            // Hash passwords using password_hash (bcrypt/argon2)
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);

            // Build token name from navigation_title (safe key)
            $token_name = preg_replace('/[^a-z0-9]+/', '_', strtolower($navigation_title)) . '_token';

            // Handle database path
            // If empty, use default relative path
            if (empty($database_path)) {
                $database_path = 'db/drone-dashboard-database.sqlite';
            }
            // Normalize path separators
            $database_path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $database_path);
            // Escape for PHP string
            $database_path_escaped = addslashes($database_path);
            $logo_path_escaped = addslashes($logo_path);

            // Create manifest.json
            $manifest = [
                'name' => $name,
                'short_name' => $short_name,
                'start_url' => 'index.php',
                'display' => 'standalone',
                'background_color' => '#ffffff',
                'theme_color' => '#333333',
                'orientation' => 'portrait',
                'icons' => [
                    [
                        'src' => 'icons/icon-192x192.png',
                        'sizes' => '192x192',
                        'type' => 'image/png'
                    ],
                    [
                        'src' => 'icons/icon-512x512.png',
                        'sizes' => '512x512',
                        'type' => 'image/png'
                    ]
                ]
            ];
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Create config.php
            $logoConfigLine = !empty($logo_path) ? "    'logo_path' => '{$logo_path_escaped}'," : '';
            $config = <<<PHP
<?php
return [
    'navigation_title' => '{$navigation_title}',
    'token_name' => '{$token_name}',
    'external_documentation_url'=> '{$external_documentation_url}',
    'debugMode' => false,
    'timezone' => 'Europe/Berlin',
    'database_path' => '{$database_path_escaped}',
{$logoConfigLine}
    'encryption' => [
        'method' => 'aes-256-cbc',
        'iv' => '{$iv}',
    ],
    'password_hash' => '{$password_hash}', // password_hash (bcrypt/argon2)
    'admin_hash' => '{$admin_hash}',
    'ask_for_install_notification' => true,
];
PHP;
            file_put_contents($configPath, $config);

            // Redirect to setup_database.php
            header('Location: setup_database.php');
            exit;
        }
    } else {
        if (empty($error)) {
            $error = "Bitte füllen Sie alle erforderlichen Felder aus.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Setup - Flug- und Dienstbuch</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/setup.css?v=<?php echo APP_VERSION; ?>">
    <script src="js/setup.js"></script>
</head>
<body>
    <h1>Einrichtung</h1>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
    <label>
        Logo (Optional)
        <span class="tooltip">?
            <span class="tooltiptext">Lade ein Logo hoch, das vor dem Navigations-Titel angezeigt wird. Erlaubte Formate: JPEG, PNG, GIF, SVG, WebP (max. 2MB)</span>
        </span>
    </label>
    <input type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp">

    <label>
        WebApp Name
        <span class="tooltip">?
            <span class="tooltiptext">Beispiel: „Drohnendashboard Feuerwehr Musterstadt“</span>
        </span>
    </label>
    <input type="text" name="name" required>

    <label>
        Short Name
        <span class="tooltip">?
            <span class="tooltiptext">Der Name unter dem App-Icon nach der Installation, z. B. „DB F Musterstadt“</span>
        </span>
    </label>
    <input type="text" name="short_name" required>

    <label>
        Navigations-Titel
        <span class="tooltip">?
            <span class="tooltiptext">Wird meist identisch mit dem WebApp-Namen verwendet</span>
        </span>
    </label>
    <input type="text" name="navigation_title" required>

    <label>
        WebApp Passwort
        <span class="tooltip">?
            <span class="tooltiptext">Ein gemeinsames Passwort für alle Nutzer – es gibt keine Benutzerkonten</span>
        </span>
    </label>
    <input type="password" name="password" required>

    <label>
        Admin Passwort
        <span class="tooltip">?
            <span class="tooltiptext">Verwende dieses Passwort für erweiterte Administratorfunktionen</span>
        </span>
    </label>
    <input type="password" name="admin_password" required>

    <label>
        Einsatzdoku URL
        <span class="tooltip">?
            <span class="tooltiptext">Du verwendest auch die Einsatzdoku? Füge hier den Link dazu ein. Alternativ geht dies auch später in config Datei</span>
        </span>
    </label>
    <input type="text" name="external_documentation_url">

    <label>
        Datenbank-Pfad (Merke dir den Pfad, wenn du auch das Einsatztagebuch verwendest)
        <span class="tooltip">?
            <span class="tooltiptext">
                <strong>Sicherheitshinweis:</strong> Speichere die Datenbank außerhalb des Web-Verzeichnisses!<br><br>
                <strong>Beispiele:</strong><br>
                <?php if ($isWindows): ?>
                Windows: C:/data/drone-dashboard-database.sqlite oder ..\\data\\drone-dashboard-database.sqlite<br>
                <?php else: ?>
                Linux: /var/data/drone-dashboard-database.sqlite oder ../data/drone-dashboard-database.sqlite<br>
                <?php endif; ?>
                Relativ: db/drone-dashboard-database.sqlite (Standard, weniger sicher)<br><br>
                Wähle einen empfohlenen Pfad oder verwende "Eigener Pfad" für eine benutzerdefinierte Option.
            </span>
        </span>
    </label>
    <select name="database_path_dropdown" id="database_path_dropdown" required>
        <?php foreach ($suggestedPaths as $path => $label): ?>
            <option value="<?= htmlspecialchars($path) ?>"><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
        <option value="custom">Eigener Pfad...</option>
    </select>
    <input type="text" name="database_path_custom" id="database_path_custom" placeholder="Eigener Pfad eingeben...">
    <small>
        <?php if ($isWindows): ?>
            Betriebssystem erkannt: Windows
        <?php else: ?>
            Betriebssystem erkannt: Linux/Unix
        <?php endif; ?>
    </small>

    <input type="submit" value="Einrichten und loslegen">
</form>
</body>
</html>
