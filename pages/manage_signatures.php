<?php
session_start();
include('../auth_check.php');
requireRole(['admin']);
include('db.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

// -------------------- CSRF Protection --------------------
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// -------------------- Directories --------------------
$uploadDirRel = 'uploads/signatures';
$uploadDir = __DIR__ . '/../' . $uploadDirRel;
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$deletedDirRel = 'uploads/deleted_signatures';
$deletedDir = __DIR__ . '/../' . $deletedDirRel;
if (!is_dir($deletedDir)) mkdir($deletedDir, 0755, true);

// -------------------- Helper Functions --------------------
function jsonResp($data, $code = 200)
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

function getCommunityName($conn, $cid)
{
  $stmt = $conn->prepare("SELECT name FROM communities WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $cid);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  return $r ? $r['name'] : 'community';
}

function logDeletion($conn, $role, $path, $cid, $deletedBy = 'system', $reason = 'updated')
{
  $stmt = $conn->prepare("INSERT INTO deleted_signatures (role, file_path, deleted_at, community_id, deleted_by, reason) VALUES (?,?,?,?,?,?)");
  $now = date('Y-m-d H:i:s');
  $stmt->bind_param('sssiss', $role, $path, $now, $cid, $deletedBy, $reason);
  $stmt->execute();
}

function removeOldSignature($conn, $role, $cid, $deletedBy = 'system', $reason = 'updated')
{
  global $deletedDir, $deletedDirRel;
  $stmt = $conn->prepare("SELECT file_path FROM signatures WHERE role=? AND community_id=? LIMIT 1");
  $stmt->bind_param('si', $role, $cid);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();

  if ($res && !empty($res['file_path'])) {
    $oldFile = __DIR__ . '/../' . $res['file_path'];
    if (file_exists($oldFile)) {
      $baseName = basename($oldFile);
      $newPath = rtrim($deletedDir, '/') . '/' . time() . '_' . $baseName;
      if (@rename($oldFile, $newPath)) {
        $relPath = rtrim($deletedDirRel, '/') . '/' . basename($newPath);
        logDeletion($conn, $role, $relPath, $cid, $deletedBy, $reason);
      } else {
        error_log("Failed to move deleted signature: $oldFile -> $newPath");
      }
    }
  }
}

function saveSignature($conn, $role, $cid, $file, $uploadDirRel, $uploadDir)
{
  if (!$file || $file['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'message' => 'No file uploaded'];

  // Verify MIME type
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);
  if (!in_array($mime, ['image/png', 'image/jpeg', 'image/jpg'])) return ['ok' => false, 'message' => 'Only PNG/JPG allowed'];

  // Additional check for valid image
  if (!@getimagesize($file['tmp_name'])) return ['ok' => false, 'message' => 'Invalid image file'];

  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  $safeRole = preg_replace('/[^a-z0-9_\-]/i', '', $role);

  removeOldSignature($conn, $role, $cid, $_SESSION['username'] ?? 'system', 'updated');

  $communityName = getCommunityName($conn, $cid);
  $safeCommunity = preg_replace('/[^a-z0-9_\-]/i', '_', strtolower($communityName));

  try {
    $random = bin2hex(random_bytes(3));
  } catch (Exception $e) {
    $random = substr(md5(uniqid('', true)), 0, 6);
  }

  $fname = sprintf('%s_sig_%s_%s.%s', $safeRole, $safeCommunity, time() . '_' . $random, $ext);
  $destFull = rtrim($uploadDir, '/') . '/' . $fname;
  $destRel = rtrim($uploadDirRel, '/') . '/' . $fname;

  if (!is_uploaded_file($file['tmp_name'])) return ['ok' => false, 'message' => 'Invalid uploaded file'];
  if (!move_uploaded_file($file['tmp_name'], $destFull)) return ['ok' => false, 'message' => 'Upload failed'];

  $stmt = $conn->prepare(
    "INSERT INTO signatures (role,file_path,community_id,uploaded_at,updated_at)
         VALUES (?,?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE file_path=VALUES(file_path),updated_at=NOW()"
  );
  $stmt->bind_param('ssi', $role, $destRel, $cid);
  if (!$stmt->execute()) {
    @unlink($destFull);
    return ['ok' => false, 'message' => 'DB Error: ' . $stmt->error];
  }

  return ['ok' => true, 'file' => $destRel . '?t=' . time(), 'updated' => date('Y-m-d H:i:s')];
}

// -------------------- AJAX Handling --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
  // CSRF check
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    jsonResp(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
  }

  $action = trim($_POST['ajax_action']);
  $allowedActions = ['upload_role', 'upload_all', 'delete_signature', 'save_digital', 'fetch_signatures'];
  if (!in_array($action, $allowedActions)) jsonResp(['status' => 'error', 'message' => 'Invalid action'], 400);

  // ----- Upload single -----
  if ($action === 'upload_role') {
    $role = trim($_POST['role'] ?? '');
    $cid = intval($_POST['community_id'] ?? 0);
    if (!$role || !$cid) jsonResp(['status' => 'error', 'message' => 'Missing parameters']);
    $file = $_FILES['signature'] ?? null;
    $res = saveSignature($conn, $role, $cid, $file, $uploadDirRel, $uploadDir);
    jsonResp($res['ok'] ? ['status' => 'success', 'file' => $res['file'], 'updated' => $res['updated']] : ['status' => 'error', 'message' => $res['message']]);
  }

  // ----- Upload both -----
  if ($action === 'upload_all') {
    $cid = intval($_POST['community_id'] ?? 0);
    if (!$cid) jsonResp(['status' => 'error', 'message' => 'Missing community']);
    $roles = ['president', 'secretary'];
    $out = [];
    foreach ($roles as $r) {
      $f = $_FILES["signature_$r"] ?? null;
      if ($f && $f['error'] === UPLOAD_ERR_OK) $out[$r] = saveSignature($conn, $r, $cid, $f, $uploadDirRel, $uploadDir);
      else $out[$r] = ['ok' => false, 'message' => 'No file provided'];
    }
    jsonResp(['status' => 'success', 'result' => $out]);
  }

  // ----- Delete -----
  if ($action === 'delete_signature') {
    $role = trim($_POST['role'] ?? '');
    $cid = intval($_POST['community_id'] ?? 0);
    if (!$role || !$cid) jsonResp(['status' => 'error', 'message' => 'Missing parameters']);
    removeOldSignature($conn, $role, $cid, $_SESSION['username'] ?? 'system', 'manual delete');
    $stmt2 = $conn->prepare("DELETE FROM signatures WHERE role=? AND community_id=?");
    $stmt2->bind_param('si', $role, $cid);
    if (!$stmt2->execute()) jsonResp(['status' => 'error', 'message' => 'DB Error: ' . $stmt2->error]);
    jsonResp(['status' => 'success', 'message' => 'Deleted']);
  }

  // ----- Fetch -----
  if ($action === 'fetch_signatures') {
    $cid = intval($_POST['community_id'] ?? 0);
    if (!$cid) jsonResp(['status' => 'error', 'message' => 'Missing community']);
    $q = $conn->prepare("SELECT role,file_path,updated_at FROM signatures WHERE community_id=?");
    $q->bind_param('i', $cid);
    $q->execute();
    $r = $q->get_result();
    $out = [];
    while ($row = $r->fetch_assoc()) $out[$row['role']] = $row;
    jsonResp(['status' => 'success', 'data' => $out]);
  }

  // ----- Save digital -----
  if ($action === 'save_digital') {
    $role = trim($_POST['role'] ?? '');
    $cid = intval($_POST['community_id'] ?? 0);
    $data = $_POST['data'] ?? '';
    if (!$role || !$cid || !$data) jsonResp(['status' => 'error', 'message' => 'Missing data']);

    $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data));
    if ($binary === false) jsonResp(['status' => 'error', 'message' => 'Invalid image data']);

    $fname = sprintf('%s_digital_%s_%s.png', preg_replace('/[^a-z0-9_\-]/i', '', $role), $cid, time() . '_' . bin2hex(random_bytes(3)));
    $destFull = rtrim($uploadDir, '/') . '/' . $fname;
    $destRel = rtrim($uploadDirRel, '/') . '/' . $fname;

    if (file_put_contents($destFull, $binary) === false) jsonResp(['status' => 'error', 'message' => 'Save failed']);
    removeOldSignature($conn, $role, $cid, $_SESSION['username'] ?? 'system', 'digital');

    $stmt = $conn->prepare("INSERT INTO signatures (role,file_path,community_id,uploaded_at,updated_at)
                          VALUES (?,?,?,NOW(),NOW())
                          ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), updated_at=NOW()");
    $stmt->bind_param('ssi', $role, $destRel, $cid);
    if (!$stmt->execute()) jsonResp(['status' => 'error', 'message' => 'DB Error: ' . $stmt->error]);

    jsonResp(['status' => 'success', 'file' => $destRel . '?t=' . time(), 'updated' => date('Y-m-d H:i:s')]);
  }
}

// -------------------- Render Page --------------------
$communities = $conn->query("SELECT id,name FROM communities ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
ob_start();
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Manage Community Signatures</title>
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.12/dist/sweetalert2.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/manage_signatures.css">
</head>

<body>
  <div class="container">
    <h2>Manage Community Signatures</h2>
    <input type="hidden" id="csrf_token" value="<?= $csrfToken ?>">
    <select id="communitySelect" class="community-select">
      <option value="">-- Select a Community --</option>
      <?php foreach ($communities as $c): ?>
        <option value="<?= htmlspecialchars($c['id']) ?>"><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div id="communityContent"></div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
  <script src="../js/manage_signatures.js" defer></script>
</body>

</html>

<?php
$content = ob_get_clean();
include('../include/layout.php');
?>