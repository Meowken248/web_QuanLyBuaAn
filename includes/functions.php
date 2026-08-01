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
function food_image_url($image) {
    $fallback = BASE_URL . '/img/bg1.jpg';
    if (!is_string($image) || trim($image) === '') {
        return $fallback;
    }

    $image = str_replace('\\', '/', trim($image));
    if (preg_match('#^https?://#i', $image)) {
        return filter_var($image, FILTER_VALIDATE_URL) ? $image : $fallback;
    }

    $relative = '/' . ltrim($image, '/');
    $full_path = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (is_file($full_path)) {
        return BASE_URL . $relative;
    }

    $filename = basename($image);
    foreach (['/uploads/foods/', '/assets/uploads/foods/'] as $directory) {
        $candidate = $directory . $filename;
        $candidate_path = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
        if (is_file($candidate_path)) {
            return BASE_URL . $candidate;
        }
    }

    return $fallback;
}
function meal_plan_image_url($image) {
    $fallback = BASE_URL . '/img/bg1.jpg';
    if (!is_string($image) || trim($image) === '') {
        return $fallback;
    }

    $image = str_replace('\\', '/', trim($image));
    if (preg_match('#^https?://#i', $image)) {
        return filter_var($image, FILTER_VALIDATE_URL) ? $image : $fallback;
    }

    $filename = basename($image);
    $relative = '/uploads/meal_plans/' . $filename;
    $full_path = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    return is_file($full_path) ? BASE_URL . $relative : $fallback;
}
