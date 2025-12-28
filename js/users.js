// ==========================
// users.js v5.1 - Mobile cards with labels, AJAX add/edit, fixed Add user
// ==========================

document.addEventListener("DOMContentLoaded", () => {
    const usersTable = document.getElementById('usersTable');
    const addUserBtn = document.getElementById('openAddModalBtn');
    const userModal = document.getElementById('userModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const userForm = document.getElementById('userForm');
    const profileImageInput = document.getElementById('profile_image');
    const profilePreview = document.getElementById('profilePreview');

    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const communityFilter = document.getElementById('communityFilter');
    const mobileCardsContainer = document.getElementById('mobileCards');
    const paginationContainer = document.getElementById('pagination');
    const mobilePaginationContainer = document.getElementById('mobilePagination');

    let allUsers = [];
    let editingUserId = null;
    let currentPage = 1;
    const usersPerPage = 8;

    // -------------------- Modal Handlers ----------------
    addUserBtn.addEventListener('click', () => {
        modalTitle.textContent = 'Add User';
        editingUserId = null;
        userForm.reset();
        document.getElementById('userIdField').value = '';
        profilePreview.src = '../uploads/users/default.png';
        userModal.classList.add('show');
    });

    modalCloseBtn.addEventListener('click', () => userModal.classList.remove('show'));
    cancelBtn.addEventListener('click', () => userModal.classList.remove('show'));

    profileImageInput.addEventListener('change', e => {
        const file = e.target.files[0];
        if (file) profilePreview.src = URL.createObjectURL(file);
    });

    // -------------------- Fetch Users ----------------
    async function fetchUsers() {
        try {
            const res = await axios.get('manage_users.php?action=fetch');
            if (res.data.status === 'success') {
                allUsers = res.data.users || [];
                currentPage = 1;
                renderUsers();
            } else {
                usersTable.innerHTML = '<tr><td colspan="7">No users found</td></tr>';
                Swal.fire('Error', res.data.message || 'Failed to load users.', 'error');
            }
        } catch (err) {
            console.error(err);
            usersTable.innerHTML = '<tr><td colspan="7">Error loading users</td></tr>';
            Swal.fire('Error', 'Failed to load users.', 'error');
        }
    }

    // -------------------- Render Users ----------------
    function renderUsers() {
        const searchText = searchInput.value.trim().toLowerCase();
        const roleVal = roleFilter.value;
        const communityVal = communityFilter.value;

        let filtered = allUsers.filter(u => {
            const matchesSearch = u.full_name.toLowerCase().includes(searchText) || u.username.toLowerCase().includes(searchText);
            const matchesRole = roleVal ? u.role === roleVal : true;
            const matchesCommunity = communityVal ? u.assigned_community_id == communityVal : true;
            return matchesSearch && matchesRole && matchesCommunity;
        });

        const totalPages = Math.ceil(filtered.length / usersPerPage);
        const start = (currentPage - 1) * usersPerPage;
        const paginated = filtered.slice(start, start + usersPerPage);

        // --- Desktop Table ---
        usersTable.innerHTML = '';
        if (!paginated.length) {
            usersTable.innerHTML = '<tr><td colspan="7">No users found</td></tr>';
        } else {
            paginated.forEach(u => {
                const highlight = text => searchText ? text.replace(new RegExp(`(${searchText})`, 'gi'), '<mark>$1</mark>') : text;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><img src="../uploads/users/${u.profile_image || 'default.png'}" style="width:40px;height:40px;border-radius:50%;" onerror="this.src='../uploads/users/default.png'"></td>
                    <td>${highlight(u.full_name)}</td>
                    <td>${highlight(u.username)}</td>
                    <td>${u.role}</td>
                    <td>${u.community_name || ''}</td>
                    <td>${u.created_at}</td>
                    <td>
                        <button class="editBtn btn btn-sm btn-warning" data-id="${u.id}"><i class="fa fa-edit"></i></button>
                        <button class="deleteBtn btn btn-sm btn-danger" data-id="${u.id}"><i class="fa fa-trash"></i></button>
                    </td>`;
                usersTable.appendChild(tr);
            });
        }

        // --- Mobile Cards ---
        mobileCardsContainer.innerHTML = '';
        paginated.forEach(u => {
            const highlight = text => searchText ? text.replace(new RegExp(`(${searchText})`, 'gi'), '<mark>$1</mark>') : text;
            const card = document.createElement('div');
            card.classList.add('mobile-card');

            card.innerHTML = `
                <div class="mobile-card-row"><label>Image:</label><img src="../uploads/users/${u.profile_image || 'default.png'}" onerror="this.src='../uploads/users/default.png'"></div>
                <div class="mobile-card-row"><label>Full Name:</label> <span>${highlight(u.full_name)}</span></div>
                <div class="mobile-card-row"><label>Username:</label> <span>${highlight(u.username)}</span></div>
                <div class="mobile-card-row"><label>Role:</label> <span>${u.role}</span></div>
                <div class="mobile-card-row"><label>Community:</label> <span title="${u.community_name || ''}">${u.community_name || ''}</span></div>
                <div class="mobile-card-row"><label>Created:</label> <span>${u.created_at}</span></div>
                <div class="mobile-card-row actions">
                    <button class="editBtn btn btn-sm btn-warning" data-id="${u.id}"><i class="fa fa-edit"></i></button>
                    <button class="deleteBtn btn btn-sm btn-danger" data-id="${u.id}"><i class="fa fa-trash"></i></button>
                </div>`;
            
            mobileCardsContainer.appendChild(card);
        });

        renderPagination(totalPages);
        handleRowActions(usersTable);
        handleRowActions(mobileCardsContainer);
    }

    // -------------------- Pagination ----------------
    function renderPagination(totalPages) {
        paginationContainer.innerHTML = '';
        mobilePaginationContainer.innerHTML = '';
        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            const liDesktop = document.createElement('li');
            liDesktop.textContent = i;
            liDesktop.className = i === currentPage ? 'active' : '';
            liDesktop.addEventListener('click', () => { currentPage = i; renderUsers(); window.scrollTo({top:0, behavior:'smooth'}); });
            paginationContainer.appendChild(liDesktop);

            const liMobile = document.createElement('li');
            liMobile.textContent = i;
            liMobile.className = i === currentPage ? 'active' : '';
            liMobile.addEventListener('click', () => { currentPage = i; renderUsers(); window.scrollTo({top:0, behavior:'smooth'}); });
            mobilePaginationContainer.appendChild(liMobile);
        }

        paginationContainer.style.display = window.innerWidth > 768 ? 'flex' : 'none';
        mobilePaginationContainer.style.display = window.innerWidth <= 768 ? 'flex' : 'none';
    }

    // -------------------- Event Delegation ----------------
    function handleRowActions(container) {
        container.querySelectorAll('.editBtn').forEach(btn => {
            btn.onclick = () => {
                const id = btn.dataset.id;
                const user = allUsers.find(u => u.id == id);
                if (!user) return;

                editingUserId = id;
                modalTitle.textContent = 'Edit User';
                document.getElementById('userIdField').value = user.id;
                document.getElementById('full_name').value = user.full_name;
                document.getElementById('username').value = user.username;
                document.getElementById('role').value = user.role;
                document.getElementById('assigned_community_id').value = user.assigned_community_id || '';
                profilePreview.src = `../uploads/users/${user.profile_image || 'default.png'}`;
                userModal.classList.add('show');
            };
        });

        container.querySelectorAll('.deleteBtn').forEach(btn => {
            btn.onclick = async () => {
                const id = btn.dataset.id;
                const result = await Swal.fire({
                    title: 'Delete user?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete'
                });
                if (!result.isConfirmed) return;

                try {
                    await axios.post('manage_users.php?action=delete', new URLSearchParams({ id }));
                    Swal.fire('Deleted', 'User deleted successfully', 'success');
                    fetchUsers();
                } catch (err) {
                    console.error(err);
                    Swal.fire('Error', 'Failed to delete user', 'error');
                }
            };
        });
    }

    // -------------------- Filters ----------------
    searchInput.addEventListener('input', () => { currentPage = 1; renderUsers(); });
    roleFilter.addEventListener('change', () => { currentPage = 1; renderUsers(); });
    communityFilter.addEventListener('change', () => { currentPage = 1; renderUsers(); });

    // -------------------- Add/Edit Submit ----------------
    userForm.addEventListener('submit', async e => {
        e.preventDefault();
        const formData = new FormData(userForm);
        if (editingUserId) formData.set('id', editingUserId);

        try {
            const res = await axios.post('manage_users.php?action=save', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
            if (res.data.status === 'success') {
                Swal.fire('Success', res.data.message, 'success');
                userModal.classList.remove('show');
                fetchUsers();
            } else {
                Swal.fire('Error', res.data.message, 'error');
            }
        } catch (err) {
            console.error(err);
            Swal.fire('Error', 'Failed to save user', 'error');
        }
    });

    // -------------------- Initial Fetch ----------------
    fetchUsers();
});
