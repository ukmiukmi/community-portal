<?php
if (!isset($_SESSION)) session_start();
require_once 'db.php';

/* ----------------------------------------------------
   Upload Directories
---------------------------------------------------- */
$communityImgDirRel = 'uploads/communities';
$communityImgDir    = __DIR__ . '/../' . $communityImgDirRel;

if (!is_dir($communityImgDir)) mkdir($communityImgDir, 0755, true);

/* ----------------------------------------------------
   Helpers
---------------------------------------------------- */
function slugify($text)
{
  $text = strtolower(trim($text));
  $text = preg_replace('/[^a-z0-9]+/', '_', $text);
  return trim($text, '_');
}

function uploadCommunityImage($file, $baseName, $type, $oldFile = null)
{
  global $communityImgDir, $communityImgDirRel;

  if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) return $oldFile;

  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  $fname = "{$baseName}_{$type}." . $ext;
  $destFull = $communityImgDir . '/' . $fname;
  $destRel  = $communityImgDirRel . '/' . $fname;

  if ($oldFile && file_exists(__DIR__ . '/../' . $oldFile)) unlink(__DIR__ . '/../' . $oldFile);

  if (move_uploaded_file($file['tmp_name'], $destFull)) return $destRel;

  return $oldFile;
}

/* ----------------------------------------------------
   FETCH SINGLE COMMUNITY (AJAX)
---------------------------------------------------- */
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'fetch') {
  $id = intval($_GET['id']);
  $stmt = $conn->prepare("SELECT * FROM communities WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $data = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  header('Content-Type: application/json');
  echo json_encode($data ?: []);
  exit;
}

/* ----------------------------------------------------
   DELETE COMMUNITY (AJAX)
---------------------------------------------------- */
if (isset($_POST['delete_id'])) {
  $id = intval($_POST['delete_id']);

  $stmt = $conn->prepare("SELECT name, logo, coat_of_arms, stamp FROM communities WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->bind_result($communityName, $logo, $coat, $stamp);
  $stmt->fetch();
  $stmt->close();

  $stmt = $conn->prepare("DELETE FROM communities WHERE id=?");
  $stmt->bind_param("i", $id);
  $ok = $stmt->execute();
  $stmt->close();

  if ($ok) {
    foreach ([$logo, $coat, $stamp] as $img) {
      if (!empty($img) && file_exists(__DIR__ . '/../' . $img)) @unlink(__DIR__ . '/../' . $img);
    }
  }

  echo json_encode(["success" => $ok, "community" => ["name" => $communityName]]);
  exit;
}

/* ----------------------------------------------------
   CREATE COMMUNITY (AJAX)
---------------------------------------------------- */
if (isset($_POST['create'])) {
  $errors = [];

  $name = trim($_POST['name']);
  $slug = trim($_POST['slug']);
  $description = trim($_POST['description'] ?? '');
  $motto = trim($_POST['motto'] ?? '');

  if ($name === '') $errors[] = "Name is required.";
  if ($slug === '') $errors[] = "Slug is required.";

  $baseName = slugify($name);

  $logo = $coat_of_arms = $stamp = "";

  foreach (["logo", "coat_of_arms", "stamp"] as $type) {
    if (!empty($_FILES[$type]['name'])) {
      $$type = uploadCommunityImage($_FILES[$type], $baseName, $type);
    }
  }

  if ($errors) {
    echo json_encode(["errors" => $errors]);
    exit;
  }

  $stmt = $conn->prepare("
        INSERT INTO communities (name, slug, description, logo, coat_of_arms, motto, stamp, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
  $stmt->bind_param("sssssss", $name, $slug, $description, $logo, $coat_of_arms, $motto, $stamp);
  $stmt->execute();
  $newId = $stmt->insert_id;
  $stmt->close();

  $stmt = $conn->prepare("SELECT * FROM communities WHERE id=?");
  $stmt->bind_param("i", $newId);
  $stmt->execute();
  $community = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  echo json_encode(["success" => true, "community" => $community]);
  exit;
}

/* ----------------------------------------------------
   EDIT COMMUNITY (AJAX)
---------------------------------------------------- */
if (isset($_POST['edit_id'])) {
  $errors = [];

  $id = intval($_POST['edit_id']);
  $name = trim($_POST['name']);
  $slug = trim($_POST['slug']);
  $description = trim($_POST['description'] ?? '');
  $motto = trim($_POST['motto'] ?? '');

  if ($name === '') $errors[] = "Name is required.";
  if ($slug === '') $errors[] = "Slug is required.";

  $stmt = $conn->prepare("SELECT logo, coat_of_arms, stamp FROM communities WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->bind_result($logo, $coat_of_arms, $stamp);
  $stmt->fetch();
  $stmt->close();

  $baseName = slugify($name);
  foreach (["logo", "coat_of_arms", "stamp"] as $type) {
    if (!empty($_FILES[$type]['name'])) {
      $$type = uploadCommunityImage($_FILES[$type], $baseName, $type, $$type);
    }
  }

  if ($errors) {
    echo json_encode(["errors" => $errors]);
    exit;
  }

  $stmt = $conn->prepare("
        UPDATE communities
        SET name=?, slug=?, description=?, logo=?, coat_of_arms=?, motto=?, stamp=?
        WHERE id=?
    ");
  $stmt->bind_param("sssssssi", $name, $slug, $description, $logo, $coat_of_arms, $motto, $stamp, $id);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("SELECT * FROM communities WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $community = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  echo json_encode(["success" => true, "community" => $community]);
  exit;
}

/* ----------------------------------------------------
   SEARCH + PAGINATION
---------------------------------------------------- */
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$start = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$searchLike = "%$search%";

// Count total records
if ($search !== '') {
  $stmt = $conn->prepare("
        SELECT COUNT(*) FROM communities
        WHERE name LIKE ? OR slug LIKE ? OR motto LIKE ? OR description LIKE ?
    ");
  $stmt->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
} else {
  $stmt = $conn->prepare("SELECT COUNT(*) FROM communities");
}

$stmt->execute();
$total = $stmt->get_result()->fetch_row()[0];
$stmt->close();
$totalPages = max(1, ceil($total / $limit));

// Fetch data
if ($search !== '') {
  $stmt = $conn->prepare("
        SELECT * FROM communities
        WHERE name LIKE ? OR slug LIKE ? OR motto LIKE ? OR description LIKE ?
        ORDER BY created_at DESC
        LIMIT ?, ?
    ");
  $stmt->bind_param("ssssii", $searchLike, $searchLike, $searchLike, $searchLike, $start, $limit);
} else {
  $stmt = $conn->prepare("
        SELECT * FROM communities
        ORDER BY created_at DESC
        LIMIT ?, ?
    ");
  $stmt->bind_param("ii", $start, $limit);
}

$stmt->execute();
$res = $stmt->get_result();
$communities = [];
while ($row = $res->fetch_assoc()) {
  $communities[] = $row;
}
$stmt->close();

// AJAX RELOAD
if (isset($_GET['ajax_reload'])) {
  include 'community_table.php';
  exit;
}

// PAGE OUTPUT
ob_start();
?>
<div class="container p-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Manage Communities</h3>
    <button class="btn btn-success" id="openAddCommunityModal">
      <i class="fa fa-plus me-1"></i> Add Community
    </button>
  </div>

  <div class="mb-3">
    <input type="text" id="communitySearch" class="form-control" placeholder="Search communities...">
  </div>

  <div id="communityListWrapper">
    <?php include 'community_table.php'; ?>
  </div>
</div>

<?php include 'community_modal.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-dark@5/dark.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="../css/manage_community.css">
<script src="../js/manage_community.js"></script>
<script src="../js/community_modal.js"></script>
<?php
$content = ob_get_clean();
require_once '../include/layout.php';
