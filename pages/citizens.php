<?php
include('../includes/db_connect.php');

// Fetch all citizens with their community and village names
$query = "
  SELECT c.id, c.citizen_id, c.first_name, c.last_name, c.phone, c.house_address, 
         c.citizen_type, c.image_path, 
         com.name AS community_name, 
         v.name AS village_name
  FROM citizens c
  LEFT JOIN communities com ON c.community_id = com.id
  LEFT JOIN villages v ON c.village_id = v.id
  ORDER BY c.id DESC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>All Citizens</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .container {
      margin-top: 50px;
    }

    .table img {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
    }
  </style>
</head>

<body>

  <div class="container">
    <h3 class="mb-4 text-center fw-bold">All Citizens</h3>

    <table class="table table-hover align-middle table-bordered bg-white shadow-sm">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>Image</th>
          <th>Citizen ID</th>
          <th>Full Name</th>
          <th>Phone</th>
          <th>Address</th>
          <th>Community</th>
          <th>Village</th>
          <th>Type</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($result->num_rows > 0) {
          $sn = 1;
          while ($row = $result->fetch_assoc()) {
        ?>
            <tr>
              <td><?= $sn++; ?></td>
              <td><img src="../<?= $row['image_path']; ?>" alt="Citizen"></td>
              <td><?= $row['citizen_id']; ?></td>
              <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
              <td><?= htmlspecialchars($row['phone']); ?></td>
              <td><?= htmlspecialchars($row['house_address']); ?></td>
              <td><?= htmlspecialchars($row['community_name']); ?></td>
              <td><?= htmlspecialchars($row['village_name']); ?></td>
              <td><span class="badge bg-primary"><?= htmlspecialchars($row['citizen_type']); ?></span></td>
              <td>
                <a href="edit_citizen.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                <button class="btn btn-sm btn-danger delete-btn"
                  data-id="<?= $row['id']; ?>"
                  data-name="<?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>">
                  Delete
                </button>
              </td>
            </tr>
        <?php
          }
        } else {
          echo "<tr><td colspan='10' class='text-center text-muted'>No citizens found</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteCitizenModal" tabindex="-1" aria-labelledby="deleteCitizenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="deleteCitizenModalLabel">Confirm Delete</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p id="citizenInfo" class="fs-6 mb-0"></p>
          <p class="text-muted">This action cannot be undone. Are you sure you want to delete this citizen?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Yes, Delete</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Modal Script -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const deleteButtons = document.querySelectorAll('.delete-btn');
      const deleteModal = new bootstrap.Modal(document.getElementById('deleteCitizenModal'));
      const citizenInfo = document.getElementById('citizenInfo');
      const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

      deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
          const citizenId = this.getAttribute('data-id');
          const citizenName = this.getAttribute('data-name');

          citizenInfo.textContent = `You are about to delete ${citizenName}.`;
          confirmDeleteBtn.href = `delete_citizen.php?id=${citizenId}`;
          deleteModal.show();
        });
      });
    });
  </script>

</body>

</html>