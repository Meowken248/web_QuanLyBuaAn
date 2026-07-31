<?php
// includes/functions.php

// Function to set flash message
function set_flash_message($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

// Function to display flash message
function display_flash_message() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_message']['type'];
        $message = $_SESSION['flash_message']['message'];
        
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        
        // Clear message after displaying
        unset($_SESSION['flash_message']);
    }
}

// Helper for CSRF token
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
function is_valid_date($date) {
    if (!is_string($date)) return false;
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function old($key, $default = '') {
    return htmlspecialchars((string)($_POST[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}
