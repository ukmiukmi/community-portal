<?php
session_start();

// Set logout message BEFORE destroying session
$_SESSION['logout_message'] = "You have logged out successfully!";

// Destroy the session data
session_unset();
session_destroy();

// Start a new session to pass the message safely
session_start();
$_SESSION['logout_message'] = "You have logged out successfully!";

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to homepage
header("Location: ../index.php");
exit;
?>
