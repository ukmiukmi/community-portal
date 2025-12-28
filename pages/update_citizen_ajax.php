<?php
session_start();

include('../auth_check.php');
requireRole(['admin', 'registrar']);
include('db.php');

header('Content-Type: application/json; charset=utf-8');

/* ===========================
   Helper
=========================== */
function respond($status, $message)
{
  echo json_encode([
    'status'  => $status,
    'message' => $message
  ]);
  exit;
}

/* ===========================
   Validate Request Method
=========================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond('error', 'Invalid request method');
}

/* ===========================
   DELETE Logic
=========================== */
if (!empty($_POST['delete_id'])) {

  $id = (int) $_POST['delete_id'];

  // Get image path
  $stmt = $conn->prepare("SELECT image_path FROM citizens WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  $image_path = $result['image_path'] ?? '';

  // Delete record
  $stmt = $conn->prepare("DELETE FROM citizens WHERE id=?");
  $stmt->bind_param('i', $id);

  if ($stmt->execute()) {

    // Delete image file if exists and not default
    if ($image_path && file_exists('../' . $image_path) && !str_contains($image_path, 'default')) {
      @unlink('../' . $image_path);
    }

    respond('success', 'Citizen deleted successfully');
  }

  respond('error', 'Delete failed');
}

/* ===========================
   UPDATE Logic
=========================== */
if (empty($_POST['update_id'])) {
  respond('error', 'Missing citizen ID');
}

$id = (int) $_POST['update_id'];

/* ===========================
   Sanitize Inputs
=========================== */
$first_name     = trim($_POST['first_name'] ?? '');
$last_name      = trim($_POST['last_name'] ?? '');
$phone          = trim($_POST['phone'] ?? '');
$citizen_type   = $_POST['citizen_type'] ?? '';
$state_origin   = $_POST['state_of_origin'] ?? '';
$community_id   = (int) ($_POST['community_id'] ?? 0);
$village_id     = (int) ($_POST['village_id'] ?? 0);
$house_address  = trim($_POST['house_address'] ?? '');

/* ===========================
   Validation
=========================== */
if ($first_name === '' || $last_name === '' || $citizen_type === '' || !$community_id || !$village_id) {
  respond('error', 'Please fill all required fields');
}

/* ===========================
   Optional Image Upload
=========================== */
$imageSql = '';
$params = [
  $first_name,
  $last_name,
  $phone,
  $citizen_type,
  $state_origin,
  $community_id,
  $village_id,
  $house_address
];
$types = 'sssssiis';

if (!empty($_FILES['citizen_image']['name'])) {

  $uploadDir = '../uploads/citizens/';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }

  $ext = strtolower(pathinfo($_FILES['citizen_image']['name'], PATHINFO_EXTENSION));
  $allowed = ['jpg', 'jpeg', 'png', 'webp'];

  if (!in_array($ext, $allowed)) {
    respond('error', 'Invalid image format');
  }

  $fileName = 'citizen_' . $id . '_' . time() . '.' . $ext;
  $target = $uploadDir . $fileName;

  if (!move_uploaded_file($_FILES['citizen_image']['tmp_name'], $target)) {
    respond('error', 'Image upload failed');
  }

  // Remove old image
  $stmt = $conn->prepare("SELECT image_path FROM citizens WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  $old_image = $result['image_path'] ?? '';

  if ($old_image && file_exists('../' . $old_image) && !str_contains($old_image, 'default')) {
    @unlink('../' . $old_image);
  }

  $imageSql = ', image_path=?';
  $params[] = 'uploads/citizens/' . $fileName;
  $types   .= 's';
}

/* ===========================
   Update Query
=========================== */
$params[] = $id;
$types   .= 'i';

$sql = "
UPDATE citizens SET
    first_name=?,
    last_name=?,
    phone=?,
    citizen_type=?,
    state_of_origin=?,
    community_id=?,
    village_id=?,
    house_address=?
    $imageSql
WHERE id=?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

/* ===========================
   Response
=========================== */
if ($stmt->affected_rows > 0) {
  respond('success', 'Citizen updated successfully');
}

respond('success', 'No changes were made');
