<?php
/**
 * Security headers configuration
 * Include this file early in your application (after error_reporting.php)
 */

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.buymeacoffee.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' https://www.buymeacoffee.com; frame-src https://www.buymeacoffee.com; frame-ancestors 'none';");
header("Permissions-Policy: geolocation=(self), camera=(), microphone=()");

