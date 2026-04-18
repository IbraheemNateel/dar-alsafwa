document.addEventListener("DOMContentLoaded", () => {
    // Check if IntersectionObserver is supported
    if (!('IntersectionObserver' in window)) {
        return; // Fallback to no animations if not supported
    }

    const observerOptions = {
        threshold: 0.1, // Trigger when 10% of the element is visible
        rootMargin: "0px 0px -50px 0px" // Trigger slightly before it comes into view entirely
    };

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                obs.unobserve(entry.target); // Animate only once
            }
        });
    }, observerOptions);

    // 1. Reveal on scroll (slide up)
    const fadeUpElements = document.querySelectorAll('.stat-card, .info-card, .action-card, .content-section, .data-table-wrapper, .unread-alert, .dashboard-layout > div, .form-card');
    fadeUpElements.forEach(el => {
        el.classList.add('reveal-on-scroll');
        observer.observe(el);
    });

    // 2. Reveal pop (scale up for headers)
    const popElements = document.querySelectorAll('.page-header, .hero-section');
    popElements.forEach(el => {
        el.classList.add('reveal-pop');
        observer.observe(el);
    });

    // 3. Staggered reveal for table rows or list items
    const rows = document.querySelectorAll('.data-table tbody tr, .dashboard-table tbody tr');
    rows.forEach((row, index) => {
        row.classList.add('reveal-stagger');
        // We use a modular delay so that if a table has 50 rows, it doesn't wait 5 seconds.
        row.style.transitionDelay = `${(index % 12) * 0.05}s`;
        observer.observe(row);
    });
});
