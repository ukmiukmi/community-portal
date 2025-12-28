$(function () {
    let CURRENT_PAGE = 1;
    const PER_PAGE = 50;
    let SEARCH = "";
    let THREAT = "";
    let ALL_PAGES_SELECTED = false;

    const toast = (icon, text) => {
        Swal.fire({
            toast: true,
            position: "top-end",
            icon,
            title: text,
            showConfirmButton: false,
            timer: 2600,
            timerProgressBar: true,
        });
    };

    const selectedIds = () =>
        $(".logCheckbox:checked")
            .map((_, e) => $(e).data("id"))
            .get();

    const highlightText = (text) => {
        if (!SEARCH) return text;
        return text.replace(
            new RegExp(`(${SEARCH})`, "gi"),
            '<b class="text-warning">$1</b>'
        );
    };

    function applyHighlight() {
        $(".highlight-target").each(function () {
            let text = $(this).text();
            if (SEARCH) {
                const regex = new RegExp(`(${SEARCH})`, "gi");
                $(this).html(
                    text.replace(regex, '<b class="text-warning">$1</b>')
                );
            } else {
                $(this).text(text);
            }
        });
    }

    function updateActionButtons() {
        if (SHOW_DELETED) {
            $("#restoreBtn").show();
            $("#deleteBtn").hide();
            $("#showDeletedBtn").text("Show Active");
        } else {
            $("#restoreBtn").hide();
            $("#deleteBtn").show();
            $("#showDeletedBtn").text("Show Deleted");
        }
    }

    function initMaps() {
        $(".log-map").each(function () {
            const lat = parseFloat($(this).data("lat"));
            const lon = parseFloat($(this).data("lon"));
            if (!lat || !lon) return;

            const map = L.map(this).setView([lat, lon], 10);
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 18,
            }).addTo(map);
            L.marker([lat, lon]).addTo(map);
        });
    }

    function loadLogs(page = 1) {
        CURRENT_PAGE = page;
        $.get(
            "access_logs_data.php",
            {
                page,
                per_page: PER_PAGE,
                search: SEARCH,
                threat: THREAT,
                show_deleted: SHOW_DELETED,
            },
            (res) => {
                const data = JSON.parse(res);
                $("#logsTable tbody").html(data.html);
                $("#pagination").html(data.pagination);

                $("#selectAll").prop("checked", false).prop("disabled", false);
                if (ALL_PAGES_SELECTED) {
                    $(".logCheckbox").prop("checked", true);
                    $("#selectAll")
                        .prop("checked", true)
                        .prop("disabled", true);
                }

                updateActionButtons();
                initMaps();
                applyHighlight();

                // Mark deleted rows and add badge
                $("#logsTable tbody tr").each(function () {
                    if (SHOW_DELETED) {
                        $(this).addClass("deleted-row");
                        const firstTd = $(this).find("td:first");
                        if (!firstTd.find(".deleted-badge").length) {
                            firstTd.append(
                                ' <span class="badge bg-danger deleted-badge">Deleted</span>'
                            );
                        }
                    } else {
                        $(this).removeClass("deleted-row");
                        $(this).find(".deleted-badge").remove();
                    }
                });
            }
        );
    }

    loadLogs();

    // Search input
    let timer;
    $("#searchLogs").on("input", function () {
        clearTimeout(timer);
        timer = setTimeout(() => {
            SEARCH = $(this).val().trim();
            loadLogs(1);
        }, 400);
    });

    // Threat filter
    $("#threatFilter").on("change", function () {
        THREAT = $(this).val();
        loadLogs(1);
    });

    // Pagination
    $(document).on("click", ".page-link[data-page]", function (e) {
        e.preventDefault();
        loadLogs($(this).data("page"));
    });

    // Show Deleted / Active toggle
    $("#showDeletedBtn").on("click", () => {
        SHOW_DELETED = SHOW_DELETED ? 0 : 1;
        CURRENT_PAGE = 1;
        ALL_PAGES_SELECTED = false;
        $("#selectAll").prop("checked", false).prop("disabled", false);
        loadLogs(1);
    });

    // Checkbox selection
    $(document).on("change", "#selectAll", function () {
        if ($(this).is(":disabled")) return;
        $(".logCheckbox").prop("checked", this.checked);
    });

    $(document).on("change", ".logCheckbox", function () {
        $("#selectAll").prop(
            "checked",
            $(".logCheckbox:checked").length === $(".logCheckbox").length
        );
    });

    $("#selectCurrent").on("click", () => {
        ALL_PAGES_SELECTED = false;
        $("#selectAll").prop("disabled", false);
        $(".logCheckbox").each(function () {
            $(this).prop("checked", !$(this).prop("checked"));
        });
        $("#selectAll").prop(
            "checked",
            $(".logCheckbox:checked").length === $(".logCheckbox").length
        );
    });

    $("#selectAllPages").on("click", () => {
        ALL_PAGES_SELECTED = !ALL_PAGES_SELECTED;
        $(".logCheckbox").prop("checked", ALL_PAGES_SELECTED);
        $("#selectAll")
            .prop("checked", ALL_PAGES_SELECTED)
            .prop("disabled", ALL_PAGES_SELECTED);
    });

    // Bulk Delete
    $("#deleteBtn").on("click", () => {
        const ids = selectedIds();
        if (!ids.length) return toast("warning", "No selection");
        Swal.fire({
            title: "Delete selected logs?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Delete",
        }).then((r) => {
            if (!r.isConfirmed) return;
            $.post(
                "access_logs.php",
                { action: "delete_logs", log_ids: ids, csrf_token: CSRF_TOKEN },
                (res) => {
                    const j = JSON.parse(res);
                    if (j.success) {
                        toast("success", "Deleted successfully");
                        loadLogs(CURRENT_PAGE);
                    } else toast("error", "Failed to delete");
                }
            );
        });
    });

    // Bulk Restore
    $("#restoreBtn").on("click", () => {
        const ids = selectedIds();
        if (!ids.length) return toast("warning", "No selection");

        Swal.fire({
            title: "Restore selected logs?",
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Restore",
        }).then((r) => {
            if (!r.isConfirmed) return;

            $.post(
                "access_logs.php",
                {
                    action: "restore_logs",
                    log_ids: ids,
                    csrf_token: CSRF_TOKEN,
                },
                (res) => {
                    const j = JSON.parse(res);
                    if (j.success) {
                        toast("success", "Restored successfully");

                        ids.forEach((id) => {
                            const row = $("#logRow-" + id);

                            if (SHOW_DELETED) {
                                // Remove restored row from deleted table
                                row.fadeOut(300, () => row.remove());
                            } else {
                                // Remove deleted styling
                                row.removeClass("deleted-row");
                                row.find(".deleted-badge").remove();
                            }
                        });

                        $(".logCheckbox").prop("checked", false);
                        $("#selectAll")
                            .prop("checked", false)
                            .prop("disabled", false);
                    } else toast("error", "Failed to restore");
                }
            );
        });
    });

    // Row delete
    $(document).on("click", ".row-delete", function () {
        const id = $(this).data("id");
        Swal.fire({
            title: "Delete this log?",
            icon: "warning",
            showCancelButton: true,
        }).then((r) => {
            if (!r.isConfirmed) return;
            $.post(
                "access_logs.php",
                {
                    action: "delete_logs",
                    log_ids: [id],
                    csrf_token: CSRF_TOKEN,
                },
                () => {
                    $("#logRow-" + id).fadeOut(200);
                    toast("success", "Deleted successfully");
                }
            );
        });
    });

    // Export CSV
    $("#exportBtn").on("click", () => {
        const ids = selectedIds();
        if (!ids.length) return toast("warning", "Select logs");

        const headers = [];
        $("#logsTable thead th").each(function () {
            headers.push($(this).text().trim());
        });

        const rows = [];
        $(".logCheckbox:checked").each(function () {
            const tr = $(this).closest("tr");
            const row = [];
            tr.find("td").each(function () {
                row.push('"' + $(this).text().trim().replace(/"/g, '""') + '"');
            });
            rows.push(row.join(","));
        });

        const csvContent = [headers.join(","), ...rows].join("\n");
        const blob = new Blob([csvContent], {
            type: "text/csv;charset=utf-8;",
        });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `access_logs_${Date.now()}.csv`;
        link.click();
        toast("success", "CSV exported successfully");
    });
});
