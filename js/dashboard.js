document.addEventListener('DOMContentLoaded', () => {

    // ===== SweetAlert2 Toast =====
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });

    // ===== Counter Animation =====
    const animateCounter = (el) => {
        const target = parseInt(el.dataset.count) || 0;
        let count = 0;
        const increment = Math.max(1, Math.ceil(target / 120));
        const interval = setInterval(() => {
            count += increment;
            if (count >= target) {
                el.textContent = target.toLocaleString();
                clearInterval(interval);
            } else {
                el.textContent = count.toLocaleString();
            }
        }, 18);
    };
    document.querySelectorAll('.counter').forEach(animateCounter);

    // ===== Edit Citizen Modal =====
    async function handleEdit(id) {
        try {
            const res = await fetch(`citizens_table_ajax.php?action=get&id=${id}`);
            const data = await res.json();
            if (!data?.id) return;

            const modal = document.getElementById('editCitizenModal') || document.getElementById('editModal');
            if (!modal) return;

            modal.querySelectorAll('input, select').forEach(input => {
                if (data[input.name] !== undefined) input.value = data[input.name];
            });

            await loadVillages(data.community_id, data.village_id);
            new bootstrap.Modal(modal).show();
        } catch (err) {
            console.error(err);
            Swal.fire('Error', 'Failed to load citizen for editing', 'error');
        }
    }

    // ===== Delete Citizen =====
    async function handleDelete(id) {
        const confirmed = await Swal.fire({
            title: 'Are you sure?',
            text: "This will permanently remove the citizen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete!',
            cancelButtonText: 'Cancel'
        });
        if (!confirmed.isConfirmed) return;

        try {
            const res = await fetch('citizens_table_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${encodeURIComponent(id)}`
            });
            const resp = await res.json();
            Toast.fire({ icon: resp.status === 'success' ? 'success' : 'error', title: resp.message });
            if (resp.status === 'success') window.reloadTable?.();
        } catch (err) {
            console.error(err);
            Toast.fire({ icon: 'error', title: 'Failed to delete citizen' });
        }
    }

    // ===== POA Modal =====
    function handlePOA(id, fullName) {
        const modal = document.getElementById('poaModal');
        if (!modal) return;
        modal.querySelector('input[name="id"]').value = id;
        modal.querySelector('.poa-citizen-name').textContent = fullName || 'Citizen';
        new bootstrap.Modal(modal).show();
    }

    // ===== Load Villages =====
    async function loadVillages(communityId, selectedVillageId = null) {
        const villageSelect = document.querySelector('#edit_village_id');
        if (!villageSelect) return;

        villageSelect.innerHTML = `<option>Loading villages...</option>`;
        if (!communityId) return villageSelect.innerHTML = `<option value="">Select Village</option>`;

        try {
            const res = await fetch(`get_villages.php?community_id=${encodeURIComponent(communityId)}`);
            const data = await res.json();
            let options = `<option value="">Select Village</option>`;
            if (data.status === 'success' && data.data.length) {
                data.data.forEach(v => options += `<option value="${v.id}">${v.name}</option>`);
            } else options = `<option value="">No villages found</option>`;
            villageSelect.innerHTML = options;
            if (selectedVillageId) villageSelect.value = selectedVillageId;
        } catch (err) {
            console.error(err);
            villageSelect.innerHTML = `<option value="">Load failed</option>`;
            Toast.fire({ icon: 'error', title: 'Failed to load villages' });
        }
    }

    // ===== Charts =====
    const renderGrowthChart = () => {
        const ctx = document.getElementById('growthChart');
        if (!ctx || typeof dashboardData === 'undefined') return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dashboardData.growthData.map(d => d.month),
                datasets: [{
                    data: dashboardData.growthData.map(d => d.value),
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    pointRadius: 4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
            }
        });
    };

    const renderTypeChart = () => {
        const ctx = document.getElementById('typeChart');
        if (!ctx || typeof dashboardData === 'undefined') return;

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: dashboardData.typeData.map(d => d.name),
                datasets: [{
                    data: dashboardData.typeData.map(d => d.value),
                    backgroundColor: ['#0ea5e9','#64748b'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: true, position: 'bottom' },
                    tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.raw.toLocaleString()}` } }
                }
            }
        });
    };

    // ===== Assign Community AJAX =====
    const assignForm = document.querySelector('#assignCommunityForm');
    if (assignForm) {
        assignForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(assignForm);
            try {
                const res = await fetch('assign_community.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.status === 'success') {
                    Toast.fire({ icon: 'success', title: data.message });
                    const tableBody = document.querySelector('#assignedCommunityTable');
                    if (tableBody && Array.isArray(data.updatedRegistrars)) {
                        tableBody.innerHTML = '';
                        data.updatedRegistrars.forEach(r => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `<td>${r.username}</td><td>${r.assignedCommunity}</td>`;
                            tableBody.appendChild(tr);
                        });
                    }
                    assignForm.reset();
                } else Toast.fire({ icon: 'error', title: data.message });
            } catch (err) {
                console.error(err);
                Toast.fire({ icon: 'error', title: 'Failed to assign community.' });
            }
        });
    }

    // ===== Event Delegation =====
    document.body.addEventListener('click', e => {
        if (e.target.closest('.btn-edit')) handleEdit(e.target.closest('.btn-edit').dataset.id);
        if (e.target.closest('.btn-delete')) handleDelete(e.target.closest('.btn-delete').dataset.id);
        if (e.target.closest('.btn-poa')) handlePOA(
            e.target.closest('.btn-poa').dataset.id,
            e.target.closest('.btn-poa').dataset.full
        );
    });

    // ===== Initialize Charts =====
    renderGrowthChart();
    renderTypeChart();
});
