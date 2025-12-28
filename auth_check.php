<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once './db.php';
require '../vendor/autoload.php'; // PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Admin email
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// ------------------- Helpers -------------------

function logAccessAttempt($username, $role, $page)
{
  global $conn;
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

  $stmt = $conn->prepare("INSERT INTO access_logs (username, attempted_page, role, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
  $stmt->bind_param("sssss", $username, $page, $role, $ip, $ua);
  $stmt->execute();
  $stmt->close();
}

function notifyAdmin($username, $role, $page)
{
  $mail = new PHPMailer(true);
  try {
    // SMTP settings (replace with your own)
    $mail->isSMTP();
    $mail->Host = 'smtp.yourdomain.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'no-reply@yourdomain.com';
    $mail->Password = 'yourpassword';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('no-reply@yourdomain.com', 'Portal Security');
    $mail->addAddress(ADMIN_EMAIL);

    $mail->Subject = 'Unauthorized Access Alert';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $mail->Body = "User: $username (Role: $role)\nPage: $page\nIP: $ip\nTime: " . date('Y-m-d H:i:s');
    $mail->send();
  } catch (Exception $e) {
    // optionally log mail errors
  }
}

function denyAccess($message, $redirect, $icon = 'warning')
{
  http_response_code(403);
  echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Access Denied</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <audio autoplay>
            <source src="/assets/alarm.mp3" type="audio/mpeg">
        </audio>
        <script>
            Swal.fire({
                icon: "' . $icon . '",
                title: "Access Denied",
                text: "' . $message . '",
                confirmButtonText: "OK",
                allowOutsideClick: false
            }).then(() => {
                window.location.href = "' . $redirect . '";
            });
        </script>
    </body>
    </html>';
  exit;
}

// ------------------- Temporary Block -------------------
function checkTempBlock()
{
  global $conn;
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $stmt = $conn->prepare("SELECT blocked_until FROM temp_blocks WHERE ip_address = ?");
  $stmt->bind_param("s", $ip);
  $stmt->execute();
  $stmt->bind_result($blocked_until);
  if ($stmt->fetch()) {
    if (strtotime($blocked_until) > time()) {
      denyAccess('Too many unauthorized attempts. Try later.', '/login.php', 'error');
    } else {
      // unblock expired
      $stmt->close();
      $stmt2 = $conn->prepare("DELETE FROM temp_blocks WHERE ip_address = ?");
      $stmt2->bind_param("s", $ip);
      $stmt2->execute();
      $stmt2->close();
    }
  }
  $stmt->close();
}

// Call at the start
checkTempBlock();

// ------------------- Main Logic -------------------
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Guest';
$role = $_SESSION['role'] ?? 'guest';

// Guest check
if (empty($_SESSION['username'])) {
  logAccessAttempt($username, $role, $currentPage);
  denyAccess('You must be logged in to access this page.', '/login.php', 'warning');
}

// Role check function
function requireRole($roles)
{
  global $username, $role, $currentPage, $conn;

  if (!is_array($roles)) $roles = [$roles];

  if (!in_array($role, $roles, true)) {
    logAccessAttempt($username, $role, $currentPage);
    if (in_array('admin', $roles)) {
      notifyAdmin($username, $role, $currentPage);
    }

    // Check last 3 failed attempts from this IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare("SELECT COUNT(*) FROM access_logs WHERE ip_address=? AND attempted_page=? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $stmt->bind_param("ss", $ip, $currentPage);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count >= 3) {
      // temporary block for 15 minutes
      $blocked_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
      $stmt2 = $conn->prepare("INSERT INTO temp_blocks (ip_address, blocked_until) VALUES (?, ?) ON DUPLICATE KEY UPDATE blocked_until=?");
      $stmt2->bind_param("sss", $ip, $blocked_until, $blocked_until);
      $stmt2->execute();
      $stmt2->close();
    }

    denyAccess('You do not have permission to access this page.', '/dashboard.php', 'error');
  }
}
