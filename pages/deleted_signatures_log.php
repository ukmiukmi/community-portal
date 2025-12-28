<?php
session_start();
include('../auth_check.php');
requireRole(['admin']);
include('db.php');

// Ensure restored_at column exists
$conn->query("ALTER TABLE deleted_signatures ADD COLUMN IF NOT EXISTS restored_at DATETIME NULL");

// Fetch communities for filters
$communities = $conn->query("SELECT id,name FROM communities ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// ----------------------- BULK DELETE / DOWNLOAD -----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  $data = $_POST; // Use $_POST for normal POST from JS

  // Bulk Delete
  if (isset($data['delete_ids'])) {
    $ids = $data['delete_ids'];
    if (!is_array($ids) || empty($ids)) {
      echo json_encode(['status' => 'error', 'message' => 'No records selected']);
      exit;
    }
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("DELETE FROM deleted_signatures WHERE id IN ($placeholders)");
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    echo json_encode($stmt->execute() ? ['status' => 'success'] : ['status' => 'error', 'message' => 'Failed to delete']);
    exit;
  }

  // Bulk Download
  if (isset($data['ids'])) {
    $ids = $data['ids'];
    if (!is_array($ids) || empty($ids)) {
      echo json_encode(['status' => 'error', 'message' => 'No records selected']);
      exit;
    }
    $files = [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT file_path FROM deleted_signatures WHERE id IN ($placeholders)");
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($res as $row) {
      $file = realpath(__DIR__ . '/../' . $row['file_path']);
      if ($file && file_exists($file)) $files[] = '../' . $row['file_path'];
    }
    echo json_encode(['status' => 'success', 'files' => $files]);
    exit;
  }

  // AJAX Fetch Table
  if (isset($data['action']) && $data['action'] === 'fetch') {
    header('Content-Type: application/json; charset=utf-8');

    $where = [];
    $params = [];
    $types = '';

    if (!empty($data['community_id'])) {
      $where[] = 'ds.community_id=?';
      $params[] = intval($data['community_id']);
      $types .= 'i';
    }
    if (!empty($data['role'])) {
      $where[] = 'ds.role=?';
      $params[] = $data['role'];
      $types .= 's';
    }
    if (!empty($data['from']) && !empty($data['to'])) {
      $where[] = 'DATE(ds.deleted_at) BETWEEN ? AND ?';
      $params[] = $data['from'];
      $params[] = $data['to'];
      $types .= 'ss';
    }
    if (!empty($data['search'])) {
      $search = "%{$data['search']}%";
      $where[] = '(c.name LIKE ? OR ds.role LIKE ? OR ds.deleted_by LIKE ? OR ds.reason LIKE ?)';
      $params = array_merge($params, [$search, $search, $search, $search]);
      $types .= 'ssss';
    }

    $page = max(1, intval($data['page'] ?? 1));
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    $allowedSort = ['id', 'community_name', 'role', 'deleted_by', 'reason', 'deleted_at', 'restored_at'];
    $sortColumn = in_array($data['sort_column'] ?? 'deleted_at', $allowedSort) ? $data['sort_column'] : 'deleted_at';
    $sortOrder = ($data['sort_order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

    $query = "SELECT ds.*, c.name AS community_name
                  FROM deleted_signatures ds
                  LEFT JOIN communities c ON ds.community_id=c.id " .
      ($where ? "WHERE " . implode(' AND ', $where) : '') .
      " ORDER BY $sortColumn $sortOrder LIMIT $offset,$perPage";

    $stmt = $conn->prepare($query);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Total rows
    $countQuery = "SELECT COUNT(*) as total FROM deleted_signatures ds LEFT JOIN communities c ON ds.community_id=c.id " .
      ($where ? "WHERE " . implode(' AND ', $where) : '');
    $stmt2 = $conn->prepare($countQuery);
    if ($params) $stmt2->bind_param($types, ...$params);
    $stmt2->execute();
    $total = $stmt2->get_result()->fetch_assoc()['total'] ?? 0;
    $totalPages = max(1, ceil($total / $perPage));

    // Table HTML
    ob_start();
    foreach ($logs as $row) {
      $id = (int)$row['id'];
      $communityName = htmlspecialchars($row['community_name'] ?? 'Unknown');
      $role = htmlspecialchars(ucfirst($row['role']));
      $deletedBy = htmlspecialchars($row['deleted_by']);
      $reason = htmlspecialchars($row['reason']);
      $deletedAt = $row['deleted_at'];
      $restoredAt = $row['restored_at'];

      echo "<tr class='hover:bg-gray-50'>";
      echo "<td class='px-3 py-2 text-center'><input type='checkbox' class='recordCheckbox' value='{$id}'></td>";
      echo "<td class='px-3 py-2'>{$id}</td>";
      echo "<td class='px-3 py-2'>{$communityName}</td>";
      echo "<td class='px-3 py-2'>{$role}</td>";
      echo "<td class='px-3 py-2'>{$deletedBy}</td>";
      echo "<td class='px-3 py-2'>{$reason}</td>";
      echo "<td class='px-3 py-2'>{$deletedAt}</td>";
      echo "<td class='px-3 py-2'>" . ($restoredAt ?: '<span class="text-gray-400">—</span>') . "</td>";
      echo "<td class='px-3 py-2'>";
      if (!empty($row['file_path']) && file_exists(__DIR__ . '/../' . $row['file_path'])) {
        $fileUrl = '../' . $row['file_path'];
        echo "<img src='{$fileUrl}' class='thumb w-20 h-20 object-contain border rounded cursor-zoom-in' alt='signature' data-full='{$fileUrl}'>";
      } else {
        echo '<span class="text-gray-400">File missing</span>';
      }
      echo "</td>";
      echo "<td class='px-3 py-2'>";
      if (!$row['restored_at'] && !empty($row['file_path']) && file_exists(__DIR__ . '/../' . $row['file_path'])) {
        echo "<button class='restore-btn bg-green-500 hover:bg-green-600 text-white text-xs py-1 px-2 rounded' data-id='{$id}'>Restore</button>";
      } else {
        echo "<button class='bg-gray-300 text-gray-700 text-xs py-1 px-2 rounded' disabled>Restored</button>";
      }
      echo "</td>";
      echo "</tr>";
    }
    $table = ob_get_clean();

    // Pagination
    ob_start();
    echo '<nav class="flex justify-center mt-3"><ul class="inline-flex -space-x-px">';
    for ($i = 1; $i <= $totalPages; $i++) {
      $active = ($i == $page) ? 'bg-blue-600 text-white' : 'bg-white text-gray-700';
      echo "<li data-page='$i'><button class='px-3 py-1 border border-gray-200 {$active} rounded-md mx-1'>$i</button></li>";
    }
    echo '</ul></nav>';
    $pagination = ob_get_clean();

    // Stats
    $totalDeletions = (int)($conn->query("SELECT COUNT(*) AS total FROM deleted_signatures")->fetch_assoc()['total'] ?? 0);
    $totalRestores = (int)($conn->query("SELECT COUNT(*) AS total FROM deleted_signatures WHERE restored_at IS NOT NULL")->fetch_assoc()['total'] ?? 0);
    $topCommunities = $conn->query("SELECT c.name, COUNT(*) AS total FROM deleted_signatures ds JOIN communities c ON ds.community_id=c.id GROUP BY ds.community_id ORDER BY total DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
      'status' => 'success',
      'data' => [
        'table' => $table,
        'pagination' => $pagination,
        'stats' => [
          'totalDeletions' => $totalDeletions,
          'totalRestores' => $totalRestores,
          'topCommunities' => $topCommunities,
          'rows' => $logs
        ]
      ]
    ]);
    exit;
  }

  // Restore signature
  if (isset($data['restore_id'])) {
    $id = intval($data['restore_id']);
    $stmt = $conn->prepare("SELECT * FROM deleted_signatures WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if (!$res) {
      echo json_encode(['status' => 'error', 'message' => 'Record not found']);
      exit;
    }

    $deletedFile = realpath(__DIR__ . '/../' . $res['file_path']);
    if (!$deletedFile || !file_exists($deletedFile)) {
      echo json_encode(['status' => 'error', 'message' => 'File missing']);
      exit;
    }

    $uploadDir = __DIR__ . '/../uploads/signatures';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = basename($deletedFile);
    $dest = $uploadDir . '/' . $filename;

    if (!copy($deletedFile, $dest)) {
      echo json_encode(['status' => 'error', 'message' => 'Failed to restore file']);
      exit;
    }

    $destRel = 'uploads/signatures/' . $filename;

    $stmt2 = $conn->prepare(
      "INSERT INTO signatures (role, file_path, community_id, uploaded_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), updated_at=NOW()"
    );
    $stmt2->bind_param('ssi', $res['role'], $destRel, $res['community_id']);
    $stmt2->execute();

    $stmt3 = $conn->prepare("UPDATE deleted_signatures SET restored_at=NOW() WHERE id=?");
    $stmt3->bind_param('i', $id);
    $stmt3->execute();

    echo json_encode(['status' => 'success', 'file' => $destRel]);
    exit;
  }
}

// ----------------------- ZIP / CSV -----------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
  $action = $_GET['action'];
  $ids = $_GET['ids'] ?? [];
  if ($ids === ['all'] || isset($_GET['all_pages'])) {
    $res = $conn->query("SELECT * FROM deleted_signatures");
    $rows = $res->fetch_all(MYSQLI_ASSOC);
  } else {
    $ids = array_map('intval', (array)$ids);
    if (!$ids) die('No records selected');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT * FROM deleted_signatures WHERE id IN ($placeholders)");
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  if ($action === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=deleted_signatures.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Community', 'Role', 'Deleted By', 'Reason', 'Deleted At', 'Restored At', 'File Path']);
    foreach ($rows as $r) {
      fputcsv($out, [
        $r['id'],
        $r['community_id'], // could join community name if needed
        $r['role'],
        $r['deleted_by'],
        $r['reason'],
        $r['deleted_at'],
        $r['restored_at'],
        $r['file_path']
      ]);
    }
    fclose($out);
    exit;
  }

  if ($action === 'zip') {
    $zip = new ZipArchive();
    $tmpFile = tempnam(sys_get_temp_dir(), 'sigzip');
    $zip->open($tmpFile, ZipArchive::CREATE);
    foreach ($rows as $r) {
      $file = realpath(__DIR__ . '/../' . $r['file_path']);
      if ($file && file_exists($file)) {
        $zip->addFile($file, basename($file));
      }
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="signatures.zip"');
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
  }
}

// ----------------------- PAGE LAYOUT -----------------------
$content = <<<HTML
<div class="max-w-full mx-auto p-4">
    <h2 class="text-2xl font-semibold text-gray-800 mb-4">Deleted Signatures Log</h2>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-6 gap-4 mb-4">
        <div class="stats-box p-4 bg-white shadow rounded-lg flex flex-col items-start">
            <h6 class="text-sm text-gray-600">Total Deletions</h6>
            <div id="totalDeletions" class="text-2xl font-bold text-blue-600">0</div>
        </div>
        <div class="stats-box p-4 bg-white shadow rounded-lg flex flex-col items-start">
            <h6 class="text-sm text-gray-600">Total Restorations</h6>
            <div id="totalRestores" class="text-2xl font-bold text-green-600">0</div>
        </div>
        <div class="sm:col-span-4 stats-box p-4 bg-white shadow rounded-lg">
            <h6 class="text-sm text-gray-600">Top Communities</h6>
            <ul id="topCommunities" class="mt-2 space-y-1 text-sm text-gray-700"></ul>
        </div>
    </div>

    <!-- Filters + Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
        <form id="filterForm" class="flex gap-2 flex-wrap items-center">
            <select name="community_id" class="border rounded px-3 py-2 bg-white text-sm">
                <option value="">All Communities</option>
HTML;

foreach ($communities as $c) {
  $content .= "<option value=\"{$c['id']}\">" . htmlspecialchars($c['name']) . "</option>";
}

$content .= <<<HTML
            </select>
            <select name="role" class="border rounded px-3 py-2 bg-white text-sm">
                <option value="">All Roles</option>
                <option value="president">President</option>
                <option value="secretary">Secretary</option>
            </select>
            <input type="date" name="from" class="border rounded px-3 py-2 text-sm">
            <input type="date" name="to" class="border rounded px-3 py-2 text-sm">
        </form>

        <div class="flex gap-2">
            <button id="bulk-delete" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm">Delete Selected</button>
            <button id="bulk-download" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm">Download Selected</button>
            <button id="bulk-zip" class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded text-sm">Download ZIP</button>
            <label class="flex items-center gap-2 ml-2"><input type="checkbox" id="selectAllPages"> Select All Across Pages</label>
            <a id="export-csv" href="deleted_signatures_log.php?export_csv=1" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm">Export CSV</a>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-3">
        <input id="searchInput" type="text" placeholder="Search..." class="w-full sm:w-1/3 border rounded px-3 py-2">
    </div>

    <!-- Desktop Table -->
    <div class="table-container hidden sm:block bg-white shadow rounded-lg overflow-x-auto">
        <table id="deletedTable" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-center"><input id="selectAll" type="checkbox"></th>
                    <th data-sort="id" class="px-3 py-2 text-left cursor-pointer">ID</th>
                    <th data-sort="community_name" class="px-3 py-2 text-left cursor-pointer">Community</th>
                    <th data-sort="role" class="px-3 py-2 text-left cursor-pointer">Role</th>
                    <th data-sort="deleted_by" class="px-3 py-2 text-left cursor-pointer">Deleted By</th>
                    <th data-sort="reason" class="px-3 py-2 text-left cursor-pointer">Reason</th>
                    <th data-sort="deleted_at" class="px-3 py-2 text-left cursor-pointer">Deleted At</th>
                    <th data-sort="restored_at" class="px-3 py-2 text-left cursor-pointer">Restored At</th>
                    <th class="px-3 py-2 text-left">Signature</th>
                    <th class="px-3 py-2 text-left">Action</th>
                </tr>
            </thead>
            <tbody id="tableBodyDesktop" class="bg-white divide-y divide-gray-100"></tbody>
        </table>
        <div id="paginationWrapDesktop" class="p-4"></div>
    </div>

    <!-- Mobile Cards -->
    <div id="tableBodyMobile" class="sm:hidden grid grid-cols-1 gap-4"></div>

    <!-- Mobile Card Template -->
    <template id="tableRowTemplateMobile">
        <div class="bg-white shadow rounded p-4 flex flex-col gap-2 border">
            <div class="flex justify-between items-center">
                <span class="record-id font-bold"></span>
                <input type="checkbox" class="recordCheckbox">
            </div>
            <div class="record-community text-sm text-gray-700"></div>
            <div class="record-role text-sm text-gray-700"></div>
            <div class="record-deleted-by text-sm text-gray-700"></div>
            <div class="record-reason text-sm text-gray-700"></div>
            <div class="record-deleted-at text-sm text-gray-700"></div>
            <div class="record-restored-at text-sm text-gray-700"></div>
            <img class="thumb w-full object-contain h-32 border rounded cursor-zoom-in" alt="signature">
            <button class="restore-btn bg-green-500 hover:bg-green-600 text-white px-2 py-1 text-xs rounded mt-2">Restore</button>
        </div>
    </template>

  <!-- Signature Modal -->
<div id="signatureModal" class="hidden fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50">
    <div id="modalImageWrap" class="bg-white p-4 rounded shadow-lg max-w-[90%] max-h-[90%] relative">
        <img id="modalImage" class="max-h-full max-w-full rounded" alt="signature">
        <button id="modalClose" class="absolute top-2 right-2 text-gray-700 text-2xl font-bold">&times;</button>
    </div>
</div>

</div>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link href="../css/deleted_signatures_log.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.12.0/dist/cdn.min.js" defer></script>
<script src="../js/deleted_signatures_log.js"></script>
HTML;

include('../include/layout.php');
