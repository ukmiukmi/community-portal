<?php
session_start();
include __DIR__ . "/../auth_check.php";
requireRole(['admin', 'registrar']);
include __DIR__ . "/db.php";

// Session info
$role = $_SESSION['role'] ?? 'user';
$username = $_SESSION['username'] ?? 'Guest';
$assigned_community_id = $_SESSION['assigned_community_id'] ?? null;

// ---------- Totals ----------
$totalsQuery = $role === 'admin'
    ? "SELECT 
        COUNT(*) as citizens,
        SUM(citizen_type='indigene') as indigenes,
        SUM(citizen_type='tenant') as tenants,
        (SELECT COUNT(*) FROM users) as users,
        (SELECT COUNT(*) FROM communities) as communities,
        (SELECT COUNT(*) FROM villages) as villages,
        (SELECT COUNT(*) FROM land_power_transactions) as issued_certificates
      FROM citizens"
    : "SELECT 
        COUNT(*) as citizens,
        SUM(citizen_type='indigene') as indigenes,
        SUM(citizen_type='tenant') as tenants,
        (SELECT COUNT(*) FROM land_power_transactions WHERE community_name=(SELECT name FROM communities WHERE id=$assigned_community_id LIMIT 1)) as issued_certificates
      FROM citizens
      WHERE community_id=$assigned_community_id";

$totals = $conn->query($totalsQuery)->fetch_assoc();
if ($role !== 'admin') {
    $totals['users'] = 0;
    $totals['communities'] = 0;
    $totals['villages'] = 0;
}

// ---------- Chart Data ----------
$chartQuery = $role === 'admin'
    ? "SELECT DATE_FORMAT(created_at, '%b') as m, COUNT(*) as c FROM citizens GROUP BY m ORDER BY created_at ASC LIMIT 6"
    : "SELECT DATE_FORMAT(created_at, '%b') as m, COUNT(*) as c FROM citizens WHERE community_id=$assigned_community_id GROUP BY m ORDER BY created_at ASC LIMIT 6";

$growth = [];
$res = $conn->query($chartQuery);
while ($row = $res->fetch_assoc()) $growth[] = ["month" => $row['m'], "value" => $row['c']];

$types = [
    ["name" => "Indigene", "value" => $totals['indigenes']],
    ["name" => "Tenant", "value" => $totals['tenants']]
];

$chartJSON = json_encode($growth);
$typeJSON  = json_encode($types);

