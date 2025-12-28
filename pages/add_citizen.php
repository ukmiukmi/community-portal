<?php
session_start();
include('../auth_check.php');
requireRole(['admin', 'registrar']);
include('db.php');

$role = $_SESSION['role'] ?? '';
$assigned_community_id = $_SESSION['assigned_community_id'] ?? null;
$username = $_SESSION['username'];

// Fetch allowed communities
if ($role === 'admin') {
    $communities = $conn->query("SELECT id, name, slug FROM communities ORDER BY name ASC");
} else {
    $communities = $conn->query("SELECT id, name, slug FROM communities WHERE id = $assigned_community_id");
}

// Fetch states
$states_res = $conn->query("SELECT id, name FROM states ORDER BY name ASC");

// ---------------- Capture page content -----------------
ob_start();
?>

<a href="dashboard.php#citizensTable" class="back-btn">← Back to Citizens</a>

<div class="add-citizen-container">

    <h2>Add Citizen</h2>
    <form id="addCitizenForm" enctype="multipart/form-data">

        <div class="form-card">
            <label>Full Name</label>
            <input type="text" name="full_name" placeholder="Full Name" required>
        </div>

        <?php if ($role === 'admin'): ?>
            <div class="form-card">
                <label>Community</label>
                <select name="community_id" id="community_id" required>
                    <option value="">Select Community</option>
                    <?php while ($row = $communities->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        <?php else: ?>
            <div class="form-card">
                <label>Community</label>
                <input type="text" value="<?= htmlspecialchars($communities->fetch_assoc()['name'] ?? '') ?>" disabled>
                <input type="hidden" name="community_id" value="<?= $assigned_community_id ?>">
            </div>
        <?php endif; ?>

        <div class="form-card">
            <label>Village</label>
            <select name="village_id" id="village_id" required>
                <option value="">Select Village</option>
            </select>
        </div>

        <div class="form-card">
            <label>House Address</label>
            <input type="text" name="house_address" placeholder="House Address" required>
        </div>

        <div class="form-card">
            <label>Phone</label>
            <input type="text" name="phone" placeholder="Phone" required>
        </div>

        <div class="form-card">
            <label>Citizen Type</label>
            <select name="citizen_type" required>
                <option value="">Citizen Type</option>
                <option value="indigene">Indigene</option>
                <option value="tenant">Tenant</option>
            </select>
        </div>

        <div class="form-card">
            <label>State of Origin</label>
            <select name="state_of_origin" required>
                <option value="">Select State of Origin</option>
                <?php while ($state = $states_res->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($state['name']) ?>"><?= htmlspecialchars($state['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-card">
            <label>Citizen Image</label>
            <input type="file" name="citizen_image" accept="image/*" onchange="previewImage(event)">
            <img id="preview-img" src="../uploads/citizens/default-avatar.png" alt="Default Avatar" style="display:block; max-width:120px; max-height:120px; margin-top:10px; border-radius:12px; object-fit:cover; border:1px solid #cbd5e1;">
        </div>

        <div class="form-card text-center">
            <button type="submit">Add Citizen</button>
        </div>

    </form>
</div>

<style>
    .add-citizen-container {
        max-width: 700px;
        margin: 40px auto;
        padding: 25px;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    }

    .add-citizen-container h2 {
        text-align: center;
        color: #0ea5e9;
        margin-bottom: 25px;
        font-size: 1.8rem;
    }

    .form-card {
        background: #f9fafb;
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 15px;
        transition: 0.3s;
        position: relative;
    }

    .form-card:hover {
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .form-card label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
        color: #1e293b;
    }

    .form-card input,
    .form-card select {
        width: 100%;
        padding: 12px 14px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-size: 0.95rem;
        transition: .3s;
    }

    .form-card input:focus,
    .form-card select:focus {
        outline: none;
        border-color: #0ea5e9;
        box-shadow: 0 0 6px #0ea5e9aa;
    }

    .add-citizen-container button {
        width: 100%;
        background: #0ea5e9;
        color: #fff;
        font-weight: 600;
        padding: 14px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        transition: .3s;
        font-size: 1rem;
    }

    .add-citizen-container button:hover {
        background: #0284c7;
    }

    .back-btn {
        position: fixed;
        top: 100px;
        /* below navbar */
        right: 20px;
        background: #0ea5e9;
        color: #fff;
        padding: 10px 16px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        z-index: 1001;
        /* above navbar */
        transition: 0.3s;
    }

    .back-btn:hover {
        background: #0284c7;
    }

    @media (max-width: 768px) {
        .add-citizen-container {
            margin: 60px 15px;
            padding: 15px;
        }

        .back-btn {
            top: 80px;
            right: 15px;
            padding: 8px 12px;
            font-size: 0.85rem;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function fetchVillages(commId) {
        if (!commId) return;
        fetch('get_villages.php?community_id=' + commId)
            .then(res => res.json())
            .then(resp => {
                const villageSelect = document.getElementById('village_id');
                villageSelect.innerHTML = '<option value="">Select Village</option>';
                if (resp.status === 'success') {
                    resp.data.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = v.id;
                        opt.textContent = v.name;
                        villageSelect.appendChild(opt);
                    });
                    if ("<?= $role ?>" === "registrar" && resp.data.length > 0) {
                        villageSelect.value = resp.data[0].id;
                    }
                }
            }).catch(err => console.error(err));
    }

    function previewImage(event) {
        const img = document.getElementById('preview-img');
        if (event.target.files && event.target.files[0]) {
            img.src = URL.createObjectURL(event.target.files[0]);
        } else {
            img.src = '../uploads/citizens/default-avatar.png'; // fixed default path
        }
        img.style.display = 'block';
    }

    document.addEventListener("DOMContentLoaded", () => {
        const role = "<?= $role ?>";
        const assignedCommunityId = <?= $assigned_community_id ?? 'null' ?>;
        const communitySelect = document.getElementById('community_id');
        if (role !== 'registrar' && communitySelect) {
            communitySelect.addEventListener('change', () => fetchVillages(communitySelect.value));
        } else if (role === 'registrar' && assignedCommunityId) {
            fetchVillages(assignedCommunityId);
        }

        // AJAX form submission
        const form = document.getElementById('addCitizenForm');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            Swal.fire({
                title: 'Adding Citizen...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            try {
                const res = await fetch('add_citizen_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                Swal.close();
                if (data.status === 'success') {
                    Swal.fire({
                            icon: 'success',
                            title: 'Citizen Added!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        })
                        .then(() => window.location.href = 'dashboard.php#citizen-' + data.citizen_id);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            } catch (err) {
                Swal.close();
                console.error(err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Something went wrong. Please try again.'
                });
            }
        });
    });
</script>

<?php
$content = ob_get_clean();
include('../include/layout.php');
?>