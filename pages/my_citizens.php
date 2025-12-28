<?php
session_start();
include('db.php');

$role     = $_SESSION['role'] ?? '';
$assigned = $_SESSION['assigned_community_id'] ?? null;

/* ===========================
   Stats Counters
=========================== */
$citizens = $conn->query(
  ($role === 'registrar' && $assigned)
    ? "SELECT COUNT(*) t FROM citizens WHERE community_id=$assigned"
    : "SELECT COUNT(*) t FROM citizens"
)->fetch_assoc()['t'] ?? 0;

$indigenes = $conn->query(
  ($role === 'registrar' && $assigned)
    ? "SELECT COUNT(*) t FROM citizens WHERE citizen_type='indigene' AND community_id=$assigned"
    : "SELECT COUNT(*) t FROM citizens WHERE citizen_type='indigene'"
)->fetch_assoc()['t'] ?? 0;

$tenants = $conn->query(
  ($role === 'registrar' && $assigned)
    ? "SELECT COUNT(*) t FROM citizens WHERE citizen_type='tenant' AND community_id=$assigned"
    : "SELECT COUNT(*) t FROM citizens WHERE citizen_type='tenant'"
)->fetch_assoc()['t'] ?? 0;

/* ===========================
   Return JSON counts for AJAX
=========================== */
if (isset($_GET['action']) && $_GET['action'] === 'get_counts') {
  header('Content-Type: application/json');

  echo json_encode([
    'citizens'  => (int)$citizens,
    'indigenes' => (int)$indigenes,
    'tenants'   => (int)$tenants
  ]);
  exit;
}

/* ===========================
   Initial Citizens Fetch
=========================== */
$citizensResult = ($role === 'registrar' && $assigned)
  ? $conn->query("
      SELECT c.*, 
             u.username AS created_by_name,
             com.name AS community_name,
             v.name AS village_name
      FROM citizens c
      LEFT JOIN users u ON u.id=c.created_by
      LEFT JOIN communities com ON com.id=c.community_id
      LEFT JOIN villages v ON v.id=c.village_id
      WHERE c.community_id=$assigned
      ORDER BY c.created_at DESC
    ")
  : $conn->query("
      SELECT c.*, 
             u.username AS created_by_name,
             com.name AS community_name,
             v.name AS village_name
      FROM citizens c
      LEFT JOIN users u ON u.id=c.created_by
      LEFT JOIN communities com ON com.id=c.community_id
      LEFT JOIN villages v ON v.id=c.village_id
      ORDER BY c.created_at DESC
    ");

ob_start();
?>

<div class="container-fluid px-md-5 px-3 pt-5 mt-4">

  <!-- ===== Header ===== -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Citizens</h4>
    <a href="add_citizen.php" class="btn btn-success btn-sm">
      <i class="bi bi-plus-lg"></i> Add Citizen
    </a>
  </div>

  <!-- ===== Stats ===== -->
  <div class="row g-3 mb-4">
    <div class="col-md-4 col-6">
      <div class="stat-card bg-success">
        <i class="bi bi-people icon-badge"></i>
        <h4 class="counter" data-key="citizens" data-count="<?= (int)$citizens ?>">0</h4>
        <p>Total Citizens</p>
      </div>
    </div>

    <div class="col-md-4 col-6">
      <div class="stat-card bg-primary">
        <i class="bi bi-person-check icon-badge"></i>
        <h4 class="counter" data-key="indigenes" data-count="<?= (int)$indigenes ?>">0</h4>
        <p>Indigenes</p>
      </div>
    </div>

    <div class="col-md-4 col-6">
      <div class="stat-card bg-warning">
        <i class="bi bi-house icon-badge"></i>
        <h4 class="counter" data-key="tenants" data-count="<?= (int)$tenants ?>">0</h4>
        <p>Tenants</p>
      </div>
    </div>
  </div>

  <!-- ===== Search ===== -->
  <div class="mb-3">
    <input id="searchInput"
      class="form-control rounded-pill shadow-sm ps-4 py-2"
      placeholder="Search citizens..."
      onkeyup="loadCitizens(1)">
  </div>

  <!-- ===== Desktop Table ===== -->
  <div class="table-box table-responsive d-none d-md-block">
    <table class="table align-middle">
      <thead class="bg-dark text-white">
        <tr>
          <th>Image</th>
          <th>Citizen ID</th>
          <th>Full Name</th>
          <th>Phone</th>
          <th>Type</th>
          <th>State</th>
          <th>Community</th>
          <th>Village</th>
          <th>House Address</th>
          <th>Created By</th>
          <th>Created At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="citizensBody">
        <?php while ($citizen = $citizensResult->fetch_assoc()): ?>
          <?php
          $img = $citizen['image_path'];
          if (!$img || !file_exists('../' . $img)) {
            $img = 'uploads/citizens/default-avatar.png';
          }
          ?>
          <tr id="citizen-<?= htmlspecialchars($citizen['citizen_id']) ?>">
            <td><img src="<?= htmlspecialchars($img) ?>" class="rounded-circle" style="width:50px;height:50px;object-fit:cover;"></td>
            <td><?= htmlspecialchars($citizen['citizen_id']) ?></td>
            <td><?= htmlspecialchars($citizen['first_name'] . ' ' . $citizen['last_name']) ?></td>
            <td><?= htmlspecialchars($citizen['phone']) ?></td>
            <td><?= ucfirst(htmlspecialchars($citizen['citizen_type'])) ?></td>
            <td><?= htmlspecialchars($citizen['state_of_origin']) ?></td>
            <td><?= htmlspecialchars($citizen['community_name']) ?></td>
            <td><?= htmlspecialchars($citizen['village_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($citizen['house_address']) ?></td>
            <td><?= htmlspecialchars($citizen['created_by_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($citizen['created_at']) ?></td>
            <td><!-- AJAX actions --></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- ===== Mobile Cards ===== -->
  <div id="citizensCards" class="d-md-none row g-3">
    <?php
    $citizensResult->data_seek(0);
    while ($citizen = $citizensResult->fetch_assoc()):
      $img = $citizen['image_path'];
      if (!$img || !file_exists('../' . $img)) {
        $img = 'uploads/citizens/default-avatar.png';
      }
    ?>
      <div class="col-12" id="citizen-<?= htmlspecialchars($citizen['citizen_id']) ?>">
        <div class="card showCard">
          <div class="card-body d-flex gap-3">
            <img src="<?= htmlspecialchars($img) ?>"
              class="rounded"
              style="width:60px;height:60px;object-fit:cover;">
            <div class="flex-grow-1">
              <h5>
                <?= htmlspecialchars($citizen['first_name'] . ' ' . $citizen['last_name']) ?>
                <small class="text-muted">(<?= htmlspecialchars($citizen['citizen_id']) ?>)</small>
              </h5>
              <p><?= htmlspecialchars($citizen['phone']) ?></p>
              <p><?= ucfirst(htmlspecialchars($citizen['citizen_type'])) ?></p>
              <p><?= htmlspecialchars($citizen['community_name']) ?> • <?= htmlspecialchars($citizen['village_name'] ?? '-') ?></p>
            </div>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

  <div id="pagination" class="pagination mt-4"></div>

</div>

<?php
$communities = $conn->query("SELECT id, name FROM communities ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$states = $conn->query("SELECT name FROM states ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

include 'modals.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../css/my_citizens.css">

<script src="../js/search_citizens.js"></script>
<script src="../js/my_citizens.js"></script>

<?php
$content = ob_get_clean();
include('../include/layout.php');
?>