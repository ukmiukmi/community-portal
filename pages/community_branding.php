<?php
session_start();
include('../auth_check.php');
requireRole(['admin']);
include('db.php');

/* ----------------------------------------------------
   Upload Directories
---------------------------------------------------- */
$signDirRel        = 'uploads/signatures';
$signDir           = __DIR__ . '/../' . $signDirRel;

$deletedSignDirRel = 'uploads/deleted_signatures';
$deletedSignDir    = __DIR__ . '/../' . $deletedSignDirRel;

$communityImgDirRel = 'uploads/communities';
$communityImgDir    = __DIR__ . '/../' . $communityImgDirRel;

foreach ([$signDir, $deletedSignDir, $communityImgDir] as $dir) {
  if (!is_dir($dir)) mkdir($dir, 0755, true);
}

/* ----------------------------------------------------
   Helpers
---------------------------------------------------- */
function jsonResp($data)
{
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}

function slugify($text)
{
  $text = strtolower(trim($text));
  $text = preg_replace('/[^a-z0-9]+/', '_', $text);
  return trim($text, '_');
}

function getCommunityName($conn, $cid)
{
  $stmt = $conn->prepare("SELECT name FROM communities WHERE id=?");
  $stmt->bind_param('i', $cid);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  return $r ? $r['name'] : 'community';
}

