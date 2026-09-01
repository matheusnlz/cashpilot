(function () {
    'use strict';

    const drawerOverlay = document.getElementById('drawerOverlay');
    const confirmOverlay = document.getElementById('cpConfirmOverlay');
    const confirmTitle = document.getElementById('cpConfirmTitle');
    const confirmMessage = document.getElementById('cpConfirmMessage');
    const confirmCancel = document.getElementById('cpConfirmCancel');
    const confirmAccept = document.getElementById('cpConfirmAccept');

    let confirmAction = null;

    function closeDrawers() {
        document
            .querySelectorAll('.cp-drawer.aberto')
            .forEach((drawer) => drawer.classList.remove('aberto'));

        drawerOverlay?.classList.remove('ativo');
        document.body.classList.remove('drawer-aberto');
    }

    function openDrawer(id) {
        const drawer = document.getElementById(id);

        if (!drawer) {
            return;
        }

        document
            .querySelectorAll('.cp-drawer.aberto')
            .forEach((item) => item.classList.remove('aberto'));

        drawer.classList.add('aberto');
        drawerOverlay?.classList.add('ativo');
        document.body.classList.add('drawer-aberto');

        window.setTimeout(() => {
            drawer
                .querySelector('input:not([type="hidden"]), select, textarea')
                ?.focus({ preventScroll: true });
        }, 180);
    }

    function openConfirmation(options) {
        if (
            !confirmOverlay ||
            !confirmTitle ||
            !confirmMessage ||
            !confirmAccept
        ) {
            if (typeof options.action === 'function') {
                options.action();
            }
            return;
        }

        confirmTitle.textContent = options.title || 'Confirmar ação';
        confirmMessage.textContent = options.message || 'Deseja continuar?';
        confirmAccept.textContent = options.confirmText || 'Confirmar';
        confirmAccept.classList.toggle('btn-perigo', options.danger !== false);
        confirmAccept.classList.toggle('btn-primario', options.danger === false);
        confirmAction = options.action || null;

        confirmOverlay.hidden = false;
        document.body.classList.add('cp-modal-open');

        requestAnimationFrame(() => {
            confirmOverlay.classList.add('aberto');
        });
    }

    function closeConfirmation() {
        if (!confirmOverlay) {
            return;
        }

        confirmOverlay.classList.remove('aberto');
        document.body.classList.remove('cp-modal-open');
        confirmAction = null;

        window.setTimeout(() => {
            confirmOverlay.hidden = true;
        }, 170);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const initialDrawer = document.querySelector('.cp-drawer.aberto');

        if (initialDrawer) {
            drawerOverlay?.classList.add('ativo');
            document.body.classList.add('drawer-aberto');
        }

        document
            .querySelectorAll('form[onsubmit*="confirm("]')
            .forEach((form) => {
                const original = form.getAttribute('onsubmit') || '';
                const match = original.match(/confirm\(['"](.+?)['"]\)/);

                if (match) {
                    form.dataset.confirm = match[1];
                }

                form.removeAttribute('onsubmit');
            });

        document.querySelectorAll('.alerta-mensagem').forEach((alert) => {
            alert.classList.add('cp-toast');

            window.setTimeout(() => {
                alert.classList.add('cp-toast-hide');
            }, 3300);

            window.setTimeout(() => {
                alert.remove();
            }, 3650);
        });
    });

    document.addEventListener('click', (event) => {
        const drawerButton = event.target.closest('[data-drawer-open]');

        if (drawerButton) {
            event.preventDefault();
            event.stopPropagation();
            openDrawer(drawerButton.dataset.drawerOpen);
            return;
        }

        const drawerClose = event.target.closest('[data-drawer-close]');

        if (drawerClose) {
            event.preventDefault();
            event.stopPropagation();
            closeDrawers();
            return;
        }

        const confirmLink = event.target.closest('[data-confirm-link]');

        if (confirmLink) {
            event.preventDefault();
            event.stopPropagation();

            openConfirmation({
                title: confirmLink.dataset.confirmTitle || 'Sair do CashPilot?',
                message:
                    confirmLink.dataset.confirmMessage ||
                    'Deseja continuar?',
                confirmText: 'Sair',
                danger: true,
                action: () => {
                    window.location.href = confirmLink.dataset.confirmLink;
                },
            });
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-confirm]');

        if (!form || form.dataset.confirmBypass === '1') {
            return;
        }

        event.preventDefault();

        const text = form.dataset.confirm || 'Confirmar ação';

        openConfirmation({
            title: text,
            message:
                form.dataset.confirmMessage ||
                'Essa ação será aplicada imediatamente.',
            confirmText: /excluir|desativar|arquivar/i.test(text)
                ? 'Confirmar'
                : 'Continuar',
            danger: true,
            action: () => {
                form.dataset.confirmBypass = '1';

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            },
        });
    });

    drawerOverlay?.addEventListener('click', closeDrawers);
    confirmCancel?.addEventListener('click', closeConfirmation);

    confirmOverlay?.addEventListener('click', (event) => {
        if (event.target === confirmOverlay) {
            closeConfirmation();
        }
    });

    confirmAccept?.addEventListener('click', () => {
        const action = confirmAction;
        closeConfirmation();

        if (typeof action === 'function') {
            window.setTimeout(action, 20);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        closeDrawers();
        closeConfirmation();
    });
})();
