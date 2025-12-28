<?php
session_start();
include('../auth_check.php');
requireRole(['admin', 'registrar']);
include('db.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$username = $_SESSION['username'];

// Fetch user ID
$stmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$user_id = $user['id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

/* =======================
   CAPTURE & VALIDATE INPUT
======================= */
$full_name = trim($_POST['full_name'] ?? '');
$community_id = intval($_POST['community_id'] ?? 0);
$village_id = intval($_POST['village_id'] ?? 0);
$house_address = trim($_POST['house_address'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$citizen_type = $_POST['citizen_type'] ?? '';
$state_of_origin = trim($_POST['state_of_origin'] ?? '');

if (!$full_name || !$community_id || !$village_id || !$house_address || !$phone || !$citizen_type || !$state_of_origin) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

/* =======================
   DUPLICATE PHONE CHECK
======================= */
$check = $conn->prepare("SELECT id FROM citizens WHERE phone=? LIMIT 1");
$check->bind_param("s", $phone);
$check->execute();
if ($check->get_result()->fetch_assoc()) {
    echo json_encode(['status' => 'error', 'message' => 'Phone number already exists']);
    exit;
}

/* =======================
   SPLIT NAME
======================= */
$name_parts = explode(' ', $full_name, 2);
$first_name = trim($name_parts[0]);
$last_name  = trim($name_parts[1] ?? '');

/* =======================
   GENERATE CITIZEN ID
======================= */
$slugRes = $conn->query("SELECT slug FROM communities WHERE id=$community_id");
$slug = $slugRes->fetch_assoc()['slug'] ?? 'unk';

$counter = 1;
do {
    $citizen_id = $slug . '_' . str_pad($counter, 3, '0', STR_PAD_LEFT);
    $exists = $conn->query("SELECT id FROM citizens WHERE citizen_id='$citizen_id' LIMIT 1")->fetch_assoc();
    $counter++;
} while ($exists);

/* =======================
   IMAGE UPLOAD & RESIZE
======================= */
$image_path = null;

if (!empty($_FILES['citizen_image']['name'])) {

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($_FILES['citizen_image']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid image type']);
        exit;
    }

    if ($_FILES['citizen_image']['size'] > 2 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'Image must be under 2MB']);
        exit;
    }

    $upload_dir = '../uploads/citizens/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $safe_first = preg_replace('/[^a-z0-9]/', '', strtolower($first_name));
    $safe_last  = preg_replace('/[^a-z0-9]/', '', strtolower($last_name));
    $filename   = "{$safe_first}_{$safe_last}_{$citizen_id}.{$ext}";
    $target     = $upload_dir . $filename;

    list($w, $h) = getimagesize($_FILES['citizen_image']['tmp_name']);
    $size = 200;
    $dst = imagecreatetruecolor($size, $size);

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $src = imagecreatefromjpeg($_FILES['citizen_image']['tmp_name']);
            break;
        case 'png':
            $src = imagecreatefrompng($_FILES['citizen_image']['tmp_name']);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            break;
        case 'gif':
            $src = imagecreatefromgif($_FILES['citizen_image']['tmp_name']);
            break;
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, $w, $h);

    match ($ext) {
        'jpg', 'jpeg' => imagejpeg($dst, $target, 90),
        'png'         => imagepng($dst, $target),
        'gif'         => imagegif($dst, $target),
    };

    imagedestroy($dst);
    imagedestroy($src);

    $image_path = "uploads/citizens/$filename";
}

/* =======================
   INSERT CITIZEN
======================= */
$stmt = $conn->prepare("
INSERT INTO citizens
(citizen_id, first_name, last_name, community_id, village_id, house_address, phone, citizen_type, image_path, state_of_origin, created_by, created_at)
VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
");

$stmt->bind_param(
    "sssiisssssi",
    $citizen_id,
    $first_name,
    $last_name,
    $community_id,
    $village_id,
    $house_address,
    $phone,
    $citizen_type,
    $image_path,
    $state_of_origin,
    $user_id
);

if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to add citizen']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'Citizen added successfully',
    'citizen_id' => $citizen_id
]);
