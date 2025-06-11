<?php
// Common helper functions for the application

/**
 * Sanitize user input
 * @param string $data The input to sanitize
 * @return string The sanitized input
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Check if user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get the current user type
 * @return string|null The user type (client, busker, admin) or null if not logged in
 */
function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

/**
 * Require user to be logged in
 * Redirects to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /tbcph/client/index.php');
        exit();
    }
}

/**
 * Require user to be admin
 * Redirects to admin login if not admin
 */
function requireAdmin() {
    if (!isLoggedIn() || getUserType() !== 'admin') {
        header('Location: /tbcph/admin/index.php');
        exit();
    }
}

/**
 * Require user to be busker
 * Redirects to busker login if not busker
 */
function requireBusker() {
    if (!isLoggedIn() || getUserType() !== 'busker') {
        header('Location: /tbcph/busker/index.php');
        exit();
    }
}

/**
 * Require user to be client
 * Redirects to client login if not client
 */
function requireClient() {
    if (!isLoggedIn() || getUserType() !== 'client') {
        header('Location: /tbcph/client/index.php');
        exit();
    }
}

/**
 * Generate CSRF token
 * @return string The generated token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * @param string $token The token to validate
 * @return bool True if token is valid, false otherwise
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

/**
 * Format date to readable format
 * @param string $date The date to format
 * @param string $format The desired format (default: 'F j, Y')
 * @return string The formatted date
 */
function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format currency
 * @param float $amount The amount to format
 * @return string The formatted amount
 */
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

/**
 * Get user's full name
 * @param int $userId The user ID
 * @param string $userType The user type (client, busker, admin)
 * @return string|null The user's full name or null if not found
 */
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

/**
 * Check if a string contains any of the given words
 * @param string $string The string to check
 * @param array $words The words to look for
 * @return bool True if any word is found, false otherwise
 */
function containsAny($string, $words) {
    foreach ($words as $word) {
        if (stripos($string, $word) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Get file extension
 * @param string $filename The filename
 * @return string The file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file is an image
 * @param string $filename The filename
 * @return bool True if file is an image, false otherwise
 */
function isImage($filename) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    return in_array(getFileExtension($filename), $allowed);
}

/**
 * Generate random string
 * @param int $length The length of the string
 * @return string The generated string
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length));
}

/**
 * Redirect with message
 * @param string $url The URL to redirect to
 * @param string $message The message to display
 * @param string $type The message type (success, error, warning, info)
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header('Location: ' . $url);
    exit();
}

/**
 * Display message if exists
 * @return string|null The message HTML or null if no message
 */
function displayMessage() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'success';
        $message = $_SESSION['message'];
        unset($_SESSION['message'], $_SESSION['message_type']);
        return "<div class='alert alert-$type'>$message</div>";
    }
    return null;
} 