// ================================
// Animate counter with easing
// ================================
function animateCounter(counter, newTarget) {
    const start = parseInt(counter.textContent || 0, 10);
    const change = newTarget - start;
    const duration = 800; // animation duration in ms
    let startTime = null;

    // Ease out cubic function
    function easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }

    function update(timestamp) {
        if (!startTime) startTime = timestamp;
        const elapsed = timestamp - startTime;
        const progress = Math.min(elapsed / duration, 1); // 0 → 1
        const eased = easeOutCubic(progress);
        const value = Math.round(start + change * eased);
        counter.textContent = value;

        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }

    requestAnimationFrame(update);
}

// ================================
// Animate counters on page load
// ================================
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".counter").forEach((counter) => {
        const target = parseInt(counter.dataset.count || 0, 10);
        animateCounter(counter, target);
    });

    // Auto-refresh every 10 seconds
    setInterval(refreshDashboard, 10000);
});

// ================================
// Refresh Citizens counters dynamically
// ================================
function refreshDashboard() {
    fetch("my_citizens.php?action=get_counts")
        .then((res) => res.json())
        .then((data) => {
            document.querySelectorAll(".counter").forEach((counter) => {
                const key = counter.dataset.key; // "citizens", "indigenes", "tenants"
                const newCount = parseInt(data[key] || 0, 10);

                if (parseInt(counter.dataset.count || 0, 10) !== newCount) {
                    counter.dataset.count = newCount; // update data-count
                    animateCounter(counter, newCount);
                }
            });
        })
        .catch((err) => console.error("Failed to refresh dashboard:", err));
}
