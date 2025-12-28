<?php
// Defaults for AJAX reload
if (!isset($page)) $page = 1;
if (!isset($totalPages)) $totalPages = 1;
$start = $start ?? 0;
$searchTerm = trim($search ?? '');

// Highlight function for search term
$highlight = function ($text) use ($searchTerm) {
  if (!$searchTerm) return htmlspecialchars($text);
  $pattern = '/' . preg_quote($searchTerm, '/') . '/i';
  return preg_replace($pattern, '<span class="search-highlight fw-bold">$0</span>', htmlspecialchars($text));
};
?>

<!-- Desktop Table -->
<div class="table-responsive d-none d-md-block">
  <table class="table table-striped table-hover align-middle">
    <thead class="table-dark">
      <tr>
        <th class="sortable" data-column="id" data-order="asc"># <i class="fa fa-sort"></i></th>
        <th class="sortable" data-column="community_name" data-order="asc">Community <i class="fa fa-sort"></i></th>
        <th class="sortable" data-column="name" data-order="asc">Village Name <i class="fa fa-sort"></i></th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="villageTableBody">
      <?php if (!empty($villages)): ?>
        <?php foreach ($villages as $i => $v): ?>
          <tr data-id="<?= (int)$v['id'] ?>">
            <td class="searchable"><?= $highlight($i + 1 + $start) ?></td>
            <td class="searchable"><?= $highlight($v['community_name'] ?? '') ?></td>
            <td class="searchable"><?= $highlight($v['name'] ?? '') ?></td>
            <td class="text-center">
              <button class="btn btn-primary btn-sm editBtn" data-id="<?= (int)$v['id'] ?>"><i class="fa fa-edit me-1"></i>Edit</button>
              <button class="btn btn-danger btn-sm deleteBtn" data-id="<?= (int)$v['id'] ?>"><i class="fa fa-trash me-1"></i>Delete</button>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="4" class="text-center text-muted">No villages found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Desktop Pagination -->
  <?php if (!empty($villages) && $totalPages > 1): ?>
    <nav>
      <ul class="pagination justify-content-center" id="desktopPaginationVillage">
        <?php if ($page > 1): ?>
          <li class="page-item">
            <a class="page-link v-page-link" href="#" data-page="<?= $page - 1 ?>" data-search="<?= htmlspecialchars($searchTerm) ?>">Prev</a>
          </li>
        <?php endif; ?>

        <?php
        // Dynamic page range, show max 5 pages at a time
        $visiblePages = 5;
        $startPage = max(1, $page - floor($visiblePages / 2));
        $endPage = min($totalPages, $startPage + $visiblePages - 1);
        if ($endPage - $startPage + 1 < $visiblePages) {
          $startPage = max(1, $endPage - $visiblePages + 1);
        }
        for ($p = $startPage; $p <= $endPage; $p++): ?>
          <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
            <a class="page-link v-page-link" href="#" data-page="<?= $p ?>" data-search="<?= htmlspecialchars($searchTerm) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <li class="page-item">
            <a class="page-link v-page-link" href="#" data-page="<?= $page + 1 ?>" data-search="<?= htmlspecialchars($searchTerm) ?>">Next</a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<!-- Mobile Cards -->
<div class="d-block d-md-none" id="villageCards">
  <?php if (!empty($villages)): ?>
    <?php foreach ($villages as $v): ?>
      <div class="village-card p-3 mb-3 border rounded" data-id="<?= (int)$v['id'] ?>">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="searchable"><?= $highlight($v['name'] ?? '') ?></h5>
          <div class="d-flex gap-1">
            <button class="btn btn-primary btn-sm editBtn" data-id="<?= (int)$v['id'] ?>"><i class="fa fa-edit"></i></button>
            <button class="btn btn-danger btn-sm deleteBtn" data-id="<?= (int)$v['id'] ?>"><i class="fa fa-trash"></i></button>
          </div>
        </div>
        <div class="mb-2">
          <span class="badge bg-secondary searchable" style="white-space: normal;word-break: break-word;"><?= $highlight($v['community_name'] ?? '') ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="text-center p-4 text-muted">No villages found.</div>
  <?php endif; ?>
</div>

<!-- Mobile Pagination -->
<?php if (!empty($villages) && $totalPages > 1): ?>
  <nav class="d-block d-md-none">
    <ul class="pagination justify-content-center" id="mobilePaginationVillage">
      <?php if ($page > 1): ?>
        <li class="page-item">
          <a class="page-link v-page-link" href="#" data-page="<?= $page - 1 ?>" data-search="<?= htmlspecialchars($searchTerm) ?>">Prev</a>
        </li>
      <?php endif; ?>

      <?php
      // Dynamic page range, max 5 visible
      $visiblePages = 5;
      $startPage = max(1, $page - floor($visiblePages / 2));
      $endPage = min($totalPages, $startPage + $visiblePages - 1);
      if ($endPage - $startPage + 1 < $visiblePages) {
        $startPage = max(1, $endPage - $visiblePages + 1);
      }
      for ($p = $startPage; $p <= $endPage; $p++): ?>
        <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
          <a class="page-link v-page-link" href="#" data-page="<?= $p ?>" data-search="<?= htmlspecialchars($searchTerm) ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>

      <?php if ($page < $totalPages): ?>
        <li class="page-item">
          <a class="page-link v-page-link" href="#" data-page="<?= $page + 1 ?>" data-search="<?= htmlspecialchars($searchTerm) ?>">Next</a>
        </li>
      <?php endif; ?>
    </ul>
  </nav>
<?php endif; ?>