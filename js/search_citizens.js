document.addEventListener("DOMContentLoaded", () => {
    const tableBody = document.getElementById("citizensBody");
    const mobileCards = document.getElementById("citizensCards");
    const searchInput = document.getElementById("searchInput");
    const filterType = document.getElementById("filterType");
    const paginationEl = document.getElementById("pagination");
    const editForm = document.getElementById("editCitizenForm");
    const editModalEl = document.getElementById("editCitizenModal");

    let currentPage = 1;
    let totalPages = 1;

    function highlightText(text, search) {
        if (!search) return text ?? "";
        try {
            return String(text).replace(
                new RegExp(`(${search})`, "gi"),
                "<mark>$1</mark>"
            );
        } catch {
            const esc = search.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
            return String(text).replace(
                new RegExp(`(${esc})`, "gi"),
                "<mark>$1</mark>"
            );
        }
    }

    // ===== Load villages helper =====
    function loadVillages(communityId, selectedVillageId = null) {
        const villageSelect = document.getElementById("edit_village_id");
        if (!villageSelect) return;
        villageSelect.innerHTML = "<option>Loading...</option>";
        if (!communityId) {
            villageSelect.innerHTML =
                '<option value="">Select Village</option>';
            return;
        }
        fetch(
            `get_villages.php?community_id=${encodeURIComponent(communityId)}`
        )
            .then((res) => res.json())
            .then((json) => {
                let html = '<option value="">Select Village</option>';
                if (json.status === "success" && Array.isArray(json.data)) {
                    html += json.data
                        .map(
                            (v) =>
                                `<option value="${v.id}" ${
                                    v.id == selectedVillageId ? "selected" : ""
                                }>${v.name}</option>`
                        )
                        .join("");
                }
                villageSelect.innerHTML = html;
            })
            .catch((err) => {
                console.error("loadVillages error", err);
                villageSelect.innerHTML =
                    '<option value="">Failed to load</option>';
            });
    }

    document
        .getElementById("edit_community_id")
        ?.addEventListener("change", (e) => {
            loadVillages(e.target.value);
        });

    // ===== Image preview =====
    window.previewEditImage = function (ev) {
        const file = ev?.target?.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = () => {
            const img = document.getElementById("edit_preview_img");
            if (img) {
                img.src = reader.result;
                img.style.display = "block";
            }
        };
        reader.readAsDataURL(file);
    };

    // ===== Delete Citizen =====
    window.deleteCitizen = function (id) {
        if (!id) return;
        Swal.fire({
            title: "Are you sure?",
            text: "This will permanently remove the citizen!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, delete!",
            cancelButtonText: "Cancel",
        }).then((result) => {
            if (!result.isConfirmed) return;
            fetch("update_citizen_ajax.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `delete_id=${encodeURIComponent(id)}`,
            })
                .then((r) => r.json())
                .then((resp) => {
                    if (resp.status === "success") {
                        Swal.fire({
                            icon: "success",
                            title: "Deleted",
                            text: resp.message,
                            timer: 1400,
                            showConfirmButton: false,
                        });
                        loadCitizens(currentPage);
                        if (typeof refreshDashboard === "function")
                            refreshDashboard();
                    } else {
                        Swal.fire(
                            "Error",
                            resp.message || "Delete failed",
                            "error"
                        );
                    }
                })
                .catch(() => Swal.fire("Error", "Delete failed", "error"));
        });
    };

    // ===== Populate edit modal =====
    window.populateEditModal = function (data) {
        if (!editModalEl || !data) return;

        const idEl = document.getElementById("edit_citizen_id");
        const firstEl = document.getElementById("edit_first_name");
        const lastEl = document.getElementById("edit_last_name");
        const phoneEl = document.getElementById("edit_phone");
        const typeEl = document.getElementById("edit_citizen_type");
        const stateEl = document.getElementById("edit_state_of_origin");
        const commEl = document.getElementById("edit_community_id");
        const villageEl = document.getElementById("edit_village_id");
        const houseEl = document.getElementById("edit_house_address");
        const previewImg = document.getElementById("edit_preview_img");

        if (idEl) idEl.value = data.id ?? "";
        if (firstEl) firstEl.value = data.first_name ?? "";
        if (lastEl) lastEl.value = data.last_name ?? "";
        if (phoneEl) phoneEl.value = data.phone ?? "";
        if (typeEl) typeEl.value = data.citizen_type ?? "";

        if (stateEl) {
            const targetState = (data.state_of_origin || "")
                .trim()
                .toLowerCase();
            const matchedOption = Array.from(stateEl.options).find(
                (opt) => opt.value.trim().toLowerCase() === targetState
            );
            stateEl.value = matchedOption ? matchedOption.value : "";
        }

        if (commEl) {
            commEl.value = data.community_id ?? "";
            setTimeout(
                () => loadVillages(data.community_id, data.village_id),
                50
            );
        }

        if (houseEl) houseEl.value = data.house_address ?? "";

        if (previewImg) {
            previewImg.src = data.image_path
                ? `../${data.image_path}?t=${Date.now()}`
                : "../uploads/default.png";
            previewImg.style.display = "block";
        }

        const fileInput = editForm.querySelector('input[name="citizen_image"]');
        if (fileInput) fileInput.value = "";

        new bootstrap.Modal(editModalEl).show();
    };

    // ===== Submit Edit Form =====
    if (editForm) {
        editForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const fd = new FormData(editForm);
            fd.append(
                "update_id",
                document.getElementById("edit_citizen_id").value
            );

            fetch("update_citizen_ajax.php", { method: "POST", body: fd })
                .then((r) => r.json())
                .then((res) => {
                    if (res.status === "success") {
                        Swal.fire({
                            icon: "success",
                            title: "Updated",
                            text: res.message,
                            timer: 1200,
                            showConfirmButton: false,
                        });

                        // ===== AUTO CLOSE MODAL FIX =====
                        const modal = bootstrap.Modal.getInstance(editModalEl);
                        if (modal) modal.hide();

                        loadCitizens(currentPage);
                        if (typeof refreshDashboard === "function")
                            refreshDashboard();
                    } else {
                        Swal.fire(
                            "Error",
                            res.message || "Update failed",
                            "error"
                        );
                    }
                })
                .catch((err) => {
                    console.error("Fetch error:", err);
                    Swal.fire("Error", "Update failed", "error");
                });
        });
    }

    // ===== Init Buttons with Safer JSON Decode & BI Icons =====
    function initActionButtons() {
        document.querySelectorAll(".btn-edit").forEach((btn) => {
            btn.onclick = () => {
                try {
                    const safeJson = btn.dataset.citizen.replace(
                        /&apos;/g,
                        "'"
                    );
                    const data = JSON.parse(safeJson);
                    populateEditModal(data);
                } catch (err) {
                    console.error("JSON decode error:", err);
                }
            };
        });

        document.querySelectorAll(".btn-delete").forEach((btn) => {
            btn.onclick = () => deleteCitizen(btn.dataset.id);
        });
    }

    // ===== Pagination =====
    function renderPagination() {
        if (!paginationEl) return;
        if (!totalPages || totalPages <= 1) {
            paginationEl.innerHTML = "";
            return;
        }
        let html = "";
        for (let i = 1; i <= totalPages; i++) {
            html += `<a href="#" class="${
                i === currentPage ? "active" : ""
            }" data-page="${i}">${i}</a>`;
        }
        paginationEl.innerHTML = html;
        paginationEl.querySelectorAll("a").forEach((a) => {
            a.onclick = (ev) => {
                ev.preventDefault();
                loadCitizens(parseInt(a.dataset.page));
            };
        });
    }
    // ===== Animate counter numbers =====
    function animateCounter(el, target) {
        const duration = 800;
        const start = parseInt(el.textContent) || 0;
        const range = target - start;
        if (range === 0) return;

        let startTime = null;
        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            const progress = Math.min((timestamp - startTime) / duration, 1);
            el.textContent = Math.floor(start + range * progress);
            if (progress < 1) requestAnimationFrame(step);
            else el.textContent = target;
        }
        requestAnimationFrame(step);
    }

    // ===== Update all stat cards =====
    function updateStatCards(totals) {
        for (const key in totals) {
            const el = document.getElementById(`stat_${key}`);
            if (el) animateCounter(el, totals[key]);
        }
    }

    // ===== Load Citizens =====
    function loadCitizens(page = 1) {
        currentPage = page;
        const search = searchInput?.value.trim() || "";
        const type = filterType?.value || "";
        const params = new URLSearchParams({ page, search });
        if (type) params.append("type", type);

        // ===== Show loading placeholders =====
        if (tableBody) {
            tableBody.innerHTML = `<tr><td colspan="12" class="text-center py-4">
            <div class="spinner-border text-primary"></div>
            <div class="mt-2">Loading citizens…</div>
        </td></tr>`;
        }
        if (mobileCards) mobileCards.innerHTML = ""; // Clear mobile cards before rendering

        fetch(`fetch_citizens.php?${params.toString()}`)
            .then((r) => r.json())
            .then((json) => {
                if (!json || json.status !== "success") {
                    const msg = json?.message || "Failed to load citizens.";
                    if (tableBody) {
                        tableBody.innerHTML = `<tr><td colspan="12" class="text-center text-danger">${msg}</td></tr>`;
                    }
                    if (mobileCards) {
                        mobileCards.innerHTML = `<div class="col-12 text-center text-danger">${msg}</div>`;
                    }
                    return;
                }

                totalPages = json.total_pages || 1;
                const searchTerm = search;
                // ===== Update stat cards dynamically =====
                if (json.totals) {
                    updateStatCards(json.totals);
                }

                // ===== Render Table =====
                if (tableBody) {
                    tableBody.innerHTML = json.data.length
                        ? json.data
                              .map((c) => {
                                  const img = c.image_path
                                      ? `../${c.image_path}`
                                      : "../uploads/default.png";
                                  const fullName = `${c.first_name || ""} ${
                                      c.last_name || ""
                                  }`;
                                  return `
                        <tr>
                            <td><img src="${img}" style="width:42px;height:42px;object-fit:cover;border-radius:6px;"></td>
                            <td>${highlightText(c.citizen_id, searchTerm)}</td>
                            <td>${highlightText(fullName, searchTerm)}</td>
                            <td>${highlightText(c.phone || "", searchTerm)}</td>
                            <td>${highlightText(
                                c.citizen_type || "",
                                searchTerm
                            )}</td>
                            <td>${highlightText(
                                c.state_of_origin || "",
                                searchTerm
                            )}</td>
                            <td>${highlightText(
                                c.community_name || "",
                                searchTerm
                            )}</td>
                            <td>${highlightText(
                                c.village_name || "",
                                searchTerm
                            )}</td>
                            <td>${highlightText(
                                c.house_address || "",
                                searchTerm
                            )}</td>
                            <td>${highlightText(
                                c.created_by_name || "",
                                searchTerm
                            )}</td>
                            <td>${c.created_at || ""}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary btn-edit" title="Edit" 
                                    data-citizen='${JSON.stringify(c).replace(
                                        /'/g,
                                        "&apos;"
                                    )}'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success btn-issue-cert" title="Issue Certificate" 
                                    data-id="${
                                        c.id
                                    }" data-full="${fullName}" data-phone="${
                                      c.phone || ""
                                  }" 
                                    data-house="${
                                        c.house_address || ""
                                    }" data-community="${c.community_id || ""}" 
                                    data-village="${
                                        c.village_id || ""
                                    }" data-citizenid="${c.citizen_id || ""}" 
                                    data-bs-toggle="modal" data-bs-target="#poaModal">
                                    <i class="bi bi-award"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-delete" title="Delete" data-id="${
                                    c.id
                                }">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                              })
                              .join("")
                        : `<tr><td colspan="12" class="text-center text-muted py-4">No citizens found.</td></tr>`;
                }

                // ===== Render Mobile Cards =====
                if (mobileCards) {
                    mobileCards.innerHTML = json.data.length
                        ? json.data
                              .map((c) => {
                                  const img = c.image_path
                                      ? `../${c.image_path}`
                                      : "../uploads/default.png";
                                  const fullName = `${c.first_name || ""} ${
                                      c.last_name || ""
                                  }`;
                                  return `
                        <div class="col-12">
                            <div class="card showCard p-3 mb-2">
                                <div class="d-flex align-items-start gap-3">
                                    <img src="${img}" style="width:60px;height:60px;object-fit:cover;border-radius:8px;" class="me-3">
                                    <div class="flex-fill">
                                        <h6 class="mb-2">${highlightText(
                                            fullName,
                                            searchTerm
                                        )} 
                                            <small class="text-muted">(${highlightText(
                                                c.citizen_id,
                                                searchTerm
                                            )})</small>
                                        </h6>
                                        <div class="small text-muted">Phone: ${highlightText(
                                            c.phone || "-",
                                            searchTerm
                                        )}</div>
                                        <div class="small">Community: ${highlightText(
                                            c.community_name || "-",
                                            searchTerm
                                        )} • ${highlightText(
                                      c.village_name || "-",
                                      searchTerm
                                  )}</div>
                                        <div class="small">Type: ${highlightText(
                                            c.citizen_type || "-",
                                            searchTerm
                                        )} | State: ${highlightText(
                                      c.state_of_origin || "-",
                                      searchTerm
                                  )}</div>
                                        <div class="small mt-1">Created At: ${
                                            c.created_at || "-"
                                        }</div>
                                        <div class="d-flex gap-2 mt-2">
                                            <button class="btn btn-sm btn-outline-primary btn-edit" title="Edit"
                                                data-citizen='${JSON.stringify(
                                                    c
                                                ).replace(/'/g, "&apos;")}'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success btn-issue-cert" title="Issue Certificate"
                                                data-id="${
                                                    c.id
                                                }" data-full="${fullName}" data-phone="${
                                      c.phone || ""
                                  }"
                                                data-house="${
                                                    c.house_address || ""
                                                }" data-community="${
                                      c.community_id || ""
                                  }"
                                                data-village="${
                                                    c.village_id || ""
                                                }" data-citizenid="${
                                      c.citizen_id || ""
                                  }"
                                                data-bs-toggle="modal" data-bs-target="#poaModal">
                                                <i class="bi bi-award"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-delete" title="Delete" data-id="${
                                                c.id
                                            }">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                              })
                              .join("")
                        : `<div class="col-12 text-center text-muted py-3">No citizens found.</div>`;
                }

                initActionButtons();
                renderPagination();
            })
            .catch((err) => {
                console.error("loadCitizens error", err);
                if (tableBody)
                    tableBody.innerHTML = `<tr><td colspan="12" class="text-center text-danger">Error loading citizens.</td></tr>`;
                if (mobileCards)
                    mobileCards.innerHTML = `<div class="col-12 text-center text-danger">Error loading citizens.</div>`;
            });
    }
    function updateStatCards(totals) {
        if (!totals) return;

        const citizensEl = document.getElementById("stat_citizens");
        const indigenesEl = document.getElementById("stat_indigenes");
        const tenantsEl = document.getElementById("stat_tenants");

        if (citizensEl) {
            citizensEl.textContent = totals.citizens ?? 0;
            citizensEl.setAttribute("data-count", totals.citizens ?? 0);
        }
        if (indigenesEl) {
            indigenesEl.textContent = totals.indigenes ?? 0;
            indigenesEl.setAttribute("data-count", totals.indigenes ?? 0);
        }
        if (tenantsEl) {
            tenantsEl.textContent = totals.tenants ?? 0;
            tenantsEl.setAttribute("data-count", totals.tenants ?? 0);
        }
    }

    searchInput?.addEventListener("input", () => loadCitizens(1));
    filterType?.addEventListener("change", () => loadCitizens(1));

    window.loadCitizens = loadCitizens;
    window.deleteCitizen = deleteCitizen;

    loadCitizens(1);
});
