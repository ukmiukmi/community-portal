document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.getElementById("appSidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const mainContent = document.getElementById("mainContent");
    const body = document.body;

    // ------------------ Sidebar Toggle ------------------
    document
        .getElementById("desktopCollapseToggle")
        ?.addEventListener("click", () => {
            sidebar.classList.toggle("collapsed");
            mainContent.classList.toggle("collapsed");
        });

    document.getElementById("sidebarToggle")?.addEventListener("click", () => {
        sidebar.classList.toggle("mobile-visible");
        overlay.classList.toggle("active");
    });

    overlay.addEventListener("click", () => {
        sidebar.classList.remove("mobile-visible");
        overlay.classList.remove("active");
    });

    window.addEventListener("resize", () => {
        if (window.innerWidth > 991) {
            sidebar.classList.remove("mobile-visible");
            overlay.classList.remove("active");
        }
    });

    // Scroll active submenu into view
    const activeSubmenu = document.querySelector(".submenu-link.active");
    if (activeSubmenu)
        activeSubmenu.scrollIntoView({ behavior: "smooth", block: "center" });

    // ------------------ Admin Accordion ------------------
    const adminToolsMenu = document.getElementById("adminToolsMenu");
    if (adminToolsMenu) {
        const adminPages = [
            "manage_signatures.php",
            "deleted_signatures_log.php",
            "community_branding.php",
            "manage_users.php",
            "manage_community.php",
            "manage_villages.php",
        ];
        const currentPage = body.getAttribute("data-current-page");
        if (adminPages.includes(currentPage)) {
            new bootstrap.Collapse(adminToolsMenu, { show: true });
        }
    }

    // ------------------ Theme Toggler ------------------
    const themeToggler = document.getElementById("themeToggler");
    let savedTheme = localStorage.getItem("theme") || "dark";
    body.classList.remove("dark-theme", "light-theme");
    body.classList.add(savedTheme + "-theme");

    themeToggler?.addEventListener("click", () => {
        let currentTheme = body.classList.contains("dark-theme")
            ? "dark"
            : "light";
        let newTheme = currentTheme === "dark" ? "light" : "dark";
        body.classList.add("theme-switching");
        setTimeout(() => body.classList.remove("theme-switching"), 800);
        body.classList.remove(currentTheme + "-theme");
        body.classList.add(newTheme + "-theme");
        localStorage.setItem("theme", newTheme);
    });

    // ------------------ Access Denied Alert + Alarm ------------------
    const userRole = body.getAttribute("data-user-role");
    const currentPage = body.getAttribute("data-current-page");
    const adminPages = [
        "manage_signatures.php",
        "deleted_signatures_log.php",
        "community_branding.php",
        "manage_users.php",
        "manage_community.php",
        "manage_villages.php",
    ];

    // Create a short beep for alarm
    function playAlarm() {
        const audioCtx = new (window.AudioContext ||
            window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        oscillator.type = "sine";
        oscillator.frequency.setValueAtTime(440, audioCtx.currentTime); // A4 beep
        gainNode.gain.setValueAtTime(0.2, audioCtx.currentTime); // soft volume
        oscillator.start();
        oscillator.stop(audioCtx.currentTime + 0.25); // 250ms beep
    }

    // Check direct URL or unauthorized admin page access
    if (
        (userRole === "guest" && currentPage !== "login.php") ||
        (userRole !== "admin" && adminPages.includes(currentPage))
    ) {
        playAlarm();
        Swal.fire({
            icon: "warning",
            title: "Access Denied",
            text: "You do not have permission to access this page!",
            confirmButtonColor: "#4d63ff",
        }).then(() => {
            window.location.href = "dashboard.php";
        });
        mainContent.classList.add("shake");
        setTimeout(() => mainContent.classList.remove("shake"), 600);
    }

    // Intercept clicks on sidebar admin links for non-admins
    const allLinks = document.querySelectorAll(".submenu-link, .nav-link");
    allLinks.forEach((link) => {
        link.addEventListener("click", (e) => {
            const href = link.getAttribute("href");
            if (href && adminPages.includes(href) && userRole !== "admin") {
                e.preventDefault();
                playAlarm();
                Swal.fire({
                    icon: "warning",
                    title: "Access Denied",
                    text: "You do not have permission to access this page!",
                    confirmButtonColor: "#4d63ff",
                });
                link.classList.add("shake");
                setTimeout(() => link.classList.remove("shake"), 600);
            }
        });
    });
});
