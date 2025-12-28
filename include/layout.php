<?php
if (!isset($_SESSION)) session_start();
require_once './db.php';

// -------------------- Default Content --------------------
if (!isset($content)) {
    $content = '<div class="container p-4"><h3>Page content is not defined.</h3></div>';
}

// -------------------- User Info --------------------
$user_full_name = 'Guest';
$user_image = 'users/default.png';
$user_role = 'guest';
$username = 'Guest';

if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $stmt = $conn->prepare("SELECT full_name, profile_image, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $stmt->bind_result($full_name, $profile_image, $role);
    if ($stmt->fetch()) {
        $user_full_name = $full_name;
        $user_image = $profile_image ?: 'users/default.png';
        $user_role = $role;
    }
    $stmt->close();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ezza North L.G.A Admin Panel</title>

    <!-- Icons + Bootstrap -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.12/dist/sweetalert2.all.min.js"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JS bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom Layout Stylesheet -->
    <link rel="stylesheet" href="../css/layout.css">
</head>

<body data-user-role="<?= htmlspecialchars($user_role) ?>" data-current-page="<?= htmlspecialchars($current_page) ?>">

    <!-- Navbar -->
    <nav class="navbar fixed-top navbar-dark bg-dark">
        <div class="container-fluid d-flex align-items-center justify-content-between">

            <!-- Left: Sidebar toggle + Brand -->
            <div class="d-flex align-items-center">
                <button class="btn btn-dark d-md-none me-2" id="sidebarToggle">
                    <i class="fa fa-bars"></i>
                </button>
                <a href="dashboard.php" class="btn btn-primary d-md-none me-2">
                    <i class="fa fa-tachometer-alt"></i> Dashboard
                </a>
                <button class="btn btn-dark d-none d-md-inline me-2" id="desktopCollapseToggle">
                    <i class="fa fa-angle-double-left"></i>
                </button>
                <span class="navbar-brand mb-0 h1">Ezza North L.G.A Admin Panel</span>
            </div>

            <!-- Right: Theme toggler + User dropdown -->
            <div class="d-flex align-items-center">

                <!-- Theme Toggler -->
                <button id="themeToggler" title="Toggle Theme">
                    <i class="fa fa-moon"></i>
                </button>

                <div class="dropdown d-flex align-items-center ms-2">
                    <img src="../uploads/users/<?= htmlspecialchars($user_image) ?>"
                        class="rounded-circle" width="36" height="36"
                        data-bs-toggle="dropdown"
                        onerror="this.src='../uploads/users/default.png'">
                    <div class="text-white small ms-2"><?= htmlspecialchars($username) ?></div>
                    <ul class="dropdown-menu dropdown-menu-end text-start">
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile overlay -->
    <div id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar mobile-hidden" id="appSidebar">

        <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
            <i class="fa fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>

        <div class="menu-header">Admin / Registrar</div>

        <a class="nav-link <?= $current_page == 'add_citizen.php' ? 'active' : '' ?>" href="add_citizen.php">
            <i class="fa fa-user-plus"></i> <span>Add Citizen</span>
        </a>

        <a class="nav-link <?= $current_page == 'my_citizens.php' ? 'active' : '' ?>" href="my_citizens.php">
            <i class="fa fa-users"></i> <span>My Citizens</span>
        </a>

        <a class="nav-link admin-link <?= $current_page == 'issue_land_poa.php' ? 'active' : '' ?>" href="issue_land_poa.php"
            data-required-role="admin,registrar">
            <i class="fa fa-file-signature"></i> <span>Issue Cert</span>
        </a>

        <a class="nav-link admin-link <?= $current_page == 'poa_records.php' ? 'active' : '' ?>" href="poa_records.php"
            data-required-role="admin,registrar">
            <i class="fa fa-clipboard-list"></i> <span>POA Records</span>
        </a>

        <?php if ($user_role === 'admin'): ?>
            <div class="menu-header">Admin Tools</div>
            <div class="accordion" id="adminToolsAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button"
                            data-bs-toggle="collapse" data-bs-target="#adminToolsMenu">
                            <i class="fa fa-tools"></i> <span>Tools</span>
                        </button>
                    </h2>
                    <div id="adminToolsMenu" class="accordion-collapse collapse">
                        <div class="accordion-body">
                            <a href="manage_signatures.php" class="submenu-link admin-link" data-required-role="admin">
                                <i class="fa fa-pen-nib"></i> Manage Signatures
                            </a>
                            <a href="deleted_signatures_log.php" class="submenu-link admin-link" data-required-role="admin">
                                <i class="fa fa-trash-alt"></i> Deleted Signatures Log
                            </a>
                            <a href="community_branding.php" class="submenu-link admin-link" data-required-role="admin">
                                <i class="fa fa-paint-brush"></i> Community Branding
                            </a>
                            <a href="manage_users.php" class="submenu-link admin-link" data-required-role="admin">
                                <i class="fa fa-users-cog"></i> Manage Users
                            </a>
                            <a href="manage_community.php" class="submenu-link admin-link" data-required-role="admin">
                                <i class="fa fa-globe"></i> Communities
                            </a>
                            <a href="manage_villages.php" class="submenu-link admin-link" data-required-role="admin">
                                <i class="fa fa-house"></i> Manage Villages
                            </a>
                            <!-- New Access Logs Link -->
                            <a href="access_logs.php" class="submenu-link admin-link" data-required-role="admin">
                                <i class="fa fa-file-alt"></i> Access Logs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="menu-header">Common</div>
        <a class="nav-link <?= $current_page == 'verify_poa.php' ? 'active' : '' ?>" href="verify_poa.php">
            <i class="fa fa-check-circle"></i> <span>Verify POA</span>
        </a>
        <a class="nav-link text-danger" href="logout.php">
            <i class="fa fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <?= $content ?>
    </div>

    <!-- Layout JS -->
    <script src="../js/layout.js"></script>

    <!-- SweetAlert Admin Link Guard -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const userRole = document.body.dataset.userRole;

            document.querySelectorAll('.admin-link').forEach(link => {
                link.addEventListener('click', e => {
                    const requiredRoles = link.dataset.requiredRole.split(',');
                    if (!requiredRoles.includes(userRole)) {
                        e.preventDefault(); // prevent navigation

                        Swal.fire({
                            icon: 'error',
                            title: 'Access Denied',
                            text: 'You do not have permission to access this page!',
                            confirmButtonText: 'OK',
                            allowOutsideClick: false
                        });

                        // Play alarm sound
                        const audio = new Audio('/assets/alarm.mp3');
                        audio.play();
                    }
                });
            });
        });
    </script>

</body>

</html>