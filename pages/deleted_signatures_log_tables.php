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
  HTML;

  // Filters and actions
  $content .= '<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">';
    $content .= '<form id="filterForm" class="flex gap-2 flex-wrap items-center">';
      $content .= '<select name="community_id" class="border rounded px-3 py-2 bg-white text-sm">';
        $content .= '<option value="">All Communities</option>';
        foreach ($communities as $c) {
        $content .= "<option value=\"{$c['id']}\">" . htmlspecialchars($c['name']) . "</option>";
        }
        $content .= '</select>';
      $content .= '<select name="role" class="border rounded px-3 py-2 bg-white text-sm">';
        $content .= '<option value="">All Roles</option>
        <option value="president">President</option>
        <option value="secretary">Secretary</option>
      </select>';
      $content .= '<input type="date" name="from" class="border rounded px-3 py-2 text-sm">';
      $content .= '<input type="date" name="to" class="border rounded px-3 py-2 text-sm">';
      $content .= '</form>';

    $content .= <<<HTML
      <div class="flex gap-2">
      <button id="bulk-delete" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm">Delete Selected</button>
      <button id="bulk-download" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm">Download Selected</button>
      <button id="bulk-zip" class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded text-sm">Download ZIP</button>
      <label class="flex items-center gap-2 ml-2"><input type="checkbox" id="selectAllPages"> Select All Across Pages</label>
      <button id="export-csv" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm">Export CSV</button>
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
  <!-- include('deleted_signatures_log_tables.php'); -->