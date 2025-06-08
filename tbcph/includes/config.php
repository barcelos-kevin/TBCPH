<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'busker_management');

// Site configuration
define('SITE_NAME', 'The Busking Community PH');
define('SITE_URL', 'http://localhost/tbcph');

// Session configuration
session_start();

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Time zone
date_default_timezone_set('Asia/Manila');

// Database connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper functions
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/client/index.php');
    }
}

function requireAdmin() {
    if (!isLoggedIn() || getUserType() !== 'admin') {
        redirect('/admin/index.php');
    }
}

function requireBusker() {
    if (!isLoggedIn() || getUserType() !== 'busker') {
        redirect('/busker/index.php');
    }
}

function requireClient() {
    if (!isLoggedIn() || getUserType() !== 'client') {
        redirect('/client/index.php');
    }
}

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
    return true;
}

// Input sanitization
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}  