function logDeletion($conn, $role, $path, $cid, $deletedBy, $reason)
{
  $stmt = $conn->prepare("
    INSERT INTO deleted_signatures
    (role,file_path,deleted_at,community_id,deleted_by,reason)
    VALUES (?,?,?,?,?,?)
  ");
  $stmt->bind_param(
    'sssiss',
    $role,
    $path,
    date('Y-m-d H:i:s'),
    $cid,
    $deletedBy,
    $reason
  );
  $stmt->execute();
}

/* ----------------------------------------------------
   Signature Handling
---------------------------------------------------- */
function removeOldSignature($conn, $role, $cid, $deletedBy, $reason)
{
  global $deletedSignDir, $deletedSignDirRel;

  $stmt = $conn->prepare("SELECT file_path FROM signatures WHERE role=? AND community_id=?");
  $stmt->bind_param('si', $role, $cid);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();

  if ($res && file_exists(__DIR__ . '/../' . $res['file_path'])) {
    $old = __DIR__ . '/../' . $res['file_path'];
    $new = $deletedSignDir . '/' . time() . '_deleted_' . basename($old);
    rename($old, $new);

    logDeletion(
      $conn,
      $role,
      $deletedSignDirRel . '/' . basename($new),
      $cid,
      $deletedBy,
      $reason
    );
  }
}

function saveSignature($conn, $role, $cid, $file)
{
  global $signDir, $signDirRel;

  if (!$file || $file['error'] !== UPLOAD_ERR_OK)
    return ['ok' => false, 'message' => 'No file uploaded'];

  if (!in_array($file['type'], ['image/png', 'image/jpeg', 'image/jpg']))
    return ['ok' => false, 'message' => 'Only PNG/JPG allowed'];

  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  $safeRole = slugify($role);
  $safeCommunity = slugify(getCommunityName($conn, $cid));

  removeOldSignature(
    $conn,
    $role,
    $cid,
    $_SESSION['username'] ?? 'system',
    'updated'
  );

  $fname    = "{$safeCommunity}_{$safeRole}_signature.$ext";
  $destFull = $signDir . '/' . $fname;
  $destRel  = $signDirRel . '/' . $fname;

  if (!move_uploaded_file($file['tmp_name'], $destFull))
    return ['ok' => false, 'message' => 'Upload failed'];

  $stmt = $conn->prepare("
    INSERT INTO signatures (role,file_path,updated_at,community_id)
    VALUES (?,?,NOW(),?)
    ON DUPLICATE KEY UPDATE file_path=VALUES(file_path),updated_at=NOW()
  ");
  $stmt->bind_param('ssi', $role, $destRel, $cid);
  $stmt->execute();

  return [
    'ok' => true,
    'file' => $destRel,
    'updated' => date('Y-m-d H:i:s')
  ];
}

/* ----------------------------------------------------
   AJAX Actions
---------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
  $action = $_POST['ajax_action'];

  if ($action === 'upload_role') {
    jsonResp(
      saveSignature(
        $conn,
        $_POST['role'],
        intval($_POST['community_id']),
        $_FILES['signature']
      )
    );
  }

  if ($action === 'delete_signature') {
    $role = $_POST['role'];
    $cid  = intval($_POST['community_id']);

    removeOldSignature(
      $conn,
      $role,
      $cid,
      $_SESSION['username'] ?? 'admin',
      'manual delete'
    );

    $stmt = $conn->prepare("DELETE FROM signatures WHERE role=? AND community_id=?");
    $stmt->bind_param('si', $role, $cid);
    $stmt->execute();

    jsonResp(['status' => 'success']);
  }

  if ($action === 'fetch_signatures') {
    $cid = intval($_POST['community_id']);
    $out = [];

    $q = $conn->prepare("SELECT role,file_path,updated_at FROM signatures WHERE community_id=?");
    $q->bind_param('i', $cid);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) $out[$row['role']] = $row;

    $img = $conn->query("
      SELECT logo,coat_of_arms,stamp,motto
      FROM communities WHERE id=$cid
    ")->fetch_assoc();

    $out['images'] = $img;
    jsonResp(['status' => 'success', 'data' => $out]);
  }

  if ($action === 'upload_all_images') {
    $cid = intval($_POST['community_id']);
    $safeCommunity = slugify(getCommunityName($conn, $cid));
    $results = [];

    foreach (['logo', 'coat_of_arms', 'stamp'] as $type) {
      if (empty($_FILES["image_$type"]['name'])) continue;

      $f = $_FILES["image_$type"];
      if (!in_array($f['type'], ['image/png', 'image/jpeg', 'image/jpg'])) {
        $results[$type] = ['status' => 'error'];
        continue;
      }

      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      $fname = "{$safeCommunity}_{$type}.$ext";
      $destFull = $communityImgDir . '/' . $fname;
      $destRel = $communityImgDirRel . '/' . $fname;

      $old = $conn->query("SELECT $type FROM communities WHERE id=$cid")->fetch_assoc()[$type] ?? null;
      if ($old && file_exists(__DIR__ . '/../' . $old)) unlink(__DIR__ . '/../' . $old);

      move_uploaded_file($f['tmp_name'], $destFull);

      $stmt = $conn->prepare("UPDATE communities SET $type=? WHERE id=?");
      $stmt->bind_param('si', $destRel, $cid);
      $stmt->execute();

      $results[$type] = ['status' => 'success', 'file' => $destRel];
    }

    if (!empty($_POST['motto'])) {
      $stmt = $conn->prepare("UPDATE communities SET motto=? WHERE id=?");
      $stmt->bind_param('si', $_POST['motto'], $cid);
      $stmt->execute();
      $results['motto'] = ['status' => 'success'];
    }

    jsonResp(['status' => 'success', 'result' => $results]);
  }

  jsonResp(['status' => 'error']);
}

/* ----------------------------------------------------
   Page Render
---------------------------------------------------- */
$communities = $conn->query("SELECT id,name FROM communities ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

ob_start();
?>
<div class="container py-4">
  <h2 class="text-success mb-4">Manage Community Signatures & Images</h2>
  <select id="communitySelect" class="form-select mb-3">
    <option value="">-- Select a Community --</option>
    <?php foreach ($communities as $c): ?>
      <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <div id="communityContent"></div>
</div>
<link rel="stylesheet" href="../css/branding.css">
<script src="../js/branding.js"></script>
<?php
$content = ob_get_clean();
include('../include/layout.php');
