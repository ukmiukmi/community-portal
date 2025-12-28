document.addEventListener("DOMContentLoaded", () => {
    let currentPage = 1;
    let sortColumn = "deleted_at";
    let sortOrder = "desc";
    let selectAllAcrossPages = false;
    let selectedIds = new Set();

    const tableBodyDesktop = document.getElementById("tableBodyDesktop");
    const tableBodyMobile = document.getElementById("tableBodyMobile");
    const templateMobile = document.getElementById(
        "tableRowTemplateMobile"
    )?.content;
    const filterForm = document.getElementById("filterForm");
    const searchInput = document.getElementById("searchInput");
    const selectAll = document.getElementById("selectAll");
    const selectAllPagesCheckbox = document.getElementById("selectAllPages");
    const bulkDeleteBtn = document.getElementById("bulk-delete");
    const bulkDownloadBtn = document.getElementById("bulk-download");
    const bulkZipBtn = document.getElementById("bulk-zip");
    const exportCsvBtn = document.querySelector('a[href*="export_csv"]');

    const signatureModal = document.getElementById("signatureModal");
    const modalImage = document.getElementById("modalImage");
    const modalClose = document.getElementById("modalClose");

    // ------------------ FETCH & RENDER ------------------
    const fetchData = (page = 1) => {
        currentPage = page;
        const formData = new FormData(filterForm);
        formData.append("action", "fetch");
        formData.append("page", page);
        formData.append("sort_column", sortColumn);
        formData.append("sort_order", sortOrder);
        formData.append("search", searchInput.value);

        fetch("deleted_signatures_log.php", { method: "POST", body: formData })
            .then((res) => res.json())
            .then((res) => {
                if (res.status !== "success") return;

                if (tableBodyDesktop)
                    tableBodyDesktop.innerHTML = res.data.table;
                renderMobileCards(res.data.stats.rows);
                attachImageModal();
                renderPagination(res.data.pagination);
                updateStats(res.data.stats);

                bindRestoreButtons();
                updateCheckboxesAfterFetch();
            });
    };

    // ------------------ MOBILE CARDS ------------------
    const renderMobileCards = (rows) => {
        if (!tableBodyMobile || !templateMobile) return;
        tableBodyMobile.innerHTML = "";

        rows.forEach((row) => {
            const card = templateMobile.cloneNode(true);
            card.querySelector(".record-id").textContent = `ID: ${row.id}`;
            card.querySelector(".record-role").textContent =
                row.role.charAt(0).toUpperCase() + row.role.slice(1);
            card.querySelector(".record-community").textContent =
                row.community_name ?? "Unknown";
            card.querySelector(
                ".record-deleted-by"
            ).textContent = `Deleted By: ${row.deleted_by}`;
            card.querySelector(
                ".record-reason"
            ).textContent = `Reason: ${row.reason}`;
            card.querySelector(
                ".record-deleted-at"
            ).textContent = `Deleted At: ${row.deleted_at}`;
            card.querySelector(".record-restored-at").textContent =
                row.restored_at
                    ? `Restored At: ${row.restored_at}`
                    : "Restored At: —";

            const img = card.querySelector("img.thumb");
            if (img && row.file_path) {
                img.src = `../${row.file_path}`;
                img.dataset.full = `../${row.file_path}`;
            } else if (img) img.remove();

            const restoreBtn = card.querySelector(".restore-btn");
            if (restoreBtn) {
                if (row.restored_at || !row.file_path)
                    restoreBtn.disabled = true;
                else {
                    restoreBtn.dataset.id = row.id;
                    restoreBtn.addEventListener("click", handleRestore);
                }
            }

            const cb = card.querySelector(".recordCheckbox");
            if (cb)
                cb.checked =
                    selectAllAcrossPages || selectedIds.has(String(row.id));
            cb?.addEventListener("change", handleCheckboxChange);

            tableBodyMobile.appendChild(card);
        });
    };

    // ------------------ PAGINATION ------------------
    const renderPagination = (html) => {
        const paginationWrap = document.getElementById("paginationWrapDesktop");
        if (!paginationWrap) return;

        paginationWrap.innerHTML = html;
        paginationWrap.querySelectorAll("li").forEach((li) => {
            li.addEventListener("click", () => {
                currentPage = li.dataset.page;
                fetchData(currentPage);
            });
        });
    };

    // ------------------ STATS ------------------
    const updateStats = (stats) => {
        if (document.getElementById("totalDeletions"))
            document.getElementById("totalDeletions").textContent =
                stats.totalDeletions;
        if (document.getElementById("totalRestores"))
            document.getElementById("totalRestores").textContent =
                stats.totalRestores;

        if (document.getElementById("topCommunities")) {
            const list = document.getElementById("topCommunities");
            list.innerHTML = "";
            stats.topCommunities.forEach((item) => {
                let li = document.createElement("li");
                li.textContent = `${item.name} (${item.total})`;
                list.appendChild(li);
            });
        }
    };

    // ------------------ RESTORE ------------------
    const handleRestore = (e) => {
        const id = e.target.dataset.id;
        if (!id) return;

        Swal.fire({
            title: "Restore Signature?",
            text: "This will return the signature to its original location.",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#2563eb",
            cancelButtonColor: "#6b7280",
            confirmButtonText: "Yes, restore",
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("deleted_signatures_log.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: `restore_id=${id}`,
                })
                    .then((res) => res.json())
                    .then((res) => {
                        if (res.status === "success") {
                            selectedIds.delete(id);
                            fetchData(currentPage);
                            Swal.fire(
                                "Restored!",
                                "Signature was restored successfully.",
                                "success"
                            );
                        } else {
                            Swal.fire(
                                "Error",
                                res.message || "Failed to restore.",
                                "error"
                            );
                        }
                    });
            }
        });
    };

    const bindRestoreButtons = () => {
        document
            .querySelectorAll("table .restore-btn")
            .forEach((btn) => btn.addEventListener("click", handleRestore));
    };

    // ------------------ MODAL ------------------
    const openModal = (src) => {
        if (!signatureModal || !modalImage) return;
        modalImage.src = src;
        signatureModal.classList.remove("hidden");
        requestAnimationFrame(() => signatureModal.classList.add("show"));
    };

    const closeModal = () => {
        signatureModal.classList.remove("show");
        setTimeout(() => signatureModal.classList.add("hidden"), 250);
    };

    modalClose?.addEventListener("click", closeModal);
    signatureModal?.addEventListener("click", (e) => {
        if (e.target === signatureModal) closeModal();
    });

    const attachImageModal = () => {
        document.querySelectorAll("img.thumb").forEach((img) => {
            img.replaceWith(img.cloneNode(true));
        });
        document.querySelectorAll("img.thumb").forEach((img) => {
            img.addEventListener("click", () => openModal(img.dataset.full));
        });
    };

    // ------------------ CHECKBOXES ------------------
    const handleCheckboxChange = (e) => {
        const id = e.target.value;
        if (!id) return;
        if (selectAllAcrossPages) return;

        if (e.target.checked) selectedIds.add(id);
        else selectedIds.delete(id);

        updateCheckboxStates();
    };

    const updateCheckboxStates = () => {
        if (selectAll) {
            const allOnPage = [
                ...document.querySelectorAll(
                    "#tableBodyDesktop .recordCheckbox, #tableBodyMobile .recordCheckbox"
                ),
            ].every((cb) => cb.checked);
            selectAll.checked = allOnPage;
            selectAll.disabled = selectAllAcrossPages;
        }
        if (selectAllPagesCheckbox)
            selectAllPagesCheckbox.checked = selectAllAcrossPages;
    };

    if (selectAll) {
        selectAll.addEventListener("change", () => {
            if (selectAllAcrossPages) return;
            const checked = selectAll.checked;
            document
                .querySelectorAll(
                    "#tableBodyDesktop .recordCheckbox, #tableBodyMobile .recordCheckbox"
                )
                .forEach((cb) => {
                    cb.checked = checked;
                    const id = cb.value;
                    if (checked) selectedIds.add(id);
                    else selectedIds.delete(id);
                });
            updateCheckboxStates();
        });
    }

    if (selectAllPagesCheckbox) {
        selectAllPagesCheckbox.addEventListener("change", () => {
            selectAllAcrossPages = selectAllPagesCheckbox.checked;
            document
                .querySelectorAll(
                    "#tableBodyDesktop .recordCheckbox, #tableBodyMobile .recordCheckbox"
                )
                .forEach((cb) => (cb.checked = selectAllAcrossPages));
            if (selectAllAcrossPages) selectedIds.clear();
            updateCheckboxStates();
        });
    }

    const updateCheckboxesAfterFetch = () => {
        document
            .querySelectorAll(
                "#tableBodyDesktop .recordCheckbox, #tableBodyMobile .recordCheckbox"
            )
            .forEach((cb) => {
                cb.checked = selectAllAcrossPages || selectedIds.has(cb.value);
            });
        updateCheckboxStates();
    };

    const getSelectedIds = () => {
        if (selectAllAcrossPages) return ["all"];
        return Array.from(
            document.querySelectorAll(
                "#tableBodyDesktop .recordCheckbox:checked, #tableBodyMobile .recordCheckbox:checked"
            )
        ).map((cb) => cb.value);
    };

    // ------------------ BULK ACTIONS ------------------
    const handleBulkAction = async (action) => {
        const ids = getSelectedIds();
        if (!ids.length)
            return Swal.fire({
                icon: "warning",
                title: "No items selected",
                text: "Please select at least one record.",
            });

        let swalText = "";
        if (action === "delete")
            swalText = `${
                ids[0] === "all" ? "All records" : ids.length + " record(s)"
            } will be permanently deleted.`;
        else if (action === "download")
            swalText = `${
                ids[0] === "all" ? "All files" : ids.length + " file(s)"
            } will be downloaded.`;
        else if (action === "zip")
            swalText = `${
                ids[0] === "all" ? "All files" : ids.length + " file(s)"
            } will be included in the ZIP.`;
        else if (action === "csv")
            swalText = `${
                ids[0] === "all" ? "All records" : ids.length + " record(s)"
            } will be exported to CSV.`;

        const confirmed = await Swal.fire({
            title: action.charAt(0).toUpperCase() + action.slice(1),
            text: swalText,
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: action === "delete" ? "#dc2626" : "#2563eb",
        });
        if (!confirmed.isConfirmed) return;

        const payload = new FormData();
        ids.forEach((id) =>
            payload.append(action === "delete" ? "delete_ids[]" : "ids[]", id)
        );
        if (selectAllAcrossPages) payload.append("all_pages", "1");

        if (action === "delete") {
            fetch("deleted_signatures_log.php", {
                method: "POST",
                body: payload,
            })
                .then((r) => r.json())
                .then((r) => {
                    if (r.status === "success") {
                        selectAllAcrossPages = false;
                        selectedIds.clear();
                        if (selectAllPagesCheckbox)
                            selectAllPagesCheckbox.checked = false;
                        fetchData(currentPage);
                        Swal.fire(
                            "Deleted!",
                            "Selected records removed.",
                            "success"
                        );
                    } else
                        Swal.fire(
                            "Error",
                            r.message || "Delete failed.",
                            "error"
                        );
                })
                .catch(() => Swal.fire("Error", "Request failed.", "error"));
        }

        if (action === "download") {
            payload.append("action", "download");
            const res = await fetch("deleted_signatures_log.php", {
                method: "POST",
                body: payload,
            });
            const data = await res.json();
            if (data.status === "success" && data.files?.length) {
                data.files.forEach((file) => {
                    const a = document.createElement("a");
                    a.href = file;
                    a.download = file.split("/").pop();
                    a.click();
                });
            } else
                Swal.fire("Error", data.message || "Download failed.", "error");
        }

        if (action === "zip" || action === "csv") {
            const query = new URLSearchParams([
                ...payload.entries(),
            ]).toString();
            window.open(
                `deleted_signatures_log.php?action=${action}&${query}`,
                "_blank"
            );
        }
    };

    bulkDeleteBtn?.addEventListener("click", () => handleBulkAction("delete"));
    bulkDownloadBtn?.addEventListener("click", () =>
        handleBulkAction("download")
    );
    bulkZipBtn?.addEventListener("click", () => handleBulkAction("zip"));
    exportCsvBtn?.addEventListener("click", (e) => {
        e.preventDefault();
        handleBulkAction("csv");
    });

    // ------------------ FILTER / SEARCH ------------------
    filterForm?.addEventListener("change", () => fetchData(1));
    searchInput?.addEventListener("input", () => fetchData(1));

    // ------------------ SORTING ------------------
    document.querySelectorAll("#deletedTable th[data-sort]").forEach((th) => {
        th.addEventListener("click", () => {
            const col = th.dataset.sort;
            if (sortColumn === col)
                sortOrder = sortOrder === "asc" ? "desc" : "asc";
            else {
                sortColumn = col;
                sortOrder = "asc";
            }
            fetchData(currentPage);
        });
    });

    fetchData();
});
