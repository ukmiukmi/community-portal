<?php
session_start();
include('../auth_check.php');
requireRole(['admin', 'registrar']);
include('db.php');

$role = $_SESSION['role'];
$assigned_community_id = $_SESSION['assigned_community_id'] ?? null;

// -------------------- Pagination Setup --------------------
$records_per_page = 10;
$page = isset($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// -------------------- Count total for pagination --------------------
$whereSQL = ($role === 'registrar' && $assigned_community_id) ? "WHERE c.community_id = $assigned_community_id" : "WHERE 1";
$countQuery = "
SELECT COUNT(*) AS total 
FROM land_power_transactions t
JOIN citizens c ON t.citizen_id = c.id
LEFT JOIN communities com ON c.community_id = com.id
$whereSQL
";
$total_records = $conn->query($countQuery)->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// -------------------- Main query --------------------
$query = "
SELECT t.*, c.first_name, c.last_name, com.name AS community_name
FROM land_power_transactions t
JOIN citizens c ON t.citizen_id = c.id
LEFT JOIN communities com ON c.community_id = com.id
$whereSQL
ORDER BY t.id DESC
LIMIT $records_per_page OFFSET $offset
";
$records = $conn->query($query);

// Fetch all rows for desktop/mobile rendering
$rows = [];
if ($records) {
    while ($r = $records->fetch_assoc()) {
        $rows[] = $r;
    }
}

// Helper function for amount badge color
function amountBadgeClass($amount){
    if($amount < 50000) return 'badge-success';
    if($amount <= 200000) return 'badge-warning';
    return 'badge-danger';
}

// -------------------- Capture Page Content --------------------
ob_start();
?>

<style>
.text-primary { color: #2563eb !important; }
.table-responsive table thead { background: #f1f5ff; color: #2a3f7f; font-size: 0.8rem; letter-spacing: 1px; }
.mobile-card { display: none; opacity: 0; transform: translateY(20px); animation: fadeUp 0.4s ease forwards; }
@keyframes fadeUp { 0% { opacity: 0; transform: translateY(20px); } 100% { opacity: 1; transform: translateY(0); } }
mark { opacity: 0; animation: markFade 0.5s ease forwards; border-radius: 4px; padding: 2px 4px; font-weight: 600; }
@keyframes markFade { 0% { opacity: 0; background-color: rgba(255,255,0,0.3); } 100% { opacity: 1; background-color: yellow; } }
/* Badges */
.badge-community { background-color: #e0f2fe; color: #0369a1; font-weight: 600; border-radius: 0.75rem; padding: 0.25em 0.6em; font-size: 0.75rem; }
.badge-amount { font-weight: 600; border-radius: 0.75rem; padding: 0.25em 0.6em; font-size: 0.75rem; }
.badge-success { background-color: #d1fae5; color: #065f46; }
.badge-warning { background-color: #fef3c7; color: #92400e; }
.badge-danger { background-color: #fee2e2; color: #991b1b; }
/* Mobile */
@media (max-width: 768px){
    .desktop-table { display: none; }
    .mobile-card { display: block; }
}
</style>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="text-center text-md-start mb-4">
        <h2 class="fw-bold text-primary">Power of Attorney Records</h2>
        <input type="text" id="searchInput" class="form-control rounded-pill px-3 mt-2 mx-auto mx-md-0" style="max-width:320px;" placeholder="Search citizen, serial or amount">
    </div>

    <!-- Desktop Table -->
    <div class="card desktop-table shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive rounded-4 overflow-hidden">
                <table class="table table-hover mb-0">
                    <thead class="text-uppercase">
                        <tr>
                            <th>Serial</th>
                            <th>Citizen</th>
                            <th>Community</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($rows) > 0): ?>
                            <?php foreach($rows as $row): ?>
                                <tr>
                                    <td data-original="<?= htmlspecialchars($row['serial_no']) ?>"><?= htmlspecialchars($row['serial_no']) ?></td>
                                    <td data-original="<?= htmlspecialchars(ucwords($row['first_name']." ".$row['last_name'])) ?>"><?= htmlspecialchars(ucwords($row['first_name']." ".$row['last_name'])) ?></td>
                                    <td data-original="<?= htmlspecialchars($row['community_name']) ?>"><span class="badge-community"><?= htmlspecialchars($row['community_name']) ?></span></td>
                                    <td data-original="₦<?= number_format($row['payment_amount'],2) ?>"><span class="badge-amount <?= amountBadgeClass($row['payment_amount']) ?>">₦<?= number_format($row['payment_amount'],2) ?></span></td>
                                    <td data-original="<?= date("d M Y", strtotime($row['payment_date'])) ?>"><?= date("d M Y", strtotime($row['payment_date'])) ?></td>
                                    <td><a href="view_poa.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="no-records">
                                <td colspan="6" class="text-center text-muted py-4">No Issued Certificate yet.</td>
                            </tr>
                        <?php endif; ?>
                        <tr id="search-no-records" style="display:none;">
                            <td colspan="6" class="text-center text-warning py-4">No record matches your search.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mobile Card View -->
    <div id="mobileCardContainer" class="row g-3 mt-2">
        <?php if(count($rows) > 0): ?>
            <?php foreach($rows as $row): ?>
                <div class="col-12 mobile-card">
                    <div class="card shadow border-0 rounded-4 p-3">
                        <h6 class="fw-bold text-primary"><?= htmlspecialchars($row['serial_no']) ?></h6>
                        <p class="mb-1"><?= htmlspecialchars(ucwords($row['first_name']." ".$row['last_name'])) ?></p>
                        <p class="mb-1 small"><span class="badge-community"><?= htmlspecialchars($row['community_name']) ?></span></p>
                        <p class="mb-2"><span class="badge-amount <?= amountBadgeClass($row['payment_amount']) ?>">₦<?= number_format($row['payment_amount'],2) ?></span></p>
                        <p class="mb-2 small text-secondary"><?= date("d M Y", strtotime($row['payment_date'])) ?></p>
                        <a href="view_poa.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary rounded-pill px-3">View Record</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card shadow border-0 rounded-4 p-4 text-center text-muted">
                    <h5>No Issued Certificate yet</h5>
                    <p class="small mb-0">Certificates will appear here once issued.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <nav class="mt-4 d-flex justify-content-center">
        <ul class="pagination pagination-sm">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($i==$page?'active':'') ?>"><a class="page-link rounded-circle" href="?page=<?= $i ?>"><?= $i ?></a></li>
            <?php endfor; ?>
        </ul>
    </nav>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("searchInput");
    const table = document.querySelector(".desktop-table tbody");
    const cards = document.getElementById("mobileCardContainer");
    const noRecordsRow = document.getElementById("no-records");
    const searchNoRecordsRow = document.getElementById("search-no-records");

    input.addEventListener("input", () => {
        const searchTerm = input.value.trim().toLowerCase();
        let anyVisible = false;

        // Desktop table highlight
        table.querySelectorAll("tr:not(#no-records):not(#search-no-records)").forEach(row=>{
            let rowText = row.textContent.toLowerCase();
            if(rowText.includes(searchTerm)) {
                row.style.display = "";
                anyVisible = true;
                row.querySelectorAll("td").forEach(td=>{
                    const originalText = td.getAttribute("data-original") || td.textContent;
                    if(searchTerm==="") td.innerHTML = originalText;
                    else td.innerHTML = originalText.replace(new RegExp(`(${searchTerm})`, "gi"), `<mark>$1</mark>`);
                });
            } else row.style.display = "none";
        });

        // Mobile cards highlight
        cards.querySelectorAll(".col-12").forEach((card)=>{
            let cardText = card.textContent.toLowerCase();
            if(cardText.includes(searchTerm)) {
                card.style.display = "";
                anyVisible = true;
                card.querySelectorAll("h6, p, span").forEach(el=>{
                    const original = el.getAttribute("data-original") || el.textContent;
                    el.setAttribute("data-original", original);
                    if(searchTerm==="") el.innerHTML = original;
                    else el.innerHTML = original.replace(new RegExp(`(${searchTerm})`, "gi"), `<mark>$1</mark>`);
                });
            } else card.style.display = "none";
        });

        if(searchNoRecordsRow) searchNoRecordsRow.style.display = anyVisible ? "none" : (searchTerm ? "" : "none");
        if(noRecordsRow) noRecordsRow.style.display = anyVisible || searchTerm ? "none" : "";
    });
});
</script>

<?php
$content = ob_get_clean();
include('../include/layout.php');
?>
