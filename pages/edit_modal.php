<?php
// Fetch states for the modal
$states = $conn->query("SELECT name FROM states ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<link rel="stylesheet" href="../css/edit_modal.css">
<div class="modal fade" id="editCitizenModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content p-3" style="border-radius:20px; backdrop-filter:blur(12px); background:rgba(255,255,255,0.9);">
      <form id="editCitizenForm" enctype="multipart/form-data" method="POST">

        <!-- Modal Header -->
        <div class="modal-header border-0 py-3">
          <div class="d-flex flex-column flex-grow-1">
            <h2 class="modal-title mb-1" id="editCitizenModalTitle">Edit Citizen</h2>
            <span id="editCitizenNameBadge" class="badge bg-gradient-primary text-white fs-6 shadow-sm"></span>
          </div>
          <button type="button" class="btn-close-custom" data-bs-dismiss="modal">×</button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
          <input type="hidden" name="update_id" id="edit_citizen_id">

          <!-- Personal Info -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="edit_first_name" class="form-label">First Name</label>
              <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
            </div>
            <div class="col-md-6">
              <label for="edit_last_name" class="form-label">Last Name</label>
              <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
            </div>
          </div>

          <!-- Contact Info -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="edit_phone" class="form-label">Phone</label>
              <input type="text" class="form-control" id="edit_phone" name="phone">
            </div>
            <div class="col-md-6">
              <label for="edit_citizen_type" class="form-label">Citizen Type</label>
              <select class="form-select" id="edit_citizen_type" name="citizen_type" required>
                <option value="">Select Type</option>
                <option value="indigene">Indigene</option>
                <option value="tenant">Tenant</option>
              </select>
            </div>
          </div>

          <!-- Location Info -->
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label for="edit_state_of_origin" class="form-label">State of Origin</label>
              <select class="form-select" id="edit_state_of_origin" name="state_of_origin" required>
                <option value="">Select State</option>
                <?php foreach ($states as $s): ?>
                  <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label for="edit_community_id" class="form-label">Community</label>
              <select class="form-select" id="edit_community_id" name="community_id" required>
                <option value="">Select Community</option>
                <?php foreach ($communities as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label for="edit_village_id" class="form-label">Village</label>
              <select class="form-select" id="edit_village_id" name="village_id" required>
                <option value="">Select Village</option>
              </select>
            </div>
          </div>

          <!-- Address & Image -->
          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label for="edit_house_address" class="form-label">House Address</label>
              <input type="text" class="form-control" id="edit_house_address" name="house_address">
            </div>
            <div class="col-md-4 d-flex flex-column align-items-center">
              <label class="form-label">Update Image</label>
              <input type="file" id="citizen_image" name="citizen_image" class="form-control mb-2" accept="image/*" onchange="previewEditImage(event)">
              <img id="edit_preview_img" style="display:none;width:120px;height:120px;object-fit:cover;border-radius:12px;border:1px solid #ccc;">
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Update Citizen</button>
        </div>

      </form>
    </div>
  </div>
</div>

<script>
  // Clear file input whenever modal opens
  const editModalEl = document.getElementById("editCitizenModal");
  editModalEl.addEventListener('show.bs.modal', () => {
    document.getElementById('citizen_image').value = '';
  });

  // Preview selected image
  function previewEditImage(ev) {
    const file = ev?.target?.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
      const img = document.getElementById("edit_preview_img");
      img.src = reader.result;
      img.style.display = "block";
    };
    reader.readAsDataURL(file);
  }
</script>