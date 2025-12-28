// -------------------- Select elements -------------------- //
const content = document.getElementById("communityContent");
const select = document.getElementById("communitySelect");

// -------------------- Load initial community -------------------- //
window.addEventListener("DOMContentLoaded", () => {
    if (select.options.length > 1) {
        select.selectedIndex = 1;
        loadCommunity(select.value);
    }
});

select.addEventListener("change", () => {
    if (select.value) loadCommunity(select.value);
    else content.innerHTML = "";
});

// -------------------- Loader & Alert -------------------- //
function showLoader() {
    content.innerHTML = '<div class="loader"></div>';
}

function showAlert(msg, type = "success") {
    Swal.fire({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 2000,
        icon: type,
        title: msg,
    });
}

// -------------------- Load community data -------------------- //
async function loadCommunity(cid) {
    showLoader();
    const fd = new FormData();
    fd.append("ajax_action", "fetch_signatures");
    fd.append("community_id", cid);
    const res = await fetch(location.href, { method: "POST", body: fd });
    const j = await res.json();
    if (j.status !== "success")
        return (content.innerHTML = "Error loading data");
    const d = j.data || {};

    content.innerHTML = `<div class="sig-grid">
        ${["president", "secretary"]
            .map(
                (role) => `
        <div class="sig-card" data-role="${role}" data-cid="${cid}">
            <div class="sig-role">${
                role.charAt(0).toUpperCase() + role.slice(1)
            }</div>
            <div class="sig-preview" id="preview_${role}">
                ${
                    d[role]
                        ? `<img src="../${
                              d[role].file_path
                          }?t=${Date.now()}"><div class="updated">Updated: ${
                              d[role].updated_at
                          }</div>`
                        : '<div style="color:#64748b;">No signature</div>'
                }
            </div>
            <div class="drop-zone" data-role="${role}" data-cid="${cid}">
                Drag & drop or click<br><small>PNG/JPG</small>
                <input type="file" id="file_${role}" accept="image/*" hidden>
            </div>
            <button class="btn upload-role" data-role="${role}" data-cid="${cid}">Upload ${role}</button>
            <button class="btn delete" data-role="${role}" data-cid="${cid}">Delete</button>
            <div class="progress-bar"></div>
        </div>`
            )
            .join("")}
    </div>

    <h3 style="margin-top:20px;">Community Images & Motto</h3>
    <div class="multi-upload">
        ${["logo", "coat_of_arms", "stamp", "motto"]
            .map(
                (type) => `
        <div class="upload-card" data-type="${type}">
            <div class="upload-label">${type
                .replace(/_/g, " ")
                .toUpperCase()}</div>
            <div class="drop-zone" data-type="${type}">
                ${
                    type === "motto"
                        ? `<textarea id="file_all_motto" placeholder="Enter motto">${
                              d.images?.motto || ""
                          }</textarea>`
                        : `Drag & drop or click<br><small>PNG/JPG</small><input type="file" id="file_all_${type}" accept="image/*" hidden>`
                }
            </div>
            <div class="preview" id="preview_${type}">
                ${
                    type !== "motto"
                        ? d.images && d.images[type]
                            ? `<img src="../${
                                  d.images[type]
                              }?t=${Date.now()}"><div class="updated"><i class="fa fa-check-circle" style="color:#16a34a;"></i> Updated</div>`
                            : '<div style="color:#64748b;">No image</div>'
                        : `<div style="font-size:14px;color:#0a501c;">${
                              d.images?.motto || "No motto set"
                          }</div>`
                }
            </div>
            <div class="progress-bar"></div>
            <div class="card-badge success" style="display:none;"></div>
        </div>`
            )
            .join("")}
        <button class="btn" id="uploadAllBtn" data-cid="${cid}">Upload All Images & Motto</button>
    </div>`;

    bindEvents();
}

