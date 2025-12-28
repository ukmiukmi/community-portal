// =====================================
// TOAST (GLOBAL)
// =====================================
const Toast = Swal.mixin({
    toast: true,
    position: "top",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    customClass: {
        container: "toast-container",
    },
});

// =====================================
// DEBOUNCE
// =====================================
function debounce(fn, delay = 300) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), delay);
    };
}

// =====================================
// RELOAD TABLE/CARDS
// =====================================
function reloadVillagesTable(highlightId = null, page = 1, search = null) {
    search = search ?? $("#villageSearch").val().trim();
    const community = $("#communityFilter").val() || "";

    $.get(
        "manage_villages.php",
        { ajax_reload: 1, page: page, search: search, community: community },
        (html) => {
            const parsed = $("<div>").html(html);

            const tbody = parsed.find("#villageTableBody").html();
            if (tbody !== undefined) $("#villageTableBody").html(tbody);

            const cards = parsed.find("#villageCards").html();
            if (cards !== undefined) $("#villageCards").html(cards);

            const desktopPagination = parsed
                .find("#desktopPaginationVillage")
                .html();
            if (desktopPagination !== undefined)
                $("#desktopPaginationVillage").html(desktopPagination);

            const mobilePagination = parsed
                .find("#mobilePaginationVillage")
                .html();
            if (mobilePagination !== undefined)
                $("#mobilePaginationVillage").html(mobilePagination);

            if (highlightId) highlightRowCard(highlightId);
            highlightSearchWords(search);

            fetchTotals(); // update counters dynamically
        }
    );
}

// =====================================
// HIGHLIGHT ROW/CARD
// =====================================
function highlightRowCard(id) {
    const row = $(`#villageTableBody tr[data-id='${id}']`);
    const card = $(`.village-card[data-id='${id}']`);

    row.addClass("table-success");
    card.addClass("border-success shadow");

    setTimeout(() => {
        row.removeClass("table-success");
        card.removeClass("border-success shadow");
    }, 2000);
}

// =====================================
// SEARCH HIGHLIGHT
// =====================================
function highlightSearchWords(search) {
    if (!search) return;

    $(".searchable").each(function () {
        const regex = new RegExp(`(${search})`, "gi");
        $(this).html(
            $(this)
                .text()
                .replace(regex, `<span class="search-highlight">$1</span>`)
        );
    });
}

// =====================================
// LIVE SEARCH
// =====================================
$("#villageSearch").on(
    "input",
    debounce(() => reloadVillagesTable(null, 1), 300)
);

// =====================================
// COMMUNITY FILTER
// =====================================
$("#communityFilter").on("change", function () {
    reloadVillagesTable(null, 1);
});

// =====================================
// PAGINATION FIX
// =====================================
$(document).on("click", ".v-page-link", function (e) {
    e.preventDefault(); // prevent URL jump

    const page = $(this).data("page");
    if (!page) return;

    const search = $("#villageSearch").val().trim();
    const community = $("#communityFilter").val() || "";

    reloadVillagesTable(null, page, search);
});

// =====================================
// SORTING
// =====================================
$(document).on("click", ".sortable", function () {
    const column = $(this).data("column");
    const currentOrder = $(this).data("order");
    const newOrder = currentOrder === "asc" ? "desc" : "asc";
    $(this).data("order", newOrder);

    const search = $("#villageSearch").val().trim();
    const community = $("#communityFilter").val() || "";

    $.get(
        "manage_villages.php",
        {
            ajax_reload: 1,
            search: search,
            community: community,
            sort_column: column,
            sort_order: newOrder,
        },
        (html) => {
            const parsed = $("<div>").html(html);
            const tbody = parsed.find("#villageTableBody").html();
            if (tbody !== undefined) $("#villageTableBody").html(tbody);

            const cards = parsed.find("#villageCards").html();
            if (cards !== undefined) $("#villageCards").html(cards);

            const desktopPagination = parsed
                .find("#desktopPaginationVillage")
                .html();
            if (desktopPagination !== undefined)
                $("#desktopPaginationVillage").html(desktopPagination);

            const mobilePagination = parsed
                .find("#mobilePaginationVillage")
                .html();
            if (mobilePagination !== undefined)
                $("#mobilePaginationVillage").html(mobilePagination);

            highlightSearchWords(search);
            fetchTotals();
        }
    );
});

// =====================================
// OPEN ADD MODAL
// =====================================
$(document).on("click", "#openAddVillageModal", function () {
    $("#villageForm")[0].reset();
    $("#village_id").val("");
    $("#villageModalTitle").text("Add Village");
    $("#addEditVillageModal").modal("show");
});

// =====================================
// OPEN EDIT MODAL
// =====================================
function openEditModal(id) {
    $.getJSON("manage_villages.php", { action: "fetch", id })
        .done((res) => {
            if (!res || !res.id) {
                Toast.fire({ icon: "error", title: "Unable to load data" });
                return;
            }

            $("#village_id").val(res.id);
            $("#village_name").val(res.name);
            $("#village_community").val(res.community_id);
            $("#villageModalTitle").text("Edit Village");
            $("#addEditVillageModal").modal("show");
        })
        .fail(() => Toast.fire({ icon: "error", title: "Network error" }));
}

// =====================================
// ADD / UPDATE VILLAGE
// =====================================
$("#villageForm").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append("ajax", 1);

    $.ajax({
        url: "manage_villages.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
    })
        .done((res) => {
            if (res.success) {
                $("#addEditVillageModal").modal("hide");
                reloadVillagesTable(res.village.id);

                const actionText = $("#village_id").val() ? "updated" : "added";
                Toast.fire({
                    icon: "success",
                    title: `Village "${res.village.name}" ${actionText}`,
                });
            } else {
                Toast.fire({
                    icon: "error",
                    title: res.errors?.join("<br>") || "Failed to save",
                });
            }
        })
        .fail(() => Toast.fire({ icon: "error", title: "Network error" }));
});

// =====================================
// DELETE VILLAGE
// =====================================
$(document).on("click", ".deleteBtn", function () {
    const id = $(this).data("id");
    const name = $(this)
        .closest("tr, .village-card")
        .find("td:nth-child(3), h5")
        .text()
        .trim();

    Swal.fire({
        title: `Delete "${name}"?`,
        text: "This action cannot be undone!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: "Yes, delete",
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.post(
            "manage_villages.php",
            { delete_id: id, ajax: 1 },
            (res) => {
                if (res.success) {
                    reloadVillagesTable(null, 1);
                    Toast.fire({ icon: "success", title: `Deleted "${name}"` });
                } else {
                    Toast.fire({ icon: "error", title: "Delete failed" });
                }
            },
            "json"
        );
    });
});

// =====================================
// EDIT BUTTON CLICK
// =====================================
$(document).on("click", ".editBtn", function () {
    openEditModal($(this).data("id"));
});

// =====================================
// FETCH TOTAL COUNTERS
// =====================================
function fetchTotals() {
    $.getJSON("manage_villages.php", { ajax_totals: 1 }).done((data) => {
        if (!data) return;
        animateCounter("#totalCommunitiesCounter", data.communities);
        animateCounter("#totalVillagesCounter", data.villages);
    });
}

function animateCounter(selector, target) {
    const el = $(selector);
    $({ count: 0 }).animate(
        { count: target },
        {
            duration: 1000,
            easing: "swing",
            step: function () {
                el.text(Math.floor(this.count));
            },
            complete: function () {
                el.text(target);
            },
        }
    );
}

// =====================================
// INITIAL LOAD
// =====================================
$(document).ready(function () {
    fetchTotals();
});
