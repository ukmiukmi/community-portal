<?php
// db.php — Database connection for Ezza North Citizenship System

// Database configuration
$host = "localhost";            // Database host
$user = "ukmi";                 // Database username (default for XAMPP)
$password = "ukmi";                 // Database password (leave empty for XAMPP default)
$database = "ezza_citizens_db";  // Database name (imported .sql file)

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Optional success message (for testing)
// echo "Database connected successfully!";
?>
