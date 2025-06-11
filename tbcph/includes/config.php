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

// Additional helper functions
function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function getUserFullName($userId, $userType) {
    global $conn;
    try {
        $table = $userType . 's'; // clients, buskers, admins
        $stmt = $conn->prepare("SELECT name FROM $table WHERE {$userType}_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting user name: " . $e->getMessage());
        return null;
    }
}

function containsAny($string, $words) {
    foreach ($words as $word) {
        if (stripos($string, $word) !== false) {
            return true;
        }
    }
    return false;
}

function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function isImage($filename) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    return in_array(getFileExtension($filename), $allowed);
}

function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length));
}

function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header('Location: ' . $url);
    exit();
}

function displayMessage() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'success';
        $message = $_SESSION['message'];
        unset($_SESSION['message'], $_SESSION['message_type']);
        return "<div class='alert alert-$type'>$message</div>";
    }
    return null;
}  