// ---------- Assign Community Data ----------
$registrars = [];
$communitiesList = $conn->query("SELECT id, name FROM communities ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
if ($role === "admin") {
    $registrars = $conn->query("SELECT id, username, assigned_community_id FROM users WHERE role='registrar'")->fetch_all(MYSQLI_ASSOC);
}

ob_start();
?>

<div class="container-fluid">
    <link rel="stylesheet" href="../css/dash.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <!-- ===== Stat Cards ===== -->
    <div class="row g-4 mb-5">

        <?php
        $cards = [
            ['label' => 'Total Citizens', 'key' => 'citizens', 'icon' => 'bi-people-fill'],
            ['label' => 'Total Users', 'key' => 'users', 'icon' => 'bi-person-badge-fill'],
            ['label' => 'Total Communities', 'key' => 'communities', 'icon' => 'bi-building'],
            ['label' => 'Total Villages', 'key' => 'villages', 'icon' => 'bi-house-fill'],
            ['label' => 'Issued Certificates', 'key' => 'issued_certificates', 'icon' => 'bi-file-earmark-check-fill'],
            ['label' => 'Total Indigenes', 'key' => 'indigenes', 'icon' => 'bi-award-fill'],
            ['label' => 'Total Tenants', 'key' => 'tenants', 'icon' => 'bi-people']
        ];

        foreach ($cards as $c):
            $count = $totals[$c['key']] ?? 0;
            $id = "stat_{$c['key']}"; // unique id
        ?>
            <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                <div class="stat-card">
                    <i class="bi <?= $c['icon'] ?>"></i>
                    <div>
                        <h4 class="counter" id="<?= $id ?>" data-count="<?= $count ?>">0</h4>
                        <p><?= $c['label'] ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- ===== Charts ===== -->
    <div class="row g-4 mb-5 justify-content-center">
        <div class="col-md-5 col-12">
            <div class="chart-card" style="height:280px;">
                <h6><i class="bi bi-graph-up me-2"></i>Citizens Growth</h6>
                <canvas id="growthChart"></canvas>
            </div>
        </div>

        <div class="col-md-5 col-12">
            <div class="chart-card" style="height:280px;">
                <h6><i class="bi bi-pie-chart me-2"></i>Citizen Type</h6>
                <canvas id="typeChart"></canvas>
            </div>
        </div>
    </div>
    <!-- end of charts -->
    <!-- ===== Admin: Assign Communities ===== -->
    <?php include('assigned_users.php'); ?>
    <!--End of ===== Admin: Assign Communities ===== -->

    <!-- ===== Citizens Table / Mobile Cards ===== -->
    <div class="row mb-5">
        <div class="col-12">

            <div class="table-responsive assigned-table-wrapper">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Citizens</h5>
                    <a href="add_citizen.php" class="btn btn-success btn-sm">
                        <i class="bi bi-plus-lg"></i> Add Citizen
                    </a>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6 col-sm-12">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search citizens by name, ID, phone...">
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <select id="filterType" class="form-select">
                            <option value="">All Types</option>
                            <option value="indigene">Indigene</option>
                            <option value="tenant">Tenant</option>
                        </select>
                    </div>
                </div>

                <!-- Desktop: table visible on md+ screens only -->
                <div class="d-none d-md-block">
                    <table class="table align-middle">
                        <thead>
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
                            <tr>
                                <td colspan="12" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status"></div>
                                    <div class="mt-2">Loading citizens…</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile: cards visible on small screens only -->
                <div id="citizensCards" class="row g-3 d-md-none mt-3"></div>

                <div id="pagination" class="mt-3 d-flex flex-wrap gap-2 justify-content-center"></div>

            </div>
        </div>
    </div>
</div>

<?php
$communities = $conn->query("SELECT id, name FROM communities ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$states = $conn->query("SELECT name FROM states ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

include 'modals.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../js/search_citizens.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // NOTE: initial loadCitizens() is handled in search_citizens.js (JS-controlled).
        // --- Simple vanilla animate function using requestAnimationFrame ---
        function animateCounter(el, start, end, duration = 1000) {
            const startTime = performance.now();
            const isInt = Number.isInteger(end);

            function tick(now) {
                const elapsed = now - startTime;
                const progress = Math.min(elapsed / duration, 1);
                // easeOutCubic
                const eased = 1 - Math.pow(1 - progress, 3);
                const current = start + (end - start) * eased;
                el.textContent = isInt ? Math.round(current) : current.toFixed(2);
                if (progress < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        }

        // --- Initial counter animation (from 0 up to data-count) ---
        document.querySelectorAll('.counter').forEach(el => {
            const count = parseInt(el.dataset.count, 10) || 0;
            // start from 0
            animateCounter(el, 0, count, 1400);
        });

        // --- Initialize Charts (unchanged logic) ---
        function initCharts(growthData, typeData) {
            const ctxGrowth = document.getElementById('growthChart').getContext('2d');
            const ctxType = document.getElementById('typeChart').getContext('2d');

            if (window.growthChartInstance) window.growthChartInstance.destroy();
            if (window.typeChartInstance) window.typeChartInstance.destroy();

            window.growthChartInstance = new Chart(ctxGrowth, {
                type: 'line',
                data: {
                    labels: growthData.map(d => d.month),
                    datasets: [{
                        label: 'New Citizens',
                        data: growthData.map(d => d.value),
                        borderColor: '#2575fc',
                        backgroundColor: 'rgba(37,117,252,0.2)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            window.typeChartInstance = new Chart(ctxType, {
                type: 'pie',
                data: {
                    labels: typeData.map(d => d.name),
                    datasets: [{
                        data: typeData.map(d => d.value),
                        backgroundColor: ['#8e2de2', '#00b09b']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // --- Initial Chart Load ---
        const growthData = <?= $chartJSON ?>;
        const typeData = <?= $typeJSON ?>;
        initCharts(growthData, typeData);

        // --- Refresh Dashboard (vanilla fetch) ---
        window.refreshDashboard = function() {
            fetch("fetch_citizens.php?refresh_dashboard=1")
                .then(r => r.json())
                .then(dash => {
                    if (dash.status === 'success') {
                        // Update counters: map counts to DOM order
                        const counters = document.querySelectorAll('.stat-card .counter');
                        const mapping = [
                            dash.counts.citizens,
                            dash.counts.users,
                            dash.counts.communities,
                            dash.counts.villages,
                            dash.counts.issued_certificates,
                            dash.counts.indigenes,
                            dash.counts.tenants
                        ];

                        counters.forEach((el, idx) => {
                            const newVal = parseInt(mapping[idx] || 0, 10);
                            const currentVal = parseInt(el.textContent.replace(/[^\d]/g, ''), 10) || 0;
                            // animate from currentVal to newVal
                            animateCounter(el, currentVal, newVal, 900);
                            // update data-count so subsequent reloads know the target
                            el.dataset.count = newVal;
                        });

                        // Update charts
                        initCharts(dash.growth, dash.types);
                    }
                })
                .catch(err => {
                    console.error('Failed to refresh dashboard:', err);
                });
        };
    });
</script>

<?php
$content = ob_get_clean();
include('../include/layout.php');
?>