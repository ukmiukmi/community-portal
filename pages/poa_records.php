<?php
session_start();
include('../auth_check.php');
requireRole(['admin', 'registrar']);
include('db.php');

$role = $_SESSION['role'];
$assigned_community_id = $_SESSION['assigned_community_id'] ?? null;

// -------------------- Fetch Communities for Filter --------------------
$communities = [];
if ($role === 'admin') {
  $comQuery = $conn->query("SELECT id, name FROM communities ORDER BY name");
  while ($c = $comQuery->fetch_assoc()) $communities[] = $c;
}

// -------------------- AJAX HANDLING --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // ---------------- BULK ACTIONS ----------------
  if (!empty($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $allPages = isset($_POST['all_pages']) ? true : false;
    $ids = $_POST['ids'] ?? [];

    if ($action === 'delete') {
      if ($allPages) {
        $whereSQL = ($role === 'registrar' && $assigned_community_id) ? "WHERE c.community_id = $assigned_community_id" : "WHERE 1";
        $conn->query("DELETE t FROM land_power_transactions t JOIN citizens c ON t.citizen_id = c.id $whereSQL");
      } else if (!empty($ids)) {
        $id_list = implode(",", array_map('intval', $ids));
        $conn->query("DELETE FROM land_power_transactions WHERE id IN ($id_list)");
      }
      echo json_encode(['status' => 'success']);
      exit;
    }

    // ---------------- DOWNLOAD / ZIP / CSV ----------------
    if (in_array($action, ['download', 'zip', 'csv'])) {
      $files = [];
      foreach ($ids as $id) {
        // Adjust path if your files are in a different folder
        $filePath = "../uploads/poa_$id.pdf";
        if (file_exists($filePath)) $files[] = $filePath;
      }

      echo json_encode([
        'status' => 'success',
        'files' => $files
      ]);
      exit;
    }
  }

  // ---------------- FILTERED FETCH SAFE ----------------
  $search = $_POST['search'] ?? '';
  $month  = $_POST['month'] ?? '';
  $year   = $_POST['year'] ?? '';
  $community = $_POST['community'] ?? '';
  $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
  $records_per_page = 10;
  $offset = ($page - 1) * $records_per_page;

  $where = [];
  $params = [];
  $types = "";

  if ($role === 'registrar' && $assigned_community_id) {
    $where[] = "c.community_id = ?";
    $params[] = $assigned_community_id;
    $types .= "i";
  }

  if ($community) {
    $where[] = "c.community_id = ?";
    $params[] = intval($community);
    $types .= "i";
  }

  if ($search) {
    $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR t.serial_no LIKE ? OR t.payment_amount LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $types .= "ssss";
  }

  if ($month) {
    [$y, $m] = explode("-", $month);
    $where[] = "MONTH(t.payment_date) = ? AND YEAR(t.payment_date) = ?";
    $params[] = intval($m);
    $params[] = intval($y);
    $types .= "ii";
  }
  if ($year) {
    $where[] = "YEAR(t.payment_date) = ?";
    $params[] = intval($year);
    $types .= "i";
  }

  $whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

  // ---------------- COUNT TOTAL RECORDS ----------------
  $countSQL = "SELECT COUNT(*) AS total 
                 FROM land_power_transactions t 
                 JOIN citizens c ON t.citizen_id = c.id 
                 LEFT JOIN communities com ON c.community_id = com.id 
                 $whereSQL";

  $stmt = $conn->prepare($countSQL);
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $total_records = $stmt->get_result()->fetch_assoc()['total'];
  $total_pages = ceil($total_records / $records_per_page);
  $stmt->close();

  // ---------------- FETCH RECORDS ----------------
  $selectSQL = "SELECT t.*, c.first_name, c.last_name, com.name AS community_name
                  FROM land_power_transactions t
                  JOIN citizens c ON t.citizen_id = c.id
                  LEFT JOIN communities com ON c.community_id = com.id
                  $whereSQL
                  ORDER BY t.id DESC
                  LIMIT ? OFFSET ?";

  $stmt = $conn->prepare($selectSQL);
  $paramsWithLimit = $params;
  $typesWithLimit = $types . "ii";
  $paramsWithLimit[] = $records_per_page;
  $paramsWithLimit[] = $offset;

  $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $stmt->close();

  function amountBadge($amount)
  {
    if ($amount < 50000) return 'badge-success';
    if ($amount <= 200000) return 'badge-warning';
    return 'badge-danger';
  }

  // ---------------- BUILD TABLE ----------------
  $table = '';
  if ($rows) {
    foreach ($rows as $r) {
      $table .= '<tr>';
      $table .= '<td><input type="checkbox" class="recordCheckbox" value="' . $r['id'] . '"></td>';
      $table .= '<td data-original="' . htmlspecialchars($r['serial_no']) . '">' . htmlspecialchars($r['serial_no']) . '</td>';
      $table .= '<td data-original="' . htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) . '">' . htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) . '</td>';
      $table .= '<td data-original="' . htmlspecialchars($r['community_name']) . '"><span class="badge-community">' . htmlspecialchars($r['community_name']) . '</span></td>';
      $table .= '<td data-original="₦' . number_format($r['payment_amount'], 2) . '"><span class="badge-amount ' . amountBadge($r['payment_amount']) . '">₦' . number_format($r['payment_amount'], 2) . '</span></td>';
      $table .= '<td data-original="' . date("d M Y", strtotime($r['payment_date'])) . '">' . date("d M Y", strtotime($r['payment_date'])) . '</td>';
      $table .= '<td><a href="view_poa.php?id=' . $r['id'] . '" class="btn btn-sm btn-outline-primary">View</a></td>';
      $table .= '</tr>';
    }
  } else {
    $table = '<tr><td colspan="7" class="text-center text-muted py-4">No records found.</td></tr>';
  }

  // ---------------- BUILD MOBILE CARDS ----------------
  $cards = '';
  if ($rows) {
    foreach ($rows as $r) {
      $cards .= '<div class="col-12 mobile-card">';
      $cards .= '<div class="card shadow border-0 rounded-4 p-3">';
      $cards .= '<input type="checkbox" class="recordCheckbox mb-2" value="' . $r['id'] . '">';
      $cards .= '<h6 class="fw-bold text-primary">' . htmlspecialchars($r['serial_no']) . '</h6>';
      $cards .= '<p class="mb-1">' . htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) . '</p>';
      $cards .= '<p class="mb-1 small"><span class="badge-community">' . htmlspecialchars($r['community_name']) . '</span></p>';
      $cards .= '<p class="mb-2"><span class="badge-amount ' . amountBadge($r['payment_amount']) . '">₦' . number_format($r['payment_amount'], 2) . '</span></p>';
      $cards .= '<p class="mb-2 small text-secondary">' . date("d M Y", strtotime($r['payment_date'])) . '</p>';
      $cards .= '<a href="view_poa.php?id=' . $r['id'] . '" class="btn btn-sm btn-primary rounded-pill px-3">View</a>';
      $cards .= '</div></div>';
    }
  } else {
    $cards = '<div class="col-12"><div class="card shadow border-0 rounded-4 p-4 text-center text-muted"><h5>No records found</h5></div></div>';
  }

  // ---------------- BUILD PAGINATION ----------------
  $pagination = '<ul class="pagination pagination-sm">';
  for ($i = 1; $i <= $total_pages; $i++) {
    $active = $i == $page ? 'active' : '';
    $pagination .= '<li class="page-item ' . $active . '" data-page="' . $i . '"><a class="page-link rounded-circle" href="#">' . $i . '</a></li>';
  }
  $pagination .= '</ul>';

  // ---------------- STATS ----------------
  $statsQuery = "SELECT COUNT(*) AS totalCertificates,
                          SUM(t.payment_amount) AS totalAmount,
                          SUM(CASE WHEN MONTH(t.payment_date)=MONTH(CURDATE()) AND YEAR(t.payment_date)=YEAR(CURDATE()) THEN 1 ELSE 0 END) AS totalCertificatesMonth,
                          SUM(CASE WHEN YEAR(t.payment_date)=YEAR(CURDATE()) THEN 1 ELSE 0 END) AS totalCertificatesYear
                   FROM land_power_transactions t
                   JOIN citizens c ON t.citizen_id = c.id
                   LEFT JOIN communities com ON c.community_id = com.id
                   $whereSQL";
  $stmt = $conn->prepare($statsQuery);
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $statsRes = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $stats = [
    'totalCertificates' => $statsRes['totalCertificates'] ?? 0,
    'totalCertificatesMonth' => $statsRes['totalCertificatesMonth'] ?? 0,
    'totalCertificatesYear' => $statsRes['totalCertificatesYear'] ?? 0,
    'totalAmount' => number_format($statsRes['totalAmount'] ?? 0, 2)
  ];

  echo json_encode([
    'status' => 'success',
    'data' => [
      'table' => $table,
      'cards' => $cards,
      'pagination' => $pagination,
      'stats' => $stats
    ]
  ]);
  exit;
}

