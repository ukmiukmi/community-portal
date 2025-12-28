<?php
// auth_check.php — must have NO whitespace or blank lines before this tag

// Ensure session starts only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect helper for login
function redirectLogin() {
    header("Location: /login.php");
    exit;
}

// Redirect helper for access denied
function redirectDenied() {
    header("Location: /access_denied.php");
    exit;
}

// Block access if user is not logged in
if (empty($_SESSION['username'])) {
    redirectLogin();
}

// Restrict access by role (strict check)
function requireRole($roles) {
    if (!is_array($roles)) {
        $roles = [$roles];
    }

    $userRole = $_SESSION['role'] ?? '';
    $allowed = in_array($userRole, $roles, true);

    if ($allowed !== true) {
        redirectDenied();
    }
}
