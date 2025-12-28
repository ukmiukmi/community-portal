<?php
if (!isset($_SESSION)) session_start();
require_once __DIR__ . '/../db.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$stmt = $conn->prepare("
    SELECT banned_until 
    FROM banned_ips 
    WHERE ip_address = ?
    LIMIT 1
");
$stmt->bind_param("s", $ip);
$stmt->execute();
$stmt->bind_result($banned_until);

if ($stmt->fetch()) {
  if (!$banned_until || strtotime($banned_until) > time()) {
    http_response_code(403);
    exit('Access denied.');
  }
}
$stmt->close();