// -------------------- PAGE LAYOUT --------------------
ob_start();
?>
<link rel="stylesheet" href="../css/poa_records.css">
<div class="container-fluid py-4">
  <div class="text-center text-md-start mb-4">
    <h2 class="fw-bold text-primary">Power of Attorney Records</h2>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <input type="text" id="searchInput" class="form-control rounded-pill px-3" style="max-width:320px;" placeholder="Search citizen, serial or amount">
      <?php if ($role === 'admin'): ?>
        <select id="filterCommunity" class="form-select rounded-pill px-3">
          <option value="">All Communities</option>
          <?php foreach ($communities as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <input type="month" id="filterMonth" class="form-control rounded-pill px-3" style="max-width:160px;">
      <input type="number" id="filterYear" class="form-control rounded-pill px-3" style="max-width:120px;" placeholder="Year">
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="row g-3 mb-3" id="statsCards">
    <div class="col-md-3">
      <div class="card">
        <h6>Total Certificates</h6>
        <h4 id="totalCertificates">0</h4>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card">
        <h6>Certificates This Month</h6>
        <h4 id="totalCertificatesMonth">0</h4>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card">
        <h6>Certificates This Year</h6>
        <h4 id="totalCertificatesYear">0</h4>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card">
        <h6>Total Amount</h6>
        <h4 id="totalAmount">₦0.00</h4>
      </div>
    </div>
  </div>


  <!-- Bulk Actions -->
  <div class="mb-3 d-flex flex-wrap gap-2">
    <button id="bulkDelete" class="btn btn-danger btn-sm rounded-pill">Delete Selected</button>
    <button id="bulkDownload" class="btn btn-primary btn-sm rounded-pill">Download Selected</button>
    <button id="bulkZip" class="btn btn-secondary btn-sm rounded-pill">Download ZIP</button>
    <button id="exportCSV" class="btn btn-info btn-sm rounded-pill">Export CSV</button>
    <div class="form-check ms-auto">
      <input class="form-check-input" type="checkbox" value="" id="selectAll">
      <label class="form-check-label" for="selectAll">Select All on Page</label>
    </div>
    <div class="form-check ms-2">
      <input class="form-check-input" type="checkbox" value="" id="selectAllPages">
      <label class="form-check-label" for="selectAllPages">Select All Across Pages</label>
    </div>
  </div>

  <!-- Desktop Table -->
  <div class="card desktop-table shadow-sm border-0 rounded-4">
    <div class="card-body p-0">
      <div class="table-responsive rounded-4 overflow-hidden">
        <table class="table table-hover mb-0">
          <thead class="text-uppercase">
            <tr>
              <th><input type="checkbox" id="selectAllHeader"></th>
              <th>Serial</th>
              <th>Citizen</th>
              <th>Community</th>
              <th>Amount</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="tableBodyDesktop"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Mobile Cards -->
  <div id="tableBodyMobile" class="row g-3 mt-2"></div>

  <!-- Pagination -->
  <nav class="mt-4 d-flex justify-content-center" id="paginationWrapDesktop"></nav>
</div>
<script src="../js/poa_records.js"></script>
<?php
$content = ob_get_clean();
include('../include/layout.php');
?>