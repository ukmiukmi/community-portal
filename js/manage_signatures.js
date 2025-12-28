document.addEventListener("DOMContentLoaded", () => {
    const content = document.getElementById("communityContent");
    const select = document.getElementById("communitySelect");
    const csrfToken = document.getElementById("csrf_token")?.value || "";
    const roles = ["president", "secretary"];

    // -------------------- Toast helper --------------------
    function showToast(msg, type = "success") {
        if (!msg) return;
        Swal.fire({
            toast: true,
            position: "top-end",
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            icon: type,
            title: msg,
            background:
                type === "success"
                    ? "#0a501c"
                    : type === "error"
                    ? "#dc3545"
                    : type === "warning"
                    ? "#ffc107"
                    : "#334155",
            color: "#fff",
            customClass: { container: "swal2-container", popup: "swal2-toast" },
            didOpen: (toast) => {
                toast.addEventListener("mouseenter", Swal.stopTimer);
                toast.addEventListener("mouseleave", Swal.resumeTimer);
            },
        });
    }

    // -------------------- Fetch community --------------------
    async function loadCommunity(cid) {
        content.innerHTML = '<div class="loader"></div>';
        const fd = new FormData();
        fd.append("ajax_action", "fetch_signatures");
        fd.append("community_id", cid);
        fd.append("csrf_token", csrfToken);

        try {
            const res = await fetch(location.href, {
                method: "POST",
                body: fd,
            });
            const j = await res.json();
            if (j.status !== "success") {
                content.innerHTML =
                    '<div style="padding:20px;color:#c00">Error loading signatures</div>';
                showToast(j.message || "Failed to load signatures", "error");
                return;
            }
            renderCommunity(cid, j.data || {});
        } catch (e) {
            console.error("Load community error:", e);
            content.innerHTML =
                '<div style="padding:20px;color:#c00">Network error</div>';
            showToast("Network error", "error");
        }
    }

    // -------------------- Render community --------------------
    function renderCommunity(cid, data) {
        content.innerHTML = `
        <div class="sig-grid">
            ${roles
                .map(
                    (role) => `
            <div class="sig-card" data-role="${role}" data-cid="${cid}">
                <div class="sig-role">${
                    role.charAt(0).toUpperCase() + role.slice(1)
                }</div>
                <div class="sig-preview" id="preview_${role}">
                    ${
                        data[role]
                            ? `<div style="position:relative; width:100%; height:100%;">
                                <img src="../${
                                    data[role].file_path
                                }?t=${Date.now()}" style="width:100%; height:100%; object-fit:contain;">
                                <div class='updated'>Updated: ${
                                    data[role].updated_at
                                }</div>
                                <span class="remove-preview">×</span>
                              </div>`
                            : `<div style='color:#64748b'>No signature</div>`
                    }
                </div>
                <div class="drop-zone" data-role="${role}" data-cid="${cid}">
                    Drag & drop or click<br><small>PNG/JPG only</small>
                    <input type="file" id="file_${role}" accept="image/*" hidden>
                </div>
                <div style="margin-top:8px">
                    <button class="btn upload-role" data-role="${role}" data-cid="${cid}">Upload</button>
                    <button class="btn delete" data-role="${role}" data-cid="${cid}" style="background:#d33">Delete</button>
                </div>
                <div style="margin-top:8px">
                    <input type="color" id="color_${role}" value="#0a501c">
                    <canvas class="digital-canvas" id="canvas_${role}" style="width:100%;height:120px;border:1px solid #e6e6e6;border-radius:6px;margin-top:8px"></canvas>
                    <div style="margin-top:6px">
                        <button class="btn save-digital" data-role="${role}" data-cid="${cid}">Save Digital</button>
                        <button class="btn clear-digital" data-role="${role}" style="background:#777">Clear</button>
                    </div>
                </div>
            </div>`
                )
                .join("")}
        </div>

        <div class="upload-all">
            <strong>Upload Both</strong>
            <div class="preview-pair" id="pairPreview" style="margin-top:8px"></div>
            ${roles
                .map(
                    (role) => `
            <div class="drop-zone" data-role="${role}" data-cid="${cid}">
                Drag & drop or click to select ${role}<br><small>PNG/JPG only</small>
                <input type="file" id="file_all_${role}" accept="image/*" hidden>
            </div>`
                )
                .join("")}
            <button class="btn" id="uploadBothBtn" data-cid="${cid}" style="margin-top:8px">Upload Both</button>
        </div>`;

        bindEvents();
    }

    // -------------------- Bind events --------------------
    function bindEvents() {
        // ----- Helper to show single preview -----
        function showSinglePreview(file, container, input, label = "Preview") {
            const reader = new FileReader();
            reader.onload = (e) => {
                container.innerHTML = `
                    <div style="position:relative; width:100%; height:100%;">
                        <img src="${e.target.result}" style="width:100%; height:100%; object-fit:contain;">
                        <div class='updated'>${label}</div>
                        <span class="remove-preview">×</span>
                    </div>`;
                container.querySelector(".remove-preview").onclick = () => {
                    input.value = "";
                    container.innerHTML = `<div style='color:#64748b'>No signature</div>`;
                };
            };
            reader.readAsDataURL(file);
        }

        // ----- Single Role Drag & Drop + Upload + Remove -----
        roles.forEach((role) => {
            const input = document.getElementById(`file_${role}`);
            const zone = input.parentElement;
            const preview = document.getElementById(`preview_${role}`);

            // Remove button for preloaded image
            const removeBtn = preview.querySelector(".remove-preview");
            if (removeBtn)
                removeBtn.onclick = () => {
                    input.value = "";
                    preview.innerHTML = `<div style='color:#64748b'>No signature</div>`;
                };

            zone.onclick = () => input.click();
            zone.ondragover = (e) => {
                e.preventDefault();
                zone.classList.add("dragover");
            };
            zone.ondragleave = () => zone.classList.remove("dragover");
            zone.ondrop = (e) => {
                e.preventDefault();
                zone.classList.remove("dragover");
                if (e.dataTransfer.files[0]) {
                    input.files = e.dataTransfer.files;
                    showSinglePreview(input.files[0], preview, input);
                }
            };
            input.onchange = () =>
                input.files[0] &&
                showSinglePreview(input.files[0], preview, input);
        });

        // ----- Upload Single -----
        document.querySelectorAll(".upload-role").forEach((btn) => {
            btn.onclick = async () => {
                const role = btn.dataset.role;
                const cid = btn.dataset.cid;
                const input = document.getElementById(`file_${role}`);
                if (!input.files.length)
                    return showToast("Select a file", "error");

                const fd = new FormData();
                fd.append("ajax_action", "upload_role");
                fd.append("role", role);
                fd.append("community_id", cid);
                fd.append("signature", input.files[0]);
                fd.append("csrf_token", csrfToken);

                try {
                    const res = await fetch(location.href, {
                        method: "POST",
                        body: fd,
                    });
                    if (!res.ok) throw new Error("HTTP error " + res.status);
                    const j = await res.json();
                    if (j.status === "success") {
                        showSinglePreview(
                            input.files[0],
                            document.getElementById(`preview_${role}`),
                            input,
                            "Updated: " + j.updated
                        );
                        showToast("Uploaded successfully!", "success");
                    } else showToast(j.message || "Upload failed", "error");
                } catch (e) {
                    console.error("Single upload error:", e);
                    showToast("Network error", "error");
                }
            };
        });

        // ----- Delete Signature -----
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
                    if (!result.isConfirmed) return;
                    const fd = new FormData();
                    fd.append("ajax_action", "delete_signature");
                    fd.append("role", btn.dataset.role);
                    fd.append("community_id", btn.dataset.cid);
                    fd.append("csrf_token", csrfToken);

                    try {
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
                            showToast("Deleted successfully!", "success");
                        } else showToast(j.message || "Delete failed", "error");
                    } catch (e) {
                        console.error("Delete error:", e);
                        showToast("Network error", "error");
                    }
                });
            };
        });

        // ----- Digital Canvas -----
        document.querySelectorAll(".digital-canvas").forEach((canvas) => {
            const ctx = canvas.getContext("2d");
            const role = canvas.id.replace("canvas_", "");
            const colorPicker = document.getElementById(`color_${role}`);
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
            let drawing = false,
                lastPos = { x: 0, y: 0 };

            function pointerPos(e) {
                const rect = canvas.getBoundingClientRect();
                return { x: e.clientX - rect.left, y: e.clientY - rect.top };
            }

            canvas.onpointerdown = (e) => {
                drawing = true;
                lastPos = pointerPos(e);
                ctx.beginPath();
                ctx.moveTo(lastPos.x, lastPos.y);
            };
            canvas.onpointermove = (e) => {
                if (!drawing) return;
                const p = pointerPos(e);
                ctx.strokeStyle = colorPicker.value;
                ctx.lineWidth = 2;
                ctx.lineCap = "round";
                ctx.lineJoin = "round";
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                lastPos = p;
            };
            canvas.onpointerup = canvas.onpointerleave = () =>
                (drawing = false);

            document.querySelector(
                `.clear-digital[data-role="${role}"]`
            ).onclick = () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                showToast("Cleared", "success");
            };

            document.querySelector(
                `.save-digital[data-role="${role}"]`
            ).onclick = async () => {
                const fd = new FormData();
                fd.append("ajax_action", "save_digital");
                fd.append("role", role);
                fd.append(
                    "community_id",
                    canvas.closest(".sig-card").dataset.cid
                );
                fd.append("data", canvas.toDataURL("image/png"));
                fd.append("csrf_token", csrfToken);

                try {
                    const res = await fetch(location.href, {
                        method: "POST",
                        body: fd,
                    });
                    if (!res.ok) throw new Error("HTTP error " + res.status);
                    const j = await res.json();
                    if (j.status === "success") {
                        const previewContainer = document.getElementById(
                            `preview_${role}`
                        );
                        previewContainer.innerHTML = `
                            <div style="position:relative; width:100%; height:100%;">
                                <img src="../${
                                    j.file
                                }?t=${Date.now()}" style="width:100%; height:100%; object-fit:contain;">
                                <div class='updated'>Updated: ${j.updated}</div>
                                <span class="remove-preview">×</span>
                            </div>`;
                        previewContainer.querySelector(
                            ".remove-preview"
                        ).onclick = () => {
                            document.getElementById(`file_${role}`).value = "";
                            previewContainer.innerHTML = `<div style='color:#64748b'>No signature</div>`;
                        };
                        showToast("Digital signature saved", "success");
                    } else showToast(j.message || "Save failed", "error");
                } catch (e) {
                    console.error("Digital save error:", e);
                    showToast("Network error", "error");
                }
            };
        });

        // ----- Pair Upload Drag & Drop + Preview -----
        roles.forEach((role) => {
            const input = document.getElementById(`file_all_${role}`);
            const zone = input.parentElement;
            zone.onclick = () => input.click();
            zone.ondragover = (e) => {
                e.preventDefault();
                zone.classList.add("dragover");
            };
            zone.ondragleave = () => zone.classList.remove("dragover");
            zone.ondrop = (e) => {
                e.preventDefault();
                zone.classList.remove("dragover");
                if (e.dataTransfer.files[0]) {
                    input.files = e.dataTransfer.files;
                    showPairPreview();
                }
            };
            input.onchange = showPairPreview;
        });

        function showPairPreview() {
            const pairPreview = document.getElementById("pairPreview");
            pairPreview.innerHTML = "";
            roles.forEach((role) => {
                const input = document.getElementById(`file_all_${role}`);
                const file = input?.files?.[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (e) => {
                    const wrapper = document.createElement("div");
                    wrapper.style.position = "relative";
                    wrapper.style.display = "inline-block";
                    const img = document.createElement("img");
                    img.src = e.target.result;
                    img.style.maxWidth = "160px";
                    img.style.maxHeight = "90px";
                    img.style.margin = "5px";
                    img.style.border = "1px solid #cbd5e1";
                    img.style.borderRadius = "10px";
                    const removeBtn = document.createElement("span");
                    removeBtn.innerText = "×";
                    removeBtn.style.cssText = `
                        position:absolute; top:4px; right:4px;
                        background:#dc3545; color:#fff; border-radius:50%;
                        width:20px; height:20px; line-height:20px;
                        text-align:center; font-weight:bold; cursor:pointer;
                    `;
                    removeBtn.onclick = () => {
                        input.value = "";
                        wrapper.remove();
                    };
                    wrapper.appendChild(img);
                    wrapper.appendChild(removeBtn);
                    pairPreview.appendChild(wrapper);
                };
                reader.readAsDataURL(file);
            });
        }

        // ----- Upload Both -----
        const uploadBothBtn = document.getElementById("uploadBothBtn");
        if (uploadBothBtn)
            uploadBothBtn.onclick = async () => {
                const cid = uploadBothBtn.dataset.cid;
                const fd = new FormData();
                fd.append("ajax_action", "upload_all");
                fd.append("community_id", cid);
                fd.append("csrf_token", csrfToken);
                roles.forEach((role) => {
                    const f = document.getElementById(`file_all_${role}`)
                        ?.files?.[0];
                    if (f) fd.append(`signature_${role}`, f);
                });
                try {
                    const res = await fetch(location.href, {
                        method: "POST",
                        body: fd,
                    });
                    const j = await res.json();
                    if (j.status === "success") {
                        roles.forEach((role) => {
                            if (j.result[role]?.ok) {
                                document.getElementById(
                                    `preview_${role}`
                                ).innerHTML = `
                                    <img src="../${j.result[role].file}" style="max-width:100%;max-height:100%">
                                    <div class='updated'>Updated: ${j.result[role].updated}</div>`;
                            }
                        });
                        showToast("Uploaded both signatures", "success");
                        showPairPreview();
                    } else showToast("Upload failed", "error");
                } catch (e) {
                    console.error("Upload both error:", e);
                    showToast("Network error", "error");
                }
            };
    }

    // -------------------- Initialize --------------------
    if (select.options.length > 1) {
        select.selectedIndex = 1;
        loadCommunity(select.value);
    }
    select.onchange = () =>
        select.value ? loadCommunity(select.value) : (content.innerHTML = "");
});
