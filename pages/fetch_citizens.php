<?php
session_start();

include('../auth_check.php');
requireRole(['admin', 'registrar']);
include('db.php');

// Ensure JSON-only output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json; charset=utf-8');

function respond($data)
{
    ob_clean();
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['username'])) {
    respond(['status' => 'error', 'message' => 'Unauthorized']);
}

$role = $_SESSION['role'] ?? '';
$assigned_community_id = $_SESSION['assigned_community_id'] ?? null;

/* ===========================
   Pagination & Filters
=========================== */
$page   = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$type   = trim($_GET['type'] ?? '');

$limit  = 10;
$offset = ($page - 1) * $limit;

$where  = [];
$params = [];
$types  = '';

if ($role !== 'admin') {
    $where[] = "c.community_id=?";
    $params[] = $assigned_community_id;
    $types   .= "i";
}

if ($search !== '') {
    $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.citizen_id LIKE ? OR c.phone LIKE ?)";
    $searchParam = "%{$search}%";
    array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
    $types .= "ssss";
}

if ($type !== '') {
    $where[] = "c.citizen_type=?";
    $params[] = $type;
    $types .= "s";
}

$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

/* ===========================
   Total Citizens Count
=========================== */
$totalStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM citizens c $whereSql");
if ($params) {
    $totalStmt->bind_param($types, ...$params);
}
$totalStmt->execute();
$total = (int)($totalStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$total_pages = max(1, ceil($total / $limit));

/* ===========================
   Fetch Citizens
=========================== */
$query = "
SELECT 
    c.*, 
    u.username AS created_by_name,
    com.name AS community_name,
    v.name AS village_name
FROM citizens c
LEFT JOIN users u ON u.id = c.created_by
LEFT JOIN communities com ON com.id = c.community_id
LEFT JOIN villages v ON v.id = c.village_id
$whereSql
ORDER BY c.created_at DESC
LIMIT ?, ?
";

$paramsWithLimit = $params;
$typesWithLimit  = $types . "ii";
$paramsWithLimit[] = $offset;
$paramsWithLimit[] = $limit;

$stmt = $conn->prepare($query);
$stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
$stmt->execute();
$res = $stmt->get_result();

$data = [];

while ($row = $res->fetch_assoc()) {

    /* ===========================
       Image Handling
    ============================ */
    $image_path = $row['image_path'] ?? '';
    if (!$image_path || !file_exists('../' . $image_path)) {
        $image_path = 'uploads/citizens/default-avatar.png';
    }
    $row['image_path'] = $image_path;

    /* ===========================
       Action Buttons
    ============================ */
    $row['actions_html'] =
        '<span class="badge bg-primary badge-action btn-edit" 
            data-citizen=\'' . json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . '\'>
            <i class="fa fa-edit"></i>
        </span>
        <span class="badge bg-success badge-action btn-issue-cert" data-id="' . (int)$row['id'] . '">
            <i class="fa fa-file"></i>
        </span>
        <span class="badge bg-danger badge-action btn-delete" data-id="' . (int)$row['id'] . '">
            <i class="fa fa-trash"></i>
        </span>';

    $data[] = $row;
}

/* ===========================
   Totals for Stat Cards
=========================== */
$totals = [
    'citizens' => $total,
    'users' => 0,
    'communities' => 0,
    'villages' => 0,
    'issued_certificates' => 0,
    'indigenes' => 0,
    'tenants' => 0
];

// Fetch other totals if needed
$row = $conn->query("SELECT COUNT(*) AS cnt FROM users")->fetch_assoc();
$totals['users'] = (int)$row['cnt'];

$row = $conn->query("SELECT COUNT(*) AS cnt FROM communities")->fetch_assoc();
$totals['communities'] = (int)$row['cnt'];

$row = $conn->query("SELECT COUNT(*) AS cnt FROM villages")->fetch_assoc();
$totals['villages'] = (int)$row['cnt'];

// You can calculate issued_certificates, indigenes, tenants similarly if needed

respond([
    'status' => 'success',
    'data' => $data,
    'total_pages' => $total_pages,
    'totals' => $totals
]);
