<?php
/**
 * Security headers configuration
 * Include this file early in your application (after error_reporting.php)
 */

// Prevent clickjacking
header('X-Frame-Options: DENY');

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Enable XSS filter (legacy browsers)
header('X-XSS-Protection: 1; mode=block');

// Referrer policy
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy (adjust as needed for your application)
// This is a basic CSP - you may need to adjust based on your needs
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none';");

// Permissions Policy (formerly Feature Policy)
header("Permissions-Policy: geolocation=(self), camera=(), microphone=()");

