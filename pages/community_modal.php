<?php
$imgBase = $communityImgDirRel ?? 'uploads/communities';
?>

<!-- Add Community Modal -->
<div class="modal fade" id="addCommunityModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header justify-content-center bg-success text-white">
        <h5 class="modal-title text-center">Add Community</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <form id="addCommunityForm" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="add_name">Name</label>
              <input type="text" name="name" id="add_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label for="add_slug">Slug</label>
              <input type="text" name="slug" id="add_slug" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label for="add_motto">Motto</label>
              <input type="text" name="motto" id="add_motto" class="form-control">
            </div>
            <div class="col-md-6">
              <label for="add_description">Description</label>
              <textarea name="description" id="add_description" class="form-control" rows="3"></textarea>
            </div>

            <!-- Images -->
            <div class="col-md-4">
              <label for="add_logo">Logo</label>
              <input type="file" name="logo" id="add_logo" class="form-control">
              <img id="addLogoPreview" src="" width="80" height="80" class="mt-2 rounded" alt="logo preview">
            </div>
            <div class="col-md-4">
              <label for="add_coat">Coat of Arms</label>
              <input type="file" name="coat_of_arms" id="add_coat" class="form-control">
              <img id="addCoatPreview" src="" width="80" height="80" class="mt-2 rounded" alt="coat preview">
            </div>
            <div class="col-md-4">
              <label for="add_stamp">Stamp</label>
              <input type="file" name="stamp" id="add_stamp" class="form-control">
              <img id="addStampPreview" src="" width="80" height="80" class="mt-2 rounded" alt="stamp preview">
            </div>
          </div>
        </div>

        <div class="modal-footer justify-content-center">
          <button type="submit" class="btn btn-success"><i class="fa fa-plus me-1"></i> Add</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Community Modal -->
<div class="modal fade" id="editCommunityModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header justify-content-center bg-primary text-white">
        <h5 class="modal-title text-center">Edit Community</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <form id="editCommunityForm" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="edit_id" id="edit_id">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="edit_name">Name</label>
              <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label for="edit_slug">Slug</label>
              <input type="text" name="slug" id="edit_slug" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label for="edit_motto">Motto</label>
              <input type="text" name="motto" id="edit_motto" class="form-control">
            </div>
            <div class="col-md-6">
              <label for="edit_description">Description</label>
              <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
            </div>

            <!-- Images -->
            <div class="col-md-4">
              <label for="edit_logo">Logo</label>
              <input type="file" name="logo" id="edit_logo" class="form-control">
              <img id="editLogoPreview" src="" width="80" height="80" class="mt-2 rounded" alt="logo preview">
            </div>
            <div class="col-md-4">
              <label for="edit_coat">Coat of Arms</label>
              <input type="file" name="coat_of_arms" id="edit_coat" class="form-control">
              <img id="editCoatPreview" src="" width="80" height="80" class="mt-2 rounded" alt="coat preview">
            </div>
            <div class="col-md-4">
              <label for="edit_stamp_input">Stamp</label>
              <input type="file" name="stamp" id="edit_stamp_input" class="form-control">
              <img id="editStampPreview" src="" width="80" height="80" class="mt-2 rounded" alt="stamp preview">
            </div>
          </div>
        </div>

        <div class="modal-footer justify-content-center">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>