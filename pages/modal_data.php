<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) session_start();

// --- Communities ---
if (!isset($_SESSION['communities'])) {
  $result = $conn->query("SELECT id, name FROM communities ORDER BY name ASC");
  $_SESSION['communities'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
$communities = $_SESSION['communities'];

// --- States ---
if (!isset($_SESSION['states'])) {
  $result = $conn->query("SELECT name FROM states ORDER BY name ASC");
  $_SESSION['states'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
$states = $_SESSION['states'];
