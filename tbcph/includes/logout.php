<?php
require_once __DIR__ . '/config.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the appropriate login page based on the user type
if (isset($_GET['type'])) {
    switch ($_GET['type']) {
        case 'admin':
            header('Location: ' . SITE_URL . '/admin/index.php');
            break;
        case 'busker':
            header('Location: ' . SITE_URL . '/busker/index.php');
            break;
        case 'client':
            header('Location: ' . SITE_URL . '/client/index.php');
            break;
        default:
            header('Location: ' . SITE_URL . '/public/index.php');
    }
} else {
    // Default redirect to public page if no type specified
    header('Location: ' . SITE_URL . '/public/index.php');
}
exit(); 