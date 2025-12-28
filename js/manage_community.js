// =====================================
// TOAST (GLOBAL)
// =====================================
const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
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
// IMAGE PREVIEW HANDLER
// =====================================
function setupImagePreview(nameSelector, previews) {
    previews.forEach((p) => {
        $(document).on("change", p.input, function () {
            const file = this.files[0];
            const $img = $(p.preview);
            if (!file || !file.type.startsWith("image/")) {
                $img.attr("src", "");
                return;
            }
            const oldSrc = $img.attr("src");
            if (oldSrc && oldSrc.startsWith("blob:"))
                URL.revokeObjectURL(oldSrc);
            $img.attr("src", URL.createObjectURL(file));
        });
    });

    $(document).on("input", nameSelector, function () {
        const name = $(this)
            .val()
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9_-]/g, "_");
        previews.forEach((p) => {
            const $img = $(p.preview);
            if ($img.attr("src"))
                $img.attr("data-preview-name", `${name}_${p.type}.png`);
        });
    });
}

// Add modal previews
setupImagePreview("input[name='name']", [
    { input: "#add_logo", preview: "#addLogoPreview", type: "logo" },
    { input: "#add_coat", preview: "#addCoatPreview", type: "coat_of_arms" },
    { input: "#add_stamp", preview: "#addStampPreview", type: "stamp" },
]);

setupImagePreview("#edit_name", [
    { input: "#edit_logo", preview: "#editLogoPreview", type: "logo" },
    { input: "#edit_coat", preview: "#editCoatPreview", type: "coat_of_arms" },
    { input: "#edit_stamp_input", preview: "#editStampPreview", type: "stamp" },
]);

// =====================================
// OPEN ADD MODAL
// =====================================
$(document).on("click", "#openAddCommunityModal", function () {
    $("#addCommunityForm")[0].reset();
    $("#addLogoPreview, #addCoatPreview, #addStampPreview").attr("src", "");
    $("#addCommunityModal").modal("show");
});

// =====================================
// ADD COMMUNITY AJAX
// =====================================
$("#addCommunityForm").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append("create", 1);

    $.ajax({
        url: "manage_community.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
    })
        .done((res) => {
            if (res.success) {
                $("#addCommunityModal").modal("hide");
                reloadCommunitiesTable(res.community.id, 1, true);
                Toast.fire({
                    icon: "success",
                    title: `Community "${res.community.name}" added`,
                });
            } else {
                Toast.fire({
                    icon: "error",
                    title: res.errors?.join("<br>") || "Failed to add",
                });
            }
        })
        .fail(() => Toast.fire({ icon: "error", title: "Network error" }));
});

// =====================================
// OPEN EDIT MODAL
// =====================================
function openEditModal(id) {
    $.getJSON("manage_community.php", { action: "fetch", id })
        .done((res) => {
            if (!res || !res.id) {
                Toast.fire({ icon: "error", title: "Unable to load data" });
                return;
            }

            $("#edit_id").val(res.id);
            $("#edit_name").val(res.name);
            $("#edit_slug").val(res.slug);
            $("#edit_motto").val(res.motto);
            $("#edit_description").val(res.description);

            $("#editLogoPreview").attr("src", res.logo ? "../" + res.logo : "");
            $("#editCoatPreview").attr(
                "src",
                res.coat_of_arms ? "../" + res.coat_of_arms : ""
            );
            $("#editStampPreview").attr(
                "src",
                res.stamp ? "../" + res.stamp : ""
            );

            $("#editCommunityModal").modal("show");
        })
        .fail(() => Toast.fire({ icon: "error", title: "Network error" }));
}

