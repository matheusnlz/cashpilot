(function () {

            const body = document.body;

            const header = document.getElementById('landingHeader');

            const menu = document.getElementById('landingMenu');

            const overlay = document.getElementById('landingMenuOverlay');

            const openButton = document.getElementById('landingMenuButton');

            const closeButton = document.getElementById('landingMenuClose');

            function openMenu() {

                        menu.classList.add('active');

                        overlay.classList.add('active');

                        body.classList.add('landing-menu-open');

                        menu.setAttribute('aria-hidden', 'false');

                        openButton.setAttribute('aria-expanded', 'true');

    }

            function closeMenu() {

                        menu.classList.remove('active');

                        overlay.classList.remove('active');

                        body.classList.remove('landing-menu-open');

                        menu.setAttribute('aria-hidden', 'true');

                        openButton.setAttribute('aria-expanded', 'false');

    }

            openButton?.addEventListener('click', openMenu);

            closeButton?.addEventListener('click', closeMenu);

            overlay?.addEventListener('click', closeMenu);

            document.addEventListener('keydown', (event) => {

                        if (event.key === 'Escape') {

                                    closeMenu();

        }

    });

            menu?.querySelectorAll('a[href^="#"]').forEach((link) => {

                        link.addEventListener('click', closeMenu);

    });

            function updateHeader() {

                        header?.classList.toggle('scrolled', window.scrollY > 20);

    }

            window.addEventListener('scroll', updateHeader, {

                 passive: true

    });

            updateHeader();

            const revealItems = document.querySelectorAll('.landing-reveal');

            if ('IntersectionObserver' in window) {

                        const observer = new IntersectionObserver((entries) => {

                                    entries.forEach((entry) => {

                                                if (entry.isIntersecting) {

                                                            entry.target.classList.add('visible');

                                                            observer.unobserve(entry.target);

                }

            });

        }, {

                                    threshold: 0.12,
                                    rootMargin: '0px 0px -40px 0px',

        });

                        revealItems.forEach((item, index) => {

                                    item.style.transitionDelay =
                                        `${Math.min((index % 4) * 60, 180)}ms`;

                                    observer.observe(item);

        });

    }
            else {

                        revealItems.forEach((item) => {

                                    item.classList.add('visible');

        });

    }

            const tabs = document.querySelectorAll('[data-profile]');

            const panels = document.querySelectorAll('[data-profile-panel]');

            function activateProfile(profile) {

                        tabs.forEach((tab) => {

                                    tab.classList.toggle('active', tab.dataset.profile === profile);

        });

                        panels.forEach((panel) => {

                                    const active = panel.dataset.profilePanel === profile;

                                    panel.hidden = !active;

                                    panel.classList.toggle('active', active);

                                    if (active) {

                                                panel.animate([
                                                    {

                                         opacity: 0, transform: 'translateY(8px)'

                },
                                                    {

                                         opacity: 1, transform: 'translateY(0)'

                },
                                                ], {

                                                            duration: 240,
                                                            easing: 'ease-out',

                });

            }

        });

    }

            tabs.forEach((tab) => {

                        tab.addEventListener('click', () => {

                                    activateProfile(tab.dataset.profile);

        });

    });

            document.querySelectorAll('a[href^="#"]').forEach((link) => {

                        link.addEventListener('click', (event) => {

                                    const targetId = link.getAttribute('href');

                                    if (!targetId || targetId === '#') {

                                                return;

            }

                                    const target = document.querySelector(targetId);

                                    if (!target) {

                                                return;

            }

                                    event.preventDefault();

                                    const offset = 74;

                                    const top = target.getBoundingClientRect().top +
                                        window.scrollY -
                                        offset;

                                    window.scrollTo( {

                                                top,
                                                behavior: 'smooth',

            });

        });

    });

})();
