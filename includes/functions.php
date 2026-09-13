<?php
// includes/functions.php - small helpers (slides-style, no framework)

// Clean input: same as Lab/form2process.php:8
function cleanInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Escape output for HTML
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Redirect helper - always exit after header
function redirect($url) {
    header("Location: " . $url);
    exit;
}

// Guard: require specific role, else redirect to landing
function require_role($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        redirect("../index.php");
    }
}

// Guard: require any logged-in user
function require_login() {
    if (!isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
        redirect("../index.php");
    }
}

// Check if logged in
function is_logged_in() {
    return isset($_SESSION['role']) && isset($_SESSION['user_id']);
}

// Get current role
function current_role() {
    return $_SESSION['role'] ?? null;
}

// Validate email format
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Sanitize role from GET (allow only 4 values)
function sanitize_role($role) {
    $allowed = ['admin', 'vendor', 'seller', 'customer'];
    return in_array($role, $allowed, true) ? $role : null;
}
?>
