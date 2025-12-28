<?php
if (!isset($_SESSION)) session_start();
require_once './db.php';
$content = '';

// -------------------- FETCH SINGLE VILLAGE --------------------
if (isset($_GET['action']) && $_GET['action'] === 'fetch' && isset($_GET['id'])) {
  $id = intval($_GET['id']);
  $stmt = $conn->prepare("SELECT id, community_id, name FROM villages WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  echo json_encode($res);
  exit;
}

// -------------------- AJAX HANDLERS --------------------
if (isset($_POST['ajax']) || isset($_GET['ajax_reload']) || isset($_GET['ajax_totals'])) {

  // ADD / UPDATE VILLAGE
  if (isset($_POST['create']) || isset($_POST['village_id'])) {
    $id = intval($_POST['village_id'] ?? 0);
    $name = trim($_POST['village_name'] ?? '');
    $community_id = intval($_POST['village_community'] ?? 0);
    $errors = [];
    if (!$name) $errors[] = "Village name is required";
    if (!$community_id) $errors[] = "Community is required";

    $response = ['success' => false];
    if (empty($errors)) {
      if ($id) {
        $stmt = $conn->prepare("UPDATE villages SET name=?, community_id=? WHERE id=?");
        $stmt->bind_param("sii", $name, $community_id, $id);
        $stmt->execute();
        $stmt->close();
      } else {
        $stmt = $conn->prepare("INSERT INTO villages (community_id,name) VALUES (?,?)");
        $stmt->bind_param("is", $community_id, $name);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
      }
      $response['success'] = true;
      $response['village'] = ['id' => $id, 'name' => $name, 'community_id' => $community_id];
    } else {
      $response['errors'] = $errors;
    }
    echo json_encode($response);
    exit;
  }

  // DELETE VILLAGE
  if (isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM villages WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $response['success'] = $stmt->affected_rows > 0;
    $stmt->close();
    echo json_encode($response);
    exit;
  }

  // TOTAL COUNTERS
  if (isset($_GET['ajax_totals'])) {
    $totals = [];
    $totals['communities'] = intval($conn->query("SELECT COUNT(*) FROM communities")->fetch_row()[0]);
    $totals['villages'] = intval($conn->query("SELECT COUNT(*) FROM villages")->fetch_row()[0]);
    echo json_encode($totals);
    exit;
  }

  // AJAX RELOAD
  if (isset($_GET['ajax_reload'])) {
    $search = trim($_GET['search'] ?? '');
    $community = intval($_GET['community'] ?? 0);
    $page = intval($_GET['page'] ?? 1);
    $perPage = 10;
    $start = ($page - 1) * $perPage;
    $sort_column = $_GET['sort_column'] ?? 'v.id';
    $sort_order = $_GET['sort_order'] ?? 'DESC';
    $allowedSort = ['id' => 'v.id', 'name' => 'v.name', 'community_name' => 'c.name'];
    $sort_column = $allowedSort[$sort_column] ?? 'v.id';
    $sort_order = ($sort_order == 'asc') ? 'ASC' : 'DESC';

    $sql = "SELECT v.*, c.name AS community_name
                    FROM villages v
                    LEFT JOIN communities c ON v.community_id=c.id
                    WHERE 1 ";
    $params = [];
    $types = '';

    if ($search) {
      $sql .= " AND (v.name LIKE ? OR c.name LIKE ?) ";
      $like = "%$search%";
      $types .= "ss";
      $params[] = &$like;
      $params[] = &$like;
    }

    if ($community) {
      $sql .= " AND v.community_id=? ";
      $types .= "i";
      $params[] = &$community;
    }

    $countSql = "SELECT COUNT(*) FROM ($sql) AS temp";
    $stmt = $conn->prepare($countSql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalRows = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    $totalPages = ceil($totalRows / $perPage);

    $sql .= " ORDER BY $sort_column $sort_order LIMIT ?, ?";
    $types .= "ii";
    $params[] = &$start;
    $params[] = &$perPage;

    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $villages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    include 'village_table_partial.php';
    exit;
  }
}

// -------------------- PAGE CONTENT --------------------
ob_start();

$search = '';
$communityFilter = 0;
$page = intval($_GET['page'] ?? 1);
$perPage = 10;
$start = ($page - 1) * $perPage;

// Fetch initial villages
$stmt = $conn->prepare("SELECT v.*, c.name AS community_name FROM villages v LEFT JOIN communities c ON v.community_id=c.id ORDER BY v.id DESC LIMIT ?, ?");
$stmt->bind_param("ii", $start, $perPage);
$stmt->execute();
$villages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Communities for filter dropdown
$communities = $conn->query("SELECT id,name FROM communities ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Total pages for initial load
$totalRows = $conn->query("SELECT COUNT(*) FROM villages")->fetch_row()[0];
$totalPages = ceil($totalRows / $perPage);
?>

<div class="page-container">
  <div class="page-header">Manage Villages</div>

  <div class="search-filter-row">
    <input type="text" id="villageSearch" class="form-control" placeholder="Search villages...">
    <select id="communityFilter" class="form-control">
      <option value="">All Communities</option>
      <?php foreach ($communities as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn add-btn" id="openAddVillageModal"><i class="fa fa-plus"></i> Add Village</button>
  </div>

  <div class="d-flex gap-3 mb-3">
    <div class="card p-3 flex-fill text-center">
      <div>Total Communities</div>
      <div id="totalCommunitiesCounter" style="font-size:1.5rem;font-weight:600">0</div>
    </div>
    <div class="card p-3 flex-fill text-center">
      <div>Total Villages</div>
      <div id="totalVillagesCounter" style="font-size:1.5rem;font-weight:600">0</div>
    </div>
  </div>

  <div id="villageTableWrapper">
    <?php include 'village_table_partial.php'; ?>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addEditVillageModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="villageModalTitle">Add Village</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="villageForm">
        <input type="hidden" id="village_id" name="village_id">
        <div class="modal-body">
          <div class="form-group">
            <label>Community</label>
            <select name="village_community" id="village_community" class="form-control" required>
              <option value="">Select Community</option>
              <?php foreach ($communities as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Village Name</label>
            <input type="text" name="village_name" id="village_name" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary save-btn">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<link rel="stylesheet" href="../css/manage_villages.css">
<script src="../js/manage_villages.js"></script>

<?php
$content = ob_get_clean();
require '../include/layout.php';
?>