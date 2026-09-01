(function () {
    'use strict';

    const groups = document.getElementById('cpMobileGroups');
    const layer = document.getElementById('cpMobileSubnavLayer');

    function closeRowMenus(except) {
        document
            .querySelectorAll('.row-actions.cp-mobile-actions-open')
            .forEach((actions) => {
                if (actions === except) {
                    return;
                }

                actions.classList.remove('cp-mobile-actions-open');
                actions
                    .querySelector('.cp-mobile-row-menu')
                    ?.setAttribute('aria-expanded', 'false');
            });
    }

    function prepareResponsiveLists() {
        document.querySelectorAll('.responsive-list').forEach((list) => {
            const headerCells = Array.from(
                list.querySelectorAll('.table-head > *')
            );

            const labels = headerCells.map((cell) =>
                (cell.textContent || '').trim()
            );

            list
                .querySelectorAll('.table-row:not(.table-head)')
                .forEach((row) => {
                    const cells = Array.from(row.children);

                    cells.forEach((cell, index) => {
                        if (cell.classList.contains('row-actions')) {
                            return;
                        }

                        cell.classList.add('cp-mobile-cell');

                        if (labels[index]) {
                            cell.dataset.mobileLabel = labels[index];
                        }

                        if (cell.classList.contains('money-cell')) {
                            cell.classList.add('cp-mobile-value');
                            row.classList.add('cp-mobile-has-value');
                        }
                    });

                    const actions = row.querySelector('.row-actions');

                    if (
                        !actions ||
                        actions.querySelector('.cp-mobile-row-menu')
                    ) {
                        return;
                    }

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'cp-mobile-row-menu';
                    button.setAttribute('aria-label', 'Opções desta movimentação');
                    button.setAttribute('aria-expanded', 'false');
                    button.textContent = '⋮';

                    actions.prepend(button);

                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();

                        closeRowMenus(actions);

                        const open = actions.classList.toggle(
                            'cp-mobile-actions-open'
                        );

                        button.setAttribute(
                            'aria-expanded',
                            open ? 'true' : 'false'
                        );
                    });
                });
        });
    }

    function centerActiveGroup() {
        if (!groups) {
            return;
        }

        const active = groups.querySelector('.cp-mobile-group.ativo');

        if (!active) {
            return;
        }

        requestAnimationFrame(() => {
            const target =
                active.offsetLeft -
                (groups.clientWidth - active.offsetWidth) / 2;

            const max = Math.max(0, groups.scrollWidth - groups.clientWidth);

            groups.scrollTo({
                left: Math.max(0, Math.min(max, target)),
                behavior: 'auto',
            });
        });
    }

    if (groups && layer) {
        const buttons = Array.from(
            groups.querySelectorAll('[data-mobile-group]')
        );

        const panels = Array.from(
            layer.querySelectorAll('[data-mobile-panel]')
        );

        const backdrop = layer.querySelector('.cp-mobile-subnav-backdrop');

        function closeNavigation() {
            layer.hidden = true;
            document.body.classList.remove('cp-mobile-nav-open');

            buttons.forEach((button) => {
                button.setAttribute('aria-expanded', 'false');
            });

            panels.forEach((panel) => {
                panel.hidden = true;
            });
        }

        function openNavigation(button) {
            const target = button.dataset.mobileGroup;
            const panel = panels.find(
                (item) => item.dataset.mobilePanel === target
            );

            if (!panel) {
                return;
            }

            panels.forEach((item) => {
                item.hidden = item !== panel;
            });

            buttons.forEach((item) => {
                item.setAttribute(
                    'aria-expanded',
                    item === button ? 'true' : 'false'
                );
            });

            layer.hidden = false;
            document.body.classList.add('cp-mobile-nav-open');
        }

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const alreadyOpen =
                    button.getAttribute('aria-expanded') === 'true' &&
                    !layer.hidden;

                if (alreadyOpen) {
                    closeNavigation();
                    return;
                }

                openNavigation(button);
            });
        });

        backdrop?.addEventListener('click', closeNavigation);

        layer
            .querySelectorAll('.cp-mobile-subnav-close')
            .forEach((button) => {
                button.addEventListener('click', closeNavigation);
            });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeNavigation();
            }
        });

        centerActiveGroup();
    }

    prepareResponsiveLists();

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.row-actions')) {
            closeRowMenus();
        }
    });
})();
