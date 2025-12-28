<!-- ===== Admin: Assign Communities ===== -->
<?php if ($role === 'admin'): ?>
  <div class="mt-4" id="assignCommunitySection">
    <h5>Manage Communities for Registrars</h5>

    <form id="assignCommunityForm" class="row g-2">
      <div class="col-md-5">
        <select name="registrar_id" class="form-select" required>
          <option value="">Select Registrar</option>
          <?php foreach ($registrars as $r): ?>
            <option value="<?= $r['id'] ?>" data-assigned="<?= $r['assigned_community_id'] ?? '' ?>"><?= htmlspecialchars($r['username']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <select name="community_id" class="form-select" required>
          <option value="">Loading communities...</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-success w-100" id="assignBtn">Assign</button>
      </div>
    </form>

    <div class="mt-3" id="assignedCommunityContainer"></div>
  </div>

  <style>
    /* Smooth highlight for assignment */
    .highlight-assign {
      animation: highlightAssign 1.5s ease forwards;
    }

    @keyframes highlightAssign {
      0% {
        background-color: #d4edda;
      }

      50% {
        background-color: #d4edda;
      }

      100% {
        background-color: transparent;
      }
    }

    /* Smooth highlight for removal */
    .highlight-remove {
      animation: highlightRemove 1.5s ease forwards;
    }

    @keyframes highlightRemove {
      0% {
        background-color: #f8d7da;
      }

      50% {
        background-color: #f8d7da;
      }

      100% {
        background-color: transparent;
      }
    }

    /* SweetAlert2 toast fixes */
    .swal-toast-container {
      z-index: 20000 !important;
      /* above navbar */
      top: 60px !important;
      /* offset from top, adjust to match navbar height */
    }
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const form = document.getElementById('assignCommunityForm');
      const registrarSelect = form.querySelector('select[name="registrar_id"]');
      const communitySelect = form.querySelector('select[name="community_id"]');
      const assignBtn = document.getElementById('assignBtn');
      const container = document.getElementById('assignedCommunityContainer');
      const perPage = 5;
      let currentPage = 1;

      function showToast(status, message) {
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: status === 'success' ? 'success' : 'error',
          title: message,
          showConfirmButton: false,
          timer: 3000,
          timerProgressBar: true,
          customClass: {
            container: 'swal-toast-container'
          }
        });
      }

      function loadCommunities(selectedId = null) {
        fetch('assign_community.php?action=get_communities')
          .then(res => res.json())
          .then(json => {
            if (json.status === 'success') {
              let html = '<option value="">Select Community</option>';
              html += json.data.map(c => `<option value="${c.id}" ${c.id==selectedId?'selected':''}>${c.name}</option>`).join('');
              communitySelect.innerHTML = html;
            } else {
              communitySelect.innerHTML = '<option value="">Failed to load</option>';
            }
          })
          .catch(() => communitySelect.innerHTML = '<option value="">Error loading</option>');
      }

      function updateAssignButton() {
        const selectedOption = registrarSelect.selectedOptions[0];
        const assigned = selectedOption ? selectedOption.dataset.assigned : null;
        assignBtn.textContent = assigned ? 'Reassign' : 'Assign';
      }

      registrarSelect.addEventListener('change', () => {
        const assigned = registrarSelect.selectedOptions[0].dataset.assigned;
        loadCommunities(assigned);
        updateAssignButton();
      });

      function loadAssigned(page = 1, highlightId = null, highlightType = 'assign') {
        currentPage = page;
        fetch('assign_community.php?action=get_assigned')
          .then(res => res.json())
          .then(json => {
            if (json.status !== 'success') {
              container.innerHTML = `<div class="text-danger py-2">${json.message||'Error loading data'}</div>`;
              return;
            }

            const start = (currentPage - 1) * perPage;
            const end = start + perPage;
            const paginated = json.data.slice(start, end);

            let tableHTML = `
<table class="table table-bordered table-striped d-none d-md-table">
    <thead><tr><th>Registrar</th><th>Assigned Community</th><th>Actions</th></tr></thead>
    <tbody>
        ${paginated.map(r=>`<tr data-id="${r.id}" class="${highlightId==r.id?highlightType==='assign'?'highlight-assign':'highlight-remove':''}">
            <td>${r.username}</td>
            <td>${r.community_name||'Not Assigned'}</td>
            <td>
                <button class="btn btn-sm btn-outline-danger btn-remove" 
                        data-id="${r.id}" 
                        ${r.assigned_community_id ? '' : 'disabled'}>
                    Remove
                </button>
            </td>
        </tr>`).join('')}
    </tbody>
</table>
`;

            let mobileHTML = `<div class="d-md-none row g-2">
${paginated.map(r=>`<div class="col-12">
    <div class="card p-3 ${highlightId==r.id?highlightType==='assign'?'highlight-assign':'highlight-remove':''}" data-id="${r.id}">
        <div><strong>Registrar:</strong> ${r.username}</div>
        <div><strong>Community:</strong> ${r.community_name||'Not Assigned'}</div>
        <div class="mt-2">
            <button class="btn btn-sm btn-outline-danger btn-remove w-100" 
                    data-id="${r.id}" 
                    ${r.assigned_community_id ? '' : 'disabled'}>
                Remove
            </button>
        </div>
    </div>
</div>`).join('')}
</div>`;

            const totalPages = Math.ceil(json.data.length / perPage);
            let paginationHTML = '';
            if (totalPages > 1) {
              paginationHTML = '<nav><ul class="pagination mt-2">';
              for (let i = 1; i <= totalPages; i++) {
                paginationHTML += `<li class="page-item ${i===currentPage?'active':''}">
            <a href="#" class="page-link" data-page="${i}">${i}</a>
        </li>`;
              }
              paginationHTML += '</ul></nav>';
            }

            container.innerHTML = tableHTML + mobileHTML + paginationHTML;

            // Remove buttons
            container.querySelectorAll('.btn-remove').forEach(btn => {
              btn.addEventListener('click', () => {
                if (btn.disabled) return;
                const id = btn.dataset.id;
                Swal.fire({
                  title: 'Are you sure?',
                  text: 'This will remove the assigned community!',
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonText: 'Yes, remove'
                }).then(result => {
                  if (result.isConfirmed) {
                    fetch('assign_community.php', {
                        method: 'POST',
                        headers: {
                          'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `remove_id=${id}`
                      })
                      .then(res => res.json())
                      .then(json => {
                        showToast(json.status, json.message);
                        const selectedRegistrar = registrarSelect.value;
                        if (selectedRegistrar == id) {
                          registrarSelect.selectedOptions[0].dataset.assigned = '';
                          updateAssignButton();
                          loadCommunities();
                        }
                        loadAssigned(currentPage, id, 'remove'); // highlight removal
                      })
                  }
                });
              });
            });

            // Pagination click
            container.querySelectorAll('.page-link').forEach(a => {
              a.addEventListener('click', e => {
                e.preventDefault();
                loadAssigned(parseInt(a.dataset.page));
              });
            });
          })
          .catch(() => container.innerHTML = '<div class="text-danger py-2">Error loading data</div>');
      }

      loadAssigned();

      form.addEventListener('submit', e => {
        e.preventDefault();
        const fd = new FormData(form);
        fetch('assign_community.php', {
            method: 'POST',
            body: fd
          })
          .then(res => res.json())
          .then(json => {
            showToast(json.status, json.message);
            if (json.status === 'success') {
              const selectedOption = registrarSelect.selectedOptions[0];
              selectedOption.dataset.assigned = form.community_id.value;
              updateAssignButton();
              form.reset();
              loadCommunities();
              loadAssigned(currentPage, selectedOption.value, 'assign'); // highlight assignment
            }
          })
          .catch(() => showToast('error', 'Failed to assign'));
      });
    });
  </script>
<?php endif; ?>