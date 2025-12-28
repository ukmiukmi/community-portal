
document.addEventListener("citizensTableUpdated", () => {
  const editButtons = document.querySelectorAll('.edit-btn');
  const deleteButtons = document.querySelectorAll('.delete-btn');

  // --- EDIT BUTTON ---
  editButtons.forEach(button => {
    button.addEventListener('click', () => {
      const id = button.getAttribute('data-id');

      // Fetch existing citizen info
      fetch(`get_citizen.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
          document.getElementById('editCitizenId').value = data.id;
          document.getElementById('editFirstName').value = data.first_name;
          document.getElementById('editLastName').value = data.last_name;
          document.getElementById('editPhone').value = data.phone;
          document.getElementById('editState').value = data.state_of_origin;
        })
        .catch(() => Swal.fire("Error", "Failed to load citizen details", "error"));
    });
  });

  // --- DELETE BUTTON ---
  deleteButtons.forEach(button => {
    button.addEventListener('click', () => {
      const id = button.getAttribute('data-id');
      document.getElementById('deleteCitizenId').value = id;
    });
  });
});

// --- SUBMIT EDIT FORM ---
document.getElementById('editCitizenForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);

  fetch('update_citizen.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.text())
  .then(response => {
    Swal.fire("Success", "Citizen updated successfully!", "success");
    const modal = bootstrap.Modal.getInstance(document.getElementById('editCitizenModal'));
    modal.hide();
    document.getElementById('searchInput').dispatchEvent(new Event('input'));
  })
  .catch(() => Swal.fire("Error", "Update failed", "error"));
});

// --- CONFIRM DELETE ---
document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
  const id = document.getElementById('deleteCitizenId').value;
  
  fetch(`delete_citizen.php?id=${id}`)
    .then(res => res.text())
    .then(() => {
      Swal.fire("Deleted!", "Citizen record has been removed.", "success");
      const modal = bootstrap.Modal.getInstance(document.getElementById('deleteCitizenModal'));
      modal.hide();
      document.getElementById('searchInput').dispatchEvent(new Event('input'));
    })
    .catch(() => Swal.fire("Error", "Could not delete record", "error"));
});

