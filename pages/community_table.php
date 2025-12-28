<!-- Desktop Table -->
<div class="table-responsive d-none d-md-block">
  <table class="table table-striped table-hover align-middle">
    <thead class="table-dark">
      <tr>
        <th>#</th>
        <th>Logo</th>
        <th>Coat</th>
        <th>Name</th>
        <th>Slug</th>
        <th>Motto</th>
        <th>Description</th>
        <th>Created At</th>
        <th>Stamp</th>
        <th>Actions</th>
      </tr>
    </thead>

    <tbody id="communityTableBody">
      <?php if (!empty($communities)): ?>
        <?php foreach ($communities as $i => $c): ?>
          <tr id="communityRow<?= (int)$c['id'] ?>" data-id="<?= (int)($c['id'] ?? 0) ?>" data-created="<?= htmlspecialchars((string)($c['created_at'] ?? '')) ?>">
            <td class="searchable"><?= $i + 1 + ($start ?? 0) ?></td>
            <td class="searchable">
              <?php if (!empty($c['logo'])): ?>
                <img src="../<?= htmlspecialchars((string)$c['logo']) ?>" width="50" height="50" class="rounded">
              <?php else: ?>
                <span class="text-muted">No logo</span>
              <?php endif; ?>
            </td>
            <td class="searchable">
              <?php if (!empty($c['coat_of_arms'])): ?>
                <img src="../<?= htmlspecialchars((string)$c['coat_of_arms']) ?>" width="50" height="50" class="rounded">
              <?php else: ?>
                <span class="text-muted">No coat</span>
              <?php endif; ?>
            </td>
            <td class="searchable"><?= htmlspecialchars((string)($c['name'] ?? '')) ?></td>
            <td class="searchable"><?= htmlspecialchars((string)($c['slug'] ?? '')) ?></td>
            <td class="searchable"><?= htmlspecialchars((string)($c['motto'] ?? '')) ?></td>
            <td class="searchable text-start"><?= htmlspecialchars((string)($c['description'] ?? '')) ?></td>
            <td><?= !empty($c['created_at']) ? date('d M Y', strtotime($c['created_at'])) : '' ?></td>
            <td class="searchable">
              <?php if (!empty($c['stamp'])): ?>
                <img src="../<?= htmlspecialchars((string)$c['stamp']) ?>" width="50" height="50" class="rounded">
              <?php else: ?>
                <span class="text-muted">No stamp</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <button class="btn btn-primary btn-sm editBtn" data-id="<?= (int)$c['id'] ?>"><i class="fa fa-edit me-1"></i>Edit</button>
              <button class="btn btn-danger btn-sm deleteBtn" data-id="<?= (int)$c['id'] ?>"><i class="fa fa-trash me-1"></i>Delete</button>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="10" class="text-center text-muted">No communities found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Desktop Pagination -->
  <?php if (!empty($communities)): ?>
    <nav>
      <ul class="pagination justify-content-center" id="desktopPagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
            <a class="page-link pageBtn" href="#" data-page="<?= $p ?>" data-search="<?= htmlspecialchars((string)$search) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<!-- Mobile Cards -->
<div class="d-block d-md-none" id="communityCards">
  <?php if (!empty($communities)): ?>
    <?php foreach ($communities as $c): ?>
      <div id="communityCard<?= (int)$c['id'] ?>" class="community-card p-3 mb-3 border rounded" data-id="<?= (int)$c['id'] ?>" data-created="<?= htmlspecialchars((string)($c['created_at'] ?? '')) ?>">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h5 class="searchable"><i class="fa fa-globe me-1"></i><?= htmlspecialchars((string)($c['name'] ?? '')) ?></h5>
          <div class="d-flex gap-1">
            <button class="btn btn-primary btn-sm editBtn" data-id="<?= (int)$c['id'] ?>"><i class="fa fa-edit"></i></button>
            <button class="btn btn-danger btn-sm deleteBtn" data-id="<?= (int)$c['id'] ?>"><i class="fa fa-trash"></i></button>
          </div>
        </div>
        <div class="d-flex gap-2 mb-2 searchable">
          <?php if (!empty($c['logo'])): ?><img src="../<?= htmlspecialchars((string)$c['logo']) ?>" width="60" height="60"><?php endif; ?>
          <?php if (!empty($c['coat_of_arms'])): ?><img src="../<?= htmlspecialchars((string)$c['coat_of_arms']) ?>" width="60" height="60"><?php endif; ?>
          <?php if (!empty($c['stamp'])): ?><img src="../<?= htmlspecialchars((string)$c['stamp']) ?>" width="60" height="60"><?php endif; ?>
        </div>
        <div class="mb-2">
          <span class="badge bg-secondary searchable">Slug: <?= htmlspecialchars((string)($c['slug'] ?? '')) ?></span>
          <span class="badge bg-info searchable">Motto: <?= htmlspecialchars((string)($c['motto'] ?? '')) ?></span>
        </div>
        <p class="text-truncate searchable"><strong>Description:</strong> <?= htmlspecialchars((string)($c['description'] ?? '')) ?></p>
        <p><strong>Created:</strong> <?= !empty($c['created_at']) ? date('d M Y', strtotime($c['created_at'])) : '' ?></p>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="text-center p-4 text-muted">No communities found.</div>
  <?php endif; ?>
</div>

<!-- Mobile Pagination -->
<nav class="d-block d-md-none">
  <ul class="pagination justify-content-center" id="mobilePagination">
    <?php if (!empty($communities)): ?>
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
          <a class="page-link pageBtn" href="#" data-page="<?= $p ?>" data-search="<?= htmlspecialchars((string)$search) ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
    <?php endif; ?>
  </ul>
</nav>