<?php
if (!isset($_SESSION)) session_start();
require_once 'db.php';

/* ================= SECURITY ================= */
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  exit('Access denied');
}

/* ================= CSRF ================= */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ================= IP BANNING ================= */
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$stmt = $conn->prepare("SELECT banned_until FROM banned_ips WHERE ip_address=? LIMIT 1");
$stmt->bind_param("s", $ip);
$stmt->execute();
$stmt->bind_result($ban_until);
if ($stmt->fetch() && (!$ban_until || strtotime($ban_until) > time())) {
  exit('Your IP is blocked');
}
$stmt->close();

/* ================= HELPERS ================= */
function detect_device($ua)
{
  $ua = strtolower($ua ?? '');
  if (preg_match('/mobile|iphone|android/', $ua)) return 'Mobile';
  if (preg_match('/tablet|ipad/', $ua)) return 'Tablet';
  return 'Desktop';
}

function detect_browser($ua)
{
  $ua = $ua ?? '';
  if (preg_match('/edge/i', $ua)) return 'Edge';
  if (preg_match('/chrome/i', $ua)) return 'Chrome';
  if (preg_match('/firefox/i', $ua)) return 'Firefox';
  if (preg_match('/safari/i', $ua)) return 'Safari';
  return 'Other';
}

function sanitize_snapshot($path)
{
  return $path ? '../uploads/snapshots/' . basename($path) : '';
}

/* ================= PAGE STATE ================= */
$showDeleted = !empty($_GET['show_deleted']);

/* ================= HTML OUTPUT ================= */
ob_start();
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
<link rel="stylesheet" href="../css/access_logs.css">

<div class="access-logs-container container-fluid p-4">
  <h3>Access Logs</h3>

  <!-- SEARCH -->
  <div class="mb-3">
    <input type="text" id="searchLogs" class="form-control" placeholder="Search username, IP, page">
  </div>

  <!-- ACTION BUTTONS -->
  <div class="mb-3">
    <button class="access-logs-btn" id="selectCurrent">Select / Deselect Page</button>
    <button class="access-logs-btn" id="selectAllPages">Select All Pages</button>
    <button class="access-logs-btn" id="exportBtn">Export Selected</button>

    <button class="access-logs-btn btn-danger" id="deleteBtn">Delete</button>
    <button class="access-logs-btn btn-success" id="restoreBtn">Restore</button>

    <button class="access-logs-btn btn-warning" id="showDeletedBtn">
      <?= $showDeleted ? 'Show Active' : 'Show Deleted' ?>
    </button>
  </div>

  <!-- TABLE -->
  <div class="table-wrapper">
    <table class="table table-bordered align-middle" id="logsTable">
      <thead class="table-dark">
        <tr>
          <th><input type="checkbox" id="selectAll"></th>
          <th>ID</th>
          <th>User</th>
          <th>Role</th>
          <th>Page</th>
          <th>IP</th>
          <th>Device</th>
          <th>Browser</th>
          <th>Date</th>
          <th>Location</th>
          <th>Snapshot</th>
          <th>Country</th>
          <th>Code</th>
          <th>ISP</th>
          <th>Threat</th>
          <th>Reason</th>
        </tr>
      </thead>
      <tbody>
        <!-- Table rows loaded via AJAX -->
      </tbody>
    </table>
  </div>

  <div id="pagination" class="mt-3 text-center">
    <!-- Pagination loaded dynamically -->
  </div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
  const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';
  let SHOW_DELETED = <?= $showDeleted ? 1 : 0 ?>;
</script>
<script src="../js/access_logs.js"></script>

<?php
$content = ob_get_clean();
require_once '../include/layout.php';
