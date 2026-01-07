<?php
/**
 * Main entry point for the application
 * Handles user authentication and redirects to dashboard if already logged in
 */

try {
    require_once __DIR__ . '/includes/error_reporting.php';
} catch (Throwable $e) {
    die("Error loading error_reporting.php: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . ":" . $e->getLine());
}

if (!file_exists(__DIR__ . '/config/config.php')) {
    header('Location: setup.php');
    exit;
}

try {
    require_once __DIR__ . '/includes/security_headers.php';
} catch (Throwable $e) {
    die("Error loading security_headers.php: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . ":" . $e->getLine());
}

try {
    require_once __DIR__ . '/version.php';
} catch (Throwable $e) {
    die("Error loading version.php: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . ":" . $e->getLine());
}

try {
    $config = include __DIR__ . '/config/config.php';
    if (!is_array($config)) {
        die("Config file did not return an array. Please check config/config.php");
    }
} catch (Throwable $e) {
    die("Error loading config.php: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . ":" . $e->getLine());
}

session_start();

if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}
$error = '';

try {
    include('auth.php');
} catch (Throwable $e) {
    die("Error loading auth.php: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . ":" . $e->getLine());
}

if(isAuthenticated()){
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/rate_limit.php';
    require_once __DIR__ . '/includes/utils.php';
    
    if (checkRateLimit('login', 5, 900)) {
        $error = 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte versuchen Sie es später erneut.';
    } else {
        $password = $_POST['password'] ?? '';
        $verifyResult = verifyPassword($password, $config['password_hash']);
        
        if ($verifyResult['valid']) {
            // Update hash if needed (legacy SHA256 -> bcrypt migration)
            if ($verifyResult['needs_rehash'] && $verifyResult['new_hash']) {
                updateConfig('password_hash', $verifyResult['new_hash']);
            }
            
            clearRateLimit('login');
            session_regenerate_id(true);
            $_SESSION['loggedin'] = true;
            $_SESSION['login_time'] = time();
            setLoginCookie(); 
            header('Location: dashboard.php');
            exit();
        } else {
            recordFailedAttempt('login');
            $error = 'Falsches Passwort!';
            sleep(1);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $config['navigation_title'] ?></title>
    <link rel="stylesheet" href="css/login.css?v=<?php echo APP_VERSION; ?>">
    <link rel="manifest" href="manifest.json">


    <script src="js/index.js"></script>

</head>
<body>
    <div class="login-container">
        <h1>Login</h1>
        <h3><?php echo $config['navigation_title'] ?></h3>
        <form method="post" action="index.php" class="login-form">
            <div class="form-group">
                <input type="password" id="password" name="password" placeholder="Passwort eingeben" required>
            </div>
            <?php if ($error): ?>
                <p class="error-message"><?php echo $error; ?></p>
            <?php endif; ?>
            <button type="submit" class="btn-login">Einloggen</button>
        </form>
        <footer>
            <p>MIT License - Erstellt von Dennis Bögner</p>
            <p>Version <?php echo APP_VERSION; ?></p>
        </footer>
    </div>
</body>
</html>