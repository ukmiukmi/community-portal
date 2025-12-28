// ===================== POA MODAL HANDLER =====================
document.addEventListener("DOMContentLoaded", function() {
    const citizensTable = document.getElementById('citizensTableWrapper');
    let currentPoaRow = null;

    // Click on Issue POA button
    citizensTable.addEventListener('click', async function(e){
        const btn = e.target.closest('button');
        if(!btn || !btn.classList.contains('btn-poa')) return;

        e.preventDefault();
        e.stopPropagation();

        const id = btn.dataset.id;
        if(!id) return;

        currentPoaRow = btn.closest('tr') || btn.closest('.card');
        document.getElementById('poaCitizenId').value = id;

        // Select citizen in modal dropdown
        let sel = document.getElementById("modal_citizen_select");
        sel.value = id;

        modalFillCitizen(sel.options[sel.selectedIndex]); // auto-fill fields

        new bootstrap.Modal(document.getElementById('poaModal')).show();
    });

    // Confirm POA button
    document.getElementById('confirmPoaBtn')?.addEventListener('click', async function(){
        const id = document.getElementById('poaCitizenId').value;
        if(!id) return;

        const fd = new FormData();
        fd.append('action','poa');
        fd.append('id',id);

        try {
            const res = await fetch('search_citizens.php', { method:'POST', body:fd });
            const data = await res.json();
            alert(data.message);

            if(data.status==='success'){
                bootstrap.Modal.getInstance(document.getElementById('poaModal')).hide();

                if(currentPoaRow){
                    const poaBtn = currentPoaRow.querySelector('.btn-poa');
                    if(poaBtn){
                        poaBtn.remove();
                        const span = document.createElement('span');
                        span.className = 'badge bg-success';
                        span.textContent = 'POA Issued';
                        currentPoaRow.querySelector('td:last-child')?.appendChild(span);
                    }
                    currentPoaRow.classList.add('poa-issued');
                }
            }
        } catch(err){
            console.error(err);
            alert('Failed to issue POA.');
        }
    });

    // ===================== Auto-fill modal fields =====================
    window.modalFillCitizen = function(opt){
        if(!opt) return;

        document.getElementById("modal_full_name").value = opt.dataset.full || "";
        document.getElementById("modal_phone").value = opt.dataset.phone || "";
        document.getElementById("modal_citizen_id").value = opt.dataset.citizenid || "";
        document.getElementById("modal_land_location").value = opt.dataset.house || "";

        // Load villages dynamically
        fetch("get_villages.php?community_id=" + opt.dataset.community)
        .then(r => r.json())
        .then(list => {
            let sel = document.getElementById("modal_village_select");
            sel.innerHTML = '<option value="">-- Select Village --</option>';
            list.forEach(v => {
                let o = document.createElement("option");
                o.value = v.id;
                o.textContent = v.name;
                if(v.id == opt.dataset.village) o.selected = true;
                sel.appendChild(o);
            });
        });
    };
});
