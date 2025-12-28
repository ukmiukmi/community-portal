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
  <div id="signatureModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div id="signatureModalOverlay" class="absolute inset-0 bg-black bg-opacity-50"></div>
    <div class="relative z-10 bg-white rounded-lg shadow-lg p-4 max-w-sm w-full mx-4">
      <button id="modalClose" class="absolute -top-3 -right-3 bg-gray-800 text-white w-8 h-8 rounded-full flex items-center justify-center text-lg font-bold">×</button>
      <img id="modalImage" src="" alt="Signature Preview" class="w-full h-auto object-contain max-h-96 rounded">
    </div>
  </div>
  </div>

  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="../css/deleted_signatures_log.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.12.0/dist/cdn.min.js" defer></script>
  <script src="../js/deleted_signatures_log.js"></script>
  HTML;