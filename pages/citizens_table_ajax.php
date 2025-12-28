<?php
session_start();
include __DIR__ . '/../auth_check.php';
requireRole(['admin', 'registrar']);
include __DIR__ . '/db.php';

$role = $_SESSION['role'] ?? 'user';
$assigned_community_id = $_SESSION['assigned_community_id'] ?? null;

function highlight($text, $query = '')
{
    if (!$query) return htmlspecialchars($text);
    return preg_replace('/(' . preg_quote($query, '/') . ')/i', '<mark>$1</mark>', htmlspecialchars($text));
}

// ---------- POST HANDLERS ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];
    $id = intval($_POST['id'] ?? 0);

    if ($action === 'delete') {
        if ($id < 1) exit(json_encode(['status' => 'error', 'message' => 'Invalid ID']));

        if ($role === 'admin') {
            $stmt = $conn->prepare("DELETE FROM citizens WHERE id=?");
            $stmt->bind_param('i', $id);
        } else {
            $stmt = $conn->prepare("DELETE FROM citizens WHERE id=? AND community_id=?");
            $stmt->bind_param('ii', $id, $assigned_community_id);
        }

        $ok = $stmt->execute();
        echo json_encode([
            'status' => $ok ? 'success' : 'error',
            'message' => $ok ? 'Citizen deleted successfully.' : 'Failed to delete citizen.'
        ]);
        exit;
    }

    if ($action === 'update') {
        // keep update flow later if needed
    }

    if ($action === 'issue_certificate') {
        echo json_encode(['status' => 'success', 'message' => 'Certificate issued (handler pending implementation)']);
        exit;
    }
}

// ---------- GET HANDLERS ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? null;
    $id = intval($_GET['id'] ?? 0);

    // ----- Citizen JSON for EDIT modal -----
    if ($action === 'get') {
        header('Content-Type: application/json; charset=utf-8');
        if ($id < 1) {
            echo json_encode([]);
            exit;
        }

        if ($role === 'admin') {
            $stmt = $conn->prepare(
                "SELECT c.id, 
                        c.citizen_id,
                        c.first_name,
                        c.last_name,
                        c.phone,
                        c.citizen_type,
                        c.state_of_origin AS state_of_origin,
                        c.community_id AS community_id,
                        c.village_id AS village_id,
                        c.house_address AS house_address,
                        c.image_path AS image_path
                 FROM citizens c WHERE c.id=?"
            );
            $stmt->bind_param('i', $id);
        } else {
            $stmt = $conn->prepare(
                "SELECT c.id, 
                        c.citizen_id,
                        c.first_name,
                        c.last_name,
                        c.phone,
                        c.citizen_type,
                        c.state_of_origin AS state_of_origin,
                        c.community_id AS community_id,
                        c.village_id AS village_id,
                        c.house_address AS house_address,
                        c.image_path AS image_path
                 FROM citizens c WHERE c.id=? AND c.community_id=?"
            );
            $stmt->bind_param('ii', $id, $assigned_community_id);
        }
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_assoc() ?: []);
        exit;
    }

    // ----- AJAX table HTML -----
    if (isset($_GET['ajax'])) {
        header('Content-Type: text/html; charset=utf-8');

        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];
        $types = '';

        if ($role !== 'admin' && $assigned_community_id) {
            $where[] = 'c.community_id=?';
            $params[] = $assigned_community_id;
            $types .= 'i';
        }

        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.phone LIKE ? OR c.citizen_id LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
            $types .= 'ssss';
        }

        $filterType = trim($_GET['type'] ?? '');
        if ($filterType && in_array(strtolower($filterType), ['indigene', 'tenant'])) {
            $where[] = 'c.citizen_type=?';
            $params[] = $filterType;
            $types .= 's';
        }

        $validSort = ['id', 'citizen_id', 'first_name', 'last_name', 'phone', 'citizen_type', 'state_of_origin', 'community_name', 'village_name', 'house_address', 'created_at'];
        $sortBy = in_array($_GET['sort'] ?? '', $validSort) ? $_GET['sort'] : 'id';
        $sortDir = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // total count
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM citizens c $where_sql");
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total_rows = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $total_pages = max(1, ceil($total_rows / $limit));

        // fetch rows
        $sql = "SELECT c.id,
                       c.citizen_id,
                       c.first_name,
                       c.last_name,
                       c.phone,
                       c.citizen_type,
                       c.state_of_origin AS state_of_origin,
                       com.name AS community_name,
                       v.name AS village_name,
                       c.house_address AS house_address,
                       c.image_path AS image_path,
                       c.village_id
                FROM citizens c
                LEFT JOIN communities com ON c.community_id=com.id
                LEFT JOIN villages v ON c.village_id=v.id
                $where_sql
                ORDER BY $sortBy $sortDir
                LIMIT ?,?";
        $paramsWithLimit = array_merge($params, [$offset, $limit]);
        $typesWithLimit = $types . 'ii';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
        $stmt->execute();
        $result = $stmt->get_result();

        ob_start();
?>

        <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Citizen ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Phone</th>
                    <th>Image</th>
                    <th>Type</th>
                    <th>State</th>
                    <th>Community</th>
                    <th>Village</th>
                    <th>Address</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>

                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr data-id="<?= $row['id'] ?>">
                        <td><?= highlight($row['id'], $search) ?></td>
                        <td><?= highlight($row['citizen_id'], $search) ?></td>
                        <td><?= highlight($row['first_name'], $search) ?></td>
                        <td><?= highlight($row['last_name'], $search) ?></td>
                        <td><?= highlight($row['phone'], $search) ?></td>
                        <td>
                            <?php if (!empty($row['image_path'])): ?>
                                <img src="../<?= htmlspecialchars($row['image_path']) ?>" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
                                <?php else: ?>N/A<?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['citizen_type']) ?></td>
                        <td><?= htmlspecialchars($row['state_of_origin']) ?></td>
                        <td><?= htmlspecialchars($row['community_name']) ?></td>
                        <td><?= htmlspecialchars($row['village_name']) ?></td>
                        <td><?= htmlspecialchars($row['house_address']) ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-primary btn-edit" data-id="<?= $row['id'] ?>">Edit</button>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="<?= $row['id'] ?>">Delete</button>
                            <button class="btn btn-sm btn-warning btn-cert" data-id="<?= $row['id'] ?>">Cert</button>
                        </td>
                    </tr>
                <?php endwhile; ?>

            </tbody>
        </table>

        <nav>
            <ul class="pagination pagination-sm justify-content-center flex-wrap">
                <?php if ($page > 1): ?><li class="page-item"><a href="#" class="page-link" data-page="<?= $page - 1 ?>">«</a></li><?php endif;
                                                                                                                                for ($i = max(1, $page - 3); $i <= min($total_pages, $page + 3); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a href="#" class="page-link" data-page="<?= $i ?>"><?= $i ?></a></li>
                <?php endfor;
                                                                                                                                if ($page < $total_pages): ?><li class="page-item"><a href="#" class="page-link" data-page="<?= $page + 1 ?>">»</a></li><?php endif; ?>
            </ul>
        </nav>

<?php
        echo ob_get_clean();
        exit;
    }
}
