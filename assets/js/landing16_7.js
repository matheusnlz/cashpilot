(() => {
    "use strict";

    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    if ("IntersectionObserver" in window && !reducedMotion.matches) {
        const elements = document.querySelectorAll(".reveal");
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("is-visible");
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.08 },
        );

        elements.forEach((element) => observer.observe(element));
        document.documentElement.classList.add("motion-ready");
        reducedMotion.addEventListener("change", (event) => {
            if (event.matches) {
                document.documentElement.classList.remove("motion-ready");
                observer.disconnect();
            }
        });
    }
})();
