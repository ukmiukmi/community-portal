<?php
if (!isset($_SESSION)) session_start();
require_once 'db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

// Get the log ID and snapshot data
$log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
$snapshot_data = $_POST['snapshot'] ?? '';

if (!$log_id || !$snapshot_data) {
  echo json_encode(['success' => false, 'message' => 'Invalid log ID or snapshot data']);
  exit;
}

// Decode base64 image
if (preg_match('/^data:image\/(\w+);base64,/', $snapshot_data, $type)) {
  $data = substr($snapshot_data, strpos($snapshot_data, ',') + 1);
  $type = strtolower($type[1]); // jpg, png, gif
  $data = base64_decode($data);
  if ($data === false) {
    echo json_encode(['success' => false, 'message' => 'Base64 decode failed']);
    exit;
  }
} else {
  echo json_encode(['success' => false, 'message' => 'Invalid image data']);
  exit;
}

// Save file to snapshots folder
$folder = __DIR__ . '/../snapshots';
if (!is_dir($folder)) mkdir($folder, 0755, true);

$filename = $folder . '/log_' . $log_id . '_' . time() . '.' . $type;
if (file_put_contents($filename, $data) === false) {
  echo json_encode(['success' => false, 'message' => 'Failed to save file']);
  exit;
}

// Save relative path to database
$relative_path = 'snapshots/' . basename($filename);
$stmt = $conn->prepare("UPDATE access_logs SET snapshot=? WHERE id=?");
$stmt->bind_param("si", $relative_path, $log_id);
if ($stmt->execute()) {
  echo json_encode(['success' => true, 'snapshot' => $relative_path]);
} else {
  echo json_encode(['success' => false, 'message' => 'Database update failed']);
}
$stmt->close();
