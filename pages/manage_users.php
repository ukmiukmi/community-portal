<?php
session_start();
include('../auth_check.php');
include('db.php');

// -------------------- AJAX HANDLERS ----------------
$action = $_REQUEST['action'] ?? null;
if ($action) {
  header('Content-Type: application/json');

  if ($action === 'fetch') {
    $users = [];
    $res = $conn->query("SELECT u.id, u.full_name, u.username, u.role, u.assigned_community_id, u.profile_image, u.created_at, c.name as community_name
                             FROM users u
                             LEFT JOIN communities c ON u.assigned_community_id = c.id
                             ORDER BY u.full_name ASC");
    if ($res) {
      while ($row = $res->fetch_assoc()) $users[] = $row;
      echo json_encode(['status' => 'success', 'message' => '', 'users' => $users]);
    } else {
      echo json_encode(['status' => 'error', 'message' => 'Failed to fetch users']);
    }
    exit;
  }

  if ($action === 'delete') {
    $id = $_POST['id'] ?? null;
    if (!$id) {
      echo json_encode(['status' => 'error', 'message' => 'User ID required']);
      exit;
    }
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['status' => 'success', 'message' => 'User deleted']);
    exit;
  }

  if ($action === 'save') {
    $id = $_POST['id'] ?? null;
    $full_name = $_POST['full_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $role = $_POST['role'] ?? '';
    $assigned_community_id = $_POST['assigned_community_id'] ?: null;
    $password = $_POST['password'] ?? '';

    if ($id) { // edit
      $updates = "full_name=?,username=?,role=?,assigned_community_id=?";
      $params = [$full_name, $username, $role, $assigned_community_id];

      if (!empty($password)) {
        $pw = password_hash($password, PASSWORD_DEFAULT);
        $updates .= ",password_hash=?";
        $params[] = $pw;
      }

      if (isset($_FILES['profile_image']) && $_FILES['profile_image']['tmp_name'] != '') {
        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $filename = time() . "_" . $id . "." . $ext;
        move_uploaded_file($_FILES['profile_image']['tmp_name'], "../uploads/users/" . $filename);
        $updates .= ",profile_image=?";
        $params[] = $filename;
      }

      $params[] = $id;
      $stmt = $conn->prepare("UPDATE users SET $updates WHERE id=?");
      $stmt->bind_param(str_repeat('s', count($params)), ...$params);
      $stmt->execute();
      echo json_encode(['status' => 'success', 'message' => 'User updated']);
      exit;
    } else { // add
      $pw = password_hash($password, PASSWORD_DEFAULT);
      $profile_image = 'default.png';
      if (isset($_FILES['profile_image']) && $_FILES['profile_image']['tmp_name'] != '') {
        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $profile_image = time() . "_" . rand(1000, 9999) . "." . $ext;
        move_uploaded_file($_FILES['profile_image']['tmp_name'], "../uploads/users/" . $profile_image);
      }

      $stmt = $conn->prepare("INSERT INTO users (full_name, username, password_hash, role, assigned_community_id, profile_image, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
      $stmt->bind_param(
        "ssssss",
        $full_name,
        $username,
        $pw,
        $role,
        $assigned_community_id,
        $profile_image
      );

      if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'User added']);
      } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add user']);
      }
      exit;
    }
  }

  echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
  exit;
}

// -------------------- Normal Page Load ----------------
$comResult = $conn->query("SELECT id,name FROM communities ORDER BY name ASC");
$communities = [];
while ($c = $comResult->fetch_assoc()) $communities[] = $c;

// -------------------- Layout.php Integration ----------------
ob_start();
?>
<link rel="stylesheet" href="../css/users.css">

<main class="container">

  <div class="page-header">
    <div>
      <h1>Manage Users</h1>
      <p>Create, edit, and manage system users in your platform</p>
    </div>
    <button id="openAddModalBtn" class="btn-primary">
      <i class="fa fa-user-plus"></i> Add User
    </button>
  </div>

  <div class="filters-container">
    <div class="search-wrapper">
      <i class="fa fa-search"></i>
      <input id="searchInput" placeholder="Search by name or username">
    </div>
    <select id="roleFilter">
      <option value="">All roles</option>
      <option value="admin">Admin</option>
      <option value="registrar">Registrar</option>
      <option value="user">User</option>
    </select>
    <select id="communityFilter">
      <option value="">All communities</option>
      <?php foreach ($communities as $c): ?>
        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="table-responsive">
    <table class="users-table">
      <thead>
        <tr>
          <th>Image</th>
          <th>Full Name</th>
          <th>Username</th>
          <th>Role</th>
          <th>Community</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="usersTable">
        <tr>
          <td colspan="7">Loading...</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div id="mobileCards"></div>
  <ul id="pagination"></ul>
  <div id="mobilePagination" class="mobile-pagination"></div>

  <!-- Add/Edit User Modal -->
  <div id="userModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modalTitle">Add User</h3>
        <span class="close" id="modalCloseBtn">&times;</span>
      </div>
      <form id="userForm" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="id" id="userIdField">
        <div class="field"><input type="text" id="full_name" name="full_name" placeholder=" " required><label>Full Name</label></div>
        <div class="field"><input type="email" id="username" name="username" placeholder=" " required><label>Username (Email)</label></div>
        <div class="field"><input type="password" id="password" name="password" placeholder=" "><label>Password (leave blank to keep)</label></div>
        <div class="field">
          <select id="role" name="role" required>
            <option value="">Select role</option>
            <option value="admin">Admin</option>
            <option value="registrar">Registrar</option>
            <option value="user">User</option>
          </select>
          <label>Role</label>
        </div>
        <div class="field">
          <select id="assigned_community_id" name="assigned_community_id">
            <option value="">None</option>
            <?php foreach ($communities as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label>Community</label>
        </div>
        <div class="field">
          <label>Profile Image</label>
          <input type="file" id="profile_image" name="profile_image" accept="image/*">
          <img id="profilePreview" src="../uploads/users/default.png">
        </div>
        <div class="form-actions">
          <button type="button" id="cancelBtn" class="btn-ghost">Cancel</button>
          <button type="submit" class="btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="../js/users.js"></script>
<?php
$content = ob_get_clean();
include('../include/layout.php');
?>