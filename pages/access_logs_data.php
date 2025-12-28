<?php
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  exit('Access denied');
}

require_once 'db.php';

/* ================= INPUTS ================= */
$page        = max(1, intval($_GET['page'] ?? 1));
$perPage     = max(1, intval($_GET['per_page'] ?? 50));
$offset      = ($page - 1) * $perPage;
$showDeleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == 1;
$search      = trim($_GET['search'] ?? '');
$threat      = trim($_GET['threat'] ?? '');

/* ================= WHERE CLAUSE ================= */
$where  = $showDeleted ? "deleted_at IS NOT NULL" : "deleted_at IS NULL";
$params = [];
$types  = '';

if ($search !== '') {
  $where .= " AND (username LIKE ? OR ip_address LIKE ? OR attempted_page LIKE ?)";
  $like = "%$search%";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types   .= 'sss';
}

if ($threat !== '') {
  $where .= " AND threat_level = ?";
  $params[] = $threat;
  $types   .= 's';
}

/* ================= MAIN QUERY ================= */
$sql = "
  SELECT *
  FROM access_logs
  WHERE $where
  ORDER BY id DESC
  LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;
$types   .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

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

/* ================= BUILD TABLE ROWS ================= */
$html = '';

while ($l = $res->fetch_assoc()) {

  $map = (!empty($l['lat']) && !empty($l['lon']))
    ? "<div class='log-map'
        data-lat='{$l['lat']}'
        data-lon='{$l['lon']}'
        style='height:120px;cursor:pointer'></div>"
    : "<span class='text-muted'>—</span>";

  $snap = !empty($l['snapshot'])
    ? "<a href='" . sanitize_snapshot($l['snapshot']) . "' target='_blank'>
         <img src='" . sanitize_snapshot($l['snapshot']) . "' width='80' class='img-thumbnail'>
       </a>"
    : "<span class='text-muted'>—</span>";

  $html .= "
    <tr id='logRow-{$l['id']}'>
      <td>
        <input type='checkbox' class='logCheckbox' data-id='{$l['id']}'>
      </td>
      <td>{$l['id']}</td>
      <td class='highlight-target'>" . htmlspecialchars($l['username'] ?? '') . "</td>
      <td class='highlight-target'>" . htmlspecialchars($l['role'] ?? '') . "</td>
      <td class='highlight-target'>" . htmlspecialchars($l['attempted_page'] ?? '') . "</td>
      <td class='highlight-target'>" . htmlspecialchars($l['ip_address'] ?? '') . "</td>
      <td>" . detect_device($l['user_agent'] ?? '') . "</td>
      <td>" . detect_browser($l['user_agent'] ?? '') . "</td>
      <td>{$l['created_at']}</td>
      <td>$map</td>
      <td>$snap</td>
      <td class='highlight-target'>" . htmlspecialchars($l['country'] ?? '') . "</td>
      <td class='highlight-target'>" . htmlspecialchars($l['country_code'] ?? '') . "</td>
      <td class='highlight-target'>" . htmlspecialchars($l['isp'] ?? '') . "</td>
      <td class='highlight-target'>" . htmlspecialchars($l['threat_level'] ?? '') . "</td>
      <td class='highlight-target'>" . htmlspecialchars($l['threat_reason'] ?? '') . "</td>
    </tr>
  ";
}

/* ================= PAGINATION ================= */
$totalRows = $conn->query("SELECT COUNT(*) AS cnt FROM access_logs WHERE $where")
  ->fetch_assoc()['cnt'];
$totalPages = max(1, ceil($totalRows / $perPage));

$pagination = '';
for ($i = 1; $i <= $totalPages; $i++) {
  $active = ($i === $page) ? 'active' : '';
  $pagination .= "
    <li class='page-item $active'>
      <a href='#' class='page-link' data-page='$i'>$i</a>
    </li>
  ";
}

/* ================= OUTPUT ================= */
echo json_encode([
  'html'       => $html,
  'pagination' => "<ul class='pagination justify-content-center'>$pagination</ul>",
  'total'      => $totalRows
]);