// -------------------- Bind events -------------------- //
function bindEvents() {
    // Drag & drop + input preview
    document.querySelectorAll(".drop-zone").forEach((zone) => {
        const type = zone.dataset.type || zone.dataset.role;
        const input = zone.querySelector("input,textarea");
        const preview = document.getElementById(`preview_${type}`);

        zone.addEventListener("click", () => input.click());
        zone.addEventListener("dragover", (e) => {
            e.preventDefault();
            zone.classList.add("dragover");
        });
        zone.addEventListener("dragleave", () =>
            zone.classList.remove("dragover")
        );
        zone.addEventListener("drop", (e) => {
            e.preventDefault();
            zone.classList.remove("dragover");
            if (e.dataTransfer.files[0]) {
                input.files = e.dataTransfer.files;
                previewImage(e.dataTransfer.files[0], preview);
            }
        });

        if (input && input.type === "file") {
            input.addEventListener("change", () => {
                if (input.files[0]) previewImage(input.files[0], preview);
            });
        }
    });

    // Upload single role (signature)
    document.querySelectorAll(".upload-role").forEach((btn) => {
        btn.onclick = async () => {
            const role = btn.dataset.role,
                cid = btn.dataset.cid,
                input = document.getElementById(`file_${role}`);
            if (!input.files.length) return showAlert("Select a file", "error");

            const fd = new FormData();
            fd.append("ajax_action", "upload_role");
            fd.append("role", role);
            fd.append("community_id", cid);
            fd.append("signature", input.files[0]);

            const card = document.querySelector(
                `.sig-card[data-role="${role}"]`
            );
            const progress = card.querySelector(".progress-bar");

            progress.style.width = "0%";
            progress.style.display = "block";
            let width = 0;
            const interval = setInterval(() => {
                if (width < 70) {
                    width += 10;
                    progress.style.width = width + "%";
                }
            }, 50);

            const res = await fetch(location.href, {
                method: "POST",
                body: fd,
            });
            const j = await res.json();
            clearInterval(interval);
            progress.style.width = "100%";
            setTimeout(() => (progress.style.display = "none"), 300);

            if (j.status === "success") {
                const preview = document.getElementById(`preview_${role}`);
                preview.innerHTML = `<img src="../${
                    j.file
                }?t=${Date.now()}" alt="${role} signature">
                                     <span class="updated-check"><i class="fa fa-check-circle" style="color:#16a34a;"></i> Updated</span>
                                     <div class="updated">Updated: ${
                                         j.updated
                                     }</div>`;
            } else showAlert(j.message, "error");
        };
    });

    // Delete signature
    document.querySelectorAll(".delete").forEach((btn) => {
        btn.onclick = () => {
            Swal.fire({
                title: "Delete this signature?",
                text: "This cannot be undone.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Yes, delete",
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append("ajax_action", "delete_signature");
                    fd.append("role", btn.dataset.role);
                    fd.append("community_id", btn.dataset.cid);

                    const res = await fetch(location.href, {
                        method: "POST",
                        body: fd,
                    });
                    const j = await res.json();
                    if (j.status === "success") {
                        document.getElementById(
                            `preview_${btn.dataset.role}`
                        ).innerHTML =
                            "<div style='color:#64748b;'>No signature</div>";
                        showAlert("Deleted");
                    } else showAlert(j.message, "error");
                }
            });
        };
    });

    // Upload all images + motto
    const uploadAll = document.getElementById("uploadAllBtn");
    if (uploadAll) {
        uploadAll.onclick = async () => {
            const cid = uploadAll.dataset.cid;
            const fd = new FormData();
            fd.append("ajax_action", "upload_all_images");
            fd.append("community_id", cid);
            let hasFile = false;

            ["logo", "coat_of_arms", "stamp", "motto"].forEach((type) => {
                if (type === "motto") {
                    const input = document.getElementById("file_all_motto");
                    if (input.value.trim() !== "") {
                        fd.append("motto", input.value.trim());
                        hasFile = true;
                    }
                    return;
                }
                const input = document.getElementById(`file_all_${type}`);
                if (input.files[0]) {
                    fd.append(`image_${type}`, input.files[0]);
                    hasFile = true;
                }
            });
            if (!hasFile) return showAlert("Select at least one file", "error");

            ["logo", "coat_of_arms", "stamp", "motto"].forEach((type) => {
                const card = document.querySelector(
                    `.upload-card[data-type="${type}"]`
                );
                const progress = card.querySelector(".progress-bar");
                progress.style.width = "0%";
                progress.style.display = "block";
                let i = 0;
                const interval = setInterval(() => {
                    if (i < 70) {
                        i += 10;
                        progress.style.width = i + "%";
                    }
                }, 50);
                card.dataset.progressInterval = interval;
            });

            const res = await fetch(location.href, {
                method: "POST",
                body: fd,
            });
            const j = await res.json();

            ["logo", "coat_of_arms", "stamp", "motto"].forEach((type) => {
                const card = document.querySelector(
                    `.upload-card[data-type="${type}"]`
                );
                const preview = card.querySelector(".preview");
                const progress = card.querySelector(".progress-bar");
                clearInterval(card.dataset.progressInterval);
                progress.style.width = "100%";
                setTimeout(() => (progress.style.display = "none"), 300);

                const badge = card.querySelector(".card-badge");
                badge.innerHTML =
                    '<i class="fa fa-check-circle" style="font-size:20px;"></i>';
                badge.style.display = "block";
                setTimeout(() => (badge.style.display = "none"), 1500);

                if (type === "motto") {
                    if (fd.get("motto")) {
                        preview.innerHTML = `<div style="font-size:14px;color:#0a501c;">${fd.get(
                            "motto"
                        )}</div>`;
                    }
                } else if (j.result[type]?.file) {
                    preview.innerHTML = `<img src="../${
                        j.result[type].file
                    }?t=${Date.now()}"><div class="updated"><i class="fa fa-check-circle" style="color:#16a34a;"></i> Updated</div>`;
                }
            });

            if (j.status !== "success") showAlert("Error", "error");
        };
    }
}

// -------------------- Preview image -------------------- //
function previewImage(file, container) {
    const r = new FileReader();
    r.onload = (e) => {
        container.innerHTML = `<img src="${e.target.result}"><div style="font-size:12px;color:#64748b;">Preview</div>`;
    };
    r.readAsDataURL(file);
}
