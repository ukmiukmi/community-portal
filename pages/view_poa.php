<?php
session_start();
include('../auth_check.php');
requireRole(['admin', 'registrar']);
include('db.php');

// Get ID
$id = intval($_GET['id'] ?? 0);

// Fetch POA + Citizen details
$sql = "
SELECT t.*, c.first_name, c.last_name
FROM land_power_transactions t
JOIN citizens c ON t.citizen_id = c.id
WHERE t.id = $id
";
$poa = $conn->query($sql)->fetch_assoc();

// -------------------- Capture Page Content --------------------
ob_start();
?>

<?php if (!$poa): ?>
<div class="container py-5 text-center">
    <h3 class="text-muted">POA Record not found</h3>
    <a href="poa_records.php" class="btn btn-primary mt-3">← Back to Records</a>
</div>
<?php else: ?>

<?php
$certificate_url = !empty($poa['certificate_file']) ? "../".$poa['certificate_file'] : null;
$receipt_url     = !empty($poa['receipt_file']) ? "../".$poa['receipt_file'] : null;

$status_colors = [
    'issued' => '#10b981',
    'pending' => '#f59e0b',
    'revoked' => '#ef4444',
    'voided'  => '#ef4444'
];

$certificate_status = $poa['status_certificate'] ?? $poa['status'] ?? 'pending';
$receipt_status     = $poa['status_receipt'] ?? $poa['status'] ?? 'pending';

$certificate_color = $status_colors[$certificate_status] ?? '#64748b';
$receipt_color     = $status_colors[$receipt_status] ?? '#64748b';

$certificate_tooltip = match($certificate_status) {
    'issued'  => 'Issued on: ' . ($poa['payment_date'] ?? 'N/A'),
    'pending' => 'Pending approval or payment',
    'revoked' => 'Revoked due to invalid request',
    default   => 'Unknown status'
};

$receipt_tooltip = match($receipt_status) {
    'issued'  => 'Issued on: ' . ($poa['payment_date'] ?? 'N/A'),
    'pending' => 'Pending confirmation of payment',
    'revoked' => 'Revoked due to invalid payment',
    default   => 'Unknown status'
};
?>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="fw-bold text-primary">Power of Attorney for <?= ucwords($poa['first_name']." ".$poa['last_name']) ?></h2>
        <a href="poa_records.php" class="btn btn-outline-secondary">← Back to Records</a>
    </div>

    <!-- POA Certificate -->
    <div class="card shadow-sm border-0 rounded-4 mb-4 position-relative">
        <div class="card-body">
            <h4 class="fw-bold mb-3">POA Certificate</h4>
            <span class="status-badge" style="background-color:<?= $certificate_color ?>;" data-tooltip="<?= htmlspecialchars($certificate_tooltip) ?>">
                <?= strtoupper($certificate_status) ?>
            </span>
            <?php if($certificate_url): ?>
            <iframe src="<?= $certificate_url ?>" height="600px" class="w-100 rounded-3 border"></iframe>
            <?php else: ?>
            <p class="text-muted">No certificate uploaded yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Receipt -->
    <div class="card shadow-sm border-0 rounded-4 mb-4 position-relative">
        <div class="card-body">
            <h4 class="fw-bold mb-3">Receipt</h4>
            <span class="status-badge" style="background-color:<?= $receipt_color ?>;" data-tooltip="<?= htmlspecialchars($receipt_tooltip) ?>">
                <?= strtoupper($receipt_status) ?>
            </span>
            <?php if($receipt_url): ?>
            <iframe src="<?= $receipt_url ?>" height="500px" class="w-100 rounded-3 border"></iframe>
            <?php else: ?>
            <p class="text-muted">No receipt uploaded yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="mb-5 d-flex flex-wrap gap-2">
        <?php if($certificate_url): ?>
            <a href="<?= $certificate_url ?>" target="_blank" class="btn btn-primary">🖨 Print Certificate</a>
            <a href="<?= $certificate_url ?>" download class="btn btn-success">⬇️ Download Certificate</a>
        <?php endif; ?>
        <?php if($receipt_url): ?>
            <a href="<?= $receipt_url ?>" target="_blank" class="btn btn-primary">🧾 Print Receipt</a>
            <a href="<?= $receipt_url ?>" download class="btn btn-success">⬇️ Download Receipt</a>
        <?php endif; ?>
    </div>

</div>

<style>
.status-badge {
    display:inline-block;
    padding:6px 12px;
    border-radius:12px;
    color:#fff;
    font-weight:600;
    font-size:0.85rem;
    cursor:pointer;
    position:absolute;
    top:20px;
    right:20px;
}
.status-badge::after {
    content: attr(data-tooltip);
    position:absolute;
    top:120%;
    right:0;
    background: rgba(0,0,0,0.85);
    color:#fff;
    padding:6px 10px;
    border-radius:6px;
    white-space: nowrap;
    font-size:0.8rem;
    opacity:0;
    transition:0.3s;
    pointer-events:none;
    z-index:1000;
}
.status-badge:hover::after, .status-badge.active::after {
    opacity:1;
}

/* Mobile responsive */
@media (max-width:768px){
    iframe { height:400px; }
    .status-badge { top:10px; right:10px; font-size:0.75rem; padding:4px 8px; }
}
</style>

<?php endif; // closes if(!$poa) ?>
<?php
$content = ob_get_clean();
include('../include/layout.php');
?>

<script>
// Apply saved theme mode
document.body.classList.toggle("dark", localStorage.getItem("theme") === "dark");

// Tap-to-toggle tooltip for mobile and small screens
document.querySelectorAll('.status-badge').forEach(badge => {
    badge.addEventListener('click', e => {
        e.stopPropagation();
        badge.classList.toggle('active');
    });
});
document.addEventListener('click', () => {
    document.querySelectorAll('.status-badge.active').forEach(badge => badge.classList.remove('active'));
});
</script>
