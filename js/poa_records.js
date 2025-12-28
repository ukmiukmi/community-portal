document.addEventListener("DOMContentLoaded", () => {

    // -------------------- DOM CACHE --------------------
    const searchInput = document.getElementById("searchInput");
    const filterCommunity = document.getElementById("filterCommunity");
    const filterMonth = document.getElementById("filterMonth");
    const filterYear = document.getElementById("filterYear");

    const tableBodyDesktop = document.getElementById("tableBodyDesktop");
    const tableBodyMobile = document.getElementById("tableBodyMobile");
    const paginationWrap = document.getElementById("paginationWrapDesktop");

    const totalCertificates = document.getElementById("totalCertificates");
    const totalCertificatesMonth = document.getElementById("totalCertificatesMonth");
    const totalCertificatesYear = document.getElementById("totalCertificatesYear");
    const totalAmount = document.getElementById("totalAmount");

    const bulkDelete = document.getElementById("bulkDelete");
    const bulkDownload = document.getElementById("bulkDownload");
    const bulkZip = document.getElementById("bulkZip");
    const exportCSV = document.getElementById("exportCSV");

    const selectAllHeader = document.getElementById("selectAllHeader");
    const selectAll = document.getElementById("selectAll");
    const selectAllPages = document.getElementById("selectAllPages");

    let currentPage = 1;
    let debounceTimer = null;


    // -------------------- DEBOUNCE --------------------
    const debounce = (func, delay = 400) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(func, delay);
    };


    // -------------------- FETCH DATA --------------------
    const fetchData = () => {
        const formData = new FormData();
        formData.append("search", searchInput.value.trim());
        formData.append("community", filterCommunity?.value || "");
        formData.append("month", filterMonth.value);
        formData.append("year", filterYear.value);
        formData.append("page", currentPage);

        fetch("poa_records.php", { method: "POST", body: formData })
            .then(res => res.json())
            .then(json => {
                if (json.status !== "success") return;

                const dt = json.data;

                tableBodyDesktop.innerHTML = dt.table;
                tableBodyMobile.innerHTML = dt.cards;
                paginationWrap.innerHTML = dt.pagination;

                totalCertificates.textContent = dt.stats.totalCertificates;
                totalCertificatesMonth.textContent = dt.stats.totalCertificatesMonth;
                totalCertificatesYear.textContent = dt.stats.totalCertificatesYear;
                totalAmount.textContent = "₦" + dt.stats.totalAmount;

                bindCheckboxEvents();
                bindPaginationEvents();

                selectAll.checked = false;
                selectAllHeader.checked = false;
                if (!selectAllPages.checked) {
                    document.querySelectorAll(".recordCheckbox").forEach(cb => cb.checked = false);
                }
            })
            .catch(err => console.error("Fetch error:", err));
    };


    // -------------------- SEARCH HIGHLIGHT --------------------
    const highlightMatches = () => {
        const term = searchInput.value.trim().toLowerCase();
        const tds = tableBodyDesktop.querySelectorAll("td[data-original]");

        tds.forEach(td => {
            const original = td.getAttribute("data-original");
            if (!term) td.innerHTML = original;
            else {
                const index = original.toLowerCase().indexOf(term);
                if (index === -1) td.innerHTML = original;
                else {
                    const match = original.substring(index, index + term.length);
                    td.innerHTML = original.replace(match, `<mark>${match}</mark>`);
                }
            }
        });
    };


    // -------------------- PAGINATION EVENTS --------------------
    const bindPaginationEvents = () => {
        paginationWrap.querySelectorAll(".page-item").forEach(item => {
            item.addEventListener("click", e => {
                e.preventDefault();
                const page = parseInt(item.dataset.page);
                if (!isNaN(page)) {
                    currentPage = page;
                    fetchData();
                }
            });
        });
    };


    // -------------------- CHECKBOX MANAGEMENT --------------------
    const bindCheckboxEvents = () => {
        const checkboxes = document.querySelectorAll(".recordCheckbox");

        selectAllHeader.onclick = () => {
            const checked = selectAllHeader.checked;
            checkboxes.forEach(cb => cb.checked = checked);
            selectAll.checked = checked;
        };

        selectAll.onclick = () => {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            selectAllHeader.checked = selectAll.checked;
        };

        checkboxes.forEach(cb => {
            cb.addEventListener("change", () => {
                const allChecked = [...checkboxes].every(c => c.checked);
                selectAll.checked = allChecked;
                selectAllHeader.checked = allChecked;
            });
        });
    };


    // -------------------- GET SELECTED IDS --------------------
    const getSelectedIDs = () => {
        return [...document.querySelectorAll(".recordCheckbox:checked")].map(cb => cb.value);
    };


    // -------------------- BULK ACTION WITH SWEETALERT COUNT --------------------
    const runBulkAction = (action) => {
        const ids = getSelectedIDs();
        const allPages = selectAllPages.checked;

        const selectedCount = allPages ? "all records" : `${ids.length} record(s)`;

        if (!allPages && ids.length === 0) {
            Swal.fire("No Selection", "Choose at least one record.", "warning");
            return;
        }

        const confirmText = {
            delete: `Are you sure you want to delete ${selectedCount}?`,
            download: `Download ${selectedCount}?`,
            zip: `Generate a ZIP file for ${selectedCount}?`,
            csv: `Export ${selectedCount} to CSV?`
        };

        Swal.fire({
            title: "Confirm Action",
            text: confirmText[action],
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, continue!"
        }).then((result) => {
            if (!result.isConfirmed) return;

            const fd = new FormData();
            fd.append("bulk_action", action);
            if (allPages) fd.append("all_pages", "1");
            ids.forEach(id => fd.append("ids[]", id));

            fetch("poa_records.php", { method: "POST", body: fd })
                .then(res => res.json())
                .then(json => {
                    if (json.status !== "success") return;

                    const msg = {
                        delete: "Records deleted successfully!",
                        download: "Download started.",
                        zip: "ZIP file generated.",
                        csv: "CSV exported."
                    };

                    Swal.fire("Done!", msg[action], "success");

                    fetchData();
                });
        });
    };


    // -------------------- EVENT LISTENERS --------------------

    searchInput.addEventListener("input", () => {
        debounce(() => {
            currentPage = 1;
            fetchData();
            highlightMatches();
        });
    });

    [filterCommunity, filterMonth].forEach(el => {
        if (el) el.addEventListener("change", () => {
            currentPage = 1;
            fetchData();
        });
    });

    filterYear.addEventListener("input", () => {
        currentPage = 1;
        fetchData();
    });

    bulkDelete.onclick = () => runBulkAction("delete");
    bulkDownload.onclick = () => runBulkAction("download");
    bulkZip.onclick = () => runBulkAction("zip");
    exportCSV.onclick = () => runBulkAction("csv");


    // -------------------- INITIAL LOAD --------------------
    fetchData();
});
