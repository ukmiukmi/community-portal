<?php
session_start();
include('../db.php');
header('Content-Type: application/json');

function response($status, $message = '', $data = [])
{
    echo json_encode(['status' => $status, 'message' => $message, 'users' => $data]);
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'fetch') {
    $users = [];
    $res = $conn->query("SELECT u.id, u.full_name, u.username, u.role, u.assigned_community_id, u.profile_image, u.created_at, c.name as community_name
                         FROM users u
                         LEFT JOIN communities c ON u.assigned_community_id = c.id
                         ORDER BY u.full_name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) $users[] = $row;
        response('success', '', $users);
    } else response('error', 'Failed to fetch users');
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? null;
    if (!$id) response('error', 'User ID required');
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    response('success', 'User deleted');
}

// Save (Add/Edit)
if ($action === 'save') {
    $id = $_POST['id'] ?? null;
    $full_name = $_POST['full_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $role = $_POST['role'] ?? '';
    $assigned_community_id = $_POST['assigned_community_id'] ?: null;
    $password = $_POST['password'] ?? '';

    // Handle file upload
    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['tmp_name'] != '') {
        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $profile_image = time() . "_" . rand(1000, 9999) . "." . $ext;
        move_uploaded_file($_FILES['profile_image']['tmp_name'], "../../uploads/users/" . $profile_image);
    }

    if ($id) { // edit
        $query = "UPDATE users SET full_name=?, username=?, role=?, assigned_community_id=?";
        $types = "sssi";
        $params = [$full_name, $username, $role, $assigned_community_id];

        if (!empty($password)) {
            $query .= ", password_hash=?";
            $types .= "s";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($profile_image) {
            $query .= ", profile_image=?";
            $types .= "s";
            $params[] = $profile_image;
        }

        $query .= " WHERE id=?";
        $types .= "i";
        $params[] = $id;

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        response('success', 'User updated');
    } else { // add
        $pw = password_hash($password, PASSWORD_DEFAULT);
        if (!$profile_image) $profile_image = 'default.png';

        $stmt = $conn->prepare("INSERT INTO users (full_name,username,password_hash,role,assigned_community_id,profile_image,created_at) VALUES (?,?,?,?,?,?,NOW())");
        $stmt->bind_param("ssssis", $full_name, $username, $pw, $role, $assigned_community_id, $profile_image);
        $stmt->execute();
        response('success', 'User added');
    }
}

response('error', 'Invalid action');