// =====================================
// UPDATE COMMUNITY AJAX
// =====================================
$("#editCommunityForm").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    $.ajax({
        url: "manage_community.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
    })
        .done((res) => {
            if (res.success) {
                $("#editCommunityModal").modal("hide");
                reloadCommunitiesTable(res.community.id, null, true);
                Toast.fire({
                    icon: "success",
                    title: `Community "${res.community.name}" updated`,
                });
            } else {
                Toast.fire({
                    icon: "error",
                    title: res.errors?.join("<br>") || "Update failed",
                });
            }
        })
        .fail(() => Toast.fire({ icon: "error", title: "Network error" }));
});

// =====================================
// DELETE COMMUNITY WITH FADE
// =====================================
function deleteCommunity(id, name) {
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
            "manage_community.php",
            { delete_id: id },
            (res) => {
                if (res.success) {
                    const row = $(`#communityRow${id}`);
                    const card = $(`#communityCard${id}`);
                    row.addClass("fade-out-deleted");
                    card.addClass("fade-out-deleted");

                    setTimeout(() => {
                        row.remove();
                        card.remove();
                    }, 500);

                    Toast.fire({ icon: "success", title: `Deleted "${name}"` });
                } else {
                    Toast.fire({ icon: "error", title: "Delete failed" });
                }
            },
            "json"
        );
    });
}

// =====================================
// RELOAD TABLE (HIGHLIGHT NEW/UPDATED)
// =====================================
function reloadCommunitiesTable(
    highlightId = null,
    page = 1,
    smoothScroll = false
) {
    const search = $("#communitySearch").val().trim();

    $.get("manage_community.php", { ajax_reload: 1, page, search }, (html) => {
        const parsed = $("<div>").html(html);

        $("#communityTableBody").html(
            parsed.find("#communityTableBody").html() || ""
        );
        $("#communityCards").html(parsed.find("#communityCards").html() || "");
        $("#desktopPagination").html(
            parsed.find("#desktopPagination").html() || ""
        );
        $("#mobilePagination").html(
            parsed.find("#mobilePagination").html() || ""
        );

        if (highlightId) {
            const row = $(`#communityRow${highlightId}`);
            const card = $(`#communityCard${highlightId}`);
            row.addClass("highlight-new");
            card.addClass("highlight-new");

            if (smoothScroll) {
                const offset = row.length
                    ? row.offset().top
                    : card.offset().top;
                $("html, body").animate({ scrollTop: offset - 100 }, 300);
            }

            setTimeout(() => {
                row.removeClass("highlight-new");
                card.removeClass("highlight-new");
            }, 2000);
        }

        if (search) highlightSearch();
    });
}

// =====================================
// HIGHLIGHT SEARCH
// =====================================
function highlightSearch() {
    const search = $("#communitySearch").val().trim();
    if (!search) {
        $(".searchable").each(function () {
            $(this).html($(this).text());
        });
        return;
    }

    const regex = new RegExp(
        `(${search.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")})`,
        "gi"
    );
    $(".searchable").each(function () {
        const original = $(this).text();
        $(this).html(
            original.replace(regex, '<span class="highlighted">$1</span>')
        );
    });

    setTimeout(() => $(".highlighted").addClass("fade-out"), 2000);
    $(".highlighted").on("transitionend", function () {
        $(this).removeClass("highlighted fade-out");
    });
}

// =====================================
// SEARCH INPUT
// =====================================
$("#communitySearch").on(
    "input",
    debounce(() => {
        reloadCommunitiesTable(null, 1);
        highlightSearch();
    }, 300)
);

// =====================================
// PAGINATION
// =====================================
$(document).on("click", ".page-link", function (e) {
    e.preventDefault();
    const page = $(this).data("page");
    if (page) reloadCommunitiesTable(null, page);
});

// =====================================
// GLOBAL BUTTON HANDLERS
// =====================================
$(document).on("click", ".editBtn", function () {
    openEditModal($(this).data("id"));
});

$(document).on("click", ".deleteBtn", function () {
    const id = $(this).data("id");
    const name = $(this)
        .closest("tr, .community-card")
        .find("h5, td:nth-child(3)")
        .text()
        .trim();
    deleteCommunity(id, name);
});
