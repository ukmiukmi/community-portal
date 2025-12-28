<?php
error_reporting(E_ERROR); // hide notices/warnings for AJAX
session_start();
include('../auth_check.php');
requireRole('admin');
include('db.php');
header('Content-Type: application/json');

// ===== POST Requests =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Single assign
    if (isset($_POST['registrar_id'], $_POST['community_id'])) {
        $registrar_id = intval($_POST['registrar_id']);
        $community_id = intval($_POST['community_id']);
        $stmt = $conn->prepare("UPDATE users SET assigned_community_id=? WHERE id=? AND role='registrar'");
        $stmt->bind_param("ii", $community_id, $registrar_id);
        echo json_encode($stmt->execute() ?
            ['status' => 'success', 'message' => 'Community successfully assigned!'] :
            ['status' => 'error', 'message' => 'Failed to assign community.']);
        exit;
    }

    // Single remove
    if (isset($_POST['remove_id'])) {
        $id = intval($_POST['remove_id']);
        $stmt = $conn->prepare("UPDATE users SET assigned_community_id=NULL WHERE id=? AND role='registrar'");
        $stmt->bind_param("i", $id);
        echo json_encode($stmt->execute() ?
            ['status' => 'success', 'message' => 'Community removed successfully!'] :
            ['status' => 'error', 'message' => 'Failed to remove community.']);
        exit;
    }
}

// ===== GET Requests =====
if (isset($_GET['action'])) {

    // Fetch communities
    if ($_GET['action'] === 'get_communities') {
        $res = $conn->query("SELECT id,name FROM communities ORDER BY name");
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // Fetch assigned registrars (filters + pagination)
    if ($_GET['action'] === 'get_assigned') {
        $search = $_GET['search'] ?? '';
        $community = $_GET['community'] ?? '';
        $role = $_GET['role'] ?? '';
        $page = max(intval($_GET['page'] ?? 1), 1);
        $pageSize = max(intval($_GET['pageSize'] ?? 5), 1);

        $where = "WHERE role='registrar'";
        $params = [];
        $types = '';

        if ($search !== '') {
            $where .= " AND username LIKE ?";
            $params[] = "%$search%";
            $types .= 's';
        }
        if ($community !== '') {
            $where .= " AND assigned_community_id=?";
            $params[] = $community;
            $types .= 'i';
        }
        if ($role !== '') {
            $where .= " AND role=?";
            $params[] = $role;
            $types .= 's';
        }

        // Count total
        $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN communities c ON u.assigned_community_id=c.id $where");
        if ($params) $stmtCount->bind_param($types, ...$params);
        $stmtCount->execute();
        $total = $stmtCount->get_result()->fetch_assoc()['total'] ?? 0;

        // Main query with LIMIT
        $offset = ($page - 1) * $pageSize;
        $stmt = $conn->prepare("SELECT u.id,u.username,u.assigned_community_id,c.name as community_name
            FROM users u LEFT JOIN communities c ON u.assigned_community_id=c.id
            $where ORDER BY u.username ASC LIMIT ? OFFSET ?");
        $typesWithLimit = $types . 'ii';
        $paramsWithLimit = $params;
        $paramsWithLimit[] = $pageSize;
        $paramsWithLimit[] = $offset;
        $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;

        echo json_encode(['status' => 'success', 'data' => $data, 'total' => $total]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
