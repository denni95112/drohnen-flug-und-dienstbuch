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

// Check if config exists before trying to include it
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
    $config = include __DIR__ . '/config/config.php';
    if (!is_array($config)) {
        die("Config file did not return an array. Please check config/config.php");
    }
} catch (Throwable $e) {
    die("Error loading config.php: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . ":" . $e->getLine());
}

session_start();

// Set timezone from config
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}
$error = '';

// Include the auth.php file where setLoginCookie is defined
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
    
    // Check rate limit
    if (checkRateLimit('login', 5, 900)) {
        $error = 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte versuchen Sie es später erneut.';
    } else {
        $password = $_POST['password'] ?? '';

        // Verify password using password_verify (supports both old SHA-256 and new password_hash)
        $passwordValid = false;
        
        // Try password_verify first (new method)
        if (password_verify($password, $config['password_hash'])) {
            $passwordValid = true;
        } 
        // Fallback for old SHA-256 hashes (for migration)
        elseif (hash('sha256', $password) === $config['password_hash']) {
            $passwordValid = true;
            // Rehash with new method for future use
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            // Note: Could update config here, but requires write access
        }
        
        if ($passwordValid) {
            // Clear rate limit on successful login
            clearRateLimit('login');
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            $_SESSION['loggedin'] = true;
            $_SESSION['login_time'] = time();
            setLoginCookie(); 
            header('Location: dashboard.php');
            exit();
        } else {
            // Record failed attempt
            recordFailedAttempt('login');
            $error = 'Falsches Passwort!';
            // Small delay to prevent brute force
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
    <link rel="stylesheet" href="css/login.css">
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
            <p>&copy; <?php echo date("Y"); ?> Dennis Bögner</p>
        </footer>
    </div>
</body>
</html>