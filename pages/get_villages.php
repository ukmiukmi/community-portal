<?php
session_start();
include('db.php'); // your mysqli connection in $conn

header('Content-Type: application/json; charset=utf-8');

// Require login
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? '';
$assigned_community_id = $_SESSION['assigned_community_id'] ?? null;

if (isset($_GET['community_id'])) {
    $community_id = intval($_GET['community_id']);

    // If user is not admin, ensure they only access their assigned community
    if ($role !== 'admin' && $assigned_community_id && $community_id !== $assigned_community_id) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied']);
        exit;
    }

    $villages = [];
    $stmt = $conn->prepare("SELECT id, name FROM villages WHERE community_id = ? ORDER BY name ASC");
    $stmt->bind_param("i", $community_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $villages[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $villages]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'community_id not provided']);
