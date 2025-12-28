<?php
require_once "db.php";

if (!isset($_SESSION['username'])) {
  exit("Unauthorized access");
}

$username = $conn->real_escape_string($_SESSION['username']);
$role = $_SESSION['role'] ?? 'registrar';

// Logged-in user data
$userQuery = $conn->query("
    SELECT u.id, u.username, u.full_name, u.assigned_community_id, c.name AS community_name
    FROM users u
    LEFT JOIN communities c ON u.assigned_community_id = c.id
    WHERE u.username = '{$username}'
");
$userData = $userQuery->fetch_assoc();
$loggedCommunityId = $userData['assigned_community_id'] ?? null;
$communityDefault  = $userData['community_name'] ?? '';

// Communities list
$communities = [];
$cRes = $conn->query("SELECT id, name FROM communities ORDER BY name ASC");
while ($c = $cRes->fetch_assoc()) {
  $communities[$c['id']] = $c['name'];
}

// Villages list grouped by community
$villagesMap = [];
$vRes = $conn->query("SELECT id, community_id, name FROM villages ORDER BY name ASC");
while ($v = $vRes->fetch_assoc()) {
  $villagesMap[$v['community_id']][] = $v;
}

// Citizens list
if ($role === 'admin') {
  $citizensRes = $conn->query("SELECT * FROM citizens ORDER BY first_name ASC");
} else {
  $citizensRes = $conn->query("SELECT * FROM citizens WHERE community_id = {$loggedCommunityId} ORDER BY first_name ASC");
}
?>

<link rel="stylesheet" href="../css/cert_modal.css">

<!-- ============================
        POA MODAL
=============================== -->
<div class="modal fade" id="poaModal" tabindex="-1" aria-labelledby="poaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content p-3">

      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="poaModalLabel">Issue Land Power of Attorney</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">×</button>
      </div>

      <div class="modal-body">
        <form action="generate_poa.php" method="POST" id="poaFormModal" data-redirect="poa_records.php">

          <!-- ===== Citizen Info ===== -->
          <div class="section-title">Citizen Information</div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label>Select Citizen</label>
              <select name="citizen_id" id="citizen_select_modal" class="form-control" required>
                <option value="">-- Choose Citizen --</option>
                <?php
                $citizensRes->data_seek(0);
                while ($c = $citizensRes->fetch_assoc()): ?>
                  <option value="<?= $c['id'] ?>"
                    data-full="<?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>"
                    data-phone="<?= htmlspecialchars($c['phone']) ?>"
                    data-house="<?= htmlspecialchars($c['house_address']) ?>"
                    data-community="<?= $c['community_id'] ?>"
                    data-village="<?= $c['village_id'] ?>"
                    data-citizenid="<?= htmlspecialchars($c['citizen_id']) ?>">
                    <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name'] . ' (' . $c['citizen_id'] . ')') ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label>Full Name</label>
              <input type="text" id="full_name_modal" name="full_name" class="form-control"
                <?= $role === 'admin' ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-6">
              <label>Phone</label>
              <input type="text" id="phone_modal" name="phone" class="form-control"
                <?= ($role === 'admin' || $role === 'registrar') ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-6">
              <label>Citizen ID</label>
              <input type="text" id="citizen_id_modal" name="citizen_code" class="form-control"
                <?= $role === 'admin' ? '' : 'readonly' ?>>
            </div>
          </div>

          <!-- ===== Payment Info ===== -->
          <div class="section-title">Payment Information</div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label>Payment Amount (₦)</label>
              <input type="number" step="0.01" name="payment_amount" class="form-control"
                <?= ($role === 'admin' || $role === 'registrar') ? '' : 'readonly' ?> required>
            </div>
            <div class="col-md-6">
              <label>Payment Date</label>
              <input type="date" name="payment_date" class="form-control"
                <?= ($role === 'admin' || $role === 'registrar') ? '' : 'readonly' ?> required
                value="<?= date('Y-m-d') ?>">
            </div>
          </div>

          <!-- ===== Location Info ===== -->
          <div class="section-title">Land & Location</div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label>Community</label>
              <?php if ($role === 'admin'): ?>
                <select id="community_field_modal" name="community_id" class="form-control" required>
                  <option value="">-- Select Community --</option>
                  <?php foreach ($communities as $cid => $cname): ?>
                    <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <input type="text" id="community_field_modal" name="community_id"
                  value="<?= htmlspecialchars($communityDefault) ?>" class="form-control" readonly>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label>Village</label>
              <select name="village_id" id="village_select_modal" class="form-control"
                <?= ($role === 'admin' || $role === 'registrar') ? '' : 'readonly' ?> required>
                <option value="">-- Select Village --</option>
              </select>
            </div>
            <div class="col-md-6">
              <label>Land Location</label>
              <input type="text" name="land_location" id="land_location_modal" class="form-control"
                <?= $role === 'admin' ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-6">
              <label>Number of Plots</label>
              <input type="number" name="number_of_plots" min="1" class="form-control"
                <?= ($role === 'admin' || $role === 'registrar') ? '' : 'readonly' ?> required>
            </div>
          </div>

          <input type="hidden" name="ajax" value="1">
          <button type="submit" class="btn btn-gradient w-100 mt-2">Issue Certificate</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- JS external -->
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const villagesByCommunity = <?= json_encode($villagesMap); ?>;

    const citizenSelect = document.getElementById('citizen_select_modal');
    const fullNameEl = document.getElementById('full_name_modal');
    const phoneEl = document.getElementById('phone_modal');
    const citizenIdEl = document.getElementById('citizen_id_modal');
    const communityEl = document.getElementById('community_field_modal');
    const villageEl = document.getElementById('village_select_modal');
    const landLocationEl = document.getElementById('land_location_modal');
    const poaForm = document.getElementById('poaFormModal');
    const poaModalEl = document.getElementById('poaModal');

    function populateVillages(commId, selectedVillage = null) {
      villageEl.innerHTML = '<option value="">-- Select Village --</option>';
      if (!commId || !villagesByCommunity[commId]) return;
      villagesByCommunity[commId].forEach(v => {
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = v.name;
        if (selectedVillage && v.id == selectedVillage) opt.selected = true;
        villageEl.appendChild(opt);
      });
    }

    poaModalEl.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      if (!button) return;

      citizenSelect.value = button.dataset.id || '';
      fullNameEl.value = button.dataset.full || '';
      phoneEl.value = button.dataset.phone || '';
      citizenIdEl.value = button.dataset.citizenid || '';
      landLocationEl.value = button.dataset.house || '';

      if (communityEl.tagName === 'SELECT')
        communityEl.value = button.dataset.community || '';

      populateVillages(button.dataset.community, button.dataset.village);
    });

    citizenSelect.addEventListener('change', function() {
      const opt = this.selectedOptions[0];
      if (!opt) return;

      fullNameEl.value = opt.dataset.full;
      phoneEl.value = opt.dataset.phone;
      citizenIdEl.value = opt.dataset.citizenid;
      landLocationEl.value = opt.dataset.house;

      if (communityEl.tagName === 'SELECT')
        communityEl.value = opt.dataset.community;

      populateVillages(opt.dataset.community, opt.dataset.village);
    });

    if (communityEl.tagName === 'SELECT') {
      communityEl.addEventListener('change', function() {
        populateVillages(this.value);
      });
    }

    poaForm.addEventListener('submit', function(e) {
      e.preventDefault();

      Swal.fire({
        title: 'Processing...',
        html: 'Generating certificate and receipt. Please wait.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
      });

      fetch('generate_poa.php', {
          method: 'POST',
          body: new FormData(this)
        })
        .then(res => res.json())
        .then(data => {
          Swal.close();

          if (data.status === 'success') {

            if (data.certificate) window.open(data.certificate, '_blank');
            if (data.receipt) window.open(data.receipt, '_blank');

            Swal.fire({
              icon: 'success',
              title: 'POA Issued!',
              html: `
            <p>Certificate and receipt generated successfully.</p>
            <a href="${data.certificate}" target="_blank" class="btn btn-success">Open Certificate</a>
            <a href="${data.receipt}" target="_blank" class="btn btn-primary ms-2">Open Receipt</a>
          `
            }).then(() => {
              const redirect = poaForm.dataset.redirect;
              if (redirect) return window.location.href = redirect;

              bootstrap.Modal.getInstance(poaModalEl).hide();
              poaForm.reset();
            });

          } else {
            Swal.fire('Error', data.message || 'Failed to generate certificate', 'error');
          }
        })
        .catch(() => Swal.fire('Error', 'An error occurred', 'error'));
    });
  });
</script>