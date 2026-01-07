<?php
/**
 * Configuration Example File
 * 
 * Copy this file to config.php and fill in your values.
 * DO NOT commit config.php to version control!
 * 
 * This file is safe to commit as it contains no sensitive data.
 */
return [
    // Website navigation title
    'navigation_title' => 'Flug- und Dienstbuch',
    
    // Token name for remember me functionality
    'token_name' => 'flight_logbook_token',
    
    // External documentation URL (optional)
    'external_documentation_url' => '',
    
    // Logo path (optional) - path to logo image file relative to project root
    // Example: 'uploads/logos/logo_1234567890_abc123.png'
    // Logo will be displayed before the navigation title
    'logo_path' => '',
    
    // Debug mode - set to true to see PHP errors on all pages
    // WARNING: Set to false in production!
    'debugMode' => false,
    
    // Timezone configuration
    // See https://www.php.net/manual/en/timezones.php for available timezones
    'timezone' => 'Europe/Berlin',
    
    // Database path (relative to project root or absolute path)
    // Examples:
    //   - Relative: 'db/drone-dashboard-database.sqlite' or '../data/drone-dashboard-database.sqlite'
    //   - Absolute Windows: 'C:/data/drone-dashboard-database.sqlite' or 'C:\\data\\drone-dashboard-database.sqlite'
    //   - Absolute Linux: '/var/data/drone-dashboard-database.sqlite' or '/home/user/data/drone-dashboard-database.sqlite'
    // SECURITY: Store database outside web root for better security!
    'database_path' => 'db/drone-dashboard-database.sqlite',
    
    // Encryption settings for file uploads
    'encryption' => [
        'method' => 'aes-256-cbc',
        'iv' => '', // Will be generated during setup
    ],
    
    // Password hashes (will be generated during setup)
    // These use password_hash() with PASSWORD_DEFAULT (bcrypt/argon2)
    'password_hash' => '', // Main application password
    'admin_hash' => '',    // Admin password
    
    // Ask for install notification - set to true to show dialog asking user if they want to notify developer about installation
    'ask_for_install_notification' => true,
];

