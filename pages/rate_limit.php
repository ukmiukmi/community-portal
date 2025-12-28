<?php
$ip = $_SERVER['REMOTE_ADDR'];
$key = 'rate_' . md5($ip);
$limit = 10;
$window = 60;

$data = $_SESSION[$key] ?? ['count' => 0, 'time' => time()];

if (time() - $data['time'] > $window) {
  $data = ['count' => 1, 'time' => time()];
} else {
  $data['count']++;
}

$_SESSION[$key] = $data;

if ($data['count'] > $limit) {
  require_once 'ban_ip.php';
  banIp($ip, 'Rate limit exceeded');
  http_response_code(429);
  exit('Too many requests');
}
